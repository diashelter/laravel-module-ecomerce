# Listas de domínio tipadas - checks

Profile: light
Plan: `.specs/features/typed-domain-lists/plan.md`

## Intent

22 checks in 4 slices · 2 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. Nenhuma prova depende do frontend. Os nomes de teste não usam parênteses nem colchetes, porque o `--filter` do Pest é uma expressão regular.

## Checks

### S1 - Carrinho e checkout tipados · 20 files · 49 KB · ~12k

**C1** - `POST /api/cart/validate` com os itens `[{A, 2}, {A, 3}, {B, 1}]`, para produtos ativos e com estoque e `A < B`, responde `200` com `data.items` de 2 linhas em ordem crescente de `product_id`. A linha `A` tem `quantity` `5`, `data.total_cents` é `5 × A.price_cents + B.price_cents` e `data.is_valid` é `true` (AC 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="merges repeated lines and totals the cart in product order"`

**C2** - Uma linha com o produto `999999` volta com `unit_price_cents` `null`, `subtotal_cents` `null`, `available_quantity` `0`, `is_available` `false` e `problem` `"Produto não encontrado."`. Ela fica fora de `total_cents`, e `data.is_valid` é `false` (AC 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps a missing product line out of the cart total"`

**C3** - Uma linha com produto de estoque `0` volta com `subtotal_cents` igual a `unit_price_cents × quantity` e fica fora de `total_cents`, que é igual à soma das outras linhas; `data.is_valid` é `false` (AC 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps an out of stock line out of total_cents"`

**C4** - Em `POST /api/cart/validate`:
- `data` tem exatamente as chaves `items`, `total_cents` e `is_valid`;
- cada item tem exatamente `product_id`, `name`, `image_url`, `unit_price_cents`, `quantity`, `subtotal_cents`, `available_quantity`, `is_available` e `problem`;
- a asserção usa a lista exata de chaves, não um subconjunto (AC 4).

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the exact cart validation response shape"`

**C5** - `POST /api/orders` com 2 produtos responde `201`, e no banco `orders.total_cents` é igual a `SUM(order_items.subtotal_cents)` do pedido. Cada item tem `subtotal_cents = unit_price_cents × quantity` (AC 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="stores an order total equal to the sum of its item subtotals"`

**C6** - `POST /api/orders` pedindo mais unidades do que o estoque tem responde `409` com erros em `items.{product_id}`. Nenhum pedido é criado e `stocks.quantity` não muda (AC 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns 409 and changes nothing when stock is insufficient"`

**C7** - Um `CartDTO` com os itens `(7, 1)`, `(3, 1)` e `(7, 5)` produz um `ProductQuantities` que itera `3 => 1` e depois `7 => 6`, e cujo `ProductIds` itera `[3, 7]` (AC 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="groups the cart into product quantities in ascending product order"`

**C8** - Um `ValidatedCart` com uma linha sem problema de subtotal `2000` e uma linha com problema de subtotal `500` informa `total_cents` `2000` e `is_valid` `false` (AC 8)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="derives the validated cart total from the lines without a problem"`

**C9** - Um `ValidatedCart` sem linhas informa `total_cents` `0` e `is_valid` `false` (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="treats an empty validated cart as invalid"`

**C10** - Um `OrderLine` com `unit_price_cents` `19990` e `quantity` `3` tem subtotal `59970`, e um `OrderLines` com ele e uma linha `1000 × 2` informa total `61970` (AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="derives the order line subtotal and the order total"`

**C11** - `CartDTO`, `OrderLines` e `ValidatedCart` construídos com um `stdClass` entre os elementos lançam `TypeError`. O teste é um dataset com as 3 classes (AC 11)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an element of another type in a typed list"`

**C12** - Dentro de uma transação, `StockReservation::lockForProducts` com `ProductIds` `[second, first]` devolve os estoques indexados por `product_id`, com as chaves em ordem crescente de `product_id` (AC 12)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="locks the stock rows of the given products keyed by product id"`

**C13** - `CheckoutService` recebe um `ProductQuantities` de `1 => 3` e `2 => 3`, com preços `1990` e `10`, e devolve um `OrderLines` com subtotais `5970` e `30` e total `6000`. A prova fica na camada do service, além da prova de borda em C5 (AC 5, AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="builds the order lines and total in cents"`

### S2 - Ids de categoria e de produto tipados · 8 files · 15 KB · ~4k

**C14** - `ProductIds`, `CategoryIds` e `CustomerIds` construídos com `0`, com `-1` ou com um id repetido (`[4, 4]`) lançam `InvalidArgumentException`. O teste é um dataset com as 3 classes × 3 entradas (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an invalid id in a typed id list"`

**C15** - O admin envia `POST /api/admin/products` com `category_ids: [c1, c2]` e recebe `201`, e o produto pertence exatamente a `c1` e `c2` em `category_product` (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a product in exactly the given categories"`

**C16** - O admin envia `PUT /api/admin/products/{product}` com `category_ids: [c2, c3]` para um produto em `c1` e `c2` e recebe `200`, e o produto passa a pertencer exatamente a `c2` e `c3` (AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="replaces the product categories on update"`

**C17** - `ProductRepository::findManyKeyedById` com `ProductIds` `[second, first, 999999]` devolve os 2 produtos existentes, indexados por id (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="finds many products keyed by id"`

### S3 - Contagem de pedidos por cliente · 4 files · 7 KB · ~2k

**C18** - Com um cliente de 2 pedidos e um sem nenhum na página, `GET /api/admin/users` responde `200` com `orders_count` `2` e `0` para eles (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists users with their order count including zero"`

**C19** - `OrderCountsByCustomer` consultado por um id de cliente que não tem entrada devolve `0`, e por um id com 3 pedidos devolve `3` (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers zero orders for a customer without orders"`

**C20** - Com 15 contas na página, `GET /api/admin/users` executa exatamente uma consulta com `from "orders"`, contada no query log (AC 19)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reads the order counts of a customers page in one query"`

### S4 - Nenhum array nas fronteiras de domínio · 2 files · 3 KB · ~1k

**C21** - Por reflexão, nenhum parâmetro ou tipo de retorno é `array` nos seguintes pontos (AC 20):
- os métodos públicos de `CartDTO`, `CartValidationService`, `CheckoutService`, `ValidateCartUseCase`, `PlaceOrderUseCase`, `ListCustomersUseCase` e `StockReservation`;
- o construtor de `ProductDTO`;
- `ProductRepository::findManyKeyedById`, `ProductRepository::syncCategories`, `OrderRepository::countPerCustomer` e `OrderRepository::createWithItems`.

O teste percorre os 12 alvos como tabela.
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares no array in the domain list signatures"`

**C22** - As classes de lista da door 1 (`CartDTO`, `ProductQuantities`, `ProductIds`, `CategoryIds`, `OrderLines`, `ValidatedCart`, `CustomerIds` e `OrderCountsByCustomer`) são `final` e `readonly` e implementam `IteratorAggregate` e `Countable`. `OrderLine` e `ValidatedCartLine` são `final` e `readonly` (AC 21)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="typed lists are final readonly iterable and countable"`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| estados de uma linha do carrinho (3) | disponível C1 · produto inexistente C2 · sem estoque C3 | - |
| chaves da resposta de `POST /api/cart/validate` (12) | `items` C4 · `total_cents` C4 · `is_valid` C4 · `product_id` C4 · `name` C4 · `image_url` C4 · `unit_price_cents` C4 · `quantity` C4 · `subtotal_cents` C4 · `available_quantity` C4 · `is_available` C4 · `problem` C4 | - |
| listas que rejeitam elemento de outro tipo (3) | `CartDTO` C11 · `OrderLines` C11 · `ValidatedCart` C11 | - |
| listas de id × entradas inválidas (9) | C14, table-driven sobre as 9 combinações de `ProductIds`, `CategoryIds` e `CustomerIds` com `0`, `-1` e `[4, 4]` | - |
| totais derivados das linhas (3) | carrinho C8, C9 · linha do pedido C10 · pedido C5, C10, C13 | - |
| ordem por `product_id` (2) | quantidades C7 · lock de estoque C12 | - |
| alvos da regra sem `array` (12) | `CartDTO` C21 · `CartValidationService` C21 · `CheckoutService` C21 · `ValidateCartUseCase` C21 · `PlaceOrderUseCase` C21 · `ListCustomersUseCase` C21 · `StockReservation` C21 · construtor de `ProductDTO` C21 · `ProductRepository::findManyKeyedById` C21 · `ProductRepository::syncCategories` C21 · `OrderRepository::countPerCustomer` C21 · `OrderRepository::createWithItems` C21 | - |
| classes da door 1 (10) | `CartDTO` C22 · `ProductQuantities` C22 · `ProductIds` C22 · `CategoryIds` C22 · `OrderLines` C22 · `ValidatedCart` C22 · `CustomerIds` C22 · `OrderCountsByCustomer` C22 · `OrderLine` C22 · `ValidatedCartLine` C22 | - |
| doors do `Landing` (2) | door 1 C11, C22 · door 2 C12, C21 | - |
| `POST /api/cart/validate` statuses (1) | 200 C1 | - |
| `POST /api/orders` statuses (2) | 201 C5 · 409 C6 | - |
| `POST /api/admin/products` statuses (1) | 201 C15 | - |
| `PUT /api/admin/products/{product}` statuses (1) | 200 C16 | - |
| `GET /api/admin/users` statuses (1) | 200 C18 | - |

- As afirmações sobre status, rota ou formato de resposta (C1 a C6, C15, C16, C18 e C20) têm prova que atravessa o HTTP.
- C7 a C11, C13, C14 e C19 provam as regras na camada da própria classe, além das provas de borda.
- C6, C12, C13 e C17 reaproveitam testes que já existem e passam a usar os novos tipos. O valor esperado continua o mesmo e nenhuma asserção é afrouxada. Os demais nomes são testes novos.

## Swept

- validation: C11, C14 - elemento de outro tipo e id inválido são recusados na construção. A validação HTTP (`ValidateCartRequest`, `ProductRequest`) não muda
- failure modes: C6 - estoque insuficiente reverte tudo e não deixa pedido parcial
- idempotency: n/a - nenhum caminho de escrita novo. O pedido continua sendo criado uma vez por requisição, e a aprovação de pagamento duplicada continua coberta por "announces OrderPaid only once for a duplicated payment approval"
- authorization: existing - `auth:sanctum`, `admin` e as policies não mudam, provados por "requires authentication for admin endpoints" e "forbids customers from admin endpoints"
- concurrency: C12 - o lock continua em ordem de `product_id` com o novo tipo; existing - "locks the stock rows with SELECT ... FOR UPDATE" e "never lets stock go negative"
- data lifecycle: n/a - nada gravado muda de forma (plan, Relations)
- dependency failure: n/a - o carrinho e o checkout não chamam nenhum serviço externo
- state transitions: n/a - nenhum status de pedido muda. `OrderStatus::Placed` continua sendo o status de criação
- observability: n/a - nenhum log ou métrica existe nesses caminhos, e nenhum é adicionado

## Handoff

- S1 = 12k, no Ordering, no contrato do Inventory e no `ProductRepository`; S2 entra no Catalog com 16k acumulados; S3 entra no Customers com 18k; S4 fecha com 19k. Fica abaixo do orçamento padrão de 150k, então um builder só.
- Mechanism: one builder (cabe no orçamento, sem pergunta)
