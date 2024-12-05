<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/menus', function (Request $request) {
    // dd($request->headers->all());
    return response()->json([
        'menus' => \App\Models\Menu::all()
    ]);
})->middleware('auth:sanctum', 'ability:create-menu');
// });