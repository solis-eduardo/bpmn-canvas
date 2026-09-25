<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\KeycloakLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DiagramCollaboratorController;
use App\Http\Controllers\DiagramController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('diagrams.index')
        : redirect()->route('login');
});

Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/login', [LoginController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware(['guest', 'throttle:5,1']);
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/login/keycloak', [KeycloakLoginController::class, 'redirect'])->name('login.keycloak');
Route::get('/login/keycloak/callback', [KeycloakLoginController::class, 'callback'])->name('login.keycloak.callback');

Route::middleware('signed')->group(function () {
    Route::get('/shares/{share}', [PublicShareController::class, 'show'])->name('shares.show');
    Route::get('/shares/{share}/download/{format}', [PublicShareController::class, 'download'])->name('shares.download');
});

Route::middleware('auth')->group(function () {
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');

    Route::get('/diagrams', [DiagramController::class, 'index'])->name('diagrams.index');
    Route::post('/diagrams', [DiagramController::class, 'store'])->name('diagrams.store');
    Route::get('/diagrams/{slug}/content', [DiagramController::class, 'content'])->name('diagrams.content');
    Route::put('/diagrams/{slug}/content', [DiagramController::class, 'save'])->name('diagrams.save');
    Route::put('/diagrams/{slug}', [DiagramController::class, 'rename'])->name('diagrams.rename');
    Route::delete('/diagrams/{slug}', [DiagramController::class, 'destroy'])->name('diagrams.destroy');

    Route::get('/diagrams/{slug}/collaborators', [DiagramCollaboratorController::class, 'index'])->name('diagrams.collaborators.index');
    Route::post('/diagrams/{slug}/collaborators', [DiagramCollaboratorController::class, 'store'])->name('diagrams.collaborators.store');
    Route::put('/diagrams/{slug}/collaborators/{user}', [DiagramCollaboratorController::class, 'update'])->name('diagrams.collaborators.update');
    Route::delete('/diagrams/{slug}/collaborators/{user}', [DiagramCollaboratorController::class, 'destroy'])->name('diagrams.collaborators.destroy');

    Route::post('/diagrams/{slug}/heartbeat', [DiagramController::class, 'heartbeat'])->name('diagrams.heartbeat');
    Route::post('/diagrams/{slug}/lock/request', [DiagramController::class, 'requestLock'])->name('diagrams.lock.request');
    Route::delete('/diagrams/{slug}/lock', [DiagramController::class, 'unlock'])->name('diagrams.unlock');
    Route::post('/diagrams/{slug}/fork', [DiagramController::class, 'fork'])->name('diagrams.fork');

    Route::get('/diagrams/{slug}/versions', [VersionController::class, 'versions'])->name('diagrams.versions');
    Route::get('/diagrams/{slug}/versions/{version}/content', [VersionController::class, 'versionContent'])->name('diagrams.versions.content');
    Route::post('/diagrams/{slug}/versions/{version}/restore', [VersionController::class, 'restoreVersion'])->name('diagrams.versions.restore');
    Route::put('/diagrams/{slug}/versions/{version}/rename', [VersionController::class, 'renameVersion'])->name('diagrams.versions.rename');
    Route::get('/diagrams/{slug}', [DiagramController::class, 'show'])->name('diagrams.show');

    Route::get('/diagrams/{slug}/shares', [ShareController::class, 'index'])->name('shares.index');
    Route::post('/diagrams/{slug}/shares', [ShareController::class, 'store'])->name('shares.store');
    Route::delete('/shares/{share}', [ShareController::class, 'destroy'])->name('shares.destroy');
});
