<?php

namespace App\Enums;

/**
 * O próximo passo da progressão de um exercício, decidido em PHP pelo
 * ProgressiveOverloadService a partir do resumo do histórico (nunca pela
 * IA) e entregue no prompt como orientação.
 */
enum ProgressionPlan: string
{
    /** Referência já no topo da faixa da ficha: próxima carga válida. */
    case IncreaseLoad = 'subir_carga';

    /** Referência abaixo do topo da faixa: mesma carga, mais repetições. */
    case IncreaseReps = 'subir_repeticoes';

    /** Ficha sem faixa (número único ou sem meta): progressão livre. */
    case Progress = 'progredir';

    /** Queda repetida: segurar a carga recente e recuperar as repetições. */
    case HoldAndRecover = 'manter_carga_recuperar_repeticoes';

    public function label(): string
    {
        return match ($this) {
            self::IncreaseLoad => 'Subir a carga',
            self::IncreaseReps => 'Subir as repetições',
            self::Progress => 'Progredir',
            self::HoldAndRecover => 'Manter a carga e recuperar as repetições',
        };
    }
}
