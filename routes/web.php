<?php

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

Route::get('login', 'LoginController@login')->name('login');
Route::get('callback', 'LoginController@callback')->name('callback');
Route::any('logout', 'LoginController@logout')->name('logout');

Route::get('/home', 'HomeController@index')->name('home');
