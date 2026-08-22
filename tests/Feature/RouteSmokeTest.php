<?php

use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Facades\Http;

/**
 * Smoke test: every authenticated app route must respond without a fatal
 * runtime error (500) for a logged-in, authorized user — either a
 * successful render (200) or a legitimate redirect (302).
 */
beforeEach(function () {
    // /dashboard triggers a Gemini call for the muscle-balance alert; fake
    // it globally so this smoke test never touches the real network.
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => file_get_contents(base_path('tests/Fixtures/muscle-balance-alert.json'))],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);
});

test('every authenticated route responds without a runtime error', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();
    $exercise = Exercise::first();

    $workout = $user->workouts()->create(['name' => 'Treino de Fumaça']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
        'rest_seconds' => 60,
    ]);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now(),
    ]);

    $routes = [
        'dashboard' => route('dashboard'),
        'workouts.index' => route('workouts.index'),
        'workouts.show' => route('workouts.show', $workout),
        'workouts.edit' => route('workouts.edit', $workout),
        'workout-sessions.show' => route('workout-sessions.show', $session),
    ];

    foreach ($routes as $name => $uri) {
        $status = $this->actingAs($user)->get($uri)->getStatusCode();

        expect(in_array($status, [200, 302], true))
            ->toBeTrue("A rota [{$name}] ({$uri}) respondeu com status inesperado {$status}.");
    }
});
