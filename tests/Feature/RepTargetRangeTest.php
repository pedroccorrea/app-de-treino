<?php

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\ProgressiveOverloadService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Phase 47 — a faixa da ficha ("8-10") é META (mínimo/máximo), nunca o
 * número de repetições feitas. Ver App\DataTransferObjects\RepRange.
 */
function rtFakeGemini(array $body): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($body)]]]]],
        ], 200),
    ]);
}

function rtExercise(string $name = 'Supino Reto'): Exercise
{
    return Exercise::query()->create([
        'user_id' => null,
        'name' => $name,
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
    ]);
}

/**
 * Ficha "como em produção" antes desta phase: só o texto, sem as colunas
 * novas (inserida pelo pivot, sem passar por nenhuma regra nova).
 */
function rtLegacyWorkout(User $user, Exercise $exercise, string $targetReps = '8-10'): Workout
{
    $workout = $user->workouts()->create(['name' => 'Ficha Antiga']);
    $workout->exercises()->attach($exercise->id, ['order' => 0, 'target_sets' => 3, 'target_reps' => $targetReps]);

    return $workout;
}

test('scan de uma ficha com "8-10" grava a meta 8–10 e a sessão recebe REPS inicial inteiro', function () {
    rtFakeGemini([
        'workout_name' => 'Treino A',
        'exercises' => [
            ['name' => 'Supino Reto', 'target_sets' => 3, 'target_reps' => '8-10', 'target_reps_min' => 8, 'target_reps_max' => 10, 'muscle_group' => 'chest'],
        ],
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    $workout = $user->workouts()->firstOrFail();
    $row = WorkoutExercise::query()->where('workout_id', $workout->id)->firstOrFail();

    expect($row->target_reps)->toBe('8-10')
        ->and($row->target_reps_min)->toBe(8)
        ->and($row->target_reps_max)->toBe(10);

    $session = $user->workoutSessions()->create(['workout_id' => $workout->id, 'started_at' => now()]);

    // Sem sugestão da IA e sem histórico: o REPS inicial é o mínimo da faixa
    // (8), calculado no frontend a partir destes props (Utils/repTarget.js).
    $this->actingAs($user)->get(route('workout-sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkoutSessions/Active')
            ->where('exercises.0.target_reps', '8-10')
            ->where('exercises.0.target_reps_min', 8)
            ->where('exercises.0.target_reps_max', 10)
            ->where('lastLogs', [])
        );
});

test('scan nunca inventa número: valida a resposta da IA pela regra única', function () {
    rtFakeGemini([
        'workout_name' => 'Treino B',
        'exercises' => [
            // A IA "inventou" números para um texto sem meta numérica.
            ['name' => 'Prancha', 'target_reps' => 'até a falha', 'target_reps_min' => 30, 'target_reps_max' => 60, 'muscle_group' => 'abs'],
            // Só os números, sem o texto original.
            ['name' => 'Remada', 'target_reps_min' => 12, 'target_reps_max' => 10, 'muscle_group' => 'back'],
            // Número no lugar de string.
            ['name' => 'Agachamento', 'target_reps' => 12, 'muscle_group' => 'quadriceps'],
            // Lixo.
            ['name' => 'Rosca', 'target_reps_min' => 'muitas', 'muscle_group' => 'biceps'],
        ],
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    $rows = WorkoutExercise::query()->orderBy('order')->get();

    expect($rows->map(fn ($row) => [$row->target_reps, $row->target_reps_min, $row->target_reps_max])->all())->toBe([
        ['até a falha', null, null],
        ['10-12', 10, 12],
        ['12', 12, 12],
        [null, null, null],
    ]);
});

test('ficha antiga com "8-10" funciona na sessão e na IA de progressão sem alterar o registro', function () {
    $user = User::factory()->create();
    $exercise = rtExercise();
    $workout = rtLegacyWorkout($user, $exercise);

    $before = WorkoutExercise::query()->firstOrFail()->getAttributes();

    $past = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);
    $past->setLogs()->create(['exercise_id' => $exercise->id, 'set_number' => 1, 'weight' => 40, 'reps' => 9]);

    $session = $user->workoutSessions()->create(['workout_id' => $workout->id, 'started_at' => now()]);

    $this->actingAs($user)->get(route('workout-sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->where('exercises.0.target_reps', '8-10')
            ->where('exercises.0.target_reps_min', 8)
            ->where('exercises.0.target_reps_max', 10)
            ->where("lastLogs.{$exercise->id}.reps", 9)
        );

    // A IA sugere 14 repetições: fora da faixa, volta para o topo (10).
    rtFakeGemini(['recommendations' => [[
        'exercise_id' => $exercise->id,
        'suggested_load' => 40,
        'suggested_reps' => 14,
        'rationale' => 'Suba as repetições.',
        'confidence' => 'high',
    ]]]);

    $suggestion = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout->fresh())->first();

    expect($suggestion->suggested_reps)->toBe(10);

    // O prompt leva a meta interpretada (mínimo/máximo), não o texto cru.
    Http::assertSent(function (Request $request) {
        $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text');

        return preg_match('/"target_reps":\s*\{\s*"min":\s*8,\s*"max":\s*10\s*\}/', $prompt) === 1;
    });

    expect(WorkoutExercise::query()->firstOrFail()->getAttributes())->toBe($before)
        ->and($before['target_reps_min'])->toBeNull();
});

test('IA de progressão arredonda repetições quebradas e não trava meta de número único', function () {
    $user = User::factory()->create();
    $exercise = rtExercise();
    $workout = rtLegacyWorkout($user, $exercise, '10');

    $past = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);
    $past->setLogs()->create(['exercise_id' => $exercise->id, 'set_number' => 1, 'weight' => 40, 'reps' => 10]);

    rtFakeGemini(['recommendations' => [[
        'exercise_id' => $exercise->id,
        'suggested_load' => 40,
        'suggested_reps' => 11.6,
        'rationale' => 'Mais uma repetição.',
        'confidence' => 'medium',
    ]]]);

    $suggestion = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout->fresh())->first();

    expect($suggestion->suggested_reps)->toBe(12);
});

test('criação e edição manual da ficha preenchem mínimo e máximo pela mesma regra', function () {
    $user = User::factory()->create();
    $supino = rtExercise('Supino Reto');
    $prancha = rtExercise('Prancha');

    $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Ficha Manual',
        'exercises' => [
            ['id' => $supino->id, 'target_sets' => 3, 'target_reps' => '8 a 10'],
            ['id' => $prancha->id, 'target_sets' => 3, 'target_reps' => 'até a falha'],
        ],
    ])->assertSessionHasNoErrors();

    $workout = $user->workouts()->firstOrFail();
    $rowFor = fn (Exercise $exercise) => WorkoutExercise::query()
        ->where('workout_id', $workout->id)->where('exercise_id', $exercise->id)->firstOrFail();

    expect([$rowFor($supino)->target_reps, $rowFor($supino)->target_reps_min, $rowFor($supino)->target_reps_max])->toBe(['8 a 10', 8, 10])
        ->and([$rowFor($prancha)->target_reps_min, $rowFor($prancha)->target_reps_max])->toBe([null, null]);

    $this->actingAs($user)->put(route('workouts.update', $workout), [
        'name' => 'Ficha Manual',
        'exercises' => [
            ['id' => $supino->id, 'target_sets' => 3, 'target_reps' => '12-10'],
            ['id' => $prancha->id, 'target_sets' => 3, 'target_reps' => '30'],
        ],
    ])->assertSessionHasNoErrors();

    expect([$rowFor($supino)->target_reps, $rowFor($supino)->target_reps_min, $rowFor($supino)->target_reps_max])->toBe(['12-10', 10, 12])
        ->and([$rowFor($prancha)->target_reps_min, $rowFor($prancha)->target_reps_max])->toBe([30, 30]);
});

test('meta de repetições inválida no formulário mostra mensagem amigável em pt-BR', function () {
    $user = User::factory()->create();
    $exercise = rtExercise();

    $this->actingAs($user)->post(route('workouts.store'), [
        'name' => 'Ficha',
        'exercises' => [['id' => $exercise->id, 'target_reps' => str_repeat('9', 51)]],
    ])->assertSessionHasErrors(['exercises.0.target_reps' => 'A meta de repetições deve ter no máximo 50 caracteres.']);
});

test('sincronização offline de criação e edição de ficha segue a mesma regra', function () {
    $user = User::factory()->create();
    $exercise = rtExercise();

    $this->actingAs($user)->postJson(route('sync.mutations'), ['actions' => [[
        'uuid' => 'action-create',
        'type' => 'create_workout',
        'client_uuid' => 'client-workout-phase-47',
        'occurred_at' => now()->subMinute()->toISOString(),
        'name' => 'Ficha Offline',
        'exercises' => [['id' => $exercise->id, 'target_sets' => 3, 'target_reps' => '8–10', 'order' => 0]],
    ]]])->assertOk()->assertJsonPath('results.0.status', 'synced');

    $workout = Workout::query()->where('client_uuid', 'client-workout-phase-47')->firstOrFail();
    $row = fn () => WorkoutExercise::query()->where('workout_id', $workout->id)->firstOrFail();

    expect([$row()->target_reps_min, $row()->target_reps_max])->toBe([8, 10]);

    $this->actingAs($user)->postJson(route('sync.mutations'), ['actions' => [[
        'uuid' => 'action-update',
        'type' => 'update_workout',
        'workout_id' => $workout->id,
        'occurred_at' => now()->toISOString(),
        'name' => 'Ficha Offline',
        'days_of_week' => [],
        'exercises' => [['id' => $exercise->id, 'target_sets' => 3, 'target_reps' => '12/10/8', 'order' => 0]],
    ]]])->assertOk()->assertJsonPath('results.0.status', 'synced');

    expect([$row()->target_reps, $row()->target_reps_min, $row()->target_reps_max])->toBe(['12/10/8', 8, 12]);
});

test('cache offline (sync-state) leva mínimo e máximo, inclusive de fichas antigas', function () {
    $user = User::factory()->create();
    rtLegacyWorkout($user, rtExercise(), '8 - 10');

    $this->actingAs($user)->getJson(route('sync.state'))
        ->assertOk()
        ->assertJsonPath('workouts.0.exercises.0.target_reps', '8 - 10')
        ->assertJsonPath('workouts.0.exercises.0.target_reps_min', 8)
        ->assertJsonPath('workouts.0.exercises.0.target_reps_max', 10);
});

test('o REPS enviado ao salvar a série é sempre inteiro: a faixa da ficha é recusada', function () {
    $user = User::factory()->create();
    $exercise = rtExercise();
    $workout = rtLegacyWorkout($user, $exercise);
    $session = $user->workoutSessions()->create(['workout_id' => $workout->id, 'started_at' => now()]);

    $this->actingAs($user)->post(route('workout-sessions.sets.store', $session), [
        'exercise_id' => $exercise->id, 'set_number' => 1, 'weight' => 40, 'reps' => '8-10',
    ])->assertSessionHasErrors('reps');

    $this->actingAs($user)->post(route('workout-sessions.sets.store', $session), [
        'exercise_id' => $exercise->id, 'set_number' => 1, 'weight' => 40, 'reps' => 8,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('set_logs', ['workout_session_id' => $session->id, 'set_number' => 1, 'reps' => 8]);
});
