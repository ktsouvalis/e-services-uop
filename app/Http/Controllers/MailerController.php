<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Mailer;
use App\Models\Department;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreMailerRequest;
use App\Http\Requests\UpdateMailerRequest;
use App\Jobs\Mailers\SendMailerFile;

class MailerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Mailer::class);
        $userId = Auth::id();
        $mailers = Mailer::with('user')
            ->where(function($q) use ($userId) {
                $q->where('is_public', true)
                  ->orWhere('user_id', $userId);
            })
            ->latest()
            ->paginate(15);

        return view('mailers.index', compact('mailers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMailerRequest $request)
    {
        Gate::authorize('create', Mailer::class);
        $validated = $request->validated();
        $validated['is_public'] = $request->boolean('is_public');

        $validated['user_id'] = auth()->user()->id;
        try{
            $mailer = Mailer::create($validated);;
        }
        catch(Exception $e){
            Log::channel('mailers_actions')->error($e->getMessage());
            return redirect()->back()->with('error', 'Mailer not created. Check today\'s mailers log for more information.');
        }
        Log::channel('mailers_actions')->info("Mailer $mailer->id created successfully by user: ".Auth::user()->username);
        return redirect()->route('mailers.edit', $mailer->id)->with('success', 'Mailer created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Mailer $mailer)
    {
        Gate::authorize('update', $mailer);

        return view('mailers.edit', [
            'mailer' => $mailer,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMailerRequest $request, Mailer $mailer)
    {
        Gate::authorize('update', $mailer);

        $data_to_update = $request->validated();
        // Only the creator can change public/private status
        $originalPublic = $mailer->is_public;
        $willCheckVisibility = false;
        if (Auth::id() === $mailer->user_id) {
            // Checkbox not sent when unchecked; boolean() returns false in that case
            $data_to_update['is_public'] = $request->boolean('is_public');
            $willCheckVisibility = true;
        } else {
            unset($data_to_update['is_public']);
        }
        $data_to_update['body'] = strip_tags($request->validated('body'), '<p><a><strong><i><em><b><u><ul><ol><li>');
        try{
            $mailer->update($data_to_update);
        }
        catch(\Exception $e){
            Log::channel('mailers_actions')->error($e->getMessage());
            return redirect()->back()->with('error', 'Mailer not updated. Check today\'s mailers log for more information.');
        }
        if ($willCheckVisibility && $originalPublic !== $mailer->is_public) {
            Log::channel('mailers_actions')->info("Mailer $mailer->id visibility changed by creator ".Auth::user()->username." to ".($mailer->is_public ? 'public' : 'private'));
        }
        Log::channel('mailers_actions')->info("Mailer $mailer->id updated by user: ".Auth::user()->username);
        return redirect()->back()->with('success', 'Mailer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mailer $mailer)
    {
        Gate::authorize('delete',$mailer );
        try{
            $mailer->delete();
        }
        catch(\Exception $e){
            Log::channel('mailers_actions')->error($e->getMessage());
            return redirect()->back()->with('error', 'Mailer not deleted. Check today\'s mailers log for more information.');
        }
        Log::channel('mailers_actions')->info("Mailer $mailer->id deleted by user: ".Auth::user()->username);
        return redirect()->route('mailers.index')->with('success', 'Mailer deleted successfully.');
    }

    public function download_file(Mailer $mailer, string $index)
    {
        Gate::authorize('view', $mailer);
        $files = $mailer->files;
        $fileKey = $this->search_key($files, $index);
        $filename = $files[$fileKey]['filename'];
        $path = "/mailers/$mailer->id/$filename";
        ob_end_clean();
        try{
            Log::channel('mailers_actions')->info("File '".$filename."' downloaded by user: ".Auth::user()->username." (mailer_id=".$mailer->id.")");
            return Storage::download($path);
        }
        catch(\Exception $e){
            Log::channel('mailers_actions')->error($e->getMessage());
            return redirect()->back()->with('error', 'File error. Check today\'s mailers log for more information.');
        }
    }

    public function delete_file(Mailer $mailer, string $index)
    {
        Gate::authorize('update', $mailer);
        $result = $this->delete_f($mailer, $index);
        return redirect()->back()->with(json_decode($result->getContent(),true));
    }

    private function delete_f($mailer, $index){
        $files = $mailer->files;
        $fileKey = $this->search_key($files, $index);

        if ($fileKey === null) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        $filename = $files[$fileKey]['filename'];
        $path = "/mailers/$mailer->id/$filename";

        unset($files[$fileKey]);
        $mailer->files = !empty($files) ? array_values($files) : null;

        DB::beginTransaction();
        try{
            $mailer->save();
            Storage::delete($path);
            DB::commit();
        }
        catch(\Exception $e){
            DB::rollBack();
            Log::channel('mailers_actions')->error($e->getMessage());
            return response()->json(['error' => 'File not deleted. Check today\'s mailers log for more information.'], 500);
        }
        Log::channel('mailers_actions')->info("File '".$filename."' deleted successfully by user: ".Auth::user()->username);
        return response()->json(['success' => 'File deleted successfully.'], 200);
    }

    private function search_key($array, $index)
    {
        foreach ($array as $key => $file) {
            if ($file['index'] == $index) {
                return $key;
            }
        }
        return null;
    }

    public function clean_storage(Mailer $mailer){
        Gate::authorize('update', $mailer);
        $files = $mailer->files;
        foreach ($files as $file) {
            $result = $this->delete_f($mailer, $file['index']);
            if ($result->getStatusCode() != 200) {
                return redirect()->back()->with('error', 'Storage not cleaned. Check today\'s mailers log for more information.');
            }
        }
        Log::channel('mailers_actions')->info("Mailer $mailer->id storage cleaned by user: ".Auth::user()->username);
        return redirect()->back()->with('success', 'Storage cleaned successfully.');
    }

    public function upload_files(Mailer $mailer, UpdateMailerRequest $request){
        Gate::authorize('update', $mailer);
        $uploaded_files = $request->validated()['files'] ?? [];
        if(empty($uploaded_files)){
            return redirect()->back()->with('warning', 'No files uploaded.');
        }
        $existingFiles = $mailer->files ?? [];
        $index = count($existingFiles);
        $error = false;
        foreach ($uploaded_files as $file) {
            $filename =  $file->getClientOriginalName();
            try{
                $file->storeAs("/mailers/$mailer->id", $filename);
            }
            catch(\Exception $e){
                $error=true;
                continue;
            }
            $existingFiles[] = ['index' => $index++, 'filename' => $filename];
        }
        $mailer->files = $existingFiles;
        $mailer->save();
        if ($error) {
            return redirect()->back()->with('error', 'Files uploaded with errors. Check today\'s mailers log for more information.');
        }
        Log::channel('mailers_actions')->info("Mailer $mailer->id files uploaded successfully by user: ".Auth::user()->username." (count=".count($uploaded_files).")");
        return redirect()->back()->with('success', 'Files Uploaded Successfully');
    }

    public function review(Mailer $mailer)
    {
        Gate::authorize('update', $mailer);

        $files = $mailer->files ?? [];

        if (empty($files)) {
            return redirect()->route('mailers.edit', $mailer)
                ->with('error', 'This mailer has no files uploaded yet. Upload files first.');
        }

        $review_array = [];
        foreach ($files as $file) {
            $filename = $file['filename'];
            $fileindex = $file['index'];
            $key=null;
            if (preg_match('/\d{4} -/', $filename, $matches)) {
                $key= (int) substr($matches[0], 0, 4);

            }
            else if (preg_match('/\d{3} -/', $filename, $matches)){
                $key= (int) substr($matches[0], 0, 3);
            }
            $department = Department::find($key);
            if($department){
                $review_array[] = ['index' => $fileindex, 'filename' => $filename, 'to' => $department];
            }
            else{
                $review_array[] = ['index' => $fileindex, 'filename' => $filename, 'to' => 'Department not found'];
            }
        }
        $this->storeReview($mailer, $review_array);

        return view('mailers.review', [
            'mailer' => $mailer,
            'review_array' => $review_array,
            'sendBatch' => $this->activeSendBatchSummary($mailer),
        ]);
    }

    public function send(Mailer $mailer, string $index, Department $department){
        Gate::authorize('update', $mailer);
        $files = $mailer->files;
        $fileKey = $this->search_key($files, $index);
        $filename = $files[$fileKey]['filename'];
        $triggeredBy = Auth::user()->username ?? 'system';

        try{
            SendMailerFile::dispatch($mailer, $department, $filename, $triggeredBy);
        }
        catch(\Exception $e){
            Log::channel('mailers')->error("Mailer $mailer->id file '$filename' to $department->name NOT queued: ".$e->getMessage()." by user: ".$triggeredBy);
            return redirect()->back()->with('error', 'Mail not queued.');
        }
        Log::channel('mailers_actions')->info("Mailer $mailer->id file '$filename' to $department->name: queued by user: ".$triggeredBy);
        return redirect()->back()->with('success', 'Mail queued successfully.');
    }

    public function send_all(Mailer $mailer){
        Gate::authorize('update', $mailer);

        $review_array = $this->pullReview($mailer) ?? [];
        $targets = array_filter($review_array, fn ($file) => $file['to'] !== 'Department not found');

        if (empty($targets)) {
            return redirect()->back()->with('warning', 'No Departments as stakeholders. Please upload some valid files');
        }

        $triggeredBy = Auth::user()->username ?? 'system';

        $jobs = collect($targets)
            ->map(fn (array $file) => new SendMailerFile($mailer, $file['to'], $file['filename'], $triggeredBy))
            ->all();

        // allowFailures(): one department's mail failing shouldn't cancel the rest of the
        // batch - by default Laravel cancels remaining jobs on the first failure.
        $batch = Bus::batch($jobs)
            ->name("mailer-{$mailer->id}")
            ->allowFailures()
            ->dispatch();

        $this->storeSendBatch($mailer, $batch->id);

        Log::channel('mailers_actions')->info('Mailer '. $mailer->id .' send batch '. $batch->id .' started by '. $triggeredBy, [
            'recipients' => count($targets),
        ]);

        return redirect()->route('mailers.review', $mailer)
            ->with('success', 'Sending to '.count($targets).' department(s) - see progress below.');
    }

    /**
     * Live progress (polled by the "Sending..." bar on the Review page) for a batch
     * started from send_all(). Scoped to the batch id this mailer's own session
     * actually started, so one user can't probe another's batch id.
     */
    public function sendStatus(Mailer $mailer, string $batch)
    {
        Gate::authorize('update', $mailer);

        if ($this->pullSendBatch($mailer) !== $batch) {
            abort(404);
        }

        $found = Bus::findBatch($batch);

        if (! $found) {
            $this->forgetSendBatch($mailer);

            return response()->json(['finished' => true, 'missing' => true]);
        }

        if ($found->finished()) {
            $this->forgetSendBatch($mailer);
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
     * Session key for this mailer's staged department/file review list.
     * Namespaced by mailer id so reviewing one mailer (e.g. in a second
     * browser tab) can't leak its review data into another mailer's send_all.
     */
    private function reviewSessionKey(Mailer $mailer): string
    {
        return "mailers.review.{$mailer->id}";
    }

    private function storeReview(Mailer $mailer, array $reviewArray): void
    {
        session()->put($this->reviewSessionKey($mailer), $reviewArray);
    }

    private function pullReview(Mailer $mailer): ?array
    {
        return session($this->reviewSessionKey($mailer));
    }

    /**
     * Session key for the batch id of this mailer's in-flight send_all, if any.
     */
    private function sendBatchSessionKey(Mailer $mailer): string
    {
        return "mailers.send_batch.{$mailer->id}";
    }

    private function storeSendBatch(Mailer $mailer, string $batchId): void
    {
        session()->put($this->sendBatchSessionKey($mailer), $batchId);
    }

    private function pullSendBatch(Mailer $mailer): ?string
    {
        return session($this->sendBatchSessionKey($mailer));
    }

    private function forgetSendBatch(Mailer $mailer): void
    {
        session()->forget($this->sendBatchSessionKey($mailer));
    }

    /**
     * Initial state for the Review page's progress bar. Only returned while the
     * batch is still running - a page load after it's finished clears the
     * session key so a stale "done" bar doesn't linger on a later visit (a
     * still-open tab polling send-status will still see the final state once
     * before that happens).
     */
    private function activeSendBatchSummary(Mailer $mailer): ?array
    {
        $batchId = $this->pullSendBatch($mailer);

        if (! $batchId) {
            return null;
        }

        $batch = Bus::findBatch($batchId);

        if (! $batch || $batch->finished()) {
            $this->forgetSendBatch($mailer);

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
