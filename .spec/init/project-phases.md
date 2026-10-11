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

# Phase 17: Header Fixo, Brilho Sutil de Botões e Navegação Contextual na Dashboard
## Tasks
1. **Header Fixo com Desfoque no Topo:**
   - Em `resources/js/Layouts/AuthenticatedLayout.vue`: tornar a barra superior mobile fixa no topo durante a rolagem (`sticky top-0 z-40 bg-surface-base/90 backdrop-blur-md border-b border-border-subtle`).
   - Garantir que o conteúdo principal (`<main>`) não fique escondido sob a barra fixa, mantendo o espaçamento visual correto.

2. **Ajuste de Brilho dos Botões Primários:**
   - Em `resources/js/Components/UI/BaseButton.vue` e nos botões primários do projeto: suavizar a intensidade do brilho roxo/violeta, substituindo sombras fortes por um brilho ambiente discreto e elegante (ex: `shadow-md shadow-violet-500/15 hover:shadow-violet-500/25` e borda sutil).

3. **Correção de Navegação da Dashboard para Treinos:**
   - Em `resources/js/Pages/Dashboard.vue` (e seus componentes de atalho a treinos):
     * Nos botões de atalho do card de descanso e na lista de treinos ativos, passar obrigatoriamente o parâmetro `return_to = route('dashboard')` nos links para `workouts.show`.
   - Em `resources/js/Pages/Workouts/Show.vue`:
     * Garantir que o botão "← Voltar" leia a prop `returnTo` e devolva o usuário para a Dashboard quando a navegação foi originada nela.

## Acceptance Criteria
1. `tests/Feature/WorkoutNavigationFlowTest.php` valida que acessar `workouts.show` com `return_to=/dashboard` faz o botão voltar apontar para `/dashboard`.
2. `php artisan test` passa em 100% de toda a suíte.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem nenhuma violação.
4. `npm run build` compila com zero erros.

# Phase 18: Otimização de Entrada, Fluxo de Login, Remoção de Autofoco Mobile e Blindagem PWA
## Tasks
1. **Redirecionamento da Rota Raiz (`routes/web.php`):**
   - Na rota raiz `/`, redirecionar para a `dashboard` caso o usuário esteja autenticado (`auth()->check()`), ou para a rota `login` caso seja um visitante.

2. **Ajuste na Tela de Login (`resources/js/Pages/Auth/Login.vue`):**
   - Garantir link claro, estilizado e acessível para a rota de cadastro (`route('register')`), permitindo novos usuários se registrarem facilmente.

3. **Remoção de Autofoco em Telas Mobile:**
   - Em `resources/js/Pages/Workouts/Edit.vue` e formulários de criação/edição: remover qualquer atributo `autofocus` dos inputs para evitar a abertura automática indesejada do teclado virtual em celulares.

4. **Blindagem PWA no HTML Principal (`resources/views/app.blade.php`):**
   - Adicionar meta tags PWA para Android e iOS:
     * `viewport-fit=cover` na meta tag viewport (para suporte correto ao entalhe/notch do iPhone).
     * `apple-mobile-web-app-capable` e `mobile-web-app-capable` com valor `yes`.
     * `apple-mobile-web-app-status-bar-style` como `black-translucent`.
     * Link para `/manifest.json` e script de registro do Service Worker `/sw.js`.

## Acceptance Criteria
1. Teste de Feature valida que a rota raiz `/` redireciona para `/dashboard` quando logado e para `/login` quando visitante.
2. `resources/views/app.blade.php` possui a tag com `viewport-fit=cover` e registro do service worker.
3. `php artisan test` passa em 100% de toda a suíte.
4. `npm run build` compila sem erros.

# Phase 19: Consultas de IA Assíncronas, Degradação Graciosa e Desduplicação de Timers
## Tasks
1. **Abertura Instantânea da Sessão de Treino (`WorkoutSessions/Active.vue`):**
   - No `WorkoutSessionController@show`: REMOVER qualquer chamada síncrona que bloqueie o carregamento da sessão de treino. A tela deve carregar instantaneamente via SQL puro (<100ms).
   - Criar rota e endpoint JSON: `GET /workout-sessions/{session}/overload-suggestions` (name: `workout-sessions.overload-suggestions`).
   - No `WorkoutSessionController@overloadSuggestions`: chamar o `ProgressiveOverloadService` em segundo plano e retornar `JsonResponse` com as sugestões.

2. **Frontend Reativo e Não-Bloqueante (`Active.vue` / `OverloadSuggestionCard.vue`):**
   - Ao abrir a tela de treino ativo, a página carrega 100% funcional imediatamente para o usuário já poder iniciar a Série 1.
   - No card do exercício, exibir um estado discreto de carregamento: *"💡 Analisando histórico de cargas..."*.
   - Fazer a requisição assíncrona em segundo plano para `/workout-sessions/{session}/overload-suggestions`.
   - Quando a resposta chegar, atualizar os cards com as sugestões automaticamente e sem piscar a tela.
   - Se a requisição falhar ou der timeout: exibir discretamente *"Mantenha a carga anterior ou progrida conforme se sentir confortável"*, sem travar o treino.

3. **Desduplicação de Timers em `Active.vue`:**
   - No topo/cabeçalho geral da tela: exibir ESTRITAMENTE o cronômetro da duração total do treino (sessão geral).
   - Remover a exibição duplicada do timer do exercício do topo da tela, mantendo o timer da série exclusivamente no card/seção inferior do exercício em execução.

4. **Blindagem Global contra Erros 500 (Degradação Graciosa):**
   - Em `GeminiClient.php`, `WorkoutScannerService.php`, `ProgressiveOverloadService.php` e `DashboardAnalyticsService.php`:
     * Envolver TODAS as chamadas externas em blocos `try / catch (\Throwable $e)` com `Log::warning(...)`.
     * NENHUMA falha de IA (seja timeout, SSL, cota ou resposta malformada) pode estourar `500 Internal Server Error`.
     * No scanner de ficha (`WorkoutScannerController@store`): em caso de falha de leitura, capturar o erro e redirecionar de volta com mensagem flash amigável: *"Não foi possível ler esta imagem. Tente tirar uma foto mais nítida ou aproximada."*.

## Acceptance Criteria
1. O carregamento de `GET /workout-sessions/{session}` responde em <100ms sem realizar chamadas HTTP externas (validado via teste Pest).
2. O endpoint `GET /workout-sessions/{session}/overload-suggestions` retorna JSON com sugestões ou array vazio em caso de falha simulada com `Http::fake()`.
3. No topo de `Active.vue`, apenas o timer da sessão total é exibido.
4. Teste em `tests/Feature/AiResilienceTest.php` valida que simulação de timeout na IA não gera erro 500 e degrada com mensagem amigável.
5. `php artisan test` passa em 100% de toda a suíte.
6. `npm run build` compila com zero erros.

# Phase 20: Arquitetura AiManager, Provedores Pluggáveis (Driver Pattern) e Auto-Failover
## Tasks
1. **Contrato e Camada de Abstração (app/Services/AI/):**
   - Criar a interface `Contracts/AiDriverInterface.php` definindo os contratos:
     * `generateStructured(string $prompt, ?string $systemInstruction = null): array`
     * `analyzeImage(UploadedFile $image, string $prompt, ?string $systemInstruction = null): array`
   - Criar a classe `AiManager.php` gerenciando os drivers configurados em `config/services.php`:
     * Suporte a múltiplos drivers: `GeminiDriver` e `ClaudeDriver` (ou OpenAI/Groq).
     * Leitura da variável `AI_DEFAULT_DRIVER` no `.env` (padrão: `gemini`).
     * Mecanismo de **Auto-Failover**: se o driver primário falhar ou der timeout, acionar automaticamente o driver secundário configurado antes de degradar.

2. **Implementação dos Drivers:**
   - Criar `Drivers/GeminiDriver.php` encapsulando a lógica de conexão com o Google Gemini.
   - Criar `Drivers/ClaudeDriver.php` encapsulando a lógica de conexão via API da Anthropic.

3. **Refatoração dos Serviços de Domínio:**
   - Refatorar `WorkoutScannerService`, `ProgressiveOverloadService` e `DashboardAnalyticsService` para injetarem e usarem estritamente o `AiManager` (eliminando qualquer dependência direta de classes proprietárias).

## Acceptance Criteria
1. O `AiManager` resolve o driver correto a partir da variável `AI_DEFAULT_DRIVER`.
2. Teste em `tests/Feature/AiManagerTest.php` valida a troca dinâmica de driver e o mecanismo de fallback automático quando o primário simula falha.
3. `php artisan test` passa em 100% de toda a suíte.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
5. `npm run build` compila com zero erros.

# Phase 21: Roteamento Semântico de Modelos por Tarefa (Task-Based AI Routing)
## Tasks
1. **Enum de Tarefas de IA (`app/Enums/AiTask.php`):**
   - Criar o enum `AiTask` com os casos `Vision` e `FastText`.
2. **Refatoração do `AiManager` e Drivers (`app/Services/AI/`):**
   - Atualizar `AiDriverInterface`, `GeminiDriver` e `ClaudeDriver` para aceitarem `?AiTask $task = null`.
   - Atualizar `config/services.php` com os modelos padrão de cada tarefa e provedor.
   - No `AiManager`: resolver dinamicamente o modelo correspondente à tarefa e driver ativo com auto-failover seguro.
3. **Consumo nos Serviços:**
   - `WorkoutScannerService`: chamar `aiManager->analyzeImage(..., task: AiTask::Vision)`.
   - `ProgressiveOverloadService` e `DashboardAnalyticsService`: chamar `aiManager->generateStructured(..., task: AiTask::FastText)`.

## Acceptance Criteria
1. `tests/Feature/AiTaskRoutingTest.php` passa 100% validando o roteamento por `AiTask`.
2. `php artisan test` passa em 100% de toda a suíte.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem nenhuma violação.
4. `npm run build` compila com zero erros.

# Phase 22: Sessão Ativa em Segundo Plano e Indicador Global de Treino
## Tasks
1. **Compartilhamento de Sessão Ativa no Inertia:**
   - Em `app/Http/Middleware/HandleInertiaRequests.php`: compartilhar globalmente a prop `activeWorkoutSession` (contendo `id`, `workout_name`, `started_at`) caso o usuário autenticado possua uma sessão em andamento (`completed_at is null`).

2. **Navegação Livre e Menu Acessível em `Active.vue`:**
   - Garantir que a barra superior com o botão hambúrguer do menu continue acessível na tela de treino ativo, permitindo ao usuário navegar livremente para outras páginas sem interromper a sessão.

3. **Componente Indicador Global (`resources/js/Components/Navigation/ActiveSessionBanner.vue`):**
   - Criar o componente de indicador pulsante no topo/header do `AuthenticatedLayout.vue`.
   - Quando `activeWorkoutSession` existir e o usuário NÃO estiver na rota `workout-sessions.show`, exibir o banner/pílula com brilho roxo no canto superior direito: "🔥 Treino em andamento" com o tempo decorrido ao vivo.
   - Clicar no indicador navega imediatamente para `route('workout-sessions.show', activeWorkoutSession.id)`.

## Acceptance Criteria
1. `tests/Feature/ActiveSessionGlobalBannerTest.php` valida que a prop `activeWorkoutSession` é compartilhada quando há sessão aberta e ausente quando todas estão finalizadas.
2. `php artisan test` passa em 100% de toda a suíte.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
4. `npm run build` compila com zero erros.

# Phase 23: Encerramento Limpo de Sessões Fantasmas e Sincronização do Banner Global
## Tasks
1. **Limpeza e Encerramento Atômico de Sessões (`WorkoutSessionService.php`):**
   - No método `finishSession(WorkoutSession $session)`:
     * Carimbar `completed_at = now()` na sessão atual.
     * Encerrar qualquer outra sessão órfã pendente do mesmo usuário que tenha ficado com `completed_at is null` em testes passados.
   - No método `startSession(User $user, Workout $workout)`:
     * Se o usuário já possuir alguma sessão aberta com `completed_at is null`, encerrá-la automaticamente antes de abrir a nova sessão.
   - Criar comando Artisan/Migration ou limpeza no service para fechar todas as sessões antigas atualmente abertas no banco.

2. **Reatividade no Frontend (`ActiveSessionBanner.vue` e `AuthenticatedLayout.vue`):**
   - Garantir que `ActiveSessionBanner.vue` seja condicional estrito (`v-if="activeWorkoutSession"` lido reativamente das props do Inertia).
   - Ao receber o redirect de finalização com sucesso, o banner deve desaparecer instantaneamente da barra superior sem deixar resíduos de timer.

## Acceptance Criteria
1. Teste de Feature `tests/Feature/ActiveSessionCleanupTest.php` valida que finalizar um treino remove completamente a prop `activeWorkoutSession` e que iniciar um novo fecha sessões anteriores.
2. `php artisan test` passa em 100% de toda a suíte.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem nenhuma violação.
4. `npm run build` compila com zero erros.

# Phase 24: Otimização Extrema de Latência de IA, Timeouts Curtos e Driver Groq Ultrarrápido
## Tasks
1. **Redução Drástica de Timeouts no `AiManager` e Drivers:**
   - Em `GeminiDriver.php` e `ClaudeDriver.php`:
     * Para tarefas `AiTask::FastText` (Sobrecarga e Dashboard): configurar timeout máximo estrito de **6 segundos**.
     * Para tarefas `AiTask::Vision` (Scanner de fotos): configurar timeout máximo de **30 segundos**.
   - No `ClaudeDriver.php`: adicionar `Http::withoutVerifying()` para eliminar o erro cURL 60 no Windows.

2. **Garantia de Resposta 200 com Fallback Suave:**
   - No `WorkoutSessionController@overloadSuggestions`: capturar qualquer falha ou timeout e retornar SEMPRE status HTTP 200 com JSON estruturado `{"status": "fallback", "recommendations": []}` — NUNCA estourar status 500 no console do navegador.

3. **Driver Groq Opcional de Ultra-Baixa Latência (app/Services/AI/Drivers/GroqDriver.php):**
   - Criar o `GroqDriver` (usando a API gratuita do Groq compatível com OpenAI `https://api.groq.com/openai/v1/chat/completions` e modelo `llama-3.3-70b-versatile`).
   - Adicionar `groq` nas opções de drivers em `config/services.php` e suporte no `AiManager`.

## Acceptance Criteria
1. O endpoint `/workout-sessions/{session}/overload-suggestions` retorna status HTTP 200 em caso de simulação de timeout ou falha de provedores.
2. Os timeouts de `FastText` não ultrapassam 6 segundos por tentativa.
3. `tests/Feature/AiResilienceTest.php` valida que falhas de rede não produzem status 500 na sessão de treino.
4. `php artisan test` passa em 100% de toda a suíte.
5. `npm run build` compila sem erros.

# Phase 25: Calibração de Imagem 2K e Prompt Especializado em Cupons Térmicos
## Tasks
1. **Calibração de Resolução no Frontend (`ScanWorkoutModal.vue`):**
   - Ajustar a compressão Canvas para uma resolução máxima de **2048px (2K)** na maior dimensão e qualidade JPEG em **88%** com `imageSmoothingQuality = 'high'`.
   - Isso preserva a nitidez dos caracteres em matriz de pontos de cupons térmicos sem estourar a memória do PHP (~500KB de arquivo).

2. **Especialização do Prompt de Leitura (`WorkoutScannerService.php`):**
   - Atualizar o prompt da IA com diretrizes específicas para comprovantes térmicos:
     * Identificação de abreviações comuns (`S: 3` = 3 séries, `Rept: 10-12` = 12 reps).
     * Mapeamento de variações de pegada (`P. PRONADA`, `P. SUPINADA`, etc.) salvando os detalhes no campo `notes` do exercício.
     * Extração do nome da divisão ou treino a partir do cabeçalho da ficha (ex: "Treino 1 - Costas e Ombros").

## Acceptance Criteria
1. `tests/Feature/WorkoutScannerTest.php` passa em 100%.
2. `php artisan test` passa em 100% de toda a suíte.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
4. `npm run build` compila com zero erros.

# Phase 26: Login Persistente, Toasts com Gesto de Deslizar e Auto-Preenchimento Sequencial de Séries
## Tasks
1. **Sessão Persistente de Longa Duração:**
   - Em `config/session.php` e `.env`: definir `lifetime` para 525600 minutos (1 ano) por padrão.
   - Em `resources/js/Pages/Auth/Login.vue`: manter a opção "Lembrar de mim" (`remember`) ativada como `true` por padrão no formulário para evitar deslogar no celular.

2. **Toasts Deslizáveis (Swipe to Dismiss) e Auto-Fechamento:**
   - No componente de notificação global / flash message:
     * Implementar gestos de toque (`touchstart`, `touchmove`, `touchend`) permitindo arrastar o toast horizontalmente para descartá-lo instantaneamente.
     * Incluir botão visível de fechar (`✕`).
     * Adicionar temporizador de auto-fechamento após 3.5 segundos para liberar os cliques no menu e no topo da tela.

3. **Auto-Preenchimento Inteligente de Séries:**
   - Em `resources/js/Pages/WorkoutSessions/Active.vue`:
     * Série 1: inicializar os inputs com a carga e repetições da última série realizada no treino anterior do mesmo exercício (se houver histórico). Se for treino novo, inicializar com 0kg e a meta de repetições.
     * Séries 2, 3 e seguintes: ao concluir a série anterior, pré-preencher automaticamente os inputs da próxima série com os valores reais (peso e repetições) registrados na série imediatamente anterior da sessão atual.

## Acceptance Criteria
1. `tests/Feature/PersistentSessionTest.php` valida que a sessão é configurada para longa duração.
2. Em `Active.vue`, as séries seguintes herdam os dados de peso e reps da série anterior concluída.
3. `php artisan test` passa em 100% de toda a suíte.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
5. `npm run build` compila com zero erros.

# Phase 27: Cronômetros Resilientes a Segundo Plano, Wake Lock de Tela e Áudio em Hardware Clock
## Tasks
1. **Timers Baseados em Timestamp Delta:**
   - Em `resources/js/Pages/WorkoutSessions/Active.vue` e componentes de cronômetro:
     * Eliminar contadores baseados exclusivamente em `setInterval` incremental.
     * Armazenar o `startTimestamp` (`Date.now()`) e recalcular o tempo decorrido dinamicamente.
     * No cronômetro de descanso, calcular o tempo restante contra um `targetEndTimestamp`.
     * Ouvir os eventos `document.addEventListener('visibilitychange')` e `window.addEventListener('focus')` para atualizar a interface imediatamente ao ligar a tela do aparelho.

2. **Prevenção de Bloqueio de Tela no Banco (Screen Wake Lock API):**
   - Utilizar a API `navigator.wakeLock.request('screen')` durante a contagem regressiva de descanso ou sessão ativa quando a tela estiver visível, evitando que o aparelho apague sozinho por inatividade.

3. **Alerta Sonoro via Relógio de Hardware (Web Audio API):**
   - No helper de áudio, agendar a execução do bipe sonoro diretamente na linha do tempo de hardware da Web Audio API (`audioContext.currentTime + tempoRestante`).
   - O bipe deve ser acionado pontualmente no segundo zero mesmo se a tela for bloqueada no botão físico ou o app for minimizado.

## Acceptance Criteria
1. Os cronômetros utilizam deltas de `Date.now()` ao retornar da visibilidade oculta.
2. O alerta sonoro agenda o nó de áudio no `AudioContext` sem depender de execução contínua da thread principal de UI.
3. `php artisan test` passa em 100% de toda a suíte.
4. `npm run build` compila com zero erros.

# Phase 28: Modo Offline-First com Cache Local e Fila de Sincronização (IndexedDB)
## Tasks
1. **Armazenamento Offline com IndexedDB:**
   - Criar módulo `resources/js/Utils/offlineStorage.js` para persistir localmente no dispositivo:
     * Programas de treino e fichas ativas (`cached_workouts`).
     * Catálogo mestre de exercícios (`cached_exercises`).
     * Histórico de cargas recentes (`cached_last_logs`).
   - Permitir navegação e abertura de treinos existentes mesmo sem conexão com a internet (`!navigator.onLine`).

2. **Fila de Sincronização (Outbox Queue):**
   - Permitir iniciar sessão, registrar séries e finalizar treino em modo offline.
   - Gravar as mutações offline na fila de espera do IndexedDB (`sync_queue`).
   - Criar rota e endpoint em lote `POST /api/sync/workout-sessions` no Laravel para processar a fila recebida.
   - Adicionar listener para `window.addEventListener('online')` para sincronizar automaticamente as ações pendentes assim que a conexão for restabelecida.

## Acceptance Criteria
1. Teste de Feature valida que `POST /api/sync/workout-sessions` processa e persiste as sessões e séries criadas offline na ordem cronológica correta.
2. O aplicativo carrega os treinos cacheados no IndexedDB quando desconectado da rede.
3. `php artisan test` passa em 100% de toda a suíte.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
5. `npm run build` compila com zero erros.

# Phase 29: Correção de Áudio com Tela Apagada e Service Worker para Inertia 100% Offline
## Tasks
1. **Sessão de Áudio Contínua para Manter o Canal Ativo no Bolso:**
   - Em `resources/js/Utils/audioAlert.js` (ou componente de áudio):
     * Ao iniciar a contagem regressiva de descanso, iniciar a reprodução em loop de uma faixa de áudio de silêncio contínuo através de um elemento HTML5 `<audio loop>` com suporte à Media Session API.
     * Isso impede que o Android e o iOS congelem o processador de som quando a tela do celular for apagada no botão físico ou o app for minimizado.
     * Ao atingir o segundo zero, disparar o apito sonoro de alerta no mesmo canal de áudio que já está acordado e, em seguida, pausar o áudio silencioso.

2. **Interceptação de Requisições do Inertia no Service Worker (`public/sw.js`):**
   - Atualizar o Service Worker para tratar requisições do Inertia (`headers.get('X-Inertia') === 'true'`):
     * Estratégia Network-First: quando online, salvar uma cópia em cache das respostas JSON das rotas acessadas (`/dashboard`, `/workouts`, `/programs`, `/workouts/{id}`).
     * Se a rede falhar ou estiver sem internet (`!navigator.onLine`), o Service Worker intercepta o pedido do Inertia e entrega o JSON em cache, permitindo navegar pelos menus sem erro de conexão.
     * Para navegação de documento completo (F5 sem internet), entregar o HTML base do App Shell em cache.

3. **Cache de Assets Estáticos:**
   - Garantir que todos os pacotes compilados em `/build/assets/*` fiquem armazenados em cache permanente para que a interface carregue sem conexão.

## Acceptance Criteria
1. `public/sw.js` intercepta requisições com cabeçalho `X-Inertia` e entrega fallback de cache em falhas de rede.
2. O alerta sonoro utiliza sessão de áudio contínua para tocar no segundo zero mesmo com a tela bloqueada.
3. `php artisan test` passa em 100% de toda a suíte.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
5. `npm run build` compila com zero erros.

# Phase 30: Correção de PWA, Clone de Proxies no IndexedDB e Interceptação do Inertia
## Tasks
1. **Correção do DataCloneError no IndexedDB (`resources/js/Utils/offlineStorage.js`):**
   - Ao salvar qualquer dado no IndexedDB, o sistema não pode enviar objetos reativos (Proxies) do Vue.
   - OBRIGATORIAMENTE, antes de executar qualquer `put` ou `add` no banco de dados local, os dados devem ser convertidos para objetos JS puros utilizando `JSON.parse(JSON.stringify(data))` (ou `toRaw()`), garantindo que sejam serializáveis pelo algoritmo do navegador.

2. **Correção da Interceptação de Rede do Inertia (`public/sw.js`):**
   - O Service Worker deve explicitamente interceptar as requisições `fetch` geradas pelo Inertia.
   - Adicione lógica no `sw.js` no evento `fetch` para verificar se a requisição possui o cabeçalho `X-Inertia` ou se é para rotas de GET json.
   - Aplique a estratégia **Network First**: tente realizar o `fetch`. Se der sucesso, clone a resposta e grave no cache.
   - Se ocorrer exceção (offline ou timeout), capture o erro e DEVOLVA a resposta armazenada no cache usando `caches.match(event.request)`. 

## Acceptance Criteria
1. O comando `npm run build` passa sem erros.
2. O código de `offlineStorage.js` utiliza conversão para objeto puro antes das operações de IndexedDB.
3. `php artisan test` passa em 100%.

# Phase 31: Eager Caching (Cache Warming) para Disponibilidade 100% Offline
## Tasks
1. **Remoção de Mensagens de Bloqueio Offline:**
   - Remover qualquer bloqueio ou mensagem impeditiva (ex: "Este treino ainda não foi aberto neste dispositivo") nas views que tentam prevenir acesso offline a rotas não cacheadas. Confiar na estratégia de Cache Warming e Fallback.

2. **Cache Warming Silencioso em Segundo Plano (`resources/js/Utils/cacheWarming.js`):**
   - Criar um utilitário que busca a lista de todos os treinos ativos do usuário (através de um endpoint leve ou mapeando os dados globais disponíveis).
   - O utilitário deve iterar sobre as rotas de visualização de cada treino (`/workouts/{id}`) e realizar uma requisição `fetch` silenciosa com o header `X-Inertia: true`.
   - Essas requisições devem ocorrer em prioridade baixa (usando `requestIdleCallback` ou um `setTimeout` de alguns segundos após o carregamento) para não competir com a performance inicial da tela.

3. **Gatilho de Sincronização de Cache (`resources/js/Layouts/AuthenticatedLayout.vue`):**
   - No `onMounted` do layout principal, se `navigator.onLine` for verdadeiro, disparar o utilitário de Cache Warming.
   - Isso garantirá que o Service Worker intercepte essas chamadas silenciosas e popule o cache do aparelho com o JSON de todas as fichas.

## Acceptance Criteria
1. O comando `npm run build` passa com zero erros.
2. O Cache Warming é invocado corretamente e existe no projeto.
3. `php artisan test` passa em 100%.

# Phase 32: Paridade Visual 100% (Offline = Online) e Correção de Roteamento SW
## Tasks
1. **Paridade UI Absoluta e Erradicação de Poluição Visual:**
   - Inspecionar rigorosamente `resources/js/Pages/WorkoutSessions/Active.vue` e `resources/js/Pages/Workouts/Index.vue`.
   - REMOVER QUALQUER condicional do tipo `v-if="!isOnline"` que altere o design, insira mensagens amareladas, banners ou mude textos de botões (como "Finalizar Treino (offline)").
   - A tela offline DEVE SER **EXATAMENTE IGUAL** à tela online. Os mesmos botões, as mesmas cores, a mesma posição. A transição para offline deve ser imperceptível para o usuário.
   - Remover alertas de "Sincronizando..." ou "Sincronizado com sucesso" disparados pelo evento `online`. O processo deve ser 100% invisível (backgroud sync real).

2. **Correção do Interceptador de Rede no Service Worker (`public/sw.js` ou equivalente):**
   - O Service Worker atual está vazando requisições, causando `ERR_INTERNET_DISCONNECTED` (tela branca) ao recarregar a página (F5) ou navegar pelo menu.
   - O evento `fetch` deve ser refatorado para NUNCA permitir falha de rede sem resposta de cache:
     * Assets (`/build/assets/`): Usar `Cache First`.
     * Navegação raiz/HTML (`request.mode === 'navigate'`): Usar `Network First` caindo para o HTML do cache se offline.
     * Requisições Inertia (`request.headers.has('X-Inertia')`): Usar `Network First` caindo para o cache de JSON daquela URL exata.
   - Se a requisição Inertia falhar e não estiver no cache, devolver um JSON válido de fallback (ex: `window.location.reload()`) em vez de deixar a promessa do Axios estourar erro vermelho no console.

## Acceptance Criteria
1. Nenhum arquivo `.vue` no projeto possui textos hardcoded como "offline" ou modificações de classe baseadas no estado de conexão de rede.
2. O comando `npm run build` passa sem erros.
3. `php artisan test` passa em 100% da suíte.

# Phase 33: Arquitetura Local-First, CRUD Offline e Mini-Servidor no Service Worker
## Tasks
1. **Download Inicial do Estado Global (Full Sync):**
   - Criar rota/endpoint `GET /api/sync/state` no Laravel que retorna todo o estado do usuário de uma vez: programas, treinos, relação N:N, catálogo de exercícios e histórico.
   - No frontend, ao fazer login ou carregar a aplicação online, buscar esse endpoint e sobrescrever os dados no IndexedDB (`resources/js/Utils/offlineStorage.js`).

2. **Mini-Servidor no Service Worker (`public/sw.js`):**
   - Refatorar drasticamente a interceptação de requisições `fetch` no SW.
   - **Navegação (GET):** Se o usuário acessar `/programs`, `/workouts` ou a raiz offline, o SW DEVE ler o IndexedDB, montar um payload JSON válido no formato do Inertia (com as props esperadas pela página) e retornar um `new Response(...)` com status 200. O Vue nunca deve saber que a rede caiu.
   - **Mutações Offline (POST/PUT/DELETE):** Interceptar rotas de criação/edição. Se offline, salvar o payload na `sync_queue` do IndexedDB, aplicar a mudança no banco local (ex: inserir na tabela `cached_programs` com um ID temporário UUID) e retornar um redirecionamento 303 forjado ou resposta JSON 200 válida para o Inertia prosseguir sem erro.

3. **Optimistic UI e Sincronização Transparente:**
   - Em telas de listagem (`Programs/Show`, `Workouts/Index`), mesclar os dados vindos como props do Inertia com os dados que estão pendentes na fila local do IndexedDB (para que um programa criado offline apareça imediatamente na tela).
   - O listener de evento `online` do navegador deve processar a fila `sync_queue`, substituindo os IDs temporários pelos IDs reais retornados pelo Laravel, em total silêncio (background sync).

## Acceptance Criteria
1. Com a rede devtools em 'Offline', navegar entre os menus Dashboard, Treinos e Programas carrega as telas imediatamente sem `ERR_INTERNET_DISCONNECTED`.
2. Criar um programa de treino offline não gera erro no console e o novo programa aparece na lista.
3. `php artisan test` passa em 100% de toda a suíte.
4. `npm run build` compila com zero erros.

# Phase 34: Estabilização PWA, Cache Dinâmico de Assets e Deep Clone do IndexedDB
## Tasks
1. **Cache Dinâmico e Resiliente de Assets (`public/sw.js`):**
   - O Service Worker atual não consegue servir os chunks do Vue quando o nome do arquivo muda após o build.
   - Atualizar a estratégia de `fetch` no `sw.js`:
     * Todas as requisições para `/build/assets/` devem usar a estratégia `Stale-While-Revalidate` com cache persistente. Se o arquivo estiver no cache, devolva imediatamente; em segundo plano, tente buscar uma versão nova na rede e atualize o cache.
     * Nenhuma requisição para `/build/assets/` deve retornar `ERR_INTERNET_DISCONNECTED`. Se falhar na rede, o fallback para o cache deve ser obrigatório.

2. **Deep Unproxy Completo no IndexedDB (`resources/js/Utils/offlineStorage.js`):**
   - O método `toPlainObject` atual falhou com `DataCloneError`.
   - Modifique o método para utilizar `toRaw()` do Vue recursivamente OU um parser seguro que garanta a remoção completa dos Handlers de Proxy gerados pelo Vue 3 antes de qualquer operação `put` ou `add` no IndexedDB.

3. **Desacoplamento de Erros Externos (Bypass de Extensões):**
   - Garantir que o Service Worker NÃO quebre o carregamento se extensões do Chrome injetarem scripts ou requisitarem URLs de terceiros (`chrome-extension://`). Ignorar requisições que não pertençam à origem do app no `sw.js`.

## Acceptance Criteria
1. Nenhuma requisição de arquivos compilados em `/build/assets/` gera `ERR_INTERNET_DISCONNECTED` no console quando offline.
2. Nenhuma operação de salvamento de treinos no offlineStorage gera `DataCloneError`.
3. `php artisan test` passa em 100%.
4. `npm run build` compila sem erros.

# Phase 35: Correção de Auth no Sync e Resiliência de Cache Vazio
## Tasks
1. **Correção do Erro 401 nas Rotas de Sync (`routes/web.php`):**
   - MOVER as rotas `GET /api/sync/state` e `POST /api/sync/workout-sessions` (ou qualquer outra rota de sync) que estejam no arquivo `api.php` para o arquivo `routes/web.php`, garantindo que fiquem DENTRO do grupo `Route::middleware('auth')`.
   - Isso garantirá que as requisições de download do estado offline via Axios utilizem a sessão/cookie normal do Inertia, resolvendo o erro 401 Unauthorized.

2. **Resiliência contra Cache Vazio no Service Worker (`sw.js`):**
   - O erro `ERR_INTERNET_DISCONNECTED` está ocorrendo porque, quando o cache está vazio e a rede falha, o `caches.match(request)` retorna `undefined`.
   - Modifique a captura de erro do `fetch` no SW:
     * Se `caches.match()` não encontrar nada, o SW DEVE ESTRITAMENTE retornar um `new Response()` sintético.
     * Se for requisição Inertia (`X-Inertia`), retorne: `new Response(JSON.stringify({}), { status: 200, headers: { 'Content-Type': 'application/json', 'X-Inertia': 'true', 'X-Inertia-Offline-Fallback': 'true' } })`.
     * Se for navegação normal, retorne um HTML de fallback básico ou force reload.
     * NUNCA deixe a Promise ser rejeitada sem resposta.

## Acceptance Criteria
1. Nenhuma requisição `/api/sync/state` retorna 401 Unauthorized.
2. O SW retorna um Response válido mesmo com cache vazio em modo offline.
3. `php artisan test` passa em 100%.
4. `npm run build` compila com zero erros.

# Phase 36: Desacoplamento de Cache, Invalidação Imediata e Autoridade do IndexedDB
## Tasks
1. **Invalidação de Snapshots do Inertia no Service Worker (`sw.js`):**
   - Implementar listener para mensagens do tipo `EVICT_INERTIA_CACHE` no Service Worker.
   - Sempre que uma sessão de treino for finalizada, iniciada ou alterada, o frontend deve enviar essa mensagem para que o SW delete imediatamente as cópias cacheadas de `/dashboard`, `/workouts*` e `/programs*` do `INERTIA_CACHE`.
   - Isso garante que o Service Worker nunca entregue uma resposta JSON antiga com estado de sessão defasado.

2. **Limpeza Atômica de Sessão no IndexedDB (`offlineStorage.js` e `useOfflineSync.js`):**
   - Ao finalizar uma sessão de treino (`finishSession` / `finishWorkout`), remover imediatamente o registro da sessão ativa do IndexedDB local (`active_session`), tanto em execuções online quanto offline.
   - Atualizar o status local do treino correspondente para refletir a conclusão de forma imediata.

3. **Autoridade do Estado Local na Dashboard e Banner (`Dashboard.vue`, `ActiveSessionBanner.vue`, `AuthenticatedLayout.vue`):**
   - Em `ActiveSessionBanner.vue`: antes de renderizar o banner de treino em andamento, verificar reativamente se o IndexedDB confirma a existência de uma sessão ativa. Se o IndexedDB indicar que não há sessão aberta, o banner DEVE ser ocultado, ignorando props antigas que possam ter vindo de cache HTTP.
   - Em `Dashboard.vue`: quando em modo offline, resolver o estado do Card Hero e dos treinos a partir dos dados do IndexedDB local, garantindo que o status exibido seja idêntico à realidade física do aparelho.

## Acceptance Criteria
1. Ao finalizar um treino e navegar para a Dashboard em modo offline, o banner "Treino em andamento" NUNCA é exibido.
2. O Service Worker expurga entradas de páginas do `INERTIA_CACHE` ao receber a mensagem de invalidação.
3. `php artisan test` passa em 100% de toda a suíte.
4. `tests/Architecture/ArchitecturalRulesTest.php` passa sem nenhuma violação.
5. `npm run build` compila com zero erros.

# Phase 37: Resiliência de Sincronização, Validação Idempotente e Segurança Tenant-Scoped
## Tasks
1. **Correção do Contrato e Eliminação do Erro 422 (`SyncWorkoutSessionsRequest.php`):**
   - Adequar as regras de validação para aceitar datas em padrão ISO 8601 emitidas pelo JavaScript (`date_format` flexível ou regra `date`).
   - Permitir `client_session_id` e `client_set_id` como identificadores únicos (UUID string) para suporte a idempotência.
   - Tratar campos numéricos (`weight`, `reps`) garantindo coerção de tipo antes de validar.

2. **Segurança e Escopo de Usuário no Backend (`SyncService.php`):**
   - O processamento do lote deve ocorrer estritamente dentro de `DB::transaction()`.
   - Garantir que cada `workout_id` pertença estritamente ao `$user->id` autenticado (`$user->workouts()->where('id', ...)`). Registros órfãos ou pertencentes a terceiros devem ser rejeitados.
   - Utilizar `updateOrCreate` baseado no `client_session_id` para garantir idempotência total (reenvios acidentais de rede não duplicam treinos nem séries).

3. **Governança da Fila Offline no Frontend (`offlineStorage.js` e `useOfflineSync.js`):**
   - A `sync_queue` do IndexedDB só deve ser esvaziada para os itens que receberem confirmação explícita de sucesso (HTTP 200) do backend.
   - Em caso de erro de validação (422) ou erro de servidor (500), manter os dados na fila local e registrar log no console, impedindo a perda do treino do usuário.

4. **Hidratação do Estado de Auth Offline e Proteção Visual:**
   - Ao sincronizar online, persistir um snapshot mínimo de `auth_user` (`id`, `name`, `email`) no IndexedDB.
   - Em `Sidebar.vue`, `AuthenticatedLayout.vue` e `Show.vue`: aplicar encadeamento opcional (`auth?.user?.name ?? cachedUser?.name ?? 'Atleta'`) e `workout.exercises?.length ?? 0`, impedindo que respostas sintéticas do Service Worker quebrem o motor reativo do Vue.

## Acceptance Criteria
1. Teste de Feature `tests/Feature/SyncSecurityAndIdempotencyTest.php` valida que:
   - O endpoint aceita o payload com datas ISO e UUIDs sem erro 422.
   - O reenvio do mesmo payload não gera duplicações no banco de dados.
   - Tentativa de sincronizar treinos vinculados a outro usuário é bloqueada.
2. `php artisan test` passa em 100% de toda a suíte.
3. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
4. `npm run build` compila com zero erros.
# Phase 38: Offline-First Definitivo — Sessão Contínua, Sincronização Resiliente e a Causa Raiz Real do "Só Atualiza Quando a Internet Volta"

Investigação longa, em várias rodadas, disparada por relatos recorrentes de que navegar pelo menu, criar e excluir fichas offline "não funcionavam" ou "só apareciam quando a internet voltava". Cada etapa abaixo corrigiu um problema real e verificável — a causa raiz definitiva só foi isolada na Etapa 38.7, depois de descartar (por eliminação e reprodução automatizada com navegador real) todas as hipóteses anteriores sobre Service Worker e sincronização.

## Etapa 38.1 — Continuidade Real da Sessão Offline e Props Compartilhadas no Mini-Servidor
### Tasks
1. **Props compartilhadas nas páginas sintetizadas (`resources/pwa/sw.js`):**
   - `buildOfflinePageResponse` passa a injetar em TODA página montada pelo mini-servidor as props que `HandleInertiaRequests` compartilha: `auth.user`, `activeWorkoutSession`, `flash`, `errors` e `userWorkoutIds`, lidas do IndexedDB.
   - Sem elas, qualquer navegação offline chegava ao Vue sem `activeWorkoutSession` — causa raiz do botão flutuante "Treino em andamento" sumir ao navegar sem internet e voltar quando a conexão retornava.

2. **Rota offline da sessão ativa (`resources/pwa/sw.js`):**
   - Nova rota `/workout-sessions/{id}` no mini-servidor, montando `WorkoutSessions/Active` a partir do IndexedDB (ficha cacheada + séries da fila de sincronização + `last logs`).
   - Prioridade do mini-servidor sobre o cache HTTP quando `navigator.onLine` é falso, tanto para navegação de documento quanto para requisições Inertia.
   - Navegação de documento completo reescreve o `data-page` do app shell em cache, em vez de rehidratar o Inertia com o estado congelado de outra página.

3. **Retomada de sessão iniciada offline (`Workouts/Show.vue`, `offlineStorage.js`):**
   - Sessões nascidas offline não têm rota própria no servidor; ao montar a página da ficha, se o IndexedDB indicar uma sessão offline aberta para ELA, a sessão é restaurada com o progresso intacto.
   - `active_session` passa a carregar `client_session_id`, `offline_only`, `workout_id` e o carimbo das séries concluídas (`set_logs`), reconstruíveis também a partir da própria `sync_queue`.

4. **Autoridade do IndexedDB no indicador global (`ActiveSessionBanner.vue`):**
   - Offline, o registro local é a fonte de verdade; online, a prop do servidor, com o registro local como rede de segurança para quando `navigator.onLine` mente.
   - O destino do link é calculado: rota real da sessão quando ela existe no servidor, página da ficha quando ela só existe neste aparelho.
   - O registro local é relido a cada navegação (`router.on('navigate')`), não só quando a prop do Inertia muda.

5. **Mesclagem não-destrutiva do cache de fichas (`offlineStorage.js`):**
   - `cacheWorkout()` passa a mesclar com o registro existente. A tela de sessão ativa, que só conhece `{id, name, description}` + exercícios, apagava `muscle_groups`/`is_active`/`days_of_week` do treino no IndexedDB — quebrando o render offline de `Workouts/Show` (só cabeçalho e botão "Voltar") e sumindo com o treino da Dashboard offline.

6. **Continuidade de sessão entre lotes de sincronização (`SyncService.php`, `SyncWorkoutSessionsRequest.php`):**
   - `log_set`/`finish_session` resolvem a sessão em três camadas: lote atual → `client_uuid` no banco → `session_id` (ou o rótulo `session-<id>`).
   - Elimina o 422 "Nenhuma sessão de treino correspondente foi encontrada" quando a conexão volta no meio do treino, a fila é esvaziada e o lote seguinte chega sem `start_session`.
   - `workout_id` aceita a referência `tmp_<client_uuid>` de uma ficha criada offline; `flushQueue` sincroniza mutações de recurso ANTES das sessões para que essa referência sempre exista.

7. **Correções de interface e de rede:**
   - `Sidebar.vue`: backdrop acima da tela de sessão ativa (`z-[80]`/`z-[90]`), que por ser `fixed inset-0 z-50` interceptava o clique "fora do menu" e deixava o drawer preso aberto.
   - `bootstrap.js`: cooldown no reload disparado pelo fallback offline, impedindo laço infinito.
   - Chamadas JSON que não são navegação Inertia (`/api/sync/state`, sugestões de sobrecarga) passam a ser rede-e-só-rede: nunca servidas do cache (uma foto antiga sobrescreveria o IndexedDB) e nunca respondidas com o JSON de fallback.
   - Volume total da sessão calculado sobre uma cópia local das séries, para não ficar congelado em "0 kg" durante um treino inteiro sem internet.

### Acceptance Criteria
1. Iniciar treino online, desligar a internet e navegar pelo app: o botão "Treino em andamento" permanece visível e leva de volta à sessão.
2. Iniciar treino offline e navegar pelo app: o botão aparece e leva de volta à sessão, com todas as séries já registradas.
3. Abrir qualquer ficha offline exibe a lista completa de exercícios, inclusive fichas já treinadas neste aparelho.
4. Menu lateral fecha ao clicar fora dele durante uma sessão de treino, com ou sem internet.
5. Reconexão no meio do treino sincroniza sem 422 e sem duplicar sessões.
6. `php artisan test` passa em 100% de toda a suíte.
7. `npm run build` compila com zero erros.

## Etapa 38.2 — Robustez do CRUD Offline de Fichas e Reconciliação de Versão do Service Worker
### Tasks
1. **Rotas offline ausentes para criar/editar fichas (`resources/pwa/sw.js`):**
   - `/workouts/create` e `/workouts/{id}/edit` não tinham entrada no mini-servidor — offline, essas navegações caíam no JSON de fallback (ou na página cacheada errada), tornando impossível sequer abrir os formulários sem internet.
   - Novas rotas `buildWorkoutCreateProps`/`buildWorkoutEditProps`, lendo o catálogo de exercícios cacheado.

2. **Redirect pós-mutação apontando para o próprio formulário (`resources/pwa/sw.js`):**
   - Sem `return_to` explícito, o fallback usava `request.referrer` — que para um POST/PUT disparado DENTRO de `/workouts/create` ou `/workouts/{id}/edit` é a própria página do formulário, não o destino correto.
   - `resolveMutationRedirectTarget()` calcula o destino "natural" de cada tipo (`create_workout` → `/workouts` ou `/programs/{id}`; `update_workout` → `/workouts/{id}`; `create_program` → `/programs`) ANTES do referrer, espelhando o que os controllers reais fazem online.

3. **Reconciliação de versão entre página e Service Worker (`resources/views/app.blade.php`):**
   - `skipWaiting()`+`clients.claim()` fazem o novo Service Worker assumir o controle quase imediatamente, mas isso não recarrega o JavaScript da página já carregada — uma aba aberta há tempo continuava rodando um bundle Vue/Inertia desatualizado enquanto já falava com um Service Worker novo, fazendo qualquer correção de bug parecer "não funcionou" até um F5 manual.
   - Novo listener `controllerchange` recarrega a página automaticamente (uma única vez) assim que um novo Service Worker assume.

4. **Blindagem contra falhas silenciosas (`resources/pwa/sw.js`):**
   - Uma exceção dentro do processamento de uma mutação offline, ou da montagem de uma página pelo mini-servidor, rejeitava a Promise de `event.respondWith()` — o navegador reporta isso como erro de rede genérico, indistinguível de "não funcionou" e sem nenhuma pista no console.
   - `handleOfflineMutation`/`safeBuildOfflinePageResponse` agora envolvem o processamento real em `try/catch`, logam `[SW] Falha ao ...` com o erro completo, e degradam para uma resposta seguem (redirect para `/dashboard`, ou `null` para a cadeia de fallback normal) em vez de deixar a falha escapar sem diagnóstico.

### Acceptance Criteria
1. Abrir "Novo Treino" e "Editar Treino" offline exibe o formulário normalmente, usando o catálogo de exercícios cacheado.
2. Criar, editar ou excluir uma ficha offline redireciona para a mesma página que o fluxo online usaria (nunca de volta para o próprio formulário).
3. Uma aba aberta antes de um deploy do Service Worker se atualiza sozinha (reload automático) assim que a nova versão assume o controle.
4. Qualquer falha inesperada no processamento offline aparece no console como `[SW] Falha ao ...`, nunca como um erro de rede genérico sem contexto.
5. `php artisan test` passa em 100% de toda a suíte.
6. `npm run build` compila com zero erros.

## Etapa 38.3 — Continuidade de Mutações de Recurso Entre Lotes e Isolamento de Falhas na Fila de Sincronização
### Tasks
1. **Fallback por `client_uuid` em `resolveWorkout`/`resolveProgram` (`SyncService.php`):**
   - As duas só resolviam uma referência `tmp_<uuid>` dentro do mapa em memória do lote ATUAL — sem olhar o banco. Criar uma ficha/programa offline e, antes do próximo full sync trocar o ID temporário pelo real, mexer nela de novo (editar, arquivar, excluir, anexar a um programa) é o uso normal, não um caso raro; se essas duas ações fossem enviadas em requisições HTTP separadas (comum com conexão instável), a segunda travava para sempre com "Nenhum treino/programa correspondente foi encontrado", mesmo a entidade já existindo de verdade no servidor.
   - Mesmo padrão já corrigido para sessões na Phase 38 (`resolveSession`), agora replicado aqui: antes de desistir, consulta `Workout`/`WorkoutProgram` por `client_uuid` pertencente ao usuário.

2. **Isolamento de falhas por ação, não por lote inteiro (`SyncService.php`):**
   - `processResourceMutations`/`processWorkoutSessionActions` processavam TODO o lote dentro de uma única transação que abortava por completo (`ValidationException` → 422) se UMA ÚNICA ação não pudesse ser resolvida — inclusive uma ação antiga, permanentemente presa na fila local desde uma sessão de testes anterior. Isso bloqueava para sempre TODAS as outras ações do mesmo lote, incluindo criações novas e perfeitamente válidas enfileiradas depois dela — a causa raiz de "não consigo criar nem excluir treinos".
   - Cada ação agora roda em seu próprio `try/catch` dentro do loop: uma falha é logada (`Log::warning`) e a ação simplesmente fica de fora de `results` (permanece na fila do dispositivo para nova tentativa), mas o processamento CONTINUA para as ações seguintes. A resposta é sempre 200, com `results` podendo ser um subconjunto das ações enviadas — contrato que o frontend (`useOfflineSync.js`) já respeitava desde a Phase 37 (só remove da fila os uuids presentes em `results`).
   - 422 continua reservado para falhas estruturais genuínas de payload, validadas em `SyncWorkoutSessionsRequest`/`SyncResourceMutationsRequest` (tipos errados, campos obrigatórios ausentes, posse de `workout_id`/`session_id` verificável antecipadamente) — nunca mais para uma referência `tmp_` que só o processamento sabe resolver.

### Acceptance Criteria
1. Criar uma ficha offline e, antes do próximo full sync, editá-la/excluí-la/anexá-la a um programa — mesmo que essas ações sincronizem em requisições HTTP separadas — nunca resulta em 422.
2. Uma ação antiga e permanentemente irresolúvel na fila local nunca mais impede outras ações (incluindo criações novas) do mesmo lote de sincronizar.
3. `php artisan test` passa em 100% de toda a suíte.
4. `npm run build` compila com zero erros.

## Etapa 38.4 — `navigator.onLine` Não É Confiável Sob DevTools Network=Offline — Prioridade Corrigida e Fallback do Ciclo de Vida da Sessão
### Tasks
1. **Causa raiz identificada via investigação ao vivo (não apenas leitura de código):** o modo "Offline" da aba **Network** do DevTools (o jeito mais comum de testar isso) bloqueia requisições de verdade, mas **nunca atualiza `navigator.onLine`** — essa flag só reflete a interface de rede do sistema operacional. Confirmado passo a passo com o usuário (worker ativo, v8 rodando, aba controlada, "Bypass for network" desmarcado) antes de reproduzir o bug via simulação executável do `sw.js` em Node (IndexedDB/Cache Storage falsos, fiéis à API real).

2. **Prioridade de fetch corrigida (`resources/pwa/sw.js`):**
   - `networkFirst()`/`handleNavigationRequest`/`handleInertiaRequest` decidiam se o mini-servidor tinha prioridade sobre o cache HTTP checando `!self.navigator.onLine` — sempre `false` sob DevTools Network=Offline, então o Service Worker sempre tentava a rede primeiro, ela falhava de verdade, e a resposta CACHEADA (desatualizada) vencia antes mesmo de o mini-servidor ser consultado.
   - Nova função `networkFirstWithOfflineSynthesis()`: ao falhar de verdade a busca na rede (independente do que `navigator.onLine` diga), tenta reconstruir a página a partir do IndexedDB ANTES de recorrer ao cache HTTP desatualizado.
   - Era exatamente isso que fazia uma ficha criada offline não aparecer até a internet realmente voltar, e a navegação entre menus parecer quebrada de forma inconsistente.

3. **Fallback do ciclo de vida da sessão de treino (`resources/pwa/sw.js`):**
   - `Workouts/Show.vue`/`WorkoutSessions/Active.vue` decidem se enfileiram a ação diretamente ou mandam um POST "normal" com base na MESMA flag não confiável. Sob DevTools Network=Offline, essas telas erram a decisão e mandam um POST que a rede rejeita de verdade — para `/workouts/{id}/start`, `/workout-sessions/{id}/sets` e `/workout-sessions/{id}/finish`, nenhuma delas tinha entrada em `MUTATION_ROUTES`, então o erro de rede cru escapava direto até o XHR (`net::ERR_FAILED`), indistinguível de "o app offline não funciona nada".
   - Três novas rotas de mutação (`start_session_fallback`, `log_set_fallback`, `finish_session_fallback`) aplicam exatamente o mesmo efeito que o caminho "offline" já aplicaria — mesma fila (`sync.workout-sessions`), mesmo `active_session` local, mesmo redirect — reaproveitando a rota `/workout-sessions/{id}` já sintetizável desde a Phase 38.
   - Validado via simulação executável ponta a ponta: registrar uma série e finalizar um treino através dessas rotas de fallback persiste corretamente e é visível ao reabrir a sessão, mesmo com a rede genuinamente bloqueada e `navigator.onLine` mentindo `true`.

### Acceptance Criteria
1. Testando com DevTools → Network → "Offline" (não apenas Wi-Fi desligado): criar uma ficha aparece IMEDIATAMENTE na lista, sem esperar a internet voltar.
2. Iniciar/registrar série/finalizar um treino funciona mesmo quando o front-end erra a decisão "estou online?" e manda um POST que a rede rejeita de verdade.
3. Navegação entre menus nunca produz um erro de rede cru (`net::ERR_FAILED`/XHR onerror), independente do método usado para simular offline.
4. `php artisan test` passa em 100% de toda a suíte.
5. `npm run build` compila com zero erros.

## Etapa 38.5 — A Página Que Registra o Service Worker Pela Primeira Vez Nunca Fica Sob o Controle Dele
### Tasks
1. **Causa raiz real, finalmente isolada por reprodução guiada com o usuário:** um comportamento DOCUMENTADO (e amplamente esquecido) da spec de Service Workers — a página cujo carregamento dispara o PRIMEIRO `register()` de um Service Worker NUNCA fica sob o controle dele, mesmo depois de ativo e mesmo com `clients.claim()` chamado no `activate`. Só a PRÓXIMA navegação de documento completo fica controlada. Isso explica o padrão intermitente relatado ao longo de toda a investigação: "funciona depois que dou Ctrl+F5, mas nunca sozinho" — a aba original nunca teve NENHUMA requisição interceptada offline (nem navegação de menu, nem criar/excluir ficha), porque o `event.respondWith()` do Service Worker nunca chegava a ser convocado para ela.
   - Confirmado com o usuário, passo a passo: worker "activated and is running", versão v8/v9 confirmada no código-fonte servido, aba com `navigator.serviceWorker.controller` retornando um objeto em ALGUNS momentos — mas o sintoma persistindo mesmo assim, até a descrição precisa "se estou no dashboard e desligo a internet, não navega, a não ser que dê Ctrl+F5" revelar o padrão.

2. **Correção (`resources/views/app.blade.php`):**
   - Depois de `register()`, aguarda `navigator.serviceWorker.ready` (garante que existe um worker ATIVO) e então checa `navigator.serviceWorker.controller`. Se ainda `null` — exatamente o caso desta página ser a que disparou o registro —, recarrega a página UMA ÚNICA VEZ (guardado por uma chave em `sessionStorage`, para nunca entrar em loop). Esse reload é seguro incondicionalmente (diferente do reload em `controllerchange`, que precisa checar `navigator.onLine`): ele só acontece logo após a página ter acabado de carregar com sucesso pela rede real.

3. **Robustez complementar (`resources/pwa/sw.js`):**
   - `install` trocou `cache.addAll()` (tudo-ou-nada: um único asset do app shell falhando em baixar derrubava a instalação INTEIRA, deixando a página permanentemente sem controle nenhum) por `Promise.allSettled()` sobre `cache.add()` individual — uma falha isolada nunca mais impede a ativação do worker.

### Acceptance Criteria
1. Uma aba recém-aberta (primeiro carregamento após registrar/atualizar o Service Worker) recarrega sozinha, uma única vez, e a partir daí toda navegação — online ou offline — é interceptada normalmente, sem precisar de F5 manual.
2. Criar ou excluir uma ficha offline reflete IMEDIATAMENTE na tela, não apenas quando a internet volta.
3. `php artisan test` passa em 100% de toda a suíte.
4. `npm run build` compila com zero erros.

## Etapa 38.6 — Causa Raiz Real (Parcial): Vite HMR Não Pode Ser Testado Offline
### Tasks
1. **Causa raiz finalmente confirmada por reprodução empírica** (Puppeteer + Edge real, contra o código-fonte exato deste repositório, não apenas leitura/simulação de código): o usuário roda `npm run dev -- --host 0.0.0.0` E `php artisan serve --host=0.0.0.0` simultaneamente. Nesse modo, `@vite(...)` aponta CADA componente de página (buscado sob demanda via `import.meta.glob`) para o **servidor do Vite, numa origem/porta separada** (não `public/build/assets`). O Service Worker, registrado para a origem do Laravel, **não tem como interceptar** requisições a essa outra origem — é uma limitação estrutural, não um bug de lógica.
   - Testado e confirmado com um navegador real automatizado (Puppeteer/CDP contra Edge): com `php artisan serve` sozinho (sem `npm run dev`), criar e excluir uma ficha offline funciona perfeitamente e reflete na tela IMEDIATAMENTE — inclusive com o servidor genuinamente derrubado (não apenas emulação de rede).
   - O sintoma relatado ("cria mas só aparece quando a internet volta") era exatamente o `import()` dinâmico de `Workouts/Index.vue` (ou qualquer página ainda não carregada nesta sessão) travando ao tentar buscar o servidor do Vite, inatingível offline — mesmo com os dados por trás (IndexedDB, fila de sincronização) já corretos, confirmados como corretos pelo mesmo teste automatizado.
   - Isso explica TODA a série de investigações anteriores (Phases 38-38.4): cada correção no Service Worker e no fluxo de sincronização estava, de fato, correta — mas nenhuma delas jamais poderia consertar um travamento que acontece numa camada (carregamento de componente Vue via HMR) completamente fora do alcance do Service Worker.

2. **Aviso explícito no console (`resources/views/app.blade.php`):**
   - Detecta a presença de `public/hot` (o sinalizador que só existe enquanto `npm run dev` está ativo) e emite um `console.warn` claro explicando a limitação, assim que o Service Worker registra.

3. **Documentação (`CLAUDE.md`):**
   - Novo comando `npm run build && php artisan serve` explicitamente rotulado para testes offline/PWA.
   - Novo parágrafo na seção Frontend explicando a limitação e apontando para este registro de fase.

### Acceptance Criteria
1. Testando com `npm run build && php artisan serve` (sem `npm run dev`), criar/excluir uma ficha offline reflete imediatamente na tela — confirmado por teste automatizado com navegador real, inclusive com o servidor genuinamente inatingível.
2. Rodando com `npm run dev` ativo, o console do navegador exibe um aviso claro sobre a limitação assim que o Service Worker registra.
3. `php artisan test` passa em 100% de toda a suíte.
4. `npm run build` compila com zero erros.

## Etapa 38.7 — Causa Raiz Definitiva: `Workouts/Index.vue` Travava numa Lista Congelada Depois da Primeira Vez Offline
### Tasks
1. **Causa raiz real, finalmente isolada e confirmada por automação de navegador de ponta a ponta** (Puppeteer + Edge real, emulando iPhone/`sec-ch-ua` exatamente como o ambiente do usuário, `php artisan serve --host=0.0.0.0` sem `npm run dev`, servidor genuinamente ou emulado offline): **não era um bug de Service Worker, backend, ou sincronização** — era um bug de reatividade em `resources/js/Pages/Workouts/Index.vue`.
   - `offlineWorkouts` era preenchido, uma única vez, dentro de `onMounted`, quando a página era aberta offline — e a partir daí o computed `displayedWorkouts` **sempre** retornava esse snapshot congelado, ignorando `props.workouts` para sempre, pelo resto da vida daquele componente montado.
   - O Inertia **não remonta** o componente quando o redirect de uma mutação aponta para a MESMA página (`/workouts` → `/workouts`, o caso de criar e excluir uma ficha a partir da própria listagem) — ele só atualiza as props. Como `onMounted` só roda uma vez, a lista ficava congelada no estado de quando a página foi aberta, e SÓ uma navegação para outra página e de volta (algo que o usuário só fazia, coincidentemente, quando testava de novo "depois que a internet voltou") disparava uma nova montagem e finalmente mostrava os dados certos — o padrão intermitente relatado ao longo de TODA a investigação (Phases 38–38.5).
   - Confirmado definitivamente: com o Service Worker funcionando perfeitamente (validado repetidamente por automação real, inclusive com o servidor genuinamente derrubado), criar/excluir uma ficha sempre atualizava `props.workouts` corretamente — só a tela de `Workouts/Index.vue` nunca refletia isso depois da primeira visita offline.

2. **Correção (`resources/js/Pages/Workouts/Index.vue`):**
   - Removido o `offlineWorkouts` (override permanente) por completo — `props.workouts` já É a mesma lista que o mini-servidor do Service Worker monta a partir do IndexedDB (`buildWorkoutsIndexProps`), então sempre pode ser confiada diretamente, online ou offline.
   - `pendingWorkouts` (treinos criados offline, ainda não sincronizados) passou de um `onMounted` único para um `watch(() => props.workouts, ..., { immediate: true })`, que roda de novo toda vez que o Inertia atualizar as props — não só na primeira montagem.

3. **Mesmo padrão corrigido por consistência, mesmo sem o bug fatal** (`Programs/Index.vue`, `Programs/Show.vue`): ambos já confiavam em `props.programs`/`props.program.workouts` diretamente (sem a trava fatal), mas seus `pendingPrograms`/`pendingWorkouts` auxiliares também só recalculavam em `onMounted` — trocado pelo mesmo `watch(..., { immediate: true })`.

### Acceptance Criteria
1. Criar ou excluir uma ficha offline, a partir da própria tela `/workouts`, atualiza a lista imediatamente — confirmado por automação de navegador real, com o servidor genuinamente inatingível.
2. `php artisan test` passa em 100% de toda a suíte.
3. `npm run build` compila com zero erros.

# Phase 39: Safe-Area do iOS e Isolamento por Ação na Sincronização de Sessões

Duas correções independentes, versionadas em commits separados: o layout do PWA instalado no iOS (conteúdo escondido sob o notch/Dynamic Island) e a sincronização offline de sessões de treino (lote inteiro rejeitado com 422 por causa de uma única ação velha na fila, trazendo de volta o banner "Treino em andamento" de um treino já finalizado).

## Etapa 39.1 — Safe-Area do iOS no PWA Instalado
### Tasks
1. **Barras superiores respeitando o notch (`AuthenticatedLayout.vue`, `Sidebar.vue`, `SessionHeader.vue`, `ExerciseSelector.vue`):**
   - Com `viewport-fit=cover` e `apple-mobile-web-app-status-bar-style=black-translucent` (já presentes em `app.blade.php`), o conteúdo é desenhado por baixo da barra de status do iOS — a barra superior mobile, o cabeçalho da Sidebar, o cabeçalho da sessão ativa e o cabeçalho do seletor de exercícios ficavam parcialmente escondidos sob o notch/Dynamic Island.
   - Cada barra ganha `padding-top` com `env(safe-area-inset-top)` (somado ao espaçamento original via `calc()` quando havia um). Nas barras de altura fixa (`h-16`), `box-content` faz o inset SOMAR à altura em vez de espremer o conteúdo dentro dela.

2. **Elementos flutuantes deslocados pelo mesmo inset (`ActiveSessionBanner.vue`, `Toast.vue`):**
   - O banner "Treino em andamento" (`top-20`) e o Toast (`top-4`) passam a usar `top-[calc(...+env(safe-area-inset-top))]`, acompanhando a barra superior que agora é mais alta no iOS.

3. **Altura dinâmica da viewport (`AuthenticatedLayout.vue`):**
   - `min-h-screen` (`100vh`) trocado por `min-h-dvh`: no Safari do iOS, `100vh` ignora a barra de endereço/home indicator e gerava rolagem fantasma.

### Acceptance Criteria
1. No PWA instalado no iPhone, nenhuma barra superior, banner ou Toast fica escondido sob o notch/Dynamic Island.
2. Em aparelhos sem notch e no desktop (`env(safe-area-inset-top)` = 0) o layout permanece idêntico ao anterior.
3. `npm run build` compila com zero erros.

## Etapa 39.2 — Isolamento por Ação Também na Validação: `workout_id` Irresolúvel Não Derruba Mais o Lote
### Tasks
1. **Causa raiz (investigada com os dados reais do banco local):**
   - Cenário: treino iniciado ONLINE (sessão 66), séries e finalização feitas offline, reconexão. `POST /api/sync/workout-sessions` voltava 422 com `actions.3.workout_id` e `actions.6.workout_id` — "A ficha informada para esta sessão de treino não foi encontrada." — e a sessão continuava aberta no servidor, trazendo o banner "Treino em andamento" de volta.
   - Nesse fluxo o front só emite `log_set`/`finish_session` (sem `workout_id`): as ações 3 e 6 eram `start_session` ANTIGOS presos na `sync_queue`, apontando para fichas que não existem mais no banco (ex.: `POST /workouts/1/start` enfileirado pelo `start_session_fallback` do Service Worker; o usuário só possui as fichas 38, 41, 42 e 43). Descartadas as hipóteses de referência `tmp_` (mensagem seria outra) e de `workout_id` ausente no `active_session` local.
   - A Etapa 38.3 isolou falhas por ação dentro do `SyncService`, mas `SyncWorkoutSessionsRequest` continuava verificando existência/posse de `workout_id`, `session_id` e `exercise_id` ANTES do processamento — uma única referência irresolúvel ainda derrubava o lote inteiro com 422.

2. **Request apenas estrutural (`SyncWorkoutSessionsRequest.php`):**
   - Removidas as checagens de existência/posse (`Rule::exists` de `session_id` e `exercise_id`, closure de `workout_id`). `workout_id` só valida o formato (inteiro ou `tmp_<uuid>`). O 422 fica reservado para tipos errados e campos obrigatórios ausentes.

3. **Posse verificada por ação no `SyncService.php`, com segurança tenant-scoped mantida:**
   - `resolveOwnedWorkoutForSession()` resolve o `workout_id` (ID real ou `tmp_<client_uuid>`) estritamente via `$user->workouts()`; `logSet()` aceita apenas exercícios `Exercise::forUser($user)`. Referência alheia, excluída ou órfã é logada (`Log::warning`, com `action_uuid` e `workout_id`) e fica fora de `results` — nunca é aceita, só deixa de derrubar o lote.
   - Cada ação roda em sua própria `DB::transaction()` aninhada (savepoint): uma falha no meio desfaz só o que aquela ação escreveu.

4. **Política de falha definitiva na fila (`useOfflineSync.js`, `offlineStorage.js`):**
   - Cada ação recusada pelo servidor (fora de `results` numa resposta 200, ou apontada por um 422 estrutural via `actions.N.*`) ganha +1 em `attempts` (`registerSyncRejections`). Ao atingir `MAX_SYNC_ATTEMPTS` (5), sai da fila com `console.warn` de falha definitiva. Quedas de rede e 5xx não contam tentativa. `attempts` é removido do payload enviado.

5. **Banner e reconexão (`ActiveSessionBanner.vue`, `useOfflineSync.js`):**
   - Com um `finish_session` pendente na fila para a sessão que o servidor diz estar aberta (`hasPendingFinishForSession`), o estado local vence a prop do servidor e o banner fica oculto, mesmo online. Reavaliado a cada mudança de `pendingCount`.
   - Ao reconectar (`handleReconnect`), a fila é esvaziada ANTES de `router.reload()` recarregar as props do servidor.

6. **Sugestões de sobrecarga offline (`WorkoutSessions/Active.vue`):**
   - `overload-suggestions` não é mais requisitado sem conexão (evita o 504 "Indisponível offline" no console); um `watch(isOnline)` busca as sugestões quando a rede volta.

7. **Service Worker:** nenhuma alteração em `resources/pwa/sw.js`, portanto sem incremento de `CACHE_VERSION` — navegação e requisições Inertia são network-first e os assets do Vite têm hash no nome, então o novo bundle chega aos PWAs instalados na próxima abertura online. Nenhuma migration nova.

### Acceptance Criteria
1. `tests/Feature/SyncActionIsolationTest.php` valida que:
   - Um lote com uma ação de `workout_id` inválido + ações válidas retorna 200, processa as válidas e omite só a inválida de `results`.
   - Uma sessão iniciada online, com séries e `finish_session` enviados em lote posterior (misturados a `start_session` velhos nas posições 3 e 6), é finalizada no servidor sem 422.
   - Um `workout_id` (ou exercício personalizado) de outro usuário continua rejeitado — fora de `results` — sem afetar o resto do lote.
   - Um payload estruturalmente inválido continua recebendo 422.
2. Após finalizar offline um treino iniciado online e reconectar, o banner "Treino em andamento" não reaparece.
3. Ações permanentemente irresolúveis saem da `sync_queue` após 5 rejeições, com log no console.
4. `php artisan test` passa em 100% de toda a suíte.
5. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
6. `npm run build` compila com zero erros.

# Phase 40: Total de Séries na Tela de Descanso

Na tela de descanso, onde hoje aparece "Próxima: Série 2", mostrar também o total de séries do exercício, no formato "Próxima: Série 2/4".

## Regras Obrigatórias (Produção)
- O Setwave está em produção (Render + Neon) com dados reais de usuários. Nenhuma mudança pode apagar, alterar ou colocar em risco dados existentes.
- Migrations só aditivas: criar tabela nova ou adicionar coluna que aceite nulo. Nunca remover, renomear ou mudar o tipo de colunas ou tabelas. Nunca apagar ou reescrever registros existentes.
- Seguir as regras do projeto: nada de cores hex em arquivos `.vue`; usar os tokens do `resources/css/design-tokens.css` e os componentes `Base*` (`resources/js/Components/UI/`).
- Rodar os testes existentes e criar testes para o que for novo.
- Não fazer deploy nem git push.

## Tasks
1. **Total de séries na mensagem "Próxima" (`RestTimerOverlay.vue`, `WorkoutSessions/Active.vue`):**
   - Ponto de partida: `RestTimerOverlay.vue` recebe hoje a prop `nextSetNumber` (passada por `Active.vue` a partir de `currentNextWaitingSet`) e exibe "Próxima: Série {{ nextSetNumber }}".
   - Passar também o total de séries do exercício e exibir "Próxima: Série X/Y".
   - O total deve ser o mesmo número usado em "SÉRIE X DE Y" na tela do exercício (hoje calculado por `totalSetsForExercise()` em `Active.vue` e exibido em `SetWaveProgress.vue`), incluindo as séries extras adicionadas pelo "+ Adicionar série extra".
   - Se o próximo passo depois do descanso for outro exercício, manter o comportamento atual dessa mensagem — só incluir o total quando ela mostrar número de série.
   - Não mudar mais nada no layout da tela de descanso.

## Acceptance Criteria
1. Com 4 séries planejadas, ao concluir a série 1 aparece "Próxima: Série 2/4".
2. Depois de adicionar uma série extra, o total é atualizado (ex.: "Próxima: Série 2/5").
3. Quando o próximo passo é outro exercício, a mensagem se comporta exatamente como antes.
4. O layout da tela de descanso não muda além do texto da mensagem.
5. `php artisan test` passa em 100% de toda a suíte, incluindo os testes novos.
6. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
7. `npm run build` compila com zero erros.

# Phase 41: Botão "Finalizar Exercício"

Permitir encerrar o exercício atual mesmo sem concluir todas as séries.

## Regras Obrigatórias (Produção)
- O Setwave está em produção (Render + Neon) com dados reais de usuários. Nenhuma mudança pode apagar, alterar ou colocar em risco dados existentes.
- Migrations só aditivas: criar tabela nova ou adicionar coluna que aceite nulo. Nunca remover, renomear ou mudar o tipo de colunas ou tabelas. Nunca apagar ou reescrever registros existentes.
- Seguir as regras do projeto: nada de cores hex em arquivos `.vue`; usar os tokens do `resources/css/design-tokens.css` e os componentes `Base*` (`resources/js/Components/UI/`).
- Rodar os testes existentes e criar testes para o que for novo.
- Não fazer deploy nem git push.

## Tasks
1. **Botão "Finalizar exercício" (`ExerciseActiveCard.vue`):**
   - Posicionado logo abaixo do "+ Adicionar série extra", com o mesmo estilo (mesmo tamanho, borda tracejada, discreto), mas com uma cor levemente diferente para se destacar um pouco.
   - Usar um token existente, por exemplo uma versão suave do violeta de destaque (ex.: `--accent-text-soft`, `--accent-label`, `--border-accent`). Se não houver um adequado, criar um token novo em `design-tokens.css` (mapeado em `tailwind.config.js` e documentado em `.ai/rules/design-system.md`).
   - O botão deve continuar discreto e não competir com o "Concluir Série".

2. **Confirmação (componentes `Base*`):**
   - Ao tocar, pedir uma confirmação simples: "Finalizar exercício com X de Y séries feitas?", com as opções Finalizar e Cancelar.
   - Usar componentes `Base*` (e o modal/confirmação já existente no projeto, se servir, ex.: `ConfirmationModal.vue`/`Modal.vue`).
   - Cancelar não altera nada.

3. **Ao confirmar (`WorkoutSessions/Active.vue`):**
   - As séries já concluídas continuam salvas normalmente.
   - As séries não feitas não são registradas: nem como feitas, nem com carga ou repetições (nenhum `SetLog` criado para elas, nem na fila offline).
   - Em seguida, seguir exatamente o mesmo fluxo de quando o usuário conclui a última série do exercício: ir para o próximo exercício ou, se for o último, fazer o mesmo que o app já faz hoje ao terminar o último exercício. Reaproveitar o fluxo existente em vez de duplicá-lo.
   - Funcionar online e offline (sessão com fila de sincronização), sem mudar o contrato de sincronização existente.

4. **Integridade dos números:**
   - Séries não feitas não podem entrar no volume total, no histórico, nas estatísticas nem na análise da IA de progressão — nem como feitas, nem como falha.
   - Não deve precisar de migration. Se for realmente necessária, só aditiva.

## Acceptance Criteria
1. Finalizar um exercício com 2 de 4 séries salva só as 2 feitas e avança igual ao fluxo normal (próximo exercício, ou o comportamento atual de fim do último exercício).
2. O volume total e o histórico mostram só o que foi feito.
3. Cancelar a confirmação não altera nada.
4. O botão fica logo abaixo do "+ Adicionar série extra", no mesmo estilo tracejado e discreto, com cor levemente diferente vinda de token (sem hex no `.vue`), sem competir com o "Concluir Série".
5. Testes novos cobrindo: finalizar com séries parciais (só as feitas persistidas) e séries não feitas fora do volume, histórico, estatísticas e IA de progressão.
6. `php artisan test` passa em 100% de toda a suíte.
7. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
8. `npm run build` compila com zero erros.

# Phase 42: IA de Progressão Sugere Apenas Cargas que Existem de Verdade

A IA de progressão deve sugerir somente cargas que existem no equipamento do exercício (por exemplo, nunca "10,5 kg" em halteres). As sugestões atuais já são boas: manter a lógica e o tom atuais, mudando só o necessário.

## Regras Obrigatórias (Produção)
- O Setwave está em produção (Render + Neon) com dados reais de usuários. Nenhuma mudança pode apagar, alterar ou colocar em risco dados existentes.
- Migrations só aditivas: criar tabela nova ou adicionar coluna que aceite nulo. Nunca remover, renomear ou mudar o tipo de colunas ou tabelas. Nunca apagar ou reescrever registros existentes.
- Seguir as regras do projeto: nada de cores hex em arquivos `.vue`; usar os tokens do `resources/css/design-tokens.css` e os componentes `Base*` (`resources/js/Components/UI/`).
- Rodar os testes existentes e criar testes para o que for novo.
- Não fazer deploy nem git push.

## Tasks
1. **Entender o que existe hoje (descrever no resumo final da fase):**
   - Ler como a sugestão de progressão funciona: serviço (`app/Services/ProgressiveOverloadService.php` e a camada `app/Services/AI/`), prompt, e onde a sugestão aparece (card de sugestão na sessão ativa).
   - Ver como a carga é registrada em cada tipo de exercício: total da barra, por lado ou por halter.
   - Descrever isso no resumo final da fase.

2. **Tipo de equipamento por exercício:**
   - Tipos: `halteres`; `barra_anilhas` (barra reta, W ou smith com anilhas); `maquina_anilhas` (leg press, hack e similares carregados com anilhas); `maquina_placas` (máquinas de pino ou pilha de placas, polia e cabo); `kettlebell`; `peso_corporal`; `outro`. Modelar como enum PHP em `app/Enums/` com `label()` em português, seguindo o padrão do projeto.
   - Guardar em um campo novo em `exercises` (migration aditiva, coluna que aceita nulo).
   - Identificar primeiro por palavras no nome (halter, haltere, máquina, polia, cabo, crossover, barra, smith, leg press, hack, kettlebell, barra fixa, paralela, flexão etc.).
   - Se as palavras não resolverem, perguntar à IA uma única vez e salvar a resposta.
   - Fazer isso sob demanda: quando o exercício for criado ou usado, inclusive pelo scan de ficha (`WorkoutScannerService`). Nunca reprocessar exercícios já classificados.
   - O equipamento é sempre deduzido automaticamente: o usuário NUNCA escolhe nem corrige o equipamento manualmente. Não criar campo "Equipamento" em nenhuma tela e não criar tela de edição de exercício por causa disso.
   - A forma de registro da carga (total, por lado ou por halter) também é deduzida automaticamente a partir do equipamento (ex.: `halteres` = por halter; `barra_anilhas`/`maquina_anilhas` = como o app já registra hoje, conforme a investigação do item 1), sem perguntar nada ao usuário.
   - Se não for possível classificar (ou enquanto o exercício ainda não foi classificado), usar `outro` como padrão sensato: nesse caso, manter o comportamento atual — o passo dos botões + e − continua o incremento atual e a sugestão da IA não é restringida a uma lista de cargas.

3. **Regras de cargas válidas (padrão):**
   - Halteres (peso de cada halter): até 10 kg, de 1 em 1 kg; acima de 10 kg, de 2 em 2 kg (12, 14, 16...). Nunca decimais.
   - Máquina de placas, polia e cabo: de 5 em 5 kg.
   - Barra ou máquina com anilhas:
     - Anilhas de 2,5 / 5 / 10 / 15 / 20 / 25 kg, sempre em par (uma de cada lado).
     - O menor aumento é um par de 2,5: +5 kg se a carga registrada é o total, +2,5 kg se é por lado.
     - Calcular sempre a partir da carga atual.
   - Kettlebell: 4, 6, 8, 10, 12, 14, 16, 20, 24, 28, 32 kg.
   - Peso corporal: não sugerir carga. Sugerir repetições, ou adicionar anilha quando fizer sentido.
   - Incremento configurado pelo usuário:
     - Se o usuário mudou o incremento de carga do exercício (o "CARGA · 2,5" abaixo dos botões + e −, em `LoadRepsStepper.vue`/`ExerciseActiveCard.vue`), esse valor tem prioridade.
     - Se for só o valor padrão, o passo dos botões + e − passa a seguir o tipo de equipamento (ex.: halteres acima de 10 kg sobem de 2 em 2).

4. **Serviço PHP determinístico (`app/Services/`):**
   - Recebe o equipamento, a forma de registro e a carga atual.
   - Devolve as próximas cargas válidas acima e abaixo.
   - Testes unitários incluindo: halteres 10 → 12 (nunca 10,5); halteres 8 → 9; máquina 45 → 50; barra (carga total) 40 → 45.

5. **Ajustes no prompt da IA:**
   - Informar o tipo de equipamento e a lista de cargas válidas próximas, e exigir que ela escolha somente dessa lista.
   - Aplicar progressão dupla: quando o próximo peso válido for um salto grande para aquele exercício (comum em halteres leves e máquinas), sugerir primeiro aumentar repetições dentro da faixa e só subir a carga ao atingir o topo da faixa.
   - Reduções de carga também só para valores válidos.
   - Manter a lógica e o tom atuais das sugestões, mudando só o necessário.

6. **Rede de segurança:**
   - Depois da resposta da IA, arredondar a carga sugerida para o valor válido mais próximo (em caso de empate, o menor).
   - Registrar em log quando esse arredondamento acontecer.

7. **Custo de IA:**
   - Não aumentar o número de chamadas à IA por série. A única chamada extra permitida é a classificação do equipamento, uma vez por exercício.

## Acceptance Criteria
1. Nenhuma sugestão mostrada ao usuário tem carga inexistente para o equipamento.
2. Os testes unitários do serviço de cargas válidas passam, incluindo: halteres 10 → 12 (nunca 10,5); halteres 8 → 9; máquina 45 → 50; barra (carga total) 40 → 45.
3. As sugestões de exercícios que já funcionavam continuam coerentes (os testes existentes de progressão continuam passando).
4. Testes cobrindo: classificação por palavra-chave, fallback para a IA uma única vez (sem reclassificar exercício já classificado), arredondamento de segurança com log, passo dos botões + e − seguindo o equipamento quando o incremento é o padrão, e exercício sem classificação (`outro`) mantendo o comportamento atual.
5. Nenhuma tela ou campo pede ao usuário para escolher o equipamento; a classificação é 100% automática.
6. Nenhuma chamada extra à IA por série; a única chamada nova é a classificação de equipamento, uma vez por exercício.
7. A migration é aditiva (coluna nova que aceita nulo); nenhum dado existente é alterado.
8. O resumo final descreve como a progressão funciona hoje e como a carga é registrada em cada tipo de exercício.
9. `php artisan test` passa em 100% de toda a suíte.
10. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
11. `npm run build` compila com zero erros.

# Phase 43: IA Preparada para Troca de Exercícios e de Programa

> **⚠️ JÁ EXISTE TRABALHO PARCIAL DESTA PHASE NO CÓDIGO.** Uma execução anterior foi interrompida antes de terminar. Revisar, aproveitar e completar esse trabalho: NÃO começar do zero e NÃO apagar o que já existe (só ajustar o que estiver errado ou incompleto). Antes de qualquer coisa, reler o estado atual dos arquivos abaixo; esta lista é a referência de quando a nota foi escrita e pode ter mudado:
> - Já criados: `app/Enums/MovementPattern.php`, `app/Enums/SuggestionKind.php`, a migration `database/migrations/2026_09_27_000002_add_movement_pattern_to_exercises_table.php` (coluna `movement_pattern` anulável), `app/Services/ExerciseMovementPatternService.php` (palavras-chave + IA uma única vez), `app/Services/ExerciseIdentityService.php` (mesmo exercício com grafia diferente, só na leitura) e `app/Services/StartingLoadService.php` (carga inicial a partir de até 5 parecidos, motivo em uma linha e "Primeira vez").
> - Já alterados: `app/DataTransferObjects/OverloadSuggestion.php` (campos `kind` e `basis`; carga/reps anuláveis), `app/Models/Exercise.php` (`movement_pattern`), `app/Services/WorkoutScannerService.php` e `WorkoutSessionService::getLastLogsForExercises` (usando o `ExerciseIdentityService`).
> - Faltava quando a nota foi escrita: (a) o `StartingLoadService` não é chamado por ninguém — o endpoint de sugestões (`WorkoutSessionController::overloadSuggestions`) só usa `ProgressiveOverloadService::analyzeWorkout`; (b) a tela da sessão ativa (`Components/WorkoutSessions/OverloadSuggestionCard.vue` / `Pages/WorkoutSessions/Active.vue`) não mostra o motivo (`basis`) nem o caso "Primeira vez" e precisa lidar com carga/reps nulos sem quebrar o "aplicar sugestão"; (c) não há testes novos; (d) o teste existente em `tests/Feature/WorkoutSessionTest.php` ("the overload suggestions endpoint returns AI recommendations ...") falha porque compara o JSON exato e agora aparecem `kind` e `basis` — atualizar o teste para o novo contrato sem perder o que ele verifica.
> - Ponto de atenção: `ProgressiveOverloadService::analyzeWorkout` olha só as últimas sessões DESTA ficha. Um exercício que o usuário já fez em outra ficha/programa não pode ser tratado como "novo" (nem cair em "Primeira vez" ou em carga estimada por parecidos) só porque a ficha mudou — isso faz parte da Task 1. A Phase 48 vai reconstruir a progressão normal a partir do histórico do exercício; aqui basta não perder esse histórico e não classificá-lo como exercício novo.

Quando o programa mudar ou um exercício novo for adicionado, a IA sugere uma carga inicial realista com base em exercícios parecidos que o usuário já fez.

## Regras Obrigatórias (Produção)
- O Setwave está em produção (Render + Neon) com dados reais de usuários. Nenhuma mudança pode apagar, alterar ou colocar em risco dados existentes.
- Migrations só aditivas: criar tabela nova ou adicionar coluna que aceite nulo. Nunca remover, renomear ou mudar o tipo de colunas ou tabelas. Nunca apagar ou reescrever registros existentes.
- Seguir as regras do projeto: nada de cores hex em arquivos `.vue`; usar os tokens do `resources/css/design-tokens.css` e os componentes `Base*` (`resources/js/Components/UI/`).
- Não mudar o comportamento do que já funciona hoje além do que a phase pede (a progressão normal da Phase 42 e o fluxo do treino em andamento continuam iguais).
- Rodar os testes existentes e criar testes para o que for novo.
- Não fazer deploy nem git push.

## Tasks
0. **Reinvestigar o código antes de implementar:**
   - Ler o trabalho parcial listado na nota acima, `app/Services/ProgressiveOverloadService.php`, a camada `app/Services/AI/`, `app/Services/ValidLoadService.php`, `app/Enums/Equipment.php`, `WorkoutSessionController` (endpoint de sugestões) e os componentes da sessão ativa que exibem a sugestão. Só então decidir o que completar.

1. **Histórico e sugestões ligados ao exercício, não ao programa (verificar e corrigir se necessário):**
   - Trocar ou editar o programa não pode fazer o usuário perder o histórico de um exercício que continua existindo.
   - Se os exercícios forem identificados por nome, reconhecer o mesmo exercício quando a diferença for só maiúsculas, acentos, espaços ou abreviações comuns ("c/" = "com"), sem alterar os dados salvos.
   - Descrever o que foi encontrado no resumo final da fase.

2. **Complementar a classificação da Phase 42:**
   - Adicionar grupo muscular principal e padrão de movimento.
   - Exemplos de padrão: empurrar horizontal, empurrar vertical, puxar horizontal, puxar vertical, agachamento, dobradiça de quadril, flexão de cotovelo, extensão de cotovelo, elevação lateral, panturrilha.
   - Mesma lógica da Phase 42: palavras-chave primeiro, IA uma única vez se preciso, salvar, migration aditiva. Reaproveitar o enum `MuscleGroup` existente quando o grupo muscular já estiver disponível.

3. **Exercício sem histórico:**
   - Buscar até 5 exercícios parecidos já feitos pelo usuário (mesmo padrão de movimento e grupo muscular, priorizando os mais recentes).
   - Mandar para a IA um resumo curto: o exercício novo (nome, equipamento, padrão de movimento) e, para cada exercício parecido, a melhor série recente (carga × repetições e data).
   - Não mandar o histórico inteiro.

4. **Orientar a IA, no prompt, a ajustar a carga pelas diferenças entre o exercício novo e o parecido:**
   - Halteres usam carga por halter bem menor que a barra no mesmo movimento.
   - Máquinas guiadas costumam permitir mais carga que peso livre.
   - Unilateral usa menos carga que bilateral.
   - Inclinado usa menos carga que reto.
   - Mais amplitude, mais instabilidade ou técnica mais difícil pedem menos carga.
   - Isolado usa menos carga que composto.
   - Na primeira sessão, ser conservador: melhor começar um pouco leve e subir na próxima.

5. **Mostrar a sugestão:**
   - A carga sugerida passa pelo serviço de cargas válidas da Phase 42.
   - Mostrar ao usuário o motivo em uma linha, por exemplo: "Baseado em Supino Reto com Barra (40 kg × 8)".

6. **Sem nenhum exercício parecido:**
   - Não inventar número. Mostrar "Primeira vez: escolha uma carga em que você faça as repetições com boa técnica".

7. **Transição para a progressão normal:**
   - Depois que o exercício novo tiver histórico próprio, a progressão normal assume.

8. **Resumo do histórico recente de cada parecido (não só a última série nem o último treino):**
   - Complementa a Task 3: para cada exercício parecido, montar em PHP um resumo curto do histórico recente — as melhores marcas (carga × repetições e data) de várias sessões recentes, em número pequeno e limitado, e a tendência calculada em PHP (subindo, estável ou caindo).
   - A referência de cada parecido é o melhor desempenho recente, não a última sessão. Um treino isolado mais fraco não pode puxar a sugestão para baixo.
   - Construir esse resumo como uma peça reutilizável e testável (por exemplo, um serviço próprio), com contrato pensado para ser reaproveitado pela Phase 48 no histórico do PRÓPRIO exercício. Não espalhar essa lógica dentro do prompt.
   - Considerar só o que já entra no histórico hoje (sessões concluídas, séries de trabalho com carga; aquecimento fora), seguindo os filtros que o código já usa.

9. **Carga E repetições coerentes:**
   - A sugestão inicial traz carga e repetições (número inteiro) coerentes entre si.
   - Quando a ficha tiver uma faixa de repetições (ex.: "8-10"), as repetições sugeridas ficam dentro dela; com um número único, usar esse número; sem meta, um valor sensato para o padrão de movimento.
   - Validar em PHP depois da resposta da IA (repetições inteiras, dentro da faixa quando ela existir); não confiar só na IA.
   - Hoje `target_reps` é texto livre na ficha (ex.: "10", "8-10"). Se for preciso interpretar a faixa aqui, fazer isso em UM único lugar pequeno e reutilizável; a Phase 47 vai consolidar essa regra no app inteiro e deve poder reaproveitá-la.

10. **Troca de máquina ou de equipamento:**
   - Funcionar bem quando o exercício novo é o "mesmo movimento" com outro equipamento (ex.: Supino Reto com Barra → Supino na Máquina; agachamento livre → hack/leg press).
   - O resumo enviado à IA informa o equipamento e a forma de registro da carga (total, por lado, por halter) do exercício novo e de cada parecido, para que as regras da Task 4 sejam aplicadas sem confusão de unidade.
   - A carga final passa pelo serviço de cargas válidas usando o equipamento do exercício NOVO.

11. **Prompt curto e custo:**
   - O resumo continua curto e montado em PHP (até 5 parecidos × poucas sessões cada); nada de mandar o histórico inteiro.
   - Nenhuma chamada extra à IA por série; a carga inicial dos exercícios novos de uma ficha continua agrupada em uma única chamada, como já está no trabalho parcial (além da classificação única por exercício).

## Acceptance Criteria
1. Testes cobrindo três casos:
   - Exercício novo com exercício parecido no histórico: a IA recebe só o resumo curto (até 5 parecidos, melhor série recente de cada), a carga passa pelo serviço de cargas válidas e o motivo em uma linha é exibido.
   - Exercício novo sem nenhum parecido: nenhum número inventado; aparece "Primeira vez: escolha uma carga em que você faça as repetições com boa técnica".
   - Troca de programa mantendo o histórico dos exercícios que continuam.
2. Depois que o exercício novo tem histórico próprio, a progressão normal da Phase 42 assume.
3. Migrations aditivas apenas; nenhum dado salvo é alterado (inclusive nomes de exercícios).
4. O resumo final descreve o que foi encontrado sobre a ligação do histórico com exercício/programa.
5. O trabalho parcial que já existia foi aproveitado (não recriado do zero) e está ligado ponta a ponta: endpoint de sugestões → tela da sessão ativa, incluindo motivo em uma linha e "Primeira vez".
6. Testes do resumo de histórico dos parecidos: com várias sessões de um parecido, um treino isolado mais fraco (o mais recente) NÃO baixa a referência enviada à IA nem a carga sugerida; a tendência (subindo/estável/caindo) é calculada corretamente.
7. Testes de repetições: a sugestão inicial traz repetições inteiras, dentro da faixa da ficha quando ela existe (ex.: ficha "8-10" → reps entre 8 e 10), mesmo se a IA responder fora dela.
8. Teste de troca de equipamento: exercício novo de máquina com histórico só de um parecido com barra recebe carga válida para a máquina e o motivo cita o exercício de barra.
9. Testes garantem que o prompt não contém o histórico inteiro (quantidade limitada de parecidos e de sessões por parecido) e que não há chamada extra à IA por série.
10. O teste existente de `tests/Feature/WorkoutSessionTest.php` sobre o endpoint de sugestões foi atualizado para o novo contrato (`kind`/`basis`) e passa.
11. `php artisan test` passa em 100% de toda a suíte.
12. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
13. `npm run build` compila com zero erros.

# Phase 44: Página de Histórico de Treinos (Somente Leitura)

Uma página com a lista dos treinos FINALIZADOS do usuário e o detalhe de cada um (exercícios e séries executadas). Esta phase é só leitura; editar e excluir vêm na Phase 45.

## Regras Obrigatórias (Produção)
- O app está em produção (Render + Neon) com dados reais de usuários. Nenhum dado existente pode ser perdido ou alterado por deploy. Migrations somente aditivas (criar tabela nova ou adicionar coluna que aceite nulo; nunca remover, renomear ou mudar o tipo de colunas/tabelas; nunca apagar ou reescrever registros existentes).
- Não mudar o comportamento do que já funciona hoje além do que a phase pede. O fluxo do treino em andamento (série atual, timer, descanso, sugestão de carga, botão Finalizar) deve continuar igual.
- Usar o design system existente (componentes `Base*` em `resources/js/Components/UI/`, tokens de `resources/css/design-tokens.css`, nenhum hex literal em `.vue`), mobile-first, textos em pt-BR, funcionando bem em Android e iPhone.
- Cada usuário só vê, edita e exclui os próprios treinos, com checagem no backend (não só na tela), seguindo o padrão de autorização do projeto.
- Tudo que é calculado a partir das séries (volume total, referência "Última", sugestões de carga/progressão, recordes, histórico por exercício, estatísticas) deve refletir edições e exclusões. Descobrir se esses valores são calculados na hora ou salvos no banco; se forem salvos, recalcular.
- Rodar os testes existentes e criar testes para o que for novo. Não fazer deploy nem git push.

## Tasks
1. **Reinvestigar o código antes de implementar:**
   - As Phases 40 a 43 mudam o treino em andamento, a IA de progressão e a classificação de exercícios. Antes de escrever qualquer código, reler o estado atual de: models e migrations de `WorkoutSession`/`SetLog`/`Workout`/`Exercise`, `WorkoutSessionService`, `ExerciseHistoryService`, `DashboardAnalyticsService`, `ProgressiveOverloadService`, rotas em `routes/web.php`, a navegação (`Components/Navigation/Sidebar.vue`, `Layouts/AuthenticatedLayout.vue`) e a página de histórico por exercício que já existe (`Pages/Exercises/History.vue`).
   - Referência do que existia quando esta phase foi escrita (confirmar, pode ter mudado): sessão finalizada = `workout_sessions.completed_at` preenchido; em andamento = `completed_at` nulo; séries em `set_logs` (carga pode ser nula e aparece como "—kg"); `workout_id` da sessão pode ser nulo se a ficha foi excluída (`nullOnDelete`).
   - Se a Phase 41 ("Finalizar exercício") já estiver implementada, respeitar a regra dela: séries não feitas não existem no histórico.

2. **Lista de treinos finalizados:**
   - Somente treinos FINALIZADOS do usuário autenticado, mais recentes primeiro.
   - Cada item com um resumo: data, nome do treino e o que mais já existir de dado aproveitável (ex.: quantidade/nomes de exercícios, volume total, duração). Tratar sessões cuja ficha foi excluída (nome ausente) com um texto neutro, sem quebrar a tela.
   - Paginação ou carregamento incremental se fizer sentido para o volume de dados.
   - Estado vazio amigável quando o usuário ainda não finalizou nenhum treino.
   - O treino em andamento NÃO aparece aqui; ele continua sendo usado só pela tela de treino.

3. **Acesso pela navegação atual do app:**
   - Adicionar a entrada "Histórico" na navegação existente, sem reorganizar os itens atuais.

4. **Tela de detalhe do treino:**
   - Exercícios e séries executadas (carga × reps), na ordem em que fazem sentido para o usuário.
   - Somente leitura nesta phase.
   - Se fizer sentido, ligar cada exercício à página de histórico por exercício que já existe, reaproveitando-a em vez de duplicar.
   - Acessar o detalhe de um treino de outro usuário (ou de um treino em andamento) deve ser bloqueado no backend.

5. **Offline:**
   - O app é offline-first (ver `resources/pwa/sw.js`). Esta phase não precisa sintetizar o histórico offline, mas abrir a página sem conexão não pode travar nem quebrar o app: mostrar um estado amigável ou o que já estiver em cache. Verificar como o Service Worker trata rotas novas antes de decidir.

## Acceptance Criteria
1. A página lista apenas treinos finalizados do próprio usuário, mais recentes primeiro, com data, nome e resumo.
2. O treino em andamento não aparece na lista.
3. O detalhe mostra exercícios e séries executadas (carga × reps), incluindo séries sem carga exibidas de forma coerente com o resto do app.
4. Estado vazio amigável quando não há treinos finalizados.
5. A página é acessível pela navegação atual do app.
6. Testes de Feature cobrindo: lista só com finalizados do próprio usuário e em ordem; detalhe de treino de outro usuário bloqueado (403/404); treino em andamento fora da lista.
7. O fluxo do treino em andamento continua igual (testes existentes passando).
8. `php artisan test` passa em 100% de toda a suíte.
9. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
10. `npm run build` compila com zero erros.

# Phase 45: Editar e Excluir no Histórico de Treinos

No detalhe de um treino do histórico (Phase 44), permitir editar carga e repetições de qualquer série, excluir um exercício de um treino e excluir um treino inteiro. Motivo: treinos feitos só para testar funcionalidades poluem o histórico real.

## Regras Obrigatórias (Produção)
- O app está em produção (Render + Neon) com dados reais de usuários. Nenhum dado existente pode ser perdido ou alterado por deploy. Migrations somente aditivas (criar tabela nova ou adicionar coluna que aceite nulo; nunca remover, renomear ou mudar o tipo de colunas/tabelas; nunca apagar ou reescrever registros existentes).
- Não mudar o comportamento do que já funciona hoje além do que a phase pede. O fluxo do treino em andamento (série atual, timer, descanso, sugestão de carga, botão Finalizar) deve continuar igual.
- Usar o design system existente (componentes `Base*` em `resources/js/Components/UI/`, tokens de `resources/css/design-tokens.css`, nenhum hex literal em `.vue`), mobile-first, textos em pt-BR, funcionando bem em Android e iPhone.
- Cada usuário só vê, edita e exclui os próprios treinos, com checagem no backend (não só na tela), seguindo o padrão de autorização do projeto.
- Tudo que é calculado a partir das séries (volume total, referência "Última", sugestões de carga/progressão, recordes, histórico por exercício, estatísticas) deve refletir edições e exclusões. Descobrir se esses valores são calculados na hora ou salvos no banco; se forem salvos, recalcular.
- Rodar os testes existentes e criar testes para o que for novo. Não fazer deploy nem git push.

## Tasks
1. **Reinvestigar o código antes de implementar:**
   - Reler o estado atual da Phase 44 (páginas, rotas, controller/service do histórico) e de tudo que lê `set_logs`/`workout_sessions`.
   - Mapear TODOS os pontos que calculam algo a partir das séries e confirmar se calculam na hora ou guardam resultado. Quando esta phase foi escrita, os cálculos eram feitos na hora (`WorkoutSessionService::getLastLogsForExercises`, `ExerciseHistoryService` — histórico, volume e recorde —, `ProgressiveOverloadService`, `DashboardAnalyticsService`), mas existiam cópias no aparelho: `lastLogs` no IndexedDB (via `/api/sync/state`) e páginas Inertia no cache do Service Worker. Confirmar e tratar o que existir no momento.
   - Verificar como a sincronização offline grava séries (`SyncService`, fila `sync_queue`), para garantir que nada editado ou excluído aqui seja recriado por uma ação antiga da fila.

2. **Editor de série como componente reutilizável:**
   - Criar um componente de edição de série (carga e repetições) com `defineProps`/`defineEmits`, reaproveitando os controles de ajuste +/− e os incrementos que o treino em andamento já usa (sem duplicar lógica).
   - Deve aceitar série sem carga (hoje exibida como "—kg").
   - Ele será reutilizado pela Phase 46 dentro do treino em andamento; desenhar o contrato (props/eventos) pensando nisso, sem acoplar ao histórico.

3. **Editar série no detalhe do treino:**
   - Editar carga e repetições de qualquer série de um treino finalizado.
   - Validação no backend em FormRequest (valores coerentes com os que o app já aceita ao registrar uma série).
   - Checagem de posse no backend.

4. **Excluir exercício de um treino e excluir treino inteiro:**
   - Toda exclusão pede confirmação clara antes (usando o modal/confirmação `Base*` já existente no projeto), dizendo o que será excluído.
   - Preferir exclusão recuperável (soft delete) se se encaixar bem no código atual — o projeto ainda não usava soft delete quando esta phase foi escrita; avaliar o impacto em consultas existentes, índices únicos e no `updateOrCreate` de séries antes de decidir. Se usar, só com colunas novas que aceitem nulo.
   - Em qualquer caso, itens excluídos somem de todas as telas e de todos os cálculos (volume, "Última", sugestões de carga/progressão, recordes, histórico por exercício, estatísticas/dashboard, sequência de dias).
   - Excluir treino ou exercício do histórico NÃO exclui a ficha (`Workout`) nem o exercício do catálogo.

5. **Refletir as mudanças em todo o app:**
   - Depois de editar/excluir, os valores derivados mostram o novo estado — no servidor e nas cópias do aparelho (atualizar/invalidar o que for preciso, reaproveitando os mecanismos que já existem, como a invalidação de cache do Service Worker e o `/api/sync/state`).

6. **Offline:**
   - Editar e excluir no histórico podem exigir conexão nesta phase. Sem conexão, informar de forma amigável e não perder nem corromper nada.

## Acceptance Criteria
1. Editar carga/reps de uma série no detalhe atualiza a série e todos os valores derivados (volume, "Última", sugestões, recordes, histórico por exercício, estatísticas).
2. Excluir um exercício de um treino remove as séries dele desse treino de todas as telas e cálculos, sem excluir a ficha nem o exercício do catálogo.
3. Excluir um treino inteiro o remove do histórico e de todos os cálculos, sem excluir a ficha.
4. Toda exclusão pede confirmação clara; cancelar não altera nada.
5. Nenhum usuário consegue editar ou excluir séries/exercícios/treinos de outro usuário (testado no backend).
6. Uma ação antiga da fila offline não recria nada que foi excluído.
7. O editor de série é um componente reutilizável, com contrato por props/eventos, pronto para a Phase 46.
8. Testes de Feature cobrindo: editar série (e o reflexo em "Última"/histórico por exercício/recorde), excluir exercício de um treino, excluir treino, bloqueio entre usuários, e os cálculos ignorando itens excluídos.
9. Migrations, se existirem, são somente aditivas; nenhum dado existente é alterado no deploy.
10. O fluxo do treino em andamento continua igual (testes existentes passando).
11. `php artisan test` passa em 100% de toda a suíte.
12. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
13. `npm run build` compila com zero erros.

# Phase 46: Editar Série Já Concluída Durante o Treino

No treino em andamento, tocar em um card de série concluída abre a edição daquela série (carga e reps). Caso de uso: a pessoa concluiu a série "no automático", sem ajustar peso ou reps.

## Regras Obrigatórias (Produção)
- O app está em produção (Render + Neon) com dados reais de usuários. Nenhum dado existente pode ser perdido ou alterado por deploy. Migrations somente aditivas (criar tabela nova ou adicionar coluna que aceite nulo; nunca remover, renomear ou mudar o tipo de colunas/tabelas; nunca apagar ou reescrever registros existentes).
- Não mudar o comportamento do que já funciona hoje além do que a phase pede. O fluxo do treino em andamento (série atual, timer, descanso, sugestão de carga, botão Finalizar) deve continuar igual.
- Usar o design system existente (componentes `Base*` em `resources/js/Components/UI/`, tokens de `resources/css/design-tokens.css`, nenhum hex literal em `.vue`), mobile-first, textos em pt-BR, funcionando bem em Android e iPhone.
- Cada usuário só vê, edita e exclui os próprios treinos, com checagem no backend (não só na tela), seguindo o padrão de autorização do projeto.
- Tudo que é calculado a partir das séries (volume total, referência "Última", sugestões de carga/progressão, recordes, histórico por exercício, estatísticas) deve refletir edições e exclusões. Descobrir se esses valores são calculados na hora ou salvos no banco; se forem salvos, recalcular.
- Rodar os testes existentes e criar testes para o que for novo. Não fazer deploy nem git push.

## Tasks
1. **Reinvestigar o código antes de implementar:**
   - Reler o estado atual do treino em andamento (`Pages/WorkoutSessions/Active.vue` e os componentes em `Components/WorkoutSessions/`), já com as mudanças das Phases 40 a 45: cards de série, controles +/− e incrementos, timer, descanso, "Finalizar exercício".
   - Reler o editor de série criado na Phase 45 e reaproveitá-lo.
   - Entender como uma série concluída é salva online e offline (rota de registrar série, fila `sync_queue`, registro local da sessão ativa no IndexedDB, cópia local usada pelo volume total). Quando esta phase foi escrita, registrar de novo a mesma série (`exercise_id` + `set_number`) atualizava o registro existente em vez de duplicar — confirmar se continua assim e, se sim, preferir reaproveitar esse caminho.

2. **Abrir a edição a partir do card de série concluída:**
   - Tocar em um card de série concluída abre a edição daquela série (carga e reps), reaproveitando o editor da Phase 45 e os mesmos controles de ajuste +/− com os incrementos atuais.
   - Funcionar também para séries salvas sem carga (hoje aparecem como "—kg").
   - Cards de séries não concluídas mantêm o comportamento atual.

3. **Salvar e cancelar:**
   - Salvar atualiza o card, o volume total e tudo que depender daquela série.
   - Cancelar sem salvar não altera nada.
   - Funcionar online e offline, como o resto do treino em andamento, sem duplicar séries na sincronização.

4. **Isolamento do fluxo atual:**
   - Editar uma série anterior NÃO muda a série atual, o contador de séries, o timer nem o descanso em andamento.
   - Não inicia descanso, não toca alarme e não avança exercício.

## Acceptance Criteria
1. Tocar em um card de série concluída abre o editor com a carga e as reps salvas (ou vazio/"—kg" quando não havia carga).
2. Salvar atualiza o card, o volume total e os valores derivados daquela série (inclusive depois, no histórico e em "Última").
3. Cancelar não altera nada.
4. Editar uma série anterior não muda a série atual, o contador de séries, o timer nem o descanso em andamento.
5. Funciona online e offline, sem criar série duplicada após sincronizar.
6. O editor usado é o mesmo componente da Phase 45, com os mesmos controles +/− e incrementos.
7. Testes cobrindo: atualização de série já concluída sem duplicar (online e via sincronização), bloqueio de edição de série de outro usuário no backend.
8. O fluxo do treino em andamento continua igual para quem não edita nada (testes existentes passando).
9. `php artisan test` passa em 100% de toda a suíte.
10. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
11. `npm run build` compila com zero erros.

# Phase 47: Repetições Sempre um Número no Treino (Faixa da Ficha Vira Meta)

No scan da ficha, quando a ficha dizia "8-10", a IA preencheu as repetições como "8-10" e esse texto foi parar no campo REPS do treino em andamento. O campo REPS é onde a pessoa registra quantas repetições FEZ, então precisa ser sempre um número inteiro. A faixa da ficha passa a ser a META do exercício (mínimo e máximo), usada pela IA de progressão — não o número de repetições feitas.

## Regras Obrigatórias (Produção)
- O app está em produção (Render + Neon) com dados reais de usuários. Nenhum dado existente pode ser perdido ou alterado por deploy. Migrations somente aditivas (criar tabela nova ou adicionar coluna que aceite nulo; nunca remover, renomear ou mudar o tipo de colunas/tabelas; nunca apagar ou reescrever registros existentes).
- Fichas já salvas em produção com faixa no texto (ex.: "8-10") precisam funcionar SEM reescrever esses registros: interpretar na leitura. Se for preciso guardar mínimo e máximo, usar colunas novas que aceitam nulo, preenchidas só em gravações novas.
- Não mudar o comportamento do que já funciona hoje além do que a phase pede. O fluxo do treino em andamento (série atual, timer, descanso, sugestão de carga, botão Finalizar, edição de série das Phases 45 e 46) deve continuar igual, exceto pelo valor inicial do REPS descrito abaixo.
- Usar o design system existente (componentes `Base*` em `resources/js/Components/UI/`, tokens de `resources/css/design-tokens.css`, nenhum hex literal em `.vue`), mobile-first, textos em pt-BR, funcionando bem em Android e iPhone. Não criar telas novas.
- Funcionar online e offline, como o resto do treino em andamento (ver `resources/pwa/sw.js` e o IndexedDB).
- Rodar os testes existentes e criar testes para o que for novo. Não fazer deploy nem git push.

## Tasks
1. **Reinvestigar o código antes de implementar (as Phases 43 a 46 mexeram em partes relacionadas):**
   - Onde a ficha guarda as repetições-alvo. Referência de quando esta phase foi escrita (confirmar): `workout_exercises.target_reps` é texto anulável (`database/migrations/2026_08_13_220003_create_workout_exercises_table.php`), lido via pivot em `Workout`/`Exercise` e no model `WorkoutExercise`.
   - Como o scan preenche esse valor: `app/Services/WorkoutScannerService.php` (o prompt pedia "preserve faixas de repetições como string" e o valor era salvo com `(string)`).
   - Como o treino em andamento usa esse valor: `WorkoutSessionController::show` (padrão `'10'`), `SyncStateController`, o mini-servidor do Service Worker em `resources/pwa/sw.js` (também com padrão `'10'`), e `Pages/WorkoutSessions/Active.vue`, que usava `exercise.target_reps` como valor inicial do REPS (`initSetsForExercise`, `addExtraSet`) — por isso "8-10" chegava ao campo. Ver também `LoadRepsStepper.vue`/`LoadRepsHero.vue` (+ e −), `LogSetRequest` (reps inteiro) e o editor de série criado nas Phases 45 e 46.
   - Onde a meta já é exibida: `Components/WorkoutSessions/ExerciseActiveCard.vue` ("× ... reps"), `Components/Workouts/WorkoutCard.vue`, `Pages/Workouts/Show.vue`.
   - Onde a ficha é criada ou editada manualmente: `Components/Workouts/WorkoutForm.vue`, `StoreWorkoutRequest`, `UpdateWorkoutRequest`, `WorkoutService`, e a edição offline de fichas (`SyncResourceMutationsRequest`/`SyncService` e o equivalente no Service Worker).
   - Como a IA de progressão usa a faixa: `ProgressiveOverloadService` (progressão dupla da Phase 42) e o serviço de carga inicial da Phase 43, que recebiam `target_reps` cru; e se a Phase 43 já criou algum ponto único de interpretação da faixa (reaproveitar em vez de duplicar).

2. **Uma regra única de interpretação da meta de repetições:**
   - Um único lugar no backend (pequeno, reutilizável e com testes unitários) transforma o texto da meta em mínimo e máximo inteiros, ou em "sem meta numérica".
   - Casos mínimos: número único ("10" → 10–10); faixa clara ("8-10", "8 a 10", "8–10", "8 - 10" → 8–10; faixa invertida é normalizada).
   - Formatos que não são uma faixa clara (ex.: "12/10/8", "até a falha", "AMRAP", "10 cada lado", texto vazio) nunca quebram nada: escolher a interpretação mais simples e documentá-la no código e no resumo final (ex.: lista de números → do menor ao maior; sem número → sem meta numérica). Nunca gravar texto onde deveria ser número.
   - Se o frontend/offline precisar interpretar dados antigos que não trazem mínimo/máximo (ex.: fichas já guardadas no IndexedDB antes desta phase), espelhar a MESMA regra em um único utilitário JS pequeno, sem espalhar parsing pelos componentes.

3. **Guardar a meta sem reescrever o que já existe:**
   - O texto original da meta continua onde está (é o que a pessoa vê como meta).
   - Se for útil guardar mínimo e máximo, criar colunas novas anuláveis (ex.: em `workout_exercises`), preenchidas apenas nas gravações novas (scan, criação/edição manual, sincronização offline). Registros antigos ficam intactos e são interpretados na leitura pela regra da Task 2.

4. **Scan da ficha:**
   - Orientar a IA, no prompt, a separar número único de faixa (ex.: devolver mínimo e máximo, ou um número), sem perder a meta original.
   - Validar em PHP depois da resposta (não confiar só na IA): passar sempre pela regra da Task 2; valores não numéricos nunca viram número inventado.

5. **Criação e edição manual da ficha (online e offline):**
   - Aplicar a mesma regra: aceitar número ou faixa no campo de repetições, preencher mínimo/máximo quando houver, e não quebrar com formatos estranhos (mensagem de validação amigável em pt-BR, se for o caso, sem mudar o resto do formulário).

6. **REPS do treino em andamento sempre inteiro:**
   - O campo REPS do treino em andamento e o editor de série das Phases 45 e 46 recebem sempre um número inteiro; os botões + e − e o salvar funcionam normalmente (inclusive quando a ficha tem faixa ou meta estranha).
   - Manter o que já funciona hoje. Ordem para o valor inicial do REPS:
     1. Sugestão da IA para o exercício (repetições sugeridas, inteiras).
     2. Sem sugestão: as repetições da última vez que o exercício foi feito (`lastLogs`), como já funciona hoje.
     3. Só quando não houver nenhum dos dois (exercício sem histórico): o menor número da faixa da ficha (número único → esse número). Sem meta numérica, manter o padrão atual, desde que seja um número.
   - As sugestões chegam depois da tela abrir: só preencher séries que a pessoa ainda não tocou nem concluiu; nunca sobrescrever o que ela editou ou já registrou.
   - Manter como está: séries seguintes continuam herdando da série anterior desta sessão; a carga inicial continua com a lógica atual; aplicar a sugestão pelo card continua funcionando.
   - Offline (página sintetizada pelo Service Worker, sem sugestão da IA): mesma ordem a partir do item 2 (última vez; sem histórico, menor número da faixa).

7. **Mostrar a faixa onde a meta já aparece:**
   - Onde o app já mostra a meta do exercício (Task 1), exibir a faixa de forma clara (ex.: "8–10 reps"). Não criar telas nem campos novos.

8. **IA de progressão usa a meta interpretada:**
   - Enviar à IA a meta como mínimo e máximo (em vez do texto cru) na progressão normal e na carga inicial, para a progressão dupla da Phase 42 usar o topo da faixa corretamente.
   - Depois da resposta, as repetições sugeridas são inteiras; quando houver faixa, manter dentro dela.
   - Reaproveitar qualquer interpretação de faixa criada na Phase 43, consolidando na regra única da Task 2 (sem duas regras diferentes no código).

## Acceptance Criteria
1. Teste: scan de uma ficha com "8-10" gera a meta 8–10 (mínimo e máximo) e o REPS do treino em andamento recebe um número inteiro (8 para exercício sem sugestão da IA e sem histórico).
2. Teste: uma ficha antiga já salva com "8-10" (registro criado como em produção, sem colunas novas preenchidas) funciona no treino em andamento e na IA de progressão SEM alterar o registro salvo.
3. Testes unitários da regra única de interpretação cobrindo: "10", "8-10", "8 a 10", "8–10", faixa invertida, "12/10/8", "até a falha", vazio/nulo — nenhum quebra e nenhum gera texto onde deveria haver número.
4. Teste/verificação da ordem do REPS inicial: com sugestão da IA → repetições sugeridas (inteiras, dentro da faixa quando existe); sem sugestão e com histórico → repetições da última vez (comportamento atual preservado); sem sugestão e sem histórico → menor número da faixa.
5. Criação/edição manual da ficha (online e pela sincronização offline) segue a mesma regra, com teste.
6. A meta aparece como faixa onde o app já a exibia; nenhuma tela nova.
7. Os botões + e −, o salvar da série e o editor de série das Phases 45 e 46 funcionam com fichas de faixa (o valor enviado em `reps` é sempre inteiro e passa no `LogSetRequest`).
8. Migrations, se existirem, são somente aditivas (colunas anuláveis); nenhum registro existente é reescrito.
9. O fluxo do treino em andamento continua igual no resto (testes existentes passando).
10. `php artisan test` passa em 100% de toda a suíte.
11. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
12. `npm run build` compila com zero erros.

# Phase 48: Sugestões de Progressão que Analisam o Histórico, Não Só o Último Treino

A IA de progressão deve sempre buscar manter ou melhorar a performance. Hoje ela se baseia demais no último treino: em dias mais fracos a pessoa baixa o peso ou faz menos repetições, e isso puxa as próximas sugestões para baixo. A referência passa a ser o melhor desempenho recente do exercício, não o último treino.

## Regras Obrigatórias (Produção)
- O app está em produção (Render + Neon) com dados reais de usuários. Nenhum dado existente pode ser perdido ou alterado por deploy. Migrations somente aditivas (criar tabela nova ou adicionar coluna que aceite nulo; nunca remover, renomear ou mudar o tipo de colunas/tabelas; nunca apagar ou reescrever registros existentes).
- Não mudar o comportamento do que já funciona hoje além do que a phase pede. O fluxo do treino em andamento (série atual, timer, descanso, card de sugestão, aplicar sugestão, botão Finalizar, edição de série) deve continuar igual.
- Manter o tom atual das sugestões (rationale curto, em pt-BR) e o custo: nenhuma chamada extra à IA por série; o prompt recebe só um resumo curto montado em PHP, nunca o histórico inteiro.
- Toda carga sugerida continua passando pelo serviço de cargas válidas da Phase 42 (`ValidLoadService`), com a rede de segurança de arredondamento.
- Usar o design system existente (componentes `Base*` em `resources/js/Components/UI/`, tokens de `resources/css/design-tokens.css`, nenhum hex literal em `.vue`), textos em pt-BR.
- Rodar os testes existentes e criar testes para o que for novo. Não fazer deploy nem git push.

## Tasks
1. **Reinvestigar como a sugestão é montada hoje (as Phases 43 a 47 mexeram nisso):**
   - `app/Services/ProgressiveOverloadService.php`, a camada `app/Services/AI/`, o que vai no prompt e como a resposta é validada; o endpoint `WorkoutSessionController::overloadSuggestions` e a exibição na sessão ativa.
   - Referência de quando esta phase foi escrita (confirmar): `analyzeWorkout` pegava as 3 últimas sessões concluídas DESTA ficha e mandava todas as séries cruas no prompt; a "carga atual" (que ancora as cargas válidas e o `previous_load`) era a primeira série da sessão mais recente — por isso um dia fraco derrubava a âncora; e um exercício feito só em outra ficha ficava sem sugestão.
   - O que a Phase 43 criou: serviço de carga inicial para exercícios novos, o resumo reutilizável do histórico recente (melhores marcas por sessão + tendência), a identificação de grafias equivalentes (`ExerciseIdentityService`) e a classificação de movimento.
   - Como as Phases 45 e 46 tratam séries editadas e treinos/exercícios excluídos (ex.: soft delete) e quais filtros as consultas de histórico precisam respeitar.
   - A meta de repetições interpretada (mínimo/máximo) da Phase 47.

2. **Resumo curto do histórico do exercício, montado em PHP:**
   - Para cada exercício da ficha que já tem histórico próprio (em qualquer ficha ou programa, incluindo grafias equivalentes do mesmo exercício), montar um resumo das últimas sessões em número pequeno e limitado: melhor série de cada sessão (carga × repetições e data), a referência (melhor desempenho recente, com critério documentado e testado) e a tendência (subindo, estável ou caindo), além de quantas sessões seguidas ficaram abaixo da referência.
   - Reaproveitar a peça de resumo criada na Phase 43 (estender se preciso), sem duplicar lógica.
   - Substituir no prompt as séries cruas das últimas sessões por esse resumo (mantendo só o que for útil e curto, ex.: RPE se existir).

3. **Referência é o melhor desempenho recente, não o último treino:**
   - A carga de referência usada para ancorar as cargas válidas próximas e orientar a IA é a da referência do resumo, não a da última sessão.
   - Um treino isolado mais fraco não derruba a sugestão: a IA sugere voltar ao nível de referência ou progredir a partir dele.
   - Rede de segurança em PHP (como a do arredondamento da Phase 42): fora do caso de queda repetida, uma sugestão abaixo da referência é levada de volta à referência (carga válida) e o ajuste fica em log.

4. **Queda repetida:**
   - Se a queda se repetir em várias sessões seguidas (limiar definido, documentado e testado), não insistir em aumentar a carga: sugerir manter a carga recente e recuperar as repetições, com o motivo em uma linha.
   - Rede de segurança em PHP: nesse caso, nenhuma sugestão de carga acima da carga recente.

5. **Histórico subindo ou estável:**
   - Progressão normal a partir da referência, com a progressão dupla da Phase 42 usando a faixa de repetições da ficha (Phase 47): subir repetições dentro da faixa e só subir para a próxima carga válida ao atingir o topo.

6. **Histórico próprio curto:**
   - Quando o exercício tiver poucas sessões próprias (limiar definido e documentado), complementar o resumo com os exercícios parecidos da Phase 43, reaproveitando o que ela criou. Exercícios sem nenhum histórico próprio continuam com a Phase 43 (carga inicial / "Primeira vez").

7. **Reflexo de edições e exclusões:**
   - Séries editadas e treinos/exercícios excluídos no histórico (Phases 45 e 46) refletem no resumo e, portanto, nas sugestões. Calcular sempre a partir dos dados atuais, sem guardar cópias que possam ficar velhas.

8. **Custo e tom:**
   - Continua uma única chamada à IA para as sugestões da ficha (nenhuma por série); o resumo é curto e limitado; o rationale mantém o tom atual.

## Acceptance Criteria
1. Teste: histórico com várias sessões boas e um último treino isolado mais fraco → a sugestão NÃO fica abaixo da referência (volta ao nível de referência ou progride a partir dele), mesmo se a IA responder abaixo.
2. Teste: histórico subindo → a sugestão progride (carga ou repetições, conforme a progressão dupla e a faixa da ficha).
3. Teste: queda repetida em várias sessões seguidas → a carga sugerida não aumenta e o motivo em uma linha explica que é para manter e recuperar as repetições.
4. Teste: toda carga sugerida é válida para o equipamento (halteres, máquina de placas, barra com anilhas), inclusive depois das redes de segurança.
5. Teste: editar uma série ou excluir um treino do histórico (Phases 45/46) muda a referência/resumo usado na sugestão seguinte.
6. Teste: exercício com histórico só em outra ficha recebe progressão normal a partir desse histórico (não é tratado como novo); exercício sem histórico continua com a Phase 43.
7. Teste: o prompt contém só o resumo (quantidade limitada de sessões por exercício), não as séries cruas de todo o histórico, e o número de chamadas à IA não aumenta.
8. Os testes existentes de progressão (Phase 42) e de carga inicial (Phase 43) continuam passando ou foram ajustados apenas no que esta phase muda de propósito.
9. O resumo final da phase descreve como a sugestão era montada antes e como é montada agora (referência, tendência, queda repetida, limiares escolhidos).
10. `php artisan test` passa em 100% de toda a suíte.
11. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
12. `npm run build` compila com zero erros.

# Phase 49: OpenAI GPT-6 Luna como IA Principal e Gemini 3.8 Flash (Gratuito) como Reserva

O app passa a usar o GPT-6 Luna da OpenAI (id na API: `gpt-6-luna`) como IA principal em TODAS as funcionalidades, inclusive o scan da ficha. O Google Gemini fica como reserva, com o modelo `gemini-3.8-flash` na camada gratuita (o Gemini não será pago). Vale no ambiente local e, depois do push, na produção. Junto com a troca: reserva que funciona de verdade, configuração de modelos limpa, HTTPS verificado, mensagem de erro do scan honesta, diagnóstico pelo `ai:doctor` e registro de uso no log.

## Regras Obrigatórias (Produção)
- O app está em produção (Render + Neon) com dados reais de usuários. Nenhum dado existente pode ser perdido ou alterado por deploy. Migrations somente aditivas (criar tabela nova ou adicionar coluna que aceite nulo; nunca remover, renomear ou mudar o tipo de colunas/tabelas; nunca apagar ou reescrever registros existentes). Esta phase não deve precisar de migration.
- Não mudar o comportamento do que já funciona hoje além do que a phase pede: as funcionalidades de IA (scan, sugestões de progressão e carga inicial, classificações de equipamento e de movimento) continuam com os mesmos prompts, a mesma validação em PHP e a mesma degradação graciosa; muda só QUEM responde, a reserva, a configuração e os pontos listados abaixo.
- Nunca escrever, registrar em log, exibir ou commitar o valor de chaves de API. O `.env.example` só tem nomes de variáveis e valores não secretos.
- Usar o design system existente (componentes `Base*` em `resources/js/Components/UI/`, tokens de `resources/css/design-tokens.css`, nenhum hex literal em `.vue`), textos em pt-BR.
- Testes nunca chamam APIs reais: todas as respostas de IA são simuladas.
- Rodar os testes existentes e criar testes para o que for novo. Não fazer deploy nem git push.

## Tasks
1. **Reinvestigar a camada de IA antes de implementar (as Phases 42 a 48 mexeram nela):**
   - Reler `app/Services/AI/` inteiro: `AiManager.php` (escolha de empresa, failover, regra que força o scan no Gemini), `Contracts/AiDriverInterface.php` (`generateStructured` e `analyzeImage`), `Drivers/GeminiDriver.php` + `GeminiClient.php`, `Drivers/ClaudeDriver.php`, `Drivers/GroqDriver.php`, `Support/AiModelResolver.php`, `Support/AiTimeoutResolver.php`, `Support/AiJsonSanitizer.php`; o bloco de IA de `config/services.php`; `app/Enums/AiTask.php`; `.env.example`; `app/Console/Commands/AiDoctorCommand.php`; `app/Exceptions/*` de IA; `.ai/rules/ai-integration.md`.
   - Mapear TODOS os serviços que chamam a IA no momento (quando esta phase foi escrita: `WorkoutScannerService`, `ProgressiveOverloadService`, `ExerciseEquipmentService`, `ExerciseMovementPatternService`, `StartingLoadService`) e confirmar que todos passam pelo `AiManager`.
   - Reler os testes de IA existentes (ex.: `AiManagerTest`, `AiTaskRoutingTest`, `AiResilienceTest`, `AiTimeoutResolverTest`, `GeminiClientTest`, `GroqDriverTest`, `WorkoutScannerTest`) — alguns verificam comportamentos que esta phase muda de propósito (ex.: scan fixo no Gemini); ajustar só esses pontos.
   - Referência do que existia quando esta phase foi escrita (confirmar): chamadas HTTP diretas com o `Http` do Laravel, sem SDK; JSON pedido só no texto do prompt; timeouts de 6 s (texto rápido) e 180 s (imagem); só o Gemini repetia a chamada (até 3 vezes em 503/falha de rede); padrões `AI_DEFAULT_DRIVER=gemini` e `AI_FALLBACK_DRIVER=gemini` (ou seja, sem reserva real); as variáveis `GEMINI_MODEL`/`ANTHROPIC_MODEL`/`GROQ_MODEL` e o modelo escrito em `GeminiClient::model()` não tinham efeito; todos os conectores usavam `withoutVerifying()`; a chave do Gemini ia também na URL (`?key=`).

2. **Novo conector OpenAI na camada existente:**
   - Criar o driver OpenAI em `app/Services/AI/` implementando o mesmo contrato dos outros (texto e imagem) e registrado no `AiManager` como os demais. Não reescrever a lógica das funcionalidades.
   - Seguir a documentação oficial atual da OpenAI, preferindo a Responses API: envio de imagem para o scan e resposta em JSON estruturado nativo da API. Manter a limpeza (`AiJsonSanitizer`) e toda a validação em PHP que já existe depois da resposta.
   - Confirmar na documentação oficial o id do modelo (`gpt-6-luna`) e os parâmetros aceitos por ele; usar exatamente esse id como padrão. Se a documentação mostrar algo incompatível, não inventar outro modelo: relatar no resumo final.
   - Variáveis, no mesmo padrão das outras empresas em `config/services.php`: `OPENAI_API_KEY`, `OPENAI_VISION_MODEL` e `OPENAI_FAST_TEXT_MODEL` (padrão `gpt-6-luna` para os dois). Usar sempre o `AiModelResolver` e o `AiTimeoutResolver`.
   - Nas chamadas de texto rápido (sugestões, classificações), configurar o nível de raciocínio do modelo para que a resposta caiba no tempo limite atual. Se o tempo limite precisar mudar, mudar o mínimo necessário e justificar no resumo final.

3. **Configuração das empresas:**
   - Principal: `openai`. Reserva: `gemini`. Ajustar os padrões no código (`config/services.php`) para isso.
   - Remover a regra que força o scan a ser sempre Gemini: o scan usa a principal (OpenAI) e cai para a reserva (Gemini) se falhar. A Groq continua bloqueada para scan (nem como principal nem como reserva).
   - Os conectores de Claude e Groq continuam existindo e funcionando quando configurados; só deixam de ser padrão.

4. **Gemini atualizado (reserva gratuita):**
   - Os modelos padrão do Gemini (imagem e texto) passam a ser `gemini-3.8-flash` (o `gemini-3.5-flash` foi aposentado pelo Google).
   - Seguir a documentação oficial atual do Gemini 3.8 Flash: remover parâmetros que ele não aceita mais (ex.: `temperature`, `top_p`, `top_k`, se forem enviados), usar um nível de raciocínio adequado para respostas rápidas e, se a API oferecer, resposta em JSON nativo — mantendo a validação em PHP.
   - Considerar que é a camada gratuita (limites de uso mais baixos): uma falha por limite do Gemini apenas degrada como hoje, sem quebrar nada.

5. **Reserva que funciona de verdade:**
   - Qualquer falha da OpenAI (sem crédito, limite de gasto atingido, chave errada ou ausente, fora do ar, tempo esgotado, resposta vazia ou JSON inválido) faz a mesma operação ser tentada no Gemini.
   - Em erro de crédito ou de limite de gasto (identificar pelos códigos de erro documentados pela OpenAI, não só pelo status HTTP), NÃO repetir a chamada na OpenAI: ir direto para a reserva. Outras novas tentativas, se existirem, são curtas e não podem estourar o tempo limite da funcionalidade.
   - Se a reserva também falhar, cada funcionalidade degrada exatamente como já degrada hoje.

6. **Limpeza da configuração de modelos (sem mudar o comportamento):**
   - Alinhar o `.env.example` com os padrões reais do código (incluindo as variáveis da OpenAI), sem valores reais de chave.
   - Remover variáveis e trechos sem efeito (ex.: `GEMINI_MODEL`, `ANTHROPIC_MODEL`, `GROQ_MODEL` e o modelo escrito em `GeminiClient` que nunca é usado), ou fazê-los funcionar — o que for mais simples e claro. Documentar a escolha no resumo.

7. **Segurança:**
   - Ligar a verificação do certificado HTTPS em todos os conectores (remover o `withoutVerifying()`). Se no Windows local der erro de certificado, NÃO desligar a verificação: explicar no resumo final o que precisa ser configurado no computador (ex.: arquivo de certificados para o PHP/cURL).
   - Tirar a chave do Gemini da URL (`?key=`), mandando só pelo cabeçalho.
   - Garantir que nenhuma mensagem de erro, log ou exceção contenha o valor de uma chave.

8. **Mensagem de erro do scan:**
   - Diferenciar "não deu para ler a foto" (a IA respondeu, mas não foi possível extrair uma ficha) de "o serviço de IA está indisponível agora, tente de novo em alguns minutos" (falha das empresas: crédito, chave, fora do ar, tempo esgotado). Nunca culpar a foto quando o problema é a IA.
   - Seguir o padrão atual de mensagens (flash) do `WorkoutScannerController`, sem criar telas novas.

9. **`php artisan ai:doctor`:**
   - Passa a testar a empresa principal e a reserva configuradas (inclusive a OpenAI), mostrando para cada uma: empresa, modelos configurados, se a chave está definida (sem mostrar o valor) e se a conexão funcionou.

10. **Registro de uso:**
   - A cada chamada de IA, registrar no log: empresa, modelo, funcionalidade (qual serviço/tarefa), tokens usados (quando a API informar) e se foi a principal ou a reserva. Nunca registrar chaves, a imagem nem o prompt inteiro.

## Acceptance Criteria
1. Existe um driver OpenAI na camada `app/Services/AI/`, com o mesmo contrato dos outros, usando a API oficial (Responses API) com imagem e JSON estruturado nativo; as funcionalidades não tiveram a lógica reescrita.
2. Padrões no código: principal `openai`, reserva `gemini`; `OPENAI_VISION_MODEL` e `OPENAI_FAST_TEXT_MODEL` com padrão `gpt-6-luna`; Gemini com padrão `gemini-3.8-flash` para imagem e texto; o scan usa a principal e cai para a reserva; a Groq continua bloqueada para scan.
3. Testes com respostas simuladas: cada funcionalidade (scan, sugestões de progressão, carga inicial, classificação de equipamento, classificação de movimento) funciona com a OpenAI.
4. Testes: quando a OpenAI falha (sem crédito, limite de gasto atingido, chave ausente/errada, fora do ar, tempo esgotado, JSON inválido), a mesma operação é atendida pelo Gemini.
5. Teste: em erro de crédito e de limite de gasto, a OpenAI é chamada uma única vez (sem nova tentativa) antes de ir para o Gemini.
6. Testes da mensagem do scan: falha das empresas → "serviço de IA indisponível, tente de novo em alguns minutos"; resposta que não vira ficha → mensagem sobre a foto. Nenhuma ficha é criada pela metade e nenhum dado existente se perde em nenhum dos casos.
7. Nenhum conector usa `withoutVerifying()`; a chave do Gemini não aparece na URL (teste verificando a requisição simulada); nenhuma chave aparece em logs ou mensagens.
8. Os parâmetros não aceitos pelo Gemini 3.8 Flash não são mais enviados (teste verificando o corpo da requisição simulada).
9. `.env.example` alinhado com os padrões do código; variáveis e trechos sem efeito removidos ou funcionando.
10. `php artisan ai:doctor` testa principal e reserva configuradas, sem mostrar valores de chaves.
11. Cada chamada de IA gera um registro de uso com empresa, modelo, funcionalidade, tokens e principal/reserva (teste verificando o log).
12. O resumo final lista exatamente as variáveis necessárias no `.env` local e no Render, com os valores (menos as chaves) — lembrando que o `.env` local estava com a Groq como principal (`AI_DEFAULT_DRIVER=groq`) e precisa ser ajustado — e explica o que configurar no Windows caso o HTTPS verificado falhe localmente.
13. `php artisan test` passa em 100% de toda a suíte.
14. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
15. `npm run build` compila com zero erros.

# Phase 50: iPhone e iPad Sem Aviso Sonoro no Fim do Descanso (Android Igual a Hoje)

No iPhone, o áudio do descanso interrompe a música que a pessoa está ouvindo em outro app (Spotify, Apple Music etc.). Decisão: no iPhone e no iPad o app NÃO toca nenhum áudio durante o treino — o fim do descanso é avisado só na tela. No Android e nos demais aparelhos, tudo continua exatamente como hoje. Notificação push fica para uma phase futura (fora do escopo).

## Regras Obrigatórias (Produção)
- O app está em produção (Render + Neon) com dados reais de usuários. Nenhum dado existente pode ser perdido ou alterado por deploy. Esta phase é só de frontend: sem mudanças no backend e sem migrations.
- Não mudar o comportamento do que já funciona hoje além do que a phase pede. O fluxo do treino em andamento (série atual, timer, descanso, sugestão de carga, "Finalizar exercício", edição de série, botão Finalizar) deve continuar igual em todos os aparelhos, exceto pelo áudio no iPhone/iPad.
- Android e outros aparelhos: comportamento de áudio e vibração EXATAMENTE igual ao de hoje. Não alterar o arquivo do alarme (`public/sounds/rest-alarm.wav`), o script que o gera, o volume, a duração, o padrão de vibração nem os momentos em que o desbloqueio e o loop de silêncio acontecem.
- Usar o design system existente (componentes `Base*` em `resources/js/Components/UI/`, tokens de `resources/css/design-tokens.css`, nenhum hex literal em `.vue`), mobile-first, textos em pt-BR.
- Funcionar online e offline, como o resto do treino em andamento (ver `resources/pwa/sw.js` e o IndexedDB), inclusive no app instalado na tela de início.
- Rodar os testes existentes e criar testes para o que for novo. Não fazer deploy nem git push.

## Tasks
1. **Reinvestigar o código antes de implementar:**
   - Reler tudo o que toca o áudio, a vibração e o descanso, confirmando o estado atual (referência de quando esta phase foi escrita — confirmar):
     * `resources/js/Utils/restAlarm.js`: alarme em `<audio>` (singleton), `unlockRestAlarm()` (toca o alarme mudo uma vez e também chama `unlockAudioKeepAlive()` a cada chamada), `playRestAlarm()` (para o loop de silêncio, põe `navigator.audioSession.type = 'playback'`, toca o WAV e vibra no Android), `stopRestAlarm()` (volta o `audioSession` para `'auto'`).
     * `resources/js/Utils/audioAlert.js`: loop de áudio de silêncio (`<audio loop>`, volume 0,01) com Media Session "Descanso em andamento — Setwave" (`startAudioKeepAlive`, `unlockAudioKeepAlive`, `stopAudioKeepAlive`).
     * `resources/js/Composables/useRestAlarm.js`: `armRestAlarm()` liga o loop de silêncio e agenda o `setTimeout`; `disarmRestAlarm()`.
     * `resources/js/Pages/WorkoutSessions/Active.vue`: desbloqueio no toque de "Concluir série" (`completeSet`) e no primeiro `touchend`/`click` da página; `startRest`, `finishRestNaturally` (setTimeout + `watch` idempotentes), `endRest`, `addRestTime`; wake lock opcional.
     * Desbloqueio no "Iniciar treino": `resources/js/Pages/Workouts/Show.vue` e `resources/js/Components/Dashboard/TodayWorkoutHero.vue`.
     * `resources/js/Pages/Profile/Partials/WorkoutPreferencesForm.vue` (botão "Testar alarme", que chama `playRestAlarm`), `resources/js/Utils/workoutPreferences.js`, `resources/js/Composables/useWakeLock.js`, `resources/js/Composables/useNowTicker.js`, `resources/js/Components/WorkoutSessions/RestTimerOverlay.vue`.
   - Procurar no projeto inteiro (incluindo `resources/pwa/sw.js`) qualquer outro ponto que toque áudio, use `navigator.audioSession`, `navigator.mediaSession`, `new Audio`, `AudioContext` ou `navigator.vibrate`, e incluir esses pontos na regra desta phase.
   - Ver como o projeto já testa utilitários JS sem runner de testes JS: `tests/js/repTarget.check.mjs` executado pelo Node a partir de `tests/Feature/RepTargetJsMirrorTest.php`.

2. **Detecção única de iPhone/iPad:**
   - Criar um único utilitário JS pequeno (ex.: em `resources/js/Utils/`) que responde se o aparelho é iPhone ou iPad (iOS/iPadOS), reaproveitado por todo o código do alarme e pelo Perfil. Nenhum outro arquivo faz sua própria checagem de user agent.
   - Precisa reconhecer: iPhone e iPad no Safari; Chrome, Firefox e Edge no iPhone/iPad (que se identificam como `CriOS`, `FxiOS`, `EdgiOS` mas rodam sobre o WebKit do iOS); o app instalado na tela de início (standalone); e o iPad com iPadOS 13+ que se identifica como Mac (ex.: user agent de Macintosh com tela sensível ao toque — `navigator.maxTouchPoints > 1` — e/ou `navigator.userAgentData.platform`, quando existir). Um Mac de verdade (sem toque) e o Android NÃO podem ser detectados como iPhone/iPad.
   - A função deve ser fácil de testar: aceitar os dados do aparelho (user agent, plataforma, pontos de toque) como parâmetros opcionais, usando os do navegador quando não forem passados, e não quebrar fora do navegador (sem `navigator`/`window`).

3. **iPhone/iPad: nenhum áudio do app durante o treino:**
   - Aplicar a regra na fonte (nos utilitários de áudio), para que qualquer chamada atual ou futura fique protegida, sem espalhar `if` pelas páginas:
     * `playRestAlarm()` não toca o bipe.
     * `startAudioKeepAlive()` não toca o loop de silêncio e não registra a Media Session do descanso.
     * `unlockRestAlarm()` e `unlockAudioKeepAlive()` não fazem nenhum `play()` (nem mudo) em nenhum toque: "Iniciar treino" (ficha e dashboard), "Concluir série" e primeiro toque da tela do treino.
     * Nenhum código mexe em `navigator.audioSession` no iPhone/iPad.
     * Nenhum elemento `<audio>` é sequer criado no iPhone/iPad, se for simples garantir isso.
   - As funções de parar (`stopRestAlarm`, `stopAudioKeepAlive`, `disarmRestAlarm`) continuam podendo ser chamadas com segurança no iPhone/iPad (sem erro, sem tocar nada).
   - A vibração já não existe no iPhone; não criar alternativa.

4. **Todo o resto do descanso continua igual em todos os aparelhos:**
   - A contagem na tela, o fim do descanso (a tela de descanso fecha e a próxima série começa sozinha), "Encerrar descanso", "+ tempo", o recálculo ao voltar para o app (tela apagada/segundo plano) e a opção "Manter tela ligada" funcionam exatamente como hoje, inclusive no iPhone/iPad, onde o descanso termina só com a mudança na tela.
   - O disparo do fim do descanso (`setTimeout` + `watch` idempotentes) não pode depender de o áudio ter tocado.

5. **Android e outros aparelhos: exatamente como hoje:**
   - Bipe no fim do descanso, vibração, loop de silêncio com Media Session durante o descanso, desbloqueio nos mesmos toques e `audioSession` como hoje. Mesmos arquivos, volume e duração.

6. **Perfil → Treino:**
   - No iPhone/iPad: esconder o botão "Testar alarme" e mostrar, no lugar, a frase curta "No iPhone, o fim do descanso é avisado só na tela." (com o estilo de texto secundário que a seção já usa).
   - Nos outros aparelhos, nada muda.
   - A opção "Manter tela ligada durante o treino" continua igual em todos os aparelhos (aparece quando o aparelho suporta).

7. **Testes:**
   - Usar o mesmo jeito de testar JS que o projeto já usa: um script em `tests/js/` executado pelo Node a partir de um teste Pest (como `RepTargetJsMirrorTest`), sem adicionar runner de testes JS nem dependências novas. Se o Node precisar importar os utilitários de áudio, resolver de forma simples os imports com alias `@/` (ex.: imports relativos nesses utilitários ou um carregamento compatível no script de teste), sem mudar o comportamento no navegador.
   - Testes da detecção com user agents reais: iPhone Safari, iPhone Chrome (`CriOS`), iPhone Firefox (`FxiOS`), iPad Safari antigo (user agent de iPad), iPad que se identifica como Mac (Macintosh + toque), app standalone no iPhone, Android Chrome (celular e tablet), Mac sem toque e Windows/computador.
   - Testes de que, simulando iPhone/iPad (com `navigator` e `Audio` falsos no Node, contando as chamadas), `playRestAlarm`, `unlockRestAlarm`, `startAudioKeepAlive`, `unlockAudioKeepAlive`, `stopRestAlarm` e `stopAudioKeepAlive` não chamam nenhum `play()`, não criam `<audio>`, não alteram `navigator.audioSession` e não registram Media Session.
   - Testes de que, simulando Android, o fluxo continua como hoje: `unlockRestAlarm` desbloqueia (play mudo do alarme e play/pause do silêncio), `startAudioKeepAlive` toca o loop e registra a Media Session, `playRestAlarm` toca o alarme, põe o `audioSession` em `'playback'` (quando existe) e chama `navigator.vibrate` com o mesmo padrão de hoje; `stopRestAlarm` volta para `'auto'`.

## Acceptance Criteria
1. Existe um único utilitário de detecção de iPhone/iPad, usado por todo o código do alarme e pelo Perfil; nenhum outro arquivo faz checagem própria de user agent.
2. Testes da detecção passam para: iPhone (Safari, Chrome, Firefox, standalone), iPad, iPad que se identifica como Mac (detectado como iPad), Android (não detectado), Mac sem toque e computador Windows (não detectados).
3. Teste: no iPhone/iPad simulado, nenhuma função de áudio do alarme toca nada — zero `play()`, nenhum `<audio>` criado, `navigator.audioSession` intocado e nenhuma Media Session registrada —, inclusive nos desbloqueios de "Iniciar treino", "Concluir série" e primeiro toque.
4. Teste: no Android simulado, o desbloqueio, o loop de silêncio com Media Session, o bipe, o `audioSession` e a vibração acontecem exatamente como hoje.
5. No iPhone/iPad, o descanso continua funcionando só pela tela: contagem, fim do descanso (overlay fecha e a próxima série começa), "Encerrar descanso", "+ tempo", recálculo ao voltar para o app e "Manter tela ligada".
6. Perfil → Treino: no iPhone/iPad, o botão "Testar alarme" não aparece e aparece a frase "No iPhone, o fim do descanso é avisado só na tela."; nos outros aparelhos a seção fica igual.
7. `public/sounds/rest-alarm.wav`, `scripts/generate-rest-alarm.mjs`, o volume, a duração e o padrão de vibração não foram alterados.
8. Nenhuma mudança no backend e nenhuma migration.
9. O resumo final explica como a detecção funciona (inclusive o caso do iPad que se identifica como Mac), lista os arquivos alterados e como testar manualmente num iPhone com música tocando e num Android.
10. `php artisan test` passa em 100% de toda a suíte.
11. `tests/Architecture/ArchitecturalRulesTest.php` passa sem violações.
12. `npm run build` compila com zero erros.
