<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Mailer;
use App\Models\Department;
use App\Mail\MailToDepartment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreMailerRequest;
use App\Http\Requests\UpdateMailerRequest;

class MailerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if(Auth::user()->admin){
            $mailers = Mailer::all();
        }
        else{
            $mailers = Mailer::where('user_id',Auth::user()->id)->get();
        }
    
        return view('mailers.index', compact('mailers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMailerRequest $request)
    {
        Gate::authorize('create', Mailer::class);
        $validated = $request->validated();

        $validated['user_id'] = auth()->user()->id;
        try{
            $mailer = Mailer::create($validated);;
        }
        catch(Exception $e){
            Log::channel('mailers')->error($e->getMessage());
            return redirect()->back()->with('error', 'Mailer not created. Check today\'s mailers log for more information.');
        }

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
        $data_to_update['body'] = strip_tags($request->validated('body'), '<p><a><strong><i><em><b><u><ul><ol><li>');
        try{
            $mailer->update($data_to_update);
        }
        catch(\Exception $e){
            Log::channel('mailers')->error($e->getMessage());
            return redirect()->back()->with('error', 'Mailer not updated. Check today\'s mailers log for more information.');
        }

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
            Log::channel('mailers')->error($e->getMessage());
            return redirect()->back()->with('error', 'Mailer not deleted. Check today\'s mailers log for more information.');
        }

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
            return Storage::download($path);
        }
        catch(\Exception $e){
            Log::channel('mailers')->error($e->getMessage());
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
            Log::channel('mailers')->error($e->getMessage());
            return response()->json(['error' => 'File not deleted. Check today\'s mailers log for more information.'], 500);
        }
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
                Log::error($e->getMessage());
                $error=true;
                continue;
            }
            $file->storeAs("/mailers/$mailer->id", $filename);
            $existingFiles[] = ['index' => $index++, 'filename' => $filename];
        }
        $mailer->files = $existingFiles;
        $mailer->save();
        if ($error) {
            return redirect()->back()->with('error', 'Files uploaded with errors. Check today\'s mailers log for more information.');
        }
        return redirect()->back()->with('success', 'Files Uploaded Successfully');
    }

    public function review(Mailer $mailer)
    {
        Gate::authorize('update', $mailer);
        $files = $mailer->files;
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
        session()->put('review_array', $review_array);
        return view('mailers.review')
            ->with('mailer', $mailer);
    }

    public function send(Mailer $mailer, string $index, Department $department){
        Gate::authorize('view', $mailer);
        $files = $mailer->files;
        $fileKey = $this->search_key($files, $index);
        $filename = $files[$fileKey]['filename'];
        $path = "/mailers/$mailer->id/$filename";
        try{
            Mail::to($department->email)->queue(new MailToDepartment($mailer->subject, $mailer->signature, $mailer->body, [$path], Auth::user()->username));
        }
        catch(\Exception $e){
            Log::channel('mailers')->error("File '".$filename."' to ".$department->name." not queued: ".$e->getMessage());
            return redirect()->back()->with('error', 'Mail not queued.');
        }
        Log::channel('mailers')->info("File '".$filename."' to ".$department->name.": Mail queued successfully.");
        return redirect()->back()->with('success', 'Mail queued successfully.');
    }

    public function send_all(Mailer $mailer){
        Gate::authorize('view', $mailer);
        $review_array = session('review_array');
        $error = ['warning' => 'No Departments as stakeholders. Please upload some valid files'];
        foreach ($review_array as $file){
            if($file['to'] == 'Department not found'){
                continue;
            }
            $filename = $file['filename'];
            $department = $file['to'];
            $path = "/mailers/$mailer->id/$filename";
            $error = ['success' => 'Valid mails queued successfully.'];
            try{
                Mail::to($department->email)->queue(new MailToDepartment($mailer->subject, $mailer->signature, $mailer->body, [$path], Auth::user()->username));
            }
            catch(\Exception $e){
                Log::channel('mailers')->error("File '".$filename."' to ".$department->name." not queued: ".$e->getMessage());
                $error = ['warning' => 'Mails queued with errors. Check today\'s mailers log for more information.'];
                continue;
            }
            Log::channel('mailers')->info("File '".$filename."' to ".$department->name.": Mails queued successfully.");
        }
        return redirect()->back()->with($error);
    }
}
