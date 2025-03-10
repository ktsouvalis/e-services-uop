<?php

namespace App\Http\Controllers;

use App\Models\AImodel;
use Illuminate\Http\Request;

class AImodelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $aimodels = AImodel::all();
        return view('aimodels.index', compact('aimodels'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        if($request->has('properties')){
            foreach($request->properties as $key => $value){
                $request[$value] = true;
            }
        }
        AImodel::create($request->all());

        return redirect()->route('aimodels.index')->with('success', 'AI Model created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AImodel $aimodel)
    {
        return view('aimodels.edit', compact('aimodel'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AImodel $aimodel)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $aimodel->fill($request->all());

        if ($request->has('properties')) {
            foreach ($aimodel->properties() as $propertyName => $propertyValue) {
                $aimodel->$propertyName = in_array($propertyName, $request->properties);
            }
        } else {
            foreach ($aimodel->properties() as $propertyName => $propertyValue) {
                $aimodel->$propertyName = false;
            }
        }

        $aimodel->save();

        return back()->with('success', 'AI Model updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AImodel $aimodel)
    {
        $aimodel->delete();

        return redirect()->route('aimodels.index')->with('success', 'AI Model deleted successfully.');
    }
}
