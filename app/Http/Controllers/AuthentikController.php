<?php

namespace App\Http\Controllers;

use App\Jobs\Authentik\PollCluster;
use App\Jobs\Authentik\RunLogsFetch;
use App\Models\AuthentikLogRun;
use App\Models\AuthentikMonitorSettings;
use App\Models\AuthentikMonitorStatus;
use App\Services\Authentik\RememberedConfig;
use App\Services\Concerns\EnsuresWritableDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class AuthentikController extends Controller
{
    use EnsuresWritableDirectory;

    public function index(RememberedConfig $remembered)
    {
        $statuses = AuthentikMonitorStatus::orderBy('service')->get()->groupBy('service');
        $settings = AuthentikMonitorSettings::first();
        $logRuns = AuthentikLogRun::with('user')->latest()->take(10)->get();
        $lastConfig = $remembered->get();

        return view('authentik.index', compact('statuses', 'settings', 'logRuns', 'lastConfig'));
    }

    public function monitorData()
    {
        $statuses = AuthentikMonitorStatus::orderBy('service')->get()->groupBy('service');

        return response()->json($statuses);
    }

    public function monitorRefresh()
    {
        PollCluster::dispatch();

        return redirect()->route('authentik.index', ['tab' => 'monitor'])->with('success', 'Cluster refresh queued.');
    }

    public function monitorSettingsUpdate(Request $request)
    {
        $request->validate([
            'node_ip' => 'required|ip',
            'authentik_url' => 'nullable|url',
            'api_token' => 'nullable|string',
        ]);

        $settings = AuthentikMonitorSettings::first() ?? new AuthentikMonitorSettings();
        $settings->node_ip = $request->input('node_ip');
        $settings->authentik_url = $request->input('authentik_url') ? rtrim($request->input('authentik_url'), '/') : null;
        // Blank means "leave the current token alone" — the field is never
        // pre-filled with the decrypted value (see _monitor.blade.php), so
        // there's no other way to distinguish "didn't touch it" from
        // "wants it cleared"; clearing is intentionally not supported here.
        if ($request->filled('api_token')) {
            $settings->api_token = Crypt::encryptString($request->input('api_token'));
        }
        $settings->save();

        PollCluster::dispatch();

        return redirect()->route('authentik.index', ['tab' => 'monitor'])->with('success', 'Monitor settings saved.');
    }

    public function logsFetch(Request $request, RememberedConfig $remembered)
    {
        $request->validate([
            'config_yml' => ['required', 'string', 'max:65535', $this->validYaml()],
            'lookback_hours' => 'nullable|integer|min:1|max:168',
            'level' => 'nullable|in:error,warning,info,debug',
        ]);

        $remembered->remember($request->input('config_yml'));

        $run = AuthentikLogRun::create([
            'user_id' => auth()->id(),
            'status' => 'queued',
            'options' => [
                'lookback_hours' => $request->input('lookback_hours'),
                'level' => $request->input('level'),
            ],
        ]);

        $runDir = storage_path("app/private/authentik/runs/{$run->id}");
        // Written by both the web process (www-data, here) and the
        // queue-worker (root, when RunLogsFetch picks this up) — see
        // EnsuresWritableDirectory for why a plain mkdir(..., 0777, true)
        // isn't actually enough on its own.
        $this->ensureWritableDirectory(dirname($runDir));
        $this->ensureWritableDirectory($runDir);
        file_put_contents("{$runDir}/config.yml", $request->input('config_yml'));
        chmod("{$runDir}/config.yml", 0600);

        RunLogsFetch::dispatch($run);

        return redirect()->route('authentik.index', ['tab' => 'logs'])->with('success', 'Log fetch queued.');
    }

    public function logsDownload(AuthentikLogRun $run)
    {
        abort_unless($run->report_path && file_exists($run->report_path), 404);

        return response()->download($run->report_path);
    }

    /**
     * Shared by logsFetch() — takes the pasted config.<site>.monitor.yml as
     * plain text, so it's worth catching an obvious typo before it's handed
     * to akropolis as a real, hard-to-debug-from-a-terminal failure.
     */
    private function validYaml(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            try {
                Yaml::parse($value);
            } catch (ParseException $e) {
                $fail("That doesn't look like valid YAML: {$e->getMessage()}");
            }
        };
    }
}
