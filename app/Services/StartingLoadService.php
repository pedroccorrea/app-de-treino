<?php

namespace App\Services;

use App\DataTransferObjects\OverloadSuggestion;
use App\Enums\AiTask;
use App\Enums\MovementPattern;
use App\Enums\OverloadConfidence;
use App\Enums\SuggestionKind;
use App\Models\Exercise;
use App\Models\SetLog;
use App\Models\User;
use App\Services\AI\AiManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Carga inicial para exercícios que o usuário ainda nunca fez (troca de
 * programa, exercício novo na ficha). Em vez de chutar um número, busca até
 * 5 exercícios PARECIDOS que ele já fez — mesmo padrão de movimento e mesmo
 * grupo muscular principal, os mais recentes primeiro — e manda para a IA
 * só um resumo curto: o exercício novo e a melhor série recente de cada
 * parecido. Nunca o histórico inteiro.
 *
 * A carga devolvida passa pelo ValidLoadService (Phase 42) e vem sempre com
 * o motivo em uma linha ("Baseado em Supino Reto com Barra (40 kg × 8)").
 * Sem nenhum parecido, nenhum número é inventado: o usuário recebe só a
 * orientação de primeira vez.
 *
 * Assim que o exercício novo tiver histórico próprio, ele deixa de passar
 * por aqui e a progressão normal (ProgressiveOverloadService) assume.
 */
class StartingLoadService
{
    public const FIRST_TIME_MESSAGE = 'Primeira vez: escolha uma carga em que você faça as repetições com boa técnica';

    /** Máximo de exercícios parecidos enviados à IA por exercício novo. */
    public const MAX_SIMILAR = 5;

    /**
     * Quantos exercícios feitos recentemente pelo usuário entram na busca
     * por parecidos (o suficiente para cobrir um programa inteiro).
     */
    private const CANDIDATE_POOL = 40;

    public function __construct(
        private readonly AiManager $aiManager,
        private readonly ValidLoadService $validLoadService,
        private readonly ExerciseMovementPatternService $movementPatternService,
    ) {}

    /**
     * @param  Collection<int, Exercise>  $exercises  exercícios da ficha SEM histórico próprio
     * @return Collection<int, OverloadSuggestion>
     */
    public function suggest(User $user, Collection $exercises): Collection
    {
        $exercises = $exercises->unique('id')->values();

        if ($exercises->isEmpty()) {
            return collect();
        }

        $pool = $this->recentlyTrainedExercises($user, $exercises->pluck('id')->all());

        if ($pool->isEmpty()) {
            return $exercises->map(fn (Exercise $exercise) => $this->firstTime($exercise))->values();
        }

        // Palavras-chave primeiro; no máximo UMA chamada à IA para o que
        // sobrar (exercícios novos e parecidos candidatos juntos).
        $this->movementPatternService->ensureClassified($exercises->concat($pool));

        $similarByExercise = $exercises->mapWithKeys(fn (Exercise $exercise) => [
            $exercise->id => $this->similarExercises($user, $exercise, $pool),
        ]);

        $withSimilar = $exercises->filter(fn (Exercise $exercise) => $similarByExercise[$exercise->id]->isNotEmpty())->values();
        $starting = $withSimilar->isEmpty() ? collect() : $this->askAi($withSimilar, $similarByExercise);

        return $exercises
            ->map(fn (Exercise $exercise) => $starting->get($exercise->id) ?? $this->firstTime($exercise))
            ->values();
    }

    /**
     * Exercícios com carga registrada em sessões concluídas do usuário, do
     * mais recente para o mais antigo.
     *
     * @param  array<int, int>  $excludeIds
     * @return Collection<int, Exercise>
     */
    private function recentlyTrainedExercises(User $user, array $excludeIds): Collection
    {
        $lastDone = $this->loggedSets($user)
            ->whereNotIn('set_logs.exercise_id', $excludeIds)
            ->groupBy('set_logs.exercise_id')
            ->selectRaw('set_logs.exercise_id, MAX(workout_sessions.completed_at) as last_done')
            ->orderByDesc('last_done')
            ->limit(self::CANDIDATE_POOL)
            ->pluck('last_done', 'exercise_id');

        if ($lastDone->isEmpty()) {
            return collect();
        }

        $order = $lastDone->keys()->map(fn ($id) => (int) $id)->flip();

        return Exercise::query()
            ->whereKey($order->keys()->all())
            ->get()
            ->sortBy(fn (Exercise $exercise) => $order[$exercise->id])
            ->values();
    }

    /**
     * Até 5 parecidos: mesmo padrão de movimento e mesmo grupo muscular
     * principal, já em ordem de recência (herdada do pool), cada um com a
     * melhor série da sua sessão mais recente.
     *
     * @param  Collection<int, Exercise>  $pool
     * @return Collection<int, array{exercise: Exercise, weight: float, reps: int, date: ?string}>
     */
    private function similarExercises(User $user, Exercise $exercise, Collection $pool): Collection
    {
        $pattern = $exercise->resolvedMovementPattern();

        if ($pattern === MovementPattern::Other) {
            return collect();
        }

        return $pool
            ->filter(fn (Exercise $candidate) => $candidate->resolvedMovementPattern() === $pattern
                && $candidate->primary_muscle_group === $exercise->primary_muscle_group)
            ->take(self::MAX_SIMILAR)
            ->map(fn (Exercise $candidate) => $this->bestRecentSet($user, $candidate))
            ->filter()
            ->values();
    }

    /**
     * A série mais pesada (desempate: mais repetições) da sessão concluída
     * mais recente em que o exercício foi feito com carga.
     *
     * @return array{exercise: Exercise, weight: float, reps: int, date: ?string}|null
     */
    private function bestRecentSet(User $user, Exercise $exercise): ?array
    {
        $latest = $this->loggedSets($user)
            ->where('set_logs.exercise_id', $exercise->id)
            ->orderByDesc('workout_sessions.completed_at')
            ->select(['set_logs.workout_session_id', 'workout_sessions.completed_at'])
            ->first();

        if ($latest === null) {
            return null;
        }

        $best = $this->loggedSets($user)
            ->where('set_logs.exercise_id', $exercise->id)
            ->where('set_logs.workout_session_id', $latest->workout_session_id)
            ->orderByDesc('set_logs.weight')
            ->orderByDesc('set_logs.reps')
            ->select('set_logs.*')
            ->first();

        return $best === null ? null : [
            'exercise' => $exercise,
            'weight' => (float) $best->weight,
            'reps' => (int) $best->reps,
            'date' => $latest->completed_at ? substr((string) $latest->completed_at, 0, 10) : null,
        ];
    }

    /**
     * Séries de trabalho com carga, em sessões concluídas do usuário.
     *
     * @return \Illuminate\Database\Eloquent\Builder<SetLog>
     */
    private function loggedSets(User $user)
    {
        return SetLog::query()
            ->join('workout_sessions', 'workout_sessions.id', '=', 'set_logs.workout_session_id')
            ->where('workout_sessions.user_id', $user->id)
            ->whereNotNull('workout_sessions.completed_at')
            ->where('set_logs.is_warmup', false)
            ->whereNotNull('set_logs.weight')
            ->where('set_logs.weight', '>', 0);
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     * @param  Collection<int, Collection<int, array{exercise: Exercise, weight: float, reps: int, date: ?string}>>  $similarByExercise
     * @return Collection<int, OverloadSuggestion> indexada por exercise_id
     */
    private function askAi(Collection $exercises, Collection $similarByExercise): Collection
    {
        try {
            $response = $this->aiManager->generateStructured($this->buildPrompt($exercises, $similarByExercise), task: AiTask::FastText);
        } catch (\Throwable $e) {
            Log::warning('Falha ao consultar carga inicial de exercícios novos via IA.', [
                'exercise_ids' => $exercises->pluck('id')->all(),
                'message' => $e->getMessage(),
            ]);

            return collect();
        }

        $answers = $response['suggestions'] ?? null;

        if (! is_array($answers)) {
            return collect();
        }

        $exercisesById = $exercises->keyBy('id');
        $result = collect();

        foreach ($answers as $answer) {
            $suggestion = $this->toSuggestion($answer, $exercisesById, $similarByExercise);

            if ($suggestion !== null && ! $result->has($suggestion->exercise_id)) {
                $result->put($suggestion->exercise_id, $suggestion);
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, Exercise>  $exercisesById
     * @param  Collection<int, Collection<int, array{exercise: Exercise, weight: float, reps: int, date: ?string}>>  $similarByExercise
     */
    private function toSuggestion(mixed $answer, Collection $exercisesById, Collection $similarByExercise): ?OverloadSuggestion
    {
        if (! is_array($answer)) {
            return null;
        }

        $exerciseId = $answer['exercise_id'] ?? null;
        $load = $answer['suggested_load'] ?? null;
        $reps = $answer['suggested_reps'] ?? null;

        if (! is_numeric($exerciseId) || ! is_numeric($load) || ! is_numeric($reps) || (float) $load < 0 || (int) $reps < 1) {
            return null;
        }

        $exercise = $exercisesById->get((int) $exerciseId);

        if ($exercise === null) {
            Log::warning('Carga inicial descartada: exercise_id não é um exercício novo desta ficha.', [
                'exercise_id' => (int) $exerciseId,
            ]);

            return null;
        }

        $similar = $similarByExercise[$exercise->id];
        $basedOnId = is_numeric($answer['based_on_exercise_id'] ?? null) ? (int) $answer['based_on_exercise_id'] : null;
        $reference = $similar->first(fn (array $item) => $item['exercise']->id === $basedOnId) ?? $similar->first();

        $equipment = $exercise->resolvedEquipment();
        // Sem carga atual, a escada relativa (barra/máquina com anilhas) é
        // ancorada no zero: a carga inicial sai em múltiplos do menor par.
        $validLoad = $this->validLoadService->roundToValid($equipment, $equipment->loadMode(), 0.0, (float) $load);
        $basis = sprintf(
            'Baseado em %s (%s kg × %d)',
            $reference['exercise']->name,
            $this->formatKg($reference['weight']),
            $reference['reps'],
        );

        return new OverloadSuggestion(
            exercise_id: $exercise->id,
            suggested_load: $validLoad,
            suggested_reps: (int) $reps,
            previous_load: null,
            previous_reps: null,
            rationale: $basis,
            confidence: OverloadConfidence::Low,
            kind: SuggestionKind::StartingLoad,
            basis: $basis,
        );
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     * @param  Collection<int, Collection<int, array{exercise: Exercise, weight: float, reps: int, date: ?string}>>  $similarByExercise
     */
    private function buildPrompt(Collection $exercises, Collection $similarByExercise): string
    {
        $summary = $exercises->map(function (Exercise $exercise) use ($similarByExercise) {
            $equipment = $exercise->resolvedEquipment();

            return [
                'exercise_id' => $exercise->id,
                'name' => $exercise->name,
                'equipment' => $equipment->value,
                'load_mode' => $equipment->loadMode()->value,
                'movement_pattern' => $exercise->resolvedMovementPattern()->value,
                'target_reps' => $exercise->pivot?->target_reps,
                'similar' => $similarByExercise[$exercise->id]->map(fn (array $item) => [
                    'exercise_id' => $item['exercise']->id,
                    'name' => $item['exercise']->name,
                    'equipment' => $item['exercise']->resolvedEquipment()->value,
                    'load_mode' => $item['exercise']->resolvedEquipment()->loadMode()->value,
                    'best_recent_set' => [
                        'weight' => $item['weight'],
                        'reps' => $item['reps'],
                        'date' => $item['date'],
                    ],
                ])->values()->all(),
            ];
        })->values()->all();

        $summaryJson = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Você é um personal trainer especialista em treinamento de força.

        O usuário vai fazer pela primeira vez os exercícios abaixo. Para cada um, você recebe o exercício novo
        (nome, equipamento, forma de registro da carga e padrão de movimento) e alguns exercícios PARECIDOS que ele
        já fez, com a melhor série recente de cada (carga em kg × repetições e data):
        {$summaryJson}

        Sugira uma carga inicial realista (suggested_load, em kg, na mesma forma de registro do exercício novo:
        load_mode "por_halter" é o peso de cada halter/kettlebell, "total" é a carga total) e as repetições
        (suggested_reps, dentro de target_reps quando informado). Ajuste a carga pelas diferenças entre o exercício
        novo e o parecido:
        - Halteres usam carga por halter bem menor que a barra no mesmo movimento.
        - Máquinas guiadas costumam permitir mais carga que peso livre.
        - Unilateral usa menos carga que bilateral.
        - Inclinado usa menos carga que reto.
        - Mais amplitude, mais instabilidade ou técnica mais difícil pedem menos carga.
        - Isolado usa menos carga que composto.
        - Na primeira sessão, seja conservador: é melhor começar um pouco leve e subir na próxima.

        Informe em based_on_exercise_id qual exercício parecido serviu de principal referência (um dos exercise_id
        da lista "similar" daquele exercício).

        Use exclusivamente os exercise_id listados acima, nunca invente um exercise_id novo.

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "suggestions": [
            { "exercise_id": 1, "suggested_load": 16, "suggested_reps": 10, "based_on_exercise_id": 2 }
          ]
        }
        PROMPT;
    }

    private function firstTime(Exercise $exercise): OverloadSuggestion
    {
        return new OverloadSuggestion(
            exercise_id: $exercise->id,
            suggested_load: null,
            suggested_reps: null,
            previous_load: null,
            previous_reps: null,
            rationale: self::FIRST_TIME_MESSAGE,
            confidence: OverloadConfidence::Low,
            kind: SuggestionKind::FirstTime,
        );
    }

    private function formatKg(float $kg): string
    {
        return rtrim(rtrim(number_format($kg, 2, ',', ''), '0'), ',');
    }
}
