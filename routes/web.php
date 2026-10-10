<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\SyncStateController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutHistoryController;
use App\Http\Controllers\WorkoutProgramController;
use App\Http\Controllers\WorkoutScannerController;
use App\Http\Controllers\WorkoutSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/manifest.json', function () {
    return response(file_get_contents(resource_path('pwa/manifest.json')), 200, [
        'Content-Type' => 'application/manifest+json',
    ]);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    return response(file_get_contents(resource_path('pwa/sw.js')), 200, [
        'Content-Type' => 'application/javascript',
        // Os navegadores já ignoram cache HTTP ao checar o script principal
        // do Service Worker por padrão — mas isso já variou entre versões, e
        // um proxy/CDN no meio do caminho não segue essa regra. Sem este
        // cabeçalho explícito, qualquer camada de cache no meio (inclusive o
        // próprio navegador, em alguma configuração) pode continuar servindo
        // bytes antigos de `/sw.js`, e nenhuma correção deste arquivo chega a
        // ser instalada — o pior tipo de bug: o código já está certo, mas
        // nunca é ele que roda.
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('pwa.service-worker');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/workouts', [WorkoutController::class, 'index'])->name('workouts.index');
    Route::get('/workouts/create', [WorkoutController::class, 'create'])->name('workouts.create');
    Route::post('/workouts', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::post('/workouts/scan', [WorkoutScannerController::class, 'store'])->name('workouts.scan');
    Route::get('/workouts/{workout}', [WorkoutController::class, 'show'])->name('workouts.show');
    Route::get('/workouts/{workout}/edit', [WorkoutController::class, 'edit'])->name('workouts.edit');
    Route::put('/workouts/{workout}', [WorkoutController::class, 'update'])->name('workouts.update');
    Route::patch('/workouts/{workout}/reorder', [WorkoutController::class, 'reorder'])->name('workouts.reorder');
    Route::patch('/workouts/{workout}/archive', [WorkoutController::class, 'toggleArchive'])->name('workouts.archive');
    Route::delete('/workouts/{workout}', [WorkoutController::class, 'destroy'])->name('workouts.destroy');
    Route::post('/workouts/{workout}/start', [WorkoutSessionController::class, 'start'])->name('workouts.start');

    Route::get('/programs', [WorkoutProgramController::class, 'index'])->name('programs.index');
    Route::post('/programs', [WorkoutProgramController::class, 'store'])->name('programs.store');
    Route::get('/programs/{program}', [WorkoutProgramController::class, 'show'])->name('programs.show');
    Route::put('/programs/{program}', [WorkoutProgramController::class, 'update'])->name('programs.update');
    Route::patch('/programs/{program}/activate', [WorkoutProgramController::class, 'activate'])->name('programs.activate');
    Route::patch('/programs/{program}/archive', [WorkoutProgramController::class, 'archive'])->name('programs.archive');
    Route::delete('/programs/{program}', [WorkoutProgramController::class, 'destroy'])->name('programs.destroy');
    Route::post('/programs/{program}/workouts/attach', [WorkoutProgramController::class, 'attachWorkouts'])->name('programs.workouts.attach');
    Route::delete('/programs/{program}/workouts/{workout}/detach', [WorkoutProgramController::class, 'detachWorkout'])->name('programs.workouts.detach');

    Route::get('/workout-sessions/{session}', [WorkoutSessionController::class, 'show'])->name('workout-sessions.show');
    Route::get('/workout-sessions/{session}/overload-suggestions', [WorkoutSessionController::class, 'overloadSuggestions'])->name('workout-sessions.overload-suggestions');
    Route::post('/workout-sessions/{session}/sets', [WorkoutSessionController::class, 'logSet'])->name('workout-sessions.sets.store');
    Route::post('/workout-sessions/{session}/finish', [WorkoutSessionController::class, 'finish'])->name('workout-sessions.finish');

    Route::get('/history', [WorkoutHistoryController::class, 'index'])->name('history.index');
    Route::get('/history/{session}', [WorkoutHistoryController::class, 'show'])->name('history.show');
    Route::delete('/history/{session}', [WorkoutHistoryController::class, 'destroy'])->name('history.destroy');
    Route::patch('/history/{session}/sets/{setLog}', [WorkoutHistoryController::class, 'updateSet'])->name('history.sets.update');
    Route::delete('/history/{session}/exercises/{exercise}', [WorkoutHistoryController::class, 'destroyExercise'])->name('history.exercises.destroy');

    Route::get('/exercises/{exercise}/history', [ExerciseHistoryController::class, 'show'])->name('exercises.history');

    Route::get('/api/sync/state', [SyncStateController::class, 'index'])->name('sync.state');
    Route::post('/api/sync/workout-sessions', [SyncController::class, 'workoutSessions'])->name('sync.workout-sessions');
    Route::post('/api/sync/mutations', [SyncController::class, 'resourceMutations'])->name('sync.mutations');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
