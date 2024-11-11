<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    public function initializeMiddleware(): void
    {
        $this->middleware('auth');
    }
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
        return view('items.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {        
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
        Item::create($incoming);

        return redirect()->route('items.index')->with('success', 'Item created successfully.');      
    }
    

    public function download_file(Request $request, Item $item){
        ob_end_clean();
        return response()->download(storage_path('app/private/items/'.$item->file_path));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        return view('items.edit')->with('item', $item);
    }   
    
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Item $item)
    {
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
        $item->update($incoming);
        return redirect()->route('items.index')->with('success', 'Item updated successfully.'); 
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        $item->delete();
        return redirect()->route('items.index')->with('success', 'Item deleted successfully.');
    }
}
