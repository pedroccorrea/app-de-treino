<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Phase 44 — Página de Histórico de Treinos (somente leitura).
 */

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exerciseA = Exercise::query()->global()->orderBy('id')->first();
    $this->exerciseB = Exercise::query()->global()->orderBy('id')->skip(1)->first();
});

/**
 * Cria uma sessão do usuário com as séries informadas
 * ([exercise, set_number, weight|null, reps]).
 */
function historySession(User $user, ?Workout $workout, ?Carbon $completedAt, array $sets = [], ?Carbon $startedAt = null): WorkoutSession
{
    $session = $user->workoutSessions()->create([
        'workout_id' => $workout?->id,
        'started_at' => $startedAt ?? ($completedAt ? $completedAt->copy()->subMinutes(45) : now()),
        'completed_at' => $completedAt,
    ]);

    foreach ($sets as [$exercise, $setNumber, $weight, $reps]) {
        $session->setLogs()->create([
            'exercise_id' => $exercise->id,
            'set_number' => $setNumber,
            'weight' => $weight,
            'reps' => $reps,
        ]);
    }

    return $session;
}

test('a lista mostra só os treinos finalizados do próprio usuário, mais recentes primeiro', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino A']);
    $otherWorkout = $other->workouts()->create(['name' => 'Treino do Outro']);

    $older = historySession($user, $workout, now()->subDays(3), [[$this->exerciseA, 1, 40, 10]]);
    $newer = historySession($user, $workout, now()->subDay(), [[$this->exerciseA, 1, 45, 10]]);
    historySession($other, $otherWorkout, now()->subHours(2), [[$this->exerciseA, 1, 100, 5]]);

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutHistory/Index')
            ->has('sessions', 2)
            ->where('sessions.0.id', $newer->id)
            ->where('sessions.1.id', $older->id)
            ->where('sessions.0.workout_name', 'Treino A')
            ->where('pagination.has_more', false)
        );
});

test('o treino em andamento não aparece na lista', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino A']);

    $finished = historySession($user, $workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);
    historySession($user, $workout, null, [[$this->exerciseA, 1, 50, 8]]);

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.id', $finished->id)
        );
});

test('o resumo traz exercícios, séries, volume e duração calculados das séries', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Peito']);

    historySession($user, $workout, now(), [
        [$this->exerciseA, 1, 50, 10],
        [$this->exerciseA, 2, 50, 8],
        [$this->exerciseB, 1, null, 12],
    ], now()->subMinutes(52));

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.exercise_count', 2)
            ->where('sessions.0.set_count', 3)
            ->where('sessions.0.volume', 900)
            ->where('sessions.0.duration_minutes', 52)
            ->where('sessions.0.exercise_names', [$this->exerciseA->name, $this->exerciseB->name])
        );
});

test('sessão cuja ficha foi excluída aparece com nome nulo, sem quebrar a tela', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Será Excluída']);
    $session = historySession($user, $workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);

    $workout->delete();

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.id', $session->id)
            ->where('sessions.0.workout_id', null)
            ->where('sessions.0.workout_name', null)
        );

    $this->get(route('history.show', $session))->assertOk();
});

test('duração implausível (sessão encerrada pela limpeza de fantasmas) vira nula', function () {
    $user = User::factory()->create();

    historySession($user, null, now(), [[$this->exerciseA, 1, 40, 10]], now()->subDays(2));

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertInertia(fn (Assert $page) => $page->where('sessions.0.duration_minutes', null));
});

test('sessões finalizadas sem nenhuma série não entram na lista', function () {
    $user = User::factory()->create();

    historySession($user, null, now()->subDay());

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertInertia(fn (Assert $page) => $page->has('sessions', 0));
});

test('estado vazio quando o usuário não tem treinos finalizados', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutHistory/Index')
            ->has('sessions', 0)
            ->where('pagination.has_more', false)
        );
});

test('a lista é paginada e a próxima página traz os treinos mais antigos', function () {
    $user = User::factory()->create();

    foreach (range(1, 17) as $daysAgo) {
        historySession($user, null, now()->subDays($daysAgo), [[$this->exerciseA, 1, 40, 10]]);
    }

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 15)
            ->where('pagination.current_page', 1)
            ->where('pagination.has_more', true)
        );

    $this->get(route('history.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 2)
            ->where('pagination.current_page', 2)
            ->where('pagination.has_more', false)
        );
});

test('o detalhe mostra exercícios na ordem executada e as séries por número, inclusive sem carga', function () {
    $user = User::factory()->create();
    $workout = $user->workouts()->create(['name' => 'Treino A']);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subHour(),
        'completed_at' => now(),
    ]);

    // B foi feito primeiro, mesmo que A venha antes na ficha/catálogo.
    $this->travelTo(now()->subMinutes(30));
    $session->setLogs()->create(['exercise_id' => $this->exerciseB->id, 'set_number' => 1, 'weight' => null, 'reps' => 15]);
    $this->travelTo(now()->addMinutes(10));
    $session->setLogs()->create(['exercise_id' => $this->exerciseA->id, 'set_number' => 2, 'weight' => 60, 'reps' => 8]);
    $session->setLogs()->create(['exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => 60, 'reps' => 10]);
    $this->travelBack();

    $this->actingAs($user)
        ->get(route('history.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutHistory/Show')
            ->where('session.id', $session->id)
            ->where('session.workout_name', 'Treino A')
            ->has('session.exercises', 2)
            ->where('session.exercises.0.exercise_id', $this->exerciseB->id)
            ->where('session.exercises.0.sets.0.weight', null)
            ->where('session.exercises.0.sets.0.reps', 15)
            ->where('session.exercises.1.exercise_id', $this->exerciseA->id)
            ->where('session.exercises.1.sets.0.set_number', 1)
            ->where('session.exercises.1.sets.0.reps', 10)
            ->where('session.exercises.1.sets.1.set_number', 2)
            ->where('session.exercises.1.volume', 1080)
        );
});

test('o detalhe de um treino de outro usuário é bloqueado no backend', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $session = historySession($owner, null, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);

    $this->actingAs($intruder)
        ->get(route('history.show', $session))
        ->assertForbidden();
});

test('o detalhe de um treino em andamento é bloqueado no backend', function () {
    $user = User::factory()->create();
    $session = historySession($user, null, null, [[$this->exerciseA, 1, 40, 10]]);

    $this->actingAs($user)
        ->get(route('history.show', $session))
        ->assertNotFound();
});

test('o histórico exige autenticação', function () {
    $user = User::factory()->create();
    $session = historySession($user, null, now(), [[$this->exerciseA, 1, 40, 10]]);

    $this->get(route('history.index'))->assertRedirect(route('login'));
    $this->get(route('history.show', $session))->assertRedirect(route('login'));
});

test('volume e séries refletem o estado atual das séries (nada é salvo em cache no banco)', function () {
    $user = User::factory()->create();
    $session = historySession($user, null, now(), [[$this->exerciseA, 1, 40, 10]]);

    $session->setLogs()->first()->update(['weight' => 50]);

    $this->actingAs($user)
        ->get(route('history.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.volume', 500)
            ->where('session.exercises.0.sets.0.weight', 50)
        );
});

test('o service worker trata as rotas do histórico offline sem travar (cache primeiro, depois estado amigável)', function () {
    $content = $this->get('/sw.js')->getContent();

    expect($content)->toContain("'WorkoutHistory/Index'");
    expect($content)->toContain("'WorkoutHistory/Show'");
    expect($content)->toContain('buildHistoryIndexOfflineProps');
    expect($content)->toContain('pageRoute?.preferCache');
});
