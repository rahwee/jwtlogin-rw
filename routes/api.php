<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::group(["prefix" => "v1"], function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

Route::get('product', [ProductController::class, 'index']);

// Route::group(['middleware' => ['auth:api']], function () {
//     Route::get('product', [ProductController::class, 'index']);
// });

