# Dinheiro em centavos inteiros - checks

Profile: light
Plan: `.specs/features/money-in-cents/plan.md`

## Intent

36 checks in 4 slices · 4 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"` (Pest no container `api`, o mesmo do `make test` que o CI executa). O frontend roda via `docker compose exec -T frontend npx vitest run <arquivo> -t "<nome>"` (door 4, depois do `make npm-install`). As verificações estruturais usam `grep` a partir da raiz do repositório e passam quando o comando sai com `0`.

## Checks

### S1 - Preço do produto em centavos · 14 files · 29 KB · ~7k

**C1** - As quatro colunas de dinheiro (`products.price_cents`, `orders.total_cents`, `order_items.unit_price_cents`, `order_items.subtotal_cents`) são `bigint` e `not null` no `information_schema` do Postgres (AC 1, AC 11)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="stores every money column as bigint not null"`

**C2** - Gravar `-1` em cada uma das quatro colunas de dinheiro falha com a constraint `<tabela>_<coluna>_non_negative` e o valor gravado continua o mesmo (AC 2, AC 11)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a negative value in every money column"`

**C3** - `POST /api/admin/products` com `price_cents: 129990` responde `201`, `data.price_cents` é o inteiro JSON `129990` e não existe a chave `data.price` (AC 3, AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a product with its price in cents"`

**C4** - `PUT /api/admin/products/{product}` com `price_cents: 19990` responde `200` com `data.price_cents` `19990`, e a linha em `products` passa a ter `price_cents` `19990` (AC 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="updates the product price in cents"`

**C5** - Em `POST` e em `PUT` de produto, cada uma destas 7 entradas responde `422` com erro em `price_cents`: `0`, `-1`, `"1299.90"`, `1299.5`, `"abc"`, `10000000000` e `price: "1299.90"` sem `price_cents` (AC 5, AC 6, AC 7, AC 8)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects an invalid price_cents"`

**C6** - `price_cents: 9999999999` é aceito no `POST /api/admin/products` com `201` e devolvido como `9999999999` (AC 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="accepts the maximum price_cents"`

**C7** - `GET /api/products` e `GET /api/products/{product}` devolvem `price_cents` como inteiro JSON e nenhuma chave `price` (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="exposes price_cents as an integer on the public catalog"`

**C8** - `GET /api/admin/products`, `GET /api/admin/products/{product}` e `PATCH /api/admin/products/{product}/status` devolvem `price_cents` como inteiro JSON e nenhuma chave `price` (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="exposes price_cents on every admin product route"`

**C9** - `sort=price_asc` ordena por `price_cents` crescente e `sort=price_desc` por `price_cents` decrescente, ambos desempatando por `id` crescente (AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="sorts the catalog with id as tie breaker"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="sorts products"`

### S2 - Carrinho e pedidos em centavos · 23 files · 47 KB · ~12k

**C10** - `CheckoutService` monta as linhas `1990 × 3 = 5970` e `10 × 3 = 30` com `total_cents` `6000`, todos `int` (AC 12, AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="builds the order lines and total in cents"`

**C11** - `POST /api/cart/validate` com um produto de `price_cents` `1990` e quantidade `3` devolve `items.0.unit_price_cents` `1990`, `items.0.subtotal_cents` `5970` e `total_cents` `5970`, todos inteiros JSON (AC 12)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="recalculates the cart in cents from database prices"`

**C12** - Uma linha com produto inexistente volta com `unit_price_cents` `null` e `subtotal_cents` `null`, fica fora de `total_cents` e `is_valid` é `false` (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns null cents for a product that does not exist"`

**C13** - Uma linha com produto indisponível volta com `subtotal_cents` inteiro, fica fora de `total_cents` e `is_valid` é `false` (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps an unavailable line out of total_cents"`

**C14** - `POST /api/orders` com 2 × `9990` e 3 × `25000` responde `201` com `data.total_cents` `94980`, e os itens gravam `unit_price_cents`/`subtotal_cents` `9990`/`19980` e `25000`/`75000` (AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="places an order with every value in cents"`

**C15** - Os campos `price`, `unit_price`, `total`, `unit_price_cents` e `total_cents` enviados pelo cliente não alteram nenhum valor calculado, tanto em `POST /api/cart/validate` quanto em `POST /api/orders` (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ignores money fields sent by the client"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ignores money fields sent by the client on checkout"`

**C16** - Depois que o `price_cents` do produto muda, o item do pedido antigo mantém o `unit_price_cents` original (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the historical snapshot when the product changes later"`

**C17** - Cada uma das 7 rotas que devolvem pedidos (`GET /api/orders`, `GET /api/orders/{order}`, `POST /api/orders/{order}/payment`, `GET /api/admin/orders`, `GET /api/admin/orders/{order}`, `GET /api/account`, `GET /api/admin/users/{user}`) devolve `total_cents` inteiro, e os itens presentes devolvem `unit_price_cents` e `subtotal_cents` inteiros, sem nenhuma chave `total`, `unit_price` ou `subtotal` (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="exposes order money in cents on every order route"`

**C18** - Não existe nenhuma referência a `BcMath` em `backend/app` nem em `backend/database` (AC 19)
Proof: `! grep -rn "BcMath" backend/app backend/database`

**C19** - Depois do seed, todo pedido tem `total_cents` igual à soma dos `subtotal_cents` dos seus itens, e todo item tem `subtotal_cents` igual a `unit_price_cents × quantity` (AC 20)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds orders whose total_cents is the sum of their items"`

**C20** - Visitante recebe `401` em cada rota autenticada da `Surface` (13 rotas: as 5 de produto do admin, `POST /api/orders`, `GET /api/orders`, `GET /api/orders/{order}`, `POST /api/orders/{order}/payment`, `GET /api/admin/orders`, `GET /api/admin/orders/{order}`, `GET /api/account` e `GET /api/admin/users/{user}`) (Surface)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps 401 for guests on the money routes"`

**C21** - Cliente recebe `403` em cada rota do admin da `Surface` (8 rotas: as 5 de produto, `GET /api/admin/orders`, `GET /api/admin/orders/{order}` e `GET /api/admin/users/{user}`) (Surface)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps 403 for customers on the admin money routes"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids a customer from seeing another customer order"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids paying an order of another customer"`

**C22** - Um id inexistente (`999999`) recebe `404` em cada rota com parâmetro da `Surface` (8 rotas: `GET /api/products/{product}`, `GET`/`PUT /api/admin/products/{product}`, `PATCH /api/admin/products/{product}/status`, `GET /api/orders/{order}`, `POST /api/orders/{order}/payment`, `GET /api/admin/orders/{order}`, `GET /api/admin/users/{user}`) (Surface)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps 404 for unknown ids on the money routes"`

**C23** - Respondem `422` com o formato padrão de erro: `PATCH /api/admin/products/{product}/status` com `status: "deleted"`, `POST /api/orders` com `items: [{ product_id: "x", quantity: 0 }]` e `POST /api/cart/validate` com o mesmo payload (Surface)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps 422 for invalid payloads on the money routes"`

**C24** - A 61ª chamada a `POST /api/cart/validate` dentro do mesmo minuto responde `429` (Surface)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles cart validation at 60 requests per minute"`

**C25** - `POST /api/orders` com estoque insuficiente responde `409` e não grava pedido; `POST /api/orders/{order}/payment` de um pedido aguardando pagamento responde `202` (Surface)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns 409 and changes nothing when stock is insufficient"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="approves the fake payment of an order awaiting payment"`

### S3 - Frontend em centavos · 25 files · 74 KB · ~19k

**C26** - `make test-frontend` executa `vitest run` no container `frontend` e sai com `0` (door 4)
Proof: `make test-frontend`

**C27** - A formatação de centavos devolve `R$ 1.299,90` para `129990`, `R$ 0,05` para `5` e `R$ 0,00` para `0`, com o espaço emitido por `Intl.NumberFormat('pt-BR')` (AC 21)
Proof: `docker compose exec -T frontend npx vitest run src/utils/money.test.ts -t "formats cents as BRL"`

**C28** - A formatação devolve `—` para `null` e para `undefined` (AC 22)
Proof: `docker compose exec -T frontend npx vitest run src/utils/money.test.ts -t "renders a dash for a missing value"`

**C29** - A leitura do texto digitado devolve `19990` para `199,90`, `19990` para `199.90`, `19990` para `199,9` e `19900` para `199` (AC 23)
Proof: `docker compose exec -T frontend npx vitest run src/utils/money.test.ts -t "parses reais input into cents"`

**C30** - A leitura do texto digitado devolve `null` para `""`, `19,999`, `1.299,90`, `-10` e `abc`, e a mensagem exportada para esse caso é `Informe um preço válido, por exemplo 199,90.` (AC 24)
Proof: `docker compose exec -T frontend npx vitest run src/utils/money.test.ts -t "rejects invalid reais input"`
Proof: `docker compose exec -T frontend npx vitest run src/utils/money.test.ts -t "exposes the invalid price message"`

**C31** - Centavos viram o texto do campo: `19990` -> `199,90`, `5` -> `0,05`, `129990` -> `1299,90` (AC 25)
Proof: `docker compose exec -T frontend npx vitest run src/utils/money.test.ts -t "renders cents as reais input"`

**C32** - O formulário de produto lê o preço por `parseReaisInput`, mostra `INVALID_PRICE_MESSAGE` quando a leitura falha, preenche o campo com `centsToReaisInput` e exibe sob o campo o erro de `price_cents` vindo do backend (AC 24, AC 25, AC 26)
Proof: `grep -q "parseReaisInput" frontend/src/pages/admin/ProductFormPage.vue`
Proof: `grep -q "INVALID_PRICE_MESSAGE" frontend/src/pages/admin/ProductFormPage.vue`
Proof: `grep -q "centsToReaisInput" frontend/src/pages/admin/ProductFormPage.vue`
Proof: `grep -q "first('price_cents')" frontend/src/pages/admin/ProductFormPage.vue`

**C33** - Com a chave `cart` no `localStorage`, carregar o carrinho a remove e começa vazio; depois de adicionar um item, só a chave `cart-v2` é gravada, com `price_cents` inteiro (AC 27, door 3)
Proof: `docker compose exec -T frontend npx vitest run src/stores/cart.test.ts -t "discards the legacy cart key"`
Proof: `docker compose exec -T frontend npx vitest run src/stores/cart.test.ts -t "persists the cart only under cart-v2"`

**C34** - O total do carrinho com `1990 × 3` e `25000 × 1` é `30970`, e não resta nenhum `toCents` no frontend (AC 28)
Proof: `docker compose exec -T frontend npx vitest run src/stores/cart.test.ts -t "totals price_cents times quantity"`
Proof: `! grep -rn "toCents" frontend/src`

**C35** - O type-check do frontend sai com `0` e nenhum campo `price`, `unit_price`, `subtotal` ou `total` é declarado como `string` nos tipos e no carrinho (AC 29)
Proof: `docker compose exec -T frontend npm run type-check`
Proof: `! grep -nE "\b(price|unit_price|subtotal|total)\??: string" frontend/src/types/index.ts frontend/src/stores/cart.ts`

### S4 - Documentação da decisão · 1 file · 40 KB · ~10k

**C36** - O README descreve `price_cents bigint` e `total_cents bigint` no modelo de dados, e a decisão **Dinheiro** cita centavos; não restam `BcMath`, `decimal(10,2)` nem `decimal(12,2)` (AC 30, AC 31)
Proof: `grep -q "price_cents bigint" README.md`
Proof: `grep -q "total_cents bigint" README.md`
Proof: `grep -n "\*\*Dinheiro\*\*" README.md | grep -q "centavos"`
Proof: `! grep -nE "BcMath|decimal\((10|12),2\)" README.md`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| colunas de dinheiro (4) | C1 e C2, table-driven sobre as 4 | - |
| entradas inválidas de `price_cents` (7) | C5, table-driven sobre as 7, em `POST` e `PUT` | - |
| limite de `price_cents` (2 bordas) | `9999999999` C6 · `10000000000` C5 | - |
| campos de dinheiro do cliente ignorados (5) | C15, table-driven sobre os 5, nas duas rotas | - |
| rotas que devolvem pedidos (7) | C17, table-driven sobre as 7 | - |
| `POST /api/admin/products` statuses (4) | 201 C3 · 401 C20 · 403 C21 · 422 C5 | - |
| `PUT /api/admin/products/{product}` statuses (5) | 200 C4 · 401 C20 · 403 C21 · 404 C22 · 422 C5 | - |
| `GET /api/products` · `GET /api/products/{product}` statuses (2) | 200 C7 · 404 C22 | - |
| `GET /api/admin/products` · `GET /api/admin/products/{product}` · `PATCH /api/admin/products/{product}/status` statuses (5) | 200 C8 · 401 C20 · 403 C21 · 404 C22 · 422 C23 | - |
| `POST /api/cart/validate` statuses (3) | 200 C11 · 422 C23 · 429 C24 | - |
| `POST /api/orders` statuses (4) | 201 C14 · 401 C20 · 409 C25 · 422 C23 | - |
| `GET /api/orders` · `GET /api/orders/{order}` · `POST /api/orders/{order}/payment` · `GET /api/admin/orders` · `GET /api/admin/orders/{order}` · `GET /api/account` · `GET /api/admin/users/{user}` statuses (5) | 200 C17 · 202 C25 · 401 C20 · 403 C21 · 404 C22 | - |
| doors do `Landing` (4) | door 1 C1 · door 2 C3 · door 3 C33 · door 4 C26 | - |
| formatação de centavos (5 entradas) | `129990` C27 · `5` C27 · `0` C27 · `null` C28 · `undefined` C28 | - |
| texto digitado aceito (4) | `199,90` C29 · `199.90` C29 · `199,9` C29 · `199` C29 | - |
| texto digitado recusado (5) | `""` C30 · `19,999` C30 · `1.299,90` C30 · `-10` C30 · `abc` C30 | - |
| startup config: banco de teste (2 assemblies) | `make test` e o Pest com `--filter` (migrations editadas, `RefreshDatabase`) C1 · `make fresh` com seed C19 | - |

- Afirmações sobre status, rota ou formato de resposta (C3-C8, C11-C17, C20-C25) têm prova que atravessa o HTTP.
- C10 prova o cálculo na própria camada (`CheckoutService`), além da prova de borda em C14.
- C32 prova a ligação do formulário só de forma estrutural (`grep`). A decisão em si (o que é aceito ou recusado) está provada em C29 e C30. No perfil `light` não há teste de componente; o Verifier deve abrir o formulário no navegador para confirmar os ACs 24 a 26.

## Swept

- validation: C5, C6, C29, C30
- failure modes: C2 - o banco recusa valor negativo; existing - a gravação parcial do checkout é revertida, provado por "returns 409 and changes nothing when stock is insufficient" (C25)
- idempotency: n/a - nenhum caminho de escrita novo; muda só a representação dos valores, e a aprovação de pagamento duplicada continua coberta por "announces OrderPaid only once for a duplicated payment approval"
- authorization: C20, C21 - middlewares e policies existentes, inalterados
- concurrency: existing - o cálculo dos valores roda dentro da transação que trava o estoque, provado por "locks the stock rows with SELECT ... FOR UPDATE"; a mudança não cria nenhuma corrida nova
- data lifecycle: C33 - o carrinho antigo do navegador é descartado; no banco não há dados a migrar (plan, Impact)
- dependency failure: n/a - o caminho do dinheiro não chama nenhum serviço externo
- state transitions: n/a - nenhum status de pedido muda; o fluxo de pagamento e entrega continua igual
- observability: n/a - nenhum log ou métrica de valor monetário existe ou é adicionado

## Handoff

- S1 = 7k, todo no Catalog e nas migrations; S2 entra no Ordering com 19k acumulados; S3 entra no frontend com 38k; S4 fecha com 48k. Fica abaixo do orçamento padrão de 150k, então um builder só.
- Mechanism: one builder (cabe no orçamento, sem pergunta)
