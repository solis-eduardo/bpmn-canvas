<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\KeycloakLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DiagramaColaboradorController;
use App\Http\Controllers\DiagramaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [LoginController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware(['guest', 'throttle:5,1']);
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/login/keycloak', [KeycloakLoginController::class, 'redirect'])->name('login.keycloak');
Route::get('/login/keycloak/callback', [KeycloakLoginController::class, 'callback'])->name('login.keycloak.callback');

Route::middleware('auth')->group(function () {
    Route::view('/home', 'home')->name('home');

    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');

    Route::get('/diagramas', [DiagramaController::class, 'index'])->name('diagramas.index');
    Route::post('/diagramas', [DiagramaController::class, 'store'])->name('diagramas.store');
    Route::get('/diagramas/{slug}/conteudo', [DiagramaController::class, 'content'])->name('diagramas.content');
    Route::put('/diagramas/{slug}/conteudo', [DiagramaController::class, 'save'])->name('diagramas.save');
    Route::put('/diagramas/{slug}', [DiagramaController::class, 'rename'])->name('diagramas.rename');
    Route::delete('/diagramas/{slug}', [DiagramaController::class, 'destroy'])->name('diagramas.destroy');

    Route::get('/diagramas/{slug}/colaboradores', [DiagramaColaboradorController::class, 'index'])->name('diagramas.colaboradores.index');
    Route::post('/diagramas/{slug}/colaboradores', [DiagramaColaboradorController::class, 'store'])->name('diagramas.colaboradores.store');
    Route::put('/diagramas/{slug}/colaboradores/{user}', [DiagramaColaboradorController::class, 'update'])->name('diagramas.colaboradores.update');
    Route::delete('/diagramas/{slug}/colaboradores/{user}', [DiagramaColaboradorController::class, 'destroy'])->name('diagramas.colaboradores.destroy');

    Route::post('/diagramas/{slug}/heartbeat', [DiagramaController::class, 'heartbeat'])->name('diagramas.heartbeat');
    Route::delete('/diagramas/{slug}/lock', [DiagramaController::class, 'unlock'])->name('diagramas.unlock');
    Route::post('/diagramas/{slug}/fork', [DiagramaController::class, 'fork'])->name('diagramas.fork');

    Route::get('/diagramas/{slug}/versoes', [DiagramaController::class, 'versoes'])->name('diagramas.versoes');
    Route::post('/diagramas/{slug}/versoes/{versao}/restaurar', [DiagramaController::class, 'restaurarVersao'])->name('diagramas.versoes.restaurar');
    Route::get('/diagramas/{slug}', [DiagramaController::class, 'show'])->name('diagramas.show');
});
