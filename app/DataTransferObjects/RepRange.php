<?php

namespace App\DataTransferObjects;

/**
 * Faixa de repetições alvo de um exercício na ficha. Hoje target_reps é
 * texto livre ("10", "8-10", "8 a 10", "3x10"...); este é o ÚNICO lugar que
 * interpreta esse texto, para poder ser reaproveitado no app inteiro.
 *
 * - "10"           → 10..10
 * - "8-10", "8 a 10", "8–10", "12/10/8" → menor..maior
 * - "3x10", "4 x 8-10" → só o que vem depois do "x" (o número de séries é ignorado)
 * - vazio, "falha", "máx" → sem faixa (null)
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

    public function contains(int $reps): bool
    {
        return $reps >= $this->min && $reps <= $this->max;
    }

    public function clamp(int $reps): int
    {
        return max($this->min, min($this->max, $reps));
    }
}
