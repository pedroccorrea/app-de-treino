<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\WorkoutService;
use Database\Seeders\ExerciseSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Phase 52: montar o bi-set/tri-set na edição da ficha. A regra do frontend
 * (`resources/js/Utils/supersetGroups.js`) é verificada pelo Node via
 * `tests/js/supersetGroups.check.mjs`, e o salvamento (online e pela fila
 * offline) com o payload exato que o formulário/Service Worker enviam.
 */

function sgFormJsOutput(): array
{
    $node = (new ExecutableFinder)->find('node');

    if ($node === null) {
        test()->markTestSkipped('Node.js não encontrado para verificar a regra JS dos exercícios combinados.');
    }

    $process = new Process([$node, base_path('tests/js/supersetGroups.check.mjs')], base_path());
    $process->mustRun();

    return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return array<int, ?int>
 */
function sgFormStoredGroups(Workout $workout): array
{
    return $workout->workoutExercises()
        ->orderBy('order')
        ->pluck('superset_group')
        ->map(fn ($group) => $group !== null ? (int) $group : null)
        ->all();
}

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->exerciseIds = Exercise::query()->orderBy('id')->limit(4)->pluck('id')->all();
});

// --- Regra do frontend --------------------------------------------------------

test('a normalização do frontend é idêntica à do WorkoutService', function () {
    $cases = [
        'vazio' => [],
        'sozinhos' => [null, null, null],
        'bi_set' => [7, 7, null],
        'grupo_de_um' => [3, null, null],
        'nao_vizinhos' => [1, 1, null, 1, 1],
        'separados_por_outro' => [4, null, 4],
        'vizinhos_diferentes' => [2, 5, 5],
        'renumera' => [9, 9, 2, 2, 2],
        'tri_set' => [3, 3, 3],
    ];

    $js = sgFormJsOutput()['normalize'];
    $service = app(WorkoutService::class);

    foreach ($cases as $name => $groups) {
        expect($js[$name])->toBe($service->normalizeSupersetGroups($groups), "Divergência em \"{$name}\"");
    }
});

test('"Juntar com o de cima" cria bi-set, forma tri-set e trata ficha antiga sem o campo', function () {
    $join = sgFormJsOutput()['join'];

    expect($join['cria_bi_set']['groups'])->toBe([1, 1, null])
        ->and($join['vira_tri_set']['groups'])->toBe([1, 1, 1])
        ->and($join['bloco_entra_no_de_cima']['groups'])->toBe([null, 1, 1, 1])
        ->and($join['primeiro_nao_muda']['groups'])->toBe([null, null])
        ->and($join['ficha_antiga']['groups'])->toBe([null, 1, 1])
        ->and($join['rotulo_quatro'])->toBe(['Combinado']);
});

test('"Separar" e remover seguem a regra do backend e nunca deixam grupo com não vizinhos', function () {
    $output = sgFormJsOutput();

    expect($output['separate']['ponta_do_tri_set']['groups'])->toBe([1, 1, null])
        ->and($output['separate']['meio_do_tri_set']['groups'])->toBe([null, null, null])
        ->and($output['separate']['bi_set_desfaz']['groups'])->toBe([null, null, null])
        ->and($output['separate']['segundo_de_quatro']['groups'])->toBe([null, null, 1, 1])
        ->and($output['remove']['meio_do_tri_set'])->toBe(['names' => 'AC', 'groups' => [1, 1], 'orders' => [0, 1]])
        ->and($output['remove']['um_do_bi_set']['groups'])->toBe([null, null]);
});

test('mover (arrastar e setas) nunca coloca um exercício de fora no meio de um grupo', function () {
    $output = sgFormJsOutput();
    $move = $output['move'];
    $arrows = $output['arrows'];

    expect($move['sozinho_sobe_no_grupo'])->toBe(['names' => 'DABC', 'groups' => [null, 1, 1, 1], 'orders' => [0, 1, 2, 3]])
        ->and($move['sozinho_desce_no_grupo']['names'])->toBe('BCDA')
        ->and($move['sozinho_desce_no_grupo']['groups'])->toBe([1, 1, 1, null])
        ->and($move['dentro_do_grupo']['names'])->toBe('BCAD')
        ->and($move['dentro_do_grupo']['groups'])->toBe([1, 1, 1, null])
        ->and($move['grupo_sai_inteiro']['names'])->toBe('CDAB')
        ->and($move['grupo_sai_inteiro']['groups'])->toBe([1, 1, null, null])
        ->and($move['sem_grupo']['names'])->toBe('BCAD')
        ->and($arrows['sozinho_sobe_resultado']['names'])->toBe('CAB')
        ->and($arrows['sozinho_sobe_resultado']['groups'])->toBe([null, 1, 1])
        ->and($arrows['grupo_ponta_cima'])->toBeNull()
        ->and($arrows['grupo_interno'])->toBe(2)
        ->and($arrows['bloco_sobe']['names'])->toBe('BCA')
        ->and($arrows['bloco_sobe']['groups'])->toBe([1, 1, null])
        ->and($arrows['bloco_primeiro'])->toBeNull();
});

// --- Salvamento online -----------------------------------------------------------

test('criar e editar a ficha com o payload do formulário grava o grupo', function () {
    [$a, $b, $c] = array_slice($this->exerciseIds, 0, 3);

    $this->actingAs($this->user)
        ->post(route('workouts.store'), [
            'name' => 'Ficha com Bi-set',
            'exercises' => [
                ['id' => $a, 'name' => 'A', 'primary_muscle' => 'Peito', 'target_sets' => 3, 'target_reps' => '10', 'order' => 0, 'superset_group' => 1],
                ['id' => $b, 'name' => 'B', 'primary_muscle' => 'Peito', 'target_sets' => 3, 'target_reps' => '10', 'order' => 1, 'superset_group' => 1],
                ['id' => $c, 'name' => 'C', 'primary_muscle' => 'Peito', 'target_sets' => 3, 'target_reps' => '10', 'order' => 2, 'superset_group' => null],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $workout = Workout::where('name', 'Ficha com Bi-set')->firstOrFail();
    expect(sgFormStoredGroups($workout))->toBe([1, 1, null]);

    // "Juntar com o de cima" no C → tri-set.
    $this->actingAs($this->user)
        ->put(route('workouts.update', $workout), [
            'name' => 'Ficha com Tri-set',
            'exercises' => [
                ['id' => $a, 'target_sets' => 3, 'target_reps' => '10', 'order' => 0, 'superset_group' => 1],
                ['id' => $b, 'target_sets' => 3, 'target_reps' => '10', 'order' => 1, 'superset_group' => 1],
                ['id' => $c, 'target_sets' => 3, 'target_reps' => '10', 'order' => 2, 'superset_group' => 1],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(sgFormStoredGroups($workout))->toBe([1, 1, 1]);

    $this->actingAs($this->user)
        ->get(route('workouts.show', $workout))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workouts/Show')
            ->where('workout.exercises.0.superset_group', 1)
            ->where('workout.exercises.2.superset_group', 1)
        );
});

test('um grupo inválido vindo do cliente é normalizado pelo backend', function () {
    [$a, $b, $c] = array_slice($this->exerciseIds, 0, 3);

    // Grupo com não vizinhos e grupo de um só exercício.
    $this->actingAs($this->user)
        ->post(route('workouts.store'), [
            'name' => 'Ficha Suja',
            'exercises' => [
                ['id' => $a, 'order' => 0, 'superset_group' => 8],
                ['id' => $b, 'order' => 1, 'superset_group' => null],
                ['id' => $c, 'order' => 2, 'superset_group' => 8],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(sgFormStoredGroups(Workout::where('name', 'Ficha Suja')->firstOrFail()))->toBe([null, null, null]);
});

test('ficha sem grupo continua sendo salva e exibida sem grupo', function () {
    [$a, $b] = array_slice($this->exerciseIds, 0, 2);

    $this->actingAs($this->user)
        ->post(route('workouts.store'), [
            'name' => 'Ficha Simples',
            'exercises' => [
                ['id' => $a, 'order' => 0, 'superset_group' => null],
                ['id' => $b, 'order' => 1, 'superset_group' => null],
            ],
        ])
        ->assertSessionHasNoErrors();

    $workout = Workout::where('name', 'Ficha Simples')->firstOrFail();
    expect(sgFormStoredGroups($workout))->toBe([null, null]);

    $this->actingAs($this->user)
        ->get(route('workouts.edit', $workout))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Workouts/Edit')
            ->where('workout.exercises.0.superset_group', null)
            ->where('workout.exercises.1.superset_group', null)
        );
});

// --- Fila offline ----------------------------------------------------------------

test('ficha com grupo criada e editada offline é gravada com o grupo quando a fila sincroniza', function () {
    [$a, $b, $c, $d] = $this->exerciseIds;

    // Mesmo formato que o Service Worker enfileira (`body.exercises` do formulário).
    $this->actingAs($this->user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'phase52-create',
            'type' => 'create_workout',
            'client_uuid' => 'phase52-client-workout',
            'occurred_at' => now()->toISOString(),
            'name' => 'Ficha Offline Bi-set',
            'description' => null,
            'days_of_week' => [],
            'workout_program_id' => null,
            'exercises' => [
                ['id' => $a, 'name' => 'A', 'primary_muscle' => 'Peito', 'target_sets' => 3, 'target_reps' => '10', 'order' => 0, 'superset_group' => null],
                ['id' => $b, 'name' => 'B', 'primary_muscle' => 'Peito', 'target_sets' => 3, 'target_reps' => '10', 'order' => 1, 'superset_group' => 1],
                ['id' => $c, 'name' => 'C', 'primary_muscle' => 'Peito', 'target_sets' => 3, 'target_reps' => '10', 'order' => 2, 'superset_group' => 1],
            ],
        ]]])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'synced');

    $workout = Workout::where('client_uuid', 'phase52-client-workout')->firstOrFail();
    expect(sgFormStoredGroups($workout))->toBe([null, 1, 1]);

    // Edição offline: o bi-set B+C sobe inteiro e D entra como sozinho.
    $this->actingAs($this->user)
        ->postJson(route('sync.mutations'), ['actions' => [[
            'uuid' => 'phase52-update',
            'type' => 'update_workout',
            'workout_id' => $workout->id,
            'occurred_at' => now()->toISOString(),
            'name' => 'Ficha Offline Bi-set',
            'description' => null,
            'days_of_week' => [],
            'exercises' => [
                ['id' => $b, 'order' => 0, 'superset_group' => 1],
                ['id' => $c, 'order' => 1, 'superset_group' => 1],
                ['id' => $a, 'order' => 2, 'superset_group' => null],
                ['id' => $d, 'order' => 3, 'superset_group' => null],
            ],
        ]]])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'synced');

    expect($workout->workoutExercises()->orderBy('order')->pluck('exercise_id')->all())->toBe([$b, $c, $a, $d])
        ->and(sgFormStoredGroups($workout))->toBe([1, 1, null, null]);
});

// --- Service Worker --------------------------------------------------------------

test('o Service Worker carrega superset_group nas fichas offline e teve o CACHE_VERSION atualizado', function () {
    $sw = file_get_contents(resource_path('pwa/sw.js'));

    expect($sw)->toContain("const CACHE_VERSION = 'v13';")
        ->and($sw)->toContain('superset_group: exercise.superset_group ?? null');

    // Tanto na ficha montada offline (criação/edição) quanto na página
    // sintetizada a partir de um registro antigo do IndexedDB.
    expect(substr_count($sw, 'superset_group: exercise.superset_group ?? null'))->toBeGreaterThanOrEqual(2);
});
