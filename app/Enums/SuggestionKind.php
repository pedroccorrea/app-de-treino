<?php

namespace App\Enums;

/**
 * De onde veio uma sugestão de carga da sessão ativa:
 * - progressão normal (o exercício já tem histórico próprio);
 * - carga inicial (exercício novo, estimada a partir de exercícios parecidos);
 * - primeira vez (exercício novo sem nenhum parecido: nenhum número é sugerido).
 */
enum SuggestionKind: string
{
    case Progression = 'progressao';
    case StartingLoad = 'carga_inicial';
    case FirstTime = 'primeira_vez';

    public function label(): string
    {
        return match ($this) {
            self::Progression => 'Progressão',
            self::StartingLoad => 'Carga inicial',
            self::FirstTime => 'Primeira vez',
        };
    }
}
