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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PangolinController extends Controller
{
    public function index(Request $request)
    {
        $importRuns = PangolinRun::ofType('import')->with('user')->latest()->take(10)->get();
        $normalizeRuns = PangolinRun::ofType('normalize')->with('user')->latest()->take(10)->get();

        $newtAgents = PangolinNewtAgent::orderBy('name')->get();
        $newtConnectionRuns = PangolinRun::ofType('newt_connections')->with('user')->latest()->take(10)->get();
        $connections = $this->connectionsQuery($request)->paginate(25)->withQueryString()
            // Pin the tab explicitly — the URL's ?tab= is only client-side
            // (history.replaceState) when the tab was switched by click, so
            // withQueryString() alone would link back to the Import tab.
            ->appends(['tab' => 'connections']);
        // Sourced from the connections themselves (not a live Pangolin
        // Postgres query on every page load) — same site_name values the
        // Site column already displays. A newly added agent with no fetch
        // yet just won't have a site to filter by until one runs.
        $sites = PangolinNewtConnection::whereNotNull('site_name')->distinct()->orderBy('site_name')->pluck('site_name');

        return view('pangolin.index', compact(
            'importRuns', 'normalizeRuns', 'newtAgents', 'newtConnectionRuns', 'connections', 'sites',
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

    /**
     * Shared by index() (paginated) and connectionsExport() (the full
     * matching set) — same filters apply to both, so "export" genuinely
     * means "export what you're looking at."
     */
    private function connectionsQuery(Request $request)
    {
        return PangolinNewtConnection::query()
            ->when($request->filled('user'), function ($q) use ($request) {
                $term = "%{$request->input('user')}%";
                $q->where(fn ($q) => $q->where('user_name', 'like', $term)->orWhere('user_email', 'like', $term));
            })
            ->when($request->filled('site'), fn ($q) => $q->where('site_name', $request->input('site')))
            ->when($request->filled('resource'), fn ($q) => $q->where('resource_name', 'like', "%{$request->input('resource')}%"))
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
            ->orderByDesc('id');
    }

    public function connectionsExport(Request $request)
    {
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['xlsx', 'ods'], true), 404);

        $connections = $this->connectionsQuery($request)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Newt connections');
        $sheet->fromArray(['Started (Athens time)', 'Duration', 'Who', 'Client', 'Site', 'Resource', 'Destination'], null, 'A1');

        $row = 2;
        foreach ($connections as $connection) {
            $sheet->fromArray([
                $connection->started_at_local->format('Y-m-d H:i:s'),
                $connection->ended_at
                    ? $connection->started_at->diffForHumans($connection->ended_at, true)
                    : 'ongoing',
                $connection->user_name ?: ($connection->user_email ?: $connection->src_ip),
                $connection->client_name ?: '—',
                $connection->site_name ?: "site#{$connection->resource_id}",
                $connection->resource_name ?: '—',
                "{$connection->dst_ip}:{$connection->dst_port}",
            ], null, "A{$row}");
            $row++;
        }
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = $format === 'ods' ? new Ods($spreadsheet) : new Xlsx($spreadsheet);
        $mimeType = $format === 'ods'
            ? 'application/vnd.oasis.opendocument.spreadsheet'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $filename = 'newt-connections-'.now()->format('Y-m-d').".{$format}";
        $directory = storage_path('app/private/pangolin/exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $path = "{$directory}/{$filename}";
        $writer->save($path);

        return response()->download($path, $filename, ['Content-Type' => $mimeType])->deleteFileAfterSend();
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
