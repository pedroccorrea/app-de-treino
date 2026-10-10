<?php

namespace App\DataTransferObjects;

/**
 * Faixa de repetições alvo de um exercício na ficha. target_reps é texto
 * livre ("10", "8-10", "8 a 10", "3x10"...); este é o ÚNICO lugar do backend
 * que interpreta esse texto (espelhado no frontend/offline por
 * `resources/js/Utils/repTarget.js`, que precisa andar junto).
 *
 * - "10", "10 cada lado"                    → 10..10
 * - "8-10", "8 a 10", "8–10", "8 - 10"      → 8..10 (faixa invertida "10-8" é normalizada)
 * - "12/10/8" (lista de números)            → do menor ao maior (8..12)
 * - "3x10", "4 x 8-10"                      → só o que vem depois do "x" (o número de séries é ignorado)
 * - vazio, nulo, "até a falha", "AMRAP", "máx" → sem meta numérica (null)
 *
 * Números fora de 1..100 não são uma meta plausível e são ignorados, então
 * nenhum formato estranho vira um número inventado.
 */
readonly class RepRange
{
    /** Acima disso o número não é uma meta de repetições plausível. */
    private const MAX_PLAUSIBLE_REPS = 100;

    public function __construct(
        public int $min,
        public int $max,
    ) {}

    public static function parse(?string $targetReps): ?self
    {
        if ($targetReps === null || trim($targetReps) === '') {
            return null;
        }

        $text = mb_strtolower($targetReps);

        // "3x10" / "4 × 8-10": o número antes do "x" é a quantidade de séries.
        if (preg_match('/\d+\s*[x×]\s*(.+)$/u', $text, $match)) {
            $text = $match[1];
        }

        preg_match_all('/\d+/', $text, $numbers);

        $reps = array_values(array_filter(
            array_map('intval', $numbers[0]),
            fn (int $n) => $n >= 1 && $n <= self::MAX_PLAUSIBLE_REPS,
        ));

        if ($reps === []) {
            return null;
        }

        return new self(min($reps), max($reps));
    }

    /**
     * Meta de uma linha de `workout_exercises` (pivot ou model): usa as
     * colunas target_reps_min/max quando a gravação já as preencheu e, em
     * registros antigos (colunas nulas), interpreta o texto na leitura —
     * sem nunca reescrever o registro salvo.
     */
    public static function fromWorkoutExercise(?object $row): ?self
    {
        if ($row === null) {
            return null;
        }

        $min = $row->target_reps_min ?? null;
        $max = $row->target_reps_max ?? null;

        if ($min !== null && $max !== null) {
            return new self(min((int) $min, (int) $max), max((int) $min, (int) $max));
        }

        return self::parse($row->target_reps ?? null);
    }

    /**
     * Colunas numéricas que acompanham o texto em toda gravação nova
     * (scan, criação/edição manual, sincronização offline).
     *
     * @return array{target_reps_min: ?int, target_reps_max: ?int}
     */
    public static function columnsFor(?string $targetReps): array
    {
        $range = self::parse($targetReps);

        return [
            'target_reps_min' => $range?->min,
            'target_reps_max' => $range?->max,
        ];
    }

    public function contains(int $reps): bool
    {
        return $reps >= $this->min && $reps <= $this->max;
    }

    public function clamp(int $reps): int
    {
        return max($this->min, min($this->max, $reps));
    }

    /**
     * @return array{min: int, max: int}
     */
    public function toArray(): array
    {
        return ['min' => $this->min, 'max' => $this->max];
    }
}
