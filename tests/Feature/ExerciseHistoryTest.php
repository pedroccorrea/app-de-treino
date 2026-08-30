<?php

use App\Enums\HistoryRange;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Services\ExerciseHistoryService;
use Illuminate\Support\Carbon;

/**
 * Creates a completed workout session for the given user, logging a single
 * set of the given exercise with the given weight/reps, completed at the
 * given moment in the past.
 */
function completeSessionWithSet(User $user, Exercise $exercise, Carbon $completedAt, float $weight, int $reps): WorkoutSession
{
    $session = $user->workoutSessions()->create([
        'workout_id' => null,
        'started_at' => $completedAt->copy()->subMinutes(45),
        'completed_at' => $completedAt,
    ]);

    $session->setLogs()->create([
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => $weight,
        'reps' => $reps,
    ]);

    return $session;
}

test('getVolumeSeries retorna o volume total por sessão em ordem cronológica', function () {
    $user = User::factory()->create();
    $exercise = Exercise::create([
        'user_id' => $user->id,
        'name' => 'Supino Reto',
        'primary_muscle_group' => MuscleGroup::Chest,
    ]);

    $older = completeSessionWithSet($user, $exercise, now()->subDays(10), weight: 40, reps: 10);
    $older->setLogs()->create(['exercise_id' => $exercise->id, 'set_number' => 2, 'weight' => 40, 'reps' => 8]);

    completeSessionWithSet($user, $exercise, now()->subDays(3), weight: 50, reps: 10);

    $series = app(ExerciseHistoryService::class)->getVolumeSeries($user, $exercise, HistoryRange::FourWeeks);

    expect($series)->toHaveCount(2);
    expect($series[0]['volume'])->toBe(720.0);
    expect($series[1]['volume'])->toBe(500.0);
    expect($series[0]['date'] < $series[1]['date'])->toBeTrue();
});

test('getPersonalRecord retorna a maior carga registrada e a respectiva data', function () {
    $user = User::factory()->create();
    $exercise = Exercise::create([
        'user_id' => $user->id,
        'name' => 'Agachamento',
        'primary_muscle_group' => MuscleGroup::Quads,
    ]);

    completeSessionWithSet($user, $exercise, now()->subDays(20), weight: 60, reps: 8);
    $recordSession = completeSessionWithSet($user, $exercise, now()->subDays(5), weight: 80, reps: 5);
    completeSessionWithSet($user, $exercise, now()->subDays(1), weight: 70, reps: 6);

    $record = app(ExerciseHistoryService::class)->getPersonalRecord($user, $exercise);

    expect($record)->not->toBeNull();
    expect($record['weight'])->toBe(80.0);
    expect($record['date'])->toBe($recordSession->completed_at->toDateString());
});

test('filtro de período retorna apenas as sessões dentro do intervalo solicitado', function () {
    $user = User::factory()->create();
    $exercise = Exercise::create([
        'user_id' => $user->id,
        'name' => 'Remada Curvada',
        'primary_muscle_group' => MuscleGroup::Back,
    ]);

    completeSessionWithSet($user, $exercise, now()->subWeeks(2), weight: 30, reps: 10); // dentro de 4S, 12S e 1A
    completeSessionWithSet($user, $exercise, now()->subWeeks(8), weight: 32, reps: 10); // fora de 4S, dentro de 12S e 1A
    completeSessionWithSet($user, $exercise, now()->subWeeks(30), weight: 34, reps: 10); // fora de 4S e 12S, dentro de 1A
    completeSessionWithSet($user, $exercise, now()->subYears(2), weight: 36, reps: 10); // fora de todos

    $this->actingAs($user)
        ->get(route('exercises.history', ['exercise' => $exercise, 'range' => '4w']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Exercises/History')
            ->where('range', '4w')
            ->has('sessions', 1)
        );

    $this->actingAs($user)
        ->get(route('exercises.history', ['exercise' => $exercise, 'range' => '12w']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Exercises/History')
            ->where('range', '12w')
            ->has('sessions', 2)
        );

    $this->actingAs($user)
        ->get(route('exercises.history', ['exercise' => $exercise, 'range' => '1y']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Exercises/History')
            ->where('range', '1y')
            ->has('sessions', 3)
        );
});

test('o histórico padrão sem query string usa o intervalo de 4 semanas', function () {
    $user = User::factory()->create();
    $exercise = Exercise::create([
        'user_id' => $user->id,
        'name' => 'Rosca Direta',
        'primary_muscle_group' => MuscleGroup::Biceps,
    ]);

    $this->actingAs($user)
        ->get(route('exercises.history', $exercise))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Exercises/History')
            ->where('range', '4w')
        );
});

test('usuário não pode acessar o histórico de um exercício pertencente a outro usuário', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $exercise = Exercise::create([
        'user_id' => $owner->id,
        'name' => 'Exercício Privado',
        'primary_muscle_group' => MuscleGroup::Shoulders,
    ]);

    $this->actingAs($intruder)
        ->get(route('exercises.history', $exercise))
        ->assertForbidden();
});

test('guests são redirecionados para o login ao acessar o histórico de um exercício', function () {
    $exercise = Exercise::create([
        'name' => 'Exercício Global',
        'primary_muscle_group' => MuscleGroup::Chest,
    ]);

    $this->get(route('exercises.history', $exercise))
        ->assertRedirect(route('login'));
});
