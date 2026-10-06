# Dinheiro em centavos inteiros

> Plan from this document. Each slice below carries its own shape - copy it, do not re-derive it.
> Status: confirmed by diashelter, 2026-10-06

## Situation

- Project: em construção. É um projeto de estudo, sem produção e sem dados reais; o banco é recriado pelo `make setup` e pelo `make fresh`.
- Decision: aberta. Confirmada nesta discovery como exercício do padrão de dinheiro em centavos inteiros, não como correção de bug.
- In flight: segue o precedente de `stocks.quantity`, que já é um inteiro protegido por `CHECK (quantity >= 0)`. Nada em andamento toca dinheiro.
- At stake: se der errado, desfaz-se em uma tarde. Não há dados para migrar nem cliente externo da API além do frontend do próprio repositório.

## Problem

Hoje o dinheiro já é exato em todas as camadas. O banco usa `decimal(10,2)` e `decimal(12,2)`, a API trafega strings e os cálculos do backend usam `BcMath\Number`. Ninguém é prejudicado pelo formato atual. A mudança existe para praticar o padrão que o mercado usa para dinheiro (inteiro na menor unidade da moeda) e tirar do caminho as duas conversões que ele torna desnecessárias:

- o backend faz aritmética com uma classe de precisão arbitrária para somar valores que cabem em um `int`;
- o frontend interpreta strings decimais para chegar aos centavos que já usa internamente.

Achado lateral: `BcMath\Number` só existe a partir do PHP 8.4, mas o `composer.json` declara `"php": "^8.3"`. Com o código atual, essa restrição de versão não é verdadeira.

## Success

- Worked if:
  - nenhum uso de `BcMath\Number` no backend;
  - as quatro colunas de dinheiro são `bigint` com CHECK de não negativo;
  - nenhuma resposta ou requisição da API carrega dinheiro como string decimal;
  - o frontend só converte valores para exibir (centavos → R$) e na entrada do formulário de produto (R$ → centavos);
  - `make test` e Pint passam.
- Going wrong: a multiplicação ou divisão por 100 aparece no backend, ou em mais de um ponto do frontend. Nos dois casos, a fronteira da conversão vazou.

## Boundary

In: `products.price`, `order_items.unit_price`, `order_items.subtotal` e `orders.total`, com os models, as validações, os resources, as factories e o seeder correspondentes. Também entram o cálculo do carrinho e do checkout, o contrato da API para o frontend, a exibição e o formulário de produto no frontend, e a atualização do README.

Out:
- Value object `Money` e suporte a mais de uma moeda: só compensariam com descontos, cupons ou frete, e o README deixa tudo isso fora do escopo.
- Compatibilidade com o contrato antigo (aliases `price`, `total`…): o único consumidor é o frontend do repositório, que muda junto.
- Migration de alteração com backfill: não existem dados a preservar (Key decision 5).

Unchanged:
- Os valores de `sort` em `GET /api/products` (`price_asc`, `price_desc`). São nomes de ordenação, não valores.
- As chaves `total` das séries do dashboard e o `meta.total` da paginação. São contagens, não dinheiro.
- As colunas `quantity`.
- A extensão `bcmath` no Dockerfile e a restrição `"php": "^8.3"` no `composer.json`. Depois da mudança essa restrição passa a ser verdadeira.

## Shape

Todo valor monetário passa a ser um inteiro de centavos, com a unidade no nome do campo, do banco até o estado do frontend. A conversão para reais acontece em um só lugar, o frontend, e só na exibição e na digitação do preço pelo admin. A decisão difícil de desfazer é o contrato da API: os nomes e os tipos mudam sem período de compatibilidade, então backend e frontend chegam juntos.

A alternativa mais pesada é um value object `Money` (centavos + moeda), com cast próprio e compartilhado entre Catalog e Ordering. Ela só ganha quando houver mais de uma moeda ou regras de preço (desconto, cupom, frete), e nenhuma das duas coisas está no escopo.

## Key decisions

1. **Dinheiro é um inteiro de centavos em todas as camadas: banco, backend, requisição e resposta da API, e estado do frontend.** Nenhuma camada usa `float` ou string decimal. A única conversão de unidade está no frontend: centavos → texto em R$ na exibição e texto digitado → centavos no formulário de produto.
2. **A unidade fica no nome: `price_cents`, `unit_price_cents`, `subtotal_cents` e `total_cents`, no banco e na API.** Os nomes antigos deixam de existir, sem alias. Assim, um consumidor que não foi atualizado quebra na checagem de tipos do frontend em vez de mostrar R$ 1.990,00 no lugar de R$ 19,90. Por isso backend e frontend são entregues no mesmo pull request.
3. **As quatro colunas são `bigint` com `CHECK (<coluna> >= 0)`.** O banco garante o "não negativo", como já faz em `stocks.quantity`. O `bigint` mantém a faixa atual: o preço máximo aceito hoje, R$ 99.999.999,99, são 9.999.999.999 centavos e não cabem em `integer`. A regra de negócio "preço ≥ 1 centavo" fica na validação, não no banco.
4. **Subtotal e total continuam calculados no backend a partir do preço salvo no banco, e qualquer valor enviado pelo cliente continua sendo ignorado.** O subtotal é o preço unitário vezes a quantidade e o total é a soma dos subtotais, tudo em `int` nativo. Não há risco de overflow nos limites atuais: 9.999.999.999 × 99 (quantidade máxima por item) fica muito abaixo do limite do `int` do PHP e do inteiro seguro do JavaScript (≈ 9 × 10¹⁵).
5. **As migrations originais são editadas no lugar, sem migration de alteração.** Não há dados a preservar e o projeto já recria o banco no `make setup` e no `make fresh`. Quem tem o ambiente antigo roda `make fresh`.

## Work

| Slice | Delivers | Status |
|---|---|---|
| [Product price](#product-price) | `products.price_cents` validado, salvo, ordenado e exposto como inteiro | clear |
| [Order money](#order-money) | carrinho, checkout e pedidos calculando e expondo centavos inteiros | clear |
| [Frontend money](#frontend-money) | frontend consumindo, guardando e formatando centavos; formulário de produto enviando centavos | clear |

Order: Product price → Order money → Frontend money, no mesmo pull request (Key decision 2).

Already handled by existing code: o produto alterado depois do pedido continua sendo resolvido pelo snapshot em `order_items` e só muda o nome da coluna. A recusa de preço vindo do cliente continua sendo feita pelo `ValidateCartRequest`, que só aceita `product_id` e `quantity`.

Derivable from the repository, left to the plan:
- mensagens de validação no formato dos outros `ApiFormRequest`;
- forma dos resources, como `ProductResource` e `OrderResource` já fazem;
- valores de exemplo das factories, como `ProductFactory` já gera hoje (agora em centavos);
- atualização do README nos pontos que citam `decimal`, `BcMath\Number` e os nomes antigos (tabela de modelo de dados, fluxo do checkout e "Escopo e decisões"), como pede o `AGENTS.md`.

### Product price

**Delivers** o preço do produto como `price_cents` em todo o Catalog. **Status: clear.**

| State | What should happen | Caller sees |
|---|---|---|
| Admin cria ou edita um produto com `price_cents` 19990 | Salva 19990 | `201` / `200`, `price_cents: 19990` |
| `price_cents` igual a 0 ou negativo | Recusado: preço mínimo de 1 centavo | `422` em `price_cents` |
| `price_cents` não inteiro (`"199.90"`, `199.5`) | Recusado | `422` em `price_cents` |
| `price_cents` acima de 9.999.999.999 | Recusado (mantém o teto atual de R$ 99.999.999,99) | `422` em `price_cents` |
| Vitrine ordenada por `price_asc` / `price_desc` | Ordena por `price_cents`, desempatando pelo `id` como hoje | `200`, ordem igual à atual |
| Escrita direta no banco com valor negativo | Recusada pelo CHECK (Key decision 3) | erro de banco, sem linha gravada |

`GET /api/products` · `GET /api/products/{product}` → `200` produto com `price_cents: int`
`POST /api/admin/products` · `PUT /api/admin/products/{product}` `{ ..., price_cents: int }` → `201` / `200` produto com `price_cents: int`

Table `products`: `price decimal(10,2)` → `price_cents bigint`.

| Column | Type | Null | References | Note |
|---|---|---|---|---|
| `price_cents` | bigint | no | | `CHECK (price_cents >= 0)`; índice, como o atual `price` |

### Order money

**Delivers** carrinho, checkout e pedidos com valores em centavos inteiros. **Status: clear.** É a fatia que contém o contrato mais consumido (Key decision 2).

| State | What should happen | Caller sees |
|---|---|---|
| Carrinho validado com produtos disponíveis | Recalcula a partir de `products.price_cents` (Key decision 4) | `200`, `unit_price_cents`, `subtotal_cents` e `total_cents` inteiros |
| Linha com produto inexistente | Linha inválida, fora do total, como hoje | `unit_price_cents: null`, `subtotal_cents: null` |
| Linha indisponível (estoque ou status) | Linha mostra o subtotal, mas fica fora do total, como hoje | `subtotal_cents` inteiro, `is_valid: false` |
| Cliente envia preço ou total no corpo | Ignorado | o mesmo total calculado pelo banco |
| Pedido criado | Grava o snapshot `unit_price_cents` e `subtotal_cents` nos itens e `total_cents` no pedido | `201`, pedido com `total_cents` e itens em centavos |
| Preço do produto alterado depois do pedido | O item mantém o `unit_price_cents` do snapshot | pedido antigo inalterado |

`POST /api/cart/validate` `{ items: [{ product_id, quantity }] }` → `200` `{ items: [{ ..., unit_price_cents: int|null, subtotal_cents: int|null }], total_cents: int, is_valid }`
`POST /api/orders` → `201`; `GET /api/orders` · `GET /api/orders/{order}` · `POST /api/orders/{order}/payment` · `GET /api/admin/orders` · `GET /api/admin/orders/{order}` · `GET /api/account` · `GET /api/admin/users/{user}` → pedido com `total_cents: int` e, quando houver itens, `items[].unit_price_cents: int` e `items[].subtotal_cents: int`

Table `orders`: `total decimal(12,2)` → `total_cents bigint`. Table `order_items`: `unit_price decimal(10,2)` → `unit_price_cents bigint`; `subtotal decimal(12,2)` → `subtotal_cents bigint`.

| Column | Type | Null | References | Note |
|---|---|---|---|---|
| `orders.total_cents` | bigint | no | | `CHECK (total_cents >= 0)`; soma dos `subtotal_cents` |
| `order_items.unit_price_cents` | bigint | no | | `CHECK (unit_price_cents >= 0)`; snapshot de `products.price_cents` |
| `order_items.subtotal_cents` | bigint | no | | `CHECK (subtotal_cents >= 0)`; `unit_price_cents × quantity` |

### Frontend money

**Delivers** o frontend tratando dinheiro como centavos do início ao fim. **Status: clear.**

| State | What should happen | Caller sees |
|---|---|---|
| Valor em centavos vindo da API | Formatado em BRL só para exibir | `R$ 1.299,90` a partir de `129990` |
| Admin digita `199,90` ou `199.90` no formulário de produto | Converte para 19990 antes de enviar | produto salvo com R$ 199,90 |
| Admin digita algo que não é um valor em reais | Não envia ou mostra o erro do campo | erro no campo de preço (o backend responde em `price_cents`) |
| Produto existente aberto para edição | O campo mostra o valor em reais, convertido de `price_cents` | `199,90` |
| Carrinho salvo no navegador antes da mudança (preço como string) | Descartado ao carregar; o cliente adiciona os produtos de novo | carrinho vazio |
| Total do carrinho na tela | Soma dos `price_cents × quantidade` guardados, sem interpretar strings | o mesmo total exibido antes |

Alternatives considered:
- Converter o carrinho antigo em vez de descartar. Ganharia se existissem clientes reais com carrinho salvo, e não existem.
- O backend aceitar reais no formulário de produto. Ganharia se outro cliente da API digitasse preços, mas quebraria a simetria do contrato (Key decision 1).

## Sources

- `README.md`, seção "Escopo e decisões": registra a decisão atual de dinheiro em `decimal` e o que fica fora do escopo (descontos, cupons, frete).
- `AGENTS.md`: exige que README e análise de domínio sejam atualizados na mesma entrega.
