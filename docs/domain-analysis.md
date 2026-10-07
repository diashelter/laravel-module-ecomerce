# Análise de Domínio: Contextos Delimitados do Backend

> Análise estratégica (DDD) do código em [`backend/`](../backend), feita em 06/10/2026.
> Última atualização: 06/10/2026 (todos os 5 passos do plano de evolução concluídos; listas de domínio tipadas; Payment com modelo próprio e gateway fake atrás de uma porta).
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

O backend é uma API Laravel de e-commerce com catálogo, estoque, carrinho, checkout, pagamento simulado e entrega simulada. A autenticação é feita com Sanctum em modo SPA (cookie de sessão).

Quando esta análise foi feita, o código estava organizado por **camada técnica** (`app/Models`, `app/Services`, `app/UseCases`, `app/Repositories`, `app/Http`), e não por domínio. Mesmo assim, a linguagem do código mostrava **7 contextos delimitados**, um **contexto de leitura** (dashboard) e um **kernel compartilhado** de infraestrutura.

Desde o passo 5 do [plano de evolução](#plano-de-evolução), cada um deles é um módulo em `backend/app/Modules` (ver a [estrutura modular adotada](#estrutura-modular-adotada)).

| Contexto | Tipo | Coesão interna |
|---|---|---|
| Ordering / Checkout | **Core Domain** | 8/10 ✅ |
| Catalog | Supporting | 9/10 ✅ |
| Inventory | Supporting | 9/10 ✅ |
| Payment | Supporting (simulado) | 8/10 ✅ |
| Fulfillment / Delivery | Supporting (simulado) | 7/10 ⚠️ |
| Identity & Access | Generic | 8/10 ✅ |
| Customer Account | Supporting | 7/10 ⚠️ |
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
                    │  User, Role, Auth   │
                    └─────────┬───────────┘
                              │ user_id
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
 Payment → Ordering:   PaymentApproved             Fulfillment → Ordering: OrderDelivered

 Payment e Fulfillment não se conhecem: só o Ordering muda o status do pedido.

   Customer Account (Supporting) e Backoffice Reporting (read model) leem de todos.
```

### Padrões de integração atuais e recomendados

| Upstream | Downstream | Padrão recomendado |
|---|---|---|
| Catalog | Ordering | Customer/Supplier: o Catalog fornece preço, nome e status; o Ordering guarda um snapshot |
| Inventory | Ordering | Open Host Service: contrato `StockReservation` (bloqueio e débito de estoque) |
| Inventory | Catalog | Open Host Service: contrato `StockInitializer` (abertura do estoque de um produto novo) |
| Ordering | Payment | Customer/Supplier: o Payment consulta se o pedido está aguardando pagamento |
| Payment | Ordering | Evento de domínio `PaymentApproved`: o Ordering marca o pedido como pago |
| Ordering | Fulfillment | Evento de domínio `OrderPaid`: o Fulfillment agenda a entrega |
| Fulfillment | Ordering | Evento de domínio `OrderDelivered`: o Ordering encerra o pedido |
| Identity | todos | Conformist: os outros contextos referenciam apenas o `user_id` |
| todos | Backoffice Reporting | Read model, somente leitura |

---

## Contextos identificados

### 1. Ordering / Checkout: Core Domain

**Linguagem ubíqua:** carrinho, item, quantidade, checkout, pedido, total, snapshot de preço, ciclo de vida do pedido.

**Capacidade de negócio:** transformar um carrinho em um pedido válido, cobrando o preço correto e garantindo que o estoque vendido realmente existe, mesmo com compras concorrentes.

**Conceitos principais:**

- `Order` (Entity): pedido do cliente, com total e status.
- `OrderItem` (Entity): item do pedido com snapshot de nome e preço.
- `OrderStatus` (Enum): ciclo de vida `placed → awaiting_payment → payment_approved → delivered`.
- `CartDTO` / `CartItemDTO` (DTOs): o carrinho carrega apenas ids e quantidades. O `CartDTO` é a lista tipada de `CartItemDTO` e entrega um `ProductQuantities`.
- [`ValueObjects`](../backend/app/Modules/Ordering/ValueObjects/): `ProductQuantities`, `OrderLines`/`OrderLine`, `ValidatedCart`/`ValidatedCartLine`, `CustomerIds` e `OrderCountsByCustomer`, listas tipadas que calculam os próprios totais.
- `CheckoutService` (Service): valida o atendimento do pedido e monta os itens.
- `CartValidationService` (Service): revalida o carrinho antes do checkout.
- `PurchaseAvailabilityService` (Service): fonte única da regra "pode ser comprado" (produto ativo **e** com estoque).
- `Customer` (Read model): o comprador visto pelo pedido, uma projeção somente leitura de `users` (`id`, `name`, `email`).
- `PlaceOrderUseCase` (Use Case): checkout atômico.
- `ValidateCartUseCase` (Use Case): validação do carrinho, só leitura.
- `MarkOrderAsAwaitingPaymentUseCase` (Use Case): primeira transição de status.
- `OrderPlaced` (Domain Event), `OrderPolicy`, `InsufficientStockException`.

**Por que é o Core Domain:** concentra a lógica mais sofisticada e mais crítica do sistema:

- Checkout atômico, com `SELECT … FOR UPDATE` nas linhas de estoque em ordem determinística (por `product_id`) para evitar deadlocks.
- O preço sempre vem do banco, nunca do cliente.
- Os itens guardam um snapshot histórico de nome e preço.
- As transições de status são idempotentes (`OrderRepository::transitionStatus`), o que torna seguros os retries das filas.
- O evento só é disparado depois do commit (`ShouldDispatchAfterCommit`).

**Dependências:**

- → Catalog: preço, nome e status do produto.
- → Inventory: bloqueio e débito de estoque.
- → Identity: `user_id` do comprador.
- ← Payment / Fulfillment: mudanças de status do pedido.

**Contexto sugerido:** `OrderingContext`

---

### 2. Catalog: Supporting Subdomain

**Linguagem ubíqua:** produto, categoria, slug, ativo/inativo, vitrine, filtro, ordenação.

**Capacidade de negócio:** cadastrar e expor os produtos à venda, organizados em categorias.

**Conceitos principais:**

- `Product` (Entity), `Category` (Entity), `ProductStatus` (Enum).
- `ProductService`, `CategoryService` (Services): imagem padrão e regras de exclusão.
- `ProductCatalogFilterDTO`, `ProductDTO`, `CreateProductDTO`, `CategoryDTO`.
- `ProductIds` e `CategoryIds` ([`ValueObjects`](../backend/app/Modules/Catalog/ValueObjects/)): listas tipadas de ids positivos e sem repetição. O `ProductIds` é o tipo do contrato `StockReservation::lockForProducts()`.
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
- `StockRepository`: `lockForProducts`, `lockById`, `decrement`. É de uso interno do Inventory.
- Contratos publicados ([`App\Modules\Inventory\Contracts`](../backend/app/Modules/Inventory/Contracts)): `StockInitializer` (para o Catalog) e `StockReservation` (para o Ordering), implementados pelo `StockRepository`.
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
- `PaymentService`: define quais pedidos podem ser pagos (`awaiting_payment` e sem pagamento aprovado).
- [`PayOrderUseCase`](../backend/app/Modules/Payment/UseCases/PayOrderUseCase.php): cobra pela porta, grava a tentativa e publica a aprovação ou lança `PaymentDeclinedException` (`402`).
- `PaymentApproved` (Domain Event), publicado pelo Payment e consumido pelo Ordering.
- `PaymentController` e `PayOrderRequest`.

**Coesão:** 8/10 ✅. Subiu de 7/10: o Payment passou a ter modelo próprio (`Payment`, tabela `payments`) e a sua regra de "pode ser pago" olha os próprios registros. Continua só publicando `PaymentApproved`, sem mexer no status do pedido. O que ainda pesa é ler o model `Order` do Ordering para saber o status e o total.

**Observação:** a comunicação com o gateway já fica atrás da porta `PaymentGateway`, que é o lugar da Anti-Corruption Layer: um gateway real será um adaptador novo que traduz os códigos dele para `PaymentStatus` e `DeclineReason`. A cobrança é síncrona; um gateway que confirme depois (Pix, webhook) exigirá um estado pendente ([design](../.design/fake-payment-gateway.md)).

**Contexto sugerido:** `PaymentContext`

---

### 5. Fulfillment / Delivery: Supporting Subdomain (simulado)

**Linguagem ubíqua:** entrega, entregue, atraso de entrega.

**Capacidade de negócio:** levar um pedido pago até o estado "entregue". Hoje é simulado por um job com atraso.

**Conceitos principais:**

- Listener `ScheduleOrderDelivery` (consome `OrderPaid`) e `ScheduleDeliveryUseCase`.
- Job `DeliverOrder` e `DeliverOrderUseCase` (transportadora fake).
- `OrderDelivered` (Domain Event), publicado pelo Fulfillment e consumido pelo Ordering.
- `config('shop.delivery_delay_seconds')`.

**Coesão:** 7/10 ⚠️. Subiu de 5/10: o Fulfillment tem namespace, job e eventos próprios e não altera o status do pedido. Ele ainda não tem estado próprio (não existe, por exemplo, uma tabela de remessas).

**Contexto sugerido:** `FulfillmentContext`

---

### 6. Identity & Access: Generic Subdomain

**Linguagem ubíqua:** usuário, login, sessão, papel (admin/cliente), permissão.

**Capacidade de negócio:** autenticar usuários e controlar o acesso às áreas pública, de cliente e administrativa.

**Conceitos principais:**

- `User` (Entity, na parte de autenticação), `UserRole` (Enum).
- `AuthController` (Sanctum SPA), `LoginCredentialsDTO`, `RegisterCustomerUseCase`.
- Middleware `EnsureUserIsAdmin`, `UserPolicy`.

- `Email` e `Password` ([`ValueObjects`](../backend/app/Modules/Identity/ValueObjects/)): as regras de e-mail (canônico, em minúsculas, até 255 caracteres) e de senha (mínimo de 8) ficam escritas uma vez e chegam aos Form Requests por `EmailRule` e `PasswordRule`. O `users.email` tem o `CHECK users_email_normalized` como última defesa.

**Coesão:** 8/10 ✅. Subiu de 6/10 depois que o `User` deixou de conhecer pedidos (problema 3). Ele agora só trata de login, senha, papel e perfil. As regras de e-mail e senha deixaram de ser copiadas nos Form Requests.

**Contexto sugerido:** `IdentityContext`

---

### 7. Customer Account / Customer Management: Supporting Subdomain

**Linguagem ubíqua:** cliente, perfil, minha conta, pedidos recentes, cadastro de clientes pelo admin.

**Capacidade de negócio:** permitir que o cliente gerencie os próprios dados e que o admin gerencie os clientes.

**Conceitos principais:**

- `AccountController`, `ProfileController`.
- `UpdateOwnProfileUseCase`, `CreateCustomerUseCase`, `UpdateCustomerUseCase`, `ListCustomersUseCase`, `ShowCustomerUseCase`.
- `CustomerSummaryDTO` / `CustomerSummaryResource`: a conta (Identity) ao lado do histórico de pedidos (Ordering).
- `UserService::profileChanges`, `Admin\UserController`.

**Observação:** este contexto **compõe** dois outros: os dados da conta vêm do Identity (`UserRepository`) e as estatísticas de pedidos vêm do Ordering (`OrderRepository`). Os dois lados são lidos separadamente e só se encontram no `CustomerSummaryDTO`.

**Coesão:** 7/10 ⚠️. Ela ainda cai porque o cadastro e a edição de clientes gravam no `User` (Identity) diretamente. Isso é aceitável enquanto não houver dados próprios de cliente (endereços, preferências etc.).

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
| Catalog | Inventory | Baixo | ✅ O estoque é criado pelo contrato `StockInitializer`; restam leituras por `Product::stock()` | Manter as leituras como read model |
| Ordering | Inventory | Alto (necessário) | ✅ O checkout usa só o contrato `StockReservation`, na mesma transação | Manter: a consistência sob concorrência exige a transação única |
| Ordering | Catalog | Médio | ✅ O snapshot de nome e preço está correto | Customer/Supplier: o Catalog publica preço e status |
| Ordering | Payment | Baixo | ✅ O Payment só publica `PaymentApproved`; o Ordering muda o status. O Payment tem modelo próprio (`Payment`) e cobra pela porta `PaymentGateway` | Manter a integração por eventos |
| Payment | Fulfillment | Nenhum | ✅ Não se conhecem: o Fulfillment reage a `OrderPaid`, publicado pelo Ordering | Manter a integração por eventos |
| Identity | Ordering | Baixo | ✅ O Ordering referencia o cliente só por id e lê nome e e-mail pelo `Customer` | Conformist: manter o `Customer` somente leitura |
| Catalog | Ordering | Baixo | ✅ A exclusão pergunta pelo contrato `ProductOrderHistory`, definido pelo Catalog e implementado pelo Ordering | Manter a inversão de dependência |

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
  - [`User`](../backend/app/Modules/Identity/Models/User.php) ficou só com identidade (login, senha, papel e perfil). O [`UserResource`](../backend/app/Modules/Identity/Http/Resources/UserResource.php) não fala mais de pedidos.
  - Novo [`Customer`](../backend/app/Modules/Ordering/Models/Customer.php): o comprador visto pelo Ordering, uma projeção somente leitura de `users` com `id`, `name` e `email`. Ele lança `LogicException` em qualquer tentativa de gravar ou excluir. `Order::user()` virou `Order::customer()`.
  - O [`OrderRepository`](../backend/app/Modules/Ordering/Repositories/OrderRepository.php) trabalha com o id do cliente (`paginateForCustomer`, `recentForCustomer`, `countForCustomer`, `countPerCustomer`, `createWithItems`), com `CustomerIds`, `OrderCountsByCustomer` e `OrderLines` no lugar de arrays, e o `PlaceOrderUseCase` recebe `int $customerId`.
  - A tela "Clientes" do admin compõe os dois lados no [`CustomerSummaryDTO`](../backend/app/Modules/Customers/DTOs/CustomerSummaryDTO.php) e no [`CustomerSummaryResource`](../backend/app/Modules/Customers/Http/Resources/CustomerSummaryResource.php), por meio do `ListCustomersUseCase` e do `ShowCustomerUseCase`. A listagem faz 2 consultas por página, sem N+1.
- **Decisões:**
  - **O contrato da API não mudou:** os endpoints `/api/admin/users`, `/api/account` e `/api/auth/me` devolvem os mesmos campos, e o frontend não precisou de ajustes.
  - **Sem escopo global de papel no `Customer`:** para o Ordering, cliente é quem fez o pedido; o papel é um conceito do Identity. A tela "Clientes" continua listando também os administradores (com 0 pedidos), como antes.
  - **A coluna continua `orders.user_id`:** renomear para `customer_id` exigiria uma migration sem ganho de comportamento. A relação `Order::customer()` declara a chave explicitamente.
- **Testes:** [`CustomerTest`](../backend/tests/Feature/Models/CustomerTest.php), além dos casos novos em `UserUseCasesTest`, `OrderRepositoryTest` e `Admin/UserTest`.

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
- **Melhoria futura:** os eventos ainda carregam o model `Order` (padrão atual do projeto, com `SerializesModels`). Se os módulos chegarem a ter persistência separada, os eventos devem carregar só o id do pedido (Published Language). Ver as [pendências](#pendências-depois-do-plano).
- **Validação:** além do `OrderStatusFlowTest`, o fluxo foi exercitado com o worker real (Redis): `MarkOrderAsPaid` → `ScheduleOrderDelivery` → `DeliverOrder` (10 s depois) → `MarkOrderAsDelivered`.

#### 6. ✅ Resolvido: checkout acessava o estoque pelo repositório

- **Onde estava:** `PlaceOrderUseCase` recebia o `StockRepository` inteiro.
- **Observação mantida:** a transação única entre os contextos é uma **escolha correta** num monólito, porque garante a consistência do estoque sob concorrência.
- **Solução aplicada:** o [`PlaceOrderUseCase`](../backend/app/Modules/Ordering/UseCases/PlaceOrderUseCase.php) depende só do contrato [`StockReservation`](../backend/app/Modules/Inventory/Contracts/StockReservation.php), com `lockForProducts()` e `decrement()`. O contrato documenta que as duas operações rodam dentro da transação de quem chama.
  - **Comportamento inalterado:** o `StockRepository` implementa os dois contratos e é ligado a eles no `InventoryServiceProvider` (`$bindings`). As consultas, a ordem dos bloqueios e o `FOR UPDATE` são exatamente os mesmos, e os testes de concorrência do `CheckoutTest` continuam passando.
- **Fronteira verificada:** o teste de arquitetura [`ModuleBoundariesTest`](../backend/tests/Unit/Architecture/ModuleBoundariesTest.php) falha se qualquer módulo além do Inventory e do read model do Backoffice usar o `StockRepository`. A falha foi conferida introduzindo uma violação de propósito.
  - **Armadilha do Pest:** com uma *lista* de namespaces, o `not->toUse` dessa versão nunca falha. Por isso o teste gera uma expectativa por namespace.

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
  Ordering/      Cart, Checkout, Order, OrderItem, OrderPlaced, OrderPaid ← Core
  Catalog/       Product, Category, ProductStatus
  Inventory/     Stock, StockOperation, AdjustStock, StockInitializer, StockReservation
  Payment/       Payment, PaymentGateway, FakePaymentGateway, PayOrder, PaymentApproved
  Fulfillment/   ScheduleDelivery, DeliverOrder, OrderDelivered
  Customers/     Account, Profile, admin customer management
  Identity/      User, UserRole, Auth, Email, Password, EnsureUserIsAdmin
  Backoffice/    Dashboard (read model)
  Shared/        ApiErrorCode, BusinessRuleException, BaseRepository...
```

Cada módulo usa só as pastas de que precisa: `Contracts/`, `DTOs/`, `Enums/`, `Events/`, `Exceptions/`, `Http/` (`Controllers/`, `Requests/`, `Resources/`, `Middleware/`, com `Admin/` para a área administrativa), `Jobs/`, `Listeners/`, `Models/`, `Policies/`, `Repositories/`, `Services/` e `UseCases/`.

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
| Pedido | Ordering | Compra confirmada, com itens congelados (snapshot) |
| Tentativa de pagamento (`Payment`) | Payment | Uma cobrança de um pedido pelo gateway, aprovada ou recusada |
| Pagamento recusado | Payment | Tentativa negada pelo gateway, com motivo; o pedido continua aguardando pagamento |
| Pagamento aprovado | Payment | Pedido que pode seguir para a entrega |
| Entregue | Fulfillment | Pedido concluído |
| Usuário | Identity | Quem se autentica, com papel `admin` ou `customer` |
| Cliente (`Customer`) | Ordering | Quem fez o pedido: identificado pelo id da conta, conhecido só por nome e e-mail |
| Resumo do cliente (`CustomerSummaryDTO`) | Customers | A conta junto com o histórico de pedidos, na tela "Clientes" do admin |

---

## Plano de evolução

A ordem abaixo prioriza o que reduz mais risco com o menor esforço:

1. ✅ **Centralizar a disponibilidade** (problemas 1 e 2). Concluído em 06/10/2026 com o `PurchaseAvailabilityService`.
2. ✅ **Separar Customer de User** (problema 3). Concluído em 06/10/2026 com o modelo `Customer` e o `CustomerSummaryDTO`.
3. ✅ **Separar Payment e Fulfillment por eventos** (problema 5). Concluído em 06/10/2026 com os eventos `OrderPaid` e `OrderDelivered`.
4. ✅ **Expor interfaces do Inventory** (problemas 4 e 6). Concluído em 06/10/2026 com os contratos `StockInitializer` e `StockReservation`, sem mudar o comportamento transacional.
5. ✅ **Migrar para `app/Modules`** (problema 8). Concluído em 06/10/2026: 9 módulos, um contexto por vez, começando pelo Inventory, com as fronteiras verificadas pelo `ModuleBoundariesTest`.

Depois do plano:

- ✅ **Modelo `Payment` próprio e gateway fake atrás de uma porta** (recomendação da matriz Ordering × Payment). Concluído em 06/10/2026: tabela `payments`, porta `PaymentGateway` ligada ao `FakePaymentGateway` e recusa com `402`, com as regras da porta verificadas pelo `ModuleBoundariesTest`. Ver o [design](../.design/fake-payment-gateway.md).

## Pendências depois do plano

Os 5 passos organizaram o código e tornaram as fronteiras explícitas e verificadas, mas os módulos **continuam dividindo o mesmo banco**. O que ainda liga um contexto aos dados de outro:

| Pendência | Onde | Por que ficou |
|---|---|---|
| Leituras do estoque pela relação `Product::stock()` | Catálogo, carrinho, `ProductResource` | São o read model da vitrine; trocar por consulta ao Inventory exigiria montar a vitrine em duas etapas |
| Estoque excluído por `cascadeOnDelete` | FK `stocks.product_id` | É a regra 1:1 garantida pelo banco |
| Catalog usa uma regra do Ordering na vitrine (achado ao resolver o problema 7) | `ProductResource` e `ProductController` usam o `PurchaseAvailabilityService` | Gera dependência do Catalog para o Ordering, que por sua vez depende do Catalog. A regra de disponibilidade foi para o Ordering no passo 1 |
| Tentativas de pagamento presas ao pedido por `restrictOnDelete` | FK `payments.order_id` | O Payment grava na própria tabela, mas referencia `orders`; pedidos nunca são apagados, então a FK só protege o histórico |
| Eventos carregam o model `Order` | `OrderPlaced`, `PaymentApproved`, `OrderPaid`, `OrderDelivered` | Padrão do projeto com `SerializesModels`; só faz diferença com persistência separada |
| Dashboard lê os repositories de vários módulos | `GetAdminDashboardUseCase` | É um read model; a dependência é só de leitura |
| Customers grava pelo `UserRepository` | `CreateCustomerUseCase`, `UpdateCustomerUseCase` | Não há dados próprios de cliente (endereço, preferências) que justifiquem um modelo separado |

> As classificações e fronteiras desta análise foram derivadas do código. Elas devem ser validadas com quem conhece o negócio antes de qualquer refatoração estrutural.
