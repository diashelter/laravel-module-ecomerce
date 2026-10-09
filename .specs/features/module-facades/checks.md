# Facades dos módulos - checks

Profile: standard
Plan: `.specs/features/module-facades/plan.md`

70 checks in 7 slices · 6 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. O frontend roda via `docker compose exec -T frontend npx vitest run -t "<nome do teste>"` e a checagem de tipos via `docker compose exec -T frontend npm run type-check`. O CI não roda o frontend, então essas provas precisam rodar localmente antes do PR. Os nomes de teste novos não usam parênteses, colchetes nem barras, porque o `--filter` do Pest e o `-t` do Vitest são expressões regulares.

A fila roda em modo `sync` nos testes (README, seção Testes). Os checks que afirmam um horário congelam o relógio com `travelTo`. "Sessão de cliente" e "sessão de equipe" são autenticações nos guards `customer` e `staff`, como nos testes atuais.

Testes existentes que afirmam o comportamento antigo são substituídos pelos checks que afirmam o novo, conforme o plano aprovado, e não afrouxados:
- `PaymentTest` "approves the payment of an order awaiting payment" afirmava `data.id` igual ao id do pedido; passa a afirmar o id da tentativa e `data.order_id` igual ao do pedido (C38);
- as asserções `Event::assertDispatched(..., fn ($e) => $e->order...)` em `PaymentTest`, `PayOrderUseCaseTest`, `CheckoutTest`, `PlaceOrderUseCaseTest`, `OrderStatusFlowTest` e `DeliveryEstimateTest` passam a comparar `orderId` com o id do pedido, sem perder nenhuma asserção;
- os testes que entregam eventos à mão aos listeners (`OrderStatusFlowTest`) passam a construí-los com ids;
- `StockRepositoryTest`, `PurchaseAvailabilityServiceTest`, `CheckoutServiceTest`, `OrderRepositoryTest`, `UserUseCasesTest`, `CustomerAddressUseCasesTest`, `PayOrderUseCaseTest` e `PaymentServiceTest` passam a chamar as assinaturas novas, com as mesmas asserções de resultado;
- a linha `ProductRepository::findManyKeyedById` do `TypedListSignaturesTest` vai para o `ProductCatalog` se o método sair do repositório (C10);
- o mock de `orderService.pay` no `usePayment.test.ts` passa a devolver `{ payment, message }`, sem mudar nenhuma asserção.

## Checks

### S1 - ModuleBoundariesTest · 2 arquivos · 9 KB · ~2k

**C1** - Para cada par de módulos distintos A e B, com B diferente de `Shared`, e para cada pasta de `app/Modules/B` fora de `Contracts`, `Events`, `DTOs`, `ValueObjects`, `Enums` e `Http`, existe uma expectativa de que `App\Modules\A` não usa `App\Modules\B\<pasta>`. As expectativas são geradas a partir das pastas que existem, e o teste afirma que a lista de pastas privadas contém pelo menos as 9 de hoje: `Exceptions`, `Gateways`, `Jobs`, `Listeners`, `Models`, `Policies`, `Repositories`, `Services` e `UseCases` (AC 1, AC 3, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reaches another module only through its public directories"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="generates the private directory rules from the folders that exist"`

**C2** - Para cada classe declarada direto em `app/Modules/B` (hoje os service providers de Customers, Fulfillment, Inventory, Ordering e Payment, mais os novos de Catalog e Identity), existe uma expectativa de que nenhum outro módulo A a usa. O teste afirma que há pelo menos 7 dessas classes (AC 2, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not use another module root class"`

**C3** - A lista de exceções do teste é exatamente esta, cada uma registrada com a issue `HEL-6` (AC 4, door 1):
- `Backoffice` → `Catalog\Repositories`, `Identity\Repositories`, `Inventory\Repositories` e `Ordering\Repositories`;
- `Catalog\Models` → `Inventory\Models\Stock`;
- `Inventory\Models` → `Catalog\Models\Product`;
- `Catalog\Http` → `Ordering\Services\PurchaseAvailabilityService`;
- `Inventory\Http` → `Ordering\Services\PurchaseAvailabilityService`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares exactly the HEL-6 exceptions"`

**C4** - `Contracts` só tem interfaces em cada um dos 5 módulos que têm a pasta: Catalog, Identity, Inventory, Ordering e Payment (AC 5, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps only interfaces in every module contracts"`

**C5** - Por reflexão, nenhum método de nenhuma interface em `app/Modules/*/Contracts` declara parâmetro ou retorno que seja subclasse de `Illuminate\Database\Eloquent\Model` ou `Illuminate\Database\Eloquent\Collection`. O teste afirma que percorreu pelo menos 11 interfaces (AC 6, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="speaks only data in every contract signature"`

**C6** - Todas as regras que o `ModuleBoundariesTest` tem hoje continuam presentes e passando (AC 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reaches the inventory only through its contracts"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="the catalog does not use the ordering"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="identity does not depend on ordering"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not use the staff user"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="never changes the order status itself"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="payment does not know fulfillment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="fulfillment does not know payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ordering does not know customers"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ordering reaches fulfillment only through its events"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="fulfillment does not know customers"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reaches the payment gateway only through its contract"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="the shared kernel does not depend on"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="identity value objects are final and readonly"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not write the password policy itself"`

**C7** - Quatro violações de propósito, uma de cada vez, fazem o `ModuleBoundariesTest` sair com código diferente de zero (AC 8):
- (a) um `use App\Modules\Ordering\Models\Order` no Payment;
- (b) um `use App\Modules\Payment\PaymentServiceProvider` no Ordering;
- (c) uma classe concreta em `Catalog\Contracts`;
- (d) um método com retorno `Product` em `ProductCatalog`.

Cada violação é revertida depois.
Proof: `docker compose exec -T api ./vendor/bin/pest tests/Unit/Architecture/ModuleBoundariesTest.php` com cada uma das quatro violações aplicada, uma de cada vez: sai com código diferente de zero

**C8** - Na branch terminada, o `ModuleBoundariesTest` inteiro passa (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest tests/Unit/Architecture/ModuleBoundariesTest.php`

**C9** - Cada contrato novo resolve pelo container para a implementação do seu módulo (door 2):
- `ProductCatalog` → `ProductRepository`;
- `StockLevels` → `StockRepository`;
- `PayableOrders` → `OrderRepository`;
- `CustomerOrderHistory` → `OrderRepository`;
- `CustomerAccounts` → `CustomerAccountRepository`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves the module contracts to their implementations"`

**C10** - O `TypedListSignaturesTest` cobre `ProductCatalog`, `StockLevels`, `StockReservation`, `PayableOrders`, `CustomerOrderHistory` e `CustomerAccounts` e não encontra `array` em nenhuma assinatura deles (AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares no array in the domain list signatures"`

### S2 - Eventos do pedido · 20 arquivos · 31 KB · ~7k

**C11** - Por reflexão, nenhuma propriedade pública de `OrderPlaced`, `OrderPaid`, `PaymentApproved`, `DeliveryScheduled`, `OrderDelivered` e `DeliverOrder` tem tipo que seja subclasse de `Model`. O `OrderPaid` continua implementando `ShouldDispatchAfterCommit` (AC 11, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="carries no model in the order events and the delivery job"`

**C12** - Um cliente faz um pedido pela rota e o `OrderPlaced` é publicado uma vez, com `orderId` igual ao id do pedido criado (AC 12, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="announces the placed order by id"`

**C13** - Um pagamento aprovado pela rota publica `PaymentApproved` exatamente uma vez, com `orderId` igual ao id do pedido pago (AC 13, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records the approved payment and announces it once"`

**C14** - Com um `PaymentApproved` entregue à mão para um pedido em `awaiting_payment` com `delivery_business_days` 4, o pedido vai para `payment_approved` e o `OrderPaid` é publicado com `orderId` igual ao id do pedido e `deliveryBusinessDays` 4 (AC 14, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="marks the order as paid and announces it, without scheduling the delivery itself"`

**C15** - Com o relógio em segunda-feira, 2026-10-05, 10:00 de São Paulo, um `OrderPaid` entregue à mão ao listener do Fulfillment com `deliveryBusinessDays` 3 faz duas coisas: publica `DeliveryScheduled` com o mesmo `orderId` e `estimatedDeliveryOn` 2026-10-08, e enfileira `DeliverOrder` com o mesmo `orderId`. O pedido não é lido para isso (AC 15, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="schedules the delivery from the business days in OrderPaid"`

**C16** - Com a fila síncrona, um pedido criado, pago com `fake_card_approved` e entregue termina com status `delivered` e `estimated_delivery_on` preenchida (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="runs the whole lifecycle with a synchronous queue"`

**C17** - Um `DeliveryScheduled` entregue para um pedido que já tem `estimated_delivery_on` mantém a primeira data (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the first delivery estimate"`

**C18** - Um `OrderDelivered` entregue duas vezes deixa o pedido em `delivered`, e a segunda entrega não muda nada (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ignores out of order or duplicated executions"`

**C19** - Um `PaymentApproved` para um pedido que não está mais em `awaiting_payment` não publica `OrderPaid` (AC 19)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="announces OrderPaid only once for a duplicated payment approval"`

**C20** - Cada um dos cinco listeners age sobre o pedido carregado pelo id do evento:
- `MarkOrderAsAwaitingPayment` move de `placed` para `awaiting_payment`;
- `RecordEstimatedDelivery` grava a data sem mudar o status;
- `MarkOrderAsDelivered` move de `payment_approved` para `delivered`;
- o Fulfillment anuncia a entrega com `OrderDelivered` carregando o `orderId`;
- os listeners continuam registrados para os seus eventos.

`MarkOrderAsPaid` e `ScheduleOrderDelivery` estão em C14 e C15 (door 4).

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="moves a placed order to awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records the delivery estimate without changing the status"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="marks a paid order as delivered"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="announces the delivery without changing the order status itself"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="registers the listeners for the order events"`

### S3 - StockInitializer · 6 arquivos · 15 KB · ~3k

**C21** - Uma sessão de equipe cria um produto pela rota do admin com `stock_quantity` 7 e recebe `201`; `stocks` passa a ter exatamente uma linha com aquele `product_id` e `quantity` 7 (AC 20)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="opens the stock of a new product with the given quantity"`

**C22** - `StockInitializer::createForProduct` recebe o id do produto e a quantidade, cria uma linha em `stocks` com esses valores e não devolve nada (door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates the stock row of a product"`

**C23** - Quando a abertura do estoque falha dentro do `CreateProductUseCase` (quantidade `-1`, recusada pelo `CHECK` de `stocks`), a exceção sobe e não fica nenhuma linha em `products` nem em `stocks` (AC 21)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rolls back the product when the stock cannot be opened"`

### S4 - ProductCatalog e StockLevels · 21 arquivos · 48 KB · ~11k

**C24** - `POST /api/cart/validate` com 5 unidades de um produto com 2 em estoque, 1 de um produto inativo e 1 do `product_id` 999999 responde `200` com `is_valid` `false` e os `problem` `"Estoque insuficiente. Disponível: 2."`, `"Produto indisponível."` e `"Produto não encontrado."`, nessa ordem de produto (AC 22, AC 23, AC 24)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reports problems for each item"`

**C25** - `POST /api/cart/validate` com um produto ativo sem linha em `stocks` responde `200` com aquele item em `available_quantity` 0, `is_available` `false` e `problem` `"Produto indisponível."` (AC 25)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="treats a product without a stock row as unavailable in the cart"`

**C26** - `POST /api/cart/validate` só com produtos ativos em estoque responde `200` com `is_valid` `true`. Cada item traz `name`, `image_url` e `unit_price_cents` iguais aos de `products`, e o formato da resposta é o mesmo de hoje (AC 26)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="recalculates the cart in cents from database prices"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the exact cart validation response shape"`

**C27** - Um cliente faz um pedido de produtos disponíveis e recebe `201`. Cada `order_items` guarda o nome e o `price_cents` do produto naquele momento, e cada `stocks.quantity` cai exatamente a quantidade comprada (AC 27)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="places an order with every value in cents, decrements stock and stores a snapshot of the items"`

**C28** - Um pedido com um produto inexistente, um inativo ou um com menos unidades que o pedido responde `409`, com erro em `errors.items.{product_id}`. Não sobra pedido, e todos os `stocks.quantity` ficam iguais (AC 28)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns 409 and changes nothing when stock is insufficient"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects inactive and out of stock products"`

**C29** - O checkout bloqueia as linhas de estoque com um único `SELECT ... FOR UPDATE` em `stocks`, ordenado por `product_id`, antes da consulta a `products`. Duas compras seguidas de 4 e 3 unidades num estoque de 5 deixam a segunda recusada com `409` e o estoque em 1 (AC 29, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="locks the stock rows with SELECT"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="never lets stock go negative"`

**C30** - Um pedido com um `address_id` fora do caderno do cliente responde `422` em `errors.address_id`, e nenhum `stocks.quantity` muda. No caso de uso, o estoque não chega a ser bloqueado (AC 30)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a delivery address that is not in the customer address book"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an unknown delivery address before touching the stock"`

**C31** - `GET /api/products` mostra um produto ativo com 0 unidades com `is_available` `false`, e a lista de estoque do admin mostra o mesmo, como hoje (AC 31)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists active and inactive products with availability"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="makes a product unavailable at zero stock and available again only if active"`

**C32** - O `PurchaseAvailabilityService`, recebendo se o produto está ativo, a quantidade em estoque e a quantidade pedida, decide (Assumptions do plano):
- ativo com unidades: disponível e sem problema;
- inativo: `"Produto indisponível."`;
- ativo com 0 unidades: `"Produto indisponível."`;
- pedido maior que o estoque: `"Estoque insuficiente. Disponível: N."` com o N do estoque;
- sem linha de estoque: tratado como 0 unidades.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="decides availability from the product status and the stock units"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="treats a missing stock row as zero units"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="explains why a quantity cannot be bought"`

**C33** - `StockLevels::quantitiesFor` devolve um `StockQuantities` em que `of()` dá a quantidade de cada produto com linha em `stocks` e 0 para um produto sem linha, sem emitir `FOR UPDATE` (door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reads the stock quantities of the given products without locking"`

**C34** - `StockReservation::lockForProducts` devolve um `StockQuantities` com a quantidade de cada produto, e `StockReservation::decrement` com um `product_id` e 2 baixa exatamente 2 unidades daquela linha e de nenhuma outra (door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="locks the stock rows of the given products keyed by product id"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="decrements the stock quantity"`

**C35** - `ProductCatalog::findMany` com os ids de um produto ativo, de um inativo e o 999999 devolve um `CatalogProducts` com uma entrada para cada produto existente. Cada entrada tem o `id`, o `name`, o `imageUrl`, o `priceCents` e o `isActive` do produto (`true` e `false`), e `find(999999)` devolve `null` (door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="finds catalog products by id for other modules"`

**C36** - O `CheckoutService` recebe os produtos do catálogo e as quantidades do estoque, sem models, e mantém as três decisões de hoje (door 2):
- aceita quantidades atendíveis;
- reporta cada produto que não pode ser comprado em `items.{id}`;
- monta as linhas com o preço do catálogo.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="accepts quantities that the stock can fulfil"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reports every product that cannot be bought, keyed by item"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="builds the order lines and total in cents"`

### S5 - PayableOrders · 15 arquivos · 44 KB · ~10k

**C37** - Uma sessão de cliente paga um pedido próprio em `awaiting_payment` com `card_token` `"fake_card_approved"` e recebe `202`. O corpo traz `message` `"Pagamento aprovado. O pedido será atualizado em instantes."` e o `data` (AC 32, door 5):
- `id` igual ao id da nova linha de `payments`;
- `order_id` igual ao id do pedido;
- `status` `"approved"`;
- `amount_cents` igual a `orders.total_cents`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="approves the payment of an order awaiting payment"`

**C38** - O `data` da resposta `202` tem exatamente as chaves `id`, `order_id`, `status` e `amount_cents`: nada de `card_token`, `gateway`, `gateway_transaction_id` ou `decline_reason` (AC 33, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers the payment attempt without card data"`

**C39** - `POST /api/orders/999999/payment` com uma sessão de cliente responde `404`, e `payments` continua sem linhas (AC 34, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 to the payment of an unknown order"`

**C40** - Pagar o pedido de outro cliente responde `403`, e `payments` continua sem linhas (AC 35)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids paying an order of another customer"`

**C41** - Pagar um pedido em `placed`, `payment_approved` ou `delivered`, ou um pedido em `awaiting_payment` que já tem pagamento aprovado, responde `409` com `message` `"Este pedido não está aguardando pagamento."` (AC 36)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects payment for orders that are not awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a second payment while the queue has not moved the order"`

**C42** - Uma recusa do gateway responde `402` com o motivo. A tentativa fica gravada como `declined`, e o pedido continua em `awaiting_payment` (AC 37)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declines the payment and keeps the order awaiting payment"`

**C43** - Na corrida de duas aprovações para o mesmo pedido, só existe uma linha `approved` em `payments`, e a perdedora recebe `409` (AC 38)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="loses the race to a concurrent approval with 409"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="allows only one approved payment per order in the database"`

**C44** - Um valor enviado no corpo é ignorado, e a cobrança e `amount_cents` usam `orders.total_cents` (AC 39)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="charges the order total and ignores amounts in the request"`

**C45** - `PayableOrders::findForPayment` devolve, para um pedido existente, um `OrderForPayment` com o `id`, o `customerId`, o `totalCents` e o `status` dele, e `null` para o id 999999 (door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="finds an order for payment by id"`

**C46** - O `PayOrderUseCase`, recebendo um `OrderForPayment` em vez do model, mantém as quatro saídas de hoje (door 2):
- publica `PaymentApproved` com o `orderId` para um pedido em `awaiting_payment`;
- não publica para os outros status;
- mantém a tentativa recusada depois de lançar a recusa;
- lança o conflito quando uma aprovação concorrente vence.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="dispatches PaymentApproved for an order awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not dispatch PaymentApproved for orders that are not awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the declined payment after raising the decline"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="raises a conflict when a concurrent approval wins the race"`

**C47** - O `PaymentService`, recebendo um `OrderForPayment`, aceita só `awaiting_payment` sem pagamento aprovado e recusa os outros três status e o pedido já aprovado
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="decides whether an order can be paid"`

**C48** - Nas rotas da loja, `POST /api/orders/{order}/payment` e `GET /api/account` respondem `401` a uma sessão de equipe. No pagamento, um `card_token` inválido responde `422` sem cobrar, e a 21ª tentativa no mesmo minuto responde `429`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a staff session on every store route"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects an invalid card token without charging"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles payment attempts after 20 per minute"`

**C49** - O log de cada tentativa de pagamento continua com o `order_id` e sem o `card_token`, e o log do agendamento continua sem o endereço
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs each payment attempt without the card token"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs the scheduled delivery without the address"`

### S6 - CustomerAccounts · 24 arquivos · 31 KB · ~7k

**C50** - Uma sessão de equipe cria um cliente com o e-mail `"NOVO.Cliente@Example.com"` e recebe `201`. O `data` traz `id`, `name`, `email` `"novo.cliente@example.com"`, `created_at` e `orders_count` 0 (AC 40)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a customer from the admin"`

**C51** - Criar um cliente com o e-mail de outro cliente responde `422` em `errors.email` (AC 41)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a customer e-mail already in use"`

**C52** - `GET` e `PUT /api/admin/customers/999999` respondem `404` a uma sessão de equipe (AC 42, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 to an unknown customer in the admin"`

**C53** - Com 16 clientes criados em instantes diferentes, `GET /api/admin/customers` devolve 15 na primeira página, do mais novo para o mais antigo, cada um com o seu `orders_count` (AC 43)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="pages customers fifteen at a time, newest first"`

**C54** - Um cliente envia `PUT /api/account/profile` com nome e e-mail novos e sem senha, e recebe `200` com `message` `"Dados atualizados com sucesso."`. O `data` tem exatamente as chaves `id`, `name`, `email` e `created_at`, e a senha antiga continua entrando (AC 44)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="updates name and email"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="updates the own profile keeping the password when none is sent"`

**C55** - Um cliente que envia `PUT` ou `DELETE` para o endereço de outro cliente recebe `403`, e o endereço não muda (AC 45, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids changing an address of another customer"`

**C56** - Um cliente cria um endereço, e a linha gravada em `customer_addresses` tem `customer_id` igual ao id dele (AC 46, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates an address in the customer address book"`

**C57** - `CustomerAccounts` (door 2):
- `register` devolve um `CustomerProfile` com o e-mail normalizado e sem senha;
- `updateProfile` muda a senha só quando uma nova é enviada;
- `findProfile` devolve `null` para o id 999999;
- `paginateNewestFirst` devolve `CustomerProfile`, do mais novo para o mais antigo.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="registers a customer account"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="changes the customer password when a new one is sent"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="finds no profile for an unknown customer"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists accounts with the order count of each one"`

**C58** - Um cliente que pede o pedido de outro recebe `403`, com a `OrderPolicy` recebendo a conta autenticada pelo `Authenticatable` (door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids a customer from seeing another customer order"`

**C59** - Todas as rotas `/api/admin/*`, incluindo `GET /api/admin/customers/{customer}`, respondem `401` a um visitante e a uma sessão de cliente
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a guest on every admin route"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a customer session on every admin route"`

### S7 - CustomerOrderHistory · 14 arquivos · 39 KB · ~9k

**C60** - Um cliente sem pedidos envia `GET /api/account` e recebe `200` com `orders_count` 0, `last_order` `null` e `recent_orders` `[]` (AC 47)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows an empty account summary for a customer without orders"`

**C61** - Um cliente com 6 pedidos criados em dias diferentes envia `GET /api/account` e recebe `orders_count` 6. Os ids de `recent_orders` são os 5 mais recentes, do mais novo para o mais antigo, e `last_order.id` é o do mais novo (AC 48)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows the account summary"`

**C62** - Cada pedido em `last_order` e `recent_orders` (`GET /api/account`) e em `orders` (`GET /api/admin/customers/{customer}`) tem exatamente as chaves `id`, `status`, `status_label`, `total_cents` e `created_at`. Os valores são iguais aos do pedido, `status_label` vem do `OrderStatus`, e `created_at` está em ISO 8601 (AC 49, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="renders the recent orders as summaries"`

**C63** - Uma sessão de equipe abre um cliente com 12 pedidos e recebe `orders_count` 12 e `orders` com os ids dos 10 mais recentes, do mais novo para o mais antigo (AC 50)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows a customer with the ten most recent orders"`

**C64** - As contagens de pedidos de uma página de clientes vêm de uma única consulta, qualquer que seja o tamanho da página (AC 51)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reads the order counts of a customers page in one query"`

**C65** - O `orders_count` de um cliente não inclui pedidos de outro, na listagem do admin e na contagem por cliente (AC 52)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists only customers with their order count"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts the orders of several customers in a single query"`

**C66** - `CustomerOrderHistory` (door 2):
- `recentForCustomer` devolve um `OrderSummaries` do mais novo para o mais antigo, usando o id como desempate, até o limite;
- `countForCustomer` conta só os pedidos daquele cliente.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the most recent orders of a customer, using the id as tie breaker"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts the orders of a customer"`

**C67** - A conta de um cliente com 6 pedidos e o cliente aberto no admin têm o mesmo resumo pelo use case. O `ShowCustomerUseCase` devolve `ordersCount` e os pedidos recentes como `OrderSummaries`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows an account with its order count and only the most recent orders"`

**C68** - O frontend compila com `last_order`, `recent_orders` e `orders` tipados como o resumo de cinco campos e com `orderService.pay` devolvendo `{ payment, message }` (AC 53)
Proof: `docker compose exec -T frontend npm run type-check`

**C69** - O `usePayment` paga com o cartão selecionado e navega para o pedido depois da aprovação, com o `orderService.pay` simulado devolvendo `{ payment, message }` (AC 53)
Proof: `docker compose exec -T frontend npx vitest run -t "pays with the selected card and locks the button while paying"`
Proof: `docker compose exec -T frontend npx vitest run -t "navigates to the order after an approved payment"`

**C70** - A suíte do frontend inteira passa (AC 53)
Proof: `docker compose exec -T frontend npm run test`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| `POST /api/orders/{order}/payment` statuses (8) | 202 C37 · 401 C48 · 402 C42 · 403 C40 · 404 C39 · 409 C41, C43 · 422 C48 · 429 C48 | - |
| `GET /api/account` statuses (2) | 200 C60, C61 · 401 C48 | - |
| `GET /api/admin/customers/{customer}` statuses (3) | 200 C63 · 401 C59 · 404 C52 | - |
| pastas públicas da regra (6) | `Contracts` C1 · `Events` C1 · `DTOs` C1 · `ValueObjects` C1 · `Enums` C1 · `Http` C1 | - |
| pastas privadas que existem hoje (9) | C1, table-driven sobre as pastas que existem, afirmando as 9 · violação de `Models` vista falhando C7 | - |
| classes da raiz de um módulo (7) | C2, table-driven, afirmando pelo menos 7 · violação vista falhando C7 | - |
| exceções da HEL-6 (8) | `Backoffice` → `Catalog\Repositories` C3 · `Backoffice` → `Identity\Repositories` C3 · `Backoffice` → `Inventory\Repositories` C3 · `Backoffice` → `Ordering\Repositories` C3 · `Catalog\Models` → `Stock` C3 · `Inventory\Models` → `Product` C3 · `Catalog\Http` → `PurchaseAvailabilityService` C3 · `Inventory\Http` → `PurchaseAvailabilityService` C3 | - |
| módulos com `Contracts` (5) | Catalog C4 · Identity C4 · Inventory C4 · Ordering C4 · Payment C4 · violação vista falhando C7 | - |
| interfaces de contrato depois da mudança (11) | C5, table-driven por reflexão, afirmando pelo menos 11 · violação vista falhando C7 | - |
| regras que o teste já tem (14) | estoque só por contrato C6 · Catalog sem models e repositories do Ordering C6 · Identity sem Ordering C6 · sem o `User` da equipe C6 · sem o `OrderRepository` no Payment e no Fulfillment C6 · Payment sem Fulfillment C6 · Fulfillment sem Payment C6 · Ordering sem Customers C6 · Ordering só com os eventos do Fulfillment C6 · Fulfillment sem Customers C6 · gateway só pelo contrato C6 · Shared sem módulos de negócio C6 · value objects do Identity C6 · política de senha C6 | - |
| ligações dos contratos novos (5) | `ProductCatalog` C9 · `StockLevels` C9 · `PayableOrders` C9 · `CustomerOrderHistory` C9 · `CustomerAccounts` C9 | - |
| contratos sem `array` na assinatura (6) | `ProductCatalog` C10 · `StockLevels` C10 · `StockReservation` C10 · `PayableOrders` C10 · `CustomerOrderHistory` C10 · `CustomerAccounts` C10 | - |
| eventos e job sem model (6) | C11, table-driven sobre os 6 | - |
| payload de cada evento e do job (7 valores) | `OrderPlaced.orderId` C12 · `PaymentApproved.orderId` C13, C46 · `OrderPaid.orderId` C14 · `OrderPaid.deliveryBusinessDays` C14 · `DeliveryScheduled.orderId` e `estimatedDeliveryOn` C15 · `OrderDelivered.orderId` C20 · `DeliverOrder.orderId` C15 | - |
| listeners que reagem a ids (5) | `MarkOrderAsAwaitingPayment` C20 · `MarkOrderAsPaid` C14 · `ScheduleOrderDelivery` C15 · `RecordEstimatedDelivery` C20, C17 · `MarkOrderAsDelivered` C20, C18 | - |
| problemas de uma linha do carrinho (4) | produto inexistente C24 · inativo C24 · estoque insuficiente C24 · sem linha de estoque C25 | - |
| recusas do checkout por item (3) | inexistente C28 · inativo C28 · estoque insuficiente C28 | - |
| decisões da regra de disponibilidade (5) | disponível C32 · inativo C32 · 0 unidades C32 · pedido acima do estoque C32 · sem linha de estoque C32 | - |
| saídas do `StockQuantities` (2) | produto com linha C33, C34 · produto sem linha dá 0 C33 | - |
| saídas do `ProductCatalog` (3) | ativo C35 · inativo C35 · id inexistente C35 | - |
| saídas do `PayableOrders` (2) | pedido encontrado C45 · `null` C45, C39 | - |
| status de pedido no pagamento (4) | `awaiting_payment` C37, C47 · `placed` C41, C47 · `payment_approved` C41, C47 · `delivered` C41, C47 | - |
| operações do `CustomerAccounts` (4) | `register` C57, C50 · `updateProfile` C57, C54 · `findProfile` C57, C52 · `paginateNewestFirst` C57, C53 | - |
| operações do `CustomerOrderHistory` (3) | `countForCustomer` C66, C61 · `countPerCustomer` C65, C64 · `recentForCustomer` C66, C61, C63 | - |
| chaves da tentativa de pagamento (4) | `id` C37, C38 · `order_id` C37, C38 · `status` C37, C38 · `amount_cents` C37, C38 | - |
| chaves do pedido resumido (5) | `id` C62 · `status` C62 · `status_label` C62 · `total_cents` C62 · `created_at` C62 | - |
| lugares que mostram o pedido resumido (3) | `last_order` C62, C61 · `recent_orders` C62, C61 · `orders` C62, C63 | - |
| chaves do perfil na resposta (4) | `id` C54 · `name` C54 · `email` C54 · `created_at` C54 | - |
| doors do `Landing` (6) | door 1 C1, C2, C3, C4, C6, C7, C8 · door 2 C5, C9, C10, C33, C35, C36, C45, C46, C57, C66 · door 3 C22, C29, C34 · door 4 C11, C12, C13, C14, C15, C20 · door 5 C37, C38, C62 · door 6 C39, C52, C55, C56, C58 | - |
| consumidores do frontend (3) | tipos C68 · `orderService.pay` C68, C69 · `usePayment` C69 | - |
| startup config: ligações dos contratos (1 montagem compartilhada) | a aplicação e os testes sobem o mesmo `bootstrap/providers.php`: C9 | - |

- As afirmações sobre status, rota ou formato de resposta (C21, C24 a C31, C37 a C44, C48, C50 a C56, C58 a C65) têm prova que atravessa o HTTP.
- C14, C15, C17 a C20 provam as reações a evento entregando o evento à mão ao listener, como o `OrderStatusFlowTest` já faz.
- C22, C32 a C36, C45 a C47, C57, C66 e C67 provam os contratos e as regras na própria camada, além das provas de borda.
- C1 a C11 são provas estruturais por arch test ou reflexão; C7 é a injeção de falha que mostra que elas falham.
- C68 a C70 provam o frontend por tipo e por Vitest. O arranjo das duas telas não muda e é conferido no navegador na verificação.
- C12, C15, C21, C23, C25, C33, C35, C38, C39, C45, C52, C53, C57 (`findProfile`), C60 e C62 são testes novos. C1 a C5, C9 e C11 são testes novos no `ModuleBoundariesTest`. Os demais reaproveitam testes existentes, estendidos sem afrouxar asserções.

## Test policy

O README diz onde ficam e como rodar os testes, mas não diz qual nível prova cada tipo de código. As linhas abaixo valem para esta feature, no mesmo molde das features anteriores.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Regra de fronteira entre módulos | uma expectativa de arquitetura por namespace, gerada das pastas que existem, vista falhando com uma violação de propósito | toda regra nova; a lista de exceções comparada literalmente |
| Implementação de contrato num repositório que decide (`null` para ausente, 0 para sem linha, ordem do mais novo, senha só quando enviada) | uma na própria camada, com banco **e** uma na borda que a usa | um caso por saída do contrato |
| Entrada que decide precedência (pagamento: `404`, depois `403`, depois `409`) | uma na borda | um caso por status |
| Regra pura (`PurchaseAvailabilityService`, `PaymentService`, `CheckoutService`) | uma na própria camada | um caso por linha da tabela de decisão |
| Reação a evento (listeners do Ordering e do Fulfillment) | uma na própria camada, com o evento entregue à mão ao listener | um caso por saída |
| Instrumentação (resources, service providers, use cases do Customers que só repassam ao contrato, controllers) | nenhuma própria | coberta pelas provas de borda e pela checagem de tipos |

Evidence:

- `StockRepository` como `StockLevels`: 1 decisão (linha ausente vale 0) → decide
- `OrderRepository` como `PayableOrders`: 1 decisão (pedido existe ou não) → decide; como `CustomerOrderHistory`: ordem e desempate do mais recente, limite → decide
- `CustomerAccountRepository` como `CustomerAccounts`: 2 decisões (perfil ausente, senha só quando enviada) → decide
- `PaymentController`: 3 recusas em ordem fixa antes de chamar o caso de uso → decide, na borda
- `PurchaseAvailabilityService`: 5 casos → decide
- listeners: carregam o pedido pelo id e chamam o caso de uso, que decide pela transição de status → decide no caso de uso, com o evento entregue à mão
- use cases do Customers depois da mudança: repassam ao contrato e embrulham o resultado, sem condição própria (exceto o limite de endereços, já provado) → instrumentação
- análogo no repositório: `tests/Feature/Repositories/OrderRepositoryTest.php` prova um repositório que implementa um contrato (`hasBeenOrdered`); `tests/Feature/OrderStatusFlowTest.php` entrega eventos à mão; `tests/Unit/Services/PurchaseAvailabilityServiceTest.php` prova a regra por linha; o `ModuleBoundariesTest` já gera expectativas a partir de pastas para o Fulfillment

Cost: 9 provas na própria camada (C22, C32 a C36, C45, C57, C66), sendo 3 novas (C33, C35, C45), mais a injeção de falha de C7. Sem estas linhas, as saídas `null` e 0 dos contratos seriam provadas só pelos caminhos que o HTTP atravessa.

## Swept

- validation: C48, porque o `card_token` inválido continua `422`; C51, pelo e-mail repetido. Os ids de rota que passam a chegar como inteiros já são restritos pelo `Route::pattern` de `order` e `customer`, que existe
- failure modes: C23, porque o produto não fica sem estoque quando a abertura falha; C28, porque não sobra pedido nem baixa quando o checkout é recusado; C42, porque a recusa grava a tentativa sem mudar o pedido
- idempotency: C13, porque o `PaymentApproved` é publicado uma vez; C17, C18 e C19, porque eventos repetidos não mudam a data, o status nem republicam o `OrderPaid`
- authorization: C40, porque pagar o pedido de outro dá `403`; C55, pelo endereço de outro; C58, pelo pedido de outro; C48 e C59, pelo `401` nas rotas da loja e do admin
- concurrency: C29, porque o bloqueio continua um `FOR UPDATE` ordenado por `product_id` antes de ler os produtos; C43, pela corrida de aprovações
- data lifecycle: n/a - nenhuma tabela, coluna ou chave estrangeira muda e não há dado a migrar (plano, Relations)
- dependency failure: C42, porque a recusa do gateway continua tratada; o gateway é o único serviço externo, e é simulado
- state transitions: C14, C16, C19 e C41, porque as transições do pedido continuam acontecendo só no Ordering e só a partir do status esperado
- observability: C49, porque os logs do pagamento e do agendamento continuam com o `order_id` e sem dado sensível

## Handoff

- S1 = 2k, S2 = 7k, S3 = 3k, S4 = 11k, S5 = 10k, S6 = 7k e S7 = 9k: 49k de código e testes (`wc -c` dos arquivos que cada slice toca, dividido por 4), mais 32k do README, da análise de domínio e do `AGENTS.md`. Somam 81k, abaixo do budget de 150k - one builder
- Mechanism: one builder (cabe no orçamento, sem pergunta)
- **Boundary:** C1-C70 fechados no commit de documentação que encerra a feature (base da feature: `2e22ebd`)
- **Settled mid-build:**
  - o pedido do pagamento é resolvido por um binding explícito da rota (`{payableOrder}`, pelo `PayableOrders`), e não no controller, para manter a precedência de hoje (`404` antes do `422` do corpo, depois `403` e `409`). O `MoneyInCentsTest` "keeps 404 for unknown ids on the money routes" exige o `404` sem corpo. O `Flow` e as `Assumptions` do plano foram corrigidos no commit do pagamento;
  - o `{customer}` do admin de clientes também é resolvido por `Route::bind`, pelo `CustomerAccounts`, no `CustomersServiceProvider`;
  - o `MoneyInCentsTest` "exposes order money in cents on every order route" passou a afirmar o `amount_cents` da tentativa na rota de pagamento (door 5), sem perder a checagem de chaves antigas de dinheiro;
  - as asserções de `items_count` em `OrderRepositoryTest` e `UserUseCasesTest` foram trocadas por asserções sobre o `OrderSummary`, que não tem itens (door 5);
  - `Payment::factory()->for($order)` virou `->create(['order_id' => ...])` nos testes, porque a relação `Payment::order()` saiu;
  - o `ProductRepository::findManyKeyedById` ficou, porque o `findMany` do `ProductCatalog` o usa, e os seus testes continuam.
- **Abandoned:** resolver o pedido do pagamento dentro do controller, porque o `422` do corpo passava a vir antes do `404`
