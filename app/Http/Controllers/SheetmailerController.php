<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Sheetmailer;
use App\Mail\Sheetmailer as MailSheetmailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SheetmailerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();

        $sheetmailers = Sheetmailer::where('user_id',$user->id)->get();
        return view('sheetmailers.index', compact('sheetmailers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Gate::authorize('create', Mailer::class);
        // $validated = $request->validated();

        $validated = $request->input();
        $validated['user_id'] = auth()->user()->id;
        
        try{
            $sheetmailer = Sheetmailer::create($validated);;
        }
        catch(Exception $e){
            Log::channel('sheetmailers_actions')->error($e->getMessage());
            return redirect()->back()->with('error', 'Files not uploaded. Check today\'s sheetmailers log for more information.');
        }
        Log::channel('sheetmailers_actions')->info('Sheetmailer created successfully');
        return redirect()->route('sheetmailers.edit', $sheetmailer->id)->with('success', 'Sheetmailer created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sheetmailer $sheetmailer)
    {
        // Gate::authorize('update', $sheetmailer);

        return view('sheetmailers.edit', [
            'sheetmailer' => $sheetmailer,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sheetmailer $sheetmailer)
    {
        // dd($request->all());
        // Gate::authorize('update', $mailer);

        // $data_to_update = $request->validated();
        $data_to_update = $request->input();
        try{
            $sheetmailer->update($data_to_update);
        }
        catch(\Exception $e){
            Log::channel('sheetmailers_actions')->error($e->getMessage());
            return redirect()->back()->with('error', 'Sheetmailer not updated. Check today\'s sheetmailers log for more information.');
        }
        Log::channel('sheetmailers_actions')->info('Sheetmailer updated successfully');
        return redirect()->back()->with('success', 'Sheetmailer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sheetmailer $sheetmailer)
    {
        // Gate::authorize('delete',$sheetmailer );
        try{
            $sheetmailer->delete();
        }
        catch(\Exception $e){
            Log::channel('sheetmailers')->error($e->getMessage());
            return redirect()->back()->with('error', 'Sheetmailer not deleted. Check today\'s sheetmailers log for more information.');
        }
        Log::channel('sheetmailers_actions')->info('Sheetmailer deleted successfully');
        return redirect()->route('sheetmailers.index')->with('success', 'sheetmailer deleted successfully.');
    }

    public function upload_file(Request $request, Sheetmailer $sheetmailer){
        //validate the input
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
        $emails = session('emails');
        return new MailSheetmailer($sheetmailer, $emails[0]['additionalData']);
    }

    public function send(Request $request, Sheetmailer $sheetmailer){
        ini_set('max_execution_time', 300); // Set max execution time to 300 seconds
        $emails = session('emails');
        $errors =0;
        foreach($emails as $email){
            $error=0;
            try{
                Mail::to($email['email'])->send(new MailSheetmailer($sheetmailer, $email['additionalData']));
            }
            catch(\Exception $e){
                Log::channel('sheetmailers_failure')->error("Sheetmailer #$sheetmailer->id: mail not sent to ".$email['email']);
                Log::error('mail not sent to '.$email['email']. '. Reason: '.$e->getMessage());
                $error = 1;
                $errors= 1;
            }
            if(!$error)Log::channel('sheetmailers_success')->info("Sheetmailer #$sheetmailer->id: mail sent to ".$email['email']);        
        }
        session()->forget('emails');
        session()->forget('emailCount');
        session()->forget('non_emails');
        if(!$errors)
            return redirect()->route("sheetmailers.edit", ['sheetmailer' => $sheetmailer->id])->with('success', 'Η ενέργεια ολοκληρώθηκε χωρίς λάθη');
        else
            return redirect()->route("sheetmailers.edit", ['sheetmailer' => $sheetmailer->id])->with('warning', 'Η ενέργεια ολοκληρώθηκε με λάθη που καταγράφηκαν στο log sheetmailers_failure');
    }
}
