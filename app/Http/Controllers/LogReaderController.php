<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LogReaderController extends Controller
{
    //
    public function read(Request $request)
    {
        // $uploaded_files = $request->validated()['files'] ?? [];
        $uploaded_file = $request->file('file');
        if(empty($uploaded_file)){
            return redirect()->back()->with('warning', 'No file uploaded.');
        }
        
        $filename =  $uploaded_file->getClientOriginalName();
        try{
            $uploaded_file->storeAs("/log-reader", $filename);
        }
        catch(Exception $e){
            Log::channel('log-reader')->error($e->getMessage());
            return back()->with('error', 'File uploaded with errors. Check today\'s log-reader log for more information.');
        }
            
        $regex = $request->input('regex');
        if (empty($regex)) {
            return redirect()->back()->with('warning', 'No regex provided.');
        }

        $path = storage_path("app/private/log-reader/{$filename}");
        $content = file_get_contents($path);

        preg_match_all("/{$regex}/", $content, $matches);
        $uniqueMatches = array_unique($matches[0]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;
        foreach ($uniqueMatches as $match) {
            $content = trim($match);
            if($request->input('extra_chars'))
                $content = substr(trim($match), 0, -$request->input('extra_chars'));
            if (!empty($content)) {
                $sheet->setCellValue('A' . $rowIndex, $content);
                $rowIndex++;
            }
        }

        $newFilename = pathinfo($filename, PATHINFO_FILENAME) . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save(storage_path("app/private/log-reader/{$newFilename}"));

        ob_end_clean();
        Log::channel('log-reader')->info("File {$filename} was read with regex {$regex}");
        return response()->download(storage_path("app/private/log-reader/{$newFilename}"))->deleteFileAfterSend(true);
        
    }
}
