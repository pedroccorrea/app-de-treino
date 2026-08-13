# 🏋️ Meu App de Treino (IA-LIFT) - Documento Mestre de Contexto, Arquitetura & Aprendizado

---

## 🎯 1. Visão do Produto & Problema de Negócio
- **Problema:** Registrar treinos de musculação no papel é arcaico e se perde; aplicativos comerciais são engessados, cheios de anúncios e não oferecem inteligência prática sobre evolução de carga e recuperação muscular.
- **Solução:** Uma aplicação web mobile-first (PWA) de alta velocidade onde o usuário tira foto da ficha da academia para popular os dados automaticamente e tem um assistente de IA orientando a sobrecarga progressiva (ajuste de cargas) e identificando pontos fracos de volume muscular.

---

## 🚀 2. Roadmap Funcional Detalhado

### 📌 Fase 1: Fundação & Diário de Treino (MVP Base)
- Catálogo mestre de exercícios com grupos musculares primários e secundários (auto-expansível).
- Criação e gestão de fichas de treino (Treino A, B, C, etc.).
- Interface de execução diária: seleção do treino do dia, marcação rápida de séries executadas (peso, repetições, RPE/percepção de esforço).
- Histórico contínuo de cargas por exercício (independente de mudanças na ficha).

### 📌 Fase 2: Módulo IA Vision (Scanner de Ficha)
- Leitura de foto da ficha física do instrutor via Vision API (OpenAI/Anthropic).
- Extração estruturada (JSON) de exercícios, séries, repetições e intervalos.
- Entity Resolution: mapeamento automático para o catálogo existente ou autocriação com classificação muscular biomecânica.

### 📌 Fase 3: Módulo IA Analytics & Sobrecarga Progressiva
- Cálculo de volume semanal por grupo muscular (séries diretas e indiretas).
- Algoritmo/Agente de IA que analisa o histórico recente e recomenda:
  - *"Subir 2kg no Supino hoje (você bateu 3x12 com facilidade na última sessão)"*.
  - Alertas de desbalanço muscular (ex: excesso de peito, carência de dorsais).

### 📌 Fase 4: Reconhecimento Visual de Máquinas (Futuro)
- Tirar foto de uma máquina na academia para carregar imediatamente o exercício correspondente e o último peso utilizado.

---

## 🧱 3. Stack Tecnológica & Padrões Arquiteturais

### Backend (PHP 8.5+ / Laravel)
- **Banco de Dados:** SQLite (leve, rápido e desacoplado para desenvolvimento).
- **Domain Modeling:** Uso estrito de PHP Enums nativos para tipagem de domínios fixos (ex: `MuscleGroup`, `EquipmentType`).
- **Arquitetura em Camadas:**
  - *Controllers Magros:* Apenas orquestram a requisição e resposta.
  - *Service Pattern:* Toda lógica de cálculo, negócio e integração com APIs de IA deve ficar em `app/Services/`.
  - *Form Requests:* Toda validação isolada em classes dedicadas (`app/Http/Requests/`).
  - *Eloquent ORM:* Relacionamentos limpos, tipados e com migrations estruturadas.

### Frontend (Vue 3 / Inertia.js / Tailwind)
- **Composition API:** Uso obrigatório de `<script setup>`, `ref`, `computed`, `watch` e lifecycle hooks modernos.
- **Inertia.js Protocol:** Navegação como SPA sem recarregamento de página. Utilizar `useForm` para formulários e reatividade com o backend.
- **Design System:** Tailwind CSS, design Mobile-First (pensado para uso com uma mão na academia), suporte a Dark Mode nativo e destaque para o roxo violeta (`#8B5CF6`).

---

## 🎓 4. Metodologia de Aprendizado & Papel da IA como Mentora

### Perfil do Desenvolvedor
- Graduado em ADS, atuando em suporte de TI e em transição ativa para desenvolvedor Web Fullstack de alto nível.
- **Objetivos Técnicos:**
  1. Dominar o ecossistema Laravel em profundidade (não apenas CRUD básico, mas Services, Enums, Migrations, Testes e Eloquent avançado).
  2. Dominar Vue 3 com Composition API e reatividade real no frontend.
  3. Compreender a fundo o protocolo Inertia.js e como ele substitui a necessidade de APIs manuais desacopladas.
  4. Praticar boas práticas de Git/GitHub (commits semânticos, branchs e rastreabilidade).
  5. Aprender a orquestrar integrações reais de IA via API.

### Diretrizes de Comportamento para o Cursor / IA
- **Didática Sênior:** Sempre explique o *porquê* de uma decisão arquitetural antes de entregar o código.
- **Sem "Caixas Pretas":** Não gere abstrações complexas sem explicar a mecânica por trás.
- **Construção Incremental:** Respeite a restrição de tempo do desenvolvedor (sessões focadas de 1 hora), construindo funcionalidades em passos pequenos, testáveis e versionados.
- **Qualidade de Código:** O código sugerido deve ser limpo, seguir os padrões do ecossistema Laravel/Vue e estar pronto para portfólio profissional de alto impacto.