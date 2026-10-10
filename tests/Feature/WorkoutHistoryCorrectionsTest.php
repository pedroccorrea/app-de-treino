<?php

use App\Enums\HistoryRange;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\DashboardAnalyticsService;
use App\Services\ExerciseHistoryService;
use App\Services\RecentPerformanceService;
use App\Services\WorkoutSessionService;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Phase 45 — Editar e excluir no histórico de treinos.
 */

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exerciseA = Exercise::query()->global()->orderBy('id')->first();
    $this->exerciseB = Exercise::query()->global()->orderBy('id')->skip(1)->first();
    $this->user = User::factory()->create();
    $this->workout = $this->user->workouts()->create(['name' => 'Treino A']);
});

/**
 * Sessão finalizada do usuário com as séries informadas
 * ([exercise, set_number, weight|null, reps]).
 */
function correctionSession(User $user, ?Workout $workout, Carbon $completedAt, array $sets, ?string $clientUuid = null): WorkoutSession
{
    $session = $user->workoutSessions()->create([
        'workout_id' => $workout?->id,
        'client_uuid' => $clientUuid,
        'started_at' => $completedAt->copy()->subMinutes(45),
        'completed_at' => $completedAt,
    ]);

    foreach ($sets as [$exercise, $setNumber, $weight, $reps]) {
        $log = $session->setLogs()->create([
            'exercise_id' => $exercise->id,
            'set_number' => $setNumber,
            'weight' => $weight,
            'reps' => $reps,
        ]);
        $log->forceFill(['created_at' => $completedAt->copy()->subMinutes(30), 'updated_at' => $completedAt->copy()->subMinutes(30)])->save();
    }

    return $session;
}

function lastLogFor(User $user, Exercise $exercise): ?array
{
    return app(WorkoutSessionService::class)->getLastLogsForExercises($user, [$exercise->id])[$exercise->id] ?? null;
}

// ─── Editar série ─────────────────────────────────────────────────────────────

test('editar uma série atualiza o detalhe, o volume e marca a correção', function () {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [
        [$this->exerciseA, 1, 40, 10],
        [$this->exerciseA, 2, 40, 8],
    ]);
    $set = $session->setLogs()->where('set_number', 1)->first();

    $this->actingAs($this->user)
        ->from(route('history.show', $session))
        ->patch(route('history.sets.update', [$session, $set]), ['weight' => 50, 'reps' => 12])
        ->assertRedirect(route('history.show', $session))
        ->assertSessionHas('success');

    $set->refresh();
    expect((float) $set->weight)->toBe(50.0)
        ->and($set->reps)->toBe(12)
        ->and($set->edited_at)->not->toBeNull();

    $this->get(route('history.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.volume', 50 * 12 + 40 * 8)
            ->where('session.exercises.0.sets.0.weight', 50)
            ->where('session.exercises.0.sets.0.reps', 12)
        );
});

test('editar uma série se reflete em "Última", no histórico por exercício e no recorde', function () {
    $older = correctionSession($this->user, $this->workout, now()->subDays(3), [[$this->exerciseA, 1, 60, 5]]);
    $latest = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);

    expect(lastLogFor($this->user, $this->exerciseA)['weight'])->toBe(40.0);

    $set = $latest->setLogs()->first();
    $this->actingAs($this->user)
        ->patch(route('history.sets.update', [$latest, $set]), ['weight' => 70, 'reps' => 6])
        ->assertRedirect();

    // "Última" (treino em andamento e `/api/sync/state`)
    expect(lastLogFor($this->user, $this->exerciseA))
        ->weight->toBe(70.0)
        ->reps->toBe(6);

    // Recorde e histórico por exercício
    $history = app(ExerciseHistoryService::class);
    expect($history->getPersonalRecord($this->user, $this->exerciseA)['weight'])->toBe(70.0);

    $this->get(route('exercises.history', $this->exerciseA))
        ->assertInertia(fn (Assert $page) => $page
            ->where('personalRecord.weight', 70)
            ->where('sessions.0.id', $latest->id)
            ->where('sessions.0.sets.0.weight', 70)
            ->where('sessions.0.sets.0.reps', 6)
            ->where('sessions.1.id', $older->id)
        );
});

test('a série pode ficar sem carga', function () {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);
    $set = $session->setLogs()->first();

    $this->actingAs($this->user)
        ->patch(route('history.sets.update', [$session, $set]), ['weight' => null, 'reps' => 15])
        ->assertSessionHasNoErrors();

    expect($set->refresh()->weight)->toBeNull()
        ->and($set->reps)->toBe(15);
});

test('a edição usa os mesmos limites do registro de série', function (array $payload, string $field) {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);
    $set = $session->setLogs()->first();

    $this->actingAs($this->user)
        ->patch(route('history.sets.update', [$session, $set]), $payload)
        ->assertSessionHasErrors($field);

    expect((float) $set->refresh()->weight)->toBe(40.0)
        ->and($set->reps)->toBe(10);
})->with([
    'reps zero' => [['weight' => 40, 'reps' => 0], 'reps'],
    'reps ausente' => [['weight' => 40], 'reps'],
    'reps fracionado' => [['weight' => 40, 'reps' => 8.5], 'reps'],
    'carga negativa' => [['weight' => -5, 'reps' => 10], 'weight'],
    'carga acima do limite' => [['weight' => 1000, 'reps' => 10], 'weight'],
]);

test('não é possível editar, pela URL de um treino, uma série de outro treino', function () {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);
    $other = correctionSession($this->user, $this->workout, now()->subDays(2), [[$this->exerciseA, 1, 30, 10]]);

    $this->actingAs($this->user)
        ->patch(route('history.sets.update', [$session, $other->setLogs()->first()]), ['weight' => 99, 'reps' => 1])
        ->assertNotFound();

    expect((float) $other->setLogs()->first()->weight)->toBe(30.0);
});

// ─── Excluir exercício de um treino ──────────────────────────────────────────

test('excluir um exercício remove as séries dele deste treino, sem tocar na ficha nem no catálogo', function () {
    $this->workout->exercises()->attach($this->exerciseA->id, ['order' => 0]);
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [
        [$this->exerciseA, 1, 40, 10],
        [$this->exerciseA, 2, 40, 8],
        [$this->exerciseB, 1, 20, 12],
    ]);

    $this->actingAs($this->user)
        ->from(route('history.show', $session))
        ->delete(route('history.exercises.destroy', [$session, $this->exerciseA]))
        ->assertRedirect(route('history.show', $session));

    // Recuperável: as linhas continuam no banco, só marcadas como excluídas.
    expect($session->setLogs()->where('exercise_id', $this->exerciseA->id)->count())->toBe(0)
        ->and(SetLog::onlyTrashed()->where('workout_session_id', $session->id)->count())->toBe(2)
        ->and($session->setLogs()->count())->toBe(1);

    expect(Workout::find($this->workout->id))->not->toBeNull()
        ->and($this->workout->exercises()->whereKey($this->exerciseA->id)->exists())->toBeTrue()
        ->and(Exercise::find($this->exerciseA->id))->not->toBeNull();

    $this->get(route('history.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->has('session.exercises', 1)
            ->where('session.exercises.0.exercise_id', $this->exerciseB->id)
            ->where('session.set_count', 1)
            ->where('session.volume', 240)
        );
});

test('excluir um exercício tira as séries de "Última", recorde, histórico por exercício e resumo da IA', function () {
    $older = correctionSession($this->user, $this->workout, now()->subDays(3), [[$this->exerciseA, 1, 40, 10]]);
    $latest = correctionSession($this->user, $this->workout, now()->subDay(), [
        [$this->exerciseA, 1, 80, 5],
        [$this->exerciseB, 1, 20, 12],
    ]);

    $this->actingAs($this->user)
        ->delete(route('history.exercises.destroy', [$latest, $this->exerciseA]))
        ->assertRedirect();

    expect(lastLogFor($this->user, $this->exerciseA))
        ->weight->toBe(40.0)
        ->reps->toBe(10);

    expect(app(ExerciseHistoryService::class)->getPersonalRecord($this->user, $this->exerciseA)['weight'])->toBe(40.0);

    $sessions = app(ExerciseHistoryService::class)->getHistory($this->user, $this->exerciseA, HistoryRange::OneYear);
    expect($sessions->pluck('id')->all())->toBe([$older->id]);

    $summary = app(RecentPerformanceService::class)->summarize($this->user, [$this->exerciseA->id])->first();
    expect($summary->sessions)->toHaveCount(1)
        ->and($summary->best['weight'])->toBe(40.0);
});

test('excluir o último exercício de um treino tira o treino inteiro do histórico', function () {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);

    $this->actingAs($this->user)
        ->delete(route('history.exercises.destroy', [$session, $this->exerciseA]))
        ->assertRedirect(route('history.index'));

    expect(WorkoutSession::find($session->id))->toBeNull()
        ->and(WorkoutSession::withTrashed()->find($session->id)->trashed())->toBeTrue()
        ->and(Workout::find($this->workout->id))->not->toBeNull();

    $this->get(route('history.show', $session))->assertNotFound();
});

test('excluir um exercício que não está no treino responde 404 e não altera nada', function () {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);

    $this->actingAs($this->user)
        ->delete(route('history.exercises.destroy', [$session, $this->exerciseB]))
        ->assertNotFound();

    expect($session->setLogs()->count())->toBe(1);
});

// ─── Excluir treino inteiro ──────────────────────────────────────────────────

test('excluir um treino o remove do histórico e de todos os cálculos, sem excluir a ficha', function () {
    $older = correctionSession($this->user, $this->workout, now()->subDays(3), [[$this->exerciseA, 1, 40, 10]]);
    $latest = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 90, 3]]);

    $this->actingAs($this->user)
        ->delete(route('history.destroy', $latest))
        ->assertRedirect(route('history.index'))
        ->assertSessionHas('success');

    expect(WorkoutSession::find($latest->id))->toBeNull()
        ->and(SetLog::onlyTrashed()->where('workout_session_id', $latest->id)->count())->toBe(1)
        ->and(Workout::find($this->workout->id))->not->toBeNull()
        ->and($this->workout->workoutSessions()->pluck('id')->all())->toBe([$older->id]);

    $this->get(route('history.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.id', $older->id)
        );

    $this->get(route('history.show', $latest))->assertNotFound();

    expect(lastLogFor($this->user, $this->exerciseA)['weight'])->toBe(40.0)
        ->and(app(ExerciseHistoryService::class)->getPersonalRecord($this->user, $this->exerciseA)['weight'])->toBe(40.0);
});

test('excluir um treino respeita o return_to da origem', function () {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);
    $origin = route('exercises.history', $this->exerciseA, false);

    $this->actingAs($this->user)
        ->delete(route('history.destroy', ['session' => $session, 'return_to' => $origin]))
        ->assertRedirect($origin);
});

test('um treino excluído deixa de contar na sequência de dias', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 18:00:00'));

    correctionSession($this->user, $this->workout, now()->subDays(2), [[$this->exerciseA, 1, 40, 10]]);
    $yesterday = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);
    correctionSession($this->user, $this->workout, now(), [[$this->exerciseA, 1, 40, 10]]);

    $analytics = app(DashboardAnalyticsService::class);
    expect($analytics->calculateStreak($this->user))->toBe(3);

    $this->actingAs($this->user)->delete(route('history.destroy', $yesterday));

    expect($analytics->calculateStreak($this->user))->toBe(1);

    Carbon::setTestNow();
});

// ─── Bloqueio entre usuários ─────────────────────────────────────────────────

test('nenhum usuário edita ou exclui séries, exercícios ou treinos de outro usuário', function () {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);
    $set = $session->setLogs()->first();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('history.sets.update', [$session, $set]), ['weight' => 1, 'reps' => 1])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('history.exercises.destroy', [$session, $this->exerciseA]))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('history.destroy', $session))
        ->assertForbidden();

    $set->refresh();
    expect((float) $set->weight)->toBe(40.0)
        ->and($set->edited_at)->toBeNull()
        ->and(WorkoutSession::find($session->id))->not->toBeNull()
        ->and($session->setLogs()->count())->toBe(1);
});

test('o treino em andamento não pode ser corrigido pelo histórico', function () {
    $active = $this->user->workoutSessions()->create([
        'workout_id' => $this->workout->id,
        'started_at' => now()->subMinutes(10),
    ]);
    $set = $active->setLogs()->create(['exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => 40, 'reps' => 10]);

    $this->actingAs($this->user)
        ->patch(route('history.sets.update', [$active, $set]), ['weight' => 50, 'reps' => 10])
        ->assertNotFound();

    $this->delete(route('history.exercises.destroy', [$active, $this->exerciseA]))->assertNotFound();
    $this->delete(route('history.destroy', $active))->assertNotFound();

    expect(WorkoutSession::find($active->id))->not->toBeNull()
        ->and((float) $set->refresh()->weight)->toBe(40.0);
});

test('as correções exigem autenticação', function () {
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 40, 10]]);

    $this->patch(route('history.sets.update', [$session, $session->setLogs()->first()]), ['weight' => 1, 'reps' => 1])
        ->assertRedirect(route('login'));
    $this->delete(route('history.destroy', $session))->assertRedirect(route('login'));
});

// ─── Fila offline antiga não recria nem desfaz nada ──────────────────────────

test('reenviar o lote offline de um treino excluído não o recria', function () {
    $actions = [
        ['uuid' => 'a-start', 'type' => 'start_session', 'client_session_id' => 'cs-1', 'occurred_at' => now()->subHour()->toISOString(), 'workout_id' => $this->workout->id],
        ['uuid' => 'a-set', 'type' => 'log_set', 'client_session_id' => 'cs-1', 'occurred_at' => now()->subMinutes(50)->toISOString(), 'exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => 40, 'reps' => 10],
        ['uuid' => 'a-finish', 'type' => 'finish_session', 'client_session_id' => 'cs-1', 'occurred_at' => now()->subMinutes(10)->toISOString()],
    ];

    $this->actingAs($this->user)->postJson(route('sync.workout-sessions'), ['actions' => $actions])->assertOk();
    $session = WorkoutSession::where('client_uuid', 'cs-1')->firstOrFail();

    $this->delete(route('history.destroy', $session))->assertRedirect();

    // Reenvio do mesmo lote (o aparelho não recebeu a confirmação a tempo).
    $results = $this->postJson(route('sync.workout-sessions'), ['actions' => $actions])
        ->assertOk()
        ->json('results');

    // Todas confirmadas (saem da fila do aparelho), nenhuma com efeito.
    expect(collect($results)->pluck('uuid')->all())->toBe(['a-start', 'a-set', 'a-finish'])
        ->and(WorkoutSession::withTrashed()->where('user_id', $this->user->id)->count())->toBe(1)
        ->and(WorkoutSession::where('user_id', $this->user->id)->count())->toBe(0)
        ->and(SetLog::where('workout_session_id', $session->id)->count())->toBe(0);
});

test('um log_set antigo da fila não recria um exercício excluído do treino', function () {
    $session = correctionSession($this->user, $this->workout, now()->subHour(), [
        [$this->exerciseA, 1, 40, 10],
        [$this->exerciseB, 1, 20, 12],
    ]);

    $this->actingAs($this->user)
        ->delete(route('history.exercises.destroy', [$session, $this->exerciseA]))
        ->assertRedirect();

    // Série 1 reenviada e uma série 2 que nunca tinha subido.
    $results = $this->postJson(route('sync.workout-sessions'), ['actions' => [
        ['uuid' => 'old-1', 'type' => 'log_set', 'client_session_id' => "session-{$session->id}", 'session_id' => $session->id, 'occurred_at' => now()->subMinutes(70)->toISOString(), 'exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => 40, 'reps' => 10],
        ['uuid' => 'old-2', 'type' => 'log_set', 'client_session_id' => "session-{$session->id}", 'session_id' => $session->id, 'occurred_at' => now()->subMinutes(68)->toISOString(), 'exercise_id' => $this->exerciseA->id, 'set_number' => 2, 'weight' => 40, 'reps' => 8],
    ]])->assertOk()->json('results');

    expect($results)->toHaveCount(2)
        ->and($session->setLogs()->where('exercise_id', $this->exerciseA->id)->count())->toBe(0)
        ->and($session->setLogs()->count())->toBe(1);
});

test('um log_set antigo da fila não desfaz uma série corrigida no histórico', function () {
    $session = correctionSession($this->user, $this->workout, now()->subHour(), [[$this->exerciseA, 1, 40, 10]]);
    $set = $session->setLogs()->first();

    $this->actingAs($this->user)
        ->patch(route('history.sets.update', [$session, $set]), ['weight' => 45, 'reps' => 9])
        ->assertRedirect();

    $results = $this->postJson(route('sync.workout-sessions'), ['actions' => [
        ['uuid' => 'stale', 'type' => 'log_set', 'client_session_id' => "session-{$session->id}", 'session_id' => $session->id, 'occurred_at' => now()->subMinutes(70)->toISOString(), 'exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => 40, 'reps' => 10],
    ]])->assertOk()->json('results');

    expect($results)->toHaveCount(1);

    $set->refresh();
    expect((float) $set->weight)->toBe(45.0)
        ->and($set->reps)->toBe(9);
});

test('sem correções, o log_set da fila continua atualizando a série como antes', function () {
    $session = correctionSession($this->user, $this->workout, now()->subHour(), [[$this->exerciseA, 1, 40, 10]]);

    $this->actingAs($this->user)->postJson(route('sync.workout-sessions'), ['actions' => [
        ['uuid' => 'fresh', 'type' => 'log_set', 'client_session_id' => "session-{$session->id}", 'session_id' => $session->id, 'occurred_at' => now()->subMinutes(5)->toISOString(), 'exercise_id' => $this->exerciseA->id, 'set_number' => 1, 'weight' => 42.5, 'reps' => 10],
    ]])->assertOk();

    expect((float) $session->setLogs()->first()->weight)->toBe(42.5);
});

// ─── Cópias no aparelho ───────────────────────────────────────────────────────

test('o service worker sabe expurgar o histórico em cache depois de uma correção', function () {
    $content = $this->get('/sw.js')->getContent();

    expect($content)->toContain('HISTORY_INERTIA_PATH_PATTERNS')
        ->and($content)->toContain('event.data.includeHistory');
});

test('o detalhe do histórico expõe o equipamento usado pelo passo de carga do editor', function () {
    $this->exerciseA->update(['equipment' => 'halteres']);
    $session = correctionSession($this->user, $this->workout, now()->subDay(), [[$this->exerciseA, 1, 12, 10]]);

    $this->actingAs($this->user)
        ->get(route('history.show', $session))
        ->assertInertia(fn (Assert $page) => $page->where('session.exercises.0.equipment', 'halteres'));
});
