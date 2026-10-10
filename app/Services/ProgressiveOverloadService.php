<?php

namespace App\Services;

use App\DataTransferObjects\OverloadSuggestion;
use App\DataTransferObjects\RecentPerformanceSummary;
use App\DataTransferObjects\RepRange;
use App\Enums\AiFeature;
use App\Enums\AiTask;
use App\Enums\Equipment;
use App\Enums\OverloadConfidence;
use App\Enums\ProgressionPlan;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\AI\AiManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Progressão normal dos exercícios da ficha que o usuário JÁ fez.
 *
 * A base não é o último treino, e sim o histórico recente do EXERCÍCIO (em
 * qualquer ficha ou programa, inclusive sob grafia equivalente), resumido em
 * PHP pelo RecentPerformanceService (Phase 43):
 * - referência = melhor série entre as últimas OWN_HISTORY_SESSIONS sessões
 *   (maior 1RM estimado). É ela que ancora as cargas válidas do prompt;
 * - tendência (subindo, estável, caindo) e quantas sessões seguidas, a
 *   partir da mais recente, ficaram abaixo da referência.
 *
 * Regras (com rede de segurança em PHP, como o arredondamento da Phase 42):
 * - dia fraco isolado (menos de REPEATED_DROP_SESSIONS sessões seguidas
 *   abaixo da referência) ou histórico subindo/estável: progressão a partir
 *   da referência. Uma sugestão abaixo dela volta à referência (carga
 *   válida) e o ajuste fica em log;
 * - queda repetida (REPEATED_DROP_SESSIONS ou mais sessões seguidas abaixo
 *   da referência): manter a carga recente e recuperar as repetições —
 *   nenhuma carga acima da carga recente, com o motivo em uma linha.
 *
 * Exercício com histórico próprio curto (menos de SHORT_HISTORY_SESSIONS
 * sessões) ganha, no mesmo prompt, o resumo de até
 * MAX_SIMILAR_FOR_SHORT_HISTORY exercícios parecidos (busca da Phase 43,
 * sem IA). Exercício sem histórico nenhum não passa por aqui: continua com
 * a carga inicial / "Primeira vez" (StartingLoadService).
 *
 * Custo: uma única chamada à IA para a ficha inteira, com um resumo curto e
 * limitado por exercício (nunca as séries cruas do histórico). Nada é
 * gravado: o resumo é calculado na hora, então séries editadas e treinos
 * excluídos no histórico (Phases 45/46) já se refletem na próxima sugestão.
 */
class ProgressiveOverloadService
{
    /** Sessões do próprio exercício resumidas no prompt (a "janela" da referência). */
    public const OWN_HISTORY_SESSIONS = 5;

    /** Sessões seguidas abaixo da referência a partir das quais a queda é "repetida". */
    public const REPEATED_DROP_SESSIONS = 3;

    /** Abaixo disso o histórico próprio é curto e ganha exercícios parecidos de apoio. */
    public const SHORT_HISTORY_SESSIONS = 3;

    /** Máximo de exercícios parecidos de apoio por exercício de histórico curto. */
    public const MAX_SIMILAR_FOR_SHORT_HISTORY = 2;

    public function __construct(
        private readonly AiManager $aiManager,
        private readonly ValidLoadService $validLoadService,
        private readonly RecentPerformanceService $recentPerformanceService,
        private readonly StartingLoadService $startingLoadService,
    ) {}

    /**
     * Read-only: nothing is written to the database during the analysis.
     *
     * The AI response is only ever consumed as structured JSON matched by
     * exercise_id (never by exercise name). Any suggestion the AI invents for
     * an exercise_id outside this workout is dropped and logged; any response
     * that isn't valid/complete JSON degrades to an empty collection instead
     * of throwing, so the screen simply shows no suggestions.
     *
     * Exercises with a known equipment get the list of loads that really
     * exist for it in the prompt, and any load the AI still invents outside
     * that list is rounded to the nearest valid one (see safeLoad()).
     * Unclassified exercises behave as "outro": no restriction at all.
     *
     * @return Collection<int, OverloadSuggestion>
     */
    public function analyzeWorkout(User $user, Workout $workout): Collection
    {
        $exercises = $workout->exercises->unique('id')->values();

        // Não exclusivo: duas grafias do mesmo exercício na ficha dividem o
        // mesmo histórico (é o mesmo critério que decide "já fez" x "novo").
        $summaries = $this->recentPerformanceService->summarize(
            $user,
            $exercises->pluck('id')->all(),
            self::OWN_HISTORY_SESSIONS,
            includeUnloaded: true,
            exclusive: false,
        );

        $withHistory = $exercises->filter(fn (Exercise $exercise) => $summaries->has($exercise->id))->values();

        if ($withHistory->isEmpty()) {
            return collect();
        }

        $similar = $this->similarForShortHistory($user, $withHistory, $summaries);

        try {
            $response = $this->aiManager->generateStructured($this->buildPrompt($withHistory, $summaries, $similar), task: AiTask::FastText, feature: AiFeature::ProgressiveOverload);
        } catch (\Throwable $e) {
            Log::warning('Falha ao consultar sugestões de sobrecarga progressiva via IA.', [
                'workout_id' => $workout->id,
                'message' => $e->getMessage(),
            ]);

            return collect();
        }

        return $this->mapRecommendations($response, $exercises->pluck('id')->all(), $summaries, $withHistory->keyBy('id'));
    }

    /**
     * Exercícios parecidos (Phase 43) para quem tem poucas sessões próprias.
     * Sem IA e sem gravar nada: só o que já está classificado ou resolve por
     * palavra-chave.
     *
     * @param  Collection<int, Exercise>  $exercises
     * @param  Collection<int, RecentPerformanceSummary>  $summaries
     * @return Collection<int, Collection<int, array{exercise: Exercise, summary: RecentPerformanceSummary}>>
     */
    private function similarForShortHistory(User $user, Collection $exercises, Collection $summaries): Collection
    {
        $short = $exercises
            ->filter(fn (Exercise $exercise) => count($summaries->get($exercise->id)->sessions) < self::SHORT_HISTORY_SESSIONS)
            ->values();

        if ($short->isEmpty()) {
            return collect();
        }

        return $this->startingLoadService->similarWithHistory($user, $short, self::MAX_SIMILAR_FOR_SHORT_HISTORY, allowAi: false);
    }

    private function isRepeatedDrop(RecentPerformanceSummary $summary): bool
    {
        return $summary->sessions_below_reference >= self::REPEATED_DROP_SESSIONS;
    }

    /**
     * Carga que ancora as cargas válidas: a referência; em queda repetida, a
     * carga recente (é ela que o usuário deve manter).
     */
    private function baseLoad(RecentPerformanceSummary $summary): float
    {
        return $this->isRepeatedDrop($summary) ? $summary->latest()['weight'] : $summary->best['weight'];
    }

    /**
     * Progressão dupla (Phase 42) com a faixa da ficha (Phase 47): abaixo do
     * topo da faixa, mais repetições; no topo, a próxima carga válida.
     */
    private function plan(Exercise $exercise, RecentPerformanceSummary $summary): ProgressionPlan
    {
        if ($this->isRepeatedDrop($summary)) {
            return ProgressionPlan::HoldAndRecover;
        }

        $range = RepRange::fromWorkoutExercise($exercise->pivot);

        if ($range === null || $range->min === $range->max) {
            return ProgressionPlan::Progress;
        }

        return $summary->best['reps'] >= $range->max ? ProgressionPlan::IncreaseLoad : ProgressionPlan::IncreaseReps;
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     * @param  Collection<int, RecentPerformanceSummary>  $summaries
     * @param  Collection<int, Collection<int, array{exercise: Exercise, summary: RecentPerformanceSummary}>>  $similar
     */
    private function buildPrompt(Collection $exercises, Collection $summaries, Collection $similar): string
    {
        $entries = $exercises
            ->map(fn (Exercise $exercise) => $this->exercisePromptEntry($exercise, $summaries->get($exercise->id), $similar->get($exercise->id, collect())))
            ->values()->all();

        $exercisesJson = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $sessions = self::OWN_HISTORY_SESSIONS;
        $drop = self::REPEATED_DROP_SESSIONS;

        return <<<PROMPT
        Você é um personal trainer especialista em treinamento de força e sobrecarga progressiva.

        Estes são os exercícios desta ficha de treino que o usuário já fez, cada um identificado por um exercise_id
        numérico, com um RESUMO do histórico recente do exercício (em qualquer ficha), calculado pelo app:
        {$exercisesJson}

        Como ler o resumo:
        - recent_sessions: a melhor série (carga em kg × repetições, data e, quando registrado, RPE) de cada uma das
          últimas sessões (no máximo {$sessions}), mais recente primeiro.
        - reference_set: a REFERÊNCIA, o melhor desempenho recente. É a partir dela que você progride, nunca a partir
          do último treino.
        - trend: tendência do desempenho (subindo, estavel ou caindo).
        - sessions_below_reference: quantas sessões seguidas, a partir da mais recente, ficaram abaixo da referência.
        - plan: o próximo passo já decidido pelo app (subir_carga, subir_repeticoes, progredir ou
          manter_carga_recuperar_repeticoes).
        - similar (só quando o histórico próprio é curto): exercícios parecidos que o usuário já fez, apenas como apoio.

        Para cada exercise_id acima, sugira a próxima carga e repetições, com uma justificativa (rationale) curta, de
        uma linha, baseada no desempenho recente. IMPORTANTE: A justificativa (rationale) DEVE ser escrita estritamente
        em Português do Brasil (pt-BR). Informe também seu grau de confiança nessa sugestão.

        O objetivo é sempre manter ou melhorar a performance:
        - Um treino isolado mais fraco NÃO baixa a sugestão: volte ao nível da referência ou progrida a partir dela.
          Nunca sugira carga abaixo de reference_set.weight nesse caso.
        - Queda repetida (plan manter_carga_recuperar_repeticoes, {$drop} ou mais sessões seguidas abaixo da referência):
          não aumente a carga; mantenha a carga da sessão mais recente (base_load) e foque em recuperar as repetições.
        - Se o RPE estiver muito alto, prefira manter a carga e subir repetições a subir a carga.

        CARGAS QUE EXISTEM DE VERDADE:
        - Cada exercício informa o equipamento (equipment), como a carga é registrada (load_mode: total, por lado
          ou por halter, isto é, o peso de cada halter/kettlebell), a carga base (base_load: a da referência, ou a
          recente em queda repetida) e, quando houver, a lista valid_loads com as cargas válidas próximas dela.
        - Quando valid_loads existir, suggested_load DEVE ser exatamente um dos valores dessa lista, inclusive em
          reduções de carga. Nunca sugira um valor fora dela (ex: nunca 10,5 kg em halteres).
        - Progressão dupla: se o próximo peso válido acima da carga base for um salto grande para o exercício
          (comum em halteres leves e em máquinas), mantenha a carga e sugira mais repetições dentro da faixa alvo
          (target_reps.min a target_reps.max); só suba para o próximo peso válido quando o usuário tiver atingido o
          topo da faixa (target_reps.max).
        - suggested_reps é sempre um número inteiro e, quando target_reps existir, fica entre target_reps.min e
          target_reps.max. target_reps null significa ficha sem meta numérica.
        - Peso corporal (peso_corporal): não sugira carga; mantenha suggested_load igual à carga base e progrida
          em repetições, ou adicione anilha (um valor de valid_loads) só quando fizer sentido.
        - Sem valid_loads (equipamento "outro"), sugira a carga como de costume.

        Use exclusivamente os exercise_id listados acima, nunca invente um exercise_id novo.

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "recommendations": [
            {
              "exercise_id": 1,
              "suggested_load": 42.5,
              "suggested_reps": 8,
              "rationale": "string",
              "confidence": "high"
            }
          ]
        }
        PROMPT;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<int, int>  $workoutExerciseIds
     * @param  Collection<int, RecentPerformanceSummary>  $summaries
     * @param  Collection<int, Exercise>  $exercisesById  só os exercícios com histórico (os que foram ao prompt)
     * @return Collection<int, OverloadSuggestion>
     */
    private function mapRecommendations(array $response, array $workoutExerciseIds, Collection $summaries, Collection $exercisesById): Collection
    {
        $recommendations = $response['recommendations'] ?? null;

        if (! is_array($recommendations)) {
            return collect();
        }

        return collect($recommendations)
            ->map(fn (mixed $recommendation) => $this->toSuggestion($recommendation, $workoutExerciseIds, $summaries, $exercisesById))
            ->filter()
            ->values();
    }

    /**
     * Builds a single OverloadSuggestion from one AI recommendation entry, or
     * returns null when it should be dropped: missing/invalid required
     * fields are dropped silently, while an exercise_id that doesn't belong
     * to this workout is dropped and logged (the AI must never be trusted to
     * only suggest exercises that were actually sent to it). An exercise of
     * the workout that wasn't sent (no history of its own) is dropped too:
     * it belongs to the starting-load flow.
     *
     * @param  array<int, int>  $workoutExerciseIds
     * @param  Collection<int, RecentPerformanceSummary>  $summaries
     * @param  Collection<int, Exercise>  $exercisesById
     */
    private function toSuggestion(mixed $recommendation, array $workoutExerciseIds, Collection $summaries, Collection $exercisesById): ?OverloadSuggestion
    {
        if (! is_array($recommendation)) {
            return null;
        }

        $exerciseId = $recommendation['exercise_id'] ?? null;
        $suggestedLoad = $recommendation['suggested_load'] ?? null;
        $suggestedReps = $recommendation['suggested_reps'] ?? null;
        $rationale = $recommendation['rationale'] ?? null;

        if (! is_numeric($exerciseId) || ! is_numeric($suggestedLoad) || ! is_numeric($suggestedReps)) {
            return null;
        }

        if (! is_string($rationale) || trim($rationale) === '') {
            return null;
        }

        $exerciseId = (int) $exerciseId;

        if (! in_array($exerciseId, $workoutExerciseIds, true)) {
            Log::warning('Sugestão de sobrecarga progressiva descartada: exercise_id não pertence ao treino.', [
                'exercise_id' => $exerciseId,
                'workout_exercise_ids' => $workoutExerciseIds,
            ]);

            return null;
        }

        $exercise = $exercisesById->get($exerciseId);
        $summary = $summaries->get($exerciseId);

        if ($exercise === null || $summary === null) {
            return null;
        }

        $equipment = $exercise->resolvedEquipment();
        $reps = $this->safeReps($exercise, (float) $suggestedReps);

        if ($this->isRepeatedDrop($summary)) {
            $load = $this->cappedLoad($exerciseId, $equipment, $summary, (float) $suggestedLoad);
            $rationale = sprintf(
                'Queda em %d treinos seguidos: mantenha %s e recupere as repetições antes de subir a carga.',
                $summary->sessions_below_reference,
                $load > 0 ? $this->formatKg($load).' kg' : 'a carga',
            );
        } else {
            [$load, $floored] = $this->flooredLoad($exerciseId, $equipment, $summary, (float) $suggestedLoad);

            if ($floored) {
                // A IA pensou numa carga menor; as repetições dela não valem
                // para a carga da referência: volta ao nível da referência.
                $reps = $this->safeReps($exercise, (float) $summary->best['reps']);
                $rationale = sprintf(
                    'Volte ao seu melhor recente (%s kg × %d): um treino mais fraco não muda a referência.',
                    $this->formatKg($summary->best['weight']),
                    $summary->best['reps'],
                );
            }
        }

        $latest = $summary->latest();

        return new OverloadSuggestion(
            exercise_id: $exerciseId,
            suggested_load: $load,
            suggested_reps: $reps,
            previous_load: $latest['weight'] > 0 ? $latest['weight'] : null,
            previous_reps: $latest['reps'],
            rationale: $rationale,
            confidence: OverloadConfidence::fromAi($recommendation['confidence'] ?? null),
        );
    }

    /**
     * Rede de segurança fora da queda repetida: a carga nunca fica abaixo da
     * referência. Abaixo dela, volta à referência (menor carga válida que não
     * fica abaixo dela) e o ajuste fica em log; acima, segue o arredondamento
     * normal para uma carga válida (safeLoad).
     *
     * @return array{0: float, 1: bool} carga final e se o piso foi aplicado
     */
    private function flooredLoad(int $exerciseId, Equipment $equipment, RecentPerformanceSummary $summary, float $aiLoad): array
    {
        $mode = $equipment->loadMode();
        $reference = $summary->best['weight'];

        if ($aiLoad >= $reference - 0.001) {
            $load = $this->safeLoad($exerciseId, $equipment, $reference, $aiLoad);

            // O arredondamento também não pode descer abaixo da referência.
            return $load < $reference - 0.001
                ? [$this->validLoadService->ceilToValid($equipment, $mode, $reference, $reference), false]
                : [$load, false];
        }

        $load = $this->validLoadService->ceilToValid($equipment, $mode, $reference, $reference);

        Log::warning('Carga sugerida pela IA abaixo da referência levada de volta à referência.', [
            'exercise_id' => $exerciseId,
            'equipment' => $equipment->value,
            'reference_load' => $reference,
            'ai_load' => $aiLoad,
            'adjusted_load' => $load,
        ]);

        return [$load, true];
    }

    /**
     * Rede de segurança na queda repetida: nenhuma carga acima da carga
     * recente. Acima dela, fica na maior carga válida que não passa da
     * recente e o ajuste fica em log.
     */
    private function cappedLoad(int $exerciseId, Equipment $equipment, RecentPerformanceSummary $summary, float $aiLoad): float
    {
        $mode = $equipment->loadMode();
        $recent = $summary->latest()['weight'];

        if ($aiLoad <= $recent + 0.001) {
            $load = $this->safeLoad($exerciseId, $equipment, $recent, $aiLoad);

            return $load > $recent + 0.001
                ? $this->validLoadService->floorToValid($equipment, $mode, $recent, $recent)
                : $load;
        }

        $load = $this->validLoadService->floorToValid($equipment, $mode, $recent, $recent);

        Log::warning('Carga sugerida pela IA acima da carga recente limitada por queda repetida.', [
            'exercise_id' => $exerciseId,
            'equipment' => $equipment->value,
            'recent_load' => $recent,
            'ai_load' => $aiLoad,
            'adjusted_load' => $load,
        ]);

        return $load;
    }

    /**
     * @param  Collection<int, array{exercise: Exercise, summary: RecentPerformanceSummary}>  $similar
     * @return array<string, mixed>
     */
    private function exercisePromptEntry(Exercise $exercise, RecentPerformanceSummary $summary, Collection $similar): array
    {
        $equipment = $exercise->resolvedEquipment();
        $mode = $equipment->loadMode();
        $baseLoad = $this->baseLoad($summary);
        $promptSummary = $summary->toPromptArray();

        return [
            'exercise_id' => $exercise->id,
            'name' => $exercise->name,
            // Meta já interpretada (min/max), nunca o texto cru da ficha: é com o
            // topo da faixa que a progressão dupla decide quando subir a carga.
            'target_reps' => RepRange::fromWorkoutExercise($exercise->pivot)?->toArray(),
            'equipment' => $equipment->value,
            'load_mode' => $mode->value,
            'reference_set' => $promptSummary['best_recent_set'],
            'recent_sessions' => $promptSummary['recent_sessions'],
            'trend' => $promptSummary['trend'],
            'sessions_below_reference' => $summary->sessions_below_reference,
            'plan' => $this->plan($exercise, $summary)->value,
            'base_load' => $baseLoad > 0 ? $baseLoad : null,
            'valid_loads' => $this->validLoadService->isRestricted($equipment)
                ? $this->validLoadService->candidateLoads($equipment, $mode, $baseLoad)
                : null,
            ...($similar->isNotEmpty() ? ['similar' => $similar->map(fn (array $item) => [
                'name' => $item['exercise']->name,
                'equipment' => $item['exercise']->resolvedEquipment()->value,
                'load_mode' => $item['exercise']->resolvedEquipment()->loadMode()->value,
                'best_recent_set' => $item['summary']->best,
                'trend' => $item['summary']->trend->value,
            ])->values()->all()] : []),
        ];
    }

    /**
     * Não confia só na IA: as repetições sugeridas viram inteiro (mínimo 1)
     * e, quando a ficha tem uma FAIXA (ex.: 8–10), ficam dentro dela. Meta de
     * número único não trava: a progressão em repetições continua livre,
     * como já era antes da faixa ser interpretada.
     */
    private function safeReps(?Exercise $exercise, float $suggestedReps): int
    {
        $reps = max(1, (int) round($suggestedReps));
        $range = RepRange::fromWorkoutExercise($exercise?->pivot);

        return $range !== null && $range->min < $range->max ? $range->clamp($reps) : $reps;
    }

    /**
     * Rede de segurança: mesmo com a lista no prompt, a IA pode devolver uma
     * carga que não existe no equipamento. Ela é levada ao valor válido mais
     * próximo da carga base (empate → o menor) e o ajuste fica em log.
     */
    private function safeLoad(int $exerciseId, Equipment $equipment, float $baseLoad, float $suggestedLoad): float
    {
        $mode = $equipment->loadMode();
        $rounded = $this->validLoadService->roundToValid($equipment, $mode, $baseLoad, $suggestedLoad);

        if (abs($rounded - $suggestedLoad) > 0.001) {
            Log::warning('Carga sugerida pela IA arredondada para um valor válido do equipamento.', [
                'exercise_id' => $exerciseId,
                'equipment' => $equipment->value,
                'load_mode' => $mode->value,
                'current_load' => $baseLoad,
                'ai_load' => $suggestedLoad,
                'rounded_load' => $rounded,
            ]);
        }

        return $rounded;
    }

    private function formatKg(float $kg): string
    {
        return rtrim(rtrim(number_format($kg, 2, ',', ''), '0'), ',');
    }
}
