<?php

namespace App\Http\Controllers;

use App\Jobs\Pangolin\PollCluster;
use App\Jobs\Pangolin\RunImport;
use App\Jobs\Pangolin\RunLogsFetch;
use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinMonitorSettings;
use App\Models\PangolinMonitorStatus;
use App\Models\PangolinNewtAgent;
use App\Models\PangolinRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PangolinController extends Controller
{
    public function index()
    {
        $statuses = PangolinMonitorStatus::orderBy('service')->orderBy('node_name')->get()->groupBy('service');
        $settings = PangolinMonitorSettings::first();
        $newtAgents = PangolinNewtAgent::orderBy('name')->get();

        $logRuns = PangolinRun::ofType('logs')->with('user')->latest()->take(10)->get();
        $importRuns = PangolinRun::ofType('import')->with('user')->latest()->take(10)->get();
        $normalizeRuns = PangolinRun::ofType('normalize')->with('user')->latest()->take(10)->get();

        return view('pangolin.index', compact('statuses', 'settings', 'newtAgents', 'logRuns', 'importRuns', 'normalizeRuns'));
    }

    public function monitorData()
    {
        $statuses = PangolinMonitorStatus::orderBy('service')->orderBy('node_name')->get()->groupBy('service');

        return response()->json($statuses);
    }

    public function monitorRefresh()
    {
        PollCluster::dispatch();

        return redirect()->route('pangolin.index', ['tab' => 'monitor'])->with('success', 'Cluster refresh queued.');
    }

    public function monitorSettingsUpdate(Request $request)
    {
        $request->validate([
            'node_ip' => 'required|ip',
            'pangolin_url' => 'nullable|url',
            'api_key' => 'nullable|string',
        ]);

        $settings = PangolinMonitorSettings::first() ?? new PangolinMonitorSettings();
        $settings->node_ip = $request->input('node_ip');
        $settings->pangolin_url = $request->input('pangolin_url') ? rtrim($request->input('pangolin_url'), '/') : null;
        // Blank means "leave the current key alone" — the field is never
        // pre-filled with the decrypted value (see _monitor.blade.php), so
        // there's no other way to distinguish "didn't touch it" from
        // "wants it cleared"; clearing is intentionally not supported here.
        if ($request->filled('api_key')) {
            $settings->api_key = Crypt::encryptString($request->input('api_key'));
        }
        $settings->save();

        PollCluster::dispatch();

        return redirect()->route('pangolin.index', ['tab' => 'monitor'])->with('success', 'Monitor settings saved.');
    }

    public function newtAgentsStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ip' => 'required|ip|unique:pangolin_newt_agents,ip',
        ]);

        PangolinNewtAgent::create($request->only('name', 'ip'));

        PollCluster::dispatch();

        return redirect()->route('pangolin.index', ['tab' => 'monitor'])->with('success', 'Newt agent added.');
    }

    public function newtAgentsDestroy(PangolinNewtAgent $agent)
    {
        $agent->delete();

        return redirect()->route('pangolin.index', ['tab' => 'monitor'])->with('success', 'Newt agent removed.');
    }

    public function logsFetch(Request $request)
    {
        $request->validate([
            'lookback_hours' => 'nullable|integer|min:1|max:168',
            'level' => 'nullable|in:error,warning,info,debug',
        ]);

        $run = PangolinRun::create([
            'type' => 'logs',
            'user_id' => auth()->id(),
            'status' => 'queued',
            'options' => [
                'lookback_hours' => $request->input('lookback_hours'),
                'level' => $request->input('level'),
            ],
        ]);

        RunLogsFetch::dispatch($run);

        return redirect()->route('pangolin.index', ['tab' => 'logs'])->with('success', 'Log fetch queued.');
    }

    public function logsDownload(PangolinRun $run, Request $request)
    {
        abort_unless($run->type === 'logs', 404);

        $path = $request->query('file') === 'newt' ? $run->extra_path : $run->report_path;
        abort_unless($path && file_exists($path), 404);

        return response()->download($path);
    }

    public function resourcesImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
            'dry_run' => 'nullable|boolean',
        ]);

        $uploaded = $request->file('file');
        $stored = $uploaded->store('pangolin/imports');
        $inputPath = storage_path("app/private/{$stored}");

        $run = PangolinRun::create([
            'type' => 'import',
            'user_id' => auth()->id(),
            'status' => 'queued',
            'options' => [
                // The form always submits `dry_run` (a hidden 0 plus the
                // checkbox's 1) precisely so this can't silently default to
                // true when unchecked — `boolean($key, true)` would otherwise
                // treat "checkbox absent" the same as "checkbox checked".
                'dry_run' => $request->boolean('dry_run'),
                'original_filename' => $uploaded->getClientOriginalName(),
            ],
            'input_path' => $inputPath,
        ]);

        RunImport::dispatch($run);

        return redirect()->route('pangolin.index', ['tab' => 'import'])->with('success', 'Import queued.');
    }

    public function resourcesNormalize(Request $request)
    {
        $request->validate([
            'apply' => 'nullable|boolean',
            'confirm' => 'accepted_if:apply,1',
            'resource_ids' => 'nullable|string',
        ]);

        // Accepts either numeric siteResourceIds or niceIds (what the report
        // and the UI actually show) — RunNormalize resolves niceIds to
        // siteResourceIds via the Integration API before invoking the script,
        // since normalize_private_resources.py's --resource-id is numeric-only.
        $resourceIds = collect(explode(',', (string) $request->input('resource_ids')))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => $id !== '')
            ->values()
            ->all();

        $run = PangolinRun::create([
            'type' => 'normalize',
            'user_id' => auth()->id(),
            'status' => 'queued',
            'options' => [
                'apply' => $request->boolean('apply'),
                'resource_ids' => $resourceIds,
            ],
        ]);

        RunNormalize::dispatch($run);

        return redirect()->route('pangolin.index', ['tab' => 'normalize'])->with('success', 'Normalize run queued.');
    }

    public function resourcesDownload(PangolinRun $run)
    {
        abort_unless(in_array($run->type, ['import', 'normalize'], true), 404);
        abort_unless($run->report_path && file_exists($run->report_path), 404);

        return response()->download($run->report_path);
    }
}
