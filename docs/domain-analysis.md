# Análise de Domínio: Contextos Delimitados do Backend

> Análise estratégica (DDD) do código em [`backend/`](../backend), feita em 06/10/2026.
> Última atualização: 09/10/2026 (ajustes da revisão do PR #12: repository sem service, um só resource para a conta de cliente e a nota de deploy sobre payloads na fila; antes, em 08/10/2026: facades dos módulos: cada módulo é usado de fora só pelos seus contratos e eventos, que falam em dados, e o `ModuleBoundariesTest` virou uma lista do que é permitido; em 07/10/2026: endereços de entrega e frete: Customers com o caderno de endereços, Fulfillment com a tabela de frete e a data prevista, e o pedido com a cópia do endereço; equipe e clientes em contas separadas, com papéis `admin` e `suporte`; todos os 5 passos do plano de evolução concluídos; listas de domínio tipadas; Payment com modelo próprio e gateway fake atrás de uma porta).
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

Desde o passo 5 do [plano de evolução](#plano-de-evolução), cada um deles é um módulo em `backend/app/Modules` (ver a [estrutura modular adotada](#estrutura-modular-adotada)).

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

 Ordering define os contratos DeliveryAddressBook (implementado pelo Customers) e
 ShippingQuoter (implementado pelo Fulfillment): o Ordering não conhece nenhum dos dois módulos.

 A facade de cada módulo é o seu Contracts mais os seus Events (dados, nunca models):
 Ordering → Catalog: ProductCatalog            Ordering → Inventory: StockLevels, StockReservation
 Catalog → Inventory: StockInitializer         Payment → Ordering: PayableOrders
 Customers → Ordering: CustomerOrderHistory    Customers → Identity: CustomerAccounts

   Customer Account (Supporting) e Backoffice Reporting (read model) leem de todos.
```

### Padrões de integração atuais e recomendados

| Upstream | Downstream | Padrão recomendado |
|---|---|---|
| Catalog | Ordering | Customer/Supplier: o Catalog fornece preço, nome e status pelo contrato `ProductCatalog` (`CatalogProduct`); o Ordering guarda um snapshot |
| Inventory | Ordering | Open Host Service: contratos `StockReservation` (bloqueio e débito de estoque) e `StockLevels` (quantidades sem bloqueio, para o carrinho), que devolvem `StockQuantities` |
| Inventory | Catalog | Open Host Service: contrato `StockInitializer` (abertura do estoque de um produto novo) |
| Ordering | Payment | Customer/Supplier: o Payment lê o pedido pelo contrato `PayableOrders` (`OrderForPayment`: cliente, total e status) e decide se ele pode ser pago |
| Ordering | Customers | Open Host Service: contrato `CustomerOrderHistory` (contagens e `OrderSummaries`) para as telas da conta e de clientes |
| Identity | Customers | Open Host Service: contrato `CustomerAccounts` (`CustomerProfile`); as regras da conta ficam no Identity |
| Payment | Ordering | Evento de domínio `PaymentApproved` (com o `orderId`): o Ordering marca o pedido como pago |
| Ordering | Fulfillment | Evento de domínio `OrderPaid` (com o `orderId` e os dias úteis prometidos): o Fulfillment agenda a entrega sem ler o pedido |
| Fulfillment | Ordering | Evento de domínio `OrderDelivered`: o Ordering encerra o pedido |
| Fulfillment | Ordering | Evento de domínio `DeliveryScheduled`: o Ordering grava a data prevista, sem mudar o status |
| Customers | Ordering | Inversão de dependência: o contrato `DeliveryAddressBook` é definido pelo Ordering e implementado pelo Customers; o Ordering recebe a cópia de um endereço do cliente |
| Fulfillment | Ordering | Inversão de dependência: o contrato `ShippingQuoter` é definido pelo Ordering e implementado pelo Fulfillment; o Ordering recebe o preço e o prazo para uma UF |
| Identity | todos | Conformist: os outros contextos referenciam apenas o id da conta; as policies recebem o `Authenticatable` do framework |
| todos | Backoffice Reporting | Read model, somente leitura |

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
- [`ValueObjects`](../backend/app/Modules/Ordering/ValueObjects/): `ProductQuantities`, `OrderLines`/`OrderLine`, `ValidatedCart`/`ValidatedCartLine`, `CustomerIds` e `OrderCountsByCustomer`, listas tipadas que calculam os próprios totais.
- [`ValueObjects`](../backend/app/Modules/Ordering/ValueObjects/) do vocabulário que os contratos trocam: `DeliveryAddress` (a cópia de um endereço) e `ShippingQuote` (preço em centavos e prazo em dias úteis), e o enum `BrazilianState` (as 27 UFs).
- [Contratos](../backend/app/Modules/Ordering/Contracts/) definidos pelo Ordering: `DeliveryAddressBook` (`find(customerId, addressId): ?DeliveryAddress`, implementado pelo Customers) e `ShippingQuoter` (`quote(BrazilianState): ShippingQuote`, implementado pelo Fulfillment).
- Contratos publicados pelo Ordering e implementados pelo `OrderRepository`: `PayableOrders` (`findForPayment(orderId): ?OrderForPayment`, para o Payment) e `CustomerOrderHistory` (`countForCustomer`, `countPerCustomer`, `recentForCustomer(...): OrderSummaries`, para o Customers).
- `CheckoutService` (Service): valida o atendimento do pedido e monta os itens.
- `CartValidationService` (Service): revalida o carrinho antes do checkout.
- `PurchaseAvailabilityService` (Service): fonte única da regra "pode ser comprado" (produto ativo **e** com estoque).
- `Customer` (Read model): o comprador visto pelo pedido, uma projeção somente leitura de `customers` (`id`, `name`, `email`). O pedido guarda `orders.customer_id`.
- `PlaceOrderUseCase` (Use Case): checkout atômico. Resolve o endereço e o frete antes da transação (um endereço que não é do cliente responde `422` sem tocar no estoque) e grava o pedido com a cópia, o frete e o total.
- `RecordEstimatedDeliveryUseCase` (Use Case) e o listener `RecordEstimatedDelivery`: gravam a data prevista que o Fulfillment anunciou, só se o pedido ainda não tem uma.
- `ValidateCartUseCase` (Use Case): validação do carrinho, só leitura.
- `MarkOrderAsAwaitingPaymentUseCase` (Use Case): primeira transição de status.
- `OrderPlaced` (Domain Event), `OrderPolicy`, `InsufficientStockException`.

**Por que é o Core Domain:** concentra a lógica mais sofisticada e mais crítica do sistema:

- Checkout atômico, com `SELECT … FOR UPDATE` nas linhas de estoque em ordem determinística (por `product_id`) para evitar deadlocks.
- O preço sempre vem do banco, nunca do cliente.
- Os itens guardam um snapshot histórico de nome e preço.
- As transições de status são idempotentes (`OrderRepository::transitionStatus`), o que torna seguros os retries das filas.
- O evento só é disparado depois do commit (`ShouldDispatchAfterCommit`).
- O pedido nasce com a cópia do endereço e o frete calculado no servidor: editar ou excluir o endereço, ou mudar a tabela de frete, nunca altera um pedido feito.

**Dependências:**

- → Catalog: preço, nome e status do produto, pelo contrato `ProductCatalog`.
- → Inventory: quantidades (`StockLevels`), bloqueio e débito de estoque (`StockReservation`), por id.
- → Identity: só o id do comprador; a `OrderPolicy` recebe o `Authenticatable` do framework.
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
- `ProductIds` e `CategoryIds` ([`ValueObjects`](../backend/app/Modules/Catalog/ValueObjects/)): listas tipadas de ids positivos e sem repetição. O `ProductIds` é o tipo dos contratos `ProductCatalog`, `StockLevels` e `StockReservation`.
- [`ProductCatalog`](../backend/app/Modules/Catalog/Contracts/ProductCatalog.php) (contrato publicado, implementado pelo `ProductRepository` e ligado no `CatalogServiceProvider`): `findMany(ProductIds): CatalogProducts`, com o id, o nome, a imagem, o preço e se cada produto está ativo (`CatalogProduct`). É por ele que o carrinho e o checkout leem os produtos.
- Use cases: `CreateProductUseCase`, `UpdateProductUseCase`, `DeleteProductUseCase`, `ChangeProductStatusUseCase`, `CreateCategoryUseCase`, `UpdateCategoryUseCase`, `DeleteCategoryUseCase`.
- `ProductController` (catálogo público), `Admin\ProductController`, `Admin\CategoryController`.

**Coesão:** 9/10 ✅. Subiu de 7/10 em dois passos: a regra de disponibilidade saiu do `Product` (problema 1) e a criação do estoque passou pelo contrato `StockInitializer` (problema 4). O que ainda pesa é a leitura do estoque pela relação `Product::stock()` e o uso do `PurchaseAvailabilityService` do Ordering na vitrine (ver as [pendências](#pendências-depois-do-plano)).

**Contexto sugerido:** `CatalogContext`

---

### 3. Inventory: Supporting Subdomain

**Linguagem ubíqua:** quantidade, unidade, entrada/saída (`StockOperation`), ajuste, estoque negativo.

**Capacidade de negócio:** controlar a quantidade disponível de cada produto, sem nunca permitir estoque negativo.

**Conceitos principais:**

- `Stock` (Entity): relação 1:1 com `Product`.
- `StockOperation` (Enum): `increase` / `decrease`.
- `AdjustStockDTO`, `StockService`, `AdjustStockUseCase`.
- `StockRepository`: `quantitiesFor`, `lockForProducts`, `lockById`, `decrement`. É de uso interno do Inventory.
- Contratos publicados ([`App\Modules\Inventory\Contracts`](../backend/app/Modules/Inventory/Contracts)): `StockInitializer` (para o Catalog), `StockLevels` e `StockReservation` (para o Ordering), implementados pelo `StockRepository`. Falam em ids e devolvem [`StockQuantities`](../backend/app/Modules/Inventory/ValueObjects/StockQuantities.php) (quantidade por produto, 0 sem linha de estoque), nunca `Product` ou `Stock`.
- `Admin\StockController`.

**Coesão:** 9/10 ✅. A regra "estoque nunca negativo" é aplicada em duas camadas: no `StockService` e na constraint `CHECK (quantity >= 0)` do banco. Subiu de 8/10 porque o que o Inventory oferece aos outros contextos agora está explícito em contratos.

**Contexto sugerido:** `InventoryContext`

---

### 4. Payment: Supporting Subdomain (simulado)

**Linguagem ubíqua:** pagar, aguardando pagamento, tentativa de pagamento, cartão de teste, pagamento aprovado, pagamento recusado, motivo da recusa.

**Capacidade de negócio:** cobrar um pedido por um gateway e registrar cada tentativa. O gateway atual é fake: aprova ou recusa pelo cartão de teste, e a recusa permite tentar de novo.

**Conceitos principais:**

- [`Payment`](../backend/app/Modules/Payment/Models/Payment.php) (Entity): uma tentativa de cobrança, `approved` ou `declined`, final depois de gravada. O banco garante no máximo uma aprovada por pedido.
- [`PaymentGateway`](../backend/app/Modules/Payment/Contracts/PaymentGateway.php) (porta): recebe `ChargeRequest` (pedido, valor em centavos, token do cartão) e devolve `ChargeResult`. Não conhece o model `Order`.
- [`FakePaymentGateway`](../backend/app/Modules/Payment/Gateways/FakePaymentGateway.php) (adaptador): decide pelo token; ligado no `PaymentServiceProvider`.
- `PaymentStatus` e `DeclineReason` (Enums).
- `PaymentService`: define quais pedidos podem ser pagos (`awaiting_payment` e sem pagamento aprovado), sobre o `OrderForPayment` que o contrato `PayableOrders` do Ordering devolve.
- `OrderPaymentPolicy`: só o cliente que fez o pedido o paga. O pedido da rota (`{payableOrder}`) é resolvido pelo `PayableOrders` no `PaymentServiceProvider`, antes da validação do corpo.
- [`PayOrderUseCase`](../backend/app/Modules/Payment/UseCases/PayOrderUseCase.php): cobra pela porta, grava a tentativa e publica a aprovação ou lança `PaymentDeclinedException` (`402`).
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

- [`ShippingRateTable`](../backend/app/Modules/Fulfillment/Services/ShippingRateTable.php) (Service): a tabela de preço e prazo por UF, lida de `config('shop.shipping_rates')`. Implementa o contrato `ShippingQuoter` do Ordering, ligado no [`FulfillmentServiceProvider`](../backend/app/Modules/Fulfillment/FulfillmentServiceProvider.php), e responde `GET /api/shipping/quote` (público).
- [`DeliveryCalendar`](../backend/app/Modules/Fulfillment/Services/DeliveryCalendar.php) (Service): a contagem de dias úteis (segunda a sexta, sem feriados) no fuso `America/Sao_Paulo`.
- Listener `ScheduleOrderDelivery` (consome `OrderPaid`) e [`ScheduleDeliveryUseCase`](../backend/app/Modules/Fulfillment/UseCases/ScheduleDeliveryUseCase.php): calcula a data prevista a partir dos dias úteis que o `OrderPaid` carrega, sem ler o pedido, registra no log (só ids e data, nunca o endereço), publica `DeliveryScheduled` e agenda o `DeliverOrder`, todos com o `orderId`.
- Job `DeliverOrder` e `DeliverOrderUseCase` (transportadora fake).
- `DeliveryScheduled` e `OrderDelivered` (Domain Events), publicados pelo Fulfillment e consumidos pelo Ordering.
- `ShippingQuoteController` e `ShippingQuoteRequest` (a UF é normalizada antes de validar).
- `config('shop.delivery_delay_seconds')`.

**Coesão:** 8/10 ✅. Subiu de 7/10: o Fulfillment ganhou regras próprias (a tabela de frete e o calendário de dias úteis) e deixou de ser só um job com atraso. Nenhum outro módulo calcula preço, prazo ou data. Ele continua sem estado próprio: não existe uma tabela de remessas, porque a data prevista é gravada no pedido pelo Ordering (só o Ordering escreve em `orders`). Uma remessa (`shipments`) só se paga com rastreio, etapa "Enviado", várias remessas por pedido ou tarifas que mudem entre o orçamento e a compra, e nada disso está no escopo.

**Contexto sugerido:** `FulfillmentContext`

---

### 6. Identity & Access: Generic Subdomain

**Linguagem ubíqua:** membro da equipe (`User`), conta do cliente (`CustomerAccount`), login, sessão, guard, papel (admin/suporte), permissão.

**Capacidade de negócio:** autenticar a equipe e os clientes, cada um com a sua conta e o seu guard, e controlar o acesso às áreas pública, de cliente e administrativa (o admin faz tudo; o suporte cadastra, edita e consulta, mas não remove nem gerencia a equipe).

**Conceitos principais:**

- `User` (Entity da equipe, tabela `users`, guard `staff`) e `UserRole` (Enum: `admin`, `support`).
- `CustomerAccount` (Entity do comprador, tabela `customers`, guard `customer`) e o `CustomerAccountRepository`. O `CustomerAccount::toProfile()` é o único lugar que transforma a conta em `CustomerProfile`.
- [`CustomerAccounts`](../backend/app/Modules/Identity/Contracts/CustomerAccounts.php) (contrato publicado para o Customers, implementado pelo `CustomerAccountRepository` e ligado no `IdentityServiceProvider`): cria, atualiza o perfil, busca e lista as contas de cliente e devolve `CustomerProfile` (id, nome, e-mail e data de criação, sem credenciais). As regras da conta (e-mail normalizado, senha com hash, senha que só muda quando uma nova é enviada) ficam atrás dele; a última está no `UpdateUserProfileDTO::toArray()`, que também serve à equipe.
- [`CustomerProfileResource`](../backend/app/Modules/Identity/Http/Resources/CustomerProfileResource.php): o único formato da conta de cliente na API, usado pelo `AuthController` e pelas telas de conta e de clientes do Customers.
- `AuthController` (login da loja, Sanctum SPA), `StaffAuthController` (login do admin), `LoginCredentialsDTO`, `RegisterCustomerUseCase`.
- Gestão da equipe: `StaffMemberController` e os casos de uso `List`, `Create`, `Update` e `DeleteStaffMemberUseCase` (o admin não muda o próprio papel nem remove a própria conta).
- Middleware `EnsureUserIsAdmin`: o grupo de rotas do admin que guarda todo `DELETE` e a gestão da equipe.

- `Email` e `Password` ([`ValueObjects`](../backend/app/Modules/Identity/ValueObjects/)): as regras de e-mail (canônico, em minúsculas, até 255 caracteres) e de senha (mínimo de 8) ficam escritas uma vez e chegam aos Form Requests por `EmailRule` e `PasswordRule`. O `users.email` e o `customers.email` têm o `CHECK ..._email_normalized` como última defesa.

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
- `Admin\CustomerController`. O `{customer}` das rotas do admin é resolvido pelo contrato `CustomerAccounts` do Identity no `CustomersServiceProvider` (`404` para conta inexistente).
- `OrderSummaryResource`: os pedidos resumidos (`id`, `status`, `status_label`, `total_cents`, `created_at`) nas telas de conta e de clientes. Os dados da conta saem pelo `CustomerProfileResource` do Identity.
- [`CustomerAddress`](../backend/app/Modules/Customers/Models/CustomerAddress.php) (Entity, tabela `customer_addresses`): o caderno do cliente, apagado junto com a conta (`cascadeOnDelete`; não há nada a preservar, porque o pedido guarda cópia). O banco garante o CEP com 8 dígitos e a UF entre as 27.
- [`CustomerAddressRepository`](../backend/app/Modules/Customers/Repositories/CustomerAddressRepository.php): também implementa o contrato `DeliveryAddressBook` do Ordering, ligado no [`CustomersServiceProvider`](../backend/app/Modules/Customers/CustomersServiceProvider.php). Um endereço de outro cliente responde igual a um que não existe.
- `CustomerAddressController` (`/api/account/addresses`), `CustomerAddressRequest` (um só para criar e editar; normaliza a UF), `CustomerAddressPolicy` (só o dono edita e exclui) e os casos de uso `CreateCustomerAddressUseCase`, `UpdateCustomerAddressUseCase` e `DeleteCustomerAddressUseCase`.
- `CustomerAddressService` (Service): o limite de 10 endereços (`409`).

**Observação:** este contexto **compõe** dois outros nas telas de clientes: os dados da conta vêm do Identity (contrato `CustomerAccounts`) e as estatísticas de pedidos vêm do Ordering (contrato `CustomerOrderHistory`). Os dois lados são lidos separadamente e só se encontram no `CustomerSummaryDTO`. O caderno de endereços é o primeiro dado que o Customers possui e grava por conta própria.

**Coesão:** 8/10 ✅. Subiu de 7/10: a nota anterior dizia que ficava aceitável gravar tudo no `CustomerAccount` enquanto não houvesse dados próprios de cliente, e agora há (`customer_addresses`, com model, repository, policy e regras próprios). O cadastro e a edição de clientes pela equipe continuam gravando a conta no Identity, agora pelo contrato `CustomerAccounts`, porque nome, e-mail e senha são dados da conta, não do cliente.

**Contexto sugerido:** `CustomersContext`

---

### Contexto de leitura: Backoffice Reporting (Dashboard)

- `DashboardService` e `GetAdminDashboardUseCase` agregam contagens de Catalog, Inventory, Identity e Ordering.
- Funciona como **read model** (lado de consulta). Depender de todos os contextos é aceitável aqui, desde que seja só leitura.

### Shared Kernel (infraestrutura, não é domínio)

`ApiErrorCode`, `ApiErrorResponse`, `ApiExceptionRenderer`, `BusinessRuleException`, `ApiFormRequest`, `BaseRepository`, middleware `AssignRequestId`.

---

## Matriz de coesão entre contextos

| Contexto A | Contexto B | Acoplamento atual | Problema | Recomendação |
|---|---|---|---|---|
| Catalog | Inventory | Baixo | ✅ O estoque é criado pelo contrato `StockInitializer`, por id; restam as leituras por `Product::stock()` na vitrine, declaradas como exceção até a HEL-6 | Resolver as leituras na HEL-6 (CQRS) |
| Ordering | Inventory | Alto (necessário) | ✅ O checkout usa só o contrato `StockReservation` e o carrinho o `StockLevels`, por id e com `StockQuantities`, na mesma transação | Manter: a consistência sob concorrência exige a transação única |
| Ordering | Catalog | Baixo | ✅ O snapshot de nome e preço está correto, e o Ordering lê os produtos pelo contrato `ProductCatalog`, sem o model `Product` nem o `ProductRepository` | Customer/Supplier: o Catalog publica preço e status |
| Ordering | Payment | Baixo | ✅ O Payment lê o pedido pelo contrato `PayableOrders` e só publica `PaymentApproved` (com o id); o Ordering muda o status. O Payment tem modelo próprio (`Payment`) e cobra pela porta `PaymentGateway` | Manter a integração por contrato e eventos |
| Payment | Fulfillment | Nenhum | ✅ Não se conhecem: o Fulfillment reage a `OrderPaid`, publicado pelo Ordering | Manter a integração por eventos |
| Identity | Ordering | Baixo | ✅ O Ordering referencia o cliente só por id (`orders.customer_id`) e lê nome e e-mail pelo `Customer`. O `User` da equipe é proibido no Ordering, no Payment e no Fulfillment (`ModuleBoundariesTest`), e nem o `CustomerAccount` chega à `OrderPolicy`, que recebe o `Authenticatable` | Conformist: manter o `Customer` somente leitura |
| Catalog | Ordering | Baixo | ✅ A exclusão pergunta pelo contrato `ProductOrderHistory`, definido pelo Catalog e implementado pelo Ordering | Manter a inversão de dependência |
| Ordering | Customers | Baixo | ✅ O Ordering pede a cópia do endereço pelo contrato `DeliveryAddressBook`, que ele define e o Customers implementa. O `ModuleBoundariesTest` proíbe o Ordering de usar o Customers | Manter a inversão de dependência |
| Ordering | Fulfillment | Baixo | ✅ O Ordering pede o orçamento pelo contrato `ShippingQuoter` (implementado pelo Fulfillment) e só conhece o Fulfillment pelos eventos. O `ModuleBoundariesTest` falha se o Ordering usar qualquer outro namespace dele (uma expectativa por namespace) | Manter a integração por contrato e eventos |
| Fulfillment | Customers | Nenhum | ✅ O Fulfillment não conhece o caderno de endereços: o pedido carrega a cópia de que ele precisa (`ModuleBoundariesTest`) | Manter |
| Customers | Identity | Baixo | ✅ O Customers cria, edita, busca e lista as contas pelo contrato `CustomerAccounts`, que devolve `CustomerProfile`; do `Http` do Identity restam as regras de e-mail e senha nos Form Requests e o `CustomerProfileResource` (HEL-10) | Manter o contrato |
| Customers | Ordering | Baixo | ✅ A contagem e os pedidos recentes vêm pelo contrato `CustomerOrderHistory`, como `OrderSummaries` | Manter o contrato |

---

## Problemas detectados

### Prioridade alta

#### 1. ✅ Resolvido: a regra de disponibilidade misturava Catalog, Inventory e Ordering

- **Onde estava:** `Product::isAvailable()`, `Product::availableQuantity()` e `Product::purchaseProblem()`.
- **Problema:** a entidade de catálogo decidia se algo "pode ser comprado". Essa é uma regra de venda que depende do estoque, e as mensagens ("Estoque insuficiente") pertencem ao Ordering.
- **Solução aplicada:** a regra foi para o [`PurchaseAvailabilityService`](../backend/app/Modules/Ordering/Services/PurchaseAvailabilityService.php), um service puro que recebe o produto e o estoque explicitamente.
  - O `Product` ficou só com `isActive()` (Catalog).
  - O `Stock` ganhou `hasUnits()` (Inventory).
  - `CheckoutService`, `CartValidationService`, `ProductController` e `ProductResource` recebem ou resolvem o service.
- **Nome:** a análise original sugeria `PurchaseAvailabilityPolicy`. A classe virou `Service` porque `app/Policies` é reservado às policies de autorização do Laravel, e `app/Services` já é a camada de regras puras do projeto. Na migração para módulos (passo 5), ela foi para `Modules/Ordering`.
- **Testes:** [`PurchaseAvailabilityServiceTest`](../backend/tests/Unit/Services/PurchaseAvailabilityServiceTest.php).

#### 2. ✅ Resolvido: regra de disponibilidade duplicada no estoque

- **Onde estava:** `StockResource` (`isActive() && quantity > 0`).
- **Problema:** era uma segunda fonte de verdade para a mesma regra. Se a regra mudasse, uma das cópias ia divergir.
- **Solução aplicada:** o [`StockResource`](../backend/app/Modules/Inventory/Http/Resources/StockResource.php) passou a usar o `PurchaseAvailabilityService`.

#### 3. ✅ Resolvido: "User" tinha dois significados, identidade e cliente

- **Onde estava:** `User::orders()`, `UserRepository::paginateWithOrderCount()` / `loadRecentOrders()`, os campos `orders` e `orders_count` do `UserResource`, `Order::user()` e os métodos do `OrderRepository` que recebiam o `User`.
- **Problema:** o modelo de autenticação conhecia pedidos, e Identity (Generic) ficava acoplado ao Core.
- **Solução aplicada:**
  - [`User`](../backend/app/Modules/Identity/Models/User.php) ficou só com identidade (login, senha, papel e perfil). O [`StaffMemberResource`](../backend/app/Modules/Identity/Http/Resources/StaffMemberResource.php) (antes `UserResource`) não fala mais de pedidos.
  - Novo [`Customer`](../backend/app/Modules/Ordering/Models/Customer.php): o comprador visto pelo Ordering, uma projeção somente leitura de `users` com `id`, `name` e `email`. Ele lança `LogicException` em qualquer tentativa de gravar ou excluir. `Order::user()` virou `Order::customer()`.
  - O [`OrderRepository`](../backend/app/Modules/Ordering/Repositories/OrderRepository.php) trabalha com o id do cliente (`paginateForCustomer`, `recentForCustomer`, `countForCustomer`, `countPerCustomer`, `createWithItems`), com `CustomerIds`, `OrderCountsByCustomer` e `OrderLines` no lugar de arrays, e o `PlaceOrderUseCase` recebe `int $customerId`.
  - A tela "Clientes" do admin compõe os dois lados no [`CustomerSummaryDTO`](../backend/app/Modules/Customers/DTOs/CustomerSummaryDTO.php) e no [`CustomerSummaryResource`](../backend/app/Modules/Customers/Http/Resources/CustomerSummaryResource.php), por meio do `ListCustomersUseCase` e do `ShowCustomerUseCase`. A listagem faz 2 consultas por página, sem N+1.
- **Decisões:**
  - **O contrato da API não mudou:** os endpoints `/api/admin/users`, `/api/account` e `/api/auth/me` devolvem os mesmos campos, e o frontend não precisou de ajustes. *(Superado pelo [problema 9](#9-resolvido-equipe-e-cliente-dividiam-a-mesma-conta): hoje a tela vive em `/api/admin/customers`.)*
  - **Sem escopo global de papel no `Customer`:** para o Ordering, cliente é quem fez o pedido; o papel é um conceito do Identity. A tela "Clientes" continua listando também os administradores (com 0 pedidos), como antes.
  - **A coluna continua `orders.user_id`:** renomear para `customer_id` exigiria uma migration sem ganho de comportamento. A relação `Order::customer()` declara a chave explicitamente. *(Superado pelo problema 9: a coluna passou a `orders.customer_id`, ligada a `customers`.)*
- **Testes:** [`CustomerTest`](../backend/tests/Feature/Models/CustomerTest.php), além dos casos novos em `UserUseCasesTest`, `OrderRepositoryTest` e `Admin/CustomerTest` (antes `Admin/UserTest`).

### Prioridade média

#### 4. ✅ Resolvido: use case de Catalog escrevia no Inventory

- **Onde estava:** `CreateProductUseCase` usava o `StockRepository` inteiro para criar a linha de estoque.
- **Problema:** o Catalog dependia dos detalhes de dados do Inventory e podia chamar qualquer operação dele (ajustar, bloquear, excluir).
- **Solução aplicada:** o [`CreateProductUseCase`](../backend/app/Modules/Catalog/UseCases/CreateProductUseCase.php) depende só do contrato [`StockInitializer`](../backend/app/Modules/Inventory/Contracts/StockInitializer.php), que tem uma única operação: `createForProduct()`.
- **Por que não um evento `ProductCreated`:** o produto e o seu estoque precisam nascer juntos. Um produto sem estoque quebraria o 1:1 e a tela de estoque do admin. Por isso a criação continua na mesma transação, como a própria recomendação previa para o caso de consistência imediata.
- **O que ainda fica (aceito por enquanto):**
  - Leituras do estoque pela relação `Product::stock()`, no catálogo, no carrinho e no `ProductResource`. Elas são o read model da vitrine.
  - A exclusão de um produto remove o estoque por `cascadeOnDelete` na chave estrangeira.
  - O dashboard lê o `StockRepository` diretamente. É um read model, e a dependência é só de leitura.

  Esses pontos continuam em aberto depois da migração para módulos (ver as [pendências](#pendências-depois-do-plano)).

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

  - Ordering: [`MarkOrderAsPaidUseCase`](../backend/app/Modules/Ordering/UseCases/MarkOrderAsPaidUseCase.php) e [`MarkOrderAsDeliveredUseCase`](../backend/app/Modules/Ordering/UseCases/MarkOrderAsDeliveredUseCase.php), com os eventos [`OrderPaid`](../backend/app/Modules/Ordering/Events/OrderPaid.php) e os listeners `MarkOrderAsPaid` / `MarkOrderAsDelivered`.
  - Payment: [`PayOrderUseCase`](../backend/app/Modules/Payment/UseCases/PayOrderUseCase.php).
  - Fulfillment: [`ScheduleDeliveryUseCase`](../backend/app/Modules/Fulfillment/UseCases/ScheduleDeliveryUseCase.php), [`DeliverOrderUseCase`](../backend/app/Modules/Fulfillment/UseCases/DeliverOrderUseCase.php), o job [`DeliverOrder`](../backend/app/Modules/Fulfillment/Jobs/DeliverOrder.php) e o evento [`OrderDelivered`](../backend/app/Modules/Fulfillment/Events/OrderDelivered.php).
- **Desvio da recomendação original:** a matriz sugeria que o Fulfillment escutasse `PaymentApproved`. Assim, a entrega e a marcação de pago correriam em paralelo; num retry ou atraso da fila, a entrega poderia rodar antes e o pedido ficaria preso em `payment_approved`. Por isso o Ordering publica `OrderPaid` **só depois** da transição, e o Fulfillment escuta esse evento.
- **`OrderStatus` continua com 4 estados:** agora ele é a visão do Ordering sobre o ciclo de vida, e cada transição é disparada pelo evento do contexto responsável.
- **Eventos com dados:** ✅ desde o problema 11 (HEL-7), os eventos e o job `DeliverOrder` carregam o `orderId` e os valores de que o consumidor precisa, nunca o model `Order` (Published Language).
- **Validação:** além do `OrderStatusFlowTest`, o fluxo foi exercitado com o worker real (Redis): `MarkOrderAsPaid` → `ScheduleOrderDelivery` → `DeliverOrder` (10 s depois) → `MarkOrderAsDelivered`.

#### 6. ✅ Resolvido: checkout acessava o estoque pelo repositório

- **Onde estava:** `PlaceOrderUseCase` recebia o `StockRepository` inteiro.
- **Observação mantida:** a transação única entre os contextos é uma **escolha correta** num monólito, porque garante a consistência do estoque sob concorrência.
- **Solução aplicada:** o [`PlaceOrderUseCase`](../backend/app/Modules/Ordering/UseCases/PlaceOrderUseCase.php) depende só do contrato [`StockReservation`](../backend/app/Modules/Inventory/Contracts/StockReservation.php), com `lockForProducts()` e `decrement()`. O contrato documenta que as duas operações rodam dentro da transação de quem chama.
  - **Comportamento inalterado:** o `StockRepository` implementa os dois contratos e é ligado a eles no `InventoryServiceProvider` (`$bindings`). As consultas, a ordem dos bloqueios e o `FOR UPDATE` são exatamente os mesmos, e os testes de concorrência do `CheckoutTest` continuam passando.
- **Fronteira verificada:** o teste de arquitetura [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) falha se qualquer módulo além do Inventory e do read model do Backoffice usar o `StockRepository`. A falha foi conferida introduzindo uma violação de propósito.
  - **Armadilha do Pest:** com uma *lista* de namespaces, o `not->toUse` dessa versão nunca falha. Por isso o teste gera uma expectativa por namespace.

#### 9. ✅ Resolvido: equipe e cliente dividiam a mesma conta

- **Onde estava:** uma única tabela `users`, com `role` `admin` ou `customer`, um único login (`POST /api/auth/login`) e o `auth:sanctum` em todas as rotas autenticadas.
- **Problema:** não havia um perfil de suporte (quem atende e mantém o catálogo sem poder excluir), o mesmo e-mail não podia ser equipe e cliente, e a tela "Clientes" listava também os administradores.
- **Solução aplicada** (feature `staff-and-customer-accounts`, [design](../.design/staff-and-customer-accounts.md)):
  - Duas contas independentes no Identity: [`User`](../backend/app/Modules/Identity/Models/User.php) (equipe, `users`, papel `admin` ou `support`, `CHECK users_role_valid`) e [`CustomerAccount`](../backend/app/Modules/Identity/Models/CustomerAccount.php) (comprador, `customers`). O e-mail é único dentro de cada tabela, não entre as duas.
  - Um guard de sessão por área em [`config/auth.php`](../backend/config/auth.php) (`customer` e `staff`), nenhuma rota com `auth:sanctum`. Uma sessão de uma área recebe `401` na outra.
  - `orders.customer_id` aponta para `customers` (`restrict`). O [`Customer`](../backend/app/Modules/Ordering/Models/Customer.php) do Ordering virou projeção de `customers`, e o `ModuleBoundariesTest` proíbe o `User` no Ordering, no Payment e no Fulfillment.
  - Todo `DELETE` de `/api/admin/*` e a gestão da equipe (`/api/admin/users`) só para o papel `admin`, no grupo com o middleware [`EnsureUserIsAdmin`](../backend/app/Modules/Identity/Http/Middleware/EnsureUserIsAdmin.php). A tela "Clientes" foi para `/api/admin/customers`.
- **Decisões:**
  - **`CustomerAccount` no Identity, e não no Customers:** a `OrderPolicy` do Ordering dependeria do Customers, que já depende do Ordering (`OrderRepository`), e isso criaria um ciclo.
  - **Sem `delete` por policy:** a regra "só o admin remove" fica no grupo de rotas, e um teste percorre a tabela de rotas, para que uma rota nova não libere o suporte em silêncio.
  - **O contrato da API mudou** (único consumidor: o frontend do repositório, no mesmo pull request): `role` saiu das respostas da loja, `GET /api/account` devolve `data.customer`, e a tela de clientes passou para `/api/admin/customers`.
  - **Sem migração de dados:** o banco é recriado por `make fresh`.
- **Testes:** `Auth/AuthTest`, `Auth/StaffAuthTest`, `AuthorizationTest`, `Admin/CustomerTest`, `Admin/StaffTest`, `Admin/StaffRoleTest`, `Middleware/EnsureUserIsAdminTest`, `Models/CustomerAccountTest`, `UseCases/User/StaffUseCasesTest` e o `ModuleBoundariesTest`.

#### 10. ✅ Resolvido: o pedido não tinha destino, e o Customers e o Fulfillment não tinham dados nem regras próprios

- **Onde estava:** `POST /api/orders` recebia só os itens, `orders.total_cents` era a soma das linhas, e a entrega era um job que esperava `ORDER_DELIVERY_DELAY_SECONDS` e anunciava "entregue" para qualquer cliente. O Customers só tinha as telas da conta, e o Fulfillment, o job.
- **Problema:** o cliente não tinha onde guardar um endereço, a loja não cobrava frete e ninguém dizia quando o pedido chegava. Os dois contextos ficavam em 7/10 pela mesma lacuna: sem dados próprios (Customers) e sem regra própria (Fulfillment).
- **Solução aplicada** (feature `addresses-and-shipping`, [design](../.design/addresses-and-shipping.md)):
  - **Customers:** o caderno de endereços ([`CustomerAddress`](../backend/app/Modules/Customers/Models/CustomerAddress.php), `/api/account/addresses`, até 10 por cliente).
  - **Fulfillment:** a tabela de frete por UF ([`ShippingRateTable`](../backend/app/Modules/Fulfillment/Services/ShippingRateTable.php)), o calendário de dias úteis ([`DeliveryCalendar`](../backend/app/Modules/Fulfillment/Services/DeliveryCalendar.php)) e o evento [`DeliveryScheduled`](../backend/app/Modules/Fulfillment/Events/DeliveryScheduled.php), publicado ao agendar a entrega com a data prevista.
  - **Ordering:** o [`PlaceOrderUseCase`](../backend/app/Modules/Ordering/UseCases/PlaceOrderUseCase.php) recebe o `address_id`, pede a cópia do endereço pelo contrato [`DeliveryAddressBook`](../backend/app/Modules/Ordering/Contracts/DeliveryAddressBook.php) e o frete pelo [`ShippingQuoter`](../backend/app/Modules/Ordering/Contracts/ShippingQuoter.php), e grava o pedido com a cópia, o frete, o prazo e o total (itens + frete). O listener [`RecordEstimatedDelivery`](../backend/app/Modules/Ordering/Listeners/RecordEstimatedDelivery.php) grava a data prevista, só a primeira, sem mexer no status.
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
  - **A facade de um módulo é o seu `Contracts` mais os seus `Events`.** Contratos por papel, só para travessias que existem: [`ProductCatalog`](../backend/app/Modules/Catalog/Contracts/ProductCatalog.php), [`StockLevels`](../backend/app/Modules/Inventory/Contracts/StockLevels.php), [`PayableOrders`](../backend/app/Modules/Ordering/Contracts/PayableOrders.php), [`CustomerOrderHistory`](../backend/app/Modules/Ordering/Contracts/CustomerOrderHistory.php) e [`CustomerAccounts`](../backend/app/Modules/Identity/Contracts/CustomerAccounts.php), cada um implementado por um repositório do módulo dono e ligado no service provider dele (o Catalog e o Identity ganharam o seu).
  - **Contratos e eventos falam em dados.** O `StockInitializer` e o `StockReservation` passaram a receber ids e a devolver `StockQuantities`. Os cinco eventos e o job `DeliverOrder` carregam o `orderId` (e o `OrderPaid`, os dias úteis prometidos), e um teste de reflexão (`OrderEventPayloadTest`) cobra isso.
  - **O checkout continua numa transação só**, com o bloqueio das linhas de estoque em ordem de `product_id`, agora pelos contratos.
  - **Fora do Identity, o cliente autenticado é um id**: as policies recebem o `Authenticatable` do framework, e o `{order}` do pagamento e o `{customer}` do admin de clientes são resolvidos pelo contrato do dono, com `404` para id inexistente.
  - **Duas respostas da API ficaram mais estreitas**: o `202` do pagamento é a tentativa de pagamento, e os pedidos recentes da conta e do admin de clientes vêm resumidos (`id`, `status`, `status_label`, `total_cents`, `created_at`).
- **Fronteira verificada:** o [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) virou uma lista do que é permitido. Ele gera uma expectativa por pasta privada de cada módulo, a partir das pastas que existem, mais uma por classe da raiz de cada módulo. Também confere que todo `Contracts` só tem interfaces e que nenhuma assinatura de contrato usa model ou coleção do Eloquent. As regras de direção de antes continuam. Quatro violações de propósito (um model do Ordering no Payment, o service provider do Payment no Ordering, uma classe concreta em `Contracts` e um contrato devolvendo `Product`) fizeram o teste falhar, cada uma na sua regra.
- **Exceções declaradas, uma por travessia:** o Backoffice lendo os repositories de quatro módulos, e a vitrine e a lista de estoque do admin com `Product::stock`, `Stock::product` e a regra de disponibilidade do Ordering. Todas apontam para a HEL-6. `DTOs`, `ValueObjects`, `Enums` e `Http` de outro módulo continuam permitidos até a HEL-10.
- **Desvio do plano:** o pedido do pagamento é resolvido por um binding explícito da rota (`{payableOrder}`), pelo contrato `PayableOrders`, e não dentro do controller. Assim ele roda antes da validação do corpo, como fazia o route model binding, e um id inexistente continua `404` mesmo com corpo inválido, como exige o `MoneyInCentsTest`.
- **Ajustes da revisão do PR #12** (09/10/2026):
  - a regra "a senha só muda quando uma nova é enviada" saiu do `UserService`, que foi apagado, para o [`UpdateUserProfileDTO::toArray()`](../backend/app/Modules/Identity/DTOs/UpdateUserProfileDTO.php), ao lado do `CreateUserDTO::toArray()`. O `CustomerAccountRepository` deixou de depender de um service, e o `ModuleBoundariesTest` ganhou a regra `repositories do not use the module services` (uma expectativa por módulo com as duas pastas; uma violação de propósito no Ordering fez o teste falhar);
  - a conta de cliente tem um único formato na API, o [`CustomerProfileResource`](../backend/app/Modules/Identity/Http/Resources/CustomerProfileResource.php) do Identity, alimentado pelo `CustomerAccount::toProfile()`. O `CustomerAccountResource` e a cópia do resource no Customers saíram;
  - o `ProductRepository::findManyKeyedById` foi inlinado no `findMany` do `ProductCatalog`, seu único chamador, e o parâmetro `$withStock`, que ninguém passava, saiu;
  - um deploy que muda o formato de um evento ou job precisa esvaziar a fila antes, porque o `queue-restart` não converte os payloads que já estão nela (ver o [README](../README.md#eventos-listeners-jobs-e-filas)).

### Prioridade baixa

#### 7. ✅ Resolvido: Catalog conhecia pedidos para bloquear a exclusão

- **Onde estava:** `ProductRepository::hasOrderItems()` e a relação `Product::orderItems()`, que faziam o model do Catalog importar o `OrderItem` do Ordering.
- **Solução aplicada:** o [`DeleteProductUseCase`](../backend/app/Modules/Catalog/UseCases/DeleteProductUseCase.php) pergunta pelo contrato [`ProductOrderHistory::hasBeenOrdered()`](../backend/app/Modules/Catalog/Contracts/ProductOrderHistory.php).
  - O contrato é **definido pelo Catalog** e **implementado pelo Ordering** ([`OrderRepository`](../backend/app/Modules/Ordering/Repositories/OrderRepository.php)), com a ligação feita no [`OrderingServiceProvider`](../backend/app/Modules/Ordering/OrderingServiceProvider.php).
  - A relação `Product::orderItems()` foi removida.
  - A chave estrangeira `order_items.product_id` com `restrictOnDelete` continua como última linha de defesa no banco.
- **Desvio da recomendação original:** a análise sugeria uma interface "implementada pelo Ordering" sem dizer onde ela fica. Se ficasse no Ordering, o Catalog passaria a depender dele, e o Ordering já depende do Catalog (usa `Product` no checkout). Os dois módulos ficariam dependentes um do outro. Com o contrato no Catalog (inversão de dependência), a dependência continua só do Ordering para o Catalog.
- **Fronteira verificada:** o `ModuleBoundariesTest` falha se o Catalog usar models ou repositories do Ordering. Isso foi conferido recolocando a relação `orderItems()` de propósito.

#### 8. ✅ Resolvido: a estrutura do código não refletia os contextos

- **Onde estava:** todo o backend, organizado em camadas técnicas (`app/Models`, `app/Services`, `app/UseCases`...).
- **Problema:** contrariava a diretriz de organização modular por domínio (`app/Modules/<Domain>`) do `AGENTS.md`. Além disso, as fronteiras dos passos 2 a 4 só existiam por convenção.
- **Solução aplicada:** as 118 classes foram para 9 módulos em `backend/app/Modules`, **um módulo por vez** (Inventory primeiro, Shared por último), com a suíte rodando entre os módulos. Para Ordering, Payment, Fulfillment e Identity, a checagem só foi confirmada ao final desse lote. Fora dos módulos ficou só o `AppServiceProvider`, com as configurações globais. Ver a [estrutura modular adotada](#estrutura-modular-adotada).
- **Como foi feito:**
  - **Preparação, sem mudar comportamento:** models e factories passaram a se declarar por `#[UseFactory]` / `protected $model`, e as policies por `#[UsePolicy]`. A descoberta de listeners passou a olhar `app/Modules/*/Listeners`, e as rotas passaram a importar cada controller pelo nome completo. Tudo isso dependia antes da convenção `App\Models` / `App\Listeners`.
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
backend/app/Modules/
  Ordering/      Cart, Checkout, Order, OrderItem, OrderPlaced, OrderPaid, DeliveryAddressBook, ShippingQuoter, PayableOrders, CustomerOrderHistory ← Core
  Catalog/       Product, Category, ProductStatus, ProductCatalog
  Inventory/     Stock, StockOperation, AdjustStock, StockInitializer, StockLevels, StockReservation
  Payment/       Payment, PaymentGateway, FakePaymentGateway, PayOrder, OrderPaymentPolicy, PaymentApproved
  Fulfillment/   ShippingRateTable, DeliveryCalendar, ScheduleDelivery, DeliverOrder, DeliveryScheduled, OrderDelivered
  Customers/     Account, Profile, admin customer management, CustomerAddress (address book)
  Identity/      User (staff), CustomerAccount, UserRole, Auth, Email, Password, EnsureUserIsAdmin, CustomerAccounts
  Backoffice/    Dashboard (read model)
  Shared/        ApiErrorCode, BusinessRuleException, BaseRepository...
```

A facade de cada módulo é o seu `Contracts/` mais os seus `Events/`: as outras pastas são privadas, e o `ModuleBoundariesTest` gera as regras a partir das pastas que existem (até a HEL-10, `DTOs/`, `ValueObjects/`, `Enums/` e `Http/` continuam alcançáveis). Cada módulo usa só as pastas de que precisa: `Contracts/`, `DTOs/`, `Enums/`, `Events/`, `Exceptions/`, `Http/` (`Controllers/`, `Requests/`, `Resources/`, `Middleware/`, com `Admin/` para a área administrativa), `Jobs/`, `Listeners/`, `Models/`, `Policies/`, `Repositories/`, `Services/` e `UseCases/`.

**Desvios em relação ao `AGENTS.md` do diretório pai:**

- **`UseCases/` em vez de `Actions/`:** é a convenção que o projeto já usava, descrita no README e no `AGENTS.md` deste repositório.
- **Testes em `backend/tests`, e não em `Tests/` dentro de cada módulo:** a configuração do Pest (`tests/Pest.php`) e do PHPUnit separa os testes por tipo (Unit sem banco, Feature com `RefreshDatabase`). Mover os testes para os módulos exigiria refazer essa separação sem ganho de comportamento.
- **Rotas centralizadas em `routes/api.php`:** mantém num só lugar o mapa da API e os grupos de middleware.

### Linguagem ubíqua por contexto

| Termo | Contexto | Significado |
|---|---|---|
| Produto | Catalog | Item cadastrado, com nome, preço, descrição, imagem e status |
| Disponível | Ordering | Produto ativo **e** com estoque suficiente para a quantidade pedida |
| Estoque | Inventory | Quantidade de unidades de um produto, nunca negativa |
| Produto do catálogo (`CatalogProduct`) | Catalog | O que outro módulo sabe de um produto: id, nome, imagem, preço e se está ativo |
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

---

## Plano de evolução

A ordem abaixo prioriza o que reduz mais risco com o menor esforço:

1. ✅ **Centralizar a disponibilidade** (problemas 1 e 2). Concluído em 06/10/2026 com o `PurchaseAvailabilityService`.
2. ✅ **Separar Customer de User** (problema 3). Concluído em 06/10/2026 com o modelo `Customer` e o `CustomerSummaryDTO`.
3. ✅ **Separar Payment e Fulfillment por eventos** (problema 5). Concluído em 06/10/2026 com os eventos `OrderPaid` e `OrderDelivered`.
4. ✅ **Expor interfaces do Inventory** (problemas 4 e 6). Concluído em 06/10/2026 com os contratos `StockInitializer` e `StockReservation`, sem mudar o comportamento transacional.
5. ✅ **Migrar para `app/Modules`** (problema 8). Concluído em 06/10/2026: 9 módulos, um contexto por vez, começando pelo Inventory, com as fronteiras verificadas pelo `ModuleBoundariesTest`.
6. ✅ **Separar a conta da equipe da conta do cliente** (problema 9). Concluído em 07/10/2026: tabelas `users` e `customers`, guards `staff` e `customer`, papéis `admin` e `support`, e o `DELETE` do admin só para o papel `admin`.

Depois do plano:

- ✅ **Facades dos módulos** (problema 11, HEL-7). Concluído em 08/10/2026: cinco contratos novos, os contratos do Inventory e os eventos falando em dados, e o `ModuleBoundariesTest` como lista do que é permitido, com as exceções da HEL-6 declaradas. Ver o [design](../.design/module-facades.md).
- ✅ **Endereços de entrega e frete** (problema 10). Concluído em 07/10/2026: o caderno de endereços no Customers, a tabela de frete e a data prevista no Fulfillment, a cópia do endereço no pedido e os contratos `DeliveryAddressBook` e `ShippingQuoter`, com as regras novas no `ModuleBoundariesTest`. Ver o [design](../.design/addresses-and-shipping.md).
- ✅ **Modelo `Payment` próprio e gateway fake atrás de uma porta** (recomendação da matriz Ordering × Payment). Concluído em 06/10/2026: tabela `payments`, porta `PaymentGateway` ligada ao `FakePaymentGateway` e recusa com `402`, com as regras da porta verificadas pelo `ModuleBoundariesTest`. Ver o [design](../.design/fake-payment-gateway.md).

## Pendências depois do plano

Os 5 passos organizaram o código e tornaram as fronteiras explícitas e verificadas, mas os módulos **continuam dividindo o mesmo banco**. O que ainda liga um contexto aos dados de outro:

| Pendência | Onde | Por que ficou |
|---|---|---|
| Leituras do estoque pela relação `Product::stock()` | Vitrine e cadastro do admin (`ProductResource`), lista de estoque (`Stock::product`) | São o read model da vitrine; o carrinho e o checkout já leem pelo Inventory. Exceção declarada no `ModuleBoundariesTest` até a HEL-6 |
| Estoque excluído por `cascadeOnDelete` | FK `stocks.product_id` | É a regra 1:1 garantida pelo banco |
| Catalog e Inventory usam uma regra do Ordering na vitrine e na lista de estoque (achado ao resolver o problema 7) | `ProductResource`, `ProductController` e `StockResource` usam o `PurchaseAvailabilityService` | Gera dependência do Catalog para o Ordering, que por sua vez depende do Catalog. A regra de disponibilidade foi para o Ordering no passo 1 e agora recebe dados. Exceção declarada até a HEL-6, que decide onde a regra mora numa leitura que junta Catalog e Inventory |
| Tentativas de pagamento presas ao pedido por `restrictOnDelete` | FK `payments.order_id` | O Payment grava na própria tabela, mas referencia `orders`; pedidos nunca são apagados, então a FK só protege o histórico |
| ✅ Resolvida: eventos carregavam o model `Order` | `OrderPlaced`, `PaymentApproved`, `OrderPaid`, `OrderDelivered`, `DeliveryScheduled`, `DeliverOrder` | Resolvida no problema 11: carregam o `orderId` e os valores de que o consumidor precisa. O envelope e o outbox ficam para a HEL-9 |
| Dashboard lê os repositories de vários módulos | `GetAdminDashboardUseCase` | É um read model; a dependência é só de leitura. Exceção declarada no `ModuleBoundariesTest` até a HEL-6 |
| Um módulo usa `Http`, `ValueObjects`, `Enums` e `DTOs` de outro | Por exemplo, o Customers usa as regras de e-mail e senha do Identity e o `BrazilianState` do Ordering | Segunda rodada das fronteiras, na HEL-10: decidir qual vocabulário é público e o que cada módulo deve ter próprio |

> As classificações e fronteiras desta análise foram derivadas do código. Elas devem ser validadas com quem conhece o negócio antes de qualquer refatoração estrutural.
