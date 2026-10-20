# Análise de Domínio: Contextos Delimitados do Backend

> Análise estratégica (DDD) do código em [`backend/`](../backend), feita em 06/10/2026.
> Última atualização: 20/10/2026 (idempotência dos eventos assíncronos, HEL-16: o Fulfillment ganhou a tabela `deliveries`, com uma linha por pedido, e só anuncia o `DeliveryScheduled` e o `OrderDelivered` na escrita que mudou essa linha; cada um dos seis pontos assíncronos tem um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery`; antes, no mesmo dia, rotas, migrations, padrões de UUID e cache de módulos por módulo, HEL-12: cada módulo declara as suas rotas em `app-modules/<módulo>/routes/api.php` e guarda as migrations das suas tabelas, o arquivo central de rotas deixou de existir e a carga foi medida de novo, com o cache de módulos da pilha de carga em `APP_MODULES_CACHE`; antes, em 19/10/2026, módulos como pacotes do `internachi/modular`, HEL-12: cada módulo é um pacote em `backend/app-modules` com a fachada pública (`Contracts`, `Events`) ao lado das camadas privadas `Core`, `Persistence` e `Http`, o `Messaging` saiu do `Shared` e virou módulo, as factories foram para os módulos e o `ModuleBoundariesTest` passou a ler a árvore nova; antes, em 18/10/2026, transporte dos eventos por feature flag, HEL-13: a porta `EventTransport` ganhou o RabbitMQ como segunda implementação, o relay escolhe uma das duas pela flag `event-transport` sem deploy, cada consumidor ganhou um nome estável, o `event-consumer` entrega pelo RabbitMQ com as mesmas regras do `DeliverEvent` e a fila do Laravel passou a rodar no Horizon; antes, em 16/10/2026, limite de requisição sem corrida: o `throttle` do Shared conta a requisição antes de decidir, no `AtomicThrottleRequests` e no `AtomicRateLimiter`; antes, no mesmo dia, lado de leitura com consultas, HEL-6: as leituras que cruzavam módulo viraram consultas do módulo que serve a rota, a regra de disponibilidade passou do Ordering para o `ProductAvailability` do Catalog e o `ModuleBoundariesTest` ficou sem nenhuma exceção; antes, ainda em 16/10/2026, revisão do PR #15 da HEL-9: o link do consumidor `RecordEstimatedDelivery` aponta para a pasta `Consumers`; antes, em 15/10/2026, outbox transacional, HEL-9: todo evento é gravado em `outbox_messages` na transação da mudança que ele anuncia, com nome e versão próprios, o relay do container `outbox-relay` o publica pela porta `EventTransport` e cada consumidor inscrito recebe a sua entrega, com o `request_id` de origem; antes, no mesmo dia, ids UUID v7 e nenhuma rota resolvendo registro, HEL-11: toda chave de negócio é um UUID v7 em texto, o pedido ganha o código de 8 símbolos, o controller carrega o id até o use case ou o repositório, o dono entra na busca das rotas do cliente e as três policies de dono saem; antes, em 09/10/2026, vocabulário público dos módulos, HEL-10: de `DTOs`, `ValueObjects` e `Enums` só é público o que as assinaturas de `Contracts` e `Events` expõem, calculado pelo `ModuleBoundariesTest`, e o `Http` de cada módulo é privado, com o Customers e o Fulfillment usando os próprios adaptadores; antes, no mesmo dia, ajustes da revisão do PR #12: repository sem service, um só resource para a conta de cliente e a nota de deploy sobre payloads na fila; antes, em 08/10/2026: facades dos módulos: cada módulo é usado de fora só pelos seus contratos e eventos, que falam em dados, e o `ModuleBoundariesTest` virou uma lista do que é permitido; em 07/10/2026: endereços de entrega e frete: Customers com o caderno de endereços, Fulfillment com a tabela de frete e a data prevista, e o pedido com a cópia do endereço; equipe e clientes em contas separadas, com papéis `admin` e `suporte`; todos os 5 passos do plano de evolução concluídos; listas de domínio tipadas; Payment com modelo próprio e gateway fake atrás de uma porta).
> O objetivo é identificar subdomínios (Core, Supporting, Generic), mapear os contextos delimitados (bounded contexts) e apontar problemas de coesão e acoplamento.

## Sumário

- [Visão geral](#visão-geral)
- [Mapa de contextos](#mapa-de-contextos)
- [Contextos identificados](#contextos-identificados)
- [Matriz de coesão entre contextos](#matriz-de-coesão-entre-contextos)
- [Problemas detectados](#problemas-detectados)
- [Estrutura modular adotada](#estrutura-modular-adotada)
- [Plano de evolução](#plano-de-evolução)
- [Pendências depois do plano](#pendências-depois-do-plano)

---

## Visão geral

O backend é uma API Laravel de e-commerce com catálogo, estoque, carrinho, endereços de entrega, checkout com frete por UF, pagamento simulado e entrega simulada com data prevista. A autenticação é feita com Sanctum em modo SPA (cookie de sessão).

Quando esta análise foi feita, o código estava organizado por **camada técnica** (`app/Models`, `app/Services`, `app/UseCases`, `app/Repositories`, `app/Http`), e não por domínio. Mesmo assim, a linguagem do código mostrava **7 contextos delimitados**, um **contexto de leitura** (dashboard) e um **kernel compartilhado** de infraestrutura.

Desde o passo 5 do [plano de evolução](#plano-de-evolução), cada um deles é um módulo em `backend/app-modules` (pacotes Composer desde a HEL-12, ver a [estrutura modular adotada](#estrutura-modular-adotada)).

| Contexto | Tipo | Coesão interna |
|---|---|---|
| Ordering / Checkout | **Core Domain** | 8/10 ✅ |
| Catalog | Supporting | 9/10 ✅ |
| Inventory | Supporting | 9/10 ✅ |
| Payment | Supporting (simulado) | 8/10 ✅ |
| Fulfillment / Delivery | Supporting (simulado) | 8/10 ✅ |
| Identity & Access | Generic | 8/10 ✅ |
| Customer Account | Supporting | 8/10 ✅ |
| Backoffice Reporting | Read model | não se aplica |
| Messaging | Infraestrutura (eventos) | não se aplica |

### Como a coesão foi calculada

```txt
Score = (
  Coesão linguística (0-3) +   // vocabulário compartilhado
  Coesão de uso (0-3) +        // conceitos usados juntos
  Coesão de dados (0-2) +      // relacionamentos entre entidades
  Coesão de mudança (0-2)      // conceitos que mudam juntos
)

8-10: alta ✅   5-7: média ⚠️   0-4: baixa ❌
```

---

## Mapa de contextos

```txt
                    ┌─────────────────────┐
                    │  Identity & Access  │  (Generic)
                    │ User (equipe), Role │
                    │ CustomerAccount     │
                    └─────────┬───────────┘
                              │ customer_id
 ┌──────────────┐   status    ▼          ┌──────────────┐
 │   Catalog    │──────────►┌──────────────────┐◄───────│  Inventory   │
 │ Product,     │  preço,   │ Ordering (CORE)  │ lock/  │ Stock,       │
 │ Category     │  nome     │ Cart, Checkout,  │ débito │ Adjustment   │
 └──────────────┘           │ Order, OrderItem │        └──────────────┘
                            └────────┬─────────┘
                                     │ eventos
            ┌────────────────────────┴──────────────────────┐
            ▼                                               ▼
     ┌──────────────┐                                ┌──────────────┐
     │   Payment    │                                │ Fulfillment  │
     │  (simulado)  │                                │  (simulado)  │
     └──────────────┘                                └──────────────┘

 Ordering → Payment:   consulta "pode ser pago?"   Ordering → Fulfillment: OrderPaid
 Payment → Ordering:   PaymentApproved             Fulfillment → Ordering: DeliveryScheduled, OrderDelivered

 Payment e Fulfillment não se conhecem: só o Ordering muda o status do pedido.

 Todo evento atravessa pelo outbox transacional do Messaging: gravado na transação da
 mudança, publicado pelo relay e entregue a cada consumidor inscrito pelo módulo que consome.

 Ordering define os contratos DeliveryAddressBook (implementado pelo Customers) e
 ShippingQuoter (implementado pelo Fulfillment): o Ordering não conhece nenhum dos dois módulos.

 A facade de cada módulo é o seu Contracts mais os seus Events (dados, nunca models):
 Ordering → Catalog: ProductCatalog            Ordering → Inventory: StockLevels, StockReservation
 Catalog → Inventory: StockInitializer,        Payment → Ordering: PayableOrders
   StockLevels (estoque da vitrine)
 Customers → Ordering: CustomerOrderHistory    Customers → Identity: CustomerAccounts
 Inventory → Catalog: ProductCatalog (produtos da lista de estoque)

   Backoffice Reporting (read model) lê pelos contratos de métricas: Catalog → CatalogMetrics,
   Inventory → StockMetrics, Identity → CustomerMetrics, Ordering → OrderMetrics.
```

### Padrões de integração atuais e recomendados

| Upstream | Downstream | Padrão recomendado |
|---|---|---|
| Catalog | Ordering | Customer/Supplier: o Catalog fornece preço, nome e status pelo contrato `ProductCatalog` (`CatalogProduct`, que responde a `availability()`); o Ordering guarda um snapshot |
| Inventory | Ordering | Open Host Service: contratos `StockReservation` (bloqueio e débito de estoque) e `StockLevels` (quantidades sem bloqueio, para o carrinho), que devolvem `StockQuantities` |
| Inventory | Catalog | Open Host Service: contrato `StockInitializer` (abertura do estoque de um produto novo) e `StockLevels::stocksFor` (a linha de estoque de cada produto da página, `ProductStocks`, lida pela consulta da vitrine e do cadastro) |
| Catalog | Inventory | Open Host Service: contrato `ProductCatalog` (`CatalogProducts`), lido pela consulta da lista de estoque do admin; o Inventory não conhece o model `Product` |
| Ordering | Payment | Customer/Supplier: o Payment lê o pedido pelo contrato `PayableOrders` (`OrderForPayment`: cliente, total e status) e decide se ele pode ser pago |
| Ordering | Customers | Open Host Service: contrato `CustomerOrderHistory` (contagens e `OrderSummaries`) para as telas da conta e de clientes |
| Identity | Customers | Open Host Service: contrato `CustomerAccounts` (`CustomerProfile`); as regras da conta ficam no Identity |
| Payment | Ordering | Evento de domínio `PaymentApproved` (`payment.payment_approved`, com o `order_id`), gravado no outbox na transação da tentativa aprovada: o Ordering marca o pedido como pago |
| Ordering | Fulfillment | Evento de domínio `OrderPaid` (`ordering.order_paid`, com o `order_id` e os dias úteis prometidos), gravado na transação da transição: o Fulfillment agenda a entrega sem ler o pedido |
| Fulfillment | Ordering | Evento de domínio `OrderDelivered` (`fulfillment.order_delivered`), pelo outbox: o Ordering encerra o pedido |
| Fulfillment | Ordering | Evento de domínio `DeliveryScheduled` (`fulfillment.delivery_scheduled`, com o dia em `Y-m-d`), pelo outbox: o Ordering grava a data prevista, sem mudar o status |
| Messaging | todos os que publicam ou consomem | Published Language: o envelope do outbox (nome `<módulo>.<evento>`, versão e payload de escalares). O produtor grava pelo `EventOutbox`, e o módulo que consome inscreve os seus consumidores no `EventSubscriptions`; ninguém conhece o transporte |
| Customers | Ordering | Inversão de dependência: o contrato `DeliveryAddressBook` é definido pelo Ordering e implementado pelo Customers; o Ordering recebe a cópia de um endereço do cliente |
| Fulfillment | Ordering | Inversão de dependência: o contrato `ShippingQuoter` é definido pelo Ordering e implementado pelo Fulfillment; o Ordering recebe o preço e o prazo para uma UF |
| Identity | todos | Conformist: os outros contextos referenciam apenas o id da conta (um UUID em texto); o cliente autenticado entra neles só como id, e o dono é conferido na busca do registro |
| Catalog, Inventory, Identity, Ordering | Backoffice Reporting | Open Host Service: os contratos de métricas `CatalogMetrics`, `StockMetrics`, `CustomerMetrics` e `OrderMetrics`, cada um implementado por uma consulta do dono e lido pela consulta do dashboard; somente leitura |

---

## Contextos identificados

### 1. Ordering / Checkout: Core Domain

**Linguagem ubíqua:** carrinho, item, quantidade, checkout, pedido, total, snapshot de preço, endereço de entrega, frete, prazo em dias úteis, data prevista, ciclo de vida do pedido.

**Capacidade de negócio:** transformar um carrinho em um pedido válido, cobrando o preço correto e garantindo que o estoque vendido realmente existe, mesmo com compras concorrentes.

**Conceitos principais:**

- `Order` (Entity): pedido do cliente, com total, status, a cópia do endereço de entrega, o frete (`shipping_cents`), o prazo prometido (`delivery_business_days`) e a data prevista (`estimated_delivery_on`, preenchida depois do pagamento). O total é a soma dos itens mais o frete.
- `OrderItem` (Entity): item do pedido com snapshot de nome e preço.
- `OrderStatus` (Enum): ciclo de vida `placed → awaiting_payment → payment_approved → delivered`.
- `CartDTO` / `CartItemDTO` (DTOs): o carrinho carrega apenas ids e quantidades. O `CartDTO` é a lista tipada de `CartItemDTO` e entrega um `ProductQuantities`.
- [`ValueObjects`](../backend/app-modules/ordering/src/Core/ValueObjects/): `ProductQuantities`, `OrderLines`/`OrderLine`, `ValidatedCart`/`ValidatedCartLine`, `CustomerIds`, `OrderCountsByCustomer` e `OrderCountsByPeriod` (a contagem por dia ou mês do `OrderMetrics`), listas tipadas que calculam os próprios totais. As do carrinho e das linhas do pedido são privadas ao Ordering: nenhuma assinatura de contrato ou evento as expõe (problema 12).
- [`ValueObjects`](../backend/app-modules/ordering/src/Core/ValueObjects/) do vocabulário que os contratos trocam: `DeliveryAddress` (a cópia de um endereço) e `ShippingQuote` (preço em centavos e prazo em dias úteis), e o enum `BrazilianState` (as 27 UFs).
- [Contratos](../backend/app-modules/ordering/src/Contracts/) definidos pelo Ordering: `DeliveryAddressBook` (`find(customerId, addressId): ?DeliveryAddress`, implementado pelo Customers) e `ShippingQuoter` (`quote(BrazilianState): ShippingQuote`, implementado pelo Fulfillment).
- Contratos publicados pelo Ordering e implementados pelo `OrderRepository`: `PayableOrders` (`findForPayment(orderId): ?OrderForPayment`, para o Payment) e `CustomerOrderHistory` (`countForCustomer`, `countPerCustomer`, `recentForCustomer(...): OrderSummaries`, para o Customers). O `OrderMetrics` (`totalOrders`, `countPerDaySince` e `countPerMonthSince`, que devolvem `OrderCountsByPeriod`, para o dashboard) é implementado pela consulta `OrderMetricsQuery`, porque só lê.
- `CheckoutService` (Service): valida o atendimento do pedido e monta os itens.
- `CartValidationService` (Service): revalida o carrinho antes do checkout.
- `PurchaseAvailabilityService` (Service): só as mensagens do carrinho e do checkout (`Produto indisponível.` e `Estoque insuficiente. Disponível: N.`), montadas sobre o `ProductAvailability` do Catalog. A regra "pode ser comprado" morava aqui e passou ao Catalog na HEL-6 (problema 15).
- `Customer` (Read model): o comprador visto pelo pedido, uma projeção somente leitura de `customers` (`id`, `name`, `email`). O pedido guarda `orders.customer_id`.
- `PlaceOrderUseCase` (Use Case): checkout atômico. Resolve o endereço e o frete antes da transação (um endereço que não é do cliente responde `422` sem tocar no estoque) e grava o pedido com a cópia, o frete e o total.
- `RecordEstimatedDeliveryUseCase` (Use Case) e o consumidor `RecordEstimatedDelivery`: gravam a data prevista que o Fulfillment anunciou, só se o pedido ainda não tem uma.
- `ValidateCartUseCase` (Use Case): validação do carrinho, só leitura.
- `MarkOrderAsAwaitingPaymentUseCase` (Use Case): primeira transição de status.
- `OrderPlaced` e `OrderPaid` (Domain Events, `ordering.order_placed` e `ordering.order_paid`), `InsufficientStockException`.
- Consumidores em [`Consumers/`](../backend/app-modules/ordering/src/Core/Consumers/) (`MarkOrderAsAwaitingPayment`, `MarkOrderAsPaid`, `RecordEstimatedDelivery`, `MarkOrderAsDelivered`), inscritos pelo `OrderingServiceProvider`.
- `OrderCodeGenerator` (Service): sorteia o código de 8 símbolos que as pessoas leem no lugar do id (`orders.code`, único). O `PlaceOrderUseCase` sorteia de novo numa colisão, até 3 vezes, num savepoint.

**Por que é o Core Domain:** concentra a lógica mais sofisticada e mais crítica do sistema:

- Checkout atômico, com `SELECT … FOR UPDATE` nas linhas de estoque em ordem determinística (por `product_id`) para evitar deadlocks.
- O preço sempre vem do banco, nunca do cliente.
- Os itens guardam um snapshot histórico de nome e preço.
- As transições de status são idempotentes (`OrderRepository::transitionStatus`), o que torna seguros os retries das filas.
- O evento é gravado no outbox na mesma transação da mudança que ele anuncia (o pedido e o `OrderPlaced`; a transição para `payment_approved` e o `OrderPaid`): um commit nunca fica sem o seu evento, e uma falha da fila só atrasa o pedido.
- O pedido nasce com a cópia do endereço e o frete calculado no servidor: editar ou excluir o endereço, ou mudar a tabela de frete, nunca altera um pedido feito.

**Dependências:**

- → Catalog: preço, nome e status do produto, pelo contrato `ProductCatalog`.
- → Inventory: quantidades (`StockLevels`), bloqueio e débito de estoque (`StockReservation`), por id.
- → Identity: só o id do comprador. O dono do pedido é conferido na busca (`OrderRepository::findForCustomer` e `findForPayment`), sem policy.
- → Customers e Fulfillment: só pelos contratos que o próprio Ordering define (`DeliveryAddressBook`, `ShippingQuoter`) e pelos eventos do Fulfillment. O `ModuleBoundariesTest` proíbe o Ordering de usar o Customers e qualquer namespace do Fulfillment além de `Events`, inclusive as classes da raiz do módulo (o `FulfillmentServiceProvider`).
- ← Payment / Fulfillment: mudanças de status do pedido (`PaymentApproved`, `OrderDelivered`) e a data prevista (`DeliveryScheduled`).

**Contexto sugerido:** `OrderingContext`

---

### 2. Catalog: Supporting Subdomain

**Linguagem ubíqua:** produto, categoria, slug, ativo/inativo, vitrine, filtro, ordenação.

**Capacidade de negócio:** cadastrar e expor os produtos à venda, organizados em categorias.

**Conceitos principais:**

- `Product` (Entity), `Category` (Entity), `ProductStatus` (Enum).
- `ProductService`, `CategoryService` (Services): imagem padrão e regras de exclusão.
- `ProductCatalogFilterDTO`, `ProductDTO`, `CreateProductDTO`, `CategoryDTO`.
- `ProductIds` e `CategoryIds` ([`ValueObjects`](../backend/app-modules/catalog/src/Core/ValueObjects/)): listas tipadas de ids canônicos e sem repetição. O `ProductIds` é o tipo dos contratos `ProductCatalog`, `StockLevels` e `StockReservation`.
- [`ProductCatalog`](../backend/app-modules/catalog/src/Contracts/ProductCatalog.php) (contrato publicado, implementado pela consulta [`ProductCatalogQuery`](../backend/app-modules/catalog/src/Persistence/Queries/ProductCatalogQuery.php) e ligado no `CatalogServiceProvider`): `findMany(ProductIds): CatalogProducts`, com o id, o nome, a imagem, o preço e o `ProductStatus` de cada produto (`CatalogProduct`). É por ele que o carrinho, o checkout e a lista de estoque leem os produtos.
- [`ProductAvailability`](../backend/app-modules/catalog/src/Core/ValueObjects/ProductAvailability.php) (Value Object, HEL-6): a regra "pode ser comprado", produto ativo **e** com unidades em estoque. O `CatalogProduct::availability($unitsInStock)` a responde, e a vitrine, o cadastro do admin, a lista de estoque, o carrinho e o checkout leem a regra dali.
- [`CatalogMetrics`](../backend/app-modules/catalog/src/Contracts/CatalogMetrics.php) (contrato publicado, implementado pela `CatalogMetricsQuery`): `productCountsByStatus(): ProductCountsByStatus`, para o dashboard.
- [`ProductQuery`](../backend/app-modules/catalog/src/Persistence/Queries/ProductQuery.php) (Consulta): a vitrine e o cadastro de produto do admin. Lê os produtos e as categorias e pede o estoque da página ao `StockLevels::stocksFor`, numa leitura só; devolve `ProductView` (privado). Os casos de uso de escrita devolvem um `ProductRecord`, e a consulta o completa sem reler o produto.
- Use cases: `CreateProductUseCase`, `UpdateProductUseCase` e `ChangeProductStatusUseCase` (os três devolvem `ProductRecord`), `DeleteProductUseCase`, `CreateCategoryUseCase`, `UpdateCategoryUseCase`, `DeleteCategoryUseCase`.
- `ProductController` (catálogo público), `Admin\ProductController`, `Admin\CategoryController`.

**Coesão:** 9/10 ✅. Subiu de 7/10 em dois passos: a regra de disponibilidade saiu do `Product` (problema 1) e a criação do estoque passou pelo contrato `StockInitializer` (problema 4). O que ainda pesava foi resolvido na HEL-6 (problema 15): a vitrine e o cadastro leem o estoque pelo contrato `StockLevels`, a relação `Product::stock()` deixou de existir e a regra de disponibilidade mora aqui, no `ProductAvailability`.

**Contexto sugerido:** `CatalogContext`

---

### 3. Inventory: Supporting Subdomain

**Linguagem ubíqua:** quantidade, unidade, entrada/saída (`StockOperation`), ajuste, estoque negativo.

**Capacidade de negócio:** controlar a quantidade disponível de cada produto, sem nunca permitir estoque negativo.

**Conceitos principais:**

- `Stock` (Entity): 1:1 com `Product` pela chave estrangeira `product_id`; desde a HEL-6 não há relação do Eloquent entre os dois (`Stock::product` deixou de existir).
- `StockOperation` (Enum): `increase` / `decrease`.
- `AdjustStockDTO`, `StockService`, `AdjustStockUseCase`.
- `StockRepository`: `createForProduct`, `lockForProducts`, `lockById`, `decrement`. As escritas e os bloqueios; é de uso interno do Inventory.
- Consultas ([`Queries/`](../backend/app-modules/inventory/src/Persistence/Queries/), HEL-6): `StockLevelsQuery` (`quantitiesFor` e `stocksFor`), `StockMetricsQuery` e `StockListQuery` (a lista de estoque do admin, que pede os produtos da página ao `ProductCatalog`). A que responde o `StockLevels` não depende de nenhum contrato do Catalog, para a vitrine e a lista de estoque não formarem um ciclo.
- Contratos publicados ([`Modules\Inventory\Contracts`](../backend/app-modules/inventory/src/Contracts)): `StockInitializer` (para o Catalog) e `StockReservation` (para o Ordering), implementados pelo `StockRepository` porque gravam ou travam; `StockLevels` (para o carrinho e para a vitrine) e `StockMetrics` (para o dashboard), implementados por consultas porque só leem. Falam em ids e devolvem [`StockQuantities`](../backend/app-modules/inventory/src/Core/ValueObjects/StockQuantities.php) (quantidade por produto, 0 sem linha de estoque), `ProductStocks` (o id da linha e a quantidade, `null` sem linha) e `StockTotals`, nunca `Product` ou `Stock`.
- `AdjustStockUseCase` devolve um `StockRecord` (o que gravou), e a `StockListQuery` o completa com o produto sem ler `stocks` de novo.
- `Admin\StockController`.

**Coesão:** 9/10 ✅. A regra "estoque nunca negativo" é aplicada em duas camadas: no `StockService` e na constraint `CHECK (quantity >= 0)` do banco. Subiu de 8/10 porque o que o Inventory oferece aos outros contextos agora está explícito em contratos.

**Contexto sugerido:** `InventoryContext`

---

### 4. Payment: Supporting Subdomain (simulado)

**Linguagem ubíqua:** pagar, aguardando pagamento, tentativa de pagamento, cartão de teste, pagamento aprovado, pagamento recusado, motivo da recusa.

**Capacidade de negócio:** cobrar um pedido por um gateway e registrar cada tentativa. O gateway atual é fake: aprova ou recusa pelo cartão de teste, e a recusa permite tentar de novo.

**Conceitos principais:**

- [`Payment`](../backend/app-modules/payment/src/Persistence/Models/Payment.php) (Entity): uma tentativa de cobrança, `approved` ou `declined`, final depois de gravada. O banco garante no máximo uma aprovada por pedido.
- [`PaymentGateway`](../backend/app-modules/payment/src/Contracts/PaymentGateway.php) (porta): recebe `ChargeRequest` (pedido, valor em centavos, token do cartão) e devolve `ChargeResult`. Não conhece o model `Order`.
- [`FakePaymentGateway`](../backend/app-modules/payment/src/Gateways/FakePaymentGateway.php) (adaptador): decide pelo token; ligado no `PaymentServiceProvider`.
- `PaymentStatus` e `DeclineReason` (Enums).
- `PaymentService`: define quais pedidos podem ser pagos (`awaiting_payment` e sem pagamento aprovado), sobre o `OrderForPayment` que o contrato `PayableOrders` do Ordering devolve.
- O dono do pedido entra na busca: o `PayOrderUseCase` pede o pedido ao `PayableOrders` com o id do cliente e o id do pedido, e um pedido desconhecido ou de outro cliente é `404`, antes da conferência do status. O corpo é validado antes, e a rota só carrega o id.
- [`PayOrderUseCase`](../backend/app-modules/payment/src/Core/UseCases/PayOrderUseCase.php): cobra pela porta, grava a tentativa e publica a aprovação ou lança `PaymentDeclinedException` (`402`).
- `PaymentApproved` (Domain Event), publicado pelo Payment e consumido pelo Ordering.
- `PaymentController`, `PayOrderRequest` e `PaymentResource` (a resposta `202` é a tentativa: `id`, `order_id`, `status`, `amount_cents`).

**Coesão:** 9/10 ✅. Subiu de 7/10 para 8/10 quando o Payment passou a ter modelo próprio (`Payment`, tabela `payments`) e a sua regra de "pode ser pago" a olhar os próprios registros. Subiu para 9/10 com as facades dos módulos (problema 11): o que pesava, ler o model `Order` do Ordering para saber o status e o total, virou o contrato `PayableOrders`. Continua só publicando `PaymentApproved`, sem mexer no status do pedido.

**Observação:** a comunicação com o gateway já fica atrás da porta `PaymentGateway`, que é o lugar da Anti-Corruption Layer: um gateway real será um adaptador novo que traduz os códigos dele para `PaymentStatus` e `DeclineReason`. A cobrança é síncrona; um gateway que confirme depois (Pix, webhook) exigirá um estado pendente ([design](../.design/fake-payment-gateway.md)).

**Contexto sugerido:** `PaymentContext`

---

### 5. Fulfillment / Delivery: Supporting Subdomain (simulado)

**Linguagem ubíqua:** entrega, entregue, frete, orçamento de frete, prazo em dias úteis, data prevista, atraso de entrega.

**Capacidade de negócio:** decidir quanto custa e quanto demora a entrega para cada UF, prometer a data prevista quando o pedido é pago e levar o pedido pago até o estado "entregue". A transportadora continua simulada por um job com atraso.

**Conceitos principais:**

- [`ShippingRateTable`](../backend/app-modules/fulfillment/src/Core/Services/ShippingRateTable.php) (Service): a tabela de preço e prazo por UF, lida de `config('shop.shipping_rates')`. Implementa o contrato `ShippingQuoter` do Ordering, ligado no [`FulfillmentServiceProvider`](../backend/app-modules/fulfillment/src/Providers/FulfillmentServiceProvider.php), e responde `GET /api/shipping/quote` (público).
- [`DeliveryCalendar`](../backend/app-modules/fulfillment/src/Core/Services/DeliveryCalendar.php) (Service): a contagem de dias úteis (segunda a sexta, sem feriados) no fuso `America/Sao_Paulo`.
- Consumidor `ScheduleOrderDelivery` (consome `OrderPaid`, inscrito pelo `FulfillmentServiceProvider`) e [`ScheduleDeliveryUseCase`](../backend/app-modules/fulfillment/src/Core/UseCases/ScheduleDeliveryUseCase.php): calcula a data prevista a partir dos dias úteis que o `OrderPaid` carrega, sem ler o pedido, registra no log (só ids e data, nunca o endereço), grava a linha de `deliveries` e o `DeliveryScheduled` no outbox na mesma transação (só a entrega que criou a linha anuncia a data) e, depois do commit, agenda o `DeliverOrder`, todos com o `orderId`. Um `OrderPaid` repetido de um pedido ainda não entregue só agenda o job de novo, e o de um pedido já entregue não faz nada.
- [`Delivery`](../backend/app-modules/fulfillment/src/Persistence/Models/Delivery.php) e o [`DeliveryRepository`](../backend/app-modules/fulfillment/src/Persistence/Repositories/DeliveryRepository.php) (tabela `deliveries`, [migration](../backend/app-modules/fulfillment/database/migrations/2026_10_20_000001_create_deliveries_table.php)): uma linha por pedido pago, com `order_id` único, a primeira data prevista e o `delivered_at`. É a chave natural de idempotência do módulo, e nenhum outro módulo a lê.
- Job `DeliverOrder` e `DeliverOrderUseCase` (transportadora fake), que preenche `delivered_at` e grava `OrderDelivered` no outbox na mesma transação; um retry ou job repetido não faz nada. O `DeliverOrder` é um comando interno, e não um evento: continua um job do Laravel, fora do outbox.
- `DeliveryScheduled` e `OrderDelivered` (Domain Events), publicados pelo Fulfillment e consumidos pelo Ordering.
- `ShippingQuoteController` e `ShippingQuoteRequest` (a UF é normalizada antes de validar pelo [`NormalizesStateInput`](../backend/app-modules/fulfillment/src/Http/Requests/Concerns/NormalizesStateInput.php) do próprio Fulfillment, que chama `BrazilianState::normalize`).
- `config('shop.delivery_delay_seconds')`.

**Coesão:** 8/10 ✅. Subiu de 7/10: o Fulfillment ganhou regras próprias (a tabela de frete e o calendário de dias úteis) e deixou de ser só um job com atraso. Nenhum outro módulo calcula preço, prazo ou data. Ele ganhou estado próprio na HEL-16, só o necessário para ser idempotente: a tabela `deliveries` guarda a primeira data e o instante da entrega, e a data prevista continua sendo gravada no pedido pelo Ordering (só o Ordering escreve em `orders`). Uma remessa (`shipments`) só se paga com rastreio, etapa "Enviado", várias remessas por pedido ou tarifas que mudem entre o orçamento e a compra, e nada disso está no escopo.

**Contexto sugerido:** `FulfillmentContext`

---

### 6. Identity & Access: Generic Subdomain

**Linguagem ubíqua:** membro da equipe (`User`), conta do cliente (`CustomerAccount`), login, sessão, guard, papel (admin/suporte), permissão.

**Capacidade de negócio:** autenticar a equipe e os clientes, cada um com a sua conta e o seu guard, e controlar o acesso às áreas pública, de cliente e administrativa (o admin faz tudo; o suporte cadastra, edita e consulta, mas não remove nem gerencia a equipe).

**Conceitos principais:**

- `User` (Entity da equipe, tabela `users`, guard `staff`) e `UserRole` (Enum: `admin`, `support`).
- `CustomerAccount` (Entity do comprador, tabela `customers`, guard `customer`) e o `CustomerAccountRepository`. O `CustomerAccount::toProfile()` é o único lugar que transforma a conta em `CustomerProfile`.
- [`CustomerAccounts`](../backend/app-modules/identity/src/Contracts/CustomerAccounts.php) (contrato publicado para o Customers, implementado pelo `CustomerAccountRepository` e ligado no `IdentityServiceProvider`): cria, atualiza o perfil, busca e lista as contas de cliente e devolve `CustomerProfile` (id, nome, e-mail e data de criação, sem credenciais). As regras da conta (e-mail normalizado, senha com hash, senha que só muda quando uma nova é enviada) ficam atrás dele; a última está no `UpdateUserProfileDTO::toArray()`, que também serve à equipe.
- [`CustomerProfileResource`](../backend/app-modules/identity/src/Http/Resources/CustomerProfileResource.php): a conta de cliente no login da loja (`AuthController`). O Customers mostra a mesma conta com o seu próprio resource, e o `CustomerProfileResourceTest` mantém os dois no mesmo formato (problema 12).
- `AuthController` (login da loja, Sanctum SPA), `StaffAuthController` (login do admin), `LoginCredentialsDTO`, `RegisterCustomerUseCase`.
- Gestão da equipe: `StaffMemberController` e os casos de uso `List`, `Create`, `Update` e `DeleteStaffMemberUseCase` (o admin não muda o próprio papel nem remove a própria conta).
- Middleware `EnsureUserIsAdmin`: o grupo de rotas do admin que guarda todo `DELETE` e a gestão da equipe.

- `Email` e `Password` ([`ValueObjects`](../backend/app-modules/identity/src/Core/ValueObjects/)): as regras de e-mail (canônico, em minúsculas, até 255 caracteres) e de senha (mínimo de 8) ficam escritas uma vez e chegam aos Form Requests por `EmailRule` e `PasswordRule`. O Identity e o Customers têm cada um o seu par de regras e o seu `NormalizesEmailInput`, que só traduzem para o Laravel o que os value objects decidem. O `users.email` e o `customers.email` têm o `CHECK ..._email_normalized` como última defesa.

**Coesão:** 8/10 ✅. Subiu de 6/10 depois que o `User` deixou de conhecer pedidos (problema 3), e a separação entre equipe e cliente (problema 9) tirou dele o papel `customer`. Cada conta agora trata só do próprio login, senha e perfil (a equipe, também do papel). As regras de e-mail e senha deixaram de ser copiadas nos Form Requests.

**Contexto sugerido:** `IdentityContext`

---

### 7. Customer Account / Customer Management: Supporting Subdomain

**Linguagem ubíqua:** cliente, perfil, minha conta, pedidos recentes, cadastro de clientes pela equipe, endereço, caderno de endereços.

**Capacidade de negócio:** permitir que o cliente gerencie os próprios dados e os seus endereços de entrega (até 10) e que a equipe (admin e suporte) consulte, cadastre e edite clientes. Não há exclusão de cliente.

**Conceitos principais:**

- `AccountController`, `ProfileController`.
- `UpdateOwnProfileUseCase`, `CreateCustomerUseCase`, `UpdateCustomerUseCase`, `ListCustomersUseCase`, `ShowCustomerUseCase`.
- `CustomerSummaryDTO` / `CustomerSummaryResource`: a conta (Identity) ao lado do histórico de pedidos (Ordering).
- `Admin\CustomerController`. O `ShowCustomerUseCase` e o `UpdateCustomerUseCase` buscam o cliente do admin pelo contrato `CustomerAccounts` do Identity, com o id da rota (`404` para conta inexistente).
- `OrderSummaryResource`: os pedidos resumidos (`id`, `status`, `status_label`, `total_cents`, `created_at`) nas telas de conta e de clientes. Os dados da conta saem pelo [`CustomerProfileResource`](../backend/app-modules/customers/src/Http/Resources/CustomerProfileResource.php) do próprio Customers, no mesmo formato do Identity.
- Adaptadores HTTP próprios (o `Http` de um módulo é privado): [`EmailRule` e `PasswordRule`](../backend/app-modules/customers/src/Http/Rules/), que delegam a `Email` e `Password`, e [`NormalizesEmailInput` e `NormalizesStateInput`](../backend/app-modules/customers/src/Http/Requests/Concerns/), que chamam `Email::normalize` e `BrazilianState::normalize`.
- [`CustomerAddress`](../backend/app-modules/customers/src/Persistence/Models/CustomerAddress.php) (Entity, tabela `customer_addresses`): o caderno do cliente, apagado junto com a conta (`cascadeOnDelete`; não há nada a preservar, porque o pedido guarda cópia). O banco garante o CEP com 8 dígitos e a UF entre as 27.
- [`CustomerAddressRepository`](../backend/app-modules/customers/src/Persistence/Repositories/CustomerAddressRepository.php): também implementa o contrato `DeliveryAddressBook` do Ordering, ligado no [`CustomersServiceProvider`](../backend/app-modules/customers/src/Providers/CustomersServiceProvider.php). Um endereço de outro cliente responde igual a um que não existe, também na edição e na exclusão (`updateForCustomer` e `deleteForCustomer` buscam com o `customer_id`, e a exclusão é um só `DELETE`).
- `CustomerAddressController` (`/api/account/addresses`), `CustomerAddressRequest` (um só para criar e editar; normaliza a UF com o `NormalizesStateInput` do Customers), e os casos de uso `CreateCustomerAddressUseCase`, `UpdateCustomerAddressUseCase` e `DeleteCustomerAddressUseCase`.
- `CustomerAddressService` (Service): o limite de 10 endereços (`409`).

**Observação:** este contexto **compõe** dois outros nas telas de clientes: os dados da conta vêm do Identity (contrato `CustomerAccounts`) e as estatísticas de pedidos vêm do Ordering (contrato `CustomerOrderHistory`). Os dois lados são lidos separadamente e só se encontram no `CustomerSummaryDTO`. O caderno de endereços é o primeiro dado que o Customers possui e grava por conta própria.

**Coesão:** 8/10 ✅. Subiu de 7/10: a nota anterior dizia que ficava aceitável gravar tudo no `CustomerAccount` enquanto não houvesse dados próprios de cliente, e agora há (`customer_addresses`, com model, repository e regras próprios). O cadastro e a edição de clientes pela equipe continuam gravando a conta no Identity, agora pelo contrato `CustomerAccounts`, porque nome, e-mail e senha são dados da conta, não do cliente. Desde a HEL-10, o Customers valida e mostra a conta com os próprios adaptadores HTTP e usa do Identity só o contrato e o vocabulário que ele expõe.

**Contexto sugerido:** `CustomersContext`

---

### Contexto de leitura: Backoffice Reporting (Dashboard)

- [`DashboardQuery`](../backend/app-modules/backoffice/src/Persistence/Queries/DashboardQuery.php) (Consulta) lê os contratos de métricas `CatalogMetrics`, `StockMetrics`, `CustomerMetrics` e `OrderMetrics`, e o `DashboardService` monta os números e preenche com zero os dias e meses sem pedido.
- Funciona como **read model** (lado de consulta), mas desde a HEL-6 sem exceção de fronteira: lê cada número do módulo que o tem, pelo contrato dele, e não usa nenhum repositório de outro módulo.

### Shared Kernel (infraestrutura, não é domínio)

`ApiErrorCode`, `ApiErrorResponse`, `ApiExceptionRenderer`, `BusinessRuleException`, `ApiFormRequest`, `BaseRepository`, `RecordId`, os middlewares `AssignRequestId` e [`AtomicThrottleRequests`](../backend/app-modules/shared/src/Http/Middleware/AtomicThrottleRequests.php) (o `throttle`, que conta antes de decidir, sobre o [`AtomicRateLimiter`](../backend/app-modules/shared/src/Cache/AtomicRateLimiter.php)). É um kernel pequeno, sem camadas `Core`, `Persistence` e `Http`, e não depende de nenhum módulo de negócio nem do `Messaging`. Desde a HEL-12 a camada de eventos não mora mais aqui: virou o módulo abaixo.

### Messaging (infraestrutura de eventos, não é domínio)

A camada de eventos (HEL-9, problema 14, e HEL-13, problema 16) é o módulo [`Messaging`](../backend/app-modules/messaging/src/): a fachada tem quatro interfaces em `Contracts/` (`DomainEvent`, `EventOutbox`, `EventConsumer` e `EventSubscriptions`), e o resto é privado. `Core/` guarda o `EventEnvelope`, a porta `EventTransport` com o `QueueEventTransport` e o `RabbitMq/` (transporte, topologia, formato da mensagem e entregas mortas), o `EventTransportFlag` e o `EventTransportSelector`, a `EventSubscriptionRegistry` (a implementação do `EventSubscriptions`), o `EventDelivery`, o `DeliverEvent`, o `OutboxRelay` e o `RestartSignal`; `Persistence/` guarda o model `OutboxMessage` e o `OutboxMessageRepository`; `Console/` guarda os comandos `outbox:relay`, `events:transport`, `events:consume`, `events:failed` e `events:retry`. Ele não conhece nenhum evento de negócio: cada módulo declara os seus e inscreve os seus consumidores, e nenhum módulo de negócio usa dele mais que as quatro interfaces.

---

## Matriz de coesão entre contextos

| Contexto A | Contexto B | Acoplamento atual | Problema | Recomendação |
|---|---|---|---|---|
| Catalog | Inventory | Baixo | ✅ O estoque é criado pelo contrato `StockInitializer` e lido pelo `StockLevels`, por id; a relação `Product::stock()` saiu na HEL-6 e a lista de estoque pede os produtos ao `ProductCatalog` | Manter os contratos |
| Backoffice | Catalog, Inventory, Identity, Ordering | Baixo | ✅ O dashboard lê os contratos `CatalogMetrics`, `StockMetrics`, `CustomerMetrics` e `OrderMetrics`, e nenhum repositório de outro módulo (HEL-6) | Manter: um número novo no dashboard é um método novo no contrato do dono |
| Ordering | Inventory | Alto (necessário) | ✅ O checkout usa só o contrato `StockReservation` e o carrinho o `StockLevels`, por id e com `StockQuantities`, na mesma transação | Manter: a consistência sob concorrência exige a transação única |
| Ordering | Catalog | Baixo | ✅ O snapshot de nome e preço está correto, e o Ordering lê os produtos pelo contrato `ProductCatalog`, sem o model `Product` nem o `ProductRepository` | Customer/Supplier: o Catalog publica preço e status |
| Ordering | Payment | Baixo | ✅ O Payment lê o pedido pelo contrato `PayableOrders` e só grava `PaymentApproved` (com o id) no outbox, na transação da tentativa aprovada; o Ordering muda o status. O Payment tem modelo próprio (`Payment`) e cobra pela porta `PaymentGateway` | Manter a integração por contrato e eventos |
| Payment | Fulfillment | Nenhum | ✅ Não se conhecem: o Fulfillment reage a `OrderPaid`, publicado pelo Ordering | Manter a integração por eventos |
| Identity | Ordering | Baixo | ✅ O Ordering referencia o cliente só por id (`orders.customer_id`) e lê nome e e-mail pelo `Customer`. O `User` da equipe é proibido no Ordering, no Payment e no Fulfillment (`ModuleBoundariesTest`), e o cliente autenticado entra no Ordering só como id, com o dono conferido na busca | Conformist: manter o `Customer` somente leitura |
| Catalog | Ordering | Baixo | ✅ A exclusão pergunta pelo contrato `ProductOrderHistory`, definido pelo Catalog e implementado pelo Ordering | Manter a inversão de dependência |
| Ordering | Customers | Baixo | ✅ O Ordering pede a cópia do endereço pelo contrato `DeliveryAddressBook`, que ele define e o Customers implementa. O `ModuleBoundariesTest` proíbe o Ordering de usar o Customers | Manter a inversão de dependência |
| Ordering | Fulfillment | Baixo | ✅ O Ordering pede o orçamento pelo contrato `ShippingQuoter` (implementado pelo Fulfillment) e só conhece o Fulfillment pelos eventos. O `ModuleBoundariesTest` falha se o Ordering usar qualquer outro namespace dele (uma expectativa por namespace) | Manter a integração por contrato e eventos |
| Fulfillment | Customers | Nenhum | ✅ O Fulfillment não conhece o caderno de endereços: o pedido carrega a cópia de que ele precisa (`ModuleBoundariesTest`) | Manter |
| Customers | Identity | Baixo | ✅ O Customers cria, edita, busca e lista as contas pelo contrato `CustomerAccounts`, que devolve `CustomerProfile`, e usa do Identity só esse contrato e o vocabulário que ele expõe (`CustomerProfile`, `CreateUserDTO`, `UpdateUserProfileDTO`, `Email`, `Password`). As regras de e-mail e senha e o resource da conta são adaptadores do próprio Customers (problema 12) | Manter o contrato |
| Customers | Ordering | Baixo | ✅ A contagem e os pedidos recentes vêm pelo contrato `CustomerOrderHistory`, como `OrderSummaries` | Manter o contrato |
| Messaging | todos os módulos com eventos | Baixo | ✅ A camada de eventos é infraestrutura, e desde a HEL-12 um módulo próprio: o Messaging não depende de nenhum módulo de negócio, o produtor só grava pelo `EventOutbox` e o consumidor só implementa `EventConsumer` e é inscrito pelo próprio módulo. Nenhum dos dois conhece o transporte (`EventTransport`) | ✅ Manter: a HEL-13 acrescentou o RabbitMQ atrás da mesma porta sem mudar um caso de uso, um evento nem o `handle()` de um consumidor: eles só ganharam o nome estável na inscrição (problema 16) |

---

## Problemas detectados

### Prioridade alta

#### 1. ✅ Resolvido: a regra de disponibilidade misturava Catalog, Inventory e Ordering

- **Onde estava:** `Product::isAvailable()`, `Product::availableQuantity()` e `Product::purchaseProblem()`.
- **Problema:** a entidade de catálogo decidia se algo "pode ser comprado". Essa é uma regra de venda que depende do estoque, e as mensagens ("Estoque insuficiente") pertencem ao Ordering.
- **Solução aplicada:** a regra foi para o [`PurchaseAvailabilityService`](../backend/app-modules/ordering/src/Core/Services/PurchaseAvailabilityService.php), um service puro que recebe o produto e o estoque explicitamente. Na HEL-6 ela passou ao [`ProductAvailability`](../backend/app-modules/catalog/src/Core/ValueObjects/ProductAvailability.php) do Catalog (problema 15), e o service ficou só com as mensagens.
  - O `Product` ficou só com `isActive()` (Catalog); na HEL-6 o `isActive()` saiu, porque o `CatalogProduct` passou a carregar o `ProductStatus`.
  - O `Stock` ganhou `hasUnits()` (Inventory).
  - `CheckoutService`, `CartValidationService`, `ProductController` e `ProductResource` recebiam ou resolviam o service; desde a HEL-6 leem o `ProductAvailability`.
- **Nome:** a análise original sugeria `PurchaseAvailabilityPolicy`. A classe virou `Service` porque `app/Policies` é reservado às policies de autorização do Laravel, e `app/Services` já é a camada de regras puras do projeto. Na migração para módulos (passo 5), ela foi para `Modules/Ordering`.
- **Testes:** [`ProductAvailabilityTest`](../backend/tests/Unit/ValueObjects/ProductAvailabilityTest.php) (a regra, HEL-6) e [`PurchaseAvailabilityServiceTest`](../backend/tests/Unit/Services/PurchaseAvailabilityServiceTest.php) (as mensagens).

#### 2. ✅ Resolvido: regra de disponibilidade duplicada no estoque

- **Onde estava:** `StockResource` (`isActive() && quantity > 0`).
- **Problema:** era uma segunda fonte de verdade para a mesma regra. Se a regra mudasse, uma das cópias ia divergir.
- **Solução aplicada:** o [`StockResource`](../backend/app-modules/inventory/src/Http/Resources/StockResource.php) passou a usar o `PurchaseAvailabilityService`; na HEL-6 ele lê o `ProductAvailability` do `CatalogProduct` (problema 15).

#### 3. ✅ Resolvido: "User" tinha dois significados, identidade e cliente

- **Onde estava:** `User::orders()`, `UserRepository::paginateWithOrderCount()` / `loadRecentOrders()`, os campos `orders` e `orders_count` do `UserResource`, `Order::user()` e os métodos do `OrderRepository` que recebiam o `User`.
- **Problema:** o modelo de autenticação conhecia pedidos, e Identity (Generic) ficava acoplado ao Core.
- **Solução aplicada:**
  - [`User`](../backend/app-modules/identity/src/Persistence/Models/User.php) ficou só com identidade (login, senha, papel e perfil). O [`StaffMemberResource`](../backend/app-modules/identity/src/Http/Resources/StaffMemberResource.php) (antes `UserResource`) não fala mais de pedidos.
  - Novo [`Customer`](../backend/app-modules/ordering/src/Persistence/Models/Customer.php): o comprador visto pelo Ordering, uma projeção somente leitura de `users` com `id`, `name` e `email`. Ele lança `LogicException` em qualquer tentativa de gravar ou excluir. `Order::user()` virou `Order::customer()`.
  - O [`OrderRepository`](../backend/app-modules/ordering/src/Persistence/Repositories/OrderRepository.php) trabalha com o id do cliente (`paginateForCustomer`, `recentForCustomer`, `countForCustomer`, `countPerCustomer`, `createWithItems`), com `CustomerIds`, `OrderCountsByCustomer` e `OrderLines` no lugar de arrays, e o `PlaceOrderUseCase` recebe `int $customerId`.
  - A tela "Clientes" do admin compõe os dois lados no [`CustomerSummaryDTO`](../backend/app-modules/customers/src/Core/DTOs/CustomerSummaryDTO.php) e no [`CustomerSummaryResource`](../backend/app-modules/customers/src/Http/Resources/CustomerSummaryResource.php), por meio do `ListCustomersUseCase` e do `ShowCustomerUseCase`. A listagem faz 2 consultas por página, sem N+1.
- **Decisões:**
  - **O contrato da API não mudou:** os endpoints `/api/admin/users`, `/api/account` e `/api/auth/me` devolvem os mesmos campos, e o frontend não precisou de ajustes. *(Superado pelo [problema 9](#9-resolvido-equipe-e-cliente-dividiam-a-mesma-conta): hoje a tela vive em `/api/admin/customers`.)*
  - **Sem escopo global de papel no `Customer`:** para o Ordering, cliente é quem fez o pedido; o papel é um conceito do Identity. A tela "Clientes" continua listando também os administradores (com 0 pedidos), como antes.
  - **A coluna continua `orders.user_id`:** renomear para `customer_id` exigiria uma migration sem ganho de comportamento. A relação `Order::customer()` declara a chave explicitamente. *(Superado pelo problema 9: a coluna passou a `orders.customer_id`, ligada a `customers`.)*
- **Testes:** [`CustomerTest`](../backend/tests/Feature/Models/CustomerTest.php), além dos casos novos em `UserUseCasesTest`, `OrderRepositoryTest` e `Admin/CustomerTest` (antes `Admin/UserTest`).

### Prioridade média

#### 4. ✅ Resolvido: use case de Catalog escrevia no Inventory

- **Onde estava:** `CreateProductUseCase` usava o `StockRepository` inteiro para criar a linha de estoque.
- **Problema:** o Catalog dependia dos detalhes de dados do Inventory e podia chamar qualquer operação dele (ajustar, bloquear, excluir).
- **Solução aplicada:** o [`CreateProductUseCase`](../backend/app-modules/catalog/src/Core/UseCases/CreateProductUseCase.php) depende só do contrato [`StockInitializer`](../backend/app-modules/inventory/src/Contracts/StockInitializer.php), que tem uma única operação: `createForProduct()`.
- **Por que não um evento `ProductCreated`:** o produto e o seu estoque precisam nascer juntos. Um produto sem estoque quebraria o 1:1 e a tela de estoque do admin. Por isso a criação continua na mesma transação, como a própria recomendação previa para o caso de consistência imediata.
- **O que ainda fica (aceito por enquanto):**
  - Leituras do estoque pela relação `Product::stock()`, no catálogo e no `ProductResource`: resolvidas na HEL-6, que a trocou pelo contrato `StockLevels` (problema 15).
  - A exclusão de um produto remove o estoque por `cascadeOnDelete` na chave estrangeira.
  - O dashboard lia o `StockRepository` diretamente: resolvido na HEL-6, que o trocou pelo contrato `StockMetrics`.

  O que sobra é a exclusão em cascata, que é regra do banco (ver as [pendências](#pendências-depois-do-plano)).

#### 5. ✅ Resolvido: pagamento e entrega estavam embutidos no ciclo de vida do pedido

- **Onde estava:** `ApproveOrderPaymentUseCase` mudava o status **e** despachava o job `MarkOrderAsDelivered`, e Payment, Fulfillment e Ordering viviam todos em `UseCases/Order`.
- **Problema:** o Payment acionava o Fulfillment diretamente.
- **Solução aplicada:** cada contexto publica o seu evento, e só o Ordering altera o status:

  ```txt
  Payment      PayOrderUseCase ──► PaymentApproved
  Ordering       MarkOrderAsPaid: awaiting_payment → payment_approved ──► OrderPaid
  Fulfillment      ScheduleOrderDelivery ──► DeliverOrder (job, delay) ──► OrderDelivered
  Ordering           MarkOrderAsDelivered: payment_approved → delivered
  ```

  - Ordering: [`MarkOrderAsPaidUseCase`](../backend/app-modules/ordering/src/Core/UseCases/MarkOrderAsPaidUseCase.php) e [`MarkOrderAsDeliveredUseCase`](../backend/app-modules/ordering/src/Core/UseCases/MarkOrderAsDeliveredUseCase.php), com os eventos [`OrderPaid`](../backend/app-modules/ordering/src/Events/OrderPaid.php) e os listeners `MarkOrderAsPaid` / `MarkOrderAsDelivered` (hoje consumidores em `Consumers/`, problema 14).
  - Payment: [`PayOrderUseCase`](../backend/app-modules/payment/src/Core/UseCases/PayOrderUseCase.php).
  - Fulfillment: [`ScheduleDeliveryUseCase`](../backend/app-modules/fulfillment/src/Core/UseCases/ScheduleDeliveryUseCase.php), [`DeliverOrderUseCase`](../backend/app-modules/fulfillment/src/Core/UseCases/DeliverOrderUseCase.php), o job [`DeliverOrder`](../backend/app-modules/fulfillment/src/Core/Jobs/DeliverOrder.php) e o evento [`OrderDelivered`](../backend/app-modules/fulfillment/src/Events/OrderDelivered.php).
- **Desvio da recomendação original:** a matriz sugeria que o Fulfillment escutasse `PaymentApproved`. Assim, a entrega e a marcação de pago correriam em paralelo; num retry ou atraso da fila, a entrega poderia rodar antes e o pedido ficaria preso em `payment_approved`. Por isso o Ordering publica `OrderPaid` **só depois** da transição, e o Fulfillment escuta esse evento.
- **`OrderStatus` continua com 4 estados:** agora ele é a visão do Ordering sobre o ciclo de vida, e cada transição é disparada pelo evento do contexto responsável.
- **Eventos com dados:** ✅ desde o problema 11 (HEL-7), os eventos e o job `DeliverOrder` carregam o `orderId` e os valores de que o consumidor precisa, nunca o model `Order` (Published Language).
- **Validação:** além do `OrderStatusFlowTest`, o fluxo foi exercitado com o worker real (Redis): `MarkOrderAsPaid` → `ScheduleOrderDelivery` → `DeliverOrder` (10 s depois) → `MarkOrderAsDelivered`.

#### 6. ✅ Resolvido: checkout acessava o estoque pelo repositório

- **Onde estava:** `PlaceOrderUseCase` recebia o `StockRepository` inteiro.
- **Observação mantida:** a transação única entre os contextos é uma **escolha correta** num monólito, porque garante a consistência do estoque sob concorrência.
- **Solução aplicada:** o [`PlaceOrderUseCase`](../backend/app-modules/ordering/src/Core/UseCases/PlaceOrderUseCase.php) depende só do contrato [`StockReservation`](../backend/app-modules/inventory/src/Contracts/StockReservation.php), com `lockForProducts()` e `decrement()`. O contrato documenta que as duas operações rodam dentro da transação de quem chama.
  - **Comportamento inalterado:** o `StockRepository` implementa os dois contratos e é ligado a eles no `InventoryServiceProvider` (`$bindings`). As consultas, a ordem dos bloqueios e o `FOR UPDATE` são exatamente os mesmos, e os testes de concorrência do `CheckoutTest` continuam passando.
- **Fronteira verificada:** o teste de arquitetura [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) falha se qualquer módulo além do Inventory usar o `StockRepository` (o Backoffice incluído, desde a HEL-6). A falha foi conferida introduzindo uma violação de propósito.
  - **Armadilha do Pest:** com uma *lista* de namespaces, o `not->toUse` dessa versão nunca falha. Por isso o teste gera uma expectativa por namespace.

#### 9. ✅ Resolvido: equipe e cliente dividiam a mesma conta

- **Onde estava:** uma única tabela `users`, com `role` `admin` ou `customer`, um único login (`POST /api/auth/login`) e o `auth:sanctum` em todas as rotas autenticadas.
- **Problema:** não havia um perfil de suporte (quem atende e mantém o catálogo sem poder excluir), o mesmo e-mail não podia ser equipe e cliente, e a tela "Clientes" listava também os administradores.
- **Solução aplicada** (feature `staff-and-customer-accounts`, [design](../.design/staff-and-customer-accounts.md)):
  - Duas contas independentes no Identity: [`User`](../backend/app-modules/identity/src/Persistence/Models/User.php) (equipe, `users`, papel `admin` ou `support`, `CHECK users_role_valid`) e [`CustomerAccount`](../backend/app-modules/identity/src/Persistence/Models/CustomerAccount.php) (comprador, `customers`). O e-mail é único dentro de cada tabela, não entre as duas.
  - Um guard de sessão por área em [`config/auth.php`](../backend/config/auth.php) (`customer` e `staff`), nenhuma rota com `auth:sanctum`. Uma sessão de uma área recebe `401` na outra.
  - `orders.customer_id` aponta para `customers` (`restrict`). O [`Customer`](../backend/app-modules/ordering/src/Persistence/Models/Customer.php) do Ordering virou projeção de `customers`, e o `ModuleBoundariesTest` proíbe o `User` no Ordering, no Payment e no Fulfillment.
  - Todo `DELETE` de `/api/admin/*` e a gestão da equipe (`/api/admin/users`) só para o papel `admin`, no grupo com o middleware [`EnsureUserIsAdmin`](../backend/app-modules/identity/src/Http/Middleware/EnsureUserIsAdmin.php). A tela "Clientes" foi para `/api/admin/customers`.
- **Decisões:**
  - **`CustomerAccount` no Identity, e não no Customers:** o Ordering dependeria do Customers, que já depende do Ordering (`OrderRepository`), e isso criaria um ciclo.
  - **Sem `delete` por policy:** a regra "só o admin remove" fica no grupo de rotas, e um teste percorre a tabela de rotas, para que uma rota nova não libere o suporte em silêncio.
  - **O contrato da API mudou** (único consumidor: o frontend do repositório, no mesmo pull request): `role` saiu das respostas da loja, `GET /api/account` devolve `data.customer`, e a tela de clientes passou para `/api/admin/customers`.
  - **Sem migração de dados:** o banco é recriado por `make fresh`.
- **Testes:** `Auth/AuthTest`, `Auth/StaffAuthTest`, `AuthorizationTest`, `Admin/CustomerTest`, `Admin/StaffTest`, `Admin/StaffRoleTest`, `Middleware/EnsureUserIsAdminTest`, `Models/CustomerAccountTest`, `UseCases/User/StaffUseCasesTest` e o `ModuleBoundariesTest`.

#### 10. ✅ Resolvido: o pedido não tinha destino, e o Customers e o Fulfillment não tinham dados nem regras próprios

- **Onde estava:** `POST /api/orders` recebia só os itens, `orders.total_cents` era a soma das linhas, e a entrega era um job que esperava `ORDER_DELIVERY_DELAY_SECONDS` e anunciava "entregue" para qualquer cliente. O Customers só tinha as telas da conta, e o Fulfillment, o job.
- **Problema:** o cliente não tinha onde guardar um endereço, a loja não cobrava frete e ninguém dizia quando o pedido chegava. Os dois contextos ficavam em 7/10 pela mesma lacuna: sem dados próprios (Customers) e sem regra própria (Fulfillment).
- **Solução aplicada** (feature `addresses-and-shipping`, [design](../.design/addresses-and-shipping.md)):
  - **Customers:** o caderno de endereços ([`CustomerAddress`](../backend/app-modules/customers/src/Persistence/Models/CustomerAddress.php), `/api/account/addresses`, até 10 por cliente).
  - **Fulfillment:** a tabela de frete por UF ([`ShippingRateTable`](../backend/app-modules/fulfillment/src/Core/Services/ShippingRateTable.php)), o calendário de dias úteis ([`DeliveryCalendar`](../backend/app-modules/fulfillment/src/Core/Services/DeliveryCalendar.php)) e o evento [`DeliveryScheduled`](../backend/app-modules/fulfillment/src/Events/DeliveryScheduled.php), publicado ao agendar a entrega com a data prevista.
  - **Ordering:** o [`PlaceOrderUseCase`](../backend/app-modules/ordering/src/Core/UseCases/PlaceOrderUseCase.php) recebe o `address_id`, pede a cópia do endereço pelo contrato [`DeliveryAddressBook`](../backend/app-modules/ordering/src/Contracts/DeliveryAddressBook.php) e o frete pelo [`ShippingQuoter`](../backend/app-modules/ordering/src/Contracts/ShippingQuoter.php), e grava o pedido com a cópia, o frete, o prazo e o total (itens + frete). O consumidor [`RecordEstimatedDelivery`](../backend/app-modules/ordering/src/Core/Consumers/RecordEstimatedDelivery.php) grava a data prevista, só a primeira, sem mexer no status.
- **Decisões:**
  - **Cópia em colunas `NOT NULL` de `orders`, sem chave para o caderno:** editar ou excluir um endereço nunca reescreve o histórico, e o banco garante que nenhum pedido existe sem destino. Uma tabela `order_delivery_addresses` não garantiria isso, e uma FK bloquearia a exclusão ou perderia a referência.
  - **Contratos definidos pelo Ordering** (como o `ProductOrderHistory`): publicá-los no Customers ou no Fulfillment faria o Ordering depender de módulos que já dependem dele. O vocabulário que eles trocam (`DeliveryAddress`, `ShippingQuote`, `BrazilianState`) fica no Ordering; o `Shared` foi descartado porque é só infraestrutura.
  - **O servidor calcula o frete e o total:** o cliente envia só os itens e o `address_id`; `shipping_cents`, `total_cents` e a UF enviados são ignorados. O frete mostrado no checkout é só exibição, e o pagamento cobra `orders.total_cents`.
  - **A data prevista chega ao pedido por evento:** só o Ordering escreve em `orders`. O Fulfillment conta os dias úteis a partir do momento em que trata o `OrderPaid`, e a primeira gravação vale, então um retry não muda a data.
  - **Sem tabela `shipments`:** não há ciclo de vida de remessa (rastreio, "Enviado") no escopo.
  - **`BrazilianState` e a lista de UFs nos `CHECK`:** as duas migrations escrevem as 27 siglas por extenso, para não mudarem de sentido se o enum mudar. As colunas novas de `orders` entraram na migration original (`make fresh`, sem backfill).
- **Fronteiras verificadas:** o `ModuleBoundariesTest` ganhou `fulfillment does not know customers`, `ordering does not know customers` e uma expectativa por namespace do Fulfillment para `ordering reaches fulfillment only through its events`, além de afirmar que os contratos do Ordering são interfaces. As regras foram conferidas introduzindo violações de propósito. A primeira versão da regra por namespace nunca falhava: dentro de aspas duplas, `\{$namespace}` não interpola e o alvo virava `...\{Services}`; só a violação de propósito mostrou o erro, e a regra passou a montar o alvo por concatenação.
- **Testes:** `Account/CustomerAddressTest`, `Models/CustomerAddressTest`, `UseCases/Customer/CustomerAddressUseCasesTest`, `Unit/Services/ShippingRateTableTest`, `Feature/ShippingQuoteTest`, `Feature/CheckoutShippingTest`, `Models/OrderDeliveryTest`, `Repositories/CustomerAddressRepositoryTest`, `UseCases/Order/PlaceOrderUseCaseTest`, `Unit/Services/DeliveryCalendarTest`, `Feature/DeliveryEstimateTest`, os casos novos de `OrderStatusFlowTest` e `SeederTest`, e o `ModuleBoundariesTest`.

#### 11. ✅ Resolvido: um módulo usava as partes internas de outro

- **Onde estava:** de 102 referências de um módulo para outro (sem contar o `Shared`), só 12 passavam por `Contracts` ou `Events`. 48 entravam em `Models`, `Repositories` e `Services`: o `Order` no Payment e no Fulfillment, o `Product` e o `Stock` no checkout, o `CustomerAccount`, o seu repositório e o `UserService` no Customers, o `OrderRepository` no Customers. Até os contratos públicos vazavam modelo (`StockInitializer::createForProduct(Product): Stock`).
- **Problema:** o `ModuleBoundariesTest` era uma lista de proibições: tudo o que nenhuma regra nomeava era permitido, então uma pasta nova nascia pública e mudar um model obrigava a revisar módulos que nem deviam conhecê-lo. A HEL-9 (outbox) precisa de eventos cujo payload possa ser gravado, e a HEL-6 (CQRS) precisa saber quais leituras atravessam módulos.
- **Solução aplicada** (HEL-7, [design](../.design/module-facades.md)):
  - **A facade de um módulo é o seu `Contracts` mais os seus `Events`.** Contratos por papel, só para travessias que existem: [`ProductCatalog`](../backend/app-modules/catalog/src/Contracts/ProductCatalog.php), [`StockLevels`](../backend/app-modules/inventory/src/Contracts/StockLevels.php), [`PayableOrders`](../backend/app-modules/ordering/src/Contracts/PayableOrders.php), [`CustomerOrderHistory`](../backend/app-modules/ordering/src/Contracts/CustomerOrderHistory.php) e [`CustomerAccounts`](../backend/app-modules/identity/src/Contracts/CustomerAccounts.php), cada um implementado por um repositório do módulo dono e ligado no service provider dele (o Catalog e o Identity ganharam o seu).
  - **Contratos e eventos falam em dados.** O `StockInitializer` e o `StockReservation` passaram a receber ids e a devolver `StockQuantities`. Os cinco eventos e o job `DeliverOrder` carregam o `orderId` (e o `OrderPaid`, os dias úteis prometidos), e um teste de reflexão (`OrderEventPayloadTest`) cobra isso.
  - **O checkout continua numa transação só**, com o bloqueio das linhas de estoque em ordem de `product_id`, agora pelos contratos.
  - **Fora do Identity, o cliente autenticado é um id**, e o `{order}` do pagamento e o `{customer}` do admin de clientes eram resolvidos pelo contrato do dono, com `404` para id inexistente (a HEL-11 levou essa busca para dentro do use case e tirou o binding: ver o problema 13).
  - **Duas respostas da API ficaram mais estreitas**: o `202` do pagamento é a tentativa de pagamento, e os pedidos recentes da conta e do admin de clientes vêm resumidos (`id`, `status`, `status_label`, `total_cents`, `created_at`).
- **Fronteira verificada:** o [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) virou uma lista do que é permitido. Ele gera uma expectativa por pasta privada de cada módulo, a partir das pastas que existem, mais uma por classe da raiz de cada módulo. Também confere que todo `Contracts` só tem interfaces e que nenhuma assinatura de contrato usa model ou coleção do Eloquent. As regras de direção de antes continuam. Quatro violações de propósito (um model do Ordering no Payment, o service provider do Payment no Ordering, uma classe concreta em `Contracts` e um contrato devolvendo `Product`) fizeram o teste falhar, cada uma na sua regra.
- **Exceções declaradas, uma por travessia:** o Backoffice lendo os repositories de quatro módulos, e a vitrine e a lista de estoque do admin com `Product::stock`, `Stock::product` e a regra de disponibilidade do Ordering. Todas apontam para a HEL-6, que as removeu (problema 15). `DTOs`, `ValueObjects`, `Enums` e `Http` de outro módulo continuaram permitidos nesta rodada e foram fechados na HEL-10 (problema 12).
- **Desvio do plano (desfeito na HEL-11, problema 13):** o pedido do pagamento era resolvido antes da validação do corpo, para que um id inexistente continuasse `404` mesmo com corpo inválido. Agora o corpo é validado primeiro, e a busca roda dentro do `PayOrderUseCase`.
- **Ajustes da revisão do PR #12** (09/10/2026):
  - a regra "a senha só muda quando uma nova é enviada" saiu do `UserService`, que foi apagado, para o [`UpdateUserProfileDTO::toArray()`](../backend/app-modules/identity/src/Core/DTOs/UpdateUserProfileDTO.php), ao lado do `CreateUserDTO::toArray()`. O `CustomerAccountRepository` deixou de depender de um service, e o `ModuleBoundariesTest` ganhou a regra `repositories do not use the module services` (uma expectativa por módulo com as duas pastas; uma violação de propósito no Ordering fez o teste falhar);
  - a conta de cliente tem um único formato na API, o [`CustomerProfileResource`](../backend/app-modules/identity/src/Http/Resources/CustomerProfileResource.php) do Identity, alimentado pelo `CustomerAccount::toProfile()`. O `CustomerAccountResource` e a cópia do resource no Customers saíram (na HEL-10, o Customers voltou a ter o seu, porque o `Http` de um módulo ficou privado, e um teste mantém os dois no mesmo formato: ver o problema 12);
  - o `ProductRepository::findManyKeyedById` foi inlinado no `findMany` do `ProductCatalog`, seu único chamador, e o parâmetro `$withStock`, que ninguém passava, saiu;
  - um deploy que muda o formato de um evento ou job precisa esvaziar a fila antes, porque o `queue-restart` não converte os payloads que já estão nela (ver o [README](../README.md#eventos-outbox-consumidores-e-filas)).

#### 12. ✅ Resolvido: um módulo usava o `Http`, os DTOs, os value objects e os enums internos de outro

- **Onde estava:** depois do problema 11, o `ModuleBoundariesTest` tratava `DTOs`, `ValueObjects`, `Enums` e `Http` de qualquer módulo como públicos por inteiro. Das 45 classes nas três primeiras pastas, 23 não apareciam em nenhuma assinatura de contrato ou de evento (`ValidatedCart`, `OrderLines`, `ProductQuantities`, `LoginCredentialsDTO`, `UserRole`...) e mesmo assim eram alcançáveis de fora. Das 57 referências de um módulo para essas quatro pastas de outro, 15 não deveriam existir: 14 para o `Http` de outro módulo (o Customers usando `EmailRule`, `PasswordRule`, `NormalizesEmailInput` e `CustomerProfileResource` do Identity, e o Customers e o Fulfillment usando o `NormalizesStateInput` do Ordering, que o próprio Ordering não usava) e 1 para um enum que nenhum contrato expõe (o `ProductStatus`, no dashboard do Backoffice).
- **Problema:** o teste não distinguia um value object que um contrato devolve de um DTO interno ou de uma regra de validação, então uma travessia nova para qualquer um deles passava sem aviso. A HEL-6 e a HEL-12 precisavam de uma regra do que é público antes de criar contratos de leitura e reorganizar as pastas.
- **Solução aplicada** (HEL-10, [design](../.design/module-vocabulary.md)):
  - **Público é o que uma assinatura expõe.** O vocabulário de um módulo são as classes dele alcançadas pelos tipos nativos (parâmetros, retornos e propriedades públicas, o construtor incluído) dos métodos públicos das interfaces de `Contracts` e das classes de `Events` dele, seguindo os mesmos tipos de forma transitiva. Tipos só em docblock, embutidos, do framework e do `Shared` não contam, e o contrato de um módulo não torna público um tipo interno de outro. O cálculo roda a cada execução do teste, sem lista guardada: na HEL-10 eram 22 tipos públicos e 23 privados, e depois da HEL-6 são 29 e 28.
  - **O `Http` de cada módulo é privado, sem exceção.** O Customers ganhou os seus [`EmailRule` e `PasswordRule`](../backend/app-modules/customers/src/Http/Rules/), [`NormalizesEmailInput` e `NormalizesStateInput`](../backend/app-modules/customers/src/Http/Requests/Concerns/) e [`CustomerProfileResource`](../backend/app-modules/customers/src/Http/Resources/CustomerProfileResource.php), e o Fulfillment o seu [`NormalizesStateInput`](../backend/app-modules/fulfillment/src/Http/Requests/Concerns/NormalizesStateInput.php). Eles só traduzem para o Laravel o que `Email`, `Password` e `BrazilianState` decidem. O `NormalizesStateInput` do Ordering saiu. As rotas devolvem os mesmos códigos, corpos e mensagens.
- **Fronteira verificada:** o [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) gera uma expectativa por classe de `DTOs`, `ValueObjects` e `Enums` fora do vocabulário do módulo dono e trata o `Http` como as outras pastas privadas. O percurso das assinaturas é provado com classes de teste em `Fixtures/`. O [`HttpAdaptersTest`](../backend/tests/Unit/Architecture/HttpAdaptersTest.php) confere que os adaptadores novos não escrevem limite, expressão regular nem lista de UFs, e o [`CustomerProfileResourceTest`](../backend/tests/Unit/Resources/CustomerProfileResourceTest.php), que a conta sai no mesmo formato no Identity e no Customers. Violações de propósito (um value object, um enum, um DTO fora do vocabulário e uma regra do `Http` do Identity no Customers) fizeram o teste falhar, cada uma na sua regra.
- **Exceção declarada na HEL-10, removida na HEL-6:** o Backoffice usando o `ProductStatus` do Catalog. Fazer um contrato do Catalog crescer só para expor o enum foi descartado então; na HEL-6 o enum entrou no vocabulário porque o `CatalogProduct` passou a carregá-lo (duas telas mostram o status).
- **Desvio do plano:** nenhum. A subpasta de vocabulário em `Contracts` e um atributo nas classes públicas foram descartados no design: a primeira movia cerca de 20 classes antes da HEL-12, e o segundo seria uma segunda fonte para a mesma informação.

#### 13. ✅ Resolvido: o id inteiro saía do banco e a rota resolvia o registro, fora do repositório

- **Onde estava:** 13 resources devolviam o id inteiro, 8 rotas do frontend o colocavam na URL e 9 lugares, em 8 telas, mostravam `#42`. O mesmo tipo estava em 9 tabelas, em 9 métodos de 7 contratos, em 5 eventos e no job `DeliverOrder`. Nas rotas com registro, o route model binding e os dois binds explícitos (`{customer}` e o do pagamento) liam o banco fora de `Repositories/`. Nas quatro rotas do cliente com registro (o pedido, o pagamento e as duas do endereço), o binding carregava o registro de qualquer cliente e a policy respondia `403`. Seis rotas de escrita liam o registro sem precisar: o ajuste de estoque (lia duas vezes), os quatro `DELETE` (produto, categoria, membro da equipe e endereço) e a edição do cliente no admin (lia no bind e de novo no `updateProfile`).
- **Problema:** o número revelava quantos pedidos a loja já recebera, e `/products/7` convidava a tentar o 8. O `403` confirmava a quem não era dono que o registro existia. E a HEL-9 (outbox), a HEL-6 (leituras) e a HEL-12 (rotas e migrations por módulo) iam gravar ou publicar esses payloads: a que chegasse antes herdaria o inteiro e o refaria.
- **Solução aplicada** (HEL-11, [design](../.design/uuid-record-ids.md)):
  - **UUID v7 como chave das 9 tabelas de negócio**, gerado pela aplicação (`HasUuids`), no tipo nativo `uuid`, e como tipo de toda chave estrangeira para elas. O id é `string` em contratos, eventos, listas tipadas, value objects, DTOs, use cases e resources, e o `IdTypesTest` cobra que nenhum `int` chamado `id` ou terminado em `Id` sobre em `Modules`.
  - **A forma canônica numa fonte só**: [`RecordId`](../backend/app-modules/shared/src/Support/RecordId.php) (minúsculas, com hífens). Dela leem os `Route::pattern` do [`AppServiceProvider`](../backend/app/Providers/AppServiceProvider.php) (um id malformado no caminho é `404` sem query), a regra [`CanonicalRecordId`](../backend/app-modules/shared/src/Http/Rules/CanonicalRecordId.php) dos ids do corpo (`422`, com `bail` antes de qualquer `exists`) e as listas `ProductIds`, `CategoryIds` e `CustomerIds`.
  - **Nenhuma rota resolve registro.** O controller recebe `string $product`, `string $order` e assim por diante. Na escrita, o id vai ao use case; na leitura, ao repositório (ou ao use case que já existe, como o `ShowCustomerUseCase`, que busca o cliente pelo `CustomerAccounts`). O [`BaseRepository`](../backend/app-modules/shared/src/Repositories/BaseRepository.php) ganhou `findOrFail` e `deleteById`, e os dois binds explícitos saíram dos service providers. Consequência aceita: o corpo é validado antes da existência (`422` para qualquer id, `404` para id desconhecido com corpo válido), o que desfaz o desvio do pagamento registrado no problema 11.
  - **O dono entra na busca**, e a `OrderPolicy`, a `CustomerAddressPolicy` e a `OrderPaymentPolicy` deixaram de existir. O [`PayableOrders::findForPayment`](../backend/app-modules/ordering/src/Contracts/PayableOrders.php) recebe o id do cliente, como o `DeliveryAddressBook` já fazia, e o [`OrderRepository`](../backend/app-modules/ordering/src/Persistence/Repositories/OrderRepository.php) e o [`CustomerAddressRepository`](../backend/app-modules/customers/src/Persistence/Repositories/CustomerAddressRepository.php) buscam, editam e excluem com o `customer_id`. O pedido, o pagamento e o endereço de outro cliente respondem `404` com o corpo de um id desconhecido, antes de qualquer conferência de status (nunca `409`). O [`PayOrderUseCase`](../backend/app-modules/payment/src/Core/UseCases/PayOrderUseCase.php) passou a buscar o pedido pelo contrato, e o `CustomerAddressRequest` perdeu o `authorize()`, que decidia antes da validação.
  - **Seis rotas fazem uma query a menos**: o ajuste de estoque lê a linha uma vez só, com lock (3 queries, eram 4); os `DELETE` de produto, categoria, membro da equipe e endereço vão direto ao `delete` (a regra de bloqueio roda com o id); e o `PUT /api/admin/customers/{customer}` lê o cliente uma vez. As demais rotas com registro mantêm a contagem de antes.
  - **O código do pedido** (`orders.code`, 8 símbolos de `ABCDEFGHJKMNPQRSTUVWXYZ23456789`): é o que as pessoas leem no lugar do `#<id>` em todas as telas. É sorteado pelo [`OrderCodeGenerator`](../backend/app-modules/ordering/src/Core/Services/OrderCodeGenerator.php), único e imutável, e nenhum contrato, evento ou URL o carrega. O [`PlaceOrderUseCase`](../backend/app-modules/ordering/src/Core/UseCases/PlaceOrderUseCase.php) grava o pedido e os itens num savepoint e sorteia de novo numa colisão, até 3 vezes, sem soltar o lock nem refazer a baixa de estoque; na terceira, o pedido falha inteiro (`500`).
  - **Frontend**: tipos `string`, as 8 rotas só casam o UUID canônico (`sensitive: true`), `#<code>` nas 8 telas e o carrinho em `cart-v3`, que descarta o `cart-v2`.
- **Fronteira verificada:** o [`RouteBindingTest`](../backend/tests/Unit/Architecture/RouteBindingTest.php) percorre `Route::getRoutes()` e falha, para qualquer rota de `/api`, se uma ação tiver parâmetro `UrlRoutable` (binding implícito), se um parâmetro tiver binding explícito ou se um parâmetro não tiver o padrão do id canônico. Uma violação de propósito por regra (`Product $product` de volta no `ProductController::show`, um bind explícito de `product` e a retirada do `Route::pattern` de `address`) fez o teste falhar na regra certa, e voltou a passar sem editar o teste. O `ModuleBoundariesTest` não mudou de regra: sem a pasta `Policies`, ele deixou de gerar as expectativas dela.
- **Desvio do plano:** o savepoint e o laço de até 3 sorteios ficam no `PlaceOrderUseCase`, e não no `OrderRepository`, porque um repositório não usa os services do módulo (o `ModuleBoundariesTest` cobra) e o gerador mora em `Services/`. O repositório só grava, com o código recebido. O parâmetro `{payableOrder}` passou a se chamar `{order}`, e o caminho `/api/orders/{order}/payment` não mudou.
- **Custo aceito:** os 48 bits mais significativos do UUID v7 são o instante de criação em milissegundos, então o id revela quando o registro nasceu. O produto, o endereço, o cliente, a equipe e o pedido já mostravam `created_at`; só a categoria, o estoque e o pagamento passam a revelar o instante. Um UUID também não é credencial: por isso o dono continua sendo conferido, agora na busca.
- **Testes:** `Database/UuidKeysTest`, `UuidIdsTest`, `Models/OrderCodeTest`, `Unit/Services/OrderCodeGeneratorTest`, `Unit/Architecture/IdTypesTest`, `Unit/Architecture/OrderCodeBoundariesTest`, `Unit/Architecture/RouteBindingTest`, `Admin/StaffRecordRoutesTest`, `Account/CustomerRecordRoutesTest`, `Repositories/OrderRepositoryTest`, `Repositories/CustomerAddressRepositoryTest` e os testes de use case de exclusão, edição e pagamento por id.

#### 14. ✅ Resolvido: um evento perdido na fila travava o pedido para sempre

- **Onde estava:** os 5 eventos (`OrderPlaced`, `PaymentApproved`, `OrderPaid`, `DeliveryScheduled` e `OrderDelivered`) usavam `Dispatchable` e o despacho depois do commit, e os 5 listeners `ShouldQueue` os recebiam pela fila do Redis, registrados pela descoberta de eventos do Laravel. O evento só existia depois do commit, numa chamada separada à fila, e ninguém ficava sabendo se ela falhasse.
- **Problema** (pela leitura do código, sem tráfego para medir): três pontos em que uma falha da fila travava o pedido para sempre. Sem o `OrderPlaced`, o pedido ficava em `placed`, com o estoque baixado, e o pagamento respondia `409`. Sem o `PaymentApproved`, que o `PayOrderUseCase` disparava fora de qualquer transação, o cliente era cobrado e o pedido ficava em `awaiting_payment`, com uma nova tentativa respondendo `409`. Sem o `OrderPaid`, a transição para `payment_approved` já estava gravada, e o retry do listener não conseguia repeti-la: o retry tornava a falha permanente. E a HEL-13, que vai pôr um segundo transporte atrás de uma feature flag, teria de mexer nos 5 casos de uso e nos 5 listeners.
- **Solução aplicada** (HEL-9, [design](../.design/transactional-outbox.md)):
  - **O evento é gravado na transação da mudança que ele anuncia**, pelo [`EventOutbox`](../backend/app-modules/messaging/src/Contracts/EventOutbox.php), numa linha de `outbox_messages` ([migration](../backend/app-modules/messaging/database/migrations/2026_10_15_000001_create_outbox_messages_table.php)). O [`OutboxMessageRepository`](../backend/app-modules/messaging/src/Persistence/Repositories/OutboxMessageRepository.php) recusa a gravação sem transação aberta (`LogicException`) e um valor do payload que não seja escalar nem nulo (`InvalidArgumentException`). No pagamento, a tentativa aprovada e o `PaymentApproved` entram juntos; na marcação de pago, a transição e o `OrderPaid`. O agendamento da entrega grava a linha de `deliveries` e a `DeliveryScheduled` juntas, a entrega preenche `delivered_at` e grava a `OrderDelivered` juntas (problema 18), e o `DeliverOrder` entra na fila depois do commit da `DeliveryScheduled`.
  - **Cada evento declara o próprio formato** ([`DomainEvent`](../backend/app-modules/messaging/src/Contracts/DomainEvent.php)): o nome `<módulo>.<evento>` (`ordering.order_placed`, `payment.payment_approved`, `ordering.order_paid`, `fulfillment.delivery_scheduled` e `fulfillment.order_delivered`), a versão 1 e um payload só com escalares em snake_case, lido de volta pelo `fromPayload()`. O nome não é a classe PHP, porque a HEL-12 vai mudar os namespaces.
  - **Um relay num container próprio** ([`OutboxRelay`](../backend/app-modules/messaging/src/Core/OutboxRelay.php) e o comando [`outbox:relay`](../backend/app-modules/messaging/src/Console/RelayOutboxCommand.php), serviço `outbox-relay`): reserva as pendentes com `FOR UPDATE SKIP LOCKED`, das mais antigas para as mais novas, entrega cada uma ao [`EventTransport`](../backend/app-modules/messaging/src/Core/EventTransport.php) e só então a marca como publicada. Na primeira falha, para o ciclo, deixa o resto pendente e registra um `warning`. Espera 500 ms sem pendentes e 5 s depois de uma falha, apaga as publicadas há mais de 7 dias (nunca as pendentes) e para com o sinal do `queue:restart`.
  - **O transporte é uma porta**, e hoje a única implementação, o [`QueueEventTransport`](../backend/app-modules/messaging/src/Core/QueueEventTransport.php), põe na fila um [`DeliverEvent`](../backend/app-modules/messaging/src/Core/DeliverEvent.php) por consumidor inscrito, com o envelope inteiro. Uma falha repete só a entrega daquele consumidor (3 tentativas, depois o `failed_jobs`), e o `request_id` de origem volta ao `Context` antes do consumidor.
  - **Os listeners viraram consumidores** (`Consumers/` no Ordering e no Fulfillment, com a interface [`EventConsumer`](../backend/app-modules/messaging/src/Contracts/EventConsumer.php), sem `ShouldQueue` nem `$tries`), inscritos pelo módulo que consome no `boot()` do service provider ([`EventSubscriptions`](../backend/app-modules/messaging/src/Core/EventSubscriptionRegistry.php)). O `withEvents(discover: ...)` saiu do `bootstrap/app.php`.
- **Fronteira verificada:** o [`DomainEventsTest`](../backend/tests/Unit/Architecture/DomainEventsTest.php) exige `DomainEvent` em toda pasta `Events` e recusa, em `Modules`, o `Dispatchable` de eventos, a interface de despacho depois do commit, a facade `Event` e o helper `event()`, uma expectativa por alvo. Cada uma das 5 violações de propósito fez a regra dela falhar, sem editar o teste. O `ModuleBoundariesTest` passou a esperar `Consumers` entre as pastas privadas, no lugar de `Listeners`: a pasta já nasce privada, porque as regras são geradas das pastas que existem.
- **Desvios do plano:** o texto do plano para o `CheckoutTest` dizia que a décima query seria o `insert into "outbox_messages"`; ela é a nona em posição, porque o `load('items')` da resposta lê os itens depois do commit. A contagem foi de 9 para 10, como previsto, e o teste confere a posição real. Na execução manual com o Redis parado, o relay caía ao ler o sinal de reinício (o cache também é o Redis) e o container entrava em laço de reinício: agora ele segue publicando enquanto o cache não responde, adota o sinal quando o cache volta e imprime uma linha de aviso por falha, que aparece no `make logs-relay`.
- **Ficou de fora** (ver o design): Horizon, RabbitMQ e a feature flag (✅ feitos na HEL-13, problema 16), migrations por módulo (✅ feitas na HEL-12, problema 17), métricas da fila e do outbox (HEL-8), inbox por consumidor, histórico e replay, ordem garantida entre mensagens e a leitura de versões antigas de um payload.
- **Testes:** `Outbox/EventOutboxTest`, `Outbox/EventDeliveryTest`, `Outbox/OutboxRelayTest`, `Outbox/OrderEventsOutboxTest`, `Unit/Architecture/DomainEventsTest`, `Unit/Architecture/OrderEventPayloadTest` e os testes de checkout, pagamento e do fluxo do pedido conferindo as linhas de `outbox_messages`.

#### 15. ✅ Resolvido: a leitura que juntava módulos passava por relações do Eloquent, repositórios e uma regra do módulo errado (HEL-6)

- **Onde estava (antes da HEL-6):** nove exceções no `ModuleBoundariesTest`, que serviam 10 rotas. O dashboard do admin lia os repositories do Catalog, do Identity, do Inventory e do Ordering e usava o `ProductStatus`, que nenhum contrato expunha (5). A vitrine e o cadastro de produto do admin liam o estoque pela relação `Product::stock`, e a lista de estoque lia o produto por `Stock::product` (2). E o `Http` do Catalog e o do Inventory chamavam o `PurchaseAvailabilityService` do Ordering para decidir se o produto podia ser comprado, embora o Ordering dependa dos dois (2).
- **Problema:** uma mudança em qualquer um de quatro repositories podia quebrar o dashboard, o Catalog e o Inventory não podiam mudar de dono da regra sem mexer no Ordering, e uma leitura nova que juntasse módulos não tinha onde nascer, então copiaria a relação do Eloquent. A HEL-12 vai reorganizar as pastas de todos os módulos, e fazer esta rodada depois dela significaria mover as nove exceções e reescrevê-las lá.
- **Solução aplicada** (HEL-6, [design](../.design/cqrs-read-side.md)):
  - **Toda leitura que cruzava módulo virou uma consulta** do módulo que serve a rota, na pasta privada `Queries/` ([`ProductQuery`](../backend/app-modules/catalog/src/Persistence/Queries/ProductQuery.php), [`StockListQuery`](../backend/app-modules/inventory/src/Persistence/Queries/StockListQuery.php) e [`DashboardQuery`](../backend/app-modules/backoffice/src/Persistence/Queries/DashboardQuery.php)). Uma consulta devolve value objects, listas tipadas ou um `LengthAwarePaginator` deles, nunca um model; lê só as tabelas do próprio módulo; e pede ao dono, pelo contrato dele, por ids e uma vez para a página inteira, o que não é seu. O tamanho da página nunca muda o número de queries.
  - **Um contrato de leitura é implementado por uma consulta do dono, e um contrato que grava ou trava continua no repositório.** O `ProductCatalog` e o `StockLevels` saíram dos repositories, o `StockLevels` ganhou `stocksFor` (o id da linha de estoque, em `ProductStocks`), e nasceram os contratos de métricas [`CatalogMetrics`](../backend/app-modules/catalog/src/Contracts/CatalogMetrics.php), [`StockMetrics`](../backend/app-modules/inventory/src/Contracts/StockMetrics.php), [`CustomerMetrics`](../backend/app-modules/identity/src/Contracts/CustomerMetrics.php) e [`OrderMetrics`](../backend/app-modules/ordering/src/Contracts/OrderMetrics.php). O `StockInitializer` e o `StockReservation` ficaram no `StockRepository`.
  - **Nenhuma relação do Eloquent atravessa módulo.** `Product::stock` e `Stock::product` deixaram de existir na HEL-6; a chave estrangeira `stocks.product_id` e a exclusão em cascata continuam. O caso de uso do dashboard saiu, e o dashboard é uma consulta do Backoffice.
  - **A escrita devolve os dados do que gravou**, num value object (`ProductRecord` e `StockRecord`, privados), e a consulta completa a resposta sem reler o registro. Devolver só o id e reler depois do commit somaria uma query a cada escrita e quebraria as contagens da HEL-11, que continuam: abrir o produto 3, editar 10, mudar o status 4 e ajustar o estoque 3, com um só `FOR UPDATE`.
  - **A regra "pode ser comprado" mora no Catalog**, no `ProductAvailability`, que o `CatalogProduct` responde. O Ordering ficou com as mensagens (`Produto indisponível.` e `Estoque insuficiente. Disponível: N.`), e o Catalog e o Inventory deixaram de depender de quem depende deles. A parte "sem linha de estoque é zero" fica onde a quantidade é lida: no `StockQuantities` do Inventory (carrinho e checkout) e no `ProductView` do Catalog (vitrine e cadastro).
  - **O `ProductStatus` entrou no vocabulário público do Catalog**, porque o `CatalogProduct` e o `ProductCountsByStatus` o carregam: duas telas mostram o status.
- **Fronteira verificada:** o [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) perdeu a lista de exceções e o Backoffice perdeu a licença de usar o `StockRepository`. Entraram três regras das consultas: nenhuma assinatura pública declara model nem coleção do Eloquent (a falha nomeia a consulta e o método), nenhuma usa os repositories do próprio módulo e nenhuma usa os casos de uso do próprio módulo. Uma violação de propósito em cada uma das nove travessias antigas (e uma assinatura com `Product` numa consulta) fez o teste falhar na regra certa, e todas voltaram a passar sem editar o teste.
- **Desvio do plano:** as consultas do Inventory são três (`StockLevelsQuery`, `StockMetricsQuery` e `StockListQuery`) e as do Catalog também (`ProductQuery`, `ProductCatalogQuery` e `CatalogMetricsQuery`): a que responde o contrato que o outro módulo consome não depende de contrato nenhum, para a vitrine (Catalog → Inventory) e a lista de estoque (Inventory → Catalog) não formarem um ciclo no container. O dashboard devolve o `array` que o `DashboardService` monta, em vez de um value object, porque converter mudaria as asserções do `DashboardServiceTest` sem ganho. A única diferença de corpo é o produto sem linha de estoque, estado que as rotas não produzem: agora `is_available: false` e `available_quantity: 0` (antes `null`).
- **Testes (HEL-6):** `Unit/ValueObjects/ProductAvailabilityTest`, `Unit/Services/PurchaseAvailabilityServiceTest`, `Feature/Queries/*`, `Feature/ProductResponseShapeTest`, `Feature/ProductCatalogTest`, `Feature/Admin/StockTest`, `Feature/Admin/DashboardTest`, `Feature/UseCases/Stock/AdjustStockUseCaseTest`, `Feature/UseCases/Product/ProductUseCasesTest`, `Feature/Admin/StaffRecordRoutesTest` (as contagens de queries) e o `ModuleBoundariesTest`.

#### 16. ✅ Resolvido: o transporte dos eventos era um só, e a porta nunca tinha provado que aguenta outro (HEL-13)

- **Onde estava:** todo evento saía do outbox por um transporte só, a fila do Laravel no Redis (`QueueEventTransport`), e todo consumidor existia apenas como um job `DeliverEvent` rodado pelo `queue:work`.
- **Problema:** a porta `EventTransport` da HEL-9 nunca recebeu uma segunda implementação. Sem ela, ninguém sabia se a troca do transporte aguentaria sem mexer em casos de uso, eventos ou consumidores, e não havia como comparar o Redis com o RabbitMQ no mesmo código nem ver um consumidor rodar fora dos jobs do Laravel. A fila não era gargalo (na linha de base do k6, o p95 de `order_payable_wait` era de 2.035 ms, a própria resolução da consulta de 2 s), então a comparação pedia também uma medição mais fina.
- **Solução aplicada** (HEL-13, [design](../.design/event-transport-flag.md) e [plano](../.specs/features/event-transport-flag/plan.md)):
  - **Uma feature flag global, `event-transport`** ([`EventTransportFlag`](../backend/app-modules/messaging/src/Core/EventTransportFlag.php)), no Pennant com o driver `database`: `redis` (o padrão) ou `rabbitmq`. O [`EventTransportSelector`](../backend/app-modules/messaging/src/Core/EventTransportSelector.php) é o que o container entrega à porta, lê a flag a cada publicação e passa o envelope ao transporte dela. O `make event-transport` (o comando [`events:transport`](../backend/app-modules/messaging/src/Console/EventTransportCommand.php)) mostra ou troca o valor sem deploy, e o relay a lê a cada ciclo.
  - **O [`RabbitMqEventTransport`](../backend/app-modules/messaging/src/Core/RabbitMq/RabbitMqEventTransport.php)** publica uma mensagem por evento na exchange `domain_events`, com confirmação do broker; o broker copia para a fila de cada consumidor. A [topologia](../backend/app-modules/messaging/src/Core/RabbitMq/RabbitMqTopology.php) usa filas `quorum` com `x-delivery-limit` 2 e uma fila de mortas por consumidor, e o produtor continua sem listar os consumidores.
  - **Cada consumidor tem um nome estável** dado na inscrição (`EventSubscriptions::subscribe($event, $consumer, $name)`), que nomeia a fila dele, então a HEL-12 pode mudar os namespaces sem deixar fila órfã.
  - **O [`event-consumer`](../backend/app-modules/messaging/src/Console/ConsumeEventsCommand.php)** lê as filas e chama as mesmas regras de entrega do job, agora na classe [`EventDelivery`](../backend/app-modules/messaging/src/Core/EventDelivery.php) que o `DeliverEvent` e o consumidor usam. Uma entrega que falha 3 vezes vai para `<consumidor>.dead`, e o `make events-failed` e o `make events-retry` a listam e a devolvem.
  - **O Horizon** substitui o `queue:work` no container `queue-worker` (mesmos parâmetros), com o painel em `/horizon` fechado pelo alias `admin`, e o container `scheduler` roda o `horizon:snapshot`.
  - **A medição**: a espera até pagar do k6 passou a ler o pedido a cada 250 ms, e o `make load-test` aceita `TRANSPORT=rabbitmq`, com uma segunda tabela de linha de base por transporte.
- **Fronteira verificada:** o `ModuleBoundariesTest` passou sem nenhuma regra nova nem exceção: o módulo de eventos (na época o Shared, hoje o Messaging) continua sem depender de módulo de negócio, e as classes novas do RabbitMQ ficam dentro dele. Os cinco consumidores só ganharam o terceiro argumento da inscrição (nos service providers do Ordering e do Fulfillment).
- **Desvios do plano:** o plano esperava que a `occurred_at` do corpo da mensagem carregasse milissegundos (`.715000` num teste com o relógio congelado); a coluna do outbox guarda segundos (decisão da HEL-9), então pelo relay o corpo sempre sai com `.000000`, e o formato com microssegundos é provado no transporte, com um envelope montado no teste.
- **Ficou de fora:** uma flag por evento, por cliente ou por porcentagem, uma tela para virar a flag, levar o `DeliverOrder` para o RabbitMQ (ele precisa de delay), um consumidor fora do PHP, inbox, espera crescente entre tentativas e um processo por consumidor, remover sozinho a fila de um consumidor que saiu do código, e o trace e as métricas do `event-consumer` (HEL-8).
- **Testes:** `Messaging/BrokerIsolationTest`, `Messaging/EventTransportFlagTest`, `Messaging/ConsumerNamesTest`, `Messaging/RabbitMqEventTransportTest`, `Messaging/ConsumeEventsCommandTest`, `Messaging/DeadDeliveriesCommandsTest` e `HorizonTest`, mais as provas do ambiente (`docker/tests/environment.sh`) e as da pilha de carga (`load-tests/tests`).

#### 17. ✅ Resolvido: o módulo era uma pasta de um pacote só, e o `Shared` guardava a mensageria (HEL-12)

- **Onde estava:** os 9 módulos de `app/Modules`, com 15 pastas por tipo técnico dentro de cada um; o `Shared` com 38 arquivos, 26 deles de mensageria; criar um módulo exigia editar à mão pelo menos cinco lugares globais (a lista de providers, o arquivo central de rotas, a pasta de migrations, a de factories e os padrões de rota do `AppServiceProvider`).
- **Problema:** a fronteira entre contextos estava verificada por teste, mas o módulo não era uma unidade, e as pastas não diziam o que é regra de negócio, o que é banco e o que é HTTP. Cada feature nova continuava tocando a raiz, e o `Shared` crescia como depósito.
- **Solução aplicada:** cada módulo virou um pacote Composer em `backend/app-modules/<módulo>` (namespace `Modules\<Módulo>`), descoberto pelo [`internachi/modular`](https://github.com/InterNACHI/modular); o provider vem do `composer.json` do pacote, e a lista de providers da raiz ficou só com o `AppServiceProvider` e o `HorizonServiceProvider`. Dentro de `src/`, a fachada (`Contracts`, `Events`) fica ao lado de `Core`, `Persistence`, `Http` e `Providers`. O `Messaging` saiu do `Shared` e virou módulo, com o registro de inscrições como interface (`EventSubscriptions`) e a implementação privada (`EventSubscriptionRegistry`). As rotas e as migrations também ficam no pacote (ver abaixo). As factories foram para o `database/factories` de cada módulo. O [design](../.design/modular-layout.md) tem as decisões e a [verificação do spike](../.specs/features/library-spike/verification.md), as respostas sobre a biblioteca.
- **Análise de domínio e acoplamento que decidiu o conjunto de módulos:** nenhum par de módulos passou no teste dos seis critérios de fusão (linguagem, ritmo de mudança, escala, consistência, dono e dor), então os 9 ficam; o único acoplamento intrusivo é a projeção `Customer` do Ordering sobre `customers` (mantida), e os ciclos de import (Catalog e Inventory; Ordering com Fulfillment e com Payment) atravessam só `Contracts` e `Events`.
- **Critério de interface:** nenhuma das 19 interfaces saiu. Cada uma é fronteira entre módulos ou tem segunda implementação ou duplo de teste real (`PaymentGateway`, `EventOutbox`, `EventTransport`, `ProductCatalog`); o `AGENTS.md` traz a regra.
- **Achados do spike:** os padrões de UUID das rotas ficam no `register()` do service provider do módulo dono do parâmetro (antes, no `AppServiceProvider`), porque uma rota copia os padrões globais quando é criada e as rotas de um módulo são criadas antes do `boot()`; o `make:module` põe o provider em `src/Providers/`; o `modules:cache` grava `bootstrap/cache/app-modules.php` sem como desviar o caminho (a pilha de carga não o roda); o container `api` não instala o pacote quando o `vendor/autoload.php` já existe, então cada checkout roda `composer install`.
- **Nota de deploy:** esvazie a fila do Redis antes da troca de namespace: os jobs `DeliverEvent` já enfileirados guardam a classe do consumidor com o namespace antigo. O RabbitMQ não é afetado, porque as filas usam o nome estável do consumidor.
- **Rotas e migrations por módulo (slices Module Routes e Module Migrations, 19/10/2026):** os 8 módulos com rotas (`backoffice`, `catalog`, `customers`, `fulfillment`, `identity`, `inventory`, `ordering` e `payment`) declaram as suas em [`app-modules/<módulo>/routes/api.php`](../backend/app-modules/catalog/routes/api.php), com `Route::prefix('api')->middleware('api')`, e o arquivo central de rotas e o `api:` do `withRouting` saíram; o `route:list` tem as mesmas 73 rotas, com a mesma ação e o mesmo middleware. Das 14 migrations, 11 foram para o módulo dono da tabela (`git mv`, mesmo nome e mesmo conteúdo) e ficam na raiz só `cache`, `jobs` e `features`; o `migrate:status` lista os mesmos 14 nomes e o `migrate` responde `Nothing to migrate.` num banco já migrado. Verificadas pelo [`ModuleRoutesTest`](../backend/tests/Unit/Architecture/ModuleRoutesTest.php) e pelo [`ModuleMigrationsTest`](../backend/tests/Unit/Architecture/ModuleMigrationsTest.php).
- **Cache de módulos (20/10/2026):** sem o `modules:cache`, a biblioteca escaneia `app-modules` a cada boot, e a vitrine passou de 25 ms para 273 ms de p95 na pilha de carga. A biblioteca grava o cache em `bootstrap/cache/app-modules.php` sem configuração para o caminho, então o `AppServiceProvider` religa o `Cache` dela a `APP_MODULES_CACHE` (sem a variável, o caminho é o mesmo de antes). A pilha de carga define a variável em `/tmp/laravel`, e o dev continua sem cache de módulos. Verificado pelo [`ModuleCachePathTest`](../backend/tests/Unit/Architecture/ModuleCachePathTest.php).
- **Ficou de fora:** rotas de console por módulo, seeders por módulo e a divisão do arquivo da `users` (as tabelas `password_reset_tokens` e `sessions` moram no Identity só por causa do arquivo).
- **Fronteiras verificadas:** o [`ModulePackagesTest`](../backend/tests/Unit/Architecture/ModulePackagesTest.php) (os 10 pacotes, nenhuma referência ao namespace antigo, só as pastas permitidas), o [`ModuleLayersTest`](../backend/tests/Unit/Architecture/ModuleLayersTest.php) (`Core` e `Persistence` sem `Http`, infraestrutura sem negócio, cada regra com uma violação plantada) e o [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) lendo a árvore nova.

---

#### 18. ✅ Resolvido: a entrega repetida do `OrderPaid` e o retry do `DeliverOrder` multiplicavam mensagens e jobs (HEL-16)

- **Onde estava:** a entrega dos eventos é ao menos uma vez (o relay pode publicar de novo, o `events:consume` devolve a mensagem com requeue, um `queue:retry` entrega dias depois). Dos seis pontos assíncronos, os quatro consumidores do Ordering toleravam, por uma escrita condicional em `orders`. O `ScheduleOrderDelivery` e o job `DeliverOrder` do Fulfillment não: cada reentrega de `OrderPaid` gravava outro `DeliveryScheduled` (com a data recalculada) e enfileirava outro `DeliverOrder`, e cada retry do job gravava outro `OrderDelivered`.
- **Problema:** o Ordering absorvia as duplicatas, então `orders` ficava certo, mas as linhas de `outbox_messages` e os jobs se multiplicavam em cadeia. O README prometia "os consumidores são idempotentes", o que era falso para o Fulfillment, e nenhum teste entregava o mesmo envelope duas vezes pelo `EventDelivery`: os existentes chamavam o `handle()` direto (2 de 6 pontos sem guarda, 0 testes de reentrega pelo caminho real).
- **Solução aplicada** (design em [.design/event-idempotency.md](../.design/event-idempotency.md)):
  - **O Fulfillment ganhou a linha por pedido** em [`deliveries`](../backend/app-modules/fulfillment/database/migrations/2026_10_20_000001_create_deliveries_table.php), com `order_id` único e restrito na exclusão (como `payments`), a primeira `estimated_delivery_on` e `delivered_at` nulo até a entrega. A guarda é a linha, e não o status do pedido: perguntar ao Ordering seria uma leitura entre módulos com corrida, e consultar o outbox vazaria o interior do Messaging e perderia a mensagem em 7 dias.
  - **Só a escrita que mudou uma linha anuncia o evento, na mesma transação.** O [`ScheduleDeliveryUseCase`](../backend/app-modules/fulfillment/src/Core/UseCases/ScheduleDeliveryUseCase.php) grava a linha com `INSERT ... ON CONFLICT DO NOTHING` (a perdedora de duas entregas simultâneas se comporta como "repetido", sem exceção) e o `DeliveryScheduled`; o [`DeliverOrderUseCase`](../backend/app-modules/fulfillment/src/Core/UseCases/DeliverOrderUseCase.php) preenche `delivered_at` com um `UPDATE ... WHERE delivered_at IS NULL` e grava o `OrderDelivered`. O `DeliverOrder` é enfileirado depois do commit e pode se repetir: uma falha ao enfileirar é reparada pela reentrega do `OrderPaid`, e um job duplicado é um no-op.
  - **A regra virou escrita e tem prova:** todo consumidor declara a sua chave natural, e cada um dos seis pontos tem um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery` ([`ConsumerRedeliveryTest`](../backend/tests/Feature/Messaging/ConsumerRedeliveryTest.php) e [`DeliverOrderTest`](../backend/tests/Feature/Fulfillment/DeliverOrderTest.php)), mais o ciclo completo com um `OrderPaid` reentregue no meio e o mesmo envelope consumido duas vezes pelo `events:consume`.
- **Desvio do design:** a data de hoje do `DeliverOrder` sem linha é a do relógio da aplicação (`now()`, UTC), como o plano assumiu, e não a de São Paulo que o `DeliveryCalendar` usa; só afeta o pedido que tinha um job na fila antes da tabela existir.
- **Ficou de fora:** um inbox genérico por (consumidor, id da mensagem), que só pagaria com um consumidor sem chave natural e não cobre o retry de um job; a idempotência de rotas HTTP (o clique duplo em `POST /api/orders`); a ordem de entrega entre mensagens; e os estados de uma transportadora real (o `delivered_at` volta a ser um enum de estado quando ela existir).

### Prioridade baixa

#### 7. ✅ Resolvido: Catalog conhecia pedidos para bloquear a exclusão

- **Onde estava:** `ProductRepository::hasOrderItems()` e a relação `Product::orderItems()`, que faziam o model do Catalog importar o `OrderItem` do Ordering.
- **Solução aplicada:** o [`DeleteProductUseCase`](../backend/app-modules/catalog/src/Core/UseCases/DeleteProductUseCase.php) pergunta pelo contrato [`ProductOrderHistory::hasBeenOrdered()`](../backend/app-modules/catalog/src/Contracts/ProductOrderHistory.php).
  - O contrato é **definido pelo Catalog** e **implementado pelo Ordering** ([`OrderRepository`](../backend/app-modules/ordering/src/Persistence/Repositories/OrderRepository.php)), com a ligação feita no [`OrderingServiceProvider`](../backend/app-modules/ordering/src/Providers/OrderingServiceProvider.php).
  - A relação `Product::orderItems()` foi removida.
  - A chave estrangeira `order_items.product_id` com `restrictOnDelete` continua como última linha de defesa no banco.
- **Desvio da recomendação original:** a análise sugeria uma interface "implementada pelo Ordering" sem dizer onde ela fica. Se ficasse no Ordering, o Catalog passaria a depender dele, e o Ordering já depende do Catalog (usa `Product` no checkout). Os dois módulos ficariam dependentes um do outro. Com o contrato no Catalog (inversão de dependência), a dependência continua só do Ordering para o Catalog.
- **Fronteira verificada:** o `ModuleBoundariesTest` falha se o Catalog usar models ou repositories do Ordering. Isso foi conferido recolocando a relação `orderItems()` de propósito.

#### 8. ✅ Resolvido: a estrutura do código não refletia os contextos

- **Onde estava:** todo o backend, organizado em camadas técnicas (`app/Models`, `app/Services`, `app/UseCases`...).
- **Problema:** contrariava a diretriz de organização modular por domínio (`app/Modules/<Domain>`) do `AGENTS.md`. Além disso, as fronteiras dos passos 2 a 4 só existiam por convenção.
- **Solução aplicada:** as 118 classes foram para 9 módulos numa pasta `app/Modules` (hoje `backend/app-modules`, ver o problema 17), **um módulo por vez** (Inventory primeiro, Shared por último), com a suíte rodando entre os módulos. Para Ordering, Payment, Fulfillment e Identity, a checagem só foi confirmada ao final desse lote. Fora dos módulos ficou só o `AppServiceProvider`, com as configurações globais. Ver a [estrutura modular adotada](#estrutura-modular-adotada).
- **Como foi feito:**
  - **Preparação, sem mudar comportamento:** models e factories passaram a se declarar por `#[UseFactory]` / `protected $model`, e as policies por `#[UsePolicy]`. A descoberta de listeners passou a olhar a pasta de listeners de cada módulo (ela saiu na HEL-9, problema 14, quando os listeners viraram consumidores inscritos pelo módulo), e as rotas passaram a importar cada controller pelo nome completo. Tudo isso dependia antes da convenção `App\Models` / `App\Listeners`.
  - **Movimentação por script:** o script move os arquivos e reescreve todas as referências. Ele cria os `use` que passam a ser necessários quando duas classes que dividiam um namespace vão para módulos diferentes, e a análise usa o tokenizer do PHP para não confundir nomes de classe com texto de comentário.
- **Fronteiras verificadas:** o [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) transforma as fronteiras dos passos anteriores em regras:
  - estoque acessado só pelos contratos;
  - Identity não depende de Ordering;
  - Payment e Fulfillment não usam o `OrderRepository` e não se conhecem;
  - Shared não depende de nenhum módulo de negócio.

  Três dessas regras foram conferidas introduzindo uma violação de propósito. O teste também falha se algum módulo citado nas regras deixar de existir, porque uma expectativa sobre um namespace inexistente passa sem verificar nada.
- **Validação:** 244 testes passando, as 34 rotas e os 4 listeners registrados nos namespaces novos, e as seeders rodando. O ciclo completo de um pedido (criar → aguardar pagamento → pagar → entregar) também foi exercitado com o worker real.

---

## Estrutura modular adotada

```txt
backend/app-modules/              um pacote Composer por módulo (Modules\<Módulo>), com src/ e as camadas abaixo
  Ordering/      Cart, Checkout, Order, OrderItem, OrderPlaced, OrderPaid, DeliveryAddressBook, ShippingQuoter, PayableOrders, CustomerOrderHistory ← Core
  Catalog/       Product, Category, ProductStatus, ProductAvailability, ProductCatalog, CatalogMetrics, ProductQuery
  Inventory/     Stock, StockOperation, AdjustStock, StockInitializer, StockLevels, StockMetrics, StockReservation, StockListQuery
  Payment/       Payment, PaymentGateway, FakePaymentGateway, PayOrder, PaymentApproved
  Fulfillment/   ShippingRateTable, DeliveryCalendar, ScheduleDelivery, DeliverOrder, DeliveryScheduled, OrderDelivered
  Customers/     Account, Profile, admin customer management, CustomerAddress (address book)
  Identity/      User (staff), CustomerAccount, UserRole, Auth, Email, Password, EnsureUserIsAdmin, CustomerAccounts
  Backoffice/    DashboardQuery (consulta que lê os contratos de métricas)
  Messaging/     DomainEvent, EventOutbox, EventConsumer, EventSubscriptions, OutboxRelay, EventTransport, RabbitMqEventTransport, comandos events:*
  Shared/        ApiErrorCode, BusinessRuleException, BaseRepository, AtomicThrottleRequests, RecordId (o kernel)
```

A facade de cada módulo é o seu `Contracts/` mais os seus `Events/`, o primeiro nível de `src/`: as outras pastas são privadas, e o `ModuleBoundariesTest` gera as regras a partir das pastas que existem. Três camadas privadas dividem o resto, com a direção `Http` → `Core` → `Persistence`: `Core/` (`UseCases/`, `Services/`, `Consumers/`, `Jobs/`, `DTOs/`, `ValueObjects/`, `Enums/`, `Exceptions/`), `Persistence/` (`Models/`, `Repositories/`, `Queries/`) e `Http/` (`Controllers/`, `Requests/`, `Resources/`, `Rules/`, `Middleware/`, com `Admin/` para a área administrativa), mais `Providers/`. `Core` e `Persistence` nunca usam o `Http` do próprio módulo (o `ModuleLayersTest`). De `DTOs/`, `ValueObjects/` e `Enums/`, que ficam em `Core/`, só é público o vocabulário que as assinaturas de `Contracts/` e `Events/` expõem, e o `Http/` é privado: cada módulo tem os próprios adaptadores (problema 12). O `Payment` tem ainda `Gateways/`, o `Messaging` tem `Console/`, e o `Shared`, por ser um kernel, não tem camadas. As factories vão com o model, em `database/factories` do módulo.

**Desvios em relação ao `AGENTS.md` do diretório pai:**

- **`Core/UseCases/` em vez de `Actions/`:** é a convenção que o projeto já usava, descrita no README e no `AGENTS.md` deste repositório.
- **Testes em `backend/tests`, e não em `Tests/` dentro de cada módulo:** a configuração do Pest (`tests/Pest.php`) e do PHPUnit separa os testes por tipo (Unit sem banco, Feature com `RefreshDatabase`). Mover os testes para os módulos exigiria refazer essa separação sem ganho de comportamento.
- **Rotas e migrations por módulo, e não centralizadas:** cada módulo declara as suas rotas em `app-modules/<módulo>/routes/api.php` e guarda as migrations das suas tabelas em `database/migrations` do pacote, como o `AGENTS.md` do diretório pai pede. A decisão anterior, um arquivo central de rotas para manter o mapa da API num só lugar, foi trocada na HEL-12 (19/10/2026): o mapa agora é a lista de `route:list`, e criar um módulo deixou de editar a raiz.

### Linguagem ubíqua por contexto

| Termo | Contexto | Significado |
|---|---|---|
| Produto | Catalog | Item cadastrado, com nome, preço, descrição, imagem e status |
| Disponível (`ProductAvailability`) | Catalog | Produto ativo **e** com unidades em estoque; o Ordering monta as mensagens de quantidade pedida sobre ela |
| Estoque | Inventory | Quantidade de unidades de um produto, nunca negativa |
| Produto do catálogo (`CatalogProduct`) | Catalog | O que outro módulo sabe de um produto: id, nome, imagem, preço e status (e, com as unidades, se está disponível) |
| Consulta (`Queries`) | o módulo que serve a rota | A classe de leitura do módulo: devolve value objects ou listas tipadas, nunca grava, lê só as tabelas do próprio módulo e pede o resto ao dono pelo contrato dele |
| Quantidades em estoque (`StockQuantities`) | Inventory | A quantidade de cada produto, com 0 para o produto sem linha de estoque |
| Pedido | Ordering | Compra confirmada, com itens congelados (snapshot) |
| Tentativa de pagamento (`Payment`) | Payment | Uma cobrança de um pedido pelo gateway, aprovada ou recusada |
| Pedido para pagamento (`OrderForPayment`) | Ordering (lido pelo Payment) | O que o Payment sabe de um pedido: cliente, total e status |
| Pedido resumido (`OrderSummary`) | Ordering (lido pelo Customers) | Um pedido do histórico do cliente: id, status, total e data |
| Pagamento recusado | Payment | Tentativa negada pelo gateway, com motivo; o pedido continua aguardando pagamento |
| Pagamento aprovado | Payment | Pedido que pode seguir para a entrega |
| Entregue | Fulfillment | Pedido concluído |
| Endereço (`CustomerAddress`) | Customers | Um endereço do caderno do cliente (até 10), que ele escolhe no checkout |
| Endereço de entrega (`DeliveryAddress`) | Ordering | A cópia de um endereço que o pedido guarda; editar ou excluir o endereço do caderno não a muda |
| Orçamento de frete (`ShippingQuote`) | Ordering (calculado pelo Fulfillment) | Preço em centavos e prazo em dias úteis para uma UF |
| Data prevista | Fulfillment (gravada pelo Ordering) | O dia da entrega, fixado quando o pagamento é aprovado: os dias úteis prometidos a partir de então, no fuso de São Paulo |
| Usuário (`User`) | Identity | Membro da equipe, que entra no admin com o papel `admin` ou `support` (suporte) |
| Conta do cliente (`CustomerAccount`) | Identity | A conta de quem compra na loja (tabela `customers`), sem papel |
| Perfil do cliente (`CustomerProfile`) | Identity (lido pelo Customers) | Os dados de uma conta de cliente sem credenciais: id, nome, e-mail e data de criação |
| Cliente (`Customer`) | Ordering | Quem fez o pedido: identificado pelo id da conta (`customer_id`), conhecido só por nome e e-mail |
| Resumo do cliente (`CustomerSummaryDTO`) | Customers | A conta junto com o histórico de pedidos, na tela "Clientes" do admin |
| Evento (`DomainEvent`) | o módulo que publica | Um fato de um módulo, com nome `<módulo>.<evento>` e versão próprios, gravado no outbox na transação da mudança que ele anuncia |
| Mensagem do outbox (`outbox_messages`) | Messaging | Um evento gravado e ainda não publicado (pendente) ou já entregue ao transporte (publicado, apagado depois de 7 dias) |
| Envelope (`EventEnvelope`) | Messaging | A mensagem como viaja: id, nome, versão, payload, instante e `request_id` |
| Consumidor (`EventConsumer`) | o módulo que consome | A classe que reage a um evento, inscrita pelo próprio módulo; recebe cada mensagem ao menos uma vez, então é idempotente |
| Entrega (`DeliverEvent`) | Messaging | Uma mensagem para um consumidor: repetida sozinha quando o consumidor falha |

---

## Plano de evolução

A ordem abaixo prioriza o que reduz mais risco com o menor esforço:

1. ✅ **Centralizar a disponibilidade** (problemas 1 e 2). Concluído em 06/10/2026 com o `PurchaseAvailabilityService`; a regra passou ao `ProductAvailability` do Catalog na HEL-6 (problema 15).
2. ✅ **Separar Customer de User** (problema 3). Concluído em 06/10/2026 com o modelo `Customer` e o `CustomerSummaryDTO`.
3. ✅ **Separar Payment e Fulfillment por eventos** (problema 5). Concluído em 06/10/2026 com os eventos `OrderPaid` e `OrderDelivered`.
4. ✅ **Expor interfaces do Inventory** (problemas 4 e 6). Concluído em 06/10/2026 com os contratos `StockInitializer` e `StockReservation`, sem mudar o comportamento transacional.
5. ✅ **Migrar para `app/Modules`** (problema 8). Concluído em 06/10/2026: 9 módulos, um contexto por vez, começando pelo Inventory, com as fronteiras verificadas pelo `ModuleBoundariesTest`.
6. ✅ **Separar a conta da equipe da conta do cliente** (problema 9). Concluído em 07/10/2026: tabelas `users` e `customers`, guards `staff` e `customer`, papéis `admin` e `support`, e o `DELETE` do admin só para o papel `admin`.

Depois do plano:

- ✅ **Idempotência dos eventos assíncronos** (problema 18, HEL-16). Concluído em 20/10/2026: a tabela `deliveries` do Fulfillment, o `DeliveryScheduled` e o `OrderDelivered` gravados só pela escrita que mudou a linha, o `DeliverOrder` repetível, e um teste de reentrega pelo `EventDelivery` para cada um dos seis pontos assíncronos. Ver o [design](../.design/event-idempotency.md).
- ✅ **Módulos como pacotes** (problema 17, HEL-12). Concluído em 19/10/2026: os 10 pacotes em `backend/app-modules`, as camadas `Core`, `Persistence` e `Http`, o `Messaging` fora do `Shared`, as factories nos módulos e o teste de arquitetura lendo a árvore nova. Ver o [design](../.design/modular-layout.md).
- ✅ **Rotas e migrations por módulo** (problema 17, HEL-12, slices Module Routes e Module Migrations). Concluído em 19/10/2026: as rotas de 8 módulos em `app-modules/<módulo>/routes/api.php`, o arquivo central de rotas fora, os padrões de UUID no `register()` do módulo dono, e 11 das 14 migrations no módulo dono da tabela, com o mesmo nome e o mesmo conteúdo.
- ✅ **Load Baseline** (HEL-12). Concluído em 20/10/2026: a primeira comparação de carga reprovou (p95 da vitrine de 273 ms contra 25 ms em `main`), porque sem o cache de módulos cada requisição escaneia `app-modules` (54,4 ms contra 16,2 ms em `/api/products`). O `AppServiceProvider` passou a religar o cache da biblioteca a `APP_MODULES_CACHE`, a pilha de carga o define em `/tmp/laravel` e o `make load-test` roda o `modules:cache`; com isso o `load` voltou a 24 e 39 ms de p95 na vitrine e em `POST /api/orders`, e a linha do commit `299af92` entrou na [tabela por transporte](../load-tests/README.md). O custo de descobrir os módulos a cada boot está medido no mesmo README.
- ✅ **Transporte dos eventos por feature flag** (problema 16, HEL-13). Concluído em 18/10/2026: a flag `event-transport` no Pennant, o `RabbitMqEventTransport` atrás da porta `EventTransport`, o nome estável dos consumidores, o `event-consumer`, as filas de mortas com `events:failed` e `events:retry`, o Horizon no `queue-worker` e a medição do k6 por transporte. Ver o [design](../.design/event-transport-flag.md).
- ✅ **Lado de leitura com consultas** (problema 15, HEL-6). Concluído em 16/10/2026: a pasta `Queries/` no Catalog, no Inventory, no Identity, no Ordering e no Backoffice, os contratos de métricas, o `StockLevels::stocksFor`, a escrita devolvendo os dados que gravou, a regra de disponibilidade no `ProductAvailability` e o `ModuleBoundariesTest` sem exceção nenhuma. Ver o [design](../.design/cqrs-read-side.md).
- ✅ **Outbox transacional** (problema 14, HEL-9). Concluído em 15/10/2026: `outbox_messages`, os 5 eventos com nome e versão gravados na transação da mudança, o relay no container `outbox-relay`, o transporte de fila atrás da porta `EventTransport`, os consumidores inscritos pelo módulo que consome e o `DomainEventsTest`. Ver o [design](../.design/transactional-outbox.md).
- ✅ **Ids UUID v7 e nenhuma rota resolvendo registro** (problema 13, HEL-11). Concluído em 15/10/2026: as 9 tabelas de negócio com chave `uuid`, ids `string` em toda assinatura, o código do pedido, o dono na busca das rotas do cliente sem as três policies, seis rotas com uma query a menos e o `RouteBindingTest` recusando qualquer binding. Ver o [design](../.design/uuid-record-ids.md).
- ✅ **Vocabulário público dos módulos** (problema 12, HEL-10). Concluído em 09/10/2026: o vocabulário calculado das assinaturas de `Contracts` e `Events`, o `Http` privado e os adaptadores próprios do Customers e do Fulfillment, com a exceção do `ProductStatus`, que a HEL-6 removeu. Ver o [design](../.design/module-vocabulary.md).
- ✅ **Facades dos módulos** (problema 11, HEL-7). Concluído em 08/10/2026: cinco contratos novos, os contratos do Inventory e os eventos falando em dados, e o `ModuleBoundariesTest` como lista do que é permitido, com as exceções da HEL-6 declaradas. Ver o [design](../.design/module-facades.md).
- ✅ **Endereços de entrega e frete** (problema 10). Concluído em 07/10/2026: o caderno de endereços no Customers, a tabela de frete e a data prevista no Fulfillment, a cópia do endereço no pedido e os contratos `DeliveryAddressBook` e `ShippingQuoter`, com as regras novas no `ModuleBoundariesTest`. Ver o [design](../.design/addresses-and-shipping.md).
- ✅ **Modelo `Payment` próprio e gateway fake atrás de uma porta** (recomendação da matriz Ordering × Payment). Concluído em 06/10/2026: tabela `payments`, porta `PaymentGateway` ligada ao `FakePaymentGateway` e recusa com `402`, com as regras da porta verificadas pelo `ModuleBoundariesTest`. Ver o [design](../.design/fake-payment-gateway.md).

## Pendências depois do plano

Os 5 passos organizaram o código e tornaram as fronteiras explícitas e verificadas, mas os módulos **continuam dividindo o mesmo banco**. O que ainda liga um contexto aos dados de outro:

| Pendência | Onde | Por que ficou |
|---|---|---|
| ✅ Resolvida: leituras do estoque pela relação `Product::stock()` | Vitrine e cadastro do admin (`ProductResource`), lista de estoque (`Stock::product`) | Resolvida na HEL-6 (problema 15): as relações saíram, e a consulta de cada módulo pede ao dono pelo contrato (`StockLevels::stocksFor` e `ProductCatalog`) |
| Estoque excluído por `cascadeOnDelete` | FK `stocks.product_id` | É a regra 1:1 garantida pelo banco |
| ✅ Resolvida: Catalog e Inventory usavam uma regra do Ordering na vitrine e na lista de estoque (achado ao resolver o problema 7) | `ProductResource`, `ProductController` e `StockResource` usavam o `PurchaseAvailabilityService` | Resolvida na HEL-6 (problema 15): a regra mora no `ProductAvailability` do Catalog, e o Ordering ficou só com as mensagens |
| Tentativas de pagamento presas ao pedido por `restrictOnDelete` | FK `payments.order_id` | O Payment grava na própria tabela, mas referencia `orders`; pedidos nunca são apagados, então a FK só protege o histórico |
| ✅ Resolvida: eventos carregavam o model `Order` | `OrderPlaced`, `PaymentApproved`, `OrderPaid`, `OrderDelivered`, `DeliveryScheduled`, `DeliverOrder` | Resolvida no problema 11: carregam o `orderId` e os valores de que o consumidor precisa. O envelope e o outbox vieram na HEL-9 (problema 14) |
| ✅ Resolvida: o dashboard lia os repositories de vários módulos | O caso de uso do dashboard e o `DashboardService` (que usa o enum `ProductStatus` do Catalog) | Resolvida na HEL-6 (problema 15): o `DashboardQuery` lê os contratos de métricas, e o `ProductStatus` entrou no vocabulário do Catalog |
| ✅ Resolvida: um módulo usa `Http`, `ValueObjects`, `Enums` e `DTOs` de outro | O Customers usava as regras de e-mail e senha e o resource da conta do Identity, e o Customers e o Fulfillment, o trait de UF do Ordering | Resolvida no problema 12 (HEL-10): só o vocabulário que as assinaturas de `Contracts` e `Events` expõem é público, o `Http` é privado e cada módulo tem os próprios adaptadores |

> As classificações e fronteiras desta análise foram derivadas do código. Elas devem ser validadas com quem conhece o negócio antes de qualquer refatoração estrutural.
