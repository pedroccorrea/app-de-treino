<?php

namespace App\Enums;

/**
 * Semantic AI workload categories used to route each request to the model
 * best suited for it (a heavy vision model for image transcription, a
 * cheap/fast text model for structured JSON generation), independently of
 * which provider (Gemini, Claude, ...) ends up serving it.
 */
enum AiTask: string
{
    case Vision = 'vision';
    case FastText = 'fast_text';

    public function label(): string
    {
        return match ($this) {
            self::Vision => 'Visão (leitura de imagem)',
            self::FastText => 'Texto rápido',
        };
    }
}
