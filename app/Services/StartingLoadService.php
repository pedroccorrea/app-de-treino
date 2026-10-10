<?php

namespace App\Services;

use App\DataTransferObjects\OverloadSuggestion;
use App\DataTransferObjects\RecentPerformanceSummary;
use App\DataTransferObjects\RepRange;
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
 * só um resumo curto montado em PHP (RecentPerformanceService): o exercício
 * novo e, para cada parecido, a melhor série de poucas sessões recentes, a
 * referência (melhor desempenho recente) e a tendência. Nunca o histórico
 * inteiro, e todos os exercícios novos da ficha vão numa ÚNICA chamada.
 *
 * A resposta da IA é validada em PHP: repetições inteiras e dentro da faixa
 * da ficha (RepRange), carga reajustada se as repetições mudaram e levada a
 * um valor que existe no equipamento do exercício NOVO (ValidLoadService,
 * Phase 42). Toda carga inicial vem com o motivo em uma linha ("Baseado em
 * Supino Reto com Barra (40 kg × 8)"). Sem nenhum parecido, nenhum número é
 * inventado: o usuário recebe só a orientação de primeira vez.
 *
 * Assim que o exercício novo tiver histórico próprio, ele deixa de passar
 * por aqui e a progressão normal (ProgressiveOverloadService) assume — ver
 * WorkoutSuggestionService.
 */
class StartingLoadService
{
    public const FIRST_TIME_MESSAGE = 'Primeira vez: escolha uma carga em que você faça as repetições com boa técnica';

    /** Máximo de exercícios parecidos enviados à IA por exercício novo. */
    public const MAX_SIMILAR = 5;

    /** Máximo de sessões resumidas por exercício parecido. */
    public const MAX_SESSIONS_PER_SIMILAR = 3;

    /**
     * Quantos exercícios feitos recentemente pelo usuário entram na busca
     * por parecidos (o suficiente para cobrir um programa inteiro).
     */
    private const CANDIDATE_POOL = 40;

    /** Teto de repetições quando a ficha não define uma faixa. */
    private const MAX_REPS_WITHOUT_TARGET = 30;

    public function __construct(
        private readonly AiManager $aiManager,
        private readonly ValidLoadService $validLoadService,
        private readonly ExerciseMovementPatternService $movementPatternService,
        private readonly ExerciseEquipmentService $equipmentService,
        private readonly ExerciseIdentityService $identityService,
        private readonly RecentPerformanceService $recentPerformanceService,
    ) {}

    /**
     * @param  Collection<int, Exercise>  $exercises  exercícios da ficha SEM histórico próprio (com pivot, para ler target_reps)
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

        // Palavras-chave primeiro; no máximo UMA chamada à IA por
        // classificação (equipamento e padrão de movimento) para o que sobrar
        // — e só para exercícios que nunca foram classificados.
        $this->equipmentService->ensureClassified($exercises->concat($pool));
        $this->movementPatternService->ensureClassified($exercises->concat($pool));

        $candidatesByExercise = $exercises->mapWithKeys(fn (Exercise $exercise) => [
            $exercise->id => $this->similarCandidates($exercise, $pool),
        ]);

        $summaries = $this->recentPerformanceService->summarize(
            $user,
            $candidatesByExercise->flatten(1)->pluck('id')->unique()->values()->all(),
            self::MAX_SESSIONS_PER_SIMILAR,
        );

        $similarByExercise = $candidatesByExercise->map(fn (Collection $candidates) => $candidates
            ->filter(fn (Exercise $candidate) => $summaries->has($candidate->id))
            ->map(fn (Exercise $candidate) => ['exercise' => $candidate, 'summary' => $summaries->get($candidate->id)])
            ->values());

        $withSimilar = $exercises->filter(fn (Exercise $exercise) => $similarByExercise[$exercise->id]->isNotEmpty())->values();
        $starting = $withSimilar->isEmpty() ? collect() : $this->askAi($withSimilar, $similarByExercise);

        return $exercises
            ->map(fn (Exercise $exercise) => $starting->get($exercise->id) ?? $this->firstTime($exercise))
            ->values();
    }

    /**
     * Exercícios com carga registrada em sessões concluídas do usuário, do
     * mais recente para o mais antigo. Grafias equivalentes do mesmo
     * exercício contam uma vez só (fica a mais recente; o resumo dela já
     * inclui o histórico das outras).
     *
     * @param  array<int, int>  $excludeIds
     * @return Collection<int, Exercise>
     */
    private function recentlyTrainedExercises(User $user, array $excludeIds): Collection
    {
        $lastDone = SetLog::query()
            ->join('workout_sessions', 'workout_sessions.id', '=', 'set_logs.workout_session_id')
            ->where('workout_sessions.user_id', $user->id)
            ->whereNotNull('workout_sessions.completed_at')
            ->where('set_logs.is_warmup', false)
            ->whereNotNull('set_logs.weight')
            ->where('set_logs.weight', '>', 0)
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
            ->unique(fn (Exercise $exercise) => $this->identityService->normalize($exercise->name))
            ->values();
    }

    /**
     * Até 5 candidatos a parecido: mesmo padrão de movimento e mesmo grupo
     * muscular principal, já em ordem de recência (herdada do pool).
     *
     * @param  Collection<int, Exercise>  $pool
     * @return Collection<int, Exercise>
     */
    private function similarCandidates(Exercise $exercise, Collection $pool): Collection
    {
        $pattern = $exercise->resolvedMovementPattern();

        if ($pattern === MovementPattern::Other) {
            return collect();
        }

        return $pool
            ->filter(fn (Exercise $candidate) => $candidate->resolvedMovementPattern() === $pattern
                && $candidate->primary_muscle_group === $exercise->primary_muscle_group)
            ->take(self::MAX_SIMILAR)
            ->values();
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     * @param  Collection<int, Collection<int, array{exercise: Exercise, summary: RecentPerformanceSummary}>>  $similarByExercise
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
     * @param  Collection<int, Collection<int, array{exercise: Exercise, summary: RecentPerformanceSummary}>>  $similarByExercise
     */
    private function toSuggestion(mixed $answer, Collection $exercisesById, Collection $similarByExercise): ?OverloadSuggestion
    {
        if (! is_array($answer)) {
            return null;
        }

        $exerciseId = $answer['exercise_id'] ?? null;
        $load = $answer['suggested_load'] ?? null;

        if (! is_numeric($exerciseId) || ! is_numeric($load) || (float) $load < 0) {
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

        [$load, $reps] = $this->coherentLoadAndReps($exercise, (float) $load, $answer['suggested_reps'] ?? null);

        // A carga sai em valores que existem no equipamento do exercício NOVO.
        // Sem carga atual, a escada relativa (barra/máquina com anilhas) é
        // ancorada no zero: a carga inicial sai em múltiplos do menor par.
        $equipment = $exercise->resolvedEquipment();
        $validLoad = $this->validLoadService->roundToValid($equipment, $equipment->loadMode(), 0.0, $load);
        $basis = sprintf(
            'Baseado em %s (%s kg × %d)',
            $reference['exercise']->name,
            $this->formatKg($reference['summary']->best['weight']),
            $reference['summary']->best['reps'],
        );

        return new OverloadSuggestion(
            exercise_id: $exercise->id,
            suggested_load: $validLoad,
            suggested_reps: $reps,
            previous_load: null,
            previous_reps: null,
            rationale: $basis,
            confidence: OverloadConfidence::Low,
            kind: SuggestionKind::StartingLoad,
            basis: $basis,
        );
    }

    /**
     * Não confia só na IA: as repetições viram inteiro e ficam dentro da
     * faixa da ficha (com número único, exatamente ele; sem meta, um valor
     * sensato para o padrão de movimento). Se a IA pensou a carga para outro
     * número de repetições, a carga é reajustada pelo 1RM estimado (Epley)
     * para continuar coerente com as repetições finais.
     *
     * @return array{0: float, 1: int}
     */
    private function coherentLoadAndReps(Exercise $exercise, float $load, mixed $aiReps): array
    {
        $range = RepRange::parse($exercise->pivot?->target_reps);
        $proposed = is_numeric($aiReps) && (float) $aiReps >= 1 ? (int) round((float) $aiReps) : null;

        $reps = match (true) {
            $range !== null => $range->clamp($proposed ?? $range->min),
            $proposed !== null => min($proposed, self::MAX_REPS_WITHOUT_TARGET),
            default => $exercise->resolvedMovementPattern()->defaultTargetReps(),
        };

        if ($proposed !== null && $proposed !== $reps) {
            $load = round($load * (1 + $proposed / 30) / (1 + $reps / 30), 2);
        }

        return [$load, $reps];
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     * @param  Collection<int, Collection<int, array{exercise: Exercise, summary: RecentPerformanceSummary}>>  $similarByExercise
     */
    private function buildPrompt(Collection $exercises, Collection $similarByExercise): string
    {
        $summary = $exercises->map(function (Exercise $exercise) use ($similarByExercise) {
            $equipment = $exercise->resolvedEquipment();
            $range = RepRange::parse($exercise->pivot?->target_reps);

            return [
                'exercise_id' => $exercise->id,
                'name' => $exercise->name,
                'equipment' => $equipment->value,
                'load_mode' => $equipment->loadMode()->value,
                'movement_pattern' => $exercise->resolvedMovementPattern()->value,
                'target_reps' => $range === null ? null : ['min' => $range->min, 'max' => $range->max],
                'similar' => $similarByExercise[$exercise->id]->map(fn (array $item) => [
                    'exercise_id' => $item['exercise']->id,
                    'name' => $item['exercise']->name,
                    'equipment' => $item['exercise']->resolvedEquipment()->value,
                    'load_mode' => $item['exercise']->resolvedEquipment()->loadMode()->value,
                    ...$item['summary']->toPromptArray(),
                ])->values()->all(),
            ];
        })->values()->all();

        $summaryJson = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Você é um personal trainer especialista em treinamento de força.

        O usuário vai fazer pela primeira vez os exercícios abaixo. Para cada um, você recebe o exercício novo
        (nome, equipamento, forma de registro da carga, padrão de movimento e faixa de repetições da ficha) e alguns
        exercícios PARECIDOS que ele já fez. Para cada parecido: best_recent_set é a melhor série recente (a
        referência principal), recent_sessions traz a melhor série de cada uma das últimas sessões (carga em kg ×
        repetições e data, mais recente primeiro) e trend é a tendência (subindo, estavel ou caindo):
        {$summaryJson}

        Formas de registro da carga (load_mode): "total" é a carga total do exercício; "por_lado" é a carga de cada
        lado; "por_halter" é o peso de CADA halter/kettlebell. Compare cargas sempre na mesma unidade: por exemplo,
        40 kg "total" numa barra não equivale a 40 kg "por_halter".

        Sugira uma carga inicial realista (suggested_load, em kg, no load_mode do exercício NOVO) e as repetições
        (suggested_reps, número inteiro, dentro de target_reps quando informado). Use best_recent_set como
        referência: um treino isolado mais fraco não deve baixar a sugestão. Ajuste a carga pelas diferenças entre
        o exercício novo e o parecido:
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
