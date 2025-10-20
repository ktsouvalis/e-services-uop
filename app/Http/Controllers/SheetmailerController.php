<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Sheetmailer;

use Illuminate\Http\Request;
use App\Mail\MailSheetMailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Requests\UpdateSheetmailerRequest;

class SheetmailerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Sheetmailer::class);
        if(Auth::user()->admin){
            $sheetmailers = Sheetmailer::with('user')->latest()->get();
        }
        else{
            // Show own or public
            $sheetmailers = Sheetmailer::with('user')
                ->where(function($q){
                    $q->where('user_id', Auth::id())
                      ->orWhere('is_public', true);
                })
                ->latest()
                ->get();
        }
        
        return view('sheetmailers.index', compact('sheetmailers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Sheetmailer::class);

        $validated = $request->input();
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

        // Clear session data
        session()->forget('emails');
        session()->forget('non_emails');
        session()->forget('emailCount');

        return view('sheetmailers.edit', [
            'sheetmailer' => $sheetmailer,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSheetmailerRequest $request, Sheetmailer $sheetmailer)
    {
        Gate::authorize('update', $sheetmailer);

        $data_to_update = $request->validated();
        $data_to_update['body'] = strip_tags($request->input('body'), '<p><a><strong><span><i><em><b><u><ul><ol><li><br>'); // allow only these tags

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
            Log::channel('sheetmailers')->error('Sheetmailer '. $sheetmailer->id .' delete failed by '. (Auth::user()->username ?? 'system'), [
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Sheetmailer not deleted. Check today\'s sheetmailers log for more information.');
        }
        Log::channel('sheetmailers_actions')->info('Sheetmailer '. $sheetmailer->id .' deleted by '. (Auth::user()->username ?? 'system'));
        return redirect()->route('sheetmailers.index')->with('success', 'Sheetmailer deleted successfully.');
    }

    public function upload_file(Request $request, Sheetmailer $sheetmailer)
    {
        // Clear session data
        session()->forget('emails');
        session()->forget('non_emails');
        session()->forget('emailCount');

        // Validate the input
        $request->validate([
            'file' => 'required|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet|max:2048', // max 2MB file
        ]);

        // Load the file
        $filePath = $request->file('file')->getRealPath();
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Get all emails from the first column and extra data from the second column
        $eligible_emails = [];
        $non_emails = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellA = $sheet->getCell('A' . $row->getRowIndex());
            $email = $cellA->getValue();
            $cellB = $sheet->getCell('B' . $row->getRowIndex());
            $additionalData = $cellB->getValue();
            // Validate email format
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $eligible_emails[] = [
                    'email' => $email,
                    'additionalData' =>$additionalData
                ];
                                    
            }
            else{
                $non_emails[]=$email;
            }
        }
        // Count the number of valid emails
        $emailCount = count($eligible_emails);
        session()->put('emails', $eligible_emails);
        session()->put('non_emails', $non_emails);
        session()->put('emailCount', $emailCount);

        return redirect()->route('sheetmailers.confirm', ['sheetmailer' => $sheetmailer->id]);
    }

    public function comma_mails(Request $request, Sheetmailer $sheetmailer){
        // Clear session data
        session()->forget('emails');
        session()->forget('non_emails');
        session()->forget('emailCount');
        $emails = explode(',', $request->comma_mails);
        $eligible_emails = [];
        $non_emails = [];
        foreach ($emails as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $eligible_emails[] = [
                    'email' => $email,
                    'additionalData' => ''
                ];
            }
            else{
                $non_emails[]=$email;
            }
        }
        $emailCount = count($eligible_emails);
        session()->put('emails', $eligible_emails);
        session()->put('non_emails', $non_emails);
        session()->put('emailCount', $emailCount);

        return redirect()->route('sheetmailers.confirm', ['sheetmailer' => $sheetmailer->id]);
    }

    public function preview(Request $request, Sheetmailer $sheetmailer){
        Gate::authorize('view', $sheetmailer);

        $emails = session('emails');
        return new MailSheetMailer($sheetmailer, $emails[0]['additionalData'], 'preview');
    }

    public function send(Request $request, Sheetmailer $sheetmailer){
        Gate::authorize('view', $sheetmailer);
        ini_set('max_execution_time', 300); // Set max execution time to 300 seconds
        $emails = session('emails');
        $errors =0;
        foreach($emails as $email){
            $error=0;
            try{
                Mail::to($email['email'])->queue(new MailSheetMailer($sheetmailer, $email['additionalData'], Auth::user()->username));
            }
            catch(\Exception $e){
                Log::channel('sheetmailers')->error('Sheetmailer '. $sheetmailer->id .' NOT queued by '. (Auth::user()->username ?? 'system'), [
                    'email' => $email['email'],
                    'error' => $e->getMessage(),
                ]);
                $error = 1;
                $errors= 1;
            }
            if(!$error) Log::channel('sheetmailers')->info('Sheetmailer '. $sheetmailer->id .' mail queued by '. (Auth::user()->username ?? 'system'), [
                'email' => $email['email'],
            ]);
        }
        session()->forget('emails');
        session()->forget('emailCount');
        session()->forget('non_emails');
        if(!$errors)
            return redirect()->route("sheetmailers.edit", ['sheetmailer' => $sheetmailer->id])->with('success', 'Τα email προστέθηκαν στην ουρά προς αποστολή');
        else
            return redirect()->route("sheetmailers.edit", ['sheetmailer' => $sheetmailer->id])->with('warning', 'Η προσθήκη των email στην ουρά προς αποστολή ολοκληρώθηκε με λάθη που καταγράφηκαν στο log sheetmailers');
    }
}
