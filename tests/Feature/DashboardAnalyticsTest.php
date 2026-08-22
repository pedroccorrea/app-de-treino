<?php

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function fakeGeminiMuscleBalanceResponse(string $text): void
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

test('guests are redirected to login when accessing the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('dashboard shows the current training streak in consecutive days', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-22 18:00:00'));

    $fixture = file_get_contents(base_path('tests/Fixtures/muscle-balance-alert.json'));
    fakeGeminiMuscleBalanceResponse($fixture);

    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    // Completed sessions today, yesterday and the day before: a 3-day streak.
    foreach ([0, 1, 2] as $daysAgo) {
        $user->workoutSessions()->create([
            'workout_id' => $workout->id,
            'started_at' => now()->subDays($daysAgo)->subHour(),
            'completed_at' => now()->subDays($daysAgo),
        ]);
    }

    // A session from 5 days ago breaks the consecutive streak, so it must not count.
    $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDays(5)->subHour(),
        'completed_at' => now()->subDays(5),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('streak', 3)
        );

    Carbon::setTestNow();
});

test('dashboard shows the personal record with the heaviest weight logged for each exercise', function () {
    $fixture = file_get_contents(base_path('tests/Fixtures/muscle-balance-alert.json'));
    fakeGeminiMuscleBalanceResponse($fixture);

    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    $supino = Exercise::query()->create([
        'user_id' => null,
        'name' => 'Supino Reto Barra',
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
    ]);

    // Older session with a lighter load.
    $olderSession = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDays(10)->subHour(),
        'completed_at' => now()->subDays(10),
    ]);
    $olderSession->setLogs()->create([
        'exercise_id' => $supino->id,
        'set_number' => 1,
        'weight' => 60,
        'reps' => 10,
        'rpe' => 8,
    ]);

    // Most recent session with the heaviest load: this is the PR.
    $recentSession = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);
    $recentSession->setLogs()->create([
        'exercise_id' => $supino->id,
        'set_number' => 1,
        'weight' => 82.5,
        'reps' => 8,
        'rpe' => 9,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('personalRecords', [
                    [
                        'exercise_id' => $supino->id,
                        'exercise_name' => 'Supino Reto Barra',
                        'weight' => 82.5,
                        'reps' => 8,
                        'achieved_at' => now()->subDay()->toDateString(),
                    ],
                ])
        );
});

test('dashboard exposes the weekly muscle volume and the AI muscle balance alert from the fixture', function () {
    $fixture = file_get_contents(base_path('tests/Fixtures/muscle-balance-alert.json'));
    fakeGeminiMuscleBalanceResponse($fixture);

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

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subHours(2),
        'completed_at' => now(),
    ]);

    for ($set = 1; $set <= 3; $set++) {
        $session->setLogs()->create([
            'exercise_id' => $supino->id,
            'set_number' => $set,
            'weight' => 40,
            'reps' => 10,
            'rpe' => 8,
        ]);
    }

    $session->setLogs()->create([
        'exercise_id' => $triceps->id,
        'set_number' => 1,
        'weight' => 20,
        'reps' => 12,
        'rpe' => 7,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('Dashboard')
            ->where('muscleBalanceAlert', json_decode($fixture, true))
            ->where('weeklyVolume', function ($weeklyVolume) {
                $chest = $weeklyVolume->firstWhere('muscle_group', MuscleGroup::Chest->value);
                $triceps = $weeklyVolume->firstWhere('muscle_group', MuscleGroup::Triceps->value);
                $quads = $weeklyVolume->firstWhere('muscle_group', MuscleGroup::Quads->value);

                return $chest['sets'] === 3
                    && $triceps['sets'] === 1
                    && $quads['sets'] === 0;
            })
    );

    Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
});

test('dashboard degrades gracefully to no muscle balance alert when the AI response is not valid json', function () {
    fakeGeminiMuscleBalanceResponse('isso não é um JSON válido.');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('muscleBalanceAlert', null)
        );
});
