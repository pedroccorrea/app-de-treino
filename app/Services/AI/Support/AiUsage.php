<?php

namespace App\Services\AI\Support;

/**
 * Token usage reported by a provider for a single call. Every field is
 * nullable because not every API (or every response) reports all of them.
 */
final class AiUsage
{
    public function __construct(
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?int $totalTokens = null,
    ) {}

    public static function fromCounts(mixed $input, mixed $output, mixed $total = null): self
    {
        $input = is_numeric($input) ? (int) $input : null;
        $output = is_numeric($output) ? (int) $output : null;
        $total = is_numeric($total)
            ? (int) $total
            : ($input !== null || $output !== null ? (int) $input + (int) $output : null);

        return new self($input, $output, $total);
    }
}
