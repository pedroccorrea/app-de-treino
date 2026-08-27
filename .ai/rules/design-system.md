# Invariantes: Design System Setwave (Nocturne)

Fonte de tokens: `resources/css/design-tokens.css`. Mapeamento Tailwind: `tailwind.config.js` (`theme.extend.colors`/`borderRadius`/`backgroundImage`).

**Regra de ouro: nunca escrever cor em hexadecimal direto no componente; sempre usar o token** (`text-accent`, `bg-surface-raised`, `border-[color:var(--danger)]`, etc.). `tests/Architecture/ArchitecturalRulesTest.php` reprova qualquer `.vue` em `resources/js/` com um hex literal (`#RRGGBB`), exceto `resources/js/Pages/Welcome.vue` (scaffold do Breeze, fora do design system).

## Paleta e quando usar cada token

### Superfícies
| Token | Valor | Uso |
|---|---|---|
| `--surface-base` | `#0B0F17` | Fundo de página/tela cheia. |
| `--surface-raised` | `#141824` | Cards, a superfície "elevada" padrão (`BaseCard`). |
| `--surface-overlay` | `#1C2130` | Modais, drawers, estado hover/active de controles interativos sobre `surface-raised`. |

### Acentos
| Token | Valor | Uso |
|---|---|---|
| `--accent` | `#8B5CF6` | Bordas e ícones de ênfase primária. **Nunca como preenchimento sólido** — ver "Uso parcimonioso do roxo" abaixo. |
| `--accent-muted` | `rgba(139,92,246,.14)` | Fundo tingido por trás de um elemento com borda `--accent` (botão primário, badge de destaque). |
| `--accent-glow` | `rgba(139,92,246,.45)` | Cor do `box-shadow`/`drop-shadow` de destaque em torno de um elemento acentuado. |
| `--accent-text-strong` | `#EDE9FE` | Texto do rótulo do botão primário (ex: "Concluir série", "Aplicar 85 kg"). |
| `--accent-text-soft` | `#C4B5FD` | Ícones/spinner sobre superfície acentuada, chips de destaque secundário (ex: badge de delta "+2,5 kg"), texto de link em hover. |
| `--accent-label` | `#A78BFA` | Rótulo em caixa alta com tom de acento (`SectionLabel tone="accent"`, ex: "SUGESTÃO DE CARGA"). Não funde com `--accent-text-soft`: papel distinto (rótulo vs. texto/ícone de botão), gap de luminância de 18%. |

### Estados
| Token | Valor | Uso |
|---|---|---|
| `--success` | `#34D399` | Texto/ícone/borda de estado positivo, sobre superfície escura (NÃO como preenchimento sólido com texto branco — ver contraste abaixo). |
| `--danger` | `#F87171` | Texto/ícone/borda de ação destrutiva (`BaseButton variant="danger"`). Mesmo padrão "tinted", nunca preenchimento sólido. |
| `--warning` | `#FBBF24` | Texto/ícone/borda de estado de atenção. |
| `--text-on-accent` | `#FFFFFF` | Reservado para texto sobre `--accent` **sólido** (não o padrão tingido usado hoje). Ver ressalva de contraste abaixo antes de usar. |

### Bordas
| Token | Valor | Uso |
|---|---|---|
| `--border-subtle` | `rgba(255,255,255,.08)` | Borda hairline decorativa padrão de cards/botões secundários. |
| `--border-accent` | `rgba(139,92,246,.60)` | Borda de elemento com ênfase de acento (botão primário, card de sugestão). |

### Raios de borda
`--radius-sm` (12px, chips pequenos) · `--radius-md` (16px, botões e steppers — `rounded-radius-md`) · `--radius-lg` (22px, cards e o CTA "hero" — `rounded-radius-lg`) · `--radius-full` (999px, pills/badges).

### Espaçamento e alturas de toque
A escala `--space-1..8` (4/8/12/16/20/24/32px) **espelha a escala numérica padrão do Tailwind** — use as utilities normais (`p-4`, `gap-2`, `gap-6`...), não é preciso reconfigurar nada. Os tokens existem para uso em CSS puro/inline fora de classes Tailwind.

Alturas de toque mínimas:
- **56px para ação primária** (`--touch-primary`, equivalente a `h-14`). Exceção documentada: o único CTA "hero" do app (botão "Concluir série") usa 68px — ver `BaseButton size="hero"`.
- **48px para ação secundária/ícone** (`--touch-min`, equivalente a `h-12`/`w-12`).

## Escala tipográfica

- **Números-herói: 82px, `font-weight: 500`, `letter-spacing: -0.045em`, `line-height: 0.86`, com `font-variant-numeric: tabular-nums`.** Usado para carga e reps em execução (`LoadRepsHero`). `tabular-nums` é obrigatório sempre que o número mudar dinamicamente (evita o layout "pular" quando os dígitos trocam de largura).
- Números secundários de destaque (ex: carga sugerida no `OverloadSuggestionCard`): 60px / 52px conforme o contexto, mesmo peso/tracking, mesma regra de `tabular-nums`.
- Rótulo em caixa alta (`SectionLabel`): 11px, uppercase, `letter-spacing: 0.2em`.
- Corpo/legendas: 12–15px conforme a tabela de texto abaixo.

### Tokens de texto: papel e contraste verificado

Todos os valores abaixo são a razão de contraste WCAG (luminância relativa) contra os fundos reais onde cada token aparece no app. Mínimo AA para texto normal: **4.5:1**.

| Token | Valor | Papel | vs. `--surface-base` | vs. `--surface-raised` | vs. `--surface-overlay` |
|---|---|---|---|---|---|
| `--text-primary` | `#F2F2F5` | Títulos e os números-herói — máxima ênfase, máximo contraste. | 17,17:1 | 15,85:1 | 14,35:1 |
| `--text-secondary` | `#8A8A99` | Rótulos de campo em caixa alta ("Carga", "Reps"), sub-rótulos de card ("Próxima série", "Anterior"), legendas de metadado. | 5,65:1 | 5,21:1 | 4,72:1 |
| `--text-body` | `#A9A9B8` | Parágrafo de corpo — texto corrido (ex: o `rationale` da sugestão de IA). Diferente de `secondary`: parágrafo usa `body`, rótulo usa `secondary`. | 8,27:1 | 7,63:1 | 6,91:1 |
| `--text-tertiary` | `#8E8EA0` | Texto de apoio inline, menor ênfase que `secondary` (ex: "Última:", "Descanso", "Pular exercício"). Nota: por causa do ajuste de contraste, `tertiary` ficou muito perto de `secondary` em claridade (5,96:1 vs 5,65:1 contra a base) — a distinção entre os dois é de **papel** (rótulo vs. texto de apoio), não de brilho. | 5,96:1 | 5,51:1 | 4,99:1 |
| `--text-numeric` | `#C9C9D4` | Dígitos/glifos de UI que precisam ler bem sem competir com os números-herói (dígitos do timer de descanso, ícones ± dos steppers). É o mais claro dos quatro tons "não-primary". | 11,68:1 | 10,78:1 | 9,76:1 |

Cores de acento sobre superfície escura (uso típico — texto/ícone sobre fundo tingido, não sobre `--accent` sólido):

| Token | Valor | vs. `--surface-raised` |
|---|---|---|
| `--accent-text-strong` | `#EDE9FE` | 14,92:1 |
| `--accent-text-soft` | `#C4B5FD` | 9,59:1 |
| `--accent-label` | `#A78BFA` | 6,51:1 |

Estados como texto/ícone sobre superfície escura:

| Token | vs. `--surface-base` | vs. `--surface-raised` |
|---|---|---|
| `--success` | 9,98:1 | 9,21:1 |
| `--danger` | 6,94:1 | 6,40:1 |
| `--warning` | 11,49:1 | 10,61:1 |

**Ressalva importante — `--text-on-accent` sobre `--accent` sólido**: `#FFFFFF` sobre `#8B5CF6` dá **4,23:1**, abaixo do mínimo AA de 4,5:1 para texto normal (passa para texto grande/negrito ≥18,66px ou para componentes não-textuais, WCAG 1.4.11, que exige só 3:1). Da mesma forma, texto branco sobre `--danger` sólido dá **2,77:1** — falha mesmo o limite de texto grande. **Por isso nenhum componente base usa acento/estado como preenchimento sólido**: `BaseButton` variant `primary` usa `--accent-muted` (fundo tingido, ~10-14% de opacidade) + `--accent-text-strong` (quase branco) por cima, o que mantém o fundo efetivo perto do escuro e o contraste real acima de 14:1. Se um preenchimento sólido de acento for necessário no futuro, use `--text-on-accent` só em texto grande/negrito ou ícones, nunca em corpo de texto pequeno.

## Uso parcimonioso do roxo

`--accent` é **acento, nunca preenchimento**: aparece como borda, ícone ou glow — o fundo por trás de um elemento acentuado é sempre `--accent-muted` (uma tinta de 10-14% sobre a superfície escura), nunca `--accent` sólido. Isso é o que mantém o roxo lendo como destaque em vez de virar a cor de fundo padrão da interface, e é também o que garante o contraste de texto descrito acima.

## Regra da zona do polegar

Em qualquer tela de execução (sessão de treino ativa), as ações primárias — completar uma série, aplicar uma sugestão de carga — devem estar posicionadas nos **200px inferiores da viewport**, a região alcançável pelo polegar em uso de uma mão. É por isso que `CompleteSetButton`/`BaseButton size="hero"` fica na parte inferior fixa do card do exercício, não no topo.

## Componentes base disponíveis (`resources/js/Components/UI/`)

### `BaseButton.vue`
Props: `variant` (`primary` | `secondary` | `danger` | `ghost`, default `primary`) · `size` (`default` | `hero`, default `default` — `hero` é a exceção documentada de 68px) · `ripple` (Boolean, default `false` — mostra o rastro de onda; reservar para o CTA de maior ênfase da tela) · `iconOnly` (Boolean) · `disabled` (Boolean) · `type` (default `'button'`).
Emite: `click`.

### `BaseCard.vue`
Props: `padded` (Boolean, default `true`).
Slot padrão. Fundo `--surface-raised`, borda `--border-subtle`, raio `--radius-lg`.

### `BaseBadge.vue`
Props: `tone` (`accent` | `success` | `danger` | `warning` | `neutral`, default `accent`).
Slot padrão. Pill de `--radius-full`.

### `SectionLabel.vue`
Props: `tone` (`secondary` | `accent`, default `secondary`).
Slot padrão. Rótulo 11px uppercase, `tracking: 0.2em`.

## Auditoria de contraste (Phase 14)

Qualquer par texto/fundo novo introduzido na propagação do design system às telas existentes deve ser medido (luminância relativa WCAG) e documentado nesta tabela antes de ser considerado aprovado — não basta "parecer legível". Ajustes feitos durante a Phase 14 devem ser registrados aqui como uma nova entrada, com o par, o valor antes/depois e o motivo.
