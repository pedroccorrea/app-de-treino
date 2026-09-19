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
