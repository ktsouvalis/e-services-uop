<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = Item::all();
        return view('items.index')->with('items', $items);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('create', Item::class);
        return view('items.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Item::class);
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|max:255',
            's_n' => 'nullable|string|max:255',
            'brand_model' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'year_of_purchase' => 'nullable|integer',
            'value' => 'nullable|numeric',
            'source_of_funding' => 'nullable|string|',
            'user_id' => 'nullable|string|max:255',
            'comments' => 'nullable|string',
            'file_path' => 'nullable|file|',
        ]);
        $incoming = $request->input();

        if($request->input('user_id') == 99){
            $incoming['user_id'] = null;
        }
        else{
            $incoming['user_id'] = $request->input('user_id');
        }
        if($request->has('file_path')){
            $file = $request->file('file_path');
            $filename = $file->getClientOriginalName();
            $file->move(storage_path('app/private/items'), $filename);
            $incoming['file_path'] = $filename;
        }
        try{
            $new_item = Item::create($incoming);
        }
        catch(\Exception $e){
            Log::channel('items')->error('User '.auth()->user()->name.' failed to create item. Error: '.$e->getMessage());
            return redirect()->back()->with('error', 'Κάποιο σφάλμα συνέβη. Ελέγξτε το αρχείο log/items για περισσότερες πληροφορίες.');
        }
        Log::channel('items')->info('User '.auth()->user()->name.' created item with id '.$new_item->id);
        return redirect()->route('items.index')->with('success', 'Το αντικείμενο δημιουργήθηκε επιτυχώς.');
    }


    public function download_file(Request $request, Item $item){
        Gate::authorize('view', $item);
        ob_end_clean();
        return response()->download(storage_path('app/private/items/'.$item->file_path));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        Gate::authorize('update', $item);
        return view('items.edit')->with('item', $item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Item $item)
    {
        Gate::authorize('update', $item);
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|max:255',
            's_n' => 'nullable|string|max:255',
            'brand_model' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'year_of_purchase' => 'nullable|integer',
            'value' => 'nullable|numeric',
            'source_of_funding' => 'nullable|string|',
            'user_id' => 'nullable|string|max:255',
            'comments' => 'nullable|string',
            'file_path' => 'nullable|file|',
        ]);

        $incoming = $request->all();
        if($incoming['user_id'] == 99){
            $incoming['user_id'] = null;
        }
        if($request->has('file_path')){

            $file = $request->file('file_path');
            $filename = $file->getClientOriginalName();
            $file->move(storage_path('app/private/items'), $filename);
            $incoming['file_path'] = $filename;
            Storage::delete('app/private/items/'.$item->file_path);
        }
        DB::beginTransaction();
        try{
            $item->lockForUpdate();
            $item->update($incoming);
            DB::commit();
        }
        catch(\Exception $e){
            DB::rollBack();
            Log::channel('items')->error('User '.auth()->user()->name.' failed to update item with id '.$item->id.'. Error: '.$e->getMessage());
            return redirect()->back()->with('error', 'Κάποιο σφάλμα συνέβη. Ελέγξτε το αρχείο log/items για περισσότερες πληροφορίες.');
        }
        Log::channel('items')->info('User '.auth()->user()->name.' updated item with id '.$item->id);
        return redirect()->back()->with('success', 'Το αντικείμενο ενημερώθηκε επιτυχώς.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        Gate::authorize('delete', $item);
        DB::beginTransaction();
        try{
            $item->lockForUpdate();
            $item->delete();
            DB::commit();
        }
        catch(Exception $e){
            DB::rollBack();
            Log::channel('items')->error('User '.auth()->user()->name.' failed to delete item with id '.$item->id.'. Error: '.$e->getMessage());
            return response()->json(['status'=>'error', 'message' => 'Κάποιο σφάλμα συνέβη. Ελέγξτε το αρχείο log/items για περισσότερες πληροφορίες.']);
        }
        Storage::delete('app/private/items/'.$item->file_path);
        Log::channel('items')->info('User '.auth()->user()->name.' deleted item with id '.$item->id);
        return response()->json(['status'=>'success','message' => 'Το αντικείμενο διαγράφηκε επιτυχώς.']);
    }

    public function extract()
    {
        Gate::authorize('create', Item::class);
        $items = Item::where('given_away', 0)->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'A1' => 'ID',
            'B1' => 'Υπεύθυνος Καταχώρησης',
            'C1' => 'Σχόλια ΜΨΔ',
            'D1' => 'Κατηγορία Εξοπλισμού',
            'E1' => 'Πλήρης Περιγραφή Παγίου',
            'F1' => 'Σειριακός Αριθμός (εάν αφορά Η/Υ)',
            'G1' => 'Διαστάσεις (εάν αφορά έπιπλα)',
            'H1' => 'Εταιρεία και Μοντέλο (εάν αφορά επιστημονικά όργανα)',
            'I1' => 'Ποσότητα',
            'J1' => 'Παράρτημα Πανεπιστημίου',
            'K1' => 'Σχολή',
            'L1' => 'Τμήμα',
            'M1' => 'Φυσική κατάσταση του παγίου',
            'N1' => 'Έτος κτήσης',
            'O1' => 'Αξία κτήσης (Με ΦΠΑ)',
            'P1' => 'Πηγή Χρηματοδότησης Παγίου'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getColumnDimension(substr($cell, 0, 1))->setAutoSize(true);
        }

        // Set background color for the 2nd and 3rd columns
        $sheet->getStyle('B1:B' . ($items->count() + 1))
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFFF00'); // Yellow color

        $sheet->getStyle('C1:C' . ($items->count() + 1))
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFFF00'); // Yellow color

        // Populate the data
        $row = 2;
        foreach ($items as $item) {
            $sheet->setCellValue('A' . $row, $item->id);
            $sheet->setCellValue('B' . $row, optional($item->user)->name);
            $sheet->setCellValue('C' . $row, $item->comments);
            $sheet->setCellValue('D' . $row, $item->category->name);
            $sheet->setCellValue('E' . $row, $item->description);
            $sheet->setCellValue('F' . $row, $item->s_n);
            $sheet->setCellValue('G' . $row, '');
            $sheet->setCellValue('H' . $row, $item->brand_model);
            $sheet->setCellValue('I' . $row, 1);
            $sheet->setCellValue('J' . $row, 'Πάτρα');
            $sheet->setCellValue('K' . $row, '18-Διευθ/ση Υπ/σιων Ηλεκτρ/κης Διακ/σης Πάτρα');
            $sheet->setCellValue('L' . $row, '');
            $sheet->setCellValue('M' . $row, $item->status);
            $sheet->setCellValue('N' . $row, $item->year_of_purchase);
            $sheet->setCellValue('O' . $row, $item->value);
            $sheet->setCellValue('P' . $row, $item->source_of_funding);


//            $sheet->setCellValue('L' . $row, $item->file_path);
            $row++;
        }
        $timestamp = now()->format('Y-m-d');
        $filename = "items_$timestamp.xlsx";
        $path = storage_path('app/private/items/' . $filename);
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        ob_end_clean();
        return response()->download($path);
    }

    public function delete_file(Request $request, Item $item){
        Gate::authorize('update', $item);
        Storage::delete('app/private/items/'.$item->file_path);
        $item->file_path = null;
        $item->save();
        Log::channel('items')->info('User '.auth()->user()->name.' deleted file of item with id '.$item->id);
        return redirect()->back()->with('success', 'Το αρχείο διαγράφηκε επιτυχώς.');
    }

    public function given(Request $request, Item $item){
        Gate::authorize('update', $item);
        if($request->input('checked')=='true'){
            $item->given_away = 1;
            $item->user_id = null;
        }
        else
            $item->given_away = 0;
        try{
            $item->save;
        }
        catch(\Exception $e){
            Log::channel('items')->error('User '.auth()->user()->name.' failed to change item\'s with id '.$item->id.' given status. Error: '.$e->getMessage());
            return response()->json(['status'=>'error','message' => 'Κάποιο σφάλμα συνέβη. Ελέγξτε το αρχείο log/items.log για περισσότερες πληροφορίες.']);
        }
        $item->save();
        Log::channel('items')->info('User '.auth()->user()->name.' changed item\'s with id '.$item->id).' given status.';
        return response()->json(['status'=>'success','message' => 'Το αντικείμενο ενημερώθηκε επιτυχώς.']);  
    }
}
