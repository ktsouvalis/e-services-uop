<?php

namespace App\Http\Controllers;

use OpenAI;
use App\Models\AiModel;
use App\Models\Chatbot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
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
        Gate::authorize('view', $chatbot);
        if($chatbot->aiModel->accepts_chat){
            return view('chatbots.show-chat', compact('chatbot'));
        }
        else{
            if($chatbot->aiModel->accepts_audio){
                return view('chatbots.show-audio', compact('chatbot'));
            }
        }
    }

    public function userUpdateHistory(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);
        $history = $request->input('history');
        $chatbot->history = json_encode($history);
        try{
            $chatbot->save();
        }
        catch(\Exception $e){
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }

        $parameters = ['messages' => $history, 'model' => $chatbot->aiModel->name];
        if ($request->input('reasoning_effort')) {
            $parameters['reasoning_effort'] = $request->input('reasoning_effort');
        }

        try {
            // Decrypt the API key
            $apiKey = Crypt::decryptString($chatbot->api_key);
            $client = OpenAI::client($apiKey);
            $response = $client->chat()->create($parameters);
            if ($response->choices) {
                $assistantMessage = $response->choices[0]->message;
                return response()->json(['assistantMessage' => $assistantMessage]);
            } 
            else{
                return response()->json(['error' => 'Failed to get a response from OpenAI'], $response->status());
            }
        } 
        catch (\Exception $e){
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function assistantUpdateHistory(Request $request, Chatbot $chatbot)
    {
        Gate::authorize('view', $chatbot);
        $history = $request->input('history');
        $chatbot->history = json_encode($history);
        $chatbot->save();

        return response()->json(['success' => 'History updated successfully']);
    }

    public function storeDeveloperMessages(Request $request, Chatbot $chatbot){
        Gate::authorize('view', $chatbot);
        $developerMessages = explode(",", $request->input('developer_messages'));
        $currentHistory = $chatbot->history ? json_decode($chatbot->history, true) : [];

        foreach ($developerMessages as $message) {
            array_unshift($currentHistory, ["role" => "developer", "content" => $message]);
        }

        $chatbot->history = json_encode($currentHistory);
        $chatbot->save();

        return back()->with(['success' => 'Developer messages saved successfully']);
    }

    public function storeSystemMessages(Request $request, Chatbot $chatbot){
        Gate::authorize('view', $chatbot);
        $systemMessages = explode(",", $request->input('system_messages'));
        $currentHistory = $chatbot->history ? json_decode($chatbot->history, true) : [];

        foreach ($systemMessages as $message) {
            array_unshift($currentHistory, ["role" => "system", "content" => $message]);
        }

        $chatbot->history = json_encode($currentHistory);
        $chatbot->save();

        return back()->with(['success' => 'Developer messages saved successfully']);
    }

    public function submitAudio(Request $request, Chatbot $chatbot){
        Gate::authorize('view', $chatbot);
        $request->validate([
            'audio_file' => 'required|file|mimes:mp3,wav',
        ]);

        $audio = $request->file('audio_file');
        $path = $audio->storeAs('whisper'.$chatbot->id, $audio->getClientOriginalName());

        // Save the filename in the chatbot's history
        $filename = basename($path);
        $chatbot->history = json_encode(["file"=>$filename, "transcription"=>null]);
        $chatbot->save();

        return back()->with(['success' => 'Audio file uploaded successfully']);
    }  
    
    public function transcribeAudio(Request $request, Chatbot $chatbot){
        Gate::authorize('view', $chatbot);
        $file_name = json_decode($chatbot->history)->file;
        $file_path = storage_path("/app/private/whisper".$chatbot->id."/".$file_name);
        $file_to_request = fopen($file_path, 'r');

        $parameters = ['file'=>$file_to_request, 'model' => 'whisper-1', 'language' => 'el', 'temperature' => 0.1];
        if($request->input('segments')){
            $parameters['response_format'] = "verbose_json";
            $parameters['timestamp_granularities'] = ["segment"];
        }
        
        $apiKey = Crypt::decryptString($chatbot->api_key);   
        $client = OpenAI::client($apiKey);
        $response = $client->audio()->transcribe($parameters);
        
        if ($response->text) {
            $transcription = ["text" => $response->text];
            if ($request->input('segments')) {
                $segments = [];
                foreach ($response->segments as $segment) {
                    $segments[] = [
                        "start" => $segment->start,
                        "end" => $segment->end,
                        "text" => $segment->text
                    ];
                }
                $transcription["segments"] = $segments;
            }
    
            $chatbot->history = json_encode([
                "file" => $file_name,
                "transcription" => $transcription
            ]);
            $chatbot->save();
            return back()->with(['success' => 'Audio file uploaded and transcribed successfully']);
        } else {
            return back()->with(['error' => 'Failed to transcribe audio file']);
        }
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
