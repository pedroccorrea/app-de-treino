# Regras Operacionais da IA para o Projeto (Meu App de Treino)

1. **Contexto Geral & Visão do Produto:**
   - Leia e siga ESTRITAMENTE todas as diretrizes, roadmap e padrões definidos no arquivo `PROJECT_CONTEXT.md`.

2. **Diretrizes de Implementação:**
   - **Backend:** Laravel com PHP 8.5+, SQLite, Service Pattern para regras de negócio (`app/Services/`), FormRequests para validação, e PHP Enums para tipos fixos.
   - **Frontend:** Vue 3 estritamente com `<script setup>` (Composition API), Inertia.js para comunicação e navegação SPA, Tailwind CSS (Mobile-First, Dark mode, acento roxo violeta `#8B5CF6`).

3. **Didática & Mentoria:**
   - O desenvolvedor prioriza o entendimento dos conceitos e da arquitetura.
   - Sempre explique brevemente o "porquê" das decisões arquiteturais tomadas.
   - Entregue código modular, limpo e legível.

### 📖 Diretriz Obrigatória de Explicação Didática (Pós-Implementação):
Sempre que concluir a criação de uma funcionalidade ou refatoração, finalize a resposta OBRIGATORIAMENTE com estas 3 seções:

1. **🗺️ O Fluxo Humano de Execução (A Jornada do Dado):**
   - Narrativa cronológica e simples da experiência: *"Passo 1: Usuário clica em [Botão] na tela [X] ➔ Passo 2: Dispara a função [Y] no Vue que envia para a rota [Z] via Inertia ➔ Passo 3: O Controller [A] aciona o Service [B] que valida e grava na tabela [C] ➔ Passo 4: O Inertia devolve a resposta e atualiza o componente [D] com as novas props."*

2. **📂 Roteiro de Leitura de Código (Ordem Recomendada):**
   - Lista numerada da ordem exata de arquivos que o desenvolvedor deve abrir para estudar a lógica sem se perder (ex: 1º Migration, 2º Route, 3º Service, 4º Controller, 5º Componente Vue).

3. **🧠 Ponto Chave de Aprendizado (Conceitos Mestres):**
   - **No Laravel:** Qual foi o conceito mais importante aplicado aqui (ex: *Service Pattern*, *Eager Loading com `with()`*, *Database Transactions*, *FormRequests*) e por que ele é crucial para um dev pleno/sênior?
   - **No Vue 3:** Qual foi o conceito reativo mais importante aplicado aqui (ex: *Reatividade com `ref/reactive`*, *Computed properties para filtros*, *Emissão de eventos customizados*, *Estado local vs Props do Inertia*) e como ele resolveu o problema da interface?