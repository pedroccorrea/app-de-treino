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
