<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ChatbotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $chatbots = auth()->user()->chatbots;
        return view('chatbots.index', compact('chatbots'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'api_key' => 'required|string|max:255',
            'ai_model_id' => 'required|exists:ai_models,id',
        ]);
        $request['user_id'] = auth()->id();
        $request['api_key'] = Crypt::encryptString($request->api_key);

        Chatbot::create($request->all());

        return redirect()->route('chatbots.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Chatbot $chatbot)
    {
        return view('chatbots.show', compact('chatbot'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Chatbot $chatbot)
    {
        $aiModels = AiModel::all();
        return view('chatbots.edit', compact('chatbot', 'aiModels'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Chatbot $chatbot)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'api_key' => 'required|string|max:255',
            'ai_model_id' => 'required|exists:ai_models,id',
        ]);

        $request['api_key'] = Crypt::encryptString($request->api_key);

        $chatbot->update($request->all());

        return redirect()->route('chatbots.index');
    }

    public function updateHistory(Request $request, Chatbot $chatbot)
    {
        $history = $request->input('history');
        $chatbot->history = json_encode($history);
        $chatbot->save();

        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Chatbot $chatbot)
    {
        $chatbot->delete();

        return redirect()->route('chatbots.index');
    }
}
