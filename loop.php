<?php

$maxTries = 3;
$phasesFile = __DIR__ . '/.spec/init/project-phases.md';
$progressFile = __DIR__ . '/.phases/.progress';
$manifestFile = __DIR__ . '/.phases/manifest.txt';
$tempTaskFile = __DIR__ . '/.spec/.active_task.tmp';

$infraErrorPattern = '/(session limit|rate limit|usage limit|quota exceeded|Invalid API key|not authenticated|unauthorized|ECONNREFUSED|ETIMEDOUT|network error)/i';

if (!file_exists(__DIR__ . '/.phases')) {
    mkdir(__DIR__ . '/.phases', 0755, true);
}

function readProgressIndex(string $file): int {
    if (!file_exists($file)) {
        file_put_contents($file, '0');
        return 0;
    }
    $raw = (string) file_get_contents($file);
    $digitsOnly = preg_replace('/\D/', '', $raw);
    return $digitsOnly === '' ? 0 : (int) $digitsOnly;
}

if (!file_exists($progressFile)) {
    file_put_contents($progressFile, '0');
}

// Suporte a override via CLI: php loop.php 14
if (isset($argv[1]) && is_numeric($argv[1])) {
    file_put_contents($progressFile, (string) (int) $argv[1]);
}

$content = file_get_contents($phasesFile);
$phases = preg_split('/^# Phase /m', $content, -1, PREG_SPLIT_NO_EMPTY);

echo "🔍 Verificação de pré-voo...\n";

exec('claude --version 2>&1', $vOut, $vCode);
if ($vCode !== 0) {
    echo "🛑 Binário 'claude' não encontrado no PATH. Abortando.\n";
    exit(1);
}

// Checagem de Git ignorando arquivos internos de controle do harness
exec('git status --porcelain', $dirtyPreflight);
$dirtyCodeFiles = array_filter($dirtyPreflight, function ($line) {
    $trimmed = trim(substr($line, 2));
    return !str_starts_with($trimmed, '.phases/') && !str_starts_with($trimmed, '.spec/.active_task.tmp');
});

if (!empty($dirtyCodeFiles)) {
    echo "🛑 A árvore do git tem alterações de código não commitadas:\n";
    echo implode("\n", $dirtyCodeFiles) . "\n";
    echo "Commite ou descarte antes de rodar o harness.\n";
    exit(1);
}

$initialPhase = readProgressIndex($progressFile);
echo "✅ Pré-voo ok. " . count($phases) . " fases carregadas.\n";
echo "   Fase atual: índice " . $initialPhase . "\n";

while (true) {
    $currentPhaseIndex = readProgressIndex($progressFile);

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
    echo "🚀 EXECUTANDO: Phase " . $phaseTitle . " (Índice: " . $currentPhaseIndex . ")\n";
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

        echo "🤖 Agente 1 (Implementador) aplicando alterações no disco...\n";

        $implOutput = [];
        $implCode = 0;
        exec('claude -p "Leia o arquivo .spec/.active_task.tmp e implemente todas as tarefas e critérios descritos nele criando e editando os arquivos necessários." --dangerously-skip-permissions 2>&1', $implOutput, $implCode);

        $implText = implode("\n", $implOutput);
        echo $implText . "\n";

        if (preg_match($infraErrorPattern, $implText)) {
            echo "\n🛑 [ABORT] Erro de infraestrutura, não de implementação:\n";
            echo trim($implText) . "\n";
            echo "Nenhum progresso foi gravado. Resolva e rode novamente.\n";
            exit(2);
        }

        $dirtyOutput = [];
        exec('git status --porcelain', $dirtyOutput);
        $dirtyCodeChanges = array_filter($dirtyOutput, function ($line) {
            $trimmed = trim(substr($line, 2));
            return !str_starts_with($trimmed, '.phases/') && !str_starts_with($trimmed, '.spec/.active_task.tmp');
        });

        if (empty($dirtyCodeChanges)) {
            echo "\n🛑 [ABORT] O agente não alterou nenhum arquivo de código.\n";
            echo "Os gates passariam por inércia (o código anterior está intacto).\n";
            exit(3);
        }

        echo "\n⚙️  Executando Esteira Mecânica de Verificação...\n";

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

        echo "⚖️  Agente 2 (Auditor Read-Only) inspecionando conformidade com a Spec...\n";

        $evalPrompt = "Você é o Auditor de Qualidade Read-Only. Inspecione os arquivos do projeto e valide se a fase abaixo foi integralmente cumprida de acordo com todos os seus Acceptance Criteria e as regras de .ai/rules/.\n\n"
                    . "FASE:\n# Phase " . $phaseText . "\n\n"
                    . "INSTRUÇÃO CRÍTICA DE FORMATO: Sua resposta DEVE começar com a palavra DONE (aprovada) ou FALTA (com pendências). "
                    . "O PRIMEIRO CARACTERE da sua resposta deve ser a letra D ou a letra F. "
                    . "Proibido qualquer coisa antes: sem markdown, sem ##, sem asteriscos, sem emoji, sem saudação, sem 'Aqui está'. "
                    . "Depois dessa primeira palavra, escreva o relatório como quiser.";

        $verdict = shell_exec('claude -p ' . escapeshellarg($evalPrompt) . ' --allowedTools "Read,Glob,Grep" 2>&1');
        $verdictTrimmed = trim((string) $verdict);

        if ($verdictTrimmed === '' || preg_match($infraErrorPattern, $verdictTrimmed)) {
            echo "\n🛑 [ABORT] Auditor não respondeu ou retornou erro de infraestrutura:\n";
            echo ($verdictTrimmed === '' ? '(resposta vazia)' : $verdictTrimmed) . "\n";
            exit(2);
        }

        $isApproved = false;

        $cleanVerdict = preg_replace('/^[\s#*`>\-–—✅❌📋🏆⚖️🔍\x{1F300}-\x{1FAFF}]+/u', '', $verdictTrimmed);
        $firstWord = strtoupper(preg_split('/[\s,.\-:]+/', $cleanVerdict, -1, PREG_SPLIT_NO_EMPTY)[0] ?? '');
        if ($firstWord === 'DONE') {
            $isApproved = true;
        }

        if (!$isApproved && preg_match('/(cumprida integralmente|integralmente cumprida|fase cumprida|aprovad[ao] integralmente|veredito:?\s*\**\s*(✅|DONE|APROVAD))/iu', $verdictTrimmed)) {
            $isApproved = true;
        }

        if (preg_match('/^\s*[#*`\s]*FALTA\b/iu', $verdictTrimmed)
            || preg_match('/(reprovad[ao]|não cumprida|nao cumprida|pendência bloqueante|pendencia bloqueante)/iu', $verdictTrimmed)) {
            $isApproved = false;
        }

        if ($isApproved) {
            echo "🏆 Auditor aprovou a fase (DONE)!\n";

            $cleanTitle = preg_replace('/^(?:Phase|Fase)\s*\d+:\s*/i', '', $phaseTitle);
            $commitMsg = "feat(fase-" . ($currentPhaseIndex + 1) . "): " . $cleanTitle;
            $commitOutput = [];
            $commitCode = 0;
            exec('git add -A && git commit -q -m ' . escapeshellarg($commitMsg) . ' 2>&1', $commitOutput, $commitCode);

            if ($commitCode !== 0) {
                echo "\n🛑 [ABORT] O commit falhou:\n" . implode("\n", $commitOutput) . "\n";
                echo "Fase NÃO avançada. Verifique a configuração do git.\n";
                exit(4);
            }

            $commitSha = trim((string) shell_exec('git rev-parse --short HEAD'));
            $manifestEntry = date('Y-m-d H:i:s') . " | Fase " . ($currentPhaseIndex + 1) . " | " . $commitSha . " | " . $cleanTitle . "\n";
            file_put_contents($manifestFile, $manifestEntry, FILE_APPEND);

            echo "📦 Commit atômico gerado: [$commitSha] '$commitMsg'\n";

            exec('git add -A && git commit -q -m ' . escapeshellarg('chore: atualiza manifesto') . ' 2>&1');

            file_put_contents($progressFile, (string) ($currentPhaseIndex + 1));
            exec('git add -A && git commit -q -m ' . escapeshellarg('chore: avanca progresso') . ' 2>&1');

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