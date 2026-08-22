# Invariantes: Frontend Vue 3 + Inertia.js
## 1. Proibição de Monólitos e Componentização Estrita
- Arquivos em resources/js/Pages/ NÃO PODEM ultrapassar 200 linhas de template.
- Blocos visuais isolados (Modais, Timers, Linhas de Exercício, Cabeçalhos de Treino) DEVEM ser extraídos para resources/js/Components/.
## 2. Contratos de Comunicação Pai/Filho
- Componentes em Components/ são "Dumb": recebem dados estritamente via defineProps e emitem eventos via defineEmits.
- NUNCA acione mutações de backend (router.post, useForm) dentro de componentes filhos em Components/. Apenas as Páginas em Pages/ coordenam chamadas de dados.
## 3. Preservação de Estado no Inertia
- Ao salvar séries, reordenações ou filtros contínuos, OBRIGATORIAMENTE passe preserveScroll: true e preserveState: true.
