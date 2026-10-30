<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [BoardController::class, 'home'])->name('home');
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
