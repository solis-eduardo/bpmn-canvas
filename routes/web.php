<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\KeycloakLoginController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [LoginController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/login/keycloak', [KeycloakLoginController::class, 'redirect'])->name('login.keycloak');
Route::get('/login/keycloak/callback', [KeycloakLoginController::class, 'callback'])->name('login.keycloak.callback');

Route::middleware('auth')->group(function () {
    Route::view('/home', 'home')->name('home');

    Route::post('/admin/users', [UserController::class, 'store']);
    Route::put('/admin/users/{user}', [UserController::class, 'update']);
});
