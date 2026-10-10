<?php

namespace App\DataTransferObjects;

use App\Enums\PerformanceTrend;

/**
 * Resumo curto do histórico recente de UM exercício: a melhor série de cada
 * uma das últimas sessões (mais recente primeiro), a referência (o melhor
 * desempenho entre elas, não a última sessão), a tendência e quantas das
 * sessões mais recentes, seguidas, ficaram abaixo da referência.
 *
 * Montado pelo RecentPerformanceService; reaproveitado tanto para os
 * exercícios parecidos da carga inicial (Phase 43) quanto para o histórico
 * do próprio exercício na progressão (Phase 48).
 */
readonly class RecentPerformanceSummary
{
    /**
     * @param  list<array{weight: float, reps: int, date: string, rpe?: float}>  $sessions  melhor série de cada sessão, mais recente primeiro (rpe só quando registrado)
     * @param  array{weight: float, reps: int, date: string, rpe?: float}  $best  a referência: melhor série entre as sessões resumidas
     * @param  int  $sessions_below_reference  sessões seguidas, a partir da mais recente, com a melhor série abaixo da referência
     */
    public function __construct(
        public int $exercise_id,
        public array $sessions,
        public array $best,
        public PerformanceTrend $trend,
        public int $sessions_below_reference = 0,
    ) {}

    /**
     * Melhor série da sessão mais recente (o "último treino").
     *
     * @return array{weight: float, reps: int, date: string, rpe?: float}
     */
    public function latest(): array
    {
        return $this->sessions[0];
    }

    /**
     * @return array{best_recent_set: array{weight: float, reps: int, date: string, rpe?: float}, recent_sessions: list<array{weight: float, reps: int, date: string, rpe?: float}>, trend: string}
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
