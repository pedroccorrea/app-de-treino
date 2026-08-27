# Phase 1: Menu Lateral e Layout Mobile-First
## Tasks
- Criar o componente resources/js/Components/Navigation/Sidebar.vue (fixo no desktop, gaveta/drawer deslizante no mobile com backdrop).
- Atualizar resources/js/Layouts/AuthenticatedLayout.vue para remover a navbar superior do Breeze e adotar a nova Sidebar.
- Incluir rotas ativas: Dashboard (dashboard), Treinos (workouts.index) e Perfil (profile.edit).
- Estilização: Dark Mode nativo com Tailwind CSS e tom violeta #8B5CF6 nos itens ativos.
## Acceptance Criteria
1. O comando npm run build passa com zero erros.
2. O comando php artisan test passa 100%.
3. O componente resources/js/Components/Navigation/Sidebar.vue existe e possui links com a tag <Link>.

# Phase 2: Módulo IA Vision (Scanner de Fichas com Entity Resolution)
## Tasks
- Criar o Service app/Services/WorkoutScannerService.php com o método scanAndImport(UploadedFile $image, User $user).
- O Service deve ler a imagem, enviar para a API do Gemini via Http::post() com a lista de Exercise::pluck('name', 'id') no prompt para resolver sinônimos sem duplicar exercícios existentes.
- Criar FormRequest app/Http/Requests/ScanWorkoutRequest.php validando arquivo de imagem (jpg, png, webp, máx 10MB).
- Criar Controller e rota POST /workouts/scan.
- Criar componente resources/js/Components/Workouts/ScanWorkoutModal.vue com upload de foto e botão 📷 Escanear Ficha em Workouts/Index.vue.
## Acceptance Criteria
1. O teste tests/Feature/WorkoutScannerTest.php passa 100% usando a fixture tests/Fixtures/ocr-workout-sheet.json com Http::fake().
2. O teste de arquitetura tests/Architecture/ArchitecturalRulesTest.php passa sem nenhuma violação.
3. O build do frontend npm run build passa sem erros.

# Phase 3: Motor IA de Sobrecarga Progressiva (Personal Trainer Digital)
## Tasks
- Criar o Service app/Services/ProgressiveOverloadService.php com o método analyzeWorkout(User $user, Workout $workout).
- O Service consulta as últimas 3 WorkoutSession com set_logs desse treino e envia o histórico estruturado para a IA gerar conselhos de progressão de carga.
- Criar a rota GET /workouts/{workout}/overload-suggestions no WorkoutController.
- Criar o componente resources/js/Components/Workouts/AIProgressiveAdvice.vue e adicioná-lo em Workouts/Show.vue antes do botão "Iniciar Treino".
## Acceptance Criteria
1. O teste tests/Feature/ProgressiveOverloadTest.php passa 100% validando a entrega do payload mockado da fixture tests/Fixtures/progressive-overload-advice.json.
2. Nenhuma alteração é gravada no banco durante a requisição de análise.
3. O comando npm run build passa sem erros.

# Phase 4: Dashboard Premium & Analytics de Músculos Negligenciados
## Tasks
- Criar o Service app/Services/DashboardAnalyticsService.php calculando Streak, Personal Records (PRs), agrupamento de volume semanal por MuscleGroup e parecer de assimetria via IA.
- Atualizar DashboardController@index para injetar esses dados via props do Inertia.
- Criar componentes em resources/js/Components/Dashboard/: MetricCards.vue, ActivityHeatmap.vue e MuscleBalanceAlert.vue.
- Atualizar a página resources/js/Pages/Dashboard.vue integrando esses componentes.
## Acceptance Criteria
1. O teste tests/Feature/DashboardAnalyticsTest.php passa 100% cobrindo Streak, PRs e a fixture tests/Fixtures/muscle-balance-alert.json.
2. php artisan test passa em 100% de toda a suíte de testes.
3. npm run build compila sem nenhum erro.

# Phase 5: Dashboard Action-First, Fichas Ativas/Arquivadas e IA Contextual
## Tasks
1. **Banco de Dados & Gestão de Fichas (Ativas vs. Arquivadas e Exclusão):**
   - Criar migration adicionando `is_active` (boolean, default true) e `program_name` (string, nullable) na tabela `workouts`.
   - Adicionar scopes `scopeActive` e `scopeArchived` no Model `Workout`.
   - No `WorkoutService` e `WorkoutController`:
     * Implementar método `toggleArchive(Workout $workout)` para arquivar/reativar fichas.
     * Implementar método `destroy(Workout $workout)` para deletar o treino com segurança (`DB::transaction`).
     * Registrar as rotas `PATCH /workouts/{workout}/archive` e `DELETE /workouts/{workout}` em `routes/web.php`.
   - No Frontend (`Workouts/Index.vue`):
     * Adicionar abas/filtro para alternar entre "Fichas Ativas" e "Arquivadas".
     * Adicionar botão de exclusão com modal de confirmação antes de deletar um treino.
     * Permitir reativar fichas arquivadas.

2. **Dashboard Otimizada e Instantânea (Action-First, <100ms):**
   - No `DashboardController@index`: REMOVER a chamada síncrona de IA do Gemini. A tela deve carregar instantaneamente via SQL puro.
   - Identificar o treino ativo agendado para o dia da semana atual (`todayWorkout`).
   - Passar como props: `todayWorkout` (com contagem de exercícios e rota de início), `streak` (dias seguidos) e `activeWorkouts`.
   - No Frontend (`Dashboard.vue`):
     * REMOVER: Heatmap de atividade, lista de Recordes Pessoais (PRs), card de volume semanal e o card de alerta de equilíbrio muscular.
     * Adicionar Card Hero em destaque: **"🔥 Treino de Hoje (Dia da Semana)"** exibindo o nome da ficha, grupo muscular, quantidade de exercícios e botão grande **"🔥 Iniciar Treino Agora"** (que inicia a sessão com 1 toque).
     * Se for dia de descanso (sem treino agendado): exibir card amigável "Hoje é dia de descanso! Ou escolha um treino abaixo" com atalhos para os treinos ativos.
     * Card de Ofensiva: "⚡ Sequência de Treinos" (X dias seguidos).
     * Lista rápida dos treinos ativos.

3. **IA de Sobrecarga Automática no Exercício Ativo (`Active.vue`):**
   - REMOVER o widget manual "Personal Trainer Digital / Gerar Sugestões" da tela `Workouts/Show.vue`.
   - No `WorkoutSessionController@show` / `WorkoutSessionService`: carregar as sugestões de sobrecarga automaticamente ao abrir a sessão e injetá-las nas props do `Active.vue`.
   - No componente do exercício ativo (`Active.vue` / `ExerciseActiveCard.vue`): exibir automaticamente um badge sutil da IA abaixo do título do exercício com a sugestão de carga (ex: *"💡 Dica da IA: 15kg → 17kg (Meta: 8-10 reps)"*) sem exigir clique manual.

## Acceptance Criteria
1. O carregamento da Dashboard (`GET /dashboard`) NÃO realiza chamadas HTTP externas e responde com status 200 via testes no Pest.
2. Testes de Feature em `tests/Feature/WorkoutManagementTest.php` cobrem arquivamento, reativação e exclusão com 100% de sucesso.
3. `php artisan test` passa em 100% da suíte de testes.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
5. `npm run build` compila sem nenhum erro.

# Phase 6: Hierarquia de Programas de Treino e Periodização
## Tasks
1. **Banco de Dados & Models:**
   - Criar model e migration `app/Models/WorkoutProgram.php` (tabela `workout_programs`: `id`, `user_id`, `name`, `description` nullable, `is_active` boolean default false, `archived_at` timestamp nullable, `timestamps`).
   - Adicionar coluna `workout_program_id` (foreign key nullable on delete cascade) na tabela `workouts`.
   - Migration Backfill: Em um hook após a criação da tabela, se existirem `workouts` órfãos, criar um `WorkoutProgram` padrão ("Meu Programa Atual", `is_active = true`) e associar todos os treinos existentes do usuário a ele.
   - Configurar relacionamentos Eloquent:
     * `User` hasMany `WorkoutProgram`.
     * `WorkoutProgram` belongsTo `User`, hasMany `Workout`.
     * `Workout` belongsTo `WorkoutProgram`.

2. **Backend Services & Controllers:**
   - Criar `app/Services/WorkoutProgramService.php`:
     * `getUserPrograms(User $user)`: retorna programas com contagem de treinos e status.
     * `createProgram(User $user, array $data)`: cria novo programa.
     * `activateProgram(WorkoutProgram $program)`: marca o programa como `is_active = true`, arquiva/desativa os outros programas do mesmo usuário em `DB::transaction`.
     * `archiveProgram(WorkoutProgram $program)`: desativa e seta `archived_at = now()`.
     * `deleteProgram(WorkoutProgram $program)`: deleta o programa e suas fichas associadas com segurança.
   - Criar `app/Http/Controllers/WorkoutProgramController.php` e registrar as rotas em `routes/web.php`:
     * `GET /programs` (name: `programs.index`)
     * `POST /programs` (name: `programs.store`)
     * `PATCH /programs/{program}/activate` (name: `programs.activate`)
     * `PATCH /programs/{program}/archive` (name: `programs.archive`)
     * `DELETE /programs/{program}` (name: `programs.destroy`)
   - Atualizar `WorkoutScannerService` e `WorkoutService`: ao criar/escanear uma ficha sem programa explícito, vincular automaticamente ao programa ativo atual do usuário.

3. **Frontend (Vue 3 + Inertia + Tailwind):**
   - Atualizar `resources/js/Pages/Workouts/Index.vue`:
     * Exibir o Cabeçalho do Programa Ativo (ex: "📋 Programa Ativo: Hipertrofia ABCD") com botão "+ Novo Programa" e modal de criação.
     * Aba "Programa Ativo": exibe as fichas daquele programa com suas opções de edição/exclusão.
     * Aba "Programas Arquivados": exibe os blocos de programas passados com data de arquivamento, lista de treinos que continham e botões "⚡ Reativar Programa" e "🗑️ Excluir".
   - Atualizar `resources/js/Pages/Dashboard.vue`:
     * Card Hero "Treino de Hoje" busca o treino agendado do programa ativo atual e exibe o badge do nome do programa.

## Acceptance Criteria
1. `tests/Feature/WorkoutProgramTest.php` passa 100% cobrindo criação, ativação exclusiva (ativar um desativa outros), arquivamento e exclusão em cascata.
2. `php artisan test` passa em 100% de toda a suíte.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem nenhuma violação.
4. `npm run build` compila com zero erros.


# Phase 7: Navegação Fluida de Programas e Edição de Rotinas
## Tasks
1. **Tela de Detalhes e Edição de Programa (`resources/js/Pages/Programs/Show.vue`):**
   - No `Programs/Index.vue`, tornar o card inteiro do programa clicável, navegando para `programs.show`.
   - Na página `Programs/Show.vue`:
     * Cabeçalho com nome e descrição do programa + botão de Lápis para alternar entre modo de visualização e edição inline (com botão Salvar via `form.put`).
     * Listagem das fichas/treinos vinculadas a este programa.
     * Cada ficha possui botão de exclusão com modal de confirmação.
     * Clicar no card da ficha navega direto para a tela de edição daquela ficha (`workouts.edit`).
     * Botão "+ Adicionar Ficha a este Programa" (abre o modal de criação já associando o `workout_program_id`).

2. **Refatoração da Tela de Treinos (`Workouts/Index.vue`):**
   - No banner do topo "PROGRAMA ATIVO", substituir o botão "+ Novo Programa" por um botão de destaque "🔄 Trocar Programa" com link para `route('programs.index')`.
   - Remover as abas confusas de "Programas Arquivados" da tela de treinos, exibindo estritamente as fichas do programa ativo atual.

3. **Backend (`WorkoutProgramController` & `WorkoutProgramService`):**
   - Adicionar método `show(WorkoutProgram $program)` carregando os treinos ordenados (`with('workouts.exercises')`).
   - Adicionar método `update(UpdateProgramRequest $request, WorkoutProgram $program)` para atualizar nome e descrição.
   - Registrar as rotas correspondentes em `routes/web.php`.

## Acceptance Criteria
1. `tests/Feature/WorkoutProgramTest.php` cobre visualização de detalhes do programa e atualização de nome/descrição.
2. `php artisan test` passa 100%.
3. `npm run build` compila com zero erros.

# Phase 8: Refatoração de Navegação Contextual, Separação de Ver/Editar e Textos de Interface
## Tasks
1. **Ajustes de Textos de Interface:**
   - Em `Programs/Show.vue`: alterar o botão de criação para "+ Adicionar Treino".
   - Em `Workouts/Edit.vue`: alterar o botão do catálogo para "+ Adicionar Exercício".

2. **Fluxo de Criação dentro do Programa:**
   - No modal de "+ Adicionar Treino" em `Programs/Show.vue`: ao submeter o formulário, manter o usuário na mesma página (`Programs/Show.vue`), fechando o modal e atualizando a listagem de treinos do programa.

3. **Separação de Visualização vs. Edição:**
   - Em `Programs/Show.vue`:
     * Clicar no card do treino abre a visualização (`Workouts/Show.vue`) com botão "Iniciar Treino", passando `return_to = route('programs.show', program.id)`.
     * Clicar no ícone de Lápis abre a edição (`Workouts/Edit.vue`), passando `return_to = route('programs.show', program.id)`.
   - Em `Workouts/Index.vue`:
     * Clicar no card do treino abre a visualização (`Workouts/Show.vue`), passando `return_to = route('workouts.index')`.
     * Clicar no ícone de Lápis abre a edição (`Workouts/Edit.vue`), passando `return_to = route('workouts.index')`.

4. **Navegação Contextual do Botão Voltar e Salvamento:**
   - Em `Workouts/Show.vue` e `Workouts/Edit.vue`:
     * O botão "Voltar" deve navegar estritamente para a URL fornecida em `return_to`.
     * Ao salvar a edição em `Workouts/Edit.vue`, redirecionar para o `return_to` fornecido com mensagem de sucesso.

## Acceptance Criteria
1. Criar um treino a partir de `programs.show` redireciona/permanece em `programs.show`.
2. Clicar no card do treino a partir de `programs.show` abre `workouts.show` com botão Voltar apontando para `programs.show`.
3. `tests/Feature/WorkoutNavigationFlowTest.php` valida os fluxos de redirecionamento contextual de `store`, `update` e `destroy`.
4. `php artisan test` passa 100%.
5. `npm run build` compila sem erros.

# Phase 9: Relação N:N Programas e Treinos, Seletor Múltiplo e Modal de Confirmação
## Tasks
1. **Banco de Dados & Models (Relação N:N):**
   - Criar migration para a tabela pivô `program_workouts` (`id`, `workout_program_id`, `workout_id`, `order` default 0, `timestamps`).
   - Adicionar índice único composto: `['workout_program_id', 'workout_id']`.
   - Migration Backfill: transferir os vínculos existentes de `workouts.workout_program_id` para a tabela pivô `program_workouts`.
   - Atualizar Model `WorkoutProgram`: relacionamento `workouts()` para `belongsToMany(Workout::class, 'program_workouts')->withPivot('order')->withTimestamps()`.
   - Atualizar Model `Workout`: relacionamento `programs()` para `belongsToMany(WorkoutProgram::class, 'program_workouts')->withPivot('order')->withTimestamps()`.

2. **Componente Reutilizável de Confirmação:**
   - Criar o componente `resources/js/Components/ConfirmationModal.vue` (utilizando `Modal.vue`, título, descrição, botão de ação perigosa/primária e botão de cancelar).
   - Substituir qualquer alerta nativo do navegador em `Programs/Index.vue`, `Programs/Show.vue` e `Workouts/Index.vue` pelo `ConfirmationModal.vue`.

3. **Backend (`WorkoutProgramService` e `WorkoutProgramController`):**
   - Implementar método `attachWorkouts(WorkoutProgram $program, array $workoutIds)` para vincular múltiplos treinos ao programa.
   - Implementar método `detachWorkout(WorkoutProgram $program, Workout $workout)` para desvincular um treino do programa (sem deletar o treino do banco).
   - Registrar as rotas:
     * `POST /programs/{program}/workouts/attach` (name: `programs.workouts.attach`)
     * `DELETE /programs/{program}/workouts/{workout}/detach` (name: `programs.workouts.detach`)

4. **Frontend - Seletor de Treinos no Programa (`Programs/Show.vue`):**
   - Ao clicar em "+ Adicionar Treino", abrir um modal com a lista de todos os treinos do usuário que ainda não estão vinculados ao programa, permitindo selecionar múltiplos via checkboxes.
   - No modal, incluir botões de ação rápida: "+ Criar Novo Treino" e "📷 Escanear Ficha" (já associando ao programa atual).
   - Na listagem de treinos do programa, o botão de lixeira deve desvincular o treino do programa (`detach`) com confirmação via `ConfirmationModal.vue`.

## Acceptance Criteria
1. Migration `program_workouts` criada e executada com sucesso.
2. `tests/Feature/WorkoutProgramManyToManyTest.php` passa 100% cobrindo vínculo de múltiplos treinos, desvinculação sem exclusão do treino e suporte a um treino pertencer a múltiplos programas.
3. `php artisan test` passa em 100% de toda a suíte.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem nenhuma violação.
5. `npm run build` compila sem erros.

# Phase 10: Otimização de Performance e Timeout do Scanner de Fichas
## Tasks
1. **Ajuste de Timeout e Memória no Backend:**
   - Em `app/Services/WorkoutScannerService.php`: adicionar `set_time_limit(120)` e `ini_set('memory_limit', '512M')` no início do método `scanAndImport` para evitar o erro fatal de 30 segundos do PHP.
   - Garantir timeout de 90s na requisição HTTP do Gemini (`timeout(90)`).

2. **Compressão Otimizada no Frontend (1200px / 75%):**
   - Em `resources/js/Components/Workouts/ScanWorkoutModal.vue`: implementar compressão via Canvas redimensionando a imagem para no máximo 1200px na maior dimensão e qualidade JPEG de 75%.
   - Isso reduz o arquivo para ~120KB antes do upload, garantindo máxima velocidade de envio e resposta da IA em menos de 3 segundos sem perder nitidez no texto térmico.

## Acceptance Criteria
1. `tests/Feature/WorkoutScannerTest.php` passa em 100%.
2. `php artisan test` passa em 100% de toda a suíte.
3. `npm run build` compila com zero erros.

# Phase 11: PWA Completo e Instalável no Celular
## Tasks
1. **Manifest e Metadados Mobile:**
   - Criar `public/manifest.json` com nome "IA-LIFT - Meu App de Treino", short_name "IA-LIFT", display `standalone`, background `#0B0F17`, theme_color `#8B5CF6`, orientação `portrait-primary` e declaração de ícones (192x192 e 512x512).
   - Atualizar `resources/views/app.blade.php` com as meta tags PWA (`theme-color`, `mobile-web-app-capable`, `apple-mobile-web-app-capable`, `apple-mobile-web-app-status-bar-style`, link do manifest e apple-touch-icon).

2. **Ícones PWA:**
   - Garantir que os arquivos de ícone `public/icons/icon-192x192.png` e `public/icons/icon-512x512.png` (ou SVGs correspondentes) existam com a identidade visual do app (fundo escuro e símbolo roxo).

3. **Service Worker e Registro:**
   - Criar `public/sw.js` com estratégia de cache para o App Shell (manifest, favicon e assets essenciais).
   - Injetar o script de registro do Service Worker no `app.blade.php`.

## Acceptance Criteria
1. `tests/Feature/PwaInfrastructureTest.php` passa 100% validando que `/manifest.json` e `/sw.js` são acessíveis via HTTP com status 200 e content-type correto.
2. `php artisan test` passa em 100% de toda a suíte.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem nenhuma violação.
4. `npm run build` compila com zero erros.

# Phase 12: Design System Setwave (Tokens Versionados e Componentes Base)
## Tasks
1. **Tokens Centralizados:**
   - Criar `resources/css/design-tokens.css` com as CSS custom properties extraídas do design aprovado (Nocturne): superfícies (`--surface-base: #0B0F17`, `--surface-raised`, `--surface-overlay`), acentos (`--accent: #8B5CF6`, `--accent-muted`, `--accent-glow`), texto (`--text-primary`, `--text-secondary`, `--text-tertiary`), raios de borda, alturas de toque (mínimo 48px) e escala de espaçamento.
   - Atualizar `tailwind.config.js` mapeando esses tokens para as classes utilitárias do projeto (`theme.extend.colors`, `borderRadius`, `spacing`), de modo que nenhum componente use hex literal.
   - Importar `design-tokens.css` em `resources/css/app.css`.

2. **Componentes Base Reutilizáveis:**
   - Extrair para `resources/js/Components/UI/`: `BaseButton.vue` (variantes `primary`, `secondary`, `danger`, `ghost`), `BaseCard.vue`, `BaseBadge.vue` e `SectionLabel.vue` (o rótulo em caixa alta e tracking largo usado no design).
   - `BaseButton.vue` deve suportar o estado de ripple/rastro de onda usado no botão principal do design.

3. **Documentação para o Harness:**
   - Criar `.ai/rules/design-system.md` documentando: paleta e quando usar cada token, escala tipográfica (incluindo os números-herói em 82px com `tabular-nums`), regra de zona do polegar (ações primárias nos 200px inferiores), alvos de toque mínimos (56px para ação primária, 48px para secundária), uso parcimonioso do roxo (acento, nunca preenchimento) e a lista de componentes base disponíveis com suas props.
   - O arquivo deve instruir explicitamente: "nunca escrever cor em hexadecimal direto no componente; sempre usar o token."
   - Adicionar referência ao novo arquivo em `.ai/rules/index.md`.

4. **Teste de Arquitetura Visual:**
   - Adicionar em `tests/Architecture/ArchitecturalRulesTest.php` uma regra que falhe se algum arquivo `.vue` em `resources/js/` contiver cor hexadecimal literal (regex `#[0-9a-fA-F]{6}`), com exceção explícita para `resources/css/design-tokens.css`.

## Acceptance Criteria
1. O arquivo `.ai/rules/design-system.md` existe e está referenciado em `.ai/rules/index.md`.
2. Os componentes em `resources/js/Components/UI/` existem e são importados por pelo menos um componente de página.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações, incluindo a nova regra de ausência de hex literal.
4. `php artisan test` passa em 100% de toda a suíte.
5. `npm run build` compila com zero erros.

# Phase 13: Rebrand Setwave (Identidade, Manifest e Ícones)
## Tasks
1. **Nome e Metadados:**
   - Atualizar `public/manifest.json`: `name` para "Setwave — Treino e Progressão", `short_name` para "Setwave", mantendo `display: standalone`, `orientation: portrait-primary`, `background_color` e `theme_color` lidos dos tokens da Phase 12.
   - Atualizar `resources/views/app.blade.php`: `<title>`, meta `application-name`, `apple-mobile-web-app-title` e `og:site_name` para Setwave.
   - Atualizar `config/app.php` (`name`) e o `.env.example` (`APP_NAME=Setwave`).
   - Substituir todas as ocorrências textuais de "IA-LIFT" no frontend e nos textos de interface.

2. **Marca Visual:**
   - Criar `public/icons/setwave-mark.svg` com o símbolo da onda em vetor, usando o token de acento.
   - Gerar `public/icons/icon-192x192.png` e `public/icons/icon-512x512.png` a partir do SVG, com fundo na cor de superfície base.
   - Criar `resources/js/Components/Brand/SetwaveLogo.vue` (props: `size`, `variant` entre `full` e `mark`) e usá-lo no cabeçalho da `Sidebar.vue`.
   - Atualizar `public/favicon.ico` e o `apple-touch-icon`.

3. **Splash e Estados Vazios:**
   - Aplicar a marca na tela de login e nos estados vazios (sem treinos, sem histórico).

## Acceptance Criteria
1. `tests/Feature/PwaInfrastructureTest.php` continua passando e valida que o `manifest.json` retorna `short_name` igual a "Setwave".
2. Nenhuma ocorrência da string "IA-LIFT" permanece em `resources/`, `config/` ou `public/manifest.json`.
3. Os arquivos de ícone 192x192 e 512x512 existem em `public/icons/`.
4. `php artisan test` passa em 100% de toda a suíte.
5. `npm run build` compila com zero erros.

# Phase 14: Propagação do Design System às Telas Existentes
## Tasks
1. **Dashboard (`resources/js/Pages/Dashboard.vue`):**
   - Reconstruir o Card Hero "Treino de Hoje" usando `BaseCard.vue` e `BaseButton.vue`, com o botão "Iniciar Treino Agora" respeitando a zona do polegar e altura mínima de 56px.
   - Aplicar a hierarquia tipográfica do design system ao Card de Ofensiva e à lista de treinos ativos.
   - Substituir os emojis dos títulos por ícones vetoriais consistentes com a marca.

2. **Treinos e Programas:**
   - Aplicar `BaseCard.vue`, `BaseBadge.vue` e `SectionLabel.vue` em `Pages/Workouts/Index.vue`, `Pages/Workouts/Show.vue`, `Pages/Programs/Index.vue` e `Pages/Programs/Show.vue`.
   - Padronizar o `ConfirmationModal.vue` com os tokens de superfície e a variante `danger` do `BaseButton.vue`.

3. **Navegação (`Components/Navigation/Sidebar.vue`):**
   - Atualizar o item ativo para usar o token de acento com o tratamento de brilho definido no design system.
   - Garantir que a gaveta mobile respeite os alvos de toque mínimos.

4. **Auditoria de Contraste:**
   - Verificar e corrigir qualquer par texto/fundo abaixo de 4.5:1, documentando os ajustes em `.ai/rules/design-system.md`.

## Acceptance Criteria
1. Nenhum arquivo `.vue` em `resources/js/Pages/` contém cor hexadecimal literal (validado pela regra de arquitetura da Phase 12).
2. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
3. `php artisan test` passa em 100% de toda a suíte.
4. `npm run build` compila com zero erros.

# Phase 15: Contrato Estruturado do Motor de Sobrecarga Progressiva
## Tasks
1. **DTO de Sugestão:**
   - Criar `app/DataTransferObjects/OverloadSuggestion.php` (readonly) com as propriedades: `exercise_id` (int), `suggested_load` (float), `suggested_reps` (int), `previous_load` (float nullable), `previous_reps` (int nullable), `rationale` (string) e `confidence` (enum ou string).
   - Criar `app/Enums/OverloadConfidence.php` com os casos `High`, `Medium` e `Low`.

2. **Refatoração do Service (`app/Services/ProgressiveOverloadService.php`):**
   - Alterar `analyzeWorkout(User $user, Workout $workout)` para retornar uma `Collection<OverloadSuggestion>` em vez de strings de texto livre.
   - Ajustar o prompt enviado à IA para exigir resposta em JSON estruturado contendo `exercise_id`, `suggested_load`, `suggested_reps` e `rationale`, enviando a lista de exercícios do treino com seus IDs.
   - **Eliminar o casamento por nome:** a associação entre sugestão e exercício deve usar exclusivamente `exercise_id`. Sugestões cujo `exercise_id` não pertença ao treino devem ser descartadas silenciosamente e registradas via `Log::warning`.
   - Implementar fallback: se a resposta da IA não for JSON válido ou vier incompleta, retornar coleção vazia sem lançar exceção — a tela deve degradar sem sugestão, nunca quebrar.

3. **Ajuste no Consumo:**
   - Atualizar `WorkoutSessionController` / `WorkoutSessionService` para injetar as sugestões já tipadas nas props do `Active.vue`, sem nenhum parse de string no controller ou no componente.
   - Atualizar `OverloadSuggestionCard.vue` para receber números prontos (`currentLoad`, `currentReps`, `previousLoad`, `previousReps`, `rationale`), removendo qualquer lógica de extração de valor do texto.

4. **Testes:**
   - Atualizar `tests/Fixtures/progressive-overload-advice.json` para o novo formato JSON estruturado.
   - Adicionar em `tests/Feature/ProgressiveOverloadTest.php` os casos: resposta válida retorna DTOs corretos; resposta com `exercise_id` inexistente é descartada; resposta malformada retorna coleção vazia sem exceção.

## Acceptance Criteria
1. `tests/Feature/ProgressiveOverloadTest.php` passa 100%, cobrindo os três casos acima.
2. Nenhuma ocorrência de casamento de sugestão por nome de exercício permanece em `app/Services/ProgressiveOverloadService.php`.
3. Nenhuma alteração é gravada no banco durante a requisição de análise.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
5. `php artisan test` passa em 100% de toda a suíte.
6. `npm run build` compila com zero erros.

# Phase 16: Tela de Histórico e Progressão por Exercício
## Tasks
1. **Backend — Consulta de Histórico:**
   - Criar `app/Services/ExerciseHistoryService.php` com os métodos:
     * `getHistory(User $user, Exercise $exercise, HistoryRange $range)`: retorna as sessões com `set_logs` do exercício no período, ordenadas por data.
     * `getVolumeSeries(User $user, Exercise $exercise, HistoryRange $range)`: volume total (carga × reps) por sessão, pronto para o gráfico.
     * `getPersonalRecord(User $user, Exercise $exercise)`: maior carga registrada e a data.
   - Criar `app/Enums/HistoryRange.php` com os casos `FourWeeks`, `TwelveWeeks` e `OneYear`.
   - Toda a consulta deve ser SQL puro, sem nenhuma chamada HTTP externa.

2. **Rota e Controller:**
   - Criar `app/Http/Controllers/ExerciseHistoryController.php` com o método `show(Exercise $exercise, Request $request)`.
   - Registrar `GET /exercises/{exercise}/history` (name: `exercises.history`) em `routes/web.php`, com autorização garantindo que o usuário só acesse o próprio histórico.
   - O parâmetro de período vem via query string (`?range=4w`), com `FourWeeks` como padrão.

3. **Frontend (`resources/js/Pages/Exercises/History.vue`):**
   - Construir a página seguindo o bloco "02 — Histórico e progressão" do design aprovado, usando exclusivamente os tokens e componentes base da Phase 12.
   - Criar `Components/Exercises/ExerciseHistoryChart.vue` (curva de progressão de carga, com a mesma linguagem visual da onda da marca).
   - Criar `Components/Exercises/SessionHistoryList.vue` (lista de sessões passadas com carga, reps e data).
   - Criar `Components/Exercises/RangeFilter.vue` (4S / 12S / 1A), navegando via `router.get` preservando o scroll.
   - Exibir o recorde pessoal em destaque no topo.

4. **Ponto de Entrada:**
   - Em `ExerciseActiveCard.vue`, tornar o nome do exercício clicável, navegando para `exercises.history` com `return_to` apontando para a sessão ativa.
   - Respeitar as regras de `.ai/rules/navigation-flow-invariants.md` para o botão Voltar.

## Acceptance Criteria
1. `tests/Feature/ExerciseHistoryTest.php` passa 100%, cobrindo: retorno da série de volume, cálculo do recorde pessoal, filtro por cada um dos três períodos e bloqueio de acesso ao histórico de outro usuário (403).
2. `GET /exercises/{exercise}/history` não realiza nenhuma chamada HTTP externa.
3. `tests/Feature/WorkoutNavigationFlowTest.php` valida o retorno contextual da tela de histórico para a sessão ativa.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
5. `php artisan test` passa em 100% de toda a suíte.
6. `npm run build` compila com zero erros.
