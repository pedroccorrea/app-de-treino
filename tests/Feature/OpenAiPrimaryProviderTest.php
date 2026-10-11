<?php

use App\Enums\AiFeature;
use App\Enums\AiTask;
use App\Enums\Equipment;
use App\Enums\MovementPattern;
use App\Enums\MuscleGroup;
use App\Exceptions\AiUnavailableException;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\AI\AiManager;
use App\Services\ExerciseEquipmentService;
use App\Services\ExerciseMovementPatternService;
use App\Services\ProgressiveOverloadService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 49: OpenAI (gpt-6-luna, Responses API) como IA principal em todas as
 * funcionalidades e Gemini 3.5 Flash (camada gratuita) como reserva. Nenhum
 * teste chega a uma API real: OpenAI e Gemini são sempre simulados.
 */

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Corpo de uma resposta da Responses API (POST /v1/responses).
 *
 * @return array<string, mixed>
 */
function p49OpenAiBody(string $text, array $usage = ['input_tokens' => 120, 'output_tokens' => 30, 'total_tokens' => 150]): array
{
    return [
        'id' => 'resp_test',
        'object' => 'response',
        'status' => 'completed',
        'output' => [
            ['type' => 'reasoning', 'summary' => []],
            ['type' => 'message', 'role' => 'assistant', 'content' => [
                ['type' => 'output_text', 'text' => $text, 'annotations' => []],
            ]],
        ],
        'usage' => $usage,
    ];
}

/**
 * @return array<string, mixed>
 */
function p49GeminiBody(string $text): array
{
    return [
        'candidates' => [
            ['content' => ['parts' => [['text' => $text]]]],
        ],
        'usageMetadata' => ['promptTokenCount' => 80, 'candidatesTokenCount' => 20, 'totalTokenCount' => 100],
    ];
}

/**
 * @return array<string, mixed>
 */
function p49OpenAiError(int $status, string $code, string $message = 'erro simulado'): array
{
    return ['error' => ['message' => $message, 'type' => $code === 'insufficient_quota' ? 'insufficient_quota' : 'invalid_request_error', 'param' => null, 'code' => $code]];
}

function p49IsOpenAi(Request $request): bool
{
    return str_starts_with($request->url(), 'https://api.openai.com/');
}

function p49IsGemini(Request $request): bool
{
    return str_starts_with($request->url(), 'https://generativelanguage.googleapis.com/');
}

function p49OpenAiCalls(): int
{
    return Http::recorded(fn (Request $request) => p49IsOpenAi($request))->count();
}

function p49Exercise(string $name, ?Equipment $equipment = null, ?MovementPattern $pattern = null): Exercise
{
    return Exercise::query()->create([
        'user_id' => null,
        'name' => $name,
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
        'equipment' => $equipment,
        'movement_pattern' => $pattern,
    ]);
}

/**
 * Ficha com um exercício e uma sessão concluída, para haver histórico.
 */
function p49WorkoutWithHistory(User $user, Exercise $exercise, float $weight = 40, int $reps = 10): Workout
{
    $workout = $user->workouts()->create(['name' => 'Treino Teste']);
    $workout->workoutExercises()->create([
        'exercise_id' => $exercise->id,
        'order' => 0,
        'target_sets' => 3,
        'target_reps' => '10',
    ]);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);
    $session->setLogs()->create([
        'exercise_id' => $exercise->id,
        'set_number' => 1,
        'weight' => $weight,
        'reps' => $reps,
    ]);

    return $workout;
}

function p49ScanFixture(): string
{
    return file_get_contents(base_path('tests/Fixtures/ocr-workout-sheet.json'));
}

beforeEach(function () {
    // Mesma configuração de produção (os padrões do código).
    config([
        'services.ai.default_driver' => 'openai',
        'services.ai.fallback_driver' => 'gemini',
        'services.openai.key' => 'test-openai-api-key',
        'services.openai.models.vision' => 'gpt-6-luna',
        'services.openai.models.fast_text' => 'gpt-6-luna',
        'services.gemini.models.vision' => 'gemini-3.5-flash',
        'services.gemini.models.fast_text' => 'gemini-3.5-flash',
    ]);
});

// ─── Padrões no código e .env.example ────────────────────────────────────────

test('os padrões do código são OpenAI principal, Gemini reserva, gpt-6-luna e gemini-3.5-flash', function () {
    $vars = [
        'AI_DEFAULT_DRIVER', 'AI_FALLBACK_DRIVER',
        'OPENAI_VISION_MODEL', 'OPENAI_FAST_TEXT_MODEL',
        'GEMINI_VISION_MODEL', 'GEMINI_FAST_TEXT_MODEL',
    ];
    $saved = [];

    foreach ($vars as $var) {
        $saved[$var] = [getenv($var), $_ENV[$var] ?? null, $_SERVER[$var] ?? null];
        putenv($var);
        unset($_ENV[$var], $_SERVER[$var]);
    }

    try {
        $services = require config_path('services.php');
    } finally {
        foreach ($saved as $var => [$env, $envArray, $server]) {
            if ($env !== false) {
                putenv("{$var}={$env}");
            }
            if ($envArray !== null) {
                $_ENV[$var] = $envArray;
            }
            if ($server !== null) {
                $_SERVER[$var] = $server;
            }
        }
    }

    expect($services['ai']['default_driver'])->toBe('openai')
        ->and($services['ai']['fallback_driver'])->toBe('gemini')
        ->and($services['openai']['models'])->toBe(['vision' => 'gpt-6-luna', 'fast_text' => 'gpt-6-luna'])
        ->and($services['gemini']['models'])->toBe(['vision' => 'gemini-3.5-flash', 'fast_text' => 'gemini-3.5-flash'])
        // Variáveis sem efeito foram removidas da configuração.
        ->and($services['gemini'])->not->toHaveKey('model')
        ->and($services['claude'])->not->toHaveKey('model')
        ->and($services['groq'])->not->toHaveKey('model');
});

test('o .env.example está alinhado com os padrões do código e não traz valores de chave', function () {
    $example = file_get_contents(base_path('.env.example'));

    expect($example)
        ->toContain("AI_DEFAULT_DRIVER=openai\n")
        ->toContain("AI_FALLBACK_DRIVER=gemini\n")
        ->toContain("OPENAI_API_KEY=\n")
        ->toContain("OPENAI_VISION_MODEL=gpt-6-luna\n")
        ->toContain("OPENAI_FAST_TEXT_MODEL=gpt-6-luna\n")
        ->toContain("GEMINI_API_KEY=\n")
        ->toContain("GEMINI_VISION_MODEL=gemini-3.5-flash\n")
        ->toContain("GEMINI_FAST_TEXT_MODEL=gemini-3.5-flash\n")
        ->not->toContain('GEMINI_MODEL=')
        ->not->toContain('ANTHROPIC_MODEL=')
        ->not->toContain('GROQ_MODEL=');
});

// ─── Conector OpenAI (Responses API) ─────────────────────────────────────────

test('o texto rápido usa a Responses API com gpt-6-luna, JSON nativo e sem raciocínio estendido', function () {
    Http::fake(['api.openai.com/*' => Http::response(p49OpenAiBody('{"ok": true}'))]);

    $result = app(AiManager::class)->generateStructured('Responda em JSON.', 'instrução de sistema', AiTask::FastText);

    expect($result)->toBe(['ok' => true]);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/responses'
        && $request->hasHeader('Authorization', 'Bearer test-openai-api-key')
        && $request['model'] === 'gpt-6-luna'
        && $request['text'] === ['format' => ['type' => 'json_object']]
        && $request['reasoning'] === ['effort' => 'none']
        && $request['instructions'] === 'instrução de sistema'
        && $request['store'] === false
        && $request['input'][0]['content'][0] === ['type' => 'input_text', 'text' => 'Responda em JSON.']);
    Http::assertNotSent(fn (Request $request) => p49IsGemini($request));
});

test('o scan usa a OpenAI (não fica mais preso ao Gemini) e envia a foto como input_image', function () {
    Http::fake(['api.openai.com/*' => Http::response(p49OpenAiBody(p49ScanFixture()))]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    $workout = $user->workouts()->firstOrFail();
    $response->assertRedirect(route('workouts.show', $workout));
    $response->assertSessionHas('success');
    expect($workout->exercises()->count())->toBe(4);

    Http::assertSent(function (Request $request) {
        $image = $request['input'][0]['content'][1] ?? [];

        return p49IsOpenAi($request)
            && $request['model'] === 'gpt-6-luna'
            && $request['reasoning'] === ['effort' => 'low']
            && $image['type'] === 'input_image'
            && $image['detail'] === 'high'
            && str_starts_with($image['image_url'], 'data:image/jpeg;base64,');
    });
    Http::assertNotSent(fn (Request $request) => p49IsGemini($request));
});

// ─── Cada funcionalidade funciona com a OpenAI ───────────────────────────────

test('sugestões de progressão funcionam com a OpenAI', function () {
    $user = User::factory()->create();
    $exercise = p49Exercise('Supino Reto com Barra', Equipment::BarbellPlates, MovementPattern::HorizontalPush);
    $workout = p49WorkoutWithHistory($user, $exercise);

    Http::fake(['api.openai.com/*' => Http::response(p49OpenAiBody(json_encode([
        'recommendations' => [[
            'exercise_id' => $exercise->id,
            'suggested_load' => 42.5,
            'suggested_reps' => 8,
            'rationale' => 'Progrida a carga com base no desempenho recente.',
            'confidence' => 'high',
        ]],
    ])))]);

    $suggestions = app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions->first()->exercise_id)->toBe($exercise->id)
        ->and($suggestions->first()->suggested_reps)->toBe(8);
    expect(p49OpenAiCalls())->toBe(1);
    Http::assertNotSent(fn (Request $request) => p49IsGemini($request));
});

test('carga inicial de exercício novo funciona com a OpenAI', function () {
    $user = User::factory()->create();
    $barbell = p49Exercise('Supino Reto com Barra');
    $dumbbells = p49Exercise('Supino Reto com Halteres');

    $past = $user->workoutSessions()->create([
        'workout_id' => null,
        'started_at' => now()->subDays(3)->subHour(),
        'completed_at' => now()->subDays(3),
    ]);
    $past->setLogs()->create(['exercise_id' => $barbell->id, 'set_number' => 1, 'weight' => 40, 'reps' => 8]);

    $workout = $user->workouts()->create(['name' => 'Treino Novo']);
    $workout->workoutExercises()->create(['exercise_id' => $dumbbells->id, 'order' => 0, 'target_sets' => 3, 'target_reps' => '8-10']);

    Http::fake(['api.openai.com/*' => Http::response(p49OpenAiBody(json_encode([
        'suggestions' => [
            ['exercise_id' => $dumbbells->id, 'suggested_load' => 16, 'suggested_reps' => 10, 'based_on_exercise_id' => $barbell->id],
        ],
    ])))]);

    $session = $user->workoutSessions()->create(['workout_id' => $workout->id, 'started_at' => now()]);

    $suggestions = $this->actingAs($user)
        ->getJson(route('workout-sessions.overload-suggestions', $session))
        ->assertOk()
        ->json('suggestions');

    expect($suggestions)->toHaveCount(1);
    expect($suggestions[0])->toMatchArray([
        'exercise_id' => $dumbbells->id,
        'kind' => 'carga_inicial',
        'suggested_load' => 16,
        'suggested_reps' => 10,
    ]);
    expect(p49OpenAiCalls())->toBe(1);
    Http::assertNotSent(fn (Request $request) => p49IsGemini($request));
});

test('classificação de equipamento funciona com a OpenAI', function () {
    $pelvic = p49Exercise('Elevação Pélvica');

    Http::fake(['api.openai.com/*' => Http::response(p49OpenAiBody(json_encode([
        'classifications' => [['exercise_id' => $pelvic->id, 'equipment' => 'barra_anilhas']],
    ])))]);

    app(ExerciseEquipmentService::class)->ensureClassified([$pelvic]);

    expect($pelvic->fresh()->equipment)->toBe(Equipment::BarbellPlates);
    expect(p49OpenAiCalls())->toBe(1);
    Http::assertNotSent(fn (Request $request) => p49IsGemini($request));
});

test('classificação de padrão de movimento funciona com a OpenAI', function () {
    $odd = p49Exercise('Movimento Esquisito A');

    Http::fake(['api.openai.com/*' => Http::response(p49OpenAiBody(json_encode([
        'classifications' => [['exercise_id' => $odd->id, 'movement_pattern' => 'puxar_vertical']],
    ])))]);

    app(ExerciseMovementPatternService::class)->ensureClassified([$odd]);

    expect($odd->fresh()->movement_pattern)->toBe(MovementPattern::VerticalPull);
    expect(p49OpenAiCalls())->toBe(1);
    Http::assertNotSent(fn (Request $request) => p49IsGemini($request));
});

// ─── Reserva que funciona de verdade ─────────────────────────────────────────

test('qualquer falha da OpenAI faz a mesma operação ser atendida pelo Gemini', function (string $failure) {
    $openAi = match ($failure) {
        'sem crédito' => Http::response(p49OpenAiError(429, 'insufficient_quota', 'You exceeded your current quota.'), 429),
        'crédito esgotado' => Http::response(p49OpenAiError(429, 'credit_balance_exhausted'), 429),
        'limite de gasto do projeto' => Http::response(p49OpenAiError(429, 'project_spend_limit_exceeded'), 429),
        'limite de gasto da organização' => Http::response(p49OpenAiError(429, 'organization_spend_limit_exceeded'), 429),
        'chave errada' => Http::response(p49OpenAiError(401, 'invalid_api_key', 'Incorrect API key provided.'), 401),
        'fora do ar' => Http::response('Service Unavailable', 503),
        'tempo esgotado' => fn () => throw new ConnectionException('cURL error 28: Operation timed out after 6000 milliseconds'),
        'resposta vazia' => Http::response(p49OpenAiBody('')),
        'JSON inválido' => Http::response(p49OpenAiBody('isso não é JSON')),
    };

    Http::fake([
        'api.openai.com/*' => $openAi,
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody('{"served_by": "gemini"}')),
    ]);

    $result = app(AiManager::class)->generateStructured('Responda em JSON.', task: AiTask::FastText);

    expect($result)->toBe(['served_by' => 'gemini']);
    Http::assertSent(fn (Request $request) => p49IsGemini($request));
})->with([
    'sem crédito', 'crédito esgotado', 'limite de gasto do projeto', 'limite de gasto da organização',
    'chave errada', 'fora do ar', 'tempo esgotado', 'resposta vazia', 'JSON inválido',
]);

test('sem a chave da OpenAI configurada, a operação vai direto para o Gemini sem chamar a OpenAI', function () {
    config(['services.openai.key' => '']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody('{"served_by": "gemini"}')),
    ]);

    $result = app(AiManager::class)->generateStructured('Responda em JSON.', task: AiTask::FastText);

    expect($result)->toBe(['served_by' => 'gemini']);
    expect(p49OpenAiCalls())->toBe(0);
});

test('em erro de crédito ou de limite de gasto, a OpenAI é chamada uma única vez antes do Gemini', function (string $code) {
    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiError(429, $code), 429),
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody('{"served_by": "gemini"}')),
    ]);

    $result = app(AiManager::class)->generateStructured('Responda em JSON.', task: AiTask::FastText);

    expect($result)->toBe(['served_by' => 'gemini']);
    expect(p49OpenAiCalls())->toBe(1);
})->with([
    'insufficient_quota',
    'credit_balance_exhausted',
    'project_spend_limit_exceeded',
    'organization_spend_limit_exceeded',
    'organization_usage_limit_exceeded',
]);

test('uma instabilidade passageira da OpenAI tem no máximo uma nova tentativa curta antes da reserva', function () {
    Http::fake([
        'api.openai.com/*' => Http::response('Bad Gateway', 502),
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody('{"served_by": "gemini"}')),
    ]);

    $result = app(AiManager::class)->generateStructured('Responda em JSON.', task: AiTask::FastText);

    expect($result)->toBe(['served_by' => 'gemini']);
    expect(p49OpenAiCalls())->toBe(2);
});

test('o scan cai para o Gemini quando a OpenAI está sem crédito', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiError(429, 'insufficient_quota'), 429),
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody(p49ScanFixture())),
    ]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    $workout = $user->workouts()->firstOrFail();
    $response->assertRedirect(route('workouts.show', $workout));
    expect($workout->exercises()->count())->toBe(4);

    // Só a leitura da foto (com imagem) conta: a classificação de equipamento
    // que roda depois da importação é outra operação.
    $scanCalls = Http::recorded(fn (Request $request) => p49IsOpenAi($request)
        && ($request['input'][0]['content'][1]['type'] ?? null) === 'input_image');
    expect($scanCalls)->toHaveCount(1);
});

test('a Groq continua bloqueada para o scan, nem como principal nem como reserva', function () {
    $image = UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg');

    // Groq como principal: o scan vai direto para a reserva (Gemini).
    config(['services.ai.default_driver' => 'groq', 'services.ai.fallback_driver' => 'gemini']);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody('{"ok": true}')),
    ]);

    expect(app(AiManager::class)->analyzeImage($image, 'leia', task: AiTask::Vision))->toBe(['ok' => true]);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.groq.com'));
});

test('com a Groq como reserva, uma falha da OpenAI no scan degrada sem chamar a Groq', function () {
    config(['services.ai.default_driver' => 'openai', 'services.ai.fallback_driver' => 'groq']);
    Http::fake([
        'api.openai.com/*' => Http::response('Service Unavailable', 503),
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"ok": true}']]]]),
    ]);

    $image = UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg');

    expect(fn () => app(AiManager::class)->analyzeImage($image, 'leia', task: AiTask::Vision))
        ->toThrow(AiUnavailableException::class);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'api.groq.com'));
});

test('os conectores Claude e Groq continuam funcionando quando configurados', function () {
    config(['services.ai.default_driver' => 'claude', 'services.ai.fallback_driver' => 'groq']);
    Http::fake([
        'api.anthropic.com/*' => Http::response('erro', 500),
        'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"served_by": "groq"}']]]]),
    ]);

    expect(app(AiManager::class)->generateStructured('Responda em JSON.', task: AiTask::FastText))
        ->toBe(['served_by' => 'groq']);
});

// ─── Mensagem de erro do scan ────────────────────────────────────────────────

test('quando as empresas de IA falham, o scan diz que o serviço está indisponível e não culpa a foto', function () {
    $user = User::factory()->create();
    $existing = $user->workouts()->create(['name' => 'Ficha Antiga']);

    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiError(429, 'insufficient_quota'), 429),
        // Limite da camada gratuita do Gemini.
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED']], 429),
    ]);

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertRedirect(route('workouts.index'));
    $response->assertSessionHas('error', 'O serviço de IA está indisponível agora. Tente de novo em alguns minutos.');
    expect($user->workouts()->pluck('name')->all())->toBe(['Ficha Antiga']);
    expect($existing->fresh()->name)->toBe('Ficha Antiga');
});

test('quando a IA responde mas não dá para extrair uma ficha, a mensagem é sobre a foto', function (string $openAiText, string $geminiText) {
    $user = User::factory()->create();
    $exercisesBefore = Exercise::query()->count();

    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiBody($openAiText)),
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody($geminiText)),
    ]);

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertRedirect(route('workouts.index'));
    $response->assertSessionHas('error', 'Não foi possível ler esta imagem. Tente tirar uma foto mais nítida ou aproximada.');
    expect($user->workouts()->count())->toBe(0);
    expect(Exercise::query()->count())->toBe($exercisesBefore);
})->with([
    'as duas devolvem algo que não é JSON' => ['não consigo ler', 'também não'],
    'JSON sem nenhum exercício' => ['{"workout_name": "Foto de um gato", "exercises": []}', '{}'],
]);

test('uma resposta que quebra no meio da importação não deixa ficha nem exercício pela metade', function () {
    $user = User::factory()->create();
    $exercisesBefore = Exercise::query()->count();

    // O primeiro exercício é válido; o segundo não é um objeto e quebra a
    // importação depois que o primeiro já foi criado dentro da transação.
    Http::fake(['api.openai.com/*' => Http::response(p49OpenAiBody(json_encode([
        'workout_name' => 'Treino X',
        'exercises' => [
            ['name' => 'Exercício Totalmente Novo', 'target_sets' => 3, 'target_reps' => '10', 'muscle_group' => 'chest'],
            'isso não é um exercício',
        ],
    ])))]);

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertSessionHas('error', 'Não foi possível ler esta imagem. Tente tirar uma foto mais nítida ou aproximada.');
    expect($user->workouts()->count())->toBe(0);
    expect(Exercise::query()->count())->toBe($exercisesBefore);
});

// ─── Segurança ───────────────────────────────────────────────────────────────

test('nenhum conector desliga a verificação do certificado HTTPS', function () {
    $offenders = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file) => str_contains(file_get_contents($file->getPathname()), 'withoutVerifying'))
        ->map(fn (SplFileInfo $file) => $file->getFilename())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('a chave do Gemini vai só no cabeçalho, nunca na URL', function () {
    config(['services.ai.default_driver' => 'gemini']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody('{"ok": true}'))]);

    app(AiManager::class)->generateStructured('Responda em JSON.', task: AiTask::FastText);

    Http::assertSent(fn (Request $request) => p49IsGemini($request)
        && ! str_contains($request->url(), 'key=')
        && ! str_contains($request->url(), 'test-gemini-api-key')
        && $request->hasHeader('x-goog-api-key', 'test-gemini-api-key'));
});

test('nenhuma chave aparece em logs ou mensagens de erro, mesmo quando a API devolve a chave no corpo do erro', function () {
    $openAiKey = 'sk-proj-SEGREDO1234567890abcdef';
    $geminiKey = 'AIzaSyFAKEsegredo1234567890abcd';
    config(['services.openai.key' => $openAiKey, 'services.gemini.key' => $geminiKey]);

    $logged = [];
    Log::listen(function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiError(401, 'invalid_api_key', "Incorrect API key provided: {$openAiKey}."), 401),
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => "API key not valid: {$geminiKey}"]], 400),
    ]);

    $messages = [];

    try {
        app(AiManager::class)->generateStructured('Responda em JSON.', task: AiTask::FastText);
    } catch (Throwable $e) {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $messages[] = $current->getMessage();
        }
    }

    $everything = implode("\n", [...$logged, ...$messages]);

    expect($messages)->not->toBeEmpty();
    expect($logged)->not->toBeEmpty();
    expect($everything)
        ->not->toContain($openAiKey)
        ->not->toContain($geminiKey)
        ->not->toContain('sk-proj-SEGREDO')
        ->not->toContain('AIzaSyFAKE');
});

// ─── Gemini 3.5 Flash ────────────────────────────────────────────────────────

test('o Gemini 3.5 Flash não recebe parâmetros que ele não aceita e pede JSON nativo com raciocínio baixo', function () {
    config(['services.ai.default_driver' => 'gemini']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody('{"ok": true}'))]);

    app(AiManager::class)->generateStructured('Responda em JSON.', task: AiTask::FastText);

    Http::assertSent(function (Request $request) {
        $config = $request['generationConfig'] ?? [];
        $flat = json_encode($request->data());

        return p49IsGemini($request)
            && str_contains($request->url(), '/models/gemini-3.5-flash:generateContent')
            && $config['responseMimeType'] === 'application/json'
            && $config['thinkingConfig'] === ['thinkingLevel' => 'low']
            && ! array_key_exists('temperature', $config)
            && ! array_key_exists('topP', $config)
            && ! array_key_exists('topK', $config)
            && ! array_key_exists('candidateCount', $config)
            && ! str_contains($flat, 'temperature')
            && ! str_contains($flat, 'top_p')
            && ! str_contains($flat, 'top_k')
            && ! str_contains($flat, 'thinkingBudget')
            && ! str_contains($flat, 'minimal');
    });
});

test('uma falha por limite da camada gratuita do Gemini só degrada a funcionalidade', function () {
    $user = User::factory()->create();
    $exercise = p49Exercise('Supino Reto com Barra', Equipment::BarbellPlates, MovementPattern::HorizontalPush);
    $workout = p49WorkoutWithHistory($user, $exercise);

    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiError(429, 'insufficient_quota'), 429),
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED']], 429),
    ]);

    expect(app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout))->toBeEmpty();
});

// ─── Registro de uso ─────────────────────────────────────────────────────────

test('cada chamada de IA registra empresa, modelo, funcionalidade, tokens e se foi a principal', function () {
    Log::spy();
    $user = User::factory()->create();
    $exercise = p49Exercise('Supino Reto com Barra', Equipment::BarbellPlates, MovementPattern::HorizontalPush);
    $workout = p49WorkoutWithHistory($user, $exercise);

    Http::fake(['api.openai.com/*' => Http::response(p49OpenAiBody('{"recommendations": []}'))]);

    app(ProgressiveOverloadService::class)->analyzeWorkout($user, $workout);

    Log::shouldHaveReceived('info')->with('Uso de IA', [
        'provider' => 'openai',
        'model' => 'gpt-6-luna',
        'feature' => AiFeature::ProgressiveOverload->value,
        'task' => 'fast_text',
        'role' => 'principal',
        'outcome' => 'sucesso',
        'input_tokens' => 120,
        'output_tokens' => 30,
        'total_tokens' => 150,
    ])->once();
});

test('quando a reserva atende, o registro de uso marca a falha da principal e o sucesso da reserva com os tokens do Gemini', function () {
    Log::spy();

    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiError(429, 'insufficient_quota'), 429),
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody(p49ScanFixture())),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    Log::shouldHaveReceived('info')->with('Uso de IA', [
        'provider' => 'openai',
        'model' => 'gpt-6-luna',
        'feature' => AiFeature::WorkoutScan->value,
        'task' => 'vision',
        'role' => 'principal',
        'outcome' => 'falha',
        'input_tokens' => null,
        'output_tokens' => null,
        'total_tokens' => null,
    ])->once();

    Log::shouldHaveReceived('info')->with('Uso de IA', [
        'provider' => 'gemini',
        'model' => 'gemini-3.5-flash',
        'feature' => AiFeature::WorkoutScan->value,
        'task' => 'vision',
        'role' => 'reserva',
        'outcome' => 'sucesso',
        'input_tokens' => 80,
        'output_tokens' => 20,
        'total_tokens' => 100,
    ])->once();
});

// ─── ai:doctor ───────────────────────────────────────────────────────────────

test('ai:doctor testa a principal e a reserva sem mostrar o valor das chaves', function () {
    ini_set('memory_limit', '512M');
    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiBody('{"status": "OK"}')),
        'generativelanguage.googleapis.com/*' => Http::response(p49GeminiBody('{"status": "OK"}')),
    ]);

    $this->artisan('ai:doctor')
        ->expectsOutputToContain('IA principal: openai')
        ->expectsOutputToContain('Modelo de imagem (scan): gpt-6-luna')
        ->expectsOutputToContain('OPENAI_API_KEY definida')
        ->expectsOutputToContain('IA reserva: gemini')
        ->expectsOutputToContain('Modelo de texto rápido: gemini-3.5-flash')
        ->expectsOutputToContain('GEMINI_API_KEY definida')
        ->doesntExpectOutputToContain('test-openai-api-key')
        ->doesntExpectOutputToContain('test-gemini-api-key')
        ->assertExitCode(0);

    Http::assertSent(fn (Request $request) => p49IsOpenAi($request));
    Http::assertSent(fn (Request $request) => p49IsGemini($request));
});

test('ai:doctor aponta a falha da principal e a chave ausente sem expor valores', function () {
    ini_set('memory_limit', '512M');
    config(['services.gemini.key' => '']);
    putenv('GEMINI_API_KEY=');
    $_ENV['GEMINI_API_KEY'] = '';
    $_SERVER['GEMINI_API_KEY'] = '';

    Http::fake([
        'api.openai.com/*' => Http::response(p49OpenAiError(401, 'invalid_api_key', 'Incorrect API key provided: test-openai-api-key.'), 401),
    ]);

    try {
        $this->artisan('ai:doctor')
            ->expectsOutputToContain('Falha ao conectar')
            ->expectsOutputToContain('GEMINI_API_KEY não está definida')
            ->doesntExpectOutputToContain('test-openai-api-key')
            ->assertExitCode(1);
    } finally {
        putenv('GEMINI_API_KEY=test-gemini-api-key');
        $_ENV['GEMINI_API_KEY'] = 'test-gemini-api-key';
        $_SERVER['GEMINI_API_KEY'] = 'test-gemini-api-key';
    }

    Http::assertNotSent(fn (Request $request) => p49IsGemini($request));
});
