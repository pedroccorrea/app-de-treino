# Invariantes: Fluxos de Navegação e Preservação de Origem (UX)

## 1. Regra do return_to (Preservação de Contexto)
Nenhum controller ou botão de interface pode usar redirecionamentos fixos quando uma ação puder ser iniciada de mais de um lugar.

BAD Pattern (Redirecionamento Cego):
- O usuário estava em /programs/4, criou um treino e o controller fez return redirect()->route('workouts.index'). O usuário perdeu o contexto de onde estava.

GOOD Pattern (Preservação de Origem):
- No Controller:
  $redirectTo = $request->input('return_to', route('workouts.index'));
  return redirect($redirectTo)->with('success', '...');

## 2. Fluxo de Criação Fluido (Criar ➔ Editar Exercícios Imediatamente)
- Uma Ficha de Treino nasce com 0 exercícios. Redirecionar o usuário para a listagem com a ficha vazia é uma péssima UX.
- Ao criar uma Ficha dentro de um Programa (/programs/{id}), o sistema DEVE redirecionar imediatamente para a tela de edição daquela ficha (/workouts/{id}/edit?return_to=/programs/{id}), permitindo ao usuário adicionar os exercícios na hora.

## 3. Botões "Voltar" Contextuais
- Botões de "Voltar" no Vue NUNCA devem ter links fixos hardcoded se a tela tiver múltiplos pontos de entrada.
- Devem ler a prop/query return_to ou usar navegação contextual inteligente:
  <Link :href="returnUrl || route('workouts.index')">← Voltar</Link>