# Skill: Vue 3 + Inertia (Arquitetura e Componentização)

1. **Proibido Monólitos (Regra de Ouro):** 
   - NUNCA crie componentes de página (`Pages/*.vue`) com mais de 200 linhas de template. 
   - Extraia blocos lógicos para a pasta `resources/js/Components/`. Exemplos: Se a página tem um cronômetro, crie `Components/Workout/RestTimer.vue`. Se tem um card de série, crie `Components/Workout/SetRow.vue`.

2. **Dumb vs Smart Components:**
   - **Páginas (Smart):** Apenas os arquivos em `Pages/` devem se comunicar com o backend via Inertia (`useForm`, `router`).
   - **Componentes (Dumb):** Arquivos em `Components/` são estúpidos. Eles recebem dados exclusivamente via `defineProps()` e comunicam ações de volta ao pai EXCLUSIVAMENTE via `defineEmits()`. Não injete estado global ou chamadas de backend dentro de um botão ou card.

3. **Reaproveitamento Extremo (DRY UI):**
   - NUNCA escreva tags nativas como `<button class="bg-violet-600 rounded...">` ou `<input type="text" class="border-gray-300...">` do zero.
   - OBRIGATORIAMENTE use os componentes primitivos do projeto (ex: `<PrimaryButton>`, `<TextInput>`, `<InputLabel>`, `<Modal>`). Se o design system já existe, obedeça-o.

4. **Inertia Mutações:**
   - Para formulários, use APENAS `const form = useForm({...})`.
   - Para chamadas contínuas na mesma página (ex: salvar 1 série sem perder o foco das outras), use OBRIGATORIAMENTE as opções de preservação: `{ preserveScroll: true, preserveState: true }`.