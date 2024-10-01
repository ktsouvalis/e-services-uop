<?php

namespace App\Http\Controllers;

use App\Models\Mailer;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Mail\MailToDepartment;
use Illuminate\Support\Facades\Log;
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
        $user = auth()->user();

        $mailers = Mailer::where('user_id',$user->id)->get();
        return view('mailers.index', compact('mailers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMailerRequest $request)
    {
        //
        $validated = $request->validated();

        $validated['user_id'] = auth()->user()->id;
        $mailer = Mailer::create($validated);
        return redirect()->route('mailers.edit', $mailer->id)->with('success', 'Mailer created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Mailer $mailer)
    {
        //
        Gate::authorize('view', $mailer);

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
        $mailer->update($data_to_update);

        return redirect()->back()->with('success', 'Mail to Departments updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mailer $mailer)
    {
        //
        Gate::authorize('delete',$mailer );

        $mailer->delete();
        return redirect()->route('mailers.index')->with('success', 'Mail to Departments deleted successfully.');
    }

    public function download_file(Mailer $mailer, string $index)
    {
        Gate::authorize('view', $mailer);
        $files = $mailer->files;
        $fileKey = $this->search_key($files, $index);
        $filename = $files[$fileKey]['filename'];
        $path = "/mailers/$mailer->id/$filename";
        ob_end_clean();
        return Storage::download($path);
    }

    public function delete_file(Mailer $mailer, string $index)
    {
        Gate::authorize('view', $mailer);
        $files = $mailer->files;
        $fileKey = $this->search_key($files, $index);
        $filename = $files[$fileKey]['filename'];
        $path = "/mailers/$mailer->id/$filename";
        Storage::delete($path);
        unset($files[$fileKey]);
        !empty($files) ? $mailer->files = array_values($files) : $mailer->files = NULL;
        $mailer->save();
        
        return redirect()->back()->with('success', 'File deleted successfully.');
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

    public function clean_storage(Mailer $mailer)
    {
        Gate::authorize('view', $mailer);
        $files = $mailer->files;
        foreach ($files as $file) {
            $filename = $file['filename'];
            $path = "/mailers/$mailer->id/$filename";
            Storage::delete($path);
        }
        $mailer->files = null;
        $mailer->save();
        return redirect()->back()->with('success', 'Storage cleaned successfully.');
    }

    public function upload_files(Mailer $mailer, Request $request){
        Gate::authorize('view', $mailer);
        $existingFiles = $mailer->files ?? [];
        $index = count($existingFiles);
        foreach ($request->file('files') as $file) {
            $filename =  $file->getClientOriginalName();
            $file->storeAs("/mailers/$mailer->id", $filename);
            $existingFiles[] = ['index' => $index++, 'filename' => $filename]; 
        }
        $mailer->files = $existingFiles; 
        $mailer->save();
        return redirect()->back()->with('success', 'Files Uploaded Successfully');
    }

    public function review(Mailer $mailer)
    {
        Gate::authorize('view', $mailer);
        $files = $mailer->files;
        foreach ($files as $file) {
            $filename = $file['filename'];
            $fileindex = $file['index'];
            if (preg_match('/\d{4}/', $filename, $matches)) {
                $review_array[] = ['index' => $fileindex, 'filename' => $filename, 'to' => Department::find($matches[0])];   
            }
            else if (preg_match('/\d{3}/', $filename, $matches)){
                $review_array[] = ['index' => $fileindex, 'filename' => $filename, 'to' => Department::find($matches[0])];
            }
        }
        
        return view('mailers.review')
            ->with('review_array', $review_array)
            ->with('mailer', $mailer);
    }

    public function send(Mailer $mailer, string $index, Department $department){
        Gate::authorize('view', $mailer);
        $files = $mailer->files;
        $fileKey = $this->search_key($files, $index);
        $filename = $files[$fileKey]['filename'];
        $path = "/mailers/$mailer->id/$filename";
        try{
            Mail::to($department->email)->send(new MailToDepartment($mailer->subject, $mailer->signature, $mailer->body, [$path]));
        }
        catch(\Exception $e){
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'Mail not sent.');
        }
        return redirect()->back()->with('success', 'Mail sent successfully.');
    }
}
