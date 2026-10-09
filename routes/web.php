<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountNotificationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\KeycloakLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CollaboratorController;
use App\Http\Controllers\DiagramContentController;
use App\Http\Controllers\DiagramController;
use App\Http\Controllers\DiagramOwnerController;
use App\Http\Controllers\DiagramTagController;
use App\Http\Controllers\DiagramWorkspaceController;
use App\Http\Controllers\ForkController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LockController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceOwnerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('home')
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
    Route::get('/account/notifications', fn () => redirect()->route('account.edit'))->name('account.notifications.edit');
    Route::put('/account/notifications', [AccountNotificationController::class, 'update'])->name('account.notifications.update');
    Route::get('/home', HomeController::class)->name('home');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/timezone', [AccountController::class, 'detectTimezone'])->name('account.timezone.detect');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');

    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/history', [NotificationController::class, 'history'])->name('notifications.history');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/diagrams', [DiagramController::class, 'index'])->name('diagrams.index');
    Route::get('/diagrams/create', [DiagramController::class, 'create'])->name('diagrams.create');
    Route::post('/diagrams', [DiagramController::class, 'store'])->name('diagrams.store');
    Route::get('/diagrams/{slug}/edit', [DiagramTagController::class, 'edit'])->name('diagrams.edit');
    Route::get('/diagrams/{slug}/content', [DiagramContentController::class, 'show'])->name('diagrams.content');
    Route::put('/diagrams/{slug}/content', [DiagramContentController::class, 'update'])->name('diagrams.save');
    Route::put('/diagrams/{slug}', [DiagramController::class, 'update'])->name('diagrams.rename');
    Route::delete('/diagrams/{slug}', [DiagramController::class, 'destroy'])->name('diagrams.destroy');

    Route::get('/diagrams/{slug}/collaborators', [CollaboratorController::class, 'index'])->name('diagrams.collaborators.index');
    Route::post('/diagrams/{slug}/collaborators', [CollaboratorController::class, 'store'])->name('diagrams.collaborators.store');
    Route::put('/diagrams/{slug}/collaborators/{user}', [CollaboratorController::class, 'update'])->name('diagrams.collaborators.update');
    Route::delete('/diagrams/{slug}/collaborators/{user}', [CollaboratorController::class, 'destroy'])->name('diagrams.collaborators.destroy');

    Route::put('/diagrams/{slug}/tags', [DiagramTagController::class, 'sync'])->name('diagrams.tags.sync');
    Route::post('/diagrams/{slug}/tags', [DiagramTagController::class, 'store'])->name('diagrams.tags.store');
    Route::delete('/diagrams/{slug}/tags/{tag}', [DiagramTagController::class, 'destroy'])->name('diagrams.tags.destroy');

    Route::put('/diagrams/{slug}/owner', [DiagramOwnerController::class, 'update'])->name('diagrams.owner.transfer');

    Route::put('/diagrams/{slug}/workspace', [DiagramWorkspaceController::class, 'update'])->name('diagrams.workspace.store');
    Route::delete('/diagrams/{slug}/workspace', [DiagramWorkspaceController::class, 'destroy'])->name('diagrams.workspace.destroy');

    Route::get('/diagrams/{slug}/lock', [LockController::class, 'show'])->name('diagrams.lock.show');
    Route::post('/diagrams/{slug}/lock', [LockController::class, 'store'])->name('diagrams.lock.store');
    Route::patch('/diagrams/{slug}/lock', [LockController::class, 'update'])->name('diagrams.lock.update');
    Route::put('/diagrams/{slug}/lock', [LockController::class, 'transfer'])->name('diagrams.lock.transfer');
    Route::delete('/diagrams/{slug}/lock', [LockController::class, 'destroy'])->name('diagrams.lock.destroy');
    Route::post('/diagrams/{slug}/fork', [ForkController::class, 'store'])->name('diagrams.fork');

    Route::get('/diagrams/{slug}/versions', [VersionController::class, 'versions'])->name('diagrams.versions');
    Route::get('/diagrams/{slug}/versions/compare', [VersionController::class, 'compareVersions'])->name('diagrams.versions.compare');
    Route::get('/diagrams/{slug}/versions/{version}/content', [VersionController::class, 'versionContent'])->name('diagrams.versions.content');
    Route::post('/diagrams/{slug}/versions/{version}/restore', [VersionController::class, 'restoreVersion'])->name('diagrams.versions.restore');
    Route::put('/diagrams/{slug}/versions/{version}/rename', [VersionController::class, 'renameVersion'])->name('diagrams.versions.rename');
    Route::get('/diagrams/{slug}', [DiagramController::class, 'show'])->name('diagrams.show');

    Route::get('/diagrams/{slug}/shares', [ShareController::class, 'index'])->name('shares.index');
    Route::post('/diagrams/{slug}/shares', [ShareController::class, 'store'])->name('shares.store');
    Route::delete('/shares/{share}', [ShareController::class, 'destroy'])->name('shares.destroy');

    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/workspaces/{workspace}/edit', [WorkspaceController::class, 'edit'])->name('workspaces.edit');
    Route::put('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::put('/workspaces/{workspace}/owner', [WorkspaceOwnerController::class, 'update'])->name('workspaces.owner.transfer');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

    Route::put('/workspaces/{workspace}/members', [MemberController::class, 'sync'])->name('workspaces.members.sync');
    Route::post('/workspaces/{workspace}/members', [MemberController::class, 'store'])->name('workspaces.members.store');
    Route::delete('/workspaces/{workspace}/members/{user}', [MemberController::class, 'destroy'])->name('workspaces.members.destroy');
    Route::delete('/workspaces/{workspace}/leave', [MemberController::class, 'leave'])->name('workspaces.leave');
});
