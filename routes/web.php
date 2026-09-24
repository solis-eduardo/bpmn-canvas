<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DiagramaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [LoginController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::view('/home', 'home')->name('home');

    Route::post('/admin/users', [UserController::class, 'store']);
    Route::put('/admin/users/{user}', [UserController::class, 'update']);

    Route::get('/diagramas', [DiagramaController::class, 'index'])->name('diagramas.index');
    Route::post('/diagramas', [DiagramaController::class, 'store'])->name('diagramas.store');
    Route::get('/diagramas/{slug}/conteudo', [DiagramaController::class, 'content'])->name('diagramas.content');
    Route::put('/diagramas/{slug}/conteudo', [DiagramaController::class, 'save'])->name('diagramas.save');
    Route::put('/diagramas/{slug}', [DiagramaController::class, 'rename'])->name('diagramas.rename');
    Route::delete('/diagramas/{slug}', [DiagramaController::class, 'destroy'])->name('diagramas.destroy');
    Route::get('/diagramas/{slug}', [DiagramaController::class, 'show'])->name('diagramas.show');
});
