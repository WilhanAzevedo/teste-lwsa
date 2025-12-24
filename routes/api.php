<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/inventory', [App\Http\Controllers\InventoryController::class, 'storeInventory']);
Route::get('/inventory', [App\Http\Controllers\InventoryController::class, 'getInventory']);
