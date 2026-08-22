# Descrição Canônica do Projeto: Meu App de Treino (IA-LIFT)
## 1. Visão do Produto
Aplicação web mobile-first (PWA) desenvolvida em Laravel 13, Vue 3 (Composition API), Inertia.js e Tailwind CSS. Objetivo: substituir fichas de papel por um diário de treino inteligente com escaneamento de fichas por visão computacional, sobrecarga progressiva guiada por IA e análise de assimetria muscular.
## 2. Escopo do MVP Atual
1. Gestão completa de fichas de treino associadas a dias da semana (DayOfWeek enum).
2. Interface de execução em tempo real (modo foco: 1 exercício por vez, ciclo de série e descanso travado em tela cheia).
3. Navegação por Sidebar lateral moderna (desktop fixa, mobile retrátil).
4. Módulo de Visão Computacional para ler fotos de fichas físicas e importar treinos automaticamente resolvendo sinônimos com o catálogo existente (Entity Resolution).
5. Motor de análise de sobrecarga progressiva com recomendações personalizadas por IA antes de cada treino.
6. Dashboard gamificada com ofensiva (streak), recordes pessoais (PRs), gráfico de frequência e alerta de grupos musculares negligenciados.
## 3. Anti-Escopo (Estritamente FORA do MVP)
- Processamento de pagamentos ou planos de assinatura no aplicativo.
- Redes sociais, feed de amigos ou compartilhamento de treinos.
- Aplicativo mobile nativo em Swift/Kotlin (o frontend é estritamente Web PWA via Inertia).
- Suporte a múltiplos idiomas (exclusivamente Português do Brasil).
## 4. Invariantes Técnicas
- Backend: PHP 8.5+, Laravel 13, SQLite para persistência local, Service Pattern estrito, FormRequests e PHP Enums.
- Frontend: Vue 3 apenas com <script setup>, Inertia.js v2, Tailwind CSS 3/4 Dark Mode nativo e destaque roxo #8B5CF6.
- Testes: Pest 5 com 100% dos testes de feature isolados de APIs pagas via Http::fake().
