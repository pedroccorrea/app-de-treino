<?php

$maxTries = 3;
$phasesFile = __DIR__ . '/.spec/init/project-phases.md';
$progressFile = __DIR__ . '/.phases/.progress';
$manifestFile = __DIR__ . '/.phases/manifest.txt';
$tempTaskFile = __DIR__ . '/.spec/.active_task.tmp';

if (!file_exists(__DIR__ . '/.phases')) {
    mkdir(__DIR__ . '/.phases', 0755, true);
}

if (!file_exists($progressFile)) {
    file_put_contents($progressFile, 0);
}

$content = file_get_contents($phasesFile);
$phases = preg_split('/^# Phase /m', $content, -1, PREG_SPLIT_NO_EMPTY);

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
                       . "Crie todos os arquivos necessários e aplique as alterações no disco. Não deixe TODOs, mocks vazios nem placeholders.\n\n"
                       . "### ESPECIFICAÇÃO DA FASE:\n# Phase " . $phaseText . "\n\n";

        if (!empty($feedback)) {
            $promptContent .= "### ⚠️ RELATÓRIO DE AUDITORIA DO CICLO ANTERIOR (CORRIJA OBRIGATORIAMENTE):\n" . $feedback . "\n";
        }

        file_put_contents($tempTaskFile, $promptContent);

        echo "🤖 Agente 1 (Implementador) aplicando alterações no disco...\n";
        passthru('claude -p "Leia o arquivo .spec/.active_task.tmp e implemente todas as tarefas e critérios descritos nele criando e editando os arquivos necessários." --dangerously-skip-permissions');

        echo "\n⚙️  Executando Esteira Mecânica de Verificação...\n";

        // Gate 1: Testes de Arquitetura e Feature (Pest)
        $testOutput = [];
        $testCode = 0;
        exec('php artisan test', $testOutput, $testCode);

        // Gate 2: Compilação de Frontend (Vite)
        $buildOutput = [];
        $buildCode = 0;
        exec('npm run build', $buildOutput, $buildCode);

        if ($testCode !== 0 || $buildCode !== 0) {
            $feedback = "FALHA NOS GATES MECÂNICOS:\n\n"
                      . "Erros nos Testes (Pest):\n" . implode("\n", array_slice($testOutput, -30)) . "\n\n"
                      . "Erros no Build (Vite):\n" . implode("\n", array_slice($buildOutput, -30));
            echo "❌ Gate Mecânico falhou. Realimentando erro no agente para auto-correção...\n";
            continue;
        }

        echo "✅ Gates Mecânicos passaram com sucesso (Testes 100% e Build 0 erros)!\n";

        echo "⚖️  Agente 2 (Auditor Read-Only) inspecionando conformidade com a Spec...\n";
        $evalPrompt = "Você é o Auditor de Qualidade Read-Only. Inspecione os arquivos do projeto e valide se a fase abaixo foi integralmente cumprida de acordo com todos os seus Acceptance Criteria e as regras de .ai/rules/.\n\n"
                    . "FASE:\n# Phase " . $phaseText . "\n\n"
                    . "INSTRUÇÃO CRÍTICA DE FORMATO: Se a fase estiver aprovada e sem pendências bloqueantes, sua PRIMEIRA PALAVRA de resposta DEVE ser 'DONE'. Se houver pendências, comece com 'FALTA - motivo'.";

        $verdict = shell_exec('claude -p ' . escapeshellarg($evalPrompt) . ' --allowedTools "Read,Glob,Grep"');
        $verdictTrimmed = trim((string) $verdict);

        // Detector Robusto de Aprovação
        $hasRejection = preg_match('/\b(FALTA|REPROVAD[AO]|NÃO cumprida|NÃO aprovad[ao]|PENDENTE)\b/i', $verdictTrimmed);
        $hasApproval = preg_match('/\b(DONE|APROVAD[AO]|cumprida integralmente|integralmente cumprida)\b/i', $verdictTrimmed);

        $isApproved = $hasApproval && !$hasRejection;

        if ($isApproved) {
            echo "🏆 Auditor aprovou a fase com louvor (DONE)!\n";

            $commitMsg = "feat(phase-" . ($currentPhaseIndex + 1) . "): " . $phaseTitle;
            exec('git add -A && git commit -q -m ' . escapeshellarg($commitMsg));
            
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