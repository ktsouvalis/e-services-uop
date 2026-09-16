<?php

namespace App\Http\Controllers;

use Exception;
use App\Mail\MailSheetMailer;
use App\Models\Sheetmailer;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Requests\StoreSheetmailerRequest;
use App\Http\Requests\UpdateSheetmailerRequest;
use App\Jobs\Sheetmailers\SendSheetmailerEmail;
use App\Services\DeliveryLog;
use App\Services\Sheetmailers\RecipientListParser;

class SheetmailerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Sheetmailer::class);
        if(Auth::user()->admin){
            $sheetmailers = Sheetmailer::with('user')->latest()->paginate(15);
        }
        else{
            // Show own or public
            $sheetmailers = Sheetmailer::with('user')
                ->where(function($q){
                    $q->where('user_id', Auth::id())
                      ->orWhere('is_public', true);
                })
                ->latest()
                ->paginate(15);
        }

        return view('sheetmailers.index', compact('sheetmailers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSheetmailerRequest $request)
    {
        Gate::authorize('create', Sheetmailer::class);

        $validated = $request->validated();
        $validated['user_id'] = auth()->user()->id;
        try{
            $sheetmailer = Sheetmailer::create($validated);
        }
        catch(Exception $e){
            Log::channel('sheetmailers_actions')->error('Sheetmail create failed by '. (Auth::user()->username ?? 'system'), [
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Files not uploaded. Check today\'s sheetmailers log for more information.');
        }
        Log::channel('sheetmailers_actions')->info('Sheetmail '. $sheetmailer->id .' created by '. (Auth::user()->username ?? 'system'));
        return redirect()->route('sheetmailers.edit', $sheetmailer->id)->with('success', 'Sheetmailer created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sheetmailer $sheetmailer)
    {
        Gate::authorize('update', $sheetmailer);

        $this->forgetRecipients($sheetmailer);

        return view('sheetmailers.edit', [
            'sheetmailer' => $sheetmailer,
            'sendBatch' => $this->activeSendBatchSummary($sheetmailer),
            'deliveryLogs' => DeliveryLog::listFor('sheetmailer', $sheetmailer->id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSheetmailerRequest $request, Sheetmailer $sheetmailer)
    {
        Gate::authorize('update', $sheetmailer);

        $data_to_update = $request->validated();
        // Allow only these tags - same whitelist for both, so inline formatting (e.g.
        // typing <em>...</em> by hand) works in the signature too, not just the body.
        $allowedTags = '<p><a><strong><span><i><em><b><u><ul><ol><li><br>';
        $data_to_update['body'] = strip_tags($request->input('body'), $allowedTags);
        if ($request->has('signature')) {
            $data_to_update['signature'] = strip_tags($request->input('signature'), $allowedTags);
        }

        // Enforce only creator can toggle is_public
        if (array_key_exists('is_public', $data_to_update)) {
            if ($sheetmailer->user_id !== Auth::id()) {
                unset($data_to_update['is_public']);
            } else {
                $newVisibility = (bool) $data_to_update['is_public'];
                if ($sheetmailer->is_public !== $newVisibility) {
                    Log::channel('sheetmailers_actions')->info('Sheetmailer '. $sheetmailer->id .' visibility change by creator '. (Auth::user()->username ?? 'system'), [
                        'from' => $sheetmailer->is_public ? 'public' : 'private',
                        'to' => $newVisibility ? 'public' : 'private',
                    ]);
                }
            }
        }

        // If checkbox was unchecked it may not be present; allow explicit false via hidden input in view
        try{
            $sheetmailer->update($data_to_update);
        }
        catch(\Exception $e){
            Log::channel('sheetmailers_actions')->error('Sheetmailer '. $sheetmailer->id .' update failed by '. (Auth::user()->username ?? 'system'), [
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Sheetmailer not updated. Check today\'s sheetmailers log for more information.');
        }
        Log::channel('sheetmailers_actions')->info('Sheetmailer '. $sheetmailer->id .' updated by '. (Auth::user()->username ?? 'system'));
        return redirect()->back()->with('success', 'Sheetmailer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sheetmailer $sheetmailer)
    {
        Gate::authorize('delete', $sheetmailer);

        try{
            $sheetmailer->delete();
        }
        catch(\Exception $e){
            Log::channel('sheetmailers_actions')->error('Sheetmailer '. $sheetmailer->id .' delete failed by '. (Auth::user()->username ?? 'system'), [
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Sheetmailer not deleted. Check today\'s sheetmailers log for more information.');
        }
        Log::channel('sheetmailers_actions')->info('Sheetmailer '. $sheetmailer->id .' deleted by '. (Auth::user()->username ?? 'system'));
        return redirect()->route('sheetmailers.index')->with('success', 'Sheetmailer deleted successfully.');
    }

    public function upload_file(Request $request, Sheetmailer $sheetmailer)
    {
        Gate::authorize('update', $sheetmailer);

        $this->forgetRecipients($sheetmailer);

        // Validate the input (mimes checks the extension, mimetypes checks the detected content type -
        // require both to agree so a renamed file can't sneak past either check alone)
        $request->validate([
            'file' => 'required|file|mimes:xlsx|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet|max:2048', // max 2MB file
        ], [
            'file.required' => 'Please choose a file to upload.',
            'file.mimes' => 'The file must be an .xlsx spreadsheet.',
            'file.mimetypes' => 'The file must be an .xlsx spreadsheet.',
            'file.max' => 'The file is too large. Maximum allowed size is 2MB.',
        ]);

        // Give parsing more headroom than the default 30s execution limit for larger sheets
        ini_set('max_execution_time', 60);

        // Load and parse the file - a corrupt/malformed upload throws from PhpSpreadsheet, not just from validation
        try {
            $filePath = $request->file('file')->getRealPath();
            $spreadsheet = IOFactory::load($filePath);
            $parsed = RecipientListParser::fromWorksheet($spreadsheet->getActiveSheet());
        }
        catch (\RuntimeException $e) {
            // Thrown by RecipientListParser itself when row 1 has no "email" header -
            // a user-fixable mistake, worth a specific message rather than the generic one below.
            return redirect()->back()->with('error', $e->getMessage());
        }
        catch (\Throwable $e) {
            Log::channel('sheetmailers')->error('Sheetmailer '. $sheetmailer->id .' file upload failed to parse for '. (Auth::user()->username ?? 'system'), [
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'The uploaded file could not be read. Make sure it is a valid, uncorrupted .xlsx file and try again.');
        }

        if (empty($parsed['eligible']) && empty($parsed['invalid'])) {
            return redirect()->back()->with('error', 'The uploaded file appears to be empty - no rows were found.');
        }

        $this->storeRecipients($sheetmailer, $parsed['eligible'], $parsed['invalid']);

        return redirect()->route('sheetmailers.confirm', ['sheetmailer' => $sheetmailer->id]);
    }

    public function comma_mails(Request $request, Sheetmailer $sheetmailer){
        Gate::authorize('update', $sheetmailer);

        $this->forgetRecipients($sheetmailer);

        $parsed = RecipientListParser::fromCommaList((string) $request->comma_mails);

        $this->storeRecipients($sheetmailer, $parsed['eligible'], $parsed['invalid']);

        return redirect()->route('sheetmailers.confirm', ['sheetmailer' => $sheetmailer->id]);
    }

    /**
     * Show the staged recipient list for the given sheetmailer, with an inline
     * preview of what will be sent, before the user confirms sending.
     */
    public function confirm(Sheetmailer $sheetmailer)
    {
        Gate::authorize('view', $sheetmailer);

        $recipients = $this->pullRecipients($sheetmailer);

        if (empty($recipients)) {
            return redirect()->route('sheetmailers.edit', $sheetmailer)
                ->with('error', 'No recipient list is staged for this sheetmailer. Upload a file or paste emails first.');
        }

        $emails = $recipients['emails'] ?? [];

        return view('sheetmailers.confirm', [
            'sheetmailer' => $sheetmailer,
            'emails' => $emails,
            'nonEmails' => $recipients['non_emails'] ?? [],
            'emailCount' => $recipients['emailCount'] ?? 0,
            // Every recipient was parsed from the same header row, so the first
            // one's placeholder keys are the full set - used to render one table
            // column per {{placeholder}} available for this send.
            'placeholderKeys' => array_keys($emails[0]['placeholders'] ?? []),
        ]);
    }

    public function send(Request $request, Sheetmailer $sheetmailer){
        Gate::authorize('update', $sheetmailer);

        $emails = $this->pullRecipients($sheetmailer)['emails'] ?? [];

        if (empty($emails)) {
            return redirect()->route('sheetmailers.edit', $sheetmailer)
                ->with('error', 'No recipient list is staged for this sheetmailer. Upload a file or paste emails first.');
        }

        // The confirm form lets the user uncheck individual recipients before sending;
        // "keep_present" tells us the request came from that form (so an empty "keep"
        // means "everything was unchecked", not "this caller doesn't know about selection").
        if ($request->boolean('keep_present')) {
            $keep = array_map('intval', $request->input('keep', []));
            $emails = array_values(array_intersect_key($emails, array_flip($keep)));

            if (empty($emails)) {
                return redirect()->route('sheetmailers.confirm', $sheetmailer)
                    ->with('error', 'No recipients were selected - nothing was sent.');
            }
        }

        $triggeredBy = Auth::user()->username ?? 'system';
        $logPath = DeliveryLog::newPath('sheetmailer', $sheetmailer->id);

        $jobs = collect($emails)
            ->map(fn (array $email) => new SendSheetmailerEmail($sheetmailer, $email['email'], $email['placeholders'], $triggeredBy, $logPath))
            ->all();

        // allowFailures(): one recipient's mail failing shouldn't cancel the rest of the
        // batch - by default Laravel cancels remaining jobs on the first failure.
        $batch = Bus::batch($jobs)
            ->name("sheetmailer-{$sheetmailer->id}")
            ->allowFailures()
            ->dispatch();

        $this->forgetRecipients($sheetmailer);
        $this->storeSendBatch($sheetmailer, $batch->id);

        Log::channel('sheetmailers_actions')->info('Sheetmailer '. $sheetmailer->id .' send batch '. $batch->id .' started by '. $triggeredBy, [
            'recipients' => count($emails),
        ]);

        return redirect()->route('sheetmailers.edit', $sheetmailer)
            ->with('success', 'Sending to '.count($emails).' recipient(s) - see progress below.');
    }

    /**
     * Render (but never send) the mail for a small sample of the staged
     * recipient list - first, middle and last - and log the rendered
     * subject/body for each. Exercises the exact same view-rendering path
     * `SendSheetmailerEmail` uses (Mailable::render(), not Mail::send()),
     * so a broken template or a bad placeholder substitution surfaces
     * without actually mailing anyone. File size/type and address validity
     * are already checked at upload time (upload_file()/comma_mails()) and
     * shown on the confirm page, so this only covers what those don't.
     */
    public function dryRun(Sheetmailer $sheetmailer)
    {
        Gate::authorize('view', $sheetmailer);

        $emails = $this->pullRecipients($sheetmailer)['emails'] ?? [];

        if (empty($emails)) {
            return redirect()->route('sheetmailers.edit', $sheetmailer)
                ->with('error', 'No recipient list is staged for this sheetmailer. Upload a file or paste emails first.');
        }

        $total = count($emails);
        $sampleIndexes = array_unique([0, intdiv($total - 1, 2), $total - 1]);
        sort($sampleIndexes);

        $failures = [];

        foreach ($sampleIndexes as $index) {
            $recipient = $emails[$index];

            try {
                $mailable = new MailSheetMailer($sheetmailer, $recipient['placeholders']);
                $rendered = $mailable->render();

                Log::channel('sheetmailers')->info(
                    'Sheetmailer ' . $sheetmailer->id . ' DRY RUN rendered OK for ' . $recipient['email']
                        . ' (recipient ' . ($index + 1) . ' of ' . $total . ')',
                    [
                        'subject' => $mailable->envelope()->subject,
                        'placeholders' => $recipient['placeholders'],
                        'body_preview' => Str::limit(strip_tags($rendered), 300),
                    ]
                );
            } catch (\Throwable $e) {
                $failures[] = $recipient['email'];

                Log::channel('sheetmailers')->error(
                    'Sheetmailer ' . $sheetmailer->id . ' DRY RUN render FAILED for ' . $recipient['email'],
                    ['error' => $e->getMessage()]
                );
            }
        }

        if (! empty($failures)) {
            return redirect()->route('sheetmailers.confirm', $sheetmailer)
                ->with('error', 'Dry run failed to render for: ' . implode(', ', $failures) . '. No emails were sent - check today\'s sheetmailers log for details.');
        }

        return redirect()->route('sheetmailers.confirm', $sheetmailer)
            ->with('success', 'Dry run OK - rendered ' . count($sampleIndexes) . ' sample email(s) (first/middle/last) without sending. Check today\'s sheetmailers log for the rendered content.');
    }

    /**
     * On-demand preview (fetched by the "Preview" button per row on the Confirm page)
     * of one staged recipient's fully merged email - subject and body with this
     * recipient's placeholders substituted in, exactly as it would be sent. Never
     * sends anything. Unlike Dry Run's fixed first/middle/last sample, this lets a
     * user spot-check any specific recipient - important now that mail-merge means
     * every recipient's content genuinely differs, so a misaligned spreadsheet row
     * could otherwise put the wrong data in front of the wrong person unnoticed.
     */
    public function previewRecipient(Sheetmailer $sheetmailer, int $index)
    {
        Gate::authorize('view', $sheetmailer);

        $emails = $this->pullRecipients($sheetmailer)['emails'] ?? [];

        if (! array_key_exists($index, $emails)) {
            abort(404);
        }

        $recipient = $emails[$index];
        // $mailable->body/$signature (already placeholder-merged, see MailSheetMailer's
        // constructor) are returned separately rather than the combined render() output,
        // so the preview panel can style the signature on its own - same as the Confirm
        // page's raw-template card does - instead of it running into the body untouched.
        $mailable = new MailSheetMailer($sheetmailer, $recipient['placeholders']);

        return response()->json([
            'email' => $recipient['email'],
            'subject' => $mailable->envelope()->subject,
            'body' => $mailable->body,
            'signature' => $mailable->signature,
        ]);
    }

    /**
     * Live progress (polled by the "Sending..." bar on the Edit page) for a batch
     * started from send(). Scoped to the batch id this sheetmailer's own session
     * actually started, so one user can't probe another's batch id.
     */
    public function sendStatus(Sheetmailer $sheetmailer, string $batch)
    {
        Gate::authorize('update', $sheetmailer);

        if ($this->pullSendBatch($sheetmailer) !== $batch) {
            abort(404);
        }

        $found = Bus::findBatch($batch);

        if (! $found) {
            $this->forgetSendBatch($sheetmailer);

            return response()->json(['finished' => true, 'missing' => true]);
        }

        if ($found->finished()) {
            $this->forgetSendBatch($sheetmailer);
        }

        return response()->json([
            'total' => $found->totalJobs,
            'processed' => $found->processedJobs(),
            'failed' => $found->failedJobs,
            'progress' => $found->progress(),
            'finished' => $found->finished(),
            'cancelled' => $found->cancelled(),
        ]);
    }

    /**
     * Download one per-send delivery log file (see App\Services\DeliveryLog)
     * for this sheetmailer, listed on its Edit page.
     */
    public function downloadLog(Sheetmailer $sheetmailer, string $filename)
    {
        Gate::authorize('view', $sheetmailer);

        $path = DeliveryLog::resolveForDownload('sheetmailer', $sheetmailer->id, $filename);

        if (! $path) {
            abort(404);
        }

        return response()->download($path);
    }

    /**
     * Session key for this sheetmailer's staged (not-yet-sent) recipient list.
     * Namespaced by sheetmailer id so staging recipients for one sheetmailer
     * (e.g. in a second browser tab) can't leak into another's confirm/send.
     */
    private function recipientsSessionKey(Sheetmailer $sheetmailer): string
    {
        return "sheetmailers.recipients.{$sheetmailer->id}";
    }

    private function storeRecipients(Sheetmailer $sheetmailer, array $eligible, array $invalid): void
    {
        session()->put($this->recipientsSessionKey($sheetmailer), [
            'emails' => $eligible,
            'non_emails' => $invalid,
            'emailCount' => count($eligible),
        ]);
    }

    private function pullRecipients(Sheetmailer $sheetmailer): ?array
    {
        return session($this->recipientsSessionKey($sheetmailer));
    }

    private function forgetRecipients(Sheetmailer $sheetmailer): void
    {
        session()->forget($this->recipientsSessionKey($sheetmailer));
    }

    /**
     * Session key for the batch id of this sheetmailer's in-flight send, if any.
     */
    private function sendBatchSessionKey(Sheetmailer $sheetmailer): string
    {
        return "sheetmailers.send_batch.{$sheetmailer->id}";
    }

    private function storeSendBatch(Sheetmailer $sheetmailer, string $batchId): void
    {
        session()->put($this->sendBatchSessionKey($sheetmailer), $batchId);
    }

    private function pullSendBatch(Sheetmailer $sheetmailer): ?string
    {
        return session($this->sendBatchSessionKey($sheetmailer));
    }

    private function forgetSendBatch(Sheetmailer $sheetmailer): void
    {
        session()->forget($this->sendBatchSessionKey($sheetmailer));
    }

    /**
     * Initial state for the Edit page's progress bar. Only returned while the
     * batch is still running - a page load after it's finished clears the
     * session key so a stale "done" bar doesn't linger on a later visit (a
     * still-open tab polling send-status will still see the final state once
     * before that happens).
     */
    private function activeSendBatchSummary(Sheetmailer $sheetmailer): ?array
    {
        $batchId = $this->pullSendBatch($sheetmailer);

        if (! $batchId) {
            return null;
        }

        $batch = Bus::findBatch($batchId);

        if (! $batch || $batch->finished()) {
            $this->forgetSendBatch($sheetmailer);

            return null;
        }

        return [
            'id' => $batch->id,
            'total' => $batch->totalJobs,
            'processed' => $batch->processedJobs(),
            'failed' => $batch->failedJobs,
            'progress' => $batch->progress(),
        ];
    }
}
