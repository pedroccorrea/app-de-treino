# Invariantes: Integrações com Modelos de Linguagem (LLMs)
1. Sanitização Rigorosa de Markdown / JSON: LLMs frequentemente envolvem a resposta JSON em blocos de markdown. O Service DEVE sanitizar a string com regex (preg_replace('/^```(?:json)?\s+|\s+```$/m', '', trim($rawResponse))) e validar json_last_error() antes de usar o array decodificado.
2. Injeção de Enums no Prompt de Sistema: Sempre que pedir para a IA classificar uma entidade do domínio, passe os valores permitidos no prompt via array_map(fn($case) => $case->value, MuscleGroup::cases()).
