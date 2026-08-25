<?php

use App\Enums\DayOfWeek;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

test('guests are redirected to login when accessing the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard never makes an external HTTP call', function () {
    Http::preventStrayRequests();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    Http::assertNothingSent();
});

test('dashboard shows the current training streak in consecutive days', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-22 18:00:00'));

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

test('dashboard exposes the active workout scheduled for today as todayWorkout', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 08:00:00')); // a Monday

    $user = User::factory()->create();

    $todayWorkout = $user->workouts()->create([
        'name' => 'Treino A - Peito e Tríceps',
        'days_of_week' => [DayOfWeek::Monday->value],
    ]);

    $user->workouts()->create([
        'name' => 'Treino B - Costas',
        'days_of_week' => [DayOfWeek::Tuesday->value],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('todayWorkout.id', $todayWorkout->id)
                ->where('todayWorkout.name', 'Treino A - Peito e Tríceps')
                ->has('activeWorkouts', 2)
        );

    Carbon::setTestNow();
});

test('dashboard reports no todayWorkout on a rest day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-26 08:00:00')); // a Wednesday

    $user = User::factory()->create();

    $user->workouts()->create([
        'name' => 'Treino A',
        'days_of_week' => [DayOfWeek::Monday->value],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('todayWorkout', null)
        );

    Carbon::setTestNow();
});

test('dashboard ignores archived workouts', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 08:00:00')); // a Monday

    $user = User::factory()->create();

    $user->workouts()->create([
        'name' => 'Treino Arquivado',
        'days_of_week' => [DayOfWeek::Monday->value],
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('todayWorkout', null)
                ->where('activeWorkouts', [])
        );

    Carbon::setTestNow();
});
