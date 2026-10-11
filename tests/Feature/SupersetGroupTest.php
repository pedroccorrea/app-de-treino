<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Services\WorkoutService;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Phase 51: exercícios combinados (bi-set/tri-set) — banco e backend do
 * grupo na ficha. Nenhuma tela muda; só o dado e a regra de normalização.
 */

/**
 * Grupos gravados na ficha, na ordem do pivot.
 *
 * @return array<int, ?int>
 */
function storedSupersetGroups(Workout $workout): array
{
    return $workout->workoutExercises()
        ->orderBy('order')
        ->pluck('superset_group')
        ->map(fn ($group) => $group !== null ? (int) $group : null)
        ->all();
}

/**
 * Cria uma ficha "como em produção hoje": linhas do pivot sem superset_group.
 */
function legacyWorkout(User $user, int $exerciseCount = 3): Workout
{
    $workout = $user->workouts()->create(['name' => 'Ficha Antiga']);

    foreach (Exercise::query()->orderBy('id')->limit($exerciseCount)->get() as $i => $exercise) {
        $workout->workoutExercises()->create([
            'exercise_id' => $exercise->id,
            'order' => $i,
            'target_sets' => 3,
            'target_reps' => '10',
        ]);
    }

    return $workout;
}

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->exerciseIds = Exercise::query()->orderBy('id')->limit(6)->pluck('id')->all();
});

// --- Migration ---------------------------------------------------------------

test('a coluna superset_group existe, é anulável e a trava unique(workout_id, exercise_id) continua valendo', function () {
    expect(Schema::hasColumn('workout_exercises', 'superset_group'))->toBeTrue();

    $workout = legacyWorkout($this->user, 1);

    expect(storedSupersetGroups($workout))->toBe([null]);

    expect(fn () => $workout->workoutExercises()->create([
        'exercise_id' => $this->exerciseIds[0],
        'order' => 1,
        'superset_group' => 1,
    ]))->toThrow(QueryException::class);
});

// --- Normalização (regra única) ---------------------------------------------

test('normalização: grupo com 2 vizinhos é mantido', function () {
    expect(app(WorkoutService::class)->normalizeSupersetGroups([null, 5, 5, null]))
        ->toBe([null, 1, 1, null]);
});

test('normalização: grupo com 1 exercício é limpo', function () {
    expect(app(WorkoutService::class)->normalizeSupersetGroups([3, null, 4, 4]))
        ->toBe([null, null, 1, 1]);
});

test('normalização: mesmo número em posições não vizinhas não forma grupo com quem não é vizinho', function () {
    $service = app(WorkoutService::class);

    // Isolados com o mesmo número: nenhum é vizinho do outro → ambos limpos.
    expect($service->normalizeSupersetGroups([1, null, 1]))->toBe([null, null, null]);

    // Dois trechos contínuos com o mesmo número viram grupos distintos.
    expect($service->normalizeSupersetGroups([1, 1, null, 1, 1]))->toBe([1, 1, null, 2, 2]);

    // Trecho válido + sobra isolada com o mesmo número: a sobra é limpa.
    expect($service->normalizeSupersetGroups([2, 2, 7, 2]))->toBe([1, 1, null, null]);
});

test('normalização: tri-set vizinho é mantido', function () {
    expect(app(WorkoutService::class)->normalizeSupersetGroups([9, 9, 9]))->toBe([1, 1, 1]);
});

test('normalização: números são renumerados de forma estável na ordem em que aparecem', function () {
    $service = app(WorkoutService::class);

    expect($service->normalizeSupersetGroups([8, 8, null, 3, 3, 3, 8, 8]))
        ->toBe([1, 1, null, 2, 2, 2, 3, 3]);

    // Grupos com números diferentes encostados continuam separados.
    expect($service->normalizeSupersetGroups([4, 4, 2, 2]))->toBe([1, 1, 2, 2]);

    // Idempotente: normalizar de novo não muda nada.
    expect($service->normalizeSupersetGroups([1, 1, null, 2, 2, 2, 3, 3]))
        ->toBe([1, 1, null, 2, 2, 2, 3, 3]);

    expect($service->normalizeSupersetGroups([]))->toBe([]);
    expect($service->normalizeSupersetGroups([null, null]))->toBe([null, null]);
});

// --- Fichas de hoje (sem o campo) continuam iguais --------------------------

test('ficha criada como em produção hoje é lida, editada e usada no treino como antes, com superset_group nulo', function () {
    $workout = legacyWorkout($this->user);
    [$a, $b, $c] = array_slice($this->exerciseIds, 0, 3);

    $this->actingAs($this->user)
        ->get(route('workouts.show', $workout))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workouts/Show')
            ->has('workout.exercises', 3)
            ->where('workout.exercises.0.superset_group', null)
            ->where('workout.exercises.0.target_reps', '10')
        );

    // Edição com o payload de hoje (sem superset_group).
    $this->actingAs($this->user)
        ->put(route('workouts.update', $workout), [
            'name' => 'Ficha Antiga Editada',
            'exercises' => [
                ['id' => $c, 'target_sets' => 4, 'target_reps' => '8-12', 'order' => 0],
                ['id' => $a, 'target_sets' => 3, 'target_reps' => '10', 'order' => 1],
                ['id' => $b, 'target_sets' => 3, 'target_reps' => '10', 'order' => 2],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($workout->fresh()->name)->toBe('Ficha Antiga Editada');
    expect(storedSupersetGroups($workout))->toBe([null, null, null]);
    expect($workout->workoutExercises()->orderBy('order')->pluck('exercise_id')->all())->toBe([$c, $a, $b]);

    // Treino: iniciar, abrir e registrar série.
    $this->actingAs($this->user)->post(route('workouts.start', $workout))->assertRedirect();
    $session = WorkoutSession::where('user_id', $this->user->id)->firstOrFail();

    $this->actingAs($this->user)
        ->get(route('workout-sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('exercises', 3)
            ->where('exercises.0.id', $c)
            ->where('exercises.0.superset_group', null)
        );

    $this->actingAs($this->user)
        ->post(route('workout-sessions.sets.store', $session), [
            'exercise_id' => $c,
            'set_number' => 1,
            'weight' => 20,
            'reps' => 10,
        ])
        ->assertSessionHasNoErrors();

    expect($session->setLogs()->count())->toBe(1);
});

test('editar sem o campo preserva o grupo já gravado (formulário ou fila offline antigos)', function () {
    $workout = legacyWorkout($this->user);
    $workout->workoutExercises()->where('order', '<', 2)->update(['superset_group' => 1]);
    [$a, $b, $c] = array_slice($this->exerciseIds, 0, 3);

    $this->actingAs($this->user)
        ->put(route('workouts.update', $workout), [
            'name' => 'Ficha',
            'exercises' => [
                ['id' => $a, 'order' => 0],
                ['id' => $b, 'order' => 1],
                ['id' => $c, 'order' => 2],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(storedSupersetGroups($workout))->toBe([1, 1, null]);
});

// --- Criar e editar (online) -------------------------------------------------

test('criar ficha online grava o grupo normalizado', function () {
    [$a, $b, $c, $d] = array_slice($this->exerciseIds, 0, 4);

    $this->actingAs($this->user)
        ->post(route('workouts.store'), [
            'name' => 'Ficha com Bi-set',
            'exercises' => [
                ['id' => $a, 'order' => 0, 'superset_group' => 7],
                ['id' => $b, 'order' => 1, 'superset_group' => 7],
                ['id' => $c, 'order' => 2, 'superset_group' => 3],
                ['id' => $d, 'order' => 3],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $workout = Workout::where('name', 'Ficha com Bi-set')->firstOrFail();

    expect(storedSupersetGroups($workout))->toBe([1, 1, null, null]);
});

test('editar ficha online grava o grupo normalizado (inclusive desfazendo grupo)', function () {
    $workout = legacyWorkout($this->user);
    [$a, $b, $c] = array_slice($this->exerciseIds, 0, 3);

    $this->actingAs($this->user)
        ->put(route('workouts.update', $workout), [
            'name' => 'Ficha',
            'exercises' => [
                ['id' => $a, 'order' => 0, 'superset_group' => 4],
                ['id' => $b, 'order' => 1, 'superset_group' => 4],
                ['id' => $c, 'order' => 2, 'superset_group' => 4],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(storedSupersetGroups($workout))->toBe([1, 1, 1]);

    // Enviar null explicitamente desfaz o grupo de quem saiu; o que sobra
    // sozinho também é limpo.
    $this->actingAs($this->user)
        ->put(route('workouts.update', $workout), [
            'name' => 'Ficha',
            'exercises' => [
                ['id' => $a, 'order' => 0, 'superset_group' => 4],
                ['id' => $b, 'order' => 1, 'superset_group' => null],
                ['id' => $c, 'order' => 2, 'superset_group' => 4],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(storedSupersetGroups($workout))->toBe([null, null, null]);
});

test('usuário não edita o grupo da ficha de outro usuário', function () {
    $workout = legacyWorkout(User::factory()->create());

    $this->actingAs($this->user)
        ->put(route('workouts.update', $workout), [
            'name' => 'Invasão',
            'exercises' => [
                ['id' => $this->exerciseIds[0], 'order' => 0, 'superset_group' => 1],
                ['id' => $this->exerciseIds[1], 'order' => 1, 'superset_group' => 1],
            ],
        ])
        ->assertForbidden();

    expect(storedSupersetGroups($workout))->toBe([null, null, null]);
});

// --- Sincronização offline ---------------------------------------------------

test('create_workout e update_workout pela sincronização offline gravam o grupo normalizado', function () {
    [$a, $b, $c] = array_slice($this->exerciseIds, 0, 3);

    $this->actingAs($this->user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-superset-create',
            'type' => 'create_workout',
            'client_uuid' => 'client-workout-superset',
            'occurred_at' => now()->toISOString(),
            'name' => 'Ficha Offline',
            'exercises' => [
                ['id' => $a, 'order' => 0, 'superset_group' => 2],
                ['id' => $b, 'order' => 1, 'superset_group' => 5],
                ['id' => $c, 'order' => 2, 'superset_group' => 5],
            ],
        ]]])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'synced');

    $workout = Workout::where('client_uuid', 'client-workout-superset')->firstOrFail();
    expect(storedSupersetGroups($workout))->toBe([null, 1, 1]);

    $this->actingAs($this->user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-superset-update',
            'type' => 'update_workout',
            'workout_id' => $workout->id,
            'occurred_at' => now()->toISOString(),
            'name' => 'Ficha Offline',
            'exercises' => [
                ['id' => $a, 'order' => 0, 'superset_group' => 3],
                ['id' => $b, 'order' => 1, 'superset_group' => 3],
                ['id' => $c, 'order' => 2, 'superset_group' => 3],
            ],
        ]]])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'synced');

    expect(storedSupersetGroups($workout))->toBe([1, 1, 1]);
});

// --- Reordenar ----------------------------------------------------------------

test('reordenar de forma que separa um grupo limpa o grupo que deixou de ser vizinho', function () {
    $workout = legacyWorkout($this->user);
    [$a, $b, $c] = array_slice($this->exerciseIds, 0, 3);
    $workout->workoutExercises()->whereIn('exercise_id', [$a, $b])->update(['superset_group' => 1]);

    // A e B (bi-set) ficam separados por C.
    $this->actingAs($this->user)
        ->patch(route('workouts.reorder', $workout), ['exercise_ids' => [$a, $c, $b]])
        ->assertRedirect();

    expect($workout->workoutExercises()->orderBy('order')->pluck('exercise_id')->all())->toBe([$a, $c, $b]);
    expect(storedSupersetGroups($workout))->toBe([null, null, null]);
});

test('reordenar mantendo os vizinhos preserva o grupo', function () {
    $workout = legacyWorkout($this->user);
    [$a, $b, $c] = array_slice($this->exerciseIds, 0, 3);
    $workout->workoutExercises()->whereIn('exercise_id', [$a, $b])->update(['superset_group' => 1]);

    $this->actingAs($this->user)
        ->patch(route('workouts.reorder', $workout), ['exercise_ids' => [$c, $b, $a]])
        ->assertRedirect();

    expect(storedSupersetGroups($workout))->toBe([null, 1, 1]);
});

// --- Campo em todas as respostas ---------------------------------------------

test('superset_group aparece na página da ficha, na edição, no treino em andamento e no estado offline', function () {
    $workout = legacyWorkout($this->user);
    [$a, $b] = array_slice($this->exerciseIds, 0, 2);
    $workout->workoutExercises()->whereIn('exercise_id', [$a, $b])->update(['superset_group' => 1]);

    $this->actingAs($this->user)
        ->get(route('workouts.show', $workout))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.superset_group', 1)
            ->where('workout.exercises.1.superset_group', 1)
            ->where('workout.exercises.2.superset_group', null)
        );

    $this->actingAs($this->user)
        ->get(route('workouts.edit', $workout))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workouts/Edit')
            ->where('workout.exercises.0.superset_group', 1)
            ->where('workout.exercises.2.superset_group', null)
        );

    $this->actingAs($this->user)
        ->get(route('workouts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('workouts.0.exercises.1.superset_group', 1)
        );

    $this->actingAs($this->user)->post(route('workouts.start', $workout));
    $session = WorkoutSession::where('user_id', $this->user->id)->firstOrFail();

    $this->actingAs($this->user)
        ->get(route('workout-sessions.show', $session))
        ->assertInertia(fn (Assert $page) => $page
            ->where('exercises.0.superset_group', 1)
            ->where('exercises.1.superset_group', 1)
            ->where('exercises.2.superset_group', null)
        );

    $state = $this->actingAs($this->user)->getJson(route('sync.state'))->assertOk();

    expect($state->json('workouts.0.exercises.*.superset_group'))->toBe([1, 1, null]);
});

// --- Validação ----------------------------------------------------------------

test('a validação rejeita superset_group inválido com mensagem em pt-BR', function (mixed $value, string $message) {
    $this->actingAs($this->user)
        ->post(route('workouts.store'), [
            'name' => 'Ficha',
            'exercises' => [['id' => $this->exerciseIds[0], 'superset_group' => $value]],
        ])
        ->assertSessionHasErrors(['exercises.0.superset_group' => $message]);

    $workout = legacyWorkout($this->user, 1);

    $this->actingAs($this->user)
        ->put(route('workouts.update', $workout), [
            'name' => 'Ficha',
            'exercises' => [['id' => $this->exerciseIds[0], 'superset_group' => $value]],
        ])
        ->assertSessionHasErrors(['exercises.0.superset_group' => $message]);

    $this->actingAs($this->user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'action-invalid',
            'type' => 'update_workout',
            'workout_id' => $workout->id,
            'occurred_at' => now()->toISOString(),
            'name' => 'Ficha',
            'exercises' => [['id' => $this->exerciseIds[0], 'superset_group' => $value]],
        ]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['actions.0.exercises.0.superset_group' => $message]);
})->with([
    'texto' => ['abc', 'O grupo do exercício combinado deve ser um número inteiro.'],
    'zero' => [0, 'O grupo do exercício combinado deve ser maior que zero.'],
    'negativo' => [-1, 'O grupo do exercício combinado deve ser maior que zero.'],
    'acima do limite' => [101, 'O grupo do exercício combinado deve ser no máximo 100.'],
]);

test('a validação aceita payload sem o campo e com o campo nulo', function () {
    $this->actingAs($this->user)
        ->post(route('workouts.store'), [
            'name' => 'Sem Campo',
            'exercises' => [
                ['id' => $this->exerciseIds[0]],
                ['id' => $this->exerciseIds[1], 'superset_group' => null],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(storedSupersetGroups(Workout::where('name', 'Sem Campo')->firstOrFail()))->toBe([null, null]);
});
