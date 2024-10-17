<?php

use App\Models\City;
use App\Models\Department;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MailerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LogReaderController;
use App\Http\Controllers\SheetmailerController;


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

Route::resource('/menus', MenuController::class);

Route::resource('/mailers', MailerController::class);

Route::group(['prefix' => 'mailers'], function(){
    Route::get('/{mailer}/download_f/{index}', [MailerController::class, 'download_file'])->name('mailers.download_file');

    Route::post('/{mailer}/delete_f/{index}', [MailerController::class, 'delete_file'])->name('mailers.delete_file');

    Route::post('/{mailer}/clean_f', [MailerController::class, 'clean_storage'])->name('mailers.clean_storage');

    Route::post(('/{mailer}/upload_files'), [MailerController::class, 'upload_files'])->name('mailers.upload_files');

    Route::get('/{mailer}/review', [MailerController::class, 'review'])->name('mailers.review');

    Route::post('/{mailer}/send/{index}/{department}', [MailerController::class, 'send'])->name('mailers.send');

    Route::post('/{mailer}/send_all/', [MailerController::class, 'send_all'])->name('mailers.send_all');
});

Route::resource('/sheetmailers', SheetmailerController::class);

Route::group(['prefix' => 'sheetmailers'], function(){
    Route::post('/{sheetmailer}/upload_file', [SheetmailerController::class, 'upload_file'])->name('sheetmailers.upload_file');

    Route::post('/{sheetmailer}/comma_mails', [SheetmailerController::class, 'comma_mails'])->name('sheetmailers.comma_mails');

    Route::view('/{sheetmailer}/confirm', 'sheetmailers.confirm')->name('sheetmailers.confirm');

    Route::get('/{sheetmailer}/preview', [SheetmailerController::class, 'preview'])->name('sheetmailers.preview');

    Route::post('/{sheetmailer}/send', [SheetmailerController::class, 'send'])->name('sheetmailers.send');
});

Route::group(['prefix' => 'log-reader'], function(){
    Route::view('/', 'log-reader.main')->name('log-reader');

    Route::post('/read_logs', [LogReaderController::class, 'read'])->name('log-reader.read-logs');
});


require __DIR__.'/auth.php';
