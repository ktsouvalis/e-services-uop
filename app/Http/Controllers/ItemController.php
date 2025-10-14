<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
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
        Gate::authorize('viewAny', Item::class);
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
            'file_path' => 'nullable|array',
            'file_path.*' => 'file|mimes:pdf',
        ]);
    $incoming = $request->except('file_path');

        // Normalize boolean checkbox (if present in create form)
        if ($request->has('in_local_storage')) {
            $incoming['in_local_storage'] = (bool)$request->input('in_local_storage');
        }

        if($request->input('user_id') == 99){
            $incoming['user_id'] = null;
        }
        else{
            $incoming['user_id'] = $request->input('user_id');
        }
        // Handle multiple file uploads
        $filesMeta = [];
        if ($request->hasFile('file_path')) {
            foreach ($request->file('file_path') as $file) {
                if (!$file) continue;
                $original = $file->getClientOriginalName();
                $unique = uniqid(date('YmdHis').'_');
                $ext = $file->getClientOriginalExtension();
                $storedName = $unique . ($ext ? ('.' . $ext) : '');
                $file->move(storage_path('app/private/items'), $storedName);
                $filesMeta[] = [
                    'original' => $original,
                    'stored' => $storedName,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }
        }
        if (!empty($filesMeta)) {
            $incoming['file_path'] = json_encode($filesMeta, JSON_UNESCAPED_UNICODE);
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
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $requestedStored = $request->query('filename');
        $files = $item->files;
        $storedName = null;
        $originalName = null;

        if ($requestedStored) {
            // Find the requested file in metadata
            foreach ($files as $file) {
                $stored = is_array($file) ? ($file['stored'] ?? $file['original'] ?? null) : $file;
                if ($stored && $stored === $requestedStored) {
                    $storedName = $stored;
                    $originalName = is_array($file) ? ($file['original'] ?? $stored) : $stored;
                    break;
                }
            }
        }

        // If not provided or not found, fallback to last uploaded
        if (!$storedName) {
            if (!empty($files)) {
                $last = end($files);
                $storedName = is_array($last) ? ($last['stored'] ?? $last['original'] ?? null) : $last;
                $originalName = is_array($last) ? ($last['original'] ?? $storedName) : $storedName;
            }
        }

        if (!$storedName) {
            abort(404);
        }

        $path = storage_path('app/private/items/' . $storedName);
        if (!file_exists($path)) {
            abort(404);
        }
        // Force download as the original filename (displayed to the user)
        return response()->download($path, $originalName ?: $storedName);
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
            'file_path' => 'nullable|array',
            'file_path.*' => 'file|mimes:pdf',
        ]);

        $incoming = $request->except('file_path');
        // Normalize boolean checkbox (if present in edit form submission)
        if ($request->has('in_local_storage')) {
            $incoming['in_local_storage'] = (bool)$request->input('in_local_storage');
        }
        if($incoming['user_id'] == 99){
            $incoming['user_id'] = null;
        }
        // Handle newly uploaded files: append to existing list
        // Start with existing files array from accessor
        $existing = $item->files;

        if ($request->hasFile('file_path')) {
            foreach ($request->file('file_path') as $file) {
                if (!$file) continue;
                $original = $file->getClientOriginalName();
                $unique = uniqid(date('YmdHis').'_');
                $ext = $file->getClientOriginalExtension();
                $storedName = $unique . ($ext ? ('.' . $ext) : '');
                $file->move(storage_path('app/private/items'), $storedName);
                $existing[] = [
                    'original' => $original,
                    'stored' => $storedName,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }
        }
        $incoming['file_path'] = !empty($existing) ? json_encode($existing, JSON_UNESCAPED_UNICODE) : null;
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
        // Delete all associated files
        foreach ($item->files as $file) {
            $stored = is_array($file) ? ($file['stored'] ?? $file['original'] ?? null) : $file;
            if ($stored) {
                File::delete(storage_path('app/private/items/'.$stored));
            }
        }
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


//            $sheet->setCellValue('L' . $row, is_array($item->file_path) ? json_encode($item->file_path) : $item->file_path);
            $row++;
        }
        $timestamp = now()->format('Y-m-d');
        $filename = "items_$timestamp.xlsx";
        $directory = storage_path('app/private/items');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $path = $directory . '/' . $filename;
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        // ob_end_clean();
        return response()->download($path);
    }

    public function delete_file(Request $request, Item $item){
        Gate::authorize('update', $item);
        $filename = $request->input('filename');
        $updated = [];
        foreach ($item->files as $file) {
            $stored = is_array($file) ? ($file['stored'] ?? $file['original'] ?? null) : $file;
            if ($stored && $stored === $filename) {
                File::delete(storage_path('app/private/items/'.$stored));
                continue;
            }
            $updated[] = $file;
        }
        $item->file_path = empty($updated) ? null : json_encode($updated, JSON_UNESCAPED_UNICODE);
        $item->save();
        Log::channel('items')->info('User '.auth()->user()->name.' deleted file '.$filename.' of item with id '.$item->id);
        return redirect()->back()->with('success', 'Το αρχείο διαγράφηκε επιτυχώς.');
    }

    public function given(Request $request, Item $item){
        Gate::authorize('update', $item);
        if($request->input('checked')=='true'){
            $item->given_away = 1;
            $item->user_id = null;
            // Automatically uncheck in_local_storage when item is given
            $item->in_local_storage = 0;
        }
        else
            $item->given_away = 0;
        try{
            $item->save();
        }
        catch(\Exception $e){
            Log::channel('items')->error('User '.auth()->user()->name.' failed to change item\'s with id '.$item->id.' given status. Error: '.$e->getMessage());
            return response()->json(['status'=>'error','message' => 'Κάποιο σφάλμα συνέβη. Ελέγξτε το αρχείο log/items.log για περισσότερες πληροφορίες.']);
        }
        Log::channel('items')->info('User '.auth()->user()->name.' changed item\'s with id '.$item->id.' given status.');
        return response()->json([
            'status'=>'success',
            'message' => 'Το αντικείμενο ενημερώθηκε επιτυχώς.',
            'data' => [
                'given_away' => (bool)$item->given_away,
                'in_local_storage' => (bool)$item->in_local_storage,
            ],
        ]);  
    }

    public function inLocalStorage(Request $request, Item $item){
        Gate::authorize('update', $item);
        $checked = filter_var($request->input('checked'), FILTER_VALIDATE_BOOLEAN);
        $item->in_local_storage = $checked ? 1 : 0;
        if ($item->in_local_storage) {
            // If now in local storage, it cannot be marked as given
            $item->given_away = 0;
        }
        try{
            $item->save();
        }
        catch(\Exception $e){
            Log::channel('items')->error('User '.auth()->user()->name.' failed to change item\'s with id '.$item->id.' in_local_storage. Error: '.$e->getMessage());
            return response()->json(['status'=>'error','message' => 'Κάποιο σφάλμα συνέβη. Ελέγξτε το αρχείο log/items.log για περισσότερες πληροφορίες.']);
        }
        Log::channel('items')->info('User '.auth()->user()->name.' changed item\'s with id '.$item->id.' in_local_storage.');
        return response()->json([
            'status'=>'success',
            'message' => 'Η κατάσταση "Αποθήκη ΜΨΔ" ενημερώθηκε επιτυχώς.',
            'data' => [
                'given_away' => (bool)$item->given_away,
                'in_local_storage' => (bool)$item->in_local_storage,
            ],
        ]);
    }
}
