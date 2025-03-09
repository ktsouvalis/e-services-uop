<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

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

    public function userUpdateHistory(Request $request, Chatbot $chatbot)
    {
        $history = $request->input('history');
        $chatbot->history = json_encode($history);
        $chatbot->save();

        // Decrypt the API key
        $apiKey = Crypt::decryptString($chatbot->api_key);

        // Send the API request to OpenAI
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
         ])->withoutVerifying()->post('https://api.openai.com/v1/chat/completions', [
            'model' => $chatbot->aiModel->name,
            'messages' => $history,
        ]);

        $assistantMessage = $response->json()['choices'][0]['message'];

        // Update the history with the assistant's response
        $newHistory = array_merge($history, [$assistantMessage]);
        $chatbot->history = json_encode($newHistory);
        $chatbot->save();

        return response()->json(['assistantMessage' => $assistantMessage]);
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
