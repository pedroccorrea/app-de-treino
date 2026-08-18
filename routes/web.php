<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutSessionController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/workouts', [WorkoutController::class, 'index'])->name('workouts.index');
    Route::get('/workouts/create', [WorkoutController::class, 'create'])->name('workouts.create');
    Route::post('/workouts', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::get('/workouts/{workout}', [WorkoutController::class, 'show'])->name('workouts.show');
    Route::patch('/workouts/{workout}/reorder', [WorkoutController::class, 'reorder'])->name('workouts.reorder');
    Route::post('/workouts/{workout}/start', [WorkoutSessionController::class, 'start'])->name('workouts.start');

    Route::get('/workout-sessions/{session}', [WorkoutSessionController::class, 'show'])->name('workout-sessions.show');
    Route::post('/workout-sessions/{session}/sets', [WorkoutSessionController::class, 'logSet'])->name('workout-sessions.sets.store');
    Route::post('/workout-sessions/{session}/finish', [WorkoutSessionController::class, 'finish'])->name('workout-sessions.finish');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
