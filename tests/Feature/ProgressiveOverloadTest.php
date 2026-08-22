<?php

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\Http;

function fakeGeminiOverloadResponse(string $text): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);
}

test('guests are redirected to login when requesting overload suggestions', function () {
    $workout = Workout::factory()->create();

    $this->get(route('workouts.overload-suggestions', $workout))
        ->assertRedirect(route('login'));
});

test('a user cannot request overload suggestions for another users workout', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $workout = Workout::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('workouts.overload-suggestions', $workout))
        ->assertForbidden();
});

test('authenticated user receives AI progressive overload advice without persisting anything', function () {
    $fixture = file_get_contents(base_path('tests/Fixtures/progressive-overload-advice.json'));

    // The AI is faked to prove the service sanitizes markdown code fences
    // around the JSON payload, as required by the AI integration rules.
    fakeGeminiOverloadResponse("```json\n{$fixture}\n```");

    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    $supino = Exercise::query()->create([
        'user_id' => null,
        'name' => 'Supino Reto Barra',
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
    ]);

    $triceps = Exercise::query()->create([
        'user_id' => null,
        'name' => 'Tríceps Corda no Pulley',
        'primary_muscle_group' => MuscleGroup::Triceps,
        'secondary_muscle_groups' => [],
    ]);

    $workout->workoutExercises()->create([
        'exercise_id' => $supino->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10-12',
    ]);

    $workout->workoutExercises()->create([
        'exercise_id' => $triceps->id,
        'order' => 1,
        'target_sets' => 3,
        'target_reps' => '12-15',
    ]);

    // Three completed sessions in the history; only the latest 3 should be considered.
    for ($i = 1; $i <= 4; $i++) {
        $session = $user->workoutSessions()->create([
            'workout_id' => $workout->id,
            'started_at' => now()->subDays($i)->subHour(),
            'completed_at' => now()->subDays($i),
        ]);

        $session->setLogs()->create([
            'exercise_id' => $supino->id,
            'set_number' => 1,
            'weight' => 30,
            'reps' => 12,
            'rpe' => 7,
        ]);

        $session->setLogs()->create([
            'exercise_id' => $triceps->id,
            'set_number' => 1,
            'weight' => 20,
            'reps' => 12,
            'rpe' => 8,
        ]);
    }

    $countBefore = WorkoutSession::count();
    $setLogCountBefore = SetLog::count();

    $response = $this->actingAs($user)->get(route('workouts.overload-suggestions', $workout));

    $response->assertOk();
    $response->assertExactJson(json_decode($fixture, true));

    expect(WorkoutSession::count())->toBe($countBefore);
    expect(SetLog::count())->toBe($setLogCountBefore);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
});

test('overload suggestions fail with an error when the AI response is not valid json', function () {
    fakeGeminiOverloadResponse('isso não é um JSON válido.');

    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('workouts.overload-suggestions', $workout))
        ->assertStatus(500);
});
