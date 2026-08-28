<?php

namespace App\Enums;

enum OverloadConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'Alta',
            self::Medium => 'Média',
            self::Low => 'Baixa',
        };
    }

    /**
     * Parses the confidence value returned by the AI, tolerating case
     * differences and falling back to Medium when the value is missing or
     * doesn't match a known case, instead of throwing.
     */
    public static function fromAi(mixed $value): self
    {
        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            foreach (self::cases() as $case) {
                if ($case->value === $normalized) {
                    return $case;
                }
            }
        }

        return self::Medium;
    }
}
