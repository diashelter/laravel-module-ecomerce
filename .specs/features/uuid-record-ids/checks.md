# Ids públicos: UUID v7 nos registros e nenhum binding nas rotas - checks

Profile: standard
Plan: `.specs/features/uuid-record-ids/plan.md`

65 checks em 4 slices, a documentação e o fechamento · 5 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. O Pint roda via `docker compose exec -T api ./vendor/bin/pint --test`, como no CI. O frontend roda via `docker compose exec -T frontend npx vitest run <arquivo> -t "<nome do teste>"` e `docker compose exec -T frontend npm run type-check`; o CI não roda o frontend, então essas provas rodam só localmente. Os nomes de teste novos não usam parênteses, colchetes nem barras, porque o `--filter` do Pest é uma expressão regular.

Termos usados nos checks:

- **UUID canônico:** 36 caracteres, dígitos hexadecimais minúsculos e hífens no formato 8-4-4-4-12, a expressão `[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}` da door 2.
- **v7:** UUID canônico com `7` como primeiro dígito do terceiro grupo.
- **Id desconhecido:** um v7 gerado no próprio teste com `Str::uuid7()`, que não está em nenhuma tabela. Substitui o `999999` de hoje.
- **Valores malformados:** o inteiro `1`, a string `"1"`, a string `"abc"` e o UUID em maiúsculas `0199E3B0-4C2A-7D10-9A3B-5F6E7D8C9B0A`. No caminho da URL, `1`, `abc` e o mesmo UUID em maiúsculas.
- **Envelope do 404:** `assertApiError($response, 'NOT_FOUND', 'Recurso não encontrado.')`, o helper de `tests/Pest.php`, com `errors` `{}`.
- **Contagem de queries:** `count(DB::getQueryLog())` durante a requisição, com a sessão aberta por `actingAs` e os dados criados antes de `DB::enableQueryLog()`. Os números de `714f1fe` foram medidos em 2026-10-15 com um teste temporário, nunca commitado, que fazia as mesmas requisições dos checks C29, C39, C40, C43 a C46, C53 e C54. Os eventos `OrderPlaced` e `PaymentApproved` ficam com `Event::fake`, porque com a fila `sync` os listeners entrariam na contagem.
- **Sorteio forçado:** o sorteio do código é resolvido pelo container. Os testes de colisão o substituem por uma sequência fixa de códigos, e a sequência começa com códigos que já pertencem a outros pedidos.

Os checks marcados com "exercício temporário" provam uma propriedade de um teste de arquitetura com uma mudança de propósito no código. A mudança é revertida logo depois e nunca é commitada, como o C7 da feature `module-facades`. Durante o exercício, `git diff --quiet -- backend/tests` precisa sair com 0, ou seja, o teste não é editado.

Testes existentes que mudam, sem afrouxar nenhuma asserção:

- o `999999` dos 20 arquivos que o usam vira um id desconhecido, e `orderRow()` de `tests/Pest.php` passa a receber `string $customerId`;
- `AuthorizationTest` "forbids a customer from seeing another customer order", `PaymentTest` "forbids paying an order of another customer" e `Account/CustomerAddressTest` "forbids changing an address of another customer" passam a esperar o envelope do `404` no lugar do `403`. O plano aprovou isso no AC 46, e o C49 cita os três;
- `MoneyInCentsTest` "keeps 404 for unknown ids on the money routes" passa a mandar corpo válido nas rotas de escrita, para continuar provando o `404` (Key decision 4 do design);
- `Admin/ProductTest` "blocks deleting a product present in orders" e `Admin/CategoryTest` "blocks deleting a category with products" ganham a asserção da mensagem (C41, C42);
- `TypedListsTest` "refuses an invalid id in a typed id list" troca o dataset de inteiros pelos valores do C9;
- `stores/cart.test.ts` "persists the cart only under cart-v2" passa a se chamar "persists the cart only under cart-v3" e a conferir a chave nova (C20).

## Checks

### S1 - UUID v7 keys · 194 arquivos · 461 KB · ~115k

**C1** - O PostgreSQL tipa como `uuid` (`information_schema.columns.data_type`) as 9 chaves primárias (`users.id`, `customers.id`, `categories.id`, `products.id`, `stocks.id`, `orders.id`, `order_items.id`, `payments.id` e `customer_addresses.id`) e as 8 chaves estrangeiras (`stocks.product_id`, `category_product.product_id`, `category_product.category_id`, `orders.customer_id`, `order_items.order_id`, `order_items.product_id`, `payments.order_id` e `customer_addresses.customer_id`). O teste é table-driven sobre as 17 colunas (AC 1, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="types every business key and foreign key as uuid"`

**C2** - Para cada um dos 9 models que gravam (`User`, `CustomerAccount`, `Category`, `Product`, `Stock`, `Order`, `OrderItem`, `Payment` e `CustomerAddress`), um registro criado pela factory tem `id` v7. A coluna `id` da tabela dele não tem default no banco (`column_default` nulo), então o id vem da aplicação, antes do insert (AC 2, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="assigns a version 7 uuid before the insert"`

**C3** - A regra de exclusão (`pg_constraint.confdeltype`) de cada chave estrangeira é a de `714f1fe`: `r` (restrict) em `orders.customer_id`, `order_items.product_id` e `payments.order_id`; `c` (cascade) em `stocks.product_id`, `category_product.product_id`, `category_product.category_id`, `order_items.order_id` e `customer_addresses.customer_id`. O teste é table-driven sobre as 8 (AC 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the delete rule of every foreign key"`

**C4** - As tabelas de negócio mantêm todas as constraints de `714f1fe` (AC 4):
- as 17 check constraints: `customer_addresses_postal_code_digits`, `customer_addresses_state_known`, `customers_email_normalized`, `order_items_subtotal_cents_non_negative`, `order_items_unit_price_cents_non_negative`, `orders_delivery_business_days_positive`, `orders_delivery_postal_code_digits`, `orders_delivery_state_known`, `orders_shipping_cents_non_negative`, `orders_total_cents_non_negative`, `orders_total_cents_not_below_shipping`, `payments_amount_cents_non_negative`, `payments_decline_reason_matches_status`, `products_price_cents_non_negative`, `stocks_quantity_non_negative`, `users_email_normalized` e `users_role_valid`;
- as 4 únicas: `categories_slug_unique`, `customers_email_unique`, `stocks_product_id_unique` e `users_email_unique`;
- `category_product_pkey` sobre (`product_id`, `category_id`);
- o índice `payments_order_id_approved_unique`, único sobre `order_id` com `WHERE status = 'approved'`.

O teste confere que cada uma existe; constraints novas (as do C23 e do C24) não o fazem falhar.
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps every constraint of the business tables"`

**C5** - Onze requisições cobrem os 13 resources. Em cada resposta, o teste percorre o JSON inteiro e exige que toda chave `id`, ou terminada em `_id`, seja um UUID canônico em string e nunca um número. Exige também que os caminhos listados existam (AC 5):

| Requisição | Resource | Caminhos |
| --- | --- | --- |
| `GET /api/categories` | `CategoryResource` | `data.0.id` |
| `GET /api/admin/products/{product}` | `ProductResource` | `data.id`, `data.stock.id`, `data.categories.0.id` |
| `GET /api/account/addresses` | `CustomerAddressResource` | `data.0.id` |
| `GET /api/account` | `CustomerProfileResource` do Customers e `OrderSummaryResource` | `data.customer.id`, `data.last_order.id`, `data.recent_orders.0.id` |
| `GET /api/admin/customers/{customer}` | `CustomerSummaryResource` | `data.id`, `data.orders.0.id` |
| `GET /api/auth/me` | `CustomerProfileResource` do Identity | `data.id` |
| `GET /api/admin/users/{user}` | `StaffMemberResource` | `data.id` |
| `GET /api/admin/stocks` | `StockResource` | `data.0.id`, `data.0.product.id` |
| `GET /api/admin/orders/{order}` | `OrderResource` e `OrderItemResource` | `data.id`, `data.customer.id`, `data.items.0.id`, `data.items.0.product_id` |
| `POST /api/cart/validate` | `ValidatedCartResource` | `data.items.0.product_id` |
| `POST /api/orders/{order}/payment` | `PaymentResource` | `data.id`, `data.order_id` |

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns every id as a uuid string"`

**C6** - Por reflexão, os 9 métodos de contrato (`ProductOrderHistory::hasBeenOrdered`, `CustomerAccounts::updateProfile`, `CustomerAccounts::findProfile`, `StockInitializer::createForProduct`, `StockReservation::decrement`, `CustomerOrderHistory::countForCustomer`, `CustomerOrderHistory::recentForCustomer`, `DeliveryAddressBook::find` e `PayableOrders::findForPayment`) declaram `string` em todo parâmetro chamado `id` ou terminado em `Id`, e cada um tem pelo menos um. O teste é table-driven sobre os 9 (AC 6, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="types every id of the contracts as a string"`

**C7** - Por reflexão, `OrderPlaced`, `OrderPaid`, `PaymentApproved`, `DeliveryScheduled`, `OrderDelivered` e o job `DeliverOrder` têm a propriedade pública `orderId` com o tipo `string` (AC 7, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="carries the order id as a string in the order events and the delivery job"`

**C8** - Nenhuma classe, interface ou enum declarado em `backend/app/Modules` tem um parâmetro de método (construtor incluído) ou uma propriedade com o tipo `int` e nome `id` ou terminado em `Id`. O teste lê os arquivos que existem, por reflexão, e afirma que leu pelo menos 200 classes, para não passar vazio (AC 8, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares no integer id in the modules"`

**C9** - `ProductIds`, `CategoryIds` e `CustomerIds` aceitam UUIDs canônicos e os devolvem na mesma ordem em `all()`. Cada uma lança `InvalidArgumentException` para `"1"`, `""`, `"abc"`, o UUID em maiúsculas e um id repetido. O dataset cobre as 3 listas, e os dois testes juntos cobrem as 6 linhas (AC 9, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an invalid id in a typed id list"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="accepts canonical uuids in a typed id list"`

**C10** - Em `POST /api/cart/validate` e em `POST /api/orders` (este com um `address_id` válido do cliente), um `items.0.product_id` com cada um dos 4 valores malformados responde `422`, com um erro em `errors["items.0.product_id"]`. Nenhuma query da requisição cita `"products"` ou `"stocks"`. O teste é table-driven sobre as 2 rotas e os 4 valores (AC 10, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a malformed product id in the cart body"`

**C11** - Em `POST /api/orders`, um `address_id` com cada um dos 4 valores malformados responde `422`, com um erro em `errors.address_id`. Nenhuma query da requisição tem `for update`, e o estoque não muda (AC 11, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a malformed address id before touching the stock"`

**C12** - Em `POST /api/admin/products` e em `PUT /api/admin/products/{product}`, um `category_ids.0` com cada um dos 4 valores malformados responde `422`, nunca `500`, com um erro em `errors["category_ids.0"]`. Nenhuma query da requisição cita `"categories"` (AC 12, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a malformed category id without querying the categories"`

**C13** - Em `POST /api/cart/validate`, um id desconhecido em `product_id` responde `200` com `data.is_valid` `false` e, na linha desse id, `problem` `"Produto não encontrado."` (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reports problems for each item"`

**C14** - Em `POST /api/orders`, um id desconhecido em `product_id` responde `409` com `errors` igual a `{"items.<o id>": ["Produto não encontrado."]}`. Nenhum pedido é criado, e o estoque dos outros produtos não muda (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects inactive and out of stock products"`

**C15** - Em `POST /api/orders`, um `address_id` canônico que não está no caderno do cliente (de outro cliente, ou desconhecido) responde `422` com `errors.address_id` `["Endereço de entrega não encontrado."]`. Nenhum pedido é criado e o estoque não muda. Na própria camada, o `PlaceOrderUseCase` recusa o endereço desconhecido sem nenhuma query `for update` (AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a delivery address that is not in the customer address book"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an unknown delivery address before touching the stock"`

**C16** - Em `POST /api/admin/products`, um id desconhecido em `category_ids.0` responde `422` com um erro em `errors["category_ids.0"]`, e nenhum produto é criado (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an unknown category id on the product"`

**C17** - Para toda rota sob `api/` com parâmetro, lida de `Route::getRoutes()`, e para cada um dos 3 valores malformados de caminho, a requisição com todos os parâmetros trocados por esse valor responde com o envelope do `404`, e o log de queries fica vazio. O teste afirma que percorreu pelo menos 19 rotas e que os nomes de parâmetro cobertos incluem `product`, `category`, `stock`, `customer`, `user`, `order` e `address` (AC 17, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 without a query for a malformed id in the path"`

**C18** - Na tabela de rotas do frontend, `/products/<v7>`, `/payment/<v7>`, `/account/orders/<v7>`, `/admin/products/<v7>/edit`, `/admin/customers/<v7>`, `/admin/customers/<v7>/edit`, `/admin/users/<v7>/edit` e `/admin/orders/<v7>` resolvem para `product`, `payment`, `account.order`, `admin.products.edit`, `admin.customers.show`, `admin.customers.edit`, `admin.users.edit` e `admin.orders.show`. As props de cada rota entregam o id como a mesma string (`id`, ou `orderId` no pagamento). O teste é table-driven sobre as 8 rotas (AC 18)
Proof: `docker compose exec -T frontend npx vitest run src/router/routes.test.ts -t "matches a canonical uuid in every route with an id"`

**C19** - `/products/7`, `/account/orders/42`, `/payment/abc` e `/admin/orders/0199E3B0-4C2A-7D10-9A3B-5F6E7D8C9B0A` resolvem para `not-found` (AC 19)
Proof: `docker compose exec -T frontend npx vitest run src/router/routes.test.ts -t "renders not found for an id that is not a uuid"`

**C20** - Com um carrinho de ids numéricos salvo em `cart-v2`, o store do carrinho carrega vazio e remove `cart-v2`. Depois de um item adicionado, o carrinho fica só em `cart-v3` (AC 20)
Proof: `docker compose exec -T frontend npx vitest run src/stores/cart.test.ts -t "drops the cart saved under cart-v2"`
Proof: `docker compose exec -T frontend npx vitest run src/stores/cart.test.ts -t "persists the cart only under cart-v3"`

**C21** - `frontend/src/types/index.ts` não declara nenhum `id` ou `*_id` como `number`, e o type-check do frontend sai com 0 (AC 21)
Proof: `! grep -nE -e '\b(id|[a-z_]+_id)\??: number' frontend/src/types/index.ts`
Proof: `docker compose exec -T frontend npm run type-check`

### S2 - orders.code · 22 arquivos · 79 KB · ~20k

**C22** - `POST /api/orders` responde `201` com `data.code` casando `^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$`, igual a `orders.code` do pedido gravado (AC 22, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the order code on checkout"`

**C23** - Uma gravação direta em `orders` (com `orderRow()`) é recusada com `QueryException` quando o `code` é nulo, tem 7 ou 9 caracteres, ou contém `0`, `O`, `1`, `I`, `L` ou uma letra minúscula. Fora o nulo, a exceção cita `orders_code_format`. O código `K7Q2M9XA` é aceito. O teste é table-driven sobre os 9 casos recusados e o aceito (AC 23, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="guards the order code format in the orders table"`

**C24** - Uma segunda gravação direta em `orders` com um `code` que já existe é recusada com `QueryException` citando `orders_code_unique` (AC 24, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a repeated order code in the orders table"`

**C25** - Com o sorteio forçado a repetir 1 código existente, e depois 2, `POST /api/orders` responde `201` com o primeiro código livre da sequência. Na requisição há exatamente uma query `for update` em `stocks`. O estoque de cada item cai exatamente pela quantidade pedida, e `OrderPlaced` é disparado uma vez. O dataset tem 1 e 2 colisões (AC 25, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="draws the order code again after a collision"`

**C26** - Com o sorteio forçado a repetir 3 códigos existentes seguidos, `POST /api/orders` responde `500`. A contagem de `orders` e de `order_items` é a de antes da requisição, o estoque de cada produto também, e nenhum `OrderPlaced` é disparado. O quarto código da sequência, livre, nunca é sorteado (AC 26, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="fails the whole order after three code collisions"`

**C27** - Na própria camada, o `PlaceOrderUseCase`, com o sorteio forçado, cria o pedido com o primeiro código livre depois de 0, 1 e 2 colisões. Depois de 3, lança a exceção sem deixar pedido, itens nem baixa de estoque. O teste é table-driven sobre as 4 bordas (Test policy, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="draws the order code up to three times"`

**C28** - Na própria camada, 1000 sorteios do código devolvem só strings que casam `^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$`, com pelo menos 990 distintas (Test policy, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="draws 8 symbols of the order code alphabet"`

**C29** - `POST /api/orders` com 2 produtos (quantidades 1 e 2), um endereço do cliente e o código livre no primeiro sorteio faz 9 queries, como em `714f1fe` (AC 27)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the query count of the checkout"`

**C30** - `GET /api/orders` (`data.0.code`), `GET /api/orders/{order}` (`data.code`), `GET /api/admin/orders` (`data.0.code`) e `GET /api/admin/orders/{order}` (`data.code`) devolvem o código gravado do pedido. O teste é table-driven sobre as 4 rotas (AC 28)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the order code with every order"`

**C31** - `GET /api/account` devolve o código gravado em `data.last_order.code` e em cada `data.recent_orders.*.code`, e `GET /api/admin/customers/{customer}` em cada `data.orders.*.code` (AC 29)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the order code in every order summary"`

**C32** - Por reflexão, nenhum método das interfaces de `Contracts` de nenhum módulo tem parâmetro chamado `code` ou terminado em `Code`. Nenhuma classe de `Events`, nem o job `DeliverOrder`, tem propriedade com esse nome. Nenhuma URI de rota sob `api/` contém `code` (AC 30)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the order code out of the contracts events and routes"`

**C33** - As telas mostram `#<code>` e nunca `#<id>` (AC 31):
- nenhum arquivo de `frontend/src` interpola `#` seguido de um `.id`;
- os 8 arquivos (`pages/account/OrderListPage.vue`, `pages/account/OrderDetailPage.vue`, `pages/account/AccountDashboardPage.vue`, `pages/public/PaymentPage.vue`, `composables/useCheckout.ts`, `pages/admin/OrderListPage.vue`, `pages/admin/OrderDetailPage.vue` e `pages/admin/CustomerDetailPage.vue`) interpolam `#` seguido de um `.code`;
- o `AccountDashboardPage.vue` faz isso nos dois lugares, o último pedido e a tabela dos recentes;
- o aviso de pedido criado diz `Pedido #K7Q2M9XA criado com sucesso!` para um pedido com esse código.

Proof: `! grep -rnE -e '#(\{\{ *[A-Za-z_.]+\.id *\}\}|\$\{[A-Za-z_.]+\.id\})' frontend/src`
Proof: `test "$(grep -lE -e '#(\{\{ *[A-Za-z_.]+\.code *\}\}|\$\{[A-Za-z_.]+\.code\})' frontend/src/pages/account/OrderListPage.vue frontend/src/pages/account/OrderDetailPage.vue frontend/src/pages/account/AccountDashboardPage.vue frontend/src/pages/public/PaymentPage.vue frontend/src/composables/useCheckout.ts frontend/src/pages/admin/OrderListPage.vue frontend/src/pages/admin/OrderDetailPage.vue frontend/src/pages/admin/CustomerDetailPage.vue | wc -l)" -eq 8`
Proof: `test "$(grep -cE -e '#\{\{ *[A-Za-z_.]+\.code *\}\}' frontend/src/pages/account/AccountDashboardPage.vue)" -ge 2`
Proof: `docker compose exec -T frontend npx vitest run src/composables/useCheckout.test.ts -t "announces the order by its code"`

**C34** - A URL do pedido continua com o id (AC 32):
- nenhum `params` ou navegação do frontend usa `.code` como `id` ou `orderId`;
- depois de criar o pedido, o checkout navega para `{ name: 'payment', params: { orderId: <o id do pedido> } }`.

Proof: `! grep -rnE -e '(id|orderId): *[A-Za-z_.]+\.code\b' frontend/src`
Proof: `docker compose exec -T frontend npx vitest run src/composables/useCheckout.test.ts -t "goes to the payment of the order by its id"`

### S3 - Guard staff e vitrine · 43 arquivos · 90 KB · ~22k

As 15 rotas desta slice: `GET /api/products/{product}`; `GET`, `PUT` e `DELETE /api/admin/products/{product}`; `PATCH /api/admin/products/{product}/status`; `GET`, `PUT` e `DELETE /api/admin/categories/{category}`; `PUT /api/admin/stocks/{stock}`; `GET` e `PUT /api/admin/customers/{customer}`; `GET`, `PUT` e `DELETE /api/admin/users/{user}`; e `GET /api/admin/orders/{order}`. A equipe entra como `admin()`.

**C35** - Por reflexão sobre a ação de cada uma das 15 rotas, o parâmetro PHP com o nome do parâmetro da rota tem o tipo `string`, e nenhum parâmetro da ação implementa `UrlRoutable` (AC 33, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="receives the record id as a string in the staff and showcase routes"`

**C36** - Para cada uma das 15 rotas, com um id desconhecido e sem corpo (`GET` e `DELETE`) ou com corpo válido (`PUT` e `PATCH`), a resposta é o envelope do `404`. A contagem de linhas das 9 tabelas de negócio é a mesma antes e depois. O teste é table-driven sobre as 15 (AC 34)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 for an unknown id in the staff and showcase routes"`

**C37** - `PUT /api/admin/products/{product}`, `PATCH /api/admin/products/{product}/status`, `PUT /api/admin/categories/{category}`, `PUT /api/admin/stocks/{stock}`, `PUT /api/admin/customers/{customer}` e `PUT /api/admin/users/{user}` respondem `422` com corpo `{}`, tanto para um id existente quanto para um desconhecido. O teste é table-driven sobre as 6 rotas e os 2 ids (AC 35)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="validates the body before the record in the staff routes"`

**C38** - `GET /api/products/{product}` de um produto inativo e de um produto sem estoque responde `404` com `message` `"Produto indisponível."` (AC 36)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not show unavailable products"`

**C39** - `PUT /api/admin/stocks/{stock}` com `{"operation": "increase", "quantity": 5}` responde `200` e faz 3 queries, contra 4 em `714f1fe`. Há uma só query que lê `stocks`, e ela tem `for update` (AC 37)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="adjusts the stock reading it once with a lock"`

**C40** - `DELETE /api/admin/products/{product}` de um produto sem pedidos responde `204` com 2 queries, contra 3 em `714f1fe`, e nenhuma é um `select` em `"products"`. `DELETE /api/admin/categories/{category}` de uma categoria sem produtos responde `204` com 2 queries, contra 3, e nenhuma é um `select * from "categories"`. O registro deixa de existir (AC 38)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes a product without reading it first"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes a category without reading it first"`

**C41** - `DELETE /api/admin/products/{product}` de um produto com item de pedido responde `409` com `code` `"BUSINESS_RULE_VIOLATION"` e `message` `"Este produto possui pedidos e não pode ser excluído. Desative-o em vez disso."`, e o produto continua existindo (AC 39)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="blocks deleting a product present in orders"`

**C42** - `DELETE /api/admin/categories/{category}` de uma categoria com produto responde `409` com `message` `"Esta categoria possui produtos associados e não pode ser excluída."`, e a categoria continua existindo (AC 40)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="blocks deleting a category with products"`

**C43** - `DELETE /api/admin/users/{user}` com o id da própria conta responde `409` com `message` `"Você não pode remover a própria conta."`, e a conta continua existindo. A requisição não faz nenhuma query que cite `"users"`; em `714f1fe`, fazia 1 (AC 41)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses removing the own staff account"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses to delete the own account without querying the users"`

**C44** - `DELETE /api/admin/users/{user}` de outro membro da equipe responde `204` com 1 query, o `delete`, contra 2 em `714f1fe`. O membro deixa de existir (AC 42)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes a staff member without reading it first"`

**C45** - `PUT /api/admin/customers/{customer}` com `{"name": "Bruna Lima", "email": "bruna@example.com"}`, para um cliente com 2 pedidos, responde `200` com 4 queries, contra 5 em `714f1fe`. Exatamente uma delas é `select * from "customers"` (AC 43)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reads the customer once when the admin edits it"`

**C46** - Com as requisições bem-sucedidas abaixo, a contagem de queries é a de `714f1fe`. O teste é table-driven sobre as 10 (AC 44):

| Requisição | Dados | Queries |
| --- | --- | --- |
| `GET /api/products/{product}` | produto com estoque 5 e 2 categorias, sem sessão | 3 |
| `GET /api/admin/products/{product}` | o mesmo produto | 3 |
| `PUT /api/admin/products/{product}` | `productPayload()`, com 2 categorias novas no lugar das 2 de antes | 10 |
| `PATCH /api/admin/products/{product}/status` | `{"status": "inactive"}` | 4 |
| `GET /api/admin/categories/{category}` | categoria sem produtos | 2 |
| `PUT /api/admin/categories/{category}` | `{"name": "Periféricos", "slug": "perifericos"}` | 4 |
| `GET /api/admin/customers/{customer}` | cliente com 2 pedidos | 3 |
| `GET /api/admin/orders/{order}` | pedido sem itens | 3 |
| `GET /api/admin/users/{user}` | membro de suporte | 1 |
| `PUT /api/admin/users/{user}` | `{"name": "Carla Dias", "email": "carla@example.com", "role": "support"}` | 3 |

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the query count of the staff and showcase reads and updates"`

**C47** - Na própria camada, os use cases de exclusão recebem o id. `DeleteProductUseCase`, `DeleteCategoryUseCase` e `DeleteStaffMemberUseCase` apagam o registro existente. Para um id desconhecido, lançam `ModelNotFoundException`, que o `ApiExceptionRenderer` transforma no envelope do `404`, e não apagam nada (Test policy, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes a product by id and refuses an unknown one"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes a category by id and refuses an unknown one"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes a staff member by id and refuses an unknown one"`

### S4 - Guard customer · 27 arquivos · 103 KB · ~26k

As 4 rotas desta slice: `GET /api/orders/{order}`, `POST /api/orders/{order}/payment`, `PUT /api/account/addresses/{address}` e `DELETE /api/account/addresses/{address}`.

**C48** - O dono recebe `200` em `GET /api/orders/{order}`. Recebe `202` em `POST /api/orders/{order}/payment`, com `card_token` `fake_card_approved`, num pedido `awaiting_payment`. Recebe `200` em `PUT /api/account/addresses/{address}` com `addressPayload()` e `204` em `DELETE /api/account/addresses/{address}`. O teste é table-driven sobre as 4 rotas (AC 45)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers the owner on the customer record routes"`

**C49** - Para cada uma das 4 rotas, o registro de outro cliente responde com o envelope do `404`. Tirando o `request_id`, o corpo é igual ao de um id desconhecido na mesma rota. Nada muda: o status do pedido é o mesmo, não há linha em `payments`, o gateway espião não recebe cobrança, e o endereço continua existindo com os campos de antes. Os três testes de `403` de hoje passam a esperar o mesmo `404` (AC 46, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers another customer record as an unknown id"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids a customer from seeing another customer order"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids paying an order of another customer"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids changing an address of another customer"`

**C50** - `POST /api/orders/{order}/payment` com corpo válido, num pedido de outro cliente com status `placed`, `payment_approved` ou `delivered`, responde `404` e nunca `409`. O dataset cobre os 3 status (AC 47, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 before the status for another customer order"`

**C51** - `POST /api/orders/{order}/payment` com corpo válido e um id desconhecido responde com o envelope do `404`. O gateway espião não recebe cobrança, `payments` fica vazia e nenhum `PaymentApproved` é disparado (AC 48)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 to the payment of an unknown order"`

**C52** - `POST /api/orders/{order}/payment` com `{}` e `PUT /api/account/addresses/{address}` com `{}` respondem `422` para o id do próprio cliente, o de outro cliente e um desconhecido. O teste é table-driven sobre as 2 rotas e os 3 ids (AC 49)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="validates the body before the owner in the customer routes"`

**C53** - `DELETE /api/account/addresses/{address}` do próprio endereço responde `204` com 1 query, contra 2 em `714f1fe`: um `delete` em `"customer_addresses"` que filtra por `"customer_id"` (AC 50)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes an own address without reading it first"`

**C54** - Com as requisições bem-sucedidas do dono, a contagem de queries é a de `714f1fe`: `GET /api/orders/{order}` de um pedido sem itens, 2; `POST /api/orders/{order}/payment` com `fake_card_approved` num pedido `awaiting_payment`, 3; `PUT /api/account/addresses/{address}` com `addressPayload(['number' => '2000'])`, 2. O teste é table-driven sobre as 3 (AC 51)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the query count of the owner reads and updates"`

**C55** - `backend/app` e `backend/tests` não citam `OrderPolicy`, `CustomerAddressPolicy` nem `OrderPaymentPolicy` (AC 52)
Proof: `! grep -rnE -e 'OrderPolicy|CustomerAddressPolicy|OrderPaymentPolicy' backend/app backend/tests`

**C56** - Por reflexão, `PayableOrders::findForPayment` tem exatamente dois parâmetros, `string $customerId` e `string $orderId`, nessa ordem, e retorna `?OrderForPayment` (AC 53, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares the owner in the payable orders contract"`

**C57** - Na própria camada, a busca leva o dono (Test policy, door 5):
- `OrderRepository::findForPayment` devolve o pedido do próprio cliente e `null` para o pedido de outro cliente e para um id desconhecido;
- a busca do pedido do `GET /api/orders/{order}` faz o mesmo: devolve o pedido do dono e nada nos outros dois casos;
- no `CustomerAddressRepository`, a edição e a exclusão alteram só o endereço do próprio cliente. Para o endereço de outro cliente e para um id desconhecido, não alteram nada e respondem como não encontrado.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="finds an order for payment only among the customer own orders"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="finds an order only among the customer own orders"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="changes an address only in the customer own book"`

**C58** - Na própria camada, o `PayOrderUseCase`, com o id de um pedido `payment_approved` de outro cliente, lança `ModelNotFoundException`, e não a `BusinessRuleException` do `409`. O gateway espião não recebe cobrança (Test policy, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses another customer order before checking its status"`

**C59** - O teste de arquitetura percorre `Route::getRoutes()` e, para cada rota sob `api/`, exige `signatureParameters(['subClass' => UrlRoutable::class])` vazio. Ele afirma que percorreu pelo menos 19 rotas com parâmetro (AC 54, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves no record by implicit binding in any api route"`

**C60** - O mesmo teste exige `Route::getBindingCallback($name)` nulo para cada parâmetro de cada rota sob `api/` (AC 55, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves no record by explicit binding in any api route"`

**C61** - O mesmo teste exige que `$route->wheres[$name]` seja a expressão canônica da door 2 para cada parâmetro de cada rota sob `api/`, lida da mesma fonte no `Shared` que o `AppServiceProvider` usa (AC 56, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="constrains every api route parameter to the canonical id"`

**C62** - Exercício temporário, uma violação por regra, cada uma revertida antes da próxima (AC 57):
- (a) `ProductController::show` volta a receber `Product $product`: o C59 falha;
- (b) `Route::bind('product', fn (string $id): string => $id);` no `boot()` do `AppServiceProvider`: o C60 falha;
- (c) a linha do `Route::pattern` de `address` sai do `AppServiceProvider`: o C61 falha.

Revertidas as três, os três testes passam de novo. Nos três casos, o arquivo de teste não é editado.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves no record by implicit binding in any api route"` com (a) aplicada: sai com código diferente de zero
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves no record by explicit binding in any api route"` com (b) aplicada: sai com código diferente de zero
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="constrains every api route parameter to the canonical id"` com (c) aplicada: sai com código diferente de zero

### Documentação e fechamento · 3 arquivos · 161 KB · ~40k

**C63** - O README, a análise de domínio e o `AGENTS.md` descrevem o estado novo e não citam mais o antigo (plano, Impact, linha docs):
- o README e o `AGENTS.md` não citam as três policies, `payableOrder` nem `Route::bind`;
- a tabela da API no README não tem `403` em nenhuma linha de `/api/orders/{id}` nem de `/api/account/addresses/{id}`;
- a análise de domínio tem o problema `#### 13. ✅ Resolvido:` e a linha "Última atualização" cita a HEL-11;
- a análise de domínio não diz mais que o pedido do pagamento é "resolvido por um binding explícito da rota".

Proof: `! grep -nE -e 'OrderPolicy|CustomerAddressPolicy|OrderPaymentPolicy|payableOrder|Route::bind' README.md AGENTS.md`
Proof: `! grep -nE -e '^\| [A-Z]+ \| ./api/(orders|account/addresses)/\{id\}.*.403.' README.md`
Proof: `grep -qE -e '^#### 13\. ✅ Resolvido:' docs/domain-analysis.md`
Proof: `grep -m1 -e 'Última atualização' docs/domain-analysis.md | grep -q -e 'HEL-11'`
Proof: `! grep -q -e 'resolvido por um binding explícito da rota' docs/domain-analysis.md`

**C64** - Os status das rotas do `Surface` que esta feature não muda continuam provados. Um teste é novo: o de `429` da validação do carrinho, que não tinha prova:
- `401` na loja: sessão de equipe e visitante nas rotas de pedido, pagamento, conta e endereço;
- `401` no admin: visitante e sessão de cliente em toda rota de `/api/admin`;
- `403` do suporte nos `DELETE` e em `/api/admin/users*`;
- `402` do cartão recusado e `409` do pedido fora de `awaiting_payment`;
- `429` do checkout, do pagamento e da validação do carrinho (61ª requisição no mesmo minuto);
- `201` da criação de produto.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a staff session on every store route"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a guest on every address route"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a guest on every admin route"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a customer session on every admin route"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids every admin delete route to the support role"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids the support role from managing staff"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declines the payment and keeps the order awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects payment for orders that are not awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles order placement after 20 requests per minute"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles payment attempts after 20 per minute"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles the cart validation after 60 requests per minute"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a product with its price in cents"`

**C65** - A suíte inteira do backend, o estilo e a suíte do frontend passam no branch terminado (AGENTS.md, verificação antes de concluir)
Proof: `make test`
Proof: `docker compose exec -T api ./vendor/bin/pint --test`
Proof: `docker compose exec -T frontend npm test`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| `POST /api/orders` statuses (6) | 201 C22, C25 · 401 C64 · 409 C14 · 422 C10, C11, C15 · 429 C64 · 500 C26 | - |
| `POST /api/cart/validate` statuses (3) | 200 C13 · 422 C10 · 429 C64 | - |
| `GET /api/orders` e `GET /api/orders/{order}` statuses (3) | 200 C30, C48 · 401 C64 · 404 C49 | - |
| `POST /api/orders/{order}/payment` statuses (7) | 202 C48 · 401 C64 · 402 C64 · 404 C49, C50, C51 · 409 C64 · 422 C52 · 429 C64 | - |
| `PUT` e `DELETE /api/account/addresses/{address}` statuses (5) | 200 C48 · 204 C48, C53 · 401 C64 · 404 C49 · 422 C52 | - |
| `GET /api/account` statuses (2) | 200 C5, C31 · 401 C64 | - |
| `POST /api/admin/products` e `PUT /api/admin/products/{product}` statuses (5) | 200 C46 · 201 C64 · 401 C64 · 404 C36 · 422 C12, C16, C37 | - |
| `GET` e `PUT /api/admin/customers/{customer}` statuses (4) | 200 C31, C45, C46 · 401 C64 · 404 C36 · 422 C37 | - |
| `GET /api/admin/orders` e `GET /api/admin/orders/{order}` statuses (3) | 200 C30, C46 · 401 C64 · 404 C36 | - |
| vitrine, produto, status, categoria, estoque e equipe com registro: statuses (5) | 200 C46, C39 · 401 C64 · 403 C64 · 404 C36, C38 · 422 C37 | - |
| `DELETE` do admin statuses (5) | 204 C40, C44 · 401 C64 · 403 C64 · 404 C36 · 409 C41, C42, C43 | - |
| chaves primárias `uuid` (9) | `users` C1, C2 · `customers` C1, C2 · `categories` C1, C2 · `products` C1, C2 · `stocks` C1, C2 · `orders` C1, C2 · `order_items` C1, C2 · `payments` C1, C2 · `customer_addresses` C1, C2 | - |
| chaves estrangeiras `uuid` e regra de exclusão (8) | `stocks.product_id` C1, C3 · `category_product.product_id` C1, C3 · `category_product.category_id` C1, C3 · `orders.customer_id` C1, C3 · `order_items.order_id` C1, C3 · `order_items.product_id` C1, C3 · `payments.order_id` C1, C3 · `customer_addresses.customer_id` C1, C3 | - |
| relações do `Relations` (8) | `customers` → `orders` C3 · `customers` → `customer_addresses` C3 · `orders` → `order_items` C3 · `products` → `order_items` C3 · `products` → `stocks`, única C3, C4 · `products` → `category_product` C3, C4 · `categories` → `category_product` C3, C4 · `orders` → `payments` C3, C4 | - |
| check constraints de `714f1fe` (17) | C4, table-driven sobre as 17 nomeadas no check | - |
| únicas, chave composta e índice parcial (6) | `categories_slug_unique` C4 · `customers_email_unique` C4 · `stocks_product_id_unique` C4 · `users_email_unique` C4 · `category_product_pkey` C4 · `payments_order_id_approved_unique` C4 | - |
| resources (13) | `CategoryResource` C5 · `ProductResource` C5 · `CustomerAddressResource` C5 · `CustomerProfileResource` do Customers C5 · `OrderSummaryResource` C5, C31 · `CustomerSummaryResource` C5, C31 · `CustomerProfileResource` do Identity C5 · `StaffMemberResource` C5 · `StockResource` C5 · `OrderResource` C5, C30 · `OrderItemResource` C5 · `ValidatedCartResource` C5 · `PaymentResource` C5 | - |
| métodos de contrato com id (9) | `hasBeenOrdered` C6 · `updateProfile` C6 · `findProfile` C6 · `createForProduct` C6 · `decrement` C6 · `countForCustomer` C6 · `recentForCustomer` C6 · `DeliveryAddressBook::find` C6 · `findForPayment` C6, C56 | - |
| eventos e job com `orderId` (6) | `OrderPlaced` C7 · `OrderPaid` C7 · `PaymentApproved` C7 · `DeliveryScheduled` C7 · `OrderDelivered` C7 · `DeliverOrder` C7 | - |
| listas tipadas de ids (3) | `ProductIds` C9 · `CategoryIds` C9 · `CustomerIds` C9 | - |
| linhas das listas tipadas (6) | canônico aceito C9 · `"1"` C9 · `""` C9 · `"abc"` C9 · maiúsculas C9 · repetido C9 | - |
| ids no corpo (3) | `items.*.product_id` C10, C13, C14 · `address_id` C11, C15 · `category_ids.*` C12, C16 | - |
| valores malformados no corpo (4) | `1` C10, C11, C12 · `"1"` C10, C11, C12 · `"abc"` C10, C11, C12 · maiúsculas C10, C11, C12 | - |
| valores malformados no caminho (3) | `1` C17 · `abc` C17 · maiúsculas C17 | - |
| nomes de parâmetro de rota (7) | `product` C17, C61 · `category` C17, C61 · `stock` C17, C61 · `customer` C17, C61 · `user` C17, C61 · `order` C17, C61 · `address` C17, C61, C62 | - |
| configuração de partida: padrões de rota (2 places) | aplicação, pelo `AppServiceProvider` de `bootstrap/providers.php` C61 · testes, pela mesma `bootstrap/app.php` C17 | - |
| rotas do frontend com id (8) | `product` C18 · `payment` C18, C34 · `account.order` C18 · `admin.products.edit` C18 · `admin.customers.show` C18 · `admin.customers.edit` C18 · `admin.users.edit` C18 · `admin.orders.show` C18 | - |
| URLs fora do formato no frontend (4) | `/products/7` C19 · `/account/orders/42` C19 · `/payment/abc` C19 · UUID em maiúsculas C19 | - |
| chaves do carrinho no navegador (2) | `cart-v2` descartada C20 · `cart-v3` gravada C20 | - |
| lugares que mostram o pedido (9) | lista do cliente C33 · detalhe do cliente C33 · último pedido do painel C33 · recentes do painel C33 · pagamento C33 · aviso de pedido criado C33 · lista do admin C33 · detalhe do admin C33 · detalhe do cliente no admin C33 | - |
| bordas do código (4) | nulo C23 · 7 caracteres C23 · 8 válidos C22, C23 · 9 caracteres C23 | - |
| símbolos fora do alfabeto (6) | `0` C23 · `O` C23 · `1` C23 · `I` C23 · `L` C23 · minúscula C23 | - |
| colisões seguidas (4) | 0 C22, C27 · 1 C25, C27 · 2 C25, C27 · 3 C26, C27 | - |
| onde o código aparece (8) | `POST /api/orders` C22 · `GET /api/orders` C30 · `GET /api/orders/{order}` C30 · `GET /api/admin/orders` C30 · `GET /api/admin/orders/{order}` C30 · `last_order` C31 · `recent_orders` C31 · pedidos do cliente no admin C31 | - |
| onde o código não aparece (3) | parâmetros de contrato C32 · eventos e job C32 · caminhos de rota C32, C34 | - |
| rotas da equipe e da vitrine com registro (15) | C35, C36, table-driven sobre as 15 listadas no cabeçalho da S3 | - |
| corpo inválido na equipe (12) | C37, table-driven sobre as 6 rotas e os 2 ids, 12 casos | - |
| contagem de queries por requisição (21) | `GET /api/products/{product}` 3 C46 · `GET /api/orders/{order}` 2 C54 · pagamento 3 C54 · `PUT` de endereço 2 C54 · `DELETE` de endereço 1 C53 · checkout 9 C29 · `GET` de produto no admin 3 C46 · `PUT` de produto 10 C46 · status 4 C46 · `DELETE` de produto 2 C40 · `GET` de categoria 2 C46 · `PUT` de categoria 4 C46 · `DELETE` de categoria 2 C40 · estoque 3 C39 · `GET` de cliente 3 C46 · `PUT` de cliente 4 C45 · `GET` de pedido no admin 3 C46 · `GET` de membro 1 C46 · `PUT` de membro 3 C46 · `DELETE` de membro 1 C44 · `DELETE` da própria conta, nenhuma em `users` C43 | - |
| rotas do cliente com registro (4) | `GET /api/orders/{order}` C48, C49 · `POST /api/orders/{order}/payment` C48, C49, C52 · `PUT /api/account/addresses/{address}` C48, C49, C52 · `DELETE /api/account/addresses/{address}` C48, C49, C53 | - |
| status fora de `awaiting_payment` (3) | `placed` C50 · `payment_approved` C50, C58 · `delivered` C50 | - |
| policies que saem (3) | `OrderPolicy` C55 · `CustomerAddressPolicy` C55 · `OrderPaymentPolicy` C55 | - |
| exclusões por id na própria camada (4) | produto C47 · categoria C47 · membro da equipe C47 · endereço C57 | - |
| busca com o dono na própria camada (5) | `findForPayment` C57 · pedido do `GET` C57 · edição de endereço C57 · exclusão de endereço C57 · `PayOrderUseCase` C58 | - |
| formas de binding (3) | implícito C59, C62 · explícito C60, C62 · padrão do parâmetro C61, C62 | - |
| doors do `Landing` (5) | door 1 C1, C2, C6, C7, C8 · door 2 C9, C10, C11, C12, C17, C61 · door 3 C22, C23, C24, C25, C26, C27, C28 · door 4 C35, C47, C59, C60, C62 · door 5 C49, C50, C56, C57, C58 | - |
| documentos (3) | README C63 · análise de domínio C63 · `AGENTS.md` C63 | - |

- As afirmações sobre status, rota ou formato de resposta (C5, C10 a C17, C22, C25, C26, C29 a C31, C36 a C46, C48 a C54 e C64) têm prova que atravessa o HTTP.
- C1 a C4, C23 e C24 provam o esquema no banco, pelo catálogo do PostgreSQL ou por gravação recusada. C6 a C8, C32, C35, C56 e C59 a C61 são provas estruturais por reflexão ou pela tabela de rotas, e o C62 mostra que as três últimas falham.
- C27, C28, C47, C57 e C58 provam as decisões na própria camada, além das provas de borda.
- C18 a C20, C33 e C34 provam o frontend pela tabela de rotas, pelo store, pelo composable e por `grep`. O profile `standard` não renderiza telas; o arranjo delas fica para quem revisar o diff.
- Nenhum check afirma mais que os casos que a sua prova exercita: os table-driven dizem o tamanho do conjunto.

## Test policy

O README diz onde ficam e como rodar os testes, mas não diz qual nível prova cada tipo de código. As linhas abaixo valem para esta feature, no mesmo molde das features anteriores.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Decide e é alcançado por uma borda (listas tipadas pelo corpo, sorteio e repetição do código, busca com o dono, exclusão por id) | uma na borda **e** uma na própria camada | o contrato na borda; um caso por linha da tabela de decisão na própria camada |
| Decide sem borda própria (alfabeto do código, padrões das rotas do frontend, carga do carrinho) | uma na própria camada | um caso por linha |
| Entrada que só confere formato (Form Requests dos ids, padrões de rota do backend) | uma na borda | a entrada aceita e cada valor recusado |
| Esquema (tipo das chaves, regras de exclusão, constraints) | uma no banco, pelo catálogo ou por gravação recusada | uma por coluna, regra ou constraint |
| Regra de arquitetura (sem binding, padrão em todo parâmetro, sem id inteiro, código fora das fronteiras) | uma por regra, e as de binding vistas falhando com uma violação de propósito | uma violação por regra |
| Instrumentação (resources, controllers, contratos e eventos que só carregam o tipo) | nenhuma própria além da reflexão do tipo | coberta pelas provas de borda |

Evidence:

- construtor de `ProductIds`, `CategoryIds` e `CustomerIds`: 2 pontos de decisão (formato, repetição), 6 linhas → decide. Análogo: `TypedListsTest` "refuses an invalid id in a typed id list", na própria camada.
- sorteio e repetição do código: 2 pontos de decisão (colisão, tentativas restantes), 4 saídas (0, 1, 2 e 3 colisões) → decide. Análogo: a gravação isolada do pagamento, provada na própria camada pelo `PayOrderUseCaseTest` "raises a conflict when a concurrent approval wins the race" e na borda pelo `PaymentTest` "loses the race to a concurrent approval with 409".
- busca com o dono: 1 ponto de decisão (`customer_id`), 3 linhas (do dono, de outro, desconhecido) → decide. Análogo: `CustomerAddressRepositoryTest` "finds a delivery address only in the customer own book", com as mesmas 3 linhas.
- exclusão por id: 2 pontos de decisão (regra de bloqueio, nenhuma linha apagada) → decide. Análogo: `ProductUseCasesTest` e `CategoryUseCasesTest` já provam a regra de bloqueio na própria camada.
- alfabeto do código: tabela de 31 símbolos, sem condicional → decide pela tabela, provado por amostragem (C28).
- padrões das rotas do frontend: 1 ponto por rota, 8 rotas → decide. Análogo: `router/guards.test.ts` resolve pela tabela de rotas real.
- carga do carrinho: 1 ponto (chave antiga presente) → decide. Análogo: `stores/cart.test.ts` "discards the legacy cart key".
- resources: mapeiam campos sem condição nova → instrumentação, coberta pelo C5, pelo C30 e pelo C31.

Cost: 10 provas na própria camada (C9, C27, C28, C47 com 3 testes, C57 com 3 testes, C58) em 8 arquivos de teste. Sem estas linhas, o sorteio do código, a busca com o dono e a exclusão por id seriam provados só pelo caminho que a borda atravessa, e a linha "não encontrado" de cada um ficaria sem prova própria.

## Swept

- validation: C9, C10, C11, C12, C17 e C23, porque o formato do id é conferido no corpo, no caminho e nas listas, e o código é conferido no banco
- failure modes: C26, porque a terceira colisão desfaz o pedido, os itens e a baixa de estoque; e C12, porque uma regra `exists` nunca recebe um id malformado e nunca vira `500`
- idempotency: C24 e C4, porque o código único e o índice parcial de um pagamento aprovado por pedido continuam no banco; um `DELETE` repetido responde `404` (C36, C49)
- authorization: C49, C50 e C55, porque o dono passa para dentro da busca e as policies saem; o guard `staff` e o middleware `admin` não mudam (C64)
- concurrency: C25, porque uma colisão não solta o lock (uma só leitura `for update`); e C24, porque dois pedidos simultâneos com o mesmo código esbarram no índice único
- data lifecycle: C3, porque as regras de exclusão das chaves estrangeiras não mudam; nenhuma linha é migrada, já que `make fresh` recria o banco (plano, Impact)
- dependency failure: C51 e C58, porque um pedido desconhecido ou de outro cliente nunca chega ao gateway de pagamento
- state transitions: C50 e C58, porque o dono é conferido antes do status: `404`, nunca `409`; as transições do pedido não mudam
- observability: C17 e C36, porque o `404` sai com o envelope `NOT_FOUND` e o `request_id`; a terceira colisão (C26) é registrada pelo handler do Laravel, sem log novo (plano, Assumptions)

## Handoff

- S1 = 115k: 461 KB em 194 arquivos (125 KB em 104 do backend, 206 KB em 46 de teste, 131 KB em 44 do frontend), `wc -c` dividido por 4. S2 = 20k (79 KB em 22 arquivos), e S1 e S2 juntas somam 135k. S3 entra nas rotas da equipe e da vitrine com 22k (90 KB) e leva o total a 157k, acima do budget de 150k. S4 = 26k (103 KB) e a documentação = 40k (161 KB). Total: 223k
- Corte proposto depois da S2, onde a superfície muda: o lote 1 (S1 e S2, 135k) troca o tipo do id e cria o código; o lote 2 (S3, S4 e a documentação, 88k) tira o binding das rotas
- Mechanism: handoff, escolhido por diashelter em 2026-10-15 antes de qualquer código. Lote 1 = S1 e S2 (C1 a C34); lote 2 = S3, S4 e a documentação (C35 a C65). Sequenciais, e o lote 2 só começa com todas as provas do lote 1 verdes. Cada builder lê o `checks.md` e o diff do que já entrou, nunca um resumo
- Boundary: a definir ao fim do lote 1
- Settled mid-build: nada até agora
- Abandoned: nada até agora
