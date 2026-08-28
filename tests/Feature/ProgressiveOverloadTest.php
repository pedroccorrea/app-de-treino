<?php

use App\Enums\OverloadConfidence;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\ProgressiveOverloadService;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates a workout with the given exercise and one completed past session
 * logging a set for it, so the service has history to analyze.
 */
function buildWorkoutWithOverloadHistory(User $user, Exercise $exercise, float $weight = 40, int $reps = 10): Workout
{
    $workout = $user->workouts()->create(['name' => 'Treino Teste']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    $pastSession = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);
    $pastSession->setLogs()->create([
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => $weight,
        'reps' => $reps,
        'rpe' => 7,
    ]);

    return $workout;
}

function fakeGeminiTextResponse(string $rawText): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => $rawText]]]],
            ],
        ], 200),
    ]);
}

function fakeGeminiOverloadResponse(array $body): void
{
    fakeGeminiTextResponse(json_encode($body));
}

test('a valid AI response is mapped into typed OverloadSuggestion DTOs', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildWorkoutWithOverloadHistory($user, $exercise, weight: 40, reps: 10);

    fakeGeminiOverloadResponse([
        'recommendations' => [
            [
                'exercise_id' => $exercise->id,
                'suggested_load' => 42.5,
                'suggested_reps' => 8,
                'rationale' => 'Progrida a carga com base no desempenho recente.',
                'confidence' => 'high',
            ],
        ],
    ]);

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions)->toHaveCount(1);

    $suggestion = $suggestions->first();
    expect($suggestion->exercise_id)->toBe($exercise->id);
    expect($suggestion->suggested_load)->toBe(42.5);
    expect($suggestion->suggested_reps)->toBe(8);
    expect($suggestion->previous_load)->toBe(40.0);
    expect($suggestion->previous_reps)->toBe(10);
    expect($suggestion->rationale)->toBe('Progrida a carga com base no desempenho recente.');
    expect($suggestion->confidence)->toBe(OverloadConfidence::High);
});

test('a suggestion whose exercise_id does not belong to the workout is discarded and logged', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $outsideExercise = Exercise::skip(1)->first();
    $workout = buildWorkoutWithOverloadHistory($user, $exercise);

    fakeGeminiOverloadResponse([
        'recommendations' => [
            [
                'exercise_id' => $exercise->id,
                'suggested_load' => 42.5,
                'suggested_reps' => 8,
                'rationale' => 'Progrida a carga.',
                'confidence' => 'high',
            ],
            [
                'exercise_id' => $outsideExercise->id,
                'suggested_load' => 10,
                'suggested_reps' => 15,
                'rationale' => 'Este exercício não pertence a este treino.',
                'confidence' => 'low',
            ],
        ],
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('Sugestão de sobrecarga progressiva descartada: exercise_id não pertence ao treino.', Mockery::any());

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions)->toHaveCount(1);
    expect($suggestions->first()->exercise_id)->toBe($exercise->id);
});

test('an invalid JSON response from the AI returns an empty collection without throwing', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildWorkoutWithOverloadHistory($user, $exercise);

    fakeGeminiTextResponse("```json\n{ isso nao é json válido, apenas lixo solto ```");

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions)->toHaveCount(0);
});

test('a structurally incomplete AI response returns an empty collection without throwing', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildWorkoutWithOverloadHistory($user, $exercise);

    // Valid JSON, but missing the "recommendations" list entirely.
    fakeGeminiOverloadResponse(['status' => 'ok']);

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions)->toHaveCount(0);
});

test('a recommendation missing required fields is silently dropped while valid ones are kept', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildWorkoutWithOverloadHistory($user, $exercise);

    fakeGeminiOverloadResponse([
        'recommendations' => [
            [
                'exercise_id' => $exercise->id,
                // suggested_load is missing entirely.
                'suggested_reps' => 8,
                'rationale' => 'Sem carga sugerida.',
            ],
            [
                'exercise_id' => $exercise->id,
                'suggested_load' => 42.5,
                'suggested_reps' => 8,
                'rationale' => 'Progrida a carga.',
                'confidence' => 'high',
            ],
        ],
    ]);

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions)->toHaveCount(1);
    expect($suggestions->first()->rationale)->toBe('Progrida a carga.');
});

test('no Gemini call is made and an empty collection is returned when there is no completed session history', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $workout = $user->workouts()->create(['name' => 'Treino Sem Histórico']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    Http::fake();

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions)->toHaveCount(0);
    Http::assertNothingSent();
});

test('a confidence value missing from the AI response defaults to Medium', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();
    $workout = buildWorkoutWithOverloadHistory($user, $exercise);

    fakeGeminiOverloadResponse([
        'recommendations' => [
            [
                'exercise_id' => $exercise->id,
                'suggested_load' => 42.5,
                'suggested_reps' => 8,
                'rationale' => 'Progrida a carga.',
            ],
        ],
    ]);

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions->first()->confidence)->toBe(OverloadConfidence::Medium);
});
