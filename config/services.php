<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // IA principal. Modelo e nível de raciocínio por AiTask: texto rápido
    // sem raciocínio estendido para caber no timeout de fast_text.
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'models' => [
            'vision' => env('OPENAI_VISION_MODEL', 'gpt-6-luna'),
            'fast_text' => env('OPENAI_FAST_TEXT_MODEL', 'gpt-6-luna'),
        ],
        'reasoning_effort' => [
            'vision' => 'low',
            'fast_text' => 'none',
        ],
    ],

    // IA reserva (camada gratuita). Gemini 3.8 Flash aceita thinkingLevel
    // low/medium/high ("minimal" não existe nele).
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'models' => [
            'vision' => env('GEMINI_VISION_MODEL', 'gemini-3.8-flash'),
            'fast_text' => env('GEMINI_FAST_TEXT_MODEL', 'gemini-3.8-flash'),
        ],
        'thinking_level' => [
            'vision' => 'medium',
            'fast_text' => 'low',
        ],
    ],

    'claude' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'models' => [
            'vision' => env('ANTHROPIC_VISION_MODEL', 'claude-opus-5'),
            'fast_text' => env('ANTHROPIC_FAST_TEXT_MODEL', 'claude-haiku-4-5-20251001'),
        ],
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'models' => [
            'vision' => env('GROQ_VISION_MODEL', 'llama-3.2-90b-vision-preview'),
            'fast_text' => env('GROQ_FAST_TEXT_MODEL', 'openai/gpt-oss-20b'),
        ],
    ],

    'ai' => [
        'default_driver' => env('AI_DEFAULT_DRIVER', 'openai'),
        'fallback_driver' => env('AI_FALLBACK_DRIVER', 'gemini'),
        // Timeouts curtos por AiTask (segundos) para nunca deixar o usuário
        // esperando uma tela travada: FastText alimenta telas síncronas
        // (dashboard, sobrecarga progressiva), Vision faz upload de imagem.
        'timeouts' => [
            'fast_text' => env('AI_TIMEOUT_FAST_TEXT', 6),
            'vision' => env('AI_TIMEOUT_VISION', 180),
        ],
    ],

];
