<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::view('/', 'welcome');

Route::get('login', [LoginController::class, 'login'])->name('login');
Route::get('callback', [LoginController::class, 'callback'])->name('callback');
Route::any('logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/home', HomeController::class)
     ->name('home')
     ->middleware('auth');
