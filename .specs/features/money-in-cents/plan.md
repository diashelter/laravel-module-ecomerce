# Dinheiro em centavos inteiros

## Problem

O dinheiro já é exato em todas as camadas, mas cada uma usa uma representação diferente: o banco usa `decimal`, a API trafega strings como `"1299.90"`, o backend calcula com `BcMath\Number` e o frontend reconverte essas strings para centavos antes de somar. O custo é de manutenção e aprendizado, não de usuário:

- o backend usa uma classe de precisão arbitrária para somas que cabem em um `int`;
- o frontend repete a interpretação de strings decimais;
- o `composer.json` declara `"php": "^8.3"`, mas `BcMath\Number` só existe a partir do PHP 8.4. Hoje essa restrição de versão é falsa.

Não há evidência de defeito: a fonte é a discovery [.design/money-in-cents.md](../../../.design/money-in-cents.md), que confirmou a mudança como exercício do padrão de mercado (dinheiro como inteiro na menor unidade).

Quando isso estiver entregue, todo valor monetário será um inteiro de centavos, do banco à tela, e a conversão para reais existirá em um só lugar: o frontend, para exibir valores e para ler o preço que o admin digita.

## Flow

Reaproveita os services de carrinho e checkout, o snapshot de `order_items`, os resources já existentes e o formato de erro do `ApiFormRequest`. Nada é duplicado e nenhuma classe nova de domínio é criada.

1. admin `POST|PUT /api/admin/products` `{ price_cents }` -> `Catalog` `ProductRequest` (exists) - valida o inteiro entre 1 e 9.999.999.999 e entrega ao `ProductDTO` (exists) -> persiste `products.price_cents` (door 1)
2. `POST /api/cart/validate` -> `Ordering` `CartValidationService` (exists) - lê `products.price_cents` e calcula `unit_price_cents × quantity` e a soma em `int` nativo
3. `POST /api/orders` -> `Ordering` `CheckoutService` (exists) - mesmo cálculo dentro da transação de `PlaceOrderUseCase` (exists); persiste `order_items.unit_price_cents`, `order_items.subtotal_cents` e `orders.total_cents` (door 1)
4. out: `ProductResource`, `OrderResource` e `OrderItemResource` (exist) emitem os campos `*_cents` como inteiros JSON (door 2). `Payment`, `Customers` e `Backoffice` reutilizam `OrderResource` sem mudança própria
5. frontend `utils/money` (exists) - é o único ponto de conversão: centavos -> texto em BRL e texto digitado -> centavos. `stores/cart` (exists) guarda `price_cents` sob a nova chave do `localStorage` (door 3)

## Impact

| Front | What changes |
| --- | --- |
| domain | `price` -> `price_cents` (produto): era string decimal em reais, passa a ser inteiro em centavos. Quem lê hoje: `ProductResource`, `ProductRequest`, `ProductDTO`, `ProductRepository` (ordenação), `CartValidationService`, `CheckoutService`, `ProductFactory`, `OrderItemFactory`, o carrinho e 7 telas do frontend |
| domain | `unit_price` / `subtotal` -> `unit_price_cents` / `subtotal_cents` (item do pedido e linha do carrinho). Quem lê hoje: `OrderItemResource`, `CartValidationService`, `CheckoutService`, `OrderRepository`, `OrderItemFactory`, `OrderSeeder`, `OrderItemsTable`, `CheckoutPage` |
| domain | `total` -> `total_cents` (pedido e validação do carrinho). Quem lê hoje: `OrderResource`, `PlaceOrderUseCase`, `OrderFactory`, `OrderSeeder` e 7 telas do frontend. As chaves `total` das séries do dashboard e o `meta.total` da paginação são contagens e **não** mudam |
| stored data | nada a migrar. As migrations originais são editadas no lugar, porque não há dados reais e o `make setup` / `make fresh` já recriam o banco. Quem tem um banco local roda `make fresh`, e dumps antigos em `storage-dumps/` deixam de ser compatíveis |
| client state | o carrinho salvo no navegador no formato antigo (preço como string, chave `cart`) é descartado (door 3) |
| dependency | `vitest` entra como devDependency do frontend, com o alvo `make test-frontend` (door 4) |
| decision log | o registro de decisões do projeto é a seção "Escopo e decisões" do README. A decisão de dinheiro é reescrita lá, em vez de abrir um `.specs/STATE.md` paralelo |

## Relations

None - nenhuma entidade ou cardinalidade muda. A troca de tipo e de nome dos valores monetários é a door 1 em `Landing`.

## Surface

Só rotas cuja assinatura muda. A autenticação, as policies e o throttle continuam como estão.

| Route | In | Out | Status |
| --- | --- | --- | --- |
| `POST /api/admin/products` | `price_cents` (int) no lugar de `price` | produto com `price_cents` | `201`, `401`, `403`, `422` |
| `PUT /api/admin/products/{product}` | `price_cents` (int) no lugar de `price` | produto com `price_cents` | `200`, `401`, `403`, `404`, `422` |
| `GET /api/products` · `GET /api/products/{product}` | sem mudança (`sort` mantém `price_asc` e `price_desc`) | produtos com `price_cents` | `200`, `404` |
| `GET /api/admin/products` · `GET /api/admin/products/{product}` · `PATCH /api/admin/products/{product}/status` | sem mudança | produtos com `price_cents` | `200`, `401`, `403`, `404`, `422` |
| `POST /api/cart/validate` | sem mudança (`product_id`, `quantity`) | `items[].unit_price_cents` (int ou null) · `items[].subtotal_cents` (int ou null) · `total_cents` (int) | `200`, `422`, `429` |
| `POST /api/orders` | sem mudança | pedido com `total_cents` e `items[].unit_price_cents` / `items[].subtotal_cents` | `201`, `401`, `409`, `422` |
| `GET /api/orders` · `GET /api/orders/{order}` · `POST /api/orders/{order}/payment` · `GET /api/admin/orders` · `GET /api/admin/orders/{order}` · `GET /api/account` · `GET /api/admin/users/{user}` | sem mudança | pedidos com `total_cents` e, quando há itens, `items[].unit_price_cents` / `items[].subtotal_cents` | `200`, `202`, `401`, `403`, `404` |

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| 1. tipo e nome das colunas de dinheiro | `products.price_cents`, `order_items.unit_price_cents`, `order_items.subtotal_cents` e `orders.total_cents` como `bigint not null`, cada uma com `CHECK (<coluna> >= 0)` nomeado `<tabela>_<coluna>_non_negative`, no padrão de `stocks_quantity_non_negative`. `products.price_cents` mantém o índice que `price` tinha | `integer`: não comporta o teto atual de 9.999.999.999 centavos (R$ 99.999.999,99). Manter `decimal`: contradiz a premissa da mudança. `unsigned`: o Postgres não tem |
| 2. contrato monetário da API | campos `price_cents`, `unit_price_cents`, `subtotal_cents` e `total_cents` como inteiros JSON; os nomes antigos (`price`, `unit_price`, `subtotal`, `total`) deixam de existir, sem alias; backend e frontend no mesmo pull request | manter os nomes com valor inteiro: a unidade fica implícita e um consumidor esquecido mostra R$ 1.990,00 em vez de R$ 19,90 sem erro de tipo. Alias temporário: o único consumidor muda no mesmo PR |
| 3. carrinho persistido no navegador | chave `cart-v2` no `localStorage`, com itens `{ product_id, name, image_url, price_cents, quantity, max_quantity }`; a chave antiga `cart` é removida na carga | converter o carrinho antigo: não existem clientes reais com carrinho salvo, e a conversão carregaria o parsing de string que a mudança remove |
| 4. dependência de teste no frontend | `vitest` em `devDependencies`, script `"test": "vitest run"` e alvo `make test-frontend` executado no container `frontend` | não ter testes: a conversão do texto digitado ficaria sem prova. Jest: exige configuração própria de transformação, enquanto o Vitest reaproveita o Vite do projeto |

- Nothing else in this change is hard to reverse

## Criteria

### S1: Preço do produto em centavos (P1)

O admin cadastra e edita o preço em centavos, e a vitrine expõe e ordena por `price_cents`.

**Acceptance Criteria**

1. The system SHALL store the product price in `products.price_cents` as `bigint not null` with the constraint `products_price_cents_non_negative` (`CHECK (price_cents >= 0)`)
2. IF a write sets `products.price_cents` to `-1` THEN the database SHALL reject it and no row SHALL be written
3. WHEN an admin sends `POST /api/admin/products` with `price_cents: 129990` and the other valid fields THEN the system SHALL return `201` with `data.price_cents` equal to the JSON integer `129990`
4. WHEN an admin sends `PUT /api/admin/products/{product}` with `price_cents: 19990` THEN the system SHALL return `200` with `data.price_cents` equal to `19990` and persist `19990`
5. IF `price_cents` is `0` or `-1` THEN the system SHALL return `422` with a validation error on `price_cents`
6. IF `price_cents` is `"1299.90"`, `1299.5` or `"abc"` THEN the system SHALL return `422` with a validation error on `price_cents`
7. IF `price_cents` is `10000000000` THEN the system SHALL return `422` with a validation error on `price_cents`, and `9999999999` SHALL be accepted
8. IF the request sends `price: "1299.90"` and no `price_cents` THEN the system SHALL return `422` with a validation error on `price_cents`
9. WHEN a product is returned by `GET /api/products`, `GET /api/products/{product}` or the admin product routes THEN it SHALL carry `price_cents` as a JSON integer and SHALL NOT carry a `price` key
10. WHEN `GET /api/products` is called with `sort=price_asc` THEN the system SHALL order by `price_cents` ascending with `id` ascending as the tie-break, and with `sort=price_desc` by `price_cents` descending with the same tie-break

**Independent test:** criar um produto com `price_cents: 129990` pelo admin e ver `price_cents: 129990` em `GET /api/products/{product}`.

### S2: Carrinho e pedidos em centavos (P1)

O carrinho e o checkout calculam com inteiros a partir do banco, e todo pedido expõe valores em centavos.

**Acceptance Criteria**

11. The system SHALL store `orders.total_cents`, `order_items.unit_price_cents` and `order_items.subtotal_cents` as `bigint not null`, each with a `CHECK (<column> >= 0)` constraint named `<table>_<column>_non_negative`
12. WHEN `POST /api/cart/validate` receives a product whose `price_cents` is `1990` with quantity `3` THEN the system SHALL return `items.0.unit_price_cents` `1990`, `items.0.subtotal_cents` `5970` and `total_cents` `5970`, all JSON integers
13. IF a cart line references a product that does not exist THEN the system SHALL return `unit_price_cents` `null` and `subtotal_cents` `null` for that line, leave it out of `total_cents` and return `is_valid` `false`
14. IF a cart line's product is unavailable THEN the system SHALL return its `subtotal_cents` as an integer, leave it out of `total_cents` and return `is_valid` `false`
15. WHEN `POST /api/orders` places 2 units of a product with `price_cents` `9990` and 3 units of one with `price_cents` `25000` THEN the system SHALL return `201` with `data.total_cents` `94980` and persist the items with `unit_price_cents`/`subtotal_cents` `9990`/`19980` and `25000`/`75000`
16. IF the body of `POST /api/orders` or `POST /api/cart/validate` carries `price`, `unit_price`, `total`, `unit_price_cents` or `total_cents` THEN the system SHALL ignore them and compute every value from `products.price_cents`
17. WHEN a product's `price_cents` changes after an order was placed THEN that order's item SHALL keep its original `unit_price_cents`
18. WHEN an order is returned by any route in `Surface` that returns orders THEN it SHALL carry `total_cents` as a JSON integer, its items SHALL carry `unit_price_cents` and `subtotal_cents` as JSON integers, and none SHALL carry `total`, `unit_price` or `subtotal`
19. The backend SHALL contain no reference to `BcMath\Number` under `backend/app` and `backend/database`
20. WHEN the database is seeded THEN every order's `total_cents` SHALL equal the sum of its items' `subtotal_cents`, and every item's `subtotal_cents` SHALL equal `unit_price_cents × quantity`

**Independent test:** fazer um pedido pelo checkout e ver `total_cents` igual à soma dos `subtotal_cents` em `GET /api/orders/{order}`.

### S3: Frontend em centavos (P2)

O frontend guarda e soma centavos e converte para reais só para exibir e para ler o preço digitado.

**Acceptance Criteria**

21. WHEN the frontend formats a value in cents THEN it SHALL render BRL in `pt-BR`: `129990` -> `R$ 1.299,90`, `5` -> `R$ 0,05`, `0` -> `R$ 0,00` (the space being the one `Intl.NumberFormat` emits)
22. IF the value to format is `null` or `undefined` THEN the frontend SHALL render `—`
23. WHEN the admin types `199,90`, `199.90`, `199,9` or `199` in the product price field THEN the form SHALL send `price_cents` `19990`, `19990`, `19990` and `19900` respectively
24. IF the admin types an empty value, more than 2 decimals (`19,999`), a thousands separator (`1.299,90`), a sign (`-10`) or letters (`abc`) THEN the form SHALL not send the request and SHALL show `Informe um preço válido, por exemplo 199,90.` under the price field
25. WHEN the admin opens an existing product with `price_cents` `19990` for editing THEN the price field SHALL show `199,90`
26. WHEN the backend answers `422` with an error on `price_cents` THEN the form SHALL show that message under the price field
27. IF the `localStorage` holds the key `cart` THEN the frontend SHALL remove it and start with an empty cart, and SHALL persist the cart only under `cart-v2`
28. The frontend SHALL compute the cart total as the sum of `price_cents × quantity` over the stored items, with no parsing of decimal strings
29. The frontend types SHALL declare every money field as `number` named with the `_cents` suffix, and `vue-tsc -b` SHALL exit `0`

**Independent test:** cadastrar um produto digitando `1299,90`, vê-lo como `R$ 1.299,90` na vitrine, levá-lo ao carrinho e conferir o mesmo total no checkout e no pedido.

### S4: Documentação da decisão (P2)

O README descreve o dinheiro como ficou.

**Acceptance Criteria**

30. The README SHALL describe the money columns as `bigint` in cents with the `_cents` names, and the decision under "Escopo e decisões" as integer cents in the database, the backend, the API and the frontend state
31. The README SHALL contain no mention of `BcMath\Number`, `decimal(10,2)` or `decimal(12,2)`

**Independent test:** ler a tabela de modelo de dados e "Escopo e decisões" no README.

## Out of scope

| Excluded | Why |
| --- | --- |
| Value object `Money` e mais de uma moeda | só compensa com descontos, cupons ou frete, que o README deixa fora do escopo |
| Compatibilidade com os nomes antigos da API | o único consumidor é o frontend do repositório, que muda no mesmo PR |
| Separador de milhar na digitação do preço (`1.299,90`) | o formulário atual também não aceita; aceitar exigiria decidir o caso ambíguo `1.299` |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| Validação de tipo do `price_cents` | a regra `integer` do Laravel, que rejeita `"1299.90"` e `1299.5` | é a regra que o projeto já usa para inteiros (`quantity`, `category_ids.*`) | n |
| Frontend no CI | o CI continua rodando só o backend; `make test-frontend` roda localmente | escolha do usuário ao optar pelo Vitest só para `money.ts` | y |
| Restrição `"php": "^8.3"` e extensão `bcmath` no Dockerfile | ficam como estão | sem `BcMath\Number` a restrição volta a ser verdadeira; remover a extensão é outra mudança | n |
| Rótulo do campo de preço no formulário | continua `Preço (R$)`, com o admin digitando em reais | o admin pensa em reais; centavos são detalhe do contrato | n |

**Open questions:** none - all resolved or logged above.

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| API product routes (`/api/products*`, `/api/admin/products*`) | response shape | AC 3, AC 4, AC 9 |
| API `POST`/`PUT /api/admin/products*` | error shape and codes | AC 5, AC 6, AC 7, AC 8 - formato existente do `ApiFormRequest` |
| API `POST /api/cart/validate` | response shape | AC 12, AC 13, AC 14 |
| API order routes (`/api/orders*`, `/api/admin/orders*`, `/api/account`, `/api/admin/users/{user}`) | response shape | AC 15, AC 18 |
| API all routes in Surface | who may call it | existing - middlewares `auth:sanctum` e `admin` e as policies, inalterados |
| API all routes in Surface | versioning | n/a - a API não é versionada e o único consumidor muda no mesmo PR (door 2) |
| API all routes in Surface | rate limit behaviour | existing - os `throttle` de `routes/api.php` não mudam |
| screen admin product form | error state | AC 24, AC 26 |
| screen admin product form | empty state | AC 24 - campo vazio bloqueia o envio; produto novo começa com o campo vazio, como hoje |
| screen admin product form | loading state | existing - o estado de envio do formulário não muda |
| screen admin product form | destructive action confirms | n/a - editar o preço não destrói nada; o snapshot preserva os pedidos (AC 17) |
| screens vitrine, carrinho, checkout, pagamento, pedidos, conta e admin | how money is shown | AC 21, AC 22 |
| screen carrinho | empty state | existing - a tela de carrinho vazio, alcançada também pelo descarte do AC 27 |
| screens com dinheiro | ordering and density | n/a - nenhuma ordem ou layout muda |
| document README | structure and what the reader does next | AC 30, AC 31 |

## Sources

- [.design/money-in-cents.md](../../../.design/money-in-cents.md) - veredito, decisões-chave e fatias, confirmados em 2026-10-06
- `AGENTS.md` - documentação atualizada na mesma entrega e verificação com `make test` e Pint
