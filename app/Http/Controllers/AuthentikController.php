<?php

namespace App\Http\Controllers;

use App\Jobs\Authentik\PollCluster;
use App\Jobs\Authentik\RunLogsFetch;
use App\Models\AuthentikLogRun;
use App\Models\AuthentikMonitorStatus;
use Illuminate\Http\Request;

class AuthentikController extends Controller
{
    public function index()
    {
        $statuses = AuthentikMonitorStatus::orderBy('service')->orderBy('node_name')->get()->groupBy('service');

        $logRuns = AuthentikLogRun::with('user')->latest()->take(10)->get();

        return view('authentik.index', compact('statuses', 'logRuns'));
    }

    public function monitorData()
    {
        $statuses = AuthentikMonitorStatus::orderBy('service')->orderBy('node_name')->get()->groupBy('service');

        return response()->json($statuses);
    }

    public function monitorRefresh()
    {
        PollCluster::dispatch();

        return redirect()->route('authentik.index', ['tab' => 'monitor'])->with('success', 'Cluster refresh queued.');
    }

    public function logsFetch(Request $request)
    {
        $request->validate([
            'lookback_hours' => 'nullable|integer|min:1|max:168',
            'level' => 'nullable|in:error,warning,info,debug',
        ]);

        $run = AuthentikLogRun::create([
            'user_id' => auth()->id(),
            'status' => 'queued',
            'options' => [
                'lookback_hours' => $request->input('lookback_hours'),
                'level' => $request->input('level'),
            ],
        ]);

        RunLogsFetch::dispatch($run);

        return redirect()->route('authentik.index', ['tab' => 'logs'])->with('success', 'Log fetch queued.');
    }

    public function logsDownload(AuthentikLogRun $run)
    {
        abort_unless($run->report_path && file_exists($run->report_path), 404);

        return response()->download($run->report_path);
    }
}
