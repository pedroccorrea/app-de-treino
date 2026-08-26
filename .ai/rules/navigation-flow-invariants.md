# Invariantes: Fluxos de Navegação e Preservação de Origem (UX)

## 1. Regra do return_to (Preservação de Contexto)
Nenhum controller ou botão de interface pode usar redirecionamentos fixos quando uma ação puder ser iniciada de mais de um lugar.
- Toda rota de criação, edição ou exclusão deve aceitar e propagar o parâmetro `return_to`.
- Ao concluir a ação no Controller, redirecione para `request('return_to')` se fornecido.

## 2. Separação de Visualização vs. Edição
- Clicar no card de um treino SEMPRE abre a tela de visualização pronta para iniciar (`Workouts/Show.vue`).
- A tela de edição (`Workouts/Edit.vue`) só deve ser aberta se o usuário clicar intencionalmente no ícone de Lápis.
- Ao salvar a edição em `Workouts/Edit.vue`, o usuário deve ser mantido na mesma página de edição (ou redirecionado para o `return_to` fornecido) com os dados atualizados.

## 3. Criação de Treino dentro de Programa
- Ao criar um treino dentro de um programa (`Programs/Show.vue`), o modal fecha e o usuário permanece na mesma página do programa (`Programs/Show.vue`), exibindo a lista atualizada com o novo treino recém-criado.

## 4. Botões "Voltar" Contextuais
- Botões de "Voltar" no Vue NUNCA devem ter links fixos hardcoded se a tela tiver múltiplos pontos de entrada.
- Devem ler a prop/query `return_to` para devolver o usuário exatamente para a tela de onde ele veio, no mesmo estado.