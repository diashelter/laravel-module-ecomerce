# Lado de leitura com consultas (HEL-6)

## Problem

Hoje três leituras atravessam módulos por fora das facades, e o `ModuleBoundariesTest` as aceita como 9 exceções declaradas "até a HEL-6":

- o dashboard do admin lê os repositórios do Catalog, do Identity, do Inventory e do Ordering, e usa o `ProductStatus`, que nenhum contrato expõe (5 exceções);
- a vitrine e o cadastro de produto do admin leem o estoque pela relação `Product::stock`, e a lista de estoque do admin lê o produto por `Stock::product` (2 exceções);
- o `Http` do Catalog e o do Inventory chamam o `PurchaseAvailabilityService` do Ordering para decidir se o produto pode ser comprado, embora o Ordering dependa dos dois (2 exceções).

Contagem de 2026-10-16 na constante `BOUNDARY_EXCEPTIONS`: 9 entradas, que servem 10 rotas. Quem paga é o próprio código. Uma mudança em qualquer um dos quatro repositórios pode quebrar o dashboard. O Catalog e o Inventory não podem mudar de dono da regra sem mexer no Ordering. E uma leitura nova que junte módulos não tem um lugar para nascer, então copiaria a relação do Eloquent. A HEL-12 vai reorganizar as pastas de todos os módulos, e fazer esta rodada depois dela significaria mover as 9 exceções e reescrevê-las lá.

Quando isto estiver entregue, o `ModuleBoundariesTest` não tem exceção nenhuma. Cada uma das três leituras é uma consulta do módulo que serve a rota e pede ao dono, pelo contrato dele, o que não é seu. A regra de disponibilidade mora no Catalog. Para quem usa a loja nada muda: as rotas devolvem os mesmos códigos, corpos e mensagens, com a mesma contagem de queries.

## Flow

Reaproveita os contratos `ProductCatalog` e `StockLevels` (que só ganham forma), o `StockQuantities` como o lugar de "sem linha de estoque é zero", o `DashboardService` como o montador dos números, as transações e os locks dos casos de uso de escrita, e os geradores de regras do `ModuleBoundariesTest`.

1. `GET /api/products`, `GET /api/products/{product}`, `GET /api/admin/products` e `GET /api/admin/products/{product}` -> `Catalog` (exists): uma consulta do Catalog (door 1) lê os produtos da página e as categorias, pede ao `StockLevels` (exists, door 3) os `ProductStocks` dos ids da página numa leitura só e monta o `ProductAvailability` (door 4) de cada produto. O `ProductResource` (exists) formata o resultado
2. `POST /api/admin/products`, `PUT /api/admin/products/{product}` e `PATCH /api/admin/products/{product}/status` -> casos de uso do `Catalog` (exists) gravam na transação de hoje e devolvem um `ProductRecord` (door 5). A consulta do Catalog (door 1) completa com as categorias e o `StockLevels` sem reler o produto
3. `GET /api/admin/stocks` -> `Inventory` (exists): uma consulta do Inventory (door 1) lê a página de `stocks` pela quantidade e pede ao `ProductCatalog` (exists, door 4) os `CatalogProducts` dos produtos da página numa leitura só. O `StockResource` (exists) formata o resultado
4. `PUT /api/admin/stocks/{stock}` -> `AdjustStockUseCase` (exists): trava e grava como hoje e devolve um `StockRecord` (door 5). A consulta do Inventory (door 1) completa com o `ProductCatalog` sem reler `stocks`
5. `GET /api/admin/dashboard` -> `Backoffice` (exists): uma consulta do Backoffice (door 1) lê o `CatalogMetrics`, o `StockMetrics`, o `CustomerMetrics` e o `OrderMetrics` (door 2), cada um implementado por uma consulta do dono. O `DashboardService` (exists) monta os números
6. `POST /api/cart/validate` e `POST /api/orders` -> `Ordering` (exists): a validação do carrinho e o checkout pedem ao `CatalogProduct` o `ProductAvailability` (door 4) e só montam as mensagens. O Ordering deixa de decidir a disponibilidade
7. `make test` -> `ModuleBoundariesTest` (exists) sem `BOUNDARY_EXCEPTIONS` e com as regras das consultas (door 6)
8. out: as mesmas respostas de hoje. `Product::stock`, `Stock::product` e o `GetAdminDashboardUseCase` deixam de existir

## Impact

| Front | What changes |
| --- | --- |
| domain | termo novo "Consulta" (`Queries`): a classe de leitura do módulo que serve uma rota; devolve value objects, listas tipadas ou um `LengthAwarePaginator` deles, e nunca grava. Nasce no Catalog, no Inventory, no Identity, no Ordering e no Backoffice |
| domain | termo existente "Disponível": era do Ordering (`PurchaseAvailabilityService`) e passa a ser do Catalog (`ProductAvailability`). Quem decide com base nele hoje: `CartValidationService` e `CheckoutService` (Ordering), `ProductResource` e `ProductController` (Catalog), `StockResource` (Inventory), o `PurchaseAvailabilityServiceTest` e a linguagem ubíqua da análise de domínio. O `PurchaseAvailabilityService` fica só com as mensagens de compra; o nome da classe fica para o diff |
| domain | `CatalogProduct`: carregava `isActive` e passa a carregar o `ProductStatus` e a responder o `ProductAvailability`. Quem lê `isActive` hoje: `CartValidationService` e `CheckoutService`; quem o constrói: o `ProductRepository::findMany` e o `CheckoutServiceTest` |
| domain | `ProductStatus` entra no vocabulário público do Catalog, calculado. Quem o usa de fora: o `DashboardService` (Backoffice) e, pelo `CatalogProduct`, o `StockResource` (Inventory) |
| domain | `Product::stock` e `Stock::product` saem. Quem usa hoje: o `ProductRepository` (três eager loads), o `StockRepository::paginateByQuantity`, o `AdjustStockUseCase`, os casos de uso de criar, editar e mudar o status do produto (`load`), o `ProductResource`, o `ProductController`, o `StockResource`, o `ProductFactory::withStock` (`has`), o `StockSeeder` (`doesntHave('stock')` e `stock()->create`), o `SeederTest` (`doesntHave('stock')`) e cerca de 37 linhas de teste que leem `$product->stock` |
| domain | retorno dos casos de uso de escrita: `CreateProductUseCase`, `UpdateProductUseCase` e `ChangeProductStatusUseCase` passam a devolver `ProductRecord` no lugar do `Product`, e o `AdjustStockUseCase`, `StockRecord` no lugar do `Stock`. Quem usa: o `ProductController` e o `StockController` do admin, e o `ProductUseCasesTest`, que hoje confere `relationLoaded('categories')`, `stock->quantity` e `relationLoaded('stock')` no model devolvido |
| domain | leituras que saem dos repositórios para as consultas: `ProductRepository` (`paginateCatalog`, `paginateForAdmin`, `findWithCategoriesAndStock`, `findMany`, `countByStatus`), `StockRepository` (`paginateByQuantity`, `quantitiesFor`, `countInStock`, `totalUnits`), `OrderRepository` (`countPerDaySince`, `countPerMonthSince`) e a contagem de clientes que o dashboard pede ao `CustomerAccountRepository`. Os testes de repositório dessas leituras passam para testes das consultas, com as mesmas asserções |
| domain | `GetAdminDashboardUseCase` sai e dá lugar a uma consulta do Backoffice; o `DashboardController` passa a usá-la. O `DashboardService` passa a receber as listas tipadas das métricas no lugar das `Collection`; o `DashboardServiceTest` muda só a montagem dos dados |
| tests | `ModuleBoundariesTest`: sai a constante `BOUNDARY_EXCEPTIONS`, o código que a trata e o teste "declares exactly the HEL-6 exceptions"; o laço do `StockRepository` deixa de excluir o Backoffice; o dataset "resolves the module contracts" troca as implementações do `ProductCatalog` e do `StockLevels`. `TypedListSignaturesTest`: a linha de `ProductRepository::findMany` passa para a consulta. As contagens da HEL-11 (`StaffRecordRoutesTest`: 3, 3, 10 e 4; ajuste de estoque: 3, com uma leitura travada) não mudam |
| stored data | nothing to migrate - nenhuma tabela, coluna, índice ou chave estrangeira muda; `stocks.product_id` continua com a exclusão em cascata |
| config | os service providers do Catalog, do Inventory, do Identity e do Ordering ligam os contratos novos e os dois que mudam de implementação; nenhuma variável de ambiente nem rota muda |
| docs | README (linhas 84, 89, 111, 565, 571, 853 e 872, e a camada `Queries` ao lado de `Repositories`), `AGENTS.md` (linhas 60, 66 e 73) e análise de domínio (linhas 93, 138, 185, 313 e 314, 326, 350 a 361, 387 a 389, 420, 468, 484, 561, 610, 632 a 637, a linguagem ubíqua, um problema novo da HEL-6, o plano de evolução e a "Última atualização"). O design `.design/cqrs-read-side.md` já registra a mudança da Key decision 4 |

## Relations

None - no stored-data shape change. As relações que saem são métodos do Eloquent, não esquema: a chave estrangeira `stocks.product_id` e a cardinalidade 1:1 entre produto e estoque continuam.

## Surface

None - nothing consumed outside changes. As 10 rotas, o carrinho e o checkout mantêm entrada, saída e códigos; S2 a S5 conferem isso. A única diferença de valor fica num estado que as rotas não produzem (produto sem linha de estoque, AC 24).

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| 1. a camada de consulta (precedente para a HEL-12 e para toda leitura nova) | Pasta privada `App\Modules\<Module>\Queries`, gerada pelo `ModuleBoundariesTest` como as outras. Uma consulta declara nas assinaturas públicas só value objects, listas tipadas, escalares e `LengthAwarePaginator` (com value objects dentro), nunca `Model` nem `Illuminate\Database\Eloquent\Collection`. Não usa `App\Modules\<Module>\Repositories` nem `App\Modules\<Module>\UseCases`. Lê só as tabelas do próprio módulo; o que é de outro módulo vem pelo contrato do dono | as leituras continuarem nos repositórios: leitura e escrita seguem misturadas na mesma classe, que é o que a HEL-6 pede para separar. Um módulo `ReadModels` que junta tabelas de vários módulos num SQL só: a vitrine passaria a depender do esquema de `stocks` sem contrato |
| 2. os contratos de métricas do dashboard | `Catalog\Contracts\CatalogMetrics::productCountsByStatus(): ProductCountsByStatus` (`of(ProductStatus): int`, `total(): int`); `Inventory\Contracts\StockMetrics::totals(): StockTotals` (`productsInStock`, `totalUnits`); `Identity\Contracts\CustomerMetrics::totalCustomers(): int`; `Ordering\Contracts\OrderMetrics::totalOrders(): int`, `countPerDaySince(CarbonInterface $start): OrderCountsByPeriod` e `countPerMonthSince(CarbonInterface $start): OrderCountsByPeriod` (`of(string $period): int`, com `Y-m-d` e `Y-m`). Cada um é implementado por uma consulta do dono | a contagem de clientes num método novo do `CustomerAccounts`: é outro papel (cadastro e perfil do Customers). Devolver `Collection<string, int>`: o tipo do docblock não entra no vocabulário e o `TypedListSignaturesTest` recusa array, então a lista tipada é a forma pública |
| 3. o `StockLevels` com o id da linha de estoque | `stocksFor(ProductIds $productIds): ProductStocks`, ao lado do `quantitiesFor` de hoje; `ProductStocks::of(string $productId): ?ProductStock`, com `ProductStock(string $id, int $quantity)`. Os dois métodos passam a ser implementados por uma consulta do Inventory; o `StockInitializer` e o `StockReservation` ficam no `StockRepository` | pôr o id da linha no `StockQuantities`: ele também é o resultado do lock do checkout (`StockReservation`), que mudaria de query. Um contrato novo só para o cadastro do admin: seria um segundo papel para o mesmo dado |
| 4. a regra de disponibilidade no Catalog | `final readonly class App\Modules\Catalog\ValueObjects\ProductAvailability`, com `isAvailable(): bool` (status `Active` e unidades acima de zero) e `availableQuantity(): int` (as unidades, inclusive para o produto inativo, como hoje). `CatalogProduct(string $id, string $name, ?string $imageUrl, int $priceCents, ProductStatus $status)`, com `availability(int $unitsInStock): ProductAvailability`. O vocabulário calculado passa a conter o `ProductStatus` e o `ProductAvailability` | o `StockQuantities` decidir com o "ativo" do Catalog: o estoque decidiria uma regra de venda (escolha de diashelter na discovery). Um contrato do Ordering para a regra: o Catalog e o Inventory dependeriam de quem depende deles, e o contrato seria uma regra pura sem dado |
| 5. a escrita devolve os dados do que gravou | `CreateProductUseCase`, `UpdateProductUseCase` e `ChangeProductStatusUseCase` devolvem `App\Modules\Catalog\ValueObjects\ProductRecord` (id, nome, preço em centavos, descrição, imagem, `ProductStatus`, `created_at` e `updated_at`); `AdjustStockUseCase` devolve `App\Modules\Inventory\ValueObjects\StockRecord` (id, `product_id`, quantidade e `updated_at`). A consulta completa a resposta a partir deles, sem reler o registro | devolver só o id e reler depois do commit: soma uma query a cada escrita e quebra as contagens da HEL-11 (decisão de diashelter no planejamento, 2026-10-16). Devolver o model: é o padrão de hoje que a Key decision 1 do design proíbe |
| 6. a fronteira sem exceções | A constante `BOUNDARY_EXCEPTIONS` e todo o código que a lê saem do `ModuleBoundariesTest`. Entram, para cada módulo M com `Queries`: `arch("queries do not use the module repositories: M")->expect("App\Modules\M\Queries")->not->toUse("App\Modules\M\Repositories")`, o mesmo para `UseCases`, e um teste de reflexão "speaks only data in every query signature" no molde do teste de contratos | manter a constante vazia para o futuro: uma porta pronta convida a exceção que o `AGENTS.md` proíbe |
| 7. o retorno da consulta do dashboard (achado ao construir: o Landing 1 lista só value objects, listas tipadas, escalares e paginator, e o corpo do dashboard é um `array`) | `Backoffice\Queries\DashboardQuery::get(): array`, o corpo de `data` que o `DashboardService::build` monta. O `ModuleBoundariesTest` só proíbe model e `Illuminate\Database\Eloquent\Collection` nas assinaturas de consulta (AC 6), então o `array` passa; o `DashboardService` recebe e devolve os mesmos tipos de hoje, com as métricas em listas tipadas na entrada | um value object `Dashboard` com os cartões, o estoque e as duas séries: mudaria as asserções do `DashboardServiceTest` (que o plano manda mudar só na montagem dos dados) e a forma do corpo, sem leitor novo que o peça. Registrado depois do código, no commit dos documentos |

- Nothing else in this change is hard to reverse: os nomes das classes de consulta e dos value objects de leitura, a forma interna dos resources, o nome do helper de teste que lê o estoque de um produto e o nome do `PurchaseAvailabilityService` ficam para o diff

## Criteria

### S1: ModuleBoundariesTest (P1)

A fronteira passa a ser só `Contracts`, `Events` e o vocabulário calculado, sem nenhuma exceção, e as consultas ficam presas às suas regras.

**Acceptance Criteria**

1. The `ModuleBoundariesTest` SHALL declare no boundary exception: no `BOUNDARY_EXCEPTIONS` constant, and no expectation that skips a pair of modules or a class
2. IF a class under `App\Modules\Backoffice` uses any class under `App\Modules\Catalog\Repositories`, `App\Modules\Identity\Repositories`, `App\Modules\Inventory\Repositories` or `App\Modules\Ordering\Repositories` THEN the `ModuleBoundariesTest` SHALL fail
3. IF a class under `App\Modules\Catalog` uses `App\Modules\Inventory\Models\Stock`, or a class under `App\Modules\Inventory` uses `App\Modules\Catalog\Models\Product`, THEN the `ModuleBoundariesTest` SHALL fail
4. IF a class under `App\Modules\Catalog` or `App\Modules\Inventory` uses `App\Modules\Ordering\Services\PurchaseAvailabilityService` THEN the `ModuleBoundariesTest` SHALL fail
5. The computed public vocabulary of the Catalog SHALL contain `Catalog\Enums\ProductStatus` and `Catalog\ValueObjects\ProductAvailability`, and SHALL not contain `Catalog\ValueObjects\ProductRecord`
6. IF a public method of a class under `App\Modules\<M>\Queries` declares a native parameter or return type that is an Eloquent `Model` or an `Illuminate\Database\Eloquent\Collection` THEN the `ModuleBoundariesTest` SHALL fail with a message naming the class and the method
7. IF a class under `App\Modules\<M>\Queries` uses `App\Modules\<M>\Repositories` or `App\Modules\<M>\UseCases` THEN the `ModuleBoundariesTest` SHALL fail
8. The container SHALL resolve `ProductCatalog`, `StockLevels`, `CatalogMetrics`, `StockMetrics`, `CustomerMetrics` and `OrderMetrics` to classes under the `Queries` namespace of their owner module, and `StockInitializer` and `StockReservation` to `StockRepository`
9. WHEN a deliberate violation of each of the 9 former exceptions is introduced THEN the `ModuleBoundariesTest` SHALL fail for each, and SHALL pass again once it is removed
10. The `ModuleBoundariesTest` SHALL keep every other rule it has today: private directories, the computed vocabulary, module root classes, contracts with interfaces only, contracts speaking data, the direction rules, repositories without their module services, the password policy in the account form requests and the final readonly Identity value objects

**Independent test:** rodar o `ModuleBoundariesTest` com uma violação de cada uma das 9 travessias antigas e vê-lo falhar; depois sem ela e vê-lo passar.

### S2: ProductAvailability (P1)

A regra "pode ser comprado" é escrita uma vez, no Catalog, e o carrinho e o checkout a leem dali.

**Acceptance Criteria**

11. WHEN a `ProductAvailability` is built for status `Active` and 7 units THEN it SHALL answer `isAvailable` `true` and `availableQuantity` 7
12. WHEN a `ProductAvailability` is built for status `Inactive` and 10 units THEN it SHALL answer `isAvailable` `false` and `availableQuantity` 10
13. WHEN a `ProductAvailability` is built for status `Active` and 0 units THEN it SHALL answer `isAvailable` `false` and `availableQuantity` 0
14. The classes `ProductResource`, `ProductController`, `StockResource`, `CartValidationService` and `CheckoutService` SHALL take availability only from a `ProductAvailability`, and no class under `App\Modules` other than `ProductAvailability` SHALL combine the product status with the stock units to decide it
15. IF `POST /api/cart/validate` receives an inactive product THEN the system SHALL return `200` with that line holding `is_available` `false` and `problem` `"Produto indisponível."`
16. IF `POST /api/cart/validate` asks 4 units of an active product with 3 THEN the system SHALL return `200` with that line holding `available_quantity` 3 and `problem` `"Estoque insuficiente. Disponível: 3."`
17. IF `POST /api/orders` receives an inactive product, or more units than the stock holds, THEN the system SHALL return `409` with `errors.items.<product_id>` `["Produto indisponível."]` or `["Estoque insuficiente. Disponível: N."]` and SHALL leave the stock unchanged

**Independent test:** validar um carrinho e tentar um checkout com um produto inativo e com mais unidades que o estoque.

### S3: ProductResource (P1)

A vitrine e o cadastro de produto do admin são lidos por uma consulta do Catalog, que pede o estoque pelo `StockLevels`.

**Acceptance Criteria**

18. WHEN `GET /api/products` or `GET /api/admin/products` returns a product THEN each item SHALL hold exactly the keys `id`, `name`, `price_cents`, `description`, `image_url`, `status`, `status_label`, `is_available`, `available_quantity`, `stock`, `categories`, `created_at` and `updated_at`, in this order, with `stock` as `{id, quantity}` and each category as `{id, name, slug}`
19. WHEN `GET /api/products` is called THEN the system SHALL return `200` with active and inactive products, 12 per page (`shop.products_per_page`), with the category filter, the three sort options and the `links` and `meta` of today
20. WHEN `GET /api/products` lists a page with 1 product and a page with 12 products THEN both SHALL run the same number of queries
21. WHEN `GET /api/products/{product}` or `GET /api/admin/products/{product}` opens a product THEN the system SHALL return `200` in exactly 3 queries
22. IF `GET /api/products/{product}` opens a product that is inactive or has 0 units THEN the system SHALL return `404` with `message` `"Produto indisponível."`
23. IF `GET /api/products/{product}` or `GET /api/admin/products/{product}` receives an unknown canonical id THEN the system SHALL return `404` with `message` `"Recurso não encontrado."`
24. IF a product has no stock row THEN its item SHALL hold `stock` `null`, `is_available` `false` and `available_quantity` 0
25. WHEN `GET /api/admin/products` is called THEN the system SHALL return `200` with 15 products per page, newest first
26. WHEN `POST /api/admin/products` succeeds with `stock_quantity` 7 and two categories THEN the system SHALL return `201` with `stock.quantity` 7, `is_available` `true` and both categories
27. WHEN `PUT /api/admin/products/{product}` succeeds THEN the system SHALL return `200` with the new data and the stock unchanged, in exactly 10 queries
28. WHEN `PATCH /api/admin/products/{product}/status` succeeds THEN the system SHALL return `200` with the new status, in exactly 4 queries
29. The `CreateProductUseCase`, the `UpdateProductUseCase` and the `ChangeProductStatusUseCase` SHALL return a `ProductRecord` holding the written values, never a `Product`

**Independent test:** listar a vitrine com 1 e com 12 produtos, abrir um produto disponível e um indisponível, e criar, editar e mudar o status de um produto no admin.

### S4: StockResource (P1)

A lista de estoque do admin é lida por uma consulta do Inventory, que pede os produtos pelo `ProductCatalog`.

**Acceptance Criteria**

30. WHEN `GET /api/admin/stocks` is called THEN the system SHALL return `200` with 20 rows per page ordered by `quantity` ascending and then by `id`, each holding exactly `id`, `quantity`, `product` and `updated_at`, with `product` as `{id, name, image_url, status, status_label, is_available}`
31. WHEN `GET /api/admin/stocks` lists a page with 1 row and a page with 20 rows THEN both SHALL run the same number of queries
32. WHEN `PUT /api/admin/stocks/{stock}` increases 5 units by 10 THEN the system SHALL return `200` with `data.quantity` 15, in exactly 3 queries, of which exactly one reads `stocks`, with `for update`
33. WHEN `PUT /api/admin/stocks/{stock}` takes an active product to 0 units THEN `data.product.is_available` SHALL be `false`, and WHEN it brings it back to 1 unit THEN it SHALL be `true`
34. IF `PUT /api/admin/stocks/{stock}` would leave the stock negative THEN the system SHALL return `422` with `message` `"O estoque não pode ficar negativo."` and SHALL leave the quantity unchanged
35. IF `PUT /api/admin/stocks/{stock}` receives an unknown canonical id with a valid body THEN the system SHALL return `404`
36. IF the `ProductCatalog` returns no product for a stock row of the page THEN that row SHALL stay in the page with `product` `null`
37. The `AdjustStockUseCase` SHALL return a `StockRecord` holding the written quantity, never a `Stock`

**Independent test:** listar o estoque com 1 e com 20 linhas e ajustar uma linha para cima, para zero e para negativo.

### S5: Dashboard (P2)

O dashboard é montado por uma consulta do Backoffice a partir de quatro contratos de métricas.

**Acceptance Criteria**

38. WHEN `GET /api/admin/dashboard` runs over 2 active products (10 and 0 units), 1 inactive product (5 units), 3 customer accounts and 2 orders (today and 3 months ago) THEN the system SHALL return `200` with `data.cards` `{total_products: 3, active_products: 2, inactive_products: 1, total_stock_units: 15, total_customers: 3, total_orders: 2}` and `data.stock` `{products_in_stock: 2, products_out_of_stock: 1}`
39. WHEN `GET /api/admin/dashboard` runs THEN `data.orders_per_day` SHALL hold 30 items and `data.orders_per_month` SHALL hold 12, with the totals of the orders in each period
40. WHEN `GET /api/admin/dashboard` runs over an empty store THEN every card and both stock numbers SHALL be 0, with 30 and 12 items of `total` 0
41. The `CustomerMetrics` SHALL count customer accounts only, never staff users
42. The Backoffice SHALL read the metrics only through `CatalogMetrics`, `StockMetrics`, `CustomerMetrics` and `OrderMetrics`, and the system SHALL not declare `GetAdminDashboardUseCase`

**Independent test:** abrir o dashboard com a loja vazia e com os dados do AC 38.

### S6: Documentação (P2)

O README, o `AGENTS.md` e a análise de domínio descrevem o código como ele ficou.

**Acceptance Criteria**

43. WHEN the feature is complete THEN no line of `README.md`, `AGENTS.md` or `docs/` SHALL describe an exception "até a HEL-6", the `Product::stock` or `Stock::product` relations, the `GetAdminDashboardUseCase`, or the `PurchaseAvailabilityService` as the owner of the availability rule, as current
44. WHEN the feature is complete THEN the `AGENTS.md` SHALL say that a read contract is implemented by a query of the owner module and a contract that writes or locks by its repository, and the README SHALL describe the `Queries` layer next to `Repositories`
45. WHEN the feature is complete THEN the domain analysis SHALL mark the three HEL-6 pending items with ✅, record the HEL-6 problem with the applied solution, move "Disponível" to the Catalog in the ubiquitous language, add "Consulta", mark the step in the evolution plan with the date and update "Última atualização"

**Independent test:** buscar os nomes antigos no README, no `AGENTS.md` e em `docs/`.

## Out of scope

| Excluded | Why |
| --- | --- |
| as leituras que não cruzam módulo (pedidos, clientes, endereços, categorias, pagamento) | discovery, Boundary "Out": mudam para consultas quando forem tocadas |
| um modelo de leitura projetado pelos eventos do outbox | discovery: nenhuma leitura lenta medida, e faltam eventos de produto, estoque e cliente |
| layout de pastas, rotas e migrations por módulo, e `internachi/modular` | HEL-12 |
| testes de carga | HEL-14 |
| mudanças no frontend | os corpos não mudam |
| mover os outros contratos de leitura (`CustomerOrderHistory`, `ProductOrderHistory`, `CustomerAccounts`, `DeliveryAddressBook`, `PayableOrders`) para consultas | Key decision 3 do design: mudam quando forem tocados |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| o `isActive` do `CatalogProduct` | sai, substituído pelo `ProductStatus` e pelo `availability()`; o Ordering só precisa da disponibilidade | dois campos para o mesmo fato podem discordar; quem lê `isActive` hoje são só os dois services do Ordering | y |
| como os testes leem o estoque de um produto sem `Product::stock` | um helper no `tests/Pest.php` lê `stocks` pelo `product_id`, e as asserções continuam com os mesmos valores | os testes ficam fora dos módulos e podem usar o model `Stock` | y |
| o `ProductFactory::withStock` e o `StockSeeder` | criam a linha de `stocks` pelo `product_id`, sem a relação | `database/` fica fora dos módulos; o comportamento do seed não muda | y |
| as três asserções do `ProductUseCasesTest` sobre relações do model devolvido | passam a conferir o `ProductRecord`; categorias e estoque passam a ser conferidos na resposta da rota (AC 26 e 27) | é o contrato que a Key decision 4 do design muda; os valores esperados não mudam | y |
| o tipo da data nos contratos de pedidos | `CarbonInterface`, para o `DashboardService` continuar passando o `Illuminate\Support\Carbon` de hoje | aceita as duas formas de Carbon sem conversão | y |
| as categorias dentro da leitura do produto | uma lista tipada do Catalog com `id`, `name` e `slug`, formatada como o `CategoryResource` formata hoje (sem `products_count`, que não vem contado) | a consulta não devolve model; o corpo fica igual | y |

**Open questions:** none - all resolved or logged above.

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| API `GET /api/products`, `GET /api/admin/products` | response shape, ordering, page size | AC 18, 19, 25 |
| API `GET /api/products/{product}`, `GET /api/admin/products/{product}` | response shape and error codes | AC 18, 21, 22, 23 |
| API `POST /api/admin/products`, `PUT /api/admin/products/{product}`, `PATCH /api/admin/products/{product}/status` | response shape | AC 26, 27, 28 |
| API `POST /api/admin/products`, `PUT /api/admin/products/{product}`, `PATCH /api/admin/products/{product}/status` | error shape and codes | existing - validação `422`, id desconhecido `404` e malformado `404` sem query, cobertos pelos testes da HEL-11; nada muda |
| API `GET /api/admin/stocks` | response shape, ordering, page size | AC 30, 31 |
| API `PUT /api/admin/stocks/{stock}` | response shape and error codes | AC 32, 33, 34, 35 |
| API `GET /api/admin/dashboard` | response shape, empty state | AC 38, 39, 40 |
| API `POST /api/cart/validate`, `POST /api/orders` | error shape and codes | AC 15, 16, 17 |
| all touched routes | who may call it | existing - guard `staff` (admin e suporte) no admin, vitrine e carrinho sem sessão, checkout com `auth:customer`; nada muda |
| all touched routes | versioning, rate limits | n/a - o projeto não versiona a API, e nenhum middleware ou limite muda |
| collection lista de estoque | ordering and the row whose product is missing | AC 30, 36 |
| document README, `AGENTS.md`, análise de domínio | structure, what the reader does next | AC 43, 44, 45 |

## Sources

- [HEL-6](https://linear.app/helter/issue/HEL-6/utilizar-comand-e-query-cqrs-para-leitura-de-dados) - a decisão de usar CQRS nas leituras
- [.design/cqrs-read-side.md](../../../.design/cqrs-read-side.md) - o design confirmado em 2026-10-16: Key decisions 1 a 7 (a 4 revista no planejamento), a forma e os slices
