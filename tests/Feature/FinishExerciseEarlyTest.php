<?php

use App\Enums\HistoryRange;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\ExerciseHistoryService;
use App\Services\ProgressiveOverloadService;
use App\Services\WorkoutSessionService;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Phase 41 — "Finalizar exercício". Encerrar um exercício antes da hora é
 * uma decisão só da interface: o app simplesmente não envia as séries não
 * feitas. Estes testes garantem que, do lado do servidor, só as séries
 * efetivamente registradas existem e alimentam volume, histórico,
 * estatísticas e a análise de progressão da IA — as ausentes não viram
 * nem série feita nem falha.
 */

/**
 * Ficha com um exercício de 4 séries planejadas.
 */
function buildFourSetWorkout(User $user, Exercise $exercise): Workout
{
    $workout = $user->workouts()->create(['name' => 'Treino Parcial']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 4,
        'target_reps' => '10',
        'rest_seconds' => 60,
    ]);

    return $workout;
}

/**
 * Fluxo online completo: inicia a sessão, conclui só as 2 primeiras das 4
 * séries (o usuário tocou "Finalizar exercício" depois) e finaliza o treino.
 */
function finishWorkoutWithTwoOfFourSets(User $user, Workout $workout, Exercise $exercise): WorkoutSession
{
    test()->actingAs($user)->post(route('workouts.start', $workout));
    $session = WorkoutSession::where('user_id', $user->id)->firstOrFail();

    test()->post(route('workout-sessions.sets.store', $session), [
        'exercise_id' => $exercise->id, 'set_number' => 1, 'weight' => 50, 'reps' => 10,
    ])->assertSessionHasNoErrors();
    test()->post(route('workout-sessions.sets.store', $session), [
        'exercise_id' => $exercise->id, 'set_number' => 2, 'weight' => 50, 'reps' => 8,
    ])->assertSessionHasNoErrors();

    test()->post(route('workout-sessions.finish', $session));

    return $session->fresh();
}

test('finalizar um exercício com 2 de 4 séries persiste só as 2 feitas (online)', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildFourSetWorkout($user, $exercise);

    $session = finishWorkoutWithTwoOfFourSets($user, $workout, $exercise);

    expect($session->completed_at)->not->toBeNull();
    expect(SetLog::where('workout_session_id', $session->id)->pluck('set_number')->sort()->values()->all())
        ->toBe([1, 2]);
});

test('finalizar um exercício com 2 de 4 séries persiste só as 2 feitas (offline, via fila de sincronização)', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildFourSetWorkout($user, $exercise);
    $clientSessionId = 'client-session-partial';

    // Exatamente o que a fila offline contém depois de "Finalizar exercício":
    // as 2 séries concluídas e o fim do treino — nenhuma ação para as séries 3 e 4.
    $actions = [
        ['uuid' => 'start', 'type' => 'start_session', 'client_session_id' => $clientSessionId,
            'occurred_at' => now()->subMinutes(30)->toISOString(), 'workout_id' => $workout->id],
        ['uuid' => 'set-1', 'type' => 'log_set', 'client_session_id' => $clientSessionId,
            'occurred_at' => now()->subMinutes(25)->toISOString(),
            'exercise_id' => $exercise->id, 'set_number' => 1, 'weight' => 50, 'reps' => 10],
        ['uuid' => 'set-2', 'type' => 'log_set', 'client_session_id' => $clientSessionId,
            'occurred_at' => now()->subMinutes(22)->toISOString(),
            'exercise_id' => $exercise->id, 'set_number' => 2, 'weight' => 50, 'reps' => 8],
        ['uuid' => 'finish', 'type' => 'finish_session', 'client_session_id' => $clientSessionId,
            'occurred_at' => now()->subMinutes(20)->toISOString()],
    ];

    $this->actingAs($user)
        ->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk();

    $session = WorkoutSession::where('user_id', $user->id)->firstOrFail();

    expect($session->completed_at)->not->toBeNull();
    expect(SetLog::where('workout_session_id', $session->id)->pluck('set_number')->sort()->values()->all())
        ->toBe([1, 2]);
});

test('séries não feitas ficam fora do volume, do histórico e das estatísticas', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildFourSetWorkout($user, $exercise);

    $session = finishWorkoutWithTwoOfFourSets($user, $workout, $exercise);
    $history = app(ExerciseHistoryService::class);

    // Volume: 50×10 + 50×8 = 900 (as séries 3 e 4 não somam nada).
    $series = $history->getVolumeSeries($user, $exercise, HistoryRange::FourWeeks);
    expect($series)->toHaveCount(1);
    expect($series[0]['volume'])->toBe(900.0);

    // Histórico: a sessão aparece com exatamente as 2 séries feitas.
    $sessions = $history->getHistory($user, $exercise, HistoryRange::FourWeeks);
    expect($sessions)->toHaveCount(1);
    expect($sessions->first()->setLogs->pluck('set_number')->all())->toBe([1, 2]);

    // Estatísticas: recorde e "último treino" consideram só as séries feitas.
    expect($history->getPersonalRecord($user, $exercise)['weight'])->toBe(50.0);

    $lastLogs = app(WorkoutSessionService::class)->getLastLogsForExercises($user, [$exercise->id]);
    expect(collect($lastLogs[$exercise->id]['sets'])->pluck('set_number')->all())->toBe([1, 2]);

    // A tela de histórico entrega as mesmas 2 séries.
    $this->get(route('exercises.history', $exercise))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('volumeSeries.0.volume', 900)
            ->where('volumeSeries.0.session_id', $session->id)
        );
});

test('séries não feitas não chegam à análise de progressão da IA, nem como feitas nem como falha', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildFourSetWorkout($user, $exercise);

    finishWorkoutWithTwoOfFourSets($user, $workout, $exercise);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['recommendations' => []])]]]]],
        ], 200),
    ]);

    app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout->fresh());

    Http::assertSent(function (Request $request) {
        $prompt = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($prompt, '\"set_number\": 1')
            && str_contains($prompt, '\"set_number\": 2')
            && ! str_contains($prompt, '\"set_number\": 3')
            && ! str_contains($prompt, '\"set_number\": 4')
            && ! str_contains($prompt, '\"reps\": null')
            && ! str_contains($prompt, '\"reps\": 0');
    });
});
