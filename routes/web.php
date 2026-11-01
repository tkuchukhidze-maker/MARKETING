<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\Reporting\PlanController;
use App\Http\Controllers\Reporting\ReportController;
use App\Http\Controllers\Reporting\SourceController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [BoardController::class, 'home'])->name('home');
    Route::get('/me', [WorkspaceController::class, 'index'])->name('workspace');
    Route::post('/tasks/{task}/start', [TaskController::class, 'start'])->name('tasks.start');
    Route::post('/tasks/{task}/ready', [TaskController::class, 'ready'])->name('tasks.ready');
    Route::post('/tasks/{task}/channels/{channel}/publish', [TaskController::class, 'publish'])->name('tasks.publish');
    Route::delete('/tasks/{task}/channels/{channel}/publish', [TaskController::class, 'unpublish'])->name('tasks.unpublish');
    Route::get('/v/{venue}', [BoardController::class, 'board'])->name('board');
    Route::get('/v/{venue}/calendar', [BoardController::class, 'calendar'])->name('calendar');

    Route::post('/v/{venue}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/quick', [TaskController::class, 'quick'])->name('tasks.quick');
    Route::post('/tasks/{task}/approve', [TaskController::class, 'approve'])->name('tasks.approve');
    Route::post('/tasks/{task}/revision', [TaskController::class, 'revision'])->name('tasks.revision');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comment');
    Route::post('/tasks/{task}/assets', [TaskController::class, 'upload'])->name('tasks.upload');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::get('/assets/{asset}', [TaskController::class, 'asset'])->name('assets.show');

    Route::get('/team', [TeamController::class, 'index'])->name('team');
    Route::post('/team/users', [TeamController::class, 'storeUser'])->name('team.users.store');
    Route::patch('/team/users/{user}', [TeamController::class, 'updateUser'])->name('team.users.update');

    Route::get('/contacts', [TeamController::class, 'contacts'])->name('contacts');
    Route::post('/contacts', [TeamController::class, 'storeContact'])->name('contacts.store');
    Route::put('/contacts/{contact}', [TeamController::class, 'updateContact'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [TeamController::class, 'destroyContact'])->name('contacts.destroy');
});

// ---- CMO reporting: admin (CMO) and marketing lead only
Route::middleware(['auth', 'can:reports'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/export', [ReportController::class, 'export'])->name('export');

    Route::get('/sources', [SourceController::class, 'index'])->name('sources');
    Route::post('/sources', [SourceController::class, 'store'])->name('sources.store');
    Route::put('/sources/{source}', [SourceController::class, 'update'])->name('sources.update');
    Route::delete('/sources/{source}', [SourceController::class, 'destroy'])->name('sources.destroy');
    Route::post('/sources/{source}/sync', [SourceController::class, 'sync'])->name('sources.sync');
    Route::post('/sources/{source}/import', [SourceController::class, 'import'])->name('sources.import');

    Route::get('/plan', [PlanController::class, 'index'])->name('plan');
    Route::post('/plan/costs', [PlanController::class, 'storeCost'])->name('costs.store');
    Route::delete('/plan/costs/{cost}', [PlanController::class, 'destroyCost'])->name('costs.destroy');
    Route::post('/plan/targets', [PlanController::class, 'saveTargets'])->name('targets.save');
    Route::put('/plan/brands/{venue}', [PlanController::class, 'saveBrand'])->name('brands.save');
});
