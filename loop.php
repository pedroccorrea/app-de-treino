<?php

$maxTries = 3;
$phasesFile = __DIR__ . '/.spec/init/project-phases.md';
$progressFile = __DIR__ . '/.phases/.progress';
$manifestFile = __DIR__ . '/.phases/manifest.txt';
$tempTaskFile = __DIR__ . '/.spec/.active_task.tmp';

// Padrões que indicam erro de INFRAESTRUTURA (limite, auth, rede).
// Nesses casos o script aborta sem consumir ciclos.
$infraErrorPattern = '/(session limit|rate limit|usage limit|quota exceeded|Invalid API key|not authenticated|unauthorized|ECONNREFUSED|ETIMEDOUT|network error)/i';

if (!file_exists(__DIR__ . '/.phases')) {
    mkdir(__DIR__ . '/.phases', 0755, true);
}

if (!file_exists($progressFile)) {
    file_put_contents($progressFile, 0);
}

$content = file_get_contents($phasesFile);
$phases = preg_split('/^# Phase /m', $content, -1, PREG_SPLIT_NO_EMPTY);

// ─── Pré-voo ──────────────────────────────────────────────────────────────
echo "🔍 Verificação de pré-voo...\n";

exec('claude --version 2>&1', $vOut, $vCode);
if ($vCode !== 0) {
    echo "🛑 Binário 'claude' não encontrado no PATH. Abortando.\n";
    exit(1);
}

exec('git status --porcelain', $dirtyPreflight);
if (!empty($dirtyPreflight)) {
    echo "🛑 A árvore do git tem alterações não commitadas:\n";
    echo implode("\n", $dirtyPreflight) . "\n";
    echo "Commite ou descarte antes de rodar o harness.\n";
    exit(1);
}

echo "✅ Pré-voo ok. " . count($phases) . " fases carregadas.\n";
echo "   Fase atual: índice " . (int) trim(file_get_contents($progressFile)) . "\n";

// ─── Loop principal ───────────────────────────────────────────────────────
while (true) {
    $currentPhaseIndex = (int) trim(file_get_contents($progressFile));

    if ($currentPhaseIndex >= count($phases)) {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "🏁 [HARNESS CONCLUÍDO] TODAS AS FASES DO PROJETO FORAM APROVADAS!\n";
        echo str_repeat('=', 70) . "\n";
        if (file_exists($tempTaskFile)) unlink($tempTaskFile);
        exit(0);
    }

    $phaseText = $phases[$currentPhaseIndex];
    $lines = explode("\n", trim($phaseText));
    $phaseTitle = trim($lines[0]);

    echo "\n" . str_repeat('=', 70) . "\n";
    echo "🚀 EXECUTANDO: Phase " . $phaseTitle . "\n";
    echo str_repeat('=', 70) . "\n";

    $tries = 0;
    $feedback = "";
    $phasePassed = false;

    while ($tries < $maxTries) {
        $tries++;
        echo "\n🔄 [Ciclo $tries de $maxTries]\n";

        $promptContent = "# DIRETRIZ DE EXECUÇÃO DO AGENTE IMPLEMENTADOR\n\n"
                       . "Você deve implementar COMPLETAMENTE a fase abaixo seguindo rigorosamente o CLAUDE.md e as regras em .ai/rules/.\n"
                       . "Crie todos os arquivos necessários e aplique as alterações no disco. Não deixe TODOs, mocks vazios nem placeholders.\n"
                       . "Responda sempre em português.\n\n"
                       . "### ESPECIFICAÇÃO DA FASE:\n# Phase " . $phaseText . "\n\n";

        if (!empty($feedback)) {
            $promptContent .= "### ⚠️ RELATÓRIO DE AUDITORIA DO CICLO ANTERIOR (CORRIJA OBRIGATORIAMENTE):\n" . $feedback . "\n";
        }

        file_put_contents($tempTaskFile, $promptContent);

        // ─── Agente 1: Implementador ──────────────────────────────────────
        echo "🤖 Agente 1 (Implementador) aplicando alterações no disco...\n";

        $implOutput = [];
        $implCode = 0;
        exec('claude -p "Leia o arquivo .spec/.active_task.tmp e implemente todas as tarefas e critérios descritos nele criando e editando os arquivos necessários." --dangerously-skip-permissions 2>&1', $implOutput, $implCode);

        $implText = implode("\n", $implOutput);
        echo $implText . "\n";

        // CORREÇÃO: aborta em erro de infra sem gastar ciclo
        if (preg_match($infraErrorPattern, $implText)) {
            echo "\n🛑 [ABORT] Erro de infraestrutura, não de implementação:\n";
            echo trim($implText) . "\n";
            echo "Nenhum progresso foi gravado. Resolva e rode novamente.\n";
            exit(2);
        }

        // CORREÇÃO: se nada mudou no disco, gates verdes são falso positivo
        $dirtyOutput = [];
        exec('git status --porcelain', $dirtyOutput);
        if (empty($dirtyOutput)) {
            echo "\n🛑 [ABORT] O agente não alterou nenhum arquivo.\n";
            echo "Os gates passariam por inércia (o código anterior está intacto).\n";
            exit(3);
        }

        // ─── Gates mecânicos ──────────────────────────────────────────────
        echo "\n⚙️  Executando Esteira Mecânica de Verificação...\n";

        // CORREÇÃO: 2>&1 captura stderr (Pest e Vite escrevem erros lá)
        $testOutput = [];
        $testCode = 0;
        exec('php artisan test 2>&1', $testOutput, $testCode);

        $buildOutput = [];
        $buildCode = 0;
        exec('npm run build 2>&1', $buildOutput, $buildCode);

        if ($testCode !== 0 || $buildCode !== 0) {
            $feedback = "FALHA NOS GATES MECÂNICOS:\n\n"
                      . "Erros nos Testes (Pest):\n" . implode("\n", array_slice($testOutput, -80)) . "\n\n"
                      . "Erros no Build (Vite):\n" . implode("\n", array_slice($buildOutput, -80));
            echo "❌ Gate Mecânico falhou. Realimentando erro no agente para auto-correção...\n";
            continue;
        }

        echo "✅ Gates Mecânicos passaram com sucesso (Testes 100% e Build 0 erros)!\n";

        // ─── Agente 2: Auditor ────────────────────────────────────────────
        echo "⚖️  Agente 2 (Auditor Read-Only) inspecionando conformidade com a Spec...\n";

        $evalPrompt = "Você é o Auditor de Qualidade Read-Only. Inspecione os arquivos do projeto e valide se a fase abaixo foi integralmente cumprida de acordo com todos os seus Acceptance Criteria e as regras de .ai/rules/.\n\n"
                    . "FASE:\n# Phase " . $phaseText . "\n\n"
                    . "INSTRUÇÃO CRÍTICA DE FORMATO: Sua resposta DEVE começar com a palavra DONE ou FALTA. Texto puro, sem markdown, sem cabeçalho, sem ##, sem asteriscos, sem emoji antes dela. O primeiro caractere da sua resposta deve ser a letra D ou a letra F. Depois disso escreva o que quiser.";

        $verdict = shell_exec('claude -p ' . escapeshellarg($evalPrompt) . ' --allowedTools "Read,Glob,Grep" 2>&1');
        $verdictTrimmed = trim((string) $verdict);

        // CORREÇÃO: erro de infra no auditor também aborta
        if ($verdictTrimmed === '' || preg_match($infraErrorPattern, $verdictTrimmed)) {
            echo "\n🛑 [ABORT] Auditor não respondeu ou retornou erro de infraestrutura:\n";
            echo ($verdictTrimmed === '' ? '(resposta vazia)' : $verdictTrimmed) . "\n";
            exit(2);
        }

        // CORREÇÃO: veredito lido apenas na PRIMEIRA palavra (determinístico)
        $firstWord = strtoupper(preg_split('/[\s,.\-:#*`>\n]+/', $verdictTrimmed, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '');
        $isApproved = ($firstWord === 'DONE');

        // Fallback: o auditor às vezes ignora o formato e responde em markdown.
        if (!$isApproved && preg_match('/(cumprida integralmente|✅\s*\**\s*Fase cumprida)/iu', $verdictTrimmed)) {
            $isApproved = true;
        }

        if ($isApproved) {
            echo "🏆 Auditor aprovou a fase com louvor (DONE)!\n";

            // CORREÇÃO: commit verificado antes de avançar a fase
            $commitMsg = "feat(phase): " . $phaseTitle;
            $commitOutput = [];
            $commitCode = 0;
            exec('git add -A && git commit -q -m ' . escapeshellarg($commitMsg) . ' 2>&1', $commitOutput, $commitCode);

            if ($commitCode !== 0) {
                echo "\n🛑 [ABORT] O commit falhou:\n" . implode("\n", $commitOutput) . "\n";
                echo "Fase NÃO avançada. Verifique a configuração do git (user.name/user.email) ou hooks.\n";
                exit(4);
            }

            $commitSha = trim((string) shell_exec('git rev-parse --short HEAD'));
            $manifestEntry = date('Y-m-d H:i:s') . " | Phase " . ($currentPhaseIndex + 1) . " | " . $commitSha . " | " . $phaseTitle . "\n";
            file_put_contents($manifestFile, $manifestEntry, FILE_APPEND);

            echo "📦 Commit atômico gerado: [$commitSha] '$commitMsg'\n";

            file_put_contents($progressFile, $currentPhaseIndex + 1);
            $phasePassed = true;
            break;
        } else {
            $feedback = "O AUDITOR READ-ONLY REPROVOU A IMPLEMENTAÇÃO:\n" . $verdictTrimmed;
            echo "❌ Auditor reprovou: " . $verdictTrimmed . "\n";
        }
    }

    if (!$phasePassed) {
        echo "\n🚨 [CIRCUIT BREAKER] A Phase " . $phaseTitle . " falhou $maxTries vezes consecutivas.\n";
        echo "Interrompendo a execução autônoma para segurança de tokens e intervenção humana.\n";
        exit(1);
    }
}