<?php

namespace App\Http\Controllers;

use App\Jobs\Pangolin\FetchNewtConnections;
use App\Jobs\Pangolin\RunImport;
use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinNewtAgent;
use App\Models\PangolinNewtConnection;
use App\Models\PangolinRun;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PangolinController extends Controller
{
    public function index(Request $request)
    {
        $importRuns = PangolinRun::ofType('import')->with('user')->latest()->take(10)->get();
        $normalizeRuns = PangolinRun::ofType('normalize')->with('user')->latest()->take(10)->get();

        $newtAgents = PangolinNewtAgent::orderBy('name')->get();
        $newtConnectionRuns = PangolinRun::ofType('newt_connections')->with('user')->latest()->take(10)->get();
        $connections = $this->filteredConnections($request);

        return view('pangolin.index', compact(
            'importRuns', 'normalizeRuns', 'newtAgents', 'newtConnectionRuns', 'connections',
        ));
    }

    public function newtConnectionsFetch()
    {
        $run = PangolinRun::create([
            'type' => 'newt_connections',
            'user_id' => auth()->id(),
            'status' => 'queued',
        ]);

        FetchNewtConnections::dispatch($run);

        return redirect()->route('pangolin.index', ['tab' => 'connections'])->with('success', 'Fetch queued.');
    }

    private function filteredConnections(Request $request)
    {
        return PangolinNewtConnection::query()
            ->when($request->filled('user'), function ($q) use ($request) {
                $term = "%{$request->input('user')}%";
                $q->where(fn ($q) => $q->where('user_name', 'like', $term)->orWhere('user_email', 'like', $term));
            })
            ->when($request->filled('agent_id'), fn ($q) => $q->where('newt_agent_id', $request->input('agent_id')))
            ->when($request->filled('proto'), fn ($q) => $q->where('proto', $request->input('proto')))
            // The date inputs are calendar days as the (Athens-based) user
            // reads them, not UTC — started_at is stored/queried in UTC, so
            // a naive string comparison would be off by the UTC offset
            // (currently +3h) from what the picked date actually means
            // locally. Matches started_at_local's display conversion on
            // App\Models\PangolinNewtConnection.
            ->when($request->filled('from'), fn ($q) => $q->where(
                'started_at', '>=', Carbon::parse($request->input('from'), 'Europe/Athens')->startOfDay()->timezone('UTC'),
            ))
            ->when($request->filled('to'), fn ($q) => $q->where(
                'started_at', '<=', Carbon::parse($request->input('to'), 'Europe/Athens')->endOfDay()->timezone('UTC'),
            ))
            // id desc as a tiebreaker: started_at alone isn't unique (several
            // sessions can share the same second, see e.g. rows 89-91 in a
            // real fetch), so without it ties have no guaranteed order and
            // can appear to shuffle between page loads.
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
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
