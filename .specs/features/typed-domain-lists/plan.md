# Listas de domínio tipadas

## Problem

As listas do carrinho, do checkout, do catálogo e da contagem de pedidos atravessam requests, use cases, services, repositórios e o contrato `StockReservation` como `array`. O formato delas só existe em docblocks:

- `CartDTO::quantitiesByProduct()` devolve `array<int, int>`, que vai para o `PlaceOrderUseCase`, o `ValidateCartUseCase`, o `CartValidationService` e os dois métodos do `CheckoutService`;
- `CheckoutService::buildOrderLines()` devolve `array{total_cents, lines: list<array<string, mixed>>}`. O total é somado ao lado das linhas, e nada impede que ele divirja delas;
- `CartValidationService::validate()` devolve `array{items, total_cents, is_valid}`, e o `total_cents` e o `is_valid` também são acumulados ao lado das linhas;
- `StockReservation::lockForProducts()`, `ProductRepository::findManyKeyedById()` e `syncCategories()`, e o `ProductDTO::$categoryIds`, recebem `list<int>`;
- `OrderRepository::countPerCustomer()` recebe `list<int>` e devolve `array<int, int>`. Quem chama precisa lembrar que cliente sem pedidos fica fora do array e usar `?? 0`.

Um elemento do tipo errado, um total que não bate com as linhas ou um id inválido só aparecem em tempo de execução, longe de onde nasceram. Não há defeito registrado: a fonte é a discovery [.design/value-objects-and-typed-lists.md](../../../.design/value-objects-and-typed-lists.md), que registrou a atividade como já decidida e fixou o alcance nas listas de domínio que atravessam camadas.

Quando isso estiver entregue, cada uma dessas listas é uma classe que garante o tipo dos elementos e calcula os próprios totais, e a API responde exatamente como hoje.

## Flow

Reaproveita os requests, os use cases, os services, os repositórios, o contrato `StockReservation`, o `PurchaseAvailabilityService` e o error bag do `InsufficientStockException`. Nenhuma regra de carrinho, estoque ou pedido muda: só os tipos que as carregam (door 1).

1. in: `POST /api/cart/validate` e `POST /api/orders` -> `ValidateCartRequest` / `StoreOrderRequest` (exist) -> `CartDTO` (exists), agora a lista tipada de `CartItemDTO` (door 1)
2. `ValidateCartUseCase` / `PlaceOrderUseCase` (exist) - pedem ao `CartDTO` um `ProductQuantities` (door 1) e dele um `ProductIds` (door 1), entregue ao `ProductRepository` (exists) e ao `StockReservation` (exists, door 2)
3. `CartValidationService` (exists) - recebe `ProductQuantities` e devolve `ValidatedCart` de `ValidatedCartLine` (door 1), que calcula `total_cents` e `is_valid`. Um Resource converte isso no JSON atual
4. `CheckoutService` (exists) - recebe `ProductQuantities` e devolve `OrderLines` de `OrderLine` (door 1), que calcula o total. O `OrderRepository` (exists) converte as linhas para o Eloquent e grava `orders` e `order_items`
5. in: `POST /api/admin/products` e `PUT /api/admin/products/{product}` -> `ProductRequest` (exists) -> `ProductDTO` (exists) com `CategoryIds` (door 1) -> `ProductRepository` (exists), que sincroniza as categorias
6. in: `GET /api/admin/users` -> `ListCustomersUseCase` (exists) -> `OrderRepository` (exists) recebe `CustomerIds` e devolve `OrderCountsByCustomer` (door 1), que responde zero para cliente sem pedidos
7. out: as mesmas respostas JSON de hoje. O `array` só aparece no repositório (`whereIn`, `sync`, `createMany`) e no Resource

## Impact

| Front | What changes |
| --- | --- |
| domain | `CartDTO` era um DTO com `public array $items`. Passa a ser a lista tipada de `CartItemDTO`. Quem lê hoje: `ValidateCartRequest` (e `StoreOrderRequest` por herança), `ValidateCartUseCase`, `PlaceOrderUseCase`, `CartValidationService` e `RequestToDtoTest` |
| domain | novo termo `ProductQuantities`: quantidade total por produto, uma entrada por `product_id`, em ordem crescente de id. Substitui o `array<int, int>` de `quantitiesByProduct()`. Quem lê hoje o array: `PlaceOrderUseCase`, `ValidateCartUseCase`, `CartValidationService`, `CheckoutService` e `CheckoutServiceTest` |
| domain | novo termo `ProductIds` (Catalog): ids de produto positivos e sem repetição. Quem lê hoje `list<int>`: `StockReservation`/`StockRepository`, `ProductRepository`, `StockRepositoryTest` e `ProductRepositoryTest` |
| domain | novos termos `OrderLines`/`OrderLine` (Ordering): as linhas de um pedido novo, com subtotal e total derivados. Substituem o retorno de `buildOrderLines()` e o `list<array>` de `OrderRepository::createWithItems()`. Quem lê hoje: `PlaceOrderUseCase`, `OrderRepositoryTest` e `CheckoutServiceTest` |
| domain | novos termos `ValidatedCart`/`ValidatedCartLine` (Ordering): o resultado da validação do carrinho, com `total_cents` e `is_valid` derivados das linhas. Quem lê hoje: `ValidateCartUseCase`, `CartController` e `CartValidationTest` |
| domain | novo termo `CategoryIds` (Catalog): ids de categoria positivos e sem repetição. Quem lê hoje: `ProductRequest`, `CreateProductUseCase`, `UpdateProductUseCase`, `ProductRepository`, `ProductUseCasesTest`, `ProductRepositoryTest` e `RequestToDtoTest` |
| domain | novos termos `CustomerIds`/`OrderCountsByCustomer` (Ordering). Quem lê hoje: `ListCustomersUseCase` e `OrderRepositoryTest` |
| contract | `StockReservation::lockForProducts()` muda de `array` para `ProductIds` (door 2). A única implementação é o `StockRepository`, e o único consumidor é o `PlaceOrderUseCase` |
| stored data | nothing to migrate - nenhuma coluna, tabela ou valor gravado muda |
| tests | os testes que hoje montam ou comparam arrays passam a usar os novos tipos, com os mesmos valores esperados |
| docs | README: a camada `ValueObjects` (se o plano de e-mail e senha ainda não tiver chegado) e o trecho do `PlaceOrderUseCase` em "Transação e concorrência de estoque". A análise de domínio corrige a nota que chama `CartDTO`/`CartItemDTO` de value objects |

## Relations

None - no stored-data shape change.

## Surface

None - nenhuma rota muda de assinatura. `POST /api/cart/validate`, `POST /api/orders`, `POST|PUT /api/admin/products` e `GET /api/admin/users` mantêm entrada, chaves de saída e códigos (critérios 4, 5, 14 e 17).

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| 1. padrão de lista tipada (precedente que os próximos módulos copiam) | `final readonly class <Name> implements IteratorAggregate, Countable`, construída com parâmetro variádico tipado (`CartItemDTO ...$items`), sem métodos que alterem a lista. Totais e flags são calculados a partir dos elementos. Mora na camada `ValueObjects` do módulo dono: `Catalog\ValueObjects\ProductIds` e `CategoryIds`; `Ordering\ValueObjects\ProductQuantities`, `OrderLine`, `OrderLines`, `ValidatedCartLine`, `ValidatedCart`, `CustomerIds` e `OrderCountsByCustomer`. O `CartDTO` continua em `Ordering\DTOs` | estender `Illuminate\Support\Collection`: aceita qualquer tipo em `push`/`put` e é mutável, então o tipo não fica garantido. Classe base genérica no Shared: abstração prematura para nove classes, e o Shared é só infraestrutura |
| 2. assinatura do contrato entre módulos | `StockReservation::lockForProducts(ProductIds $productIds): Collection`, com o `ProductIds` do Catalog. O Inventory já depende do Catalog (`StockInitializer` e `Stock`), então não nasce dependência nova | manter `array` no contrato: é justamente na fronteira entre módulos que uma lista sem tipo custa mais. `ProductIds` no Inventory: o id de produto é identidade do Catalog, e o Ordering teria de importar um tipo do Inventory para falar com o Catalog |

- Nothing else in this change is hard to reverse

## Criteria

### S1: Carrinho e checkout tipados (P1)

O carrinho e o pedido são calculados pelas listas tipadas, e as respostas não mudam.

**Acceptance Criteria**

1. WHEN `POST /api/cart/validate` is sent with items `[{A, 2}, {A, 3}, {B, 1}]` for active, in-stock products `A < B` THEN the system SHALL return `200` with `data.items` holding two lines in ascending `product_id`, line `A` with `quantity: 5`, `data.total_cents` equal to `5 × A.price_cents + B.price_cents` and `data.is_valid: true`
2. IF a cart line references the nonexistent product `999999` THEN its line SHALL have `unit_price_cents: null`, `subtotal_cents: null`, `available_quantity: 0`, `is_available: false` and `problem: "Produto não encontrado."`, SHALL be excluded from `total_cents`, and `data.is_valid` SHALL be `false`
3. IF a cart line references a product with stock `0` THEN its `subtotal_cents` SHALL be `unit_price_cents × quantity`, it SHALL be excluded from `total_cents`, and `data.is_valid` SHALL be `false`
4. The response of `POST /api/cart/validate` SHALL have exactly the keys `items`, `total_cents` and `is_valid` under `data`, and each item exactly `product_id`, `name`, `image_url`, `unit_price_cents`, `quantity`, `subtotal_cents`, `available_quantity`, `is_available` and `problem`
5. WHEN `POST /api/orders` succeeds THEN the system SHALL return `201` and the stored `orders.total_cents` SHALL equal the sum of its `order_items.subtotal_cents`, each equal to `unit_price_cents × quantity`
6. IF `POST /api/orders` asks for more units than the stock holds THEN the system SHALL return `409` with errors under `items.{product_id}`, create no order and leave `stocks.quantity` unchanged
7. WHEN `CartDTO` holds items `(7, 1)`, `(3, 1)` and `(7, 5)` THEN its `ProductQuantities` SHALL iterate `3 => 1`, `7 => 6` in that order and its `ProductIds` SHALL be `[3, 7]`
8. WHEN `ValidatedCart` holds a line without a problem with subtotal `2000` and a line with a problem and subtotal `500` THEN it SHALL report `total_cents` `2000` and `is_valid` `false`
9. WHEN `ValidatedCart` holds no lines THEN it SHALL report `total_cents` `0` and `is_valid` `false`
10. WHEN an `OrderLine` has `unit_price_cents` `19990` and `quantity` `3` THEN its subtotal SHALL be `59970`, and an `OrderLines` with it and a line of `1000 × 2` SHALL report a total of `61970`
11. IF `CartDTO`, `OrderLines` or `ValidatedCart` is built with an element of another type THEN PHP SHALL throw `TypeError`
12. WHEN `StockReservation::lockForProducts` receives `ProductIds` `[second, first]` inside a transaction THEN it SHALL return the stocks keyed by `product_id` in ascending `product_id` order

**Independent test:** validar um carrinho com uma linha repetida e um produto inexistente, depois fechar um pedido e comparar `orders.total_cents` com a soma dos itens.

### S2: Ids de categoria e de produto tipados (P1)

O catálogo recebe e sincroniza categorias e busca produtos por listas de ids que garantem o próprio formato.

**Acceptance Criteria**

13. IF `ProductIds`, `CategoryIds` or `CustomerIds` is built with `0`, a negative id or a repeated id THEN it SHALL throw `InvalidArgumentException`
14. WHEN an admin sends `POST /api/admin/products` with `category_ids: [c1, c2]` THEN the system SHALL return `201` and the product SHALL belong to exactly `c1` and `c2`
15. WHEN an admin sends `PUT /api/admin/products/{product}` with `category_ids: [c2, c3]` for a product in `c1` and `c2` THEN the product SHALL belong to exactly `c2` and `c3`
16. WHEN `ProductRepository::findManyKeyedById` receives `ProductIds` `[second, first, 999999]` THEN it SHALL return the two existing products keyed by id

**Independent test:** criar um produto com duas categorias e trocar uma delas na edição.

### S3: Contagem de pedidos por cliente (P2)

A listagem de clientes recebe as contagens em uma classe que responde zero para quem não tem pedidos.

**Acceptance Criteria**

17. WHEN an admin sends `GET /api/admin/users` and the page holds a customer with 2 orders and one with none THEN the system SHALL return `200` with `orders_count` `2` and `0` for them
18. WHEN `OrderCountsByCustomer` is asked for a customer id that has no orders THEN it SHALL return `0`
19. WHEN `GET /api/admin/users` lists a page of 15 accounts THEN the system SHALL read the order counts in exactly one query on `orders`

**Independent test:** listar clientes com e sem pedidos no admin.

### S4: Nenhum array nas fronteiras de domínio (P2)

As assinaturas do alcance deixam de aceitar ou devolver `array` para essas listas.

**Acceptance Criteria**

20. The public methods of `CartDTO`, `CartValidationService`, `CheckoutService`, `ValidateCartUseCase`, `PlaceOrderUseCase`, `ListCustomersUseCase` and `StockReservation`, the constructor of `ProductDTO`, and `ProductRepository::findManyKeyedById`, `ProductRepository::syncCategories`, `OrderRepository::countPerCustomer` and `OrderRepository::createWithItems` SHALL declare no `array` parameter or return type
21. The list classes of door 1 SHALL be `final` and `readonly` and implement `IteratorAggregate` and `Countable`, except `OrderLine` and `ValidatedCartLine`, which are `final` and `readonly` elements

**Independent test:** o teste de arquitetura falha ao devolver `array` em `CheckoutService::buildOrderLines`.

## Out of scope

| Excluded | Why |
| --- | --- |
| dashboard (`cards`, `orders_per_day`, `orders_per_month`) e timeline do `OrderResource` | estruturas de apresentação, fora do alcance escolhido na discovery |
| `rules()`, `casts()`, `messages()`, `toArray()` dos Resources, atributos de `BaseRepository::create/update`, error bags e `$bindings` | fronteiras em que o Laravel exige `array` |
| retornos `Collection` do Eloquent nos repositórios | já são classes |
| mudar nomes, ordem ou formato das respostas | o contrato da API não muda (discovery, Key decision 7) |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| id repetido em `ProductIds`/`CategoryIds`/`CustomerIds` | recusado com `InvalidArgumentException`, não removido em silêncio | o `ProductRequest` já exige `distinct` e o `ProductQuantities` já agrupa por produto, então uma repetição só chega por bug | n |
| ordem das linhas e das quantidades | crescente por `product_id`, como o `ksort` atual | mantém a ordem dos itens na resposta. O lock não depende dela, porque o `StockRepository` ordena por `product_id` | n |
| `createWithItems` e o total do pedido | o repositório recebe `OrderLines` e grava o total que elas calculam, sem um array de atributos com `total_cents` | é o único jeito de o total não poder divergir das linhas (discovery, Key decision 5) | n |
| quem cria a camada `ValueObjects` e a documenta no README | o primeiro dos dois pull requests a chegar | os dois planos são independentes | y |

**Open questions:** none - all resolved or logged above.

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| API `POST /api/cart/validate` | response and error shape | AC 1, 2, 3, 4 |
| API `POST /api/orders` | response and error shape | AC 5, 6 |
| API `POST /api/admin/products` · `PUT /api/admin/products/{product}` | response shape | AC 14, 15 |
| API `GET /api/admin/users` | response shape | AC 17 |
| all four routes | who may call them, rate limits | existing - `auth:sanctum`, `admin`, policies, `throttle:60,1` on the cart and `throttle:20,1` on orders, unchanged |
| all four routes | versioning | n/a - the contract does not change |
| collection `ProductQuantities` | duplicates and ordering | AC 7 |
| collections `ProductIds` · `CategoryIds` · `CustomerIds` | duplicates and invalid members | AC 13 |
| collection `ValidatedCart` | empty | AC 9 |

## Sources

- [.design/value-objects-and-typed-lists.md](../../../.design/value-objects-and-typed-lists.md) - slices Checkout lists, CategoryIds e OrderCountsByCustomer, Key decisions 1, 5, 6 e 7
