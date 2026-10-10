<?php

namespace App\DataTransferObjects;

use App\Enums\PerformanceTrend;

/**
 * Resumo curto do histórico recente de UM exercício: a melhor série de cada
 * uma das últimas sessões (mais recente primeiro), a referência (o melhor
 * desempenho entre elas, não a última sessão) e a tendência.
 *
 * Montado pelo RecentPerformanceService; pensado para ser reaproveitado
 * tanto para os exercícios parecidos da carga inicial (Phase 43) quanto
 * para o histórico do próprio exercício (Phase 48).
 */
readonly class RecentPerformanceSummary
{
    /**
     * @param  list<array{weight: float, reps: int, date: string}>  $sessions  melhor série de cada sessão, mais recente primeiro
     * @param  array{weight: float, reps: int, date: string}  $best
     */
    public function __construct(
        public int $exercise_id,
        public array $sessions,
        public array $best,
        public PerformanceTrend $trend,
    ) {}

    /**
     * @return array{best_recent_set: array{weight: float, reps: int, date: string}, recent_sessions: list<array{weight: float, reps: int, date: string}>, trend: string}
     */
    public function toPromptArray(): array
    {
        return [
            'best_recent_set' => $this->best,
            'recent_sessions' => $this->sessions,
            'trend' => $this->trend->value,
        ];
    }
}
