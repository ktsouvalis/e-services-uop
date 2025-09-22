<?php

use Carbon\Carbon;
use App\Models\City;
use App\Models\Menu;
use App\Models\Chatbot;
use App\Models\Department;
use App\Events\MessageSent;
use App\Models\Sheetmailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\LogReaderEnabled;
use App\Http\Controllers\MailerController;
use App\Http\Controllers\AImodelController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LogReaderController;
use App\Http\Controllers\SheetmailerController;
use App\Http\Controllers\NotificationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('/chat/send-message', function (Request $request) {
    $text = request()->input('content'); //the name of the input field from the js script!
    $user = auth()->user();
    MessageSent::dispatch($text, $user->name);
})->middleware('auth')->name('chat.send-message');

Route::resource('/menus', MenuController::class);

Route::post('menus/toggle-enabled/{menu}', [MenuController::class, 'toggleEnabled'])->name('menus.toggle-enabled');


Route::resource('/mailers', MailerController::class)->middleware('auth');

Route::group(['prefix' => 'mailers','middleware'=>'auth'], function(){
    Route::get('/{mailer}/download_f/{index}', [MailerController::class, 'download_file'])->name('mailers.download_file');

    Route::post('/{mailer}/delete_f/{index}', [MailerController::class, 'delete_file'])->name('mailers.delete_file');

    Route::post('/{mailer}/clean_f', [MailerController::class, 'clean_storage'])->name('mailers.clean_storage');

    Route::post(('/{mailer}/upload_files'), [MailerController::class, 'upload_files'])->name('mailers.upload_files');

    Route::get('/{mailer}/review', [MailerController::class, 'review'])->name('mailers.review');

    Route::post('/{mailer}/send/{index}/{department}', [MailerController::class, 'send'])->name('mailers.send');

    Route::post('/{mailer}/send_all/', [MailerController::class, 'send_all'])->name('mailers.send_all');
});

Route::resource('/sheetmailers', SheetmailerController::class)->middleware('auth');

Route::group(['prefix' => 'sheetmailers','middleware'=>'auth'], function(){
    Route::post('/{sheetmailer}/upload_file', [SheetmailerController::class, 'upload_file'])->name('sheetmailers.upload_file');

    Route::post('/{sheetmailer}/comma_mails', [SheetmailerController::class, 'comma_mails'])->name('sheetmailers.comma_mails');

    Route::get('/{sheetmailer}/confirm', function (Sheetmailer $sheetmailer) {
        return view('sheetmailers.confirm', compact('sheetmailer'));
    })->name('sheetmailers.confirm')->middleware('can:view,sheetmailer');

    Route::get('/{sheetmailer}/preview', [SheetmailerController::class, 'preview'])->name('sheetmailers.preview');

    Route::post('/{sheetmailer}/send', [SheetmailerController::class, 'send'])->name('sheetmailers.send');
});

Route::group(['prefix' => 'log-reader', 'middleware' => ['auth', LogReaderEnabled::class]], function () {
    Route::view('/', 'log-reader.main')->name('log-reader');
    Route::post('/read_logs', [LogReaderController::class, 'read'])->name('log-reader.read-logs');
});


Route::resource('/items', ItemController::class)->middleware('auth');

Route::group(['prefix' => 'items', 'middleware'=>'auth'], function(){
    Route::get('/{item}/download_f', [ItemController::class, 'download_file'])->name('items.download_file');

    Route::post('/{item}/given', [ItemController::class, 'given'])->name('items.given');

    Route::delete('/{item}/delete_f', [ItemController::class, 'delete_file'])->name('items.delete_file');
});
Route::get('/extract_items', [ItemController::class, 'extract'])->name('items.extract');

Route::resource('users', UserController::class)->middleware('auth');

Route::resource('aimodels', AImodelController::class);

Route::resource('/chatbots', ChatbotController::class)->middleware('auth');

Route::group(['prefix' => 'chatbots', 'middleware'=>'auth'], function(){
    Route::get('/{chatbot}/get-history', [ChatbotController::class, 'getHistory'])->name('chatbots.get-history');

    Route::post('/{chatbot}/user-update-history', [ChatbotController::class, 'userUpdateHistory'])->name('chatbots.user-update-history');

    Route::post('/{chatbot}/assistant-update-history', [ChatbotController::class, 'assistantUpdateHistory'])->name('chatbots.assistant-update-history');

    Route::post('/{chatbot}/store-developer-messages', [ChatbotController::class, 'storeDeveloperMessages'])->name('chatbots.store-developer-messages');

    Route::post('/{chatbot}/store-system-messages', [ChatbotController::class, 'storeSystemMessages'])->name('chatbots.store-system-messages');

    Route::post('/{chatbot}/submit-audio', [ChatbotController::class, 'submitAudio'])->name('chatbots.submit-audio');

    Route::post('/{chatbot}/transcribe-audio', [ChatbotController::class, 'transcribeAudio'])->name('chatbots.transcribe-audio');

    Route::get('/{chatbot}/download-audio', function(Chatbot $chatbot){
        $path = storage_path("/app/private/whisper".$chatbot->id."/".json_decode($chatbot->history)->file);
        return response()->download($path);
    })->name('chatbots.download-audio');
});

Route::resource('notifications', NotificationController::class)->middleware('auth');

Route::group(['prefix' => 'notifications'], function(){
    Route::post('/mark_as_read/{notification}', [NotificationController::class, 'markNotificationAsRead'])->name('notifications.mark_as_read');

    Route::post('/mark_all_as_read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark_all_as_read');

    Route::post('/delete_all/{user}', [NotificationController::class, 'deleteAll'])->name('notifications.delete_all');
});

Route::get('/get_logs', function(Request $request){
    if(auth()->user()->admin){
        $date = $request->query('date');
        $formattedDate = Carbon::parse($date)->format('Y-m-d');
        $logDirectory = storage_path('logs');
        $logFiles = glob($logDirectory . '/*' . $formattedDate . '.log');
        if (empty($logFiles)) {
            return back()->with('error', 'Δεν υπάρχουν αρχεία καταγραφής για την επιλεγμένη ημερομηνία (' . $formattedDate . ')');
        }
    
        $zipFile = tempnam(sys_get_temp_dir(), 'logs') . '.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
            return back()->with('error', 'Αποτυχία δημιουργίας αρχείου zip');
        }
    
        foreach ($logFiles as $logFile) {
            $zip->addFile($logFile, basename($logFile));
        }
    
        if ($zip->close() !== TRUE) {
            return back()->with('error', 'Αποτυχία κλεισίματος αρχείου zip');
        }
    
        if (!file_exists($zipFile)) {
            return back()->with('error', 'Δεν υπάρχει το αρχείο zip');
        }
        $filename = 'logs_' . $formattedDate . '.zip';
        // Only end (clean) output buffer if one exists to avoid warnings
        if (ob_get_level() > 0) {
            @ob_end_clean();
        }
        return response()->download($zipFile, $filename)->deleteFileAfterSend();
    }
    else {
        return back()->with('error', 'Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη λειτουργία.');
    }
});




require __DIR__.'/auth.php';
