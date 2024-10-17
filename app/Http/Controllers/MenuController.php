<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $menus = Menu::all();
        return view('menus.index', compact('menus'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Menu::class);
        

        try{
            $menu = Menu::create($request->input());
        }
        catch(Exception $e){
            Log::channel('menus')->error($e->getMessage());
            return redirect()->back()->with('error', 'Menu not created. Check today\'s menus log for more information.');
        }
        Log::channel('menus')->info('Menu created successfully');
        return redirect()->route('menus.index')->with('success', 'Menu created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Menu $menu)
    {
        Gate::authorize('update', $menu);

        return view('menus.edit', [
            'menu' => $menu,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Menu $menu)
    {
        Gate::authorize('update', $menu);

        $data_to_update = $request->input();
        try{
            $menu->update($data_to_update);
        }
        catch(\Exception $e){
            Log::channel('menus')->error($e->getMessage());
            return redirect()->back()->with('error', 'Menu not updated. Check today\'s menus log for more information.');
        }
        Log::channel('menus')->info('Menu updated successfully');
        return redirect()->back()->with('success', 'Menu updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Menu $menu)
    {
        Gate::authorize('delete',$menu );
        try{
            $menu->delete();
        }
        catch(\Exception $e){
            Log::channel('menus')->error($e->getMessage());
            return redirect()->back()->with('error', 'Menu not deleted. Check today\'s menus log for more information.');
        }
        Log::channel('menus')->info('Menu deleted successfully');
        return redirect()->route('menus.index')->with('success', 'Menu deleted successfully.');
    }
}
