<?php

namespace App\Http\Controllers;

use App\Jobs\Pangolin\RunImport;
use App\Jobs\Pangolin\RunNormalize;
use App\Models\PangolinRun;
use Illuminate\Http\Request;

class PangolinController extends Controller
{
    public function index()
    {
        $importRuns = PangolinRun::ofType('import')->with('user')->latest()->take(10)->get();
        $normalizeRuns = PangolinRun::ofType('normalize')->with('user')->latest()->take(10)->get();

        return view('pangolin.index', compact('importRuns', 'normalizeRuns'));
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
