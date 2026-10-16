# Lado de leitura com consultas (HEL-6) - checks

Profile: standard
Plan: `.specs/features/cqrs-read-side/plan.md`

51 checks em 6 slices e o fechamento · 6 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. O Pint roda via `docker compose exec -T api ./vendor/bin/pint --test`, como no CI. As provas por `grep` rodam na raiz do repositório. Os nomes de teste novos não usam parênteses, colchetes nem barras, porque o `--filter` do Pest é uma expressão regular; nos nomes gerados que têm `\`, o filtro usa `.` no lugar da barra.

Termos usados nos checks:

- **Consulta:** classe sob `App\Modules\<Module>\Queries` (door 1).
- **Contagem de queries:** `queriesDuring()` de `tests/Pest.php`, com a sessão aberta por `actingAs` e os dados criados antes da requisição, como a HEL-11 mede.
- **Chaves de hoje do produto:** `id`, `name`, `price_cents`, `description`, `image_url`, `status`, `status_label`, `is_available`, `available_quantity`, `stock`, `categories`, `created_at`, `updated_at`, nessa ordem; `stock` com `id` e `quantity`; cada categoria com `id`, `name` e `slug`.
- **Chaves de hoje da linha de estoque:** `id`, `quantity`, `product`, `updated_at`, nessa ordem; `product` com `id`, `name`, `image_url`, `status`, `status_label` e `is_available`.
- **Estoque de um produto nos testes:** o helper novo de `tests/Pest.php` que lê `stocks` pelo `product_id` (plano, Assumptions). Substitui `$product->stock` sem mudar nenhum valor esperado.

Os checks marcados com "exercício temporário" provam uma propriedade de um teste de arquitetura com uma mudança de propósito no código. A mudança é revertida logo depois e nunca é commitada, como o C7 da feature `module-facades` e o C62 da `uuid-record-ids`. Durante o exercício, `git diff --quiet -- backend/tests` precisa sair com 0, ou seja, o teste não é editado.

Testes existentes que mudam, sem afrouxar nenhuma asserção:

- as cerca de 37 linhas que leem `$product->stock` passam a usar o helper do estoque de um produto (`CheckoutTest`, `CheckoutShippingTest`, `UuidIdsTest`, `Admin/StockTest`, `Admin/StaffRecordRoutesTest`, `Repositories/StockRepositoryTest`, `UseCases/Order/PlaceOrderUseCaseTest`, `UseCases/Product/ProductUseCasesTest`), e o `SeederTest` "seeds a complete demo environment" troca o `doesntHave('stock')` por uma contagem de produtos sem linha em `stocks`;
- os testes de repositório das leituras que saem vão para testes das consultas, com o mesmo nome e as mesmas asserções: "filters the catalog by category slug", "sorts the catalog with id as tie breaker", "includes inactive products in the catalog and eager loads relations" (que passa a se chamar "includes inactive products in the catalog with categories and stock"), "paginates the catalog with the given page size", "lists products for the admin newest first", "finds catalog products by id for other modules", "counts products by status", "paginates stocks by quantity ascending", "reads the stock quantities of the given products without locking", "counts products in stock and the total units", "returns zero units when there is no stock", "counts orders per day since a date", "counts orders per month since a date" e "counts only customer accounts";
- `ProductUseCasesTest`: as três asserções sobre relações do model devolvido (`relationLoaded('categories')`, `stock->quantity` e `relationLoaded('stock')`) passam a conferir o `ProductRecord`. Categorias e estoque passam a ser conferidos na resposta da rota (C28, C29), como as Assumptions do plano aprovaram;
- `PurchaseAvailabilityServiceTest`: "decides availability from the product status and the stock units" e "treats a missing stock row as zero units" saem, porque a regra mudou de dono. O C13 as substitui no `ProductAvailabilityTest`, com os mesmos casos. "explains why a quantity cannot be bought" fica, com os mesmos 5 casos, recebendo o `ProductAvailability`;
- `CheckoutServiceTest` e `DashboardServiceTest`: só a montagem dos dados muda (o `CatalogProduct` com `ProductStatus`; as métricas em listas tipadas);
- `ModuleBoundariesTest`: sai "declares exactly the HEL-6 exceptions"; o dataset de "resolves the module contracts to their implementations" troca as implementações do `ProductCatalog` e do `StockLevels`; o laço "reaches the inventory only through its contracts" deixa de excluir o Backoffice. `TypedListSignaturesTest`: a linha de `ProductRepository::findMany` passa para a consulta do Catalog.

## Checks

### S1 - ModuleBoundariesTest · 2 arquivos · 29 KB · ~7k

**C1** - O `ModuleBoundariesTest` não declara a constante `BOUNDARY_EXCEPTIONS`, não chama `->ignoring(` e não cita `HEL-6`; o teste novo confirma que a constante não existe em tempo de execução (AC 1, door 6)
Proof: `! grep -nE "BOUNDARY_EXCEPTIONS|->ignoring\(|HEL-6" backend/tests/Unit/Architecture/ModuleBoundariesTest.php`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares no boundary exception"`

**C2** - O teste gera, para o Backoffice, a expectativa de pasta privada contra `Catalog\Repositories`, `Identity\Repositories`, `Inventory\Repositories` e `Ordering\Repositories`, e o laço do `StockRepository` passa a incluir o Backoffice; todas passam no código entregue (AC 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="Backoffice reaches another module only through its public directories: (Catalog|Identity|Inventory|Ordering).Repositories"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="Backoffice reaches the inventory only through its contracts"`

**C3** - O teste gera a expectativa do Catalog contra `Inventory\Models` e a do Inventory contra `Catalog\Models`, por pasta inteira, sem exceção para `Stock` nem `Product`; as duas passam no código entregue (AC 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="Catalog reaches another module only through its public directories: Inventory.Models"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="Inventory reaches another module only through its public directories: Catalog.Models"`

**C4** - O teste gera a expectativa do Catalog e a do Inventory contra `Ordering\Services`, por pasta inteira; as duas passam no código entregue (AC 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="(Catalog|Inventory) reaches another module only through its public directories: Ordering.Services"`

**C5** - O vocabulário calculado do Catalog contém `ProductStatus`, `ProductAvailability`, `CatalogProduct` e `ProductCountsByStatus`, e não contém `ProductRecord`; o do Inventory contém `ProductStocks`, `ProductStock` e `StockTotals`, e não contém `StockRecord`; o do Ordering contém `OrderCountsByPeriod` (AC 5, doors 2 a 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="computes the vocabulary the read side exposes"`

**C6** - O teste de reflexão percorre os métodos públicos de toda classe sob `App\Modules\*\Queries` e falha, nomeando a classe e o método, quando um parâmetro ou retorno nativo é `Model` ou `Illuminate\Database\Eloquent\Collection`. Exercício temporário: um método público que devolve `Product` numa consulta do Catalog faz o teste falhar; revertido, ele passa (AC 6, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="speaks only data in every query signature"`

**C7** - Para cada um dos 5 módulos com `Queries` (Backoffice, Catalog, Identity, Inventory e Ordering), o teste gera uma expectativa de que `<M>\Queries` não usa `<M>\Repositories` e outra de que não usa `<M>\UseCases`, e um teste confere que a lista de módulos com `Queries` é exatamente essa (AC 7, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="queries do not use the module (repositories|use cases)"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="every module with queries is covered"`

**C8** - O container resolve `ProductCatalog`, `StockLevels`, `CatalogMetrics`, `StockMetrics`, `CustomerMetrics` e `OrderMetrics` para classes sob o namespace `Queries` do módulo dono, e `StockInitializer` e `StockReservation` para o `StockRepository` (AC 8, doors 2 e 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves the read contracts to the owner queries"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves the inventory contracts to the stock repository"`

**C9** - Exercício temporário, uma violação de cada vez, revertida em seguida: (a) o Backoffice usa o `ProductRepository`; (b) o `CustomerAccountRepository`; (c) o `StockRepository`; (d) o `OrderRepository`; (e) o `Product` do Catalog usa o `Stock`; (f) o `Stock` do Inventory usa o `Product`; (g) o `ProductResource` usa o `PurchaseAvailabilityService`; (h) o `StockResource` usa o `PurchaseAvailabilityService`. Cada uma faz a expectativa do C2, do C3 ou do C4 falhar, e todas voltam a passar sem ela. A nona antiga exceção, o `ProductStatus` no Backoffice, passa a ser permitida pelo vocabulário (C5) (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="Backoffice reaches another module only through its public directories: (Catalog|Identity|Inventory|Ordering).Repositories"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="(Catalog|Inventory) reaches another module only through its public directories: (Inventory.Models|Catalog.Models|Ordering.Services)"`

**C10** - As outras regras do `ModuleBoundariesTest` continuam e passam: pastas privadas e classes raiz geradas, vocabulário calculado, contratos só com interfaces, contratos falando em dados, regras de direção, repositórios sem os services do módulo, a política de senha nos form requests de conta e os value objects do Identity finais e readonly (AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="generates the (private directory|vocabulary) rules from the (folders|classes) that exist"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps only interfaces in every module contracts"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="speaks only data in every contract signature"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="repositories do not use the module services"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not use another module root class"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="identity value objects are final and readonly"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not write the password policy itself"`

**C11** - "declares no array in the domain list signatures" cobre `CatalogMetrics`, `StockMetrics`, `CustomerMetrics`, `OrderMetrics` e `StockLevels`, e a linha que era de `ProductRepository::findMany` passa a ser da consulta do Catalog que implementa o `ProductCatalog` (doors 2 e 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares no array in the domain list signatures"`

**C12** - Nenhum `int` chamado `id` ou terminado em `Id` aparece nos value objects novos (`ProductStock`, `ProductStocks`, `ProductRecord` e `StockRecord`) nem nas consultas (doors 3 e 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares no integer id in the modules"`

### S2 - ProductAvailability · 7 arquivos · 16 KB · ~4k

**C13** - O `ProductAvailability` responde, table-driven sobre 4 casos: `Active` com 7 unidades → disponível, 7; `Inactive` com 10 → indisponível, 10; `Active` com 0 → indisponível, 0; `Inactive` com 0 → indisponível, 0 (AC 11, 12 e 13, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="decides the availability from the status and the units"`

**C14** - Só o `ProductAvailability` decide a disponibilidade. O `CatalogProduct` tem o construtor `(string $id, string $name, ?string $imageUrl, int $priceCents, ProductStatus $status)` e nenhuma propriedade `isActive`. O Ordering não usa o `ProductStatus`. Nenhum dos leitores (o `Http` do Catalog, o Inventory e os services do Ordering) chama `isActive`. O `PurchaseAvailabilityService` não declara `isAvailable` nem `availableQuantity` (AC 14, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="carries the product status in the catalog product"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ordering takes the availability from the catalog product"`
Proof: `! grep -rnE "isActive" backend/app/Modules/Catalog/Http backend/app/Modules/Inventory backend/app/Modules/Ordering`
Proof: `! grep -nE "function (isAvailable|availableQuantity)" backend/app/Modules/Ordering/Services/PurchaseAvailabilityService.php`

**C15** - `POST /api/cart/validate` com um produto inativo de 10 unidades devolve `200` com a linha em `problem` `"Produto indisponível."`. Com um produto sem linha de estoque, devolve `available_quantity` 0, `is_available` `false` e o mesmo `problem` (AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reports problems for each item"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="treats a product without a stock row as unavailable in the cart"`

**C16** - `POST /api/cart/validate` pedindo 5 unidades de um produto ativo com 2 devolve `200` com a linha em `available_quantity` 2 e `problem` `"Estoque insuficiente. Disponível: 2."`, e o corpo mantém exatamente as chaves de hoje (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reports problems for each item"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the exact cart validation response shape"`

**C17** - `POST /api/orders` com um produto inativo ou sem unidades devolve `409` com `errors` `{"items.<id>": ["Produto indisponível."]}`. Pedindo 3 de um produto com 2, devolve `409` com `["Estoque insuficiente. Disponível: 2."]`. Nos dois casos nenhum pedido é criado e o estoque não muda (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects inactive and out of stock products"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns 409 and changes nothing when stock is insufficient"`

**C18** - As mensagens de compra, montadas sobre o `ProductAvailability`, respondem table-driven sobre 5 casos: estoque suficiente → nenhuma; 2 disponíveis para 3 pedidos → `"Estoque insuficiente. Disponível: 2."`; sem unidades, sem linha de estoque e produto inativo → `"Produto indisponível."` (AC 15 a 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="explains why a quantity cannot be bought"`

### S3 - ProductResource · 27 arquivos · 97 KB · ~24k

**C19** - Table-driven sobre as 7 rotas que devolvem produto (`GET /api/products`, `GET /api/products/{product}`, `GET /api/admin/products`, `GET /api/admin/products/{product}`, `POST /api/admin/products`, `PUT /api/admin/products/{product}` e `PATCH /api/admin/products/{product}/status`), cada produto do corpo tem exatamente as chaves de hoje, na ordem de hoje, com `stock` em `{id, quantity}` e cada categoria em `{id, name, slug}` (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="renders every product with exactly the keys of today"`

**C20** - `GET /api/products` devolve `200` com os produtos ativos e inativos e a disponibilidade de cada um, 12 por página (`shop.products_per_page`), com o filtro por categoria, as três ordenações (`name`, `price_asc` e `price_desc`) e os `links` e `meta` de hoje (AC 19)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists active and inactive products with availability"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="filters products by category slug"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="sorts products"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="paginates products"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="sorts the catalog with id as tie breaker"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="filters the catalog by category slug"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="paginates the catalog with the given page size"`

**C21** - `GET /api/products` com 1 produto e com 12 produtos, cada um com 2 categorias e uma linha de estoque, faz o mesmo número de queries (AC 20, Key decision 2 do design)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists the catalog with the same query count for 1 and 12 products"`

**C22** - As contagens da HEL-11 não mudam: `GET /api/products/{product}` com 3 queries, `GET /api/admin/products/{product}` com 3, `PUT /api/admin/products/{product}` com 10 e `PATCH /api/admin/products/{product}/status` com 4, cada uma com `200` (AC 21, 27 e 28, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the query count of the staff and showcase reads and updates"`

**C23** - `GET /api/products/{product}` de um produto inativo ou sem unidades devolve `404` com `message` `"Produto indisponível."` (AC 22)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not show unavailable products"`

**C24** - `GET /api/products/{product}` e `GET /api/admin/products/{product}` com um id canônico desconhecido devolvem `404` com o envelope `NOT_FOUND` e `message` `"Recurso não encontrado."` (AC 23)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 for an unknown id in the staff and showcase routes"`

**C25** - Um produto sem linha em `stocks`, criado direto pela factory, sai em `GET /api/products` e em `GET /api/admin/products/{product}` com `stock` `null`, `is_available` `false` e `available_quantity` 0 (AC 24)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="renders a product without a stock row as unavailable"`

**C26** - `GET /api/admin/products` devolve `200` com 15 produtos por página, do mais novo para o mais antigo, na rota e na consulta (AC 25)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists the admin products newest first fifteen per page"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists products for the admin newest first"`

**C27** - A consulta do Catalog lê só as tabelas do Catalog e pede o estoque ao `StockLevels`: o log de queries de `GET /api/products` não tem nenhum `join` com `stocks`, e a única leitura de `stocks` é um `where in` pelos ids da página (door 1, Key decision 2 do design)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reads the stock of the catalog page through the inventory"`

**C28** - `POST /api/admin/products` com `stock_quantity` 7 e duas categorias devolve `201` com `stock.quantity` 7, `is_available` `true` e as duas categorias; o estoque aberto e as categorias gravadas são os do corpo (AC 26)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the new product as available with its stock and categories"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="opens the stock of a new product with the given quantity"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a product in exactly the given categories"`

**C29** - `PUT /api/admin/products/{product}` devolve `200` com os dados novos, as categorias trocadas e o estoque igual ao de antes; `PATCH /api/admin/products/{product}/status` devolve `200` com o status novo (AC 27 e 28)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="updates a product without touching its stock"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="replaces the product categories on update"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="activates and deactivates a product"`

**C30** - Table-driven sobre os 3 casos de uso de escrita do produto (`CreateProductUseCase`, `UpdateProductUseCase` e `ChangeProductStatusUseCase`), cada um devolve um `ProductRecord`, nunca um `Product`, com o id, o nome, o preço, o status e as datas que gravou (AC 29, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the written product data from every product write"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a product with its categories, stock and a default image"`

**C31** - O `StockLevels` responde, para os ids pedidos, o id da linha de estoque e a quantidade de cada produto que tem linha, e `null` para o que não tem, numa query só e sem `for update`; o `quantitiesFor` continua igual (door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reads the stock row of each product for the product pages"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reads the stock quantities of the given products without locking"`

**C32** - O `ProductCatalog` devolve os `CatalogProducts` dos ids pedidos, com o `ProductStatus`, e deixa de fora os ids sem produto (door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="finds catalog products by id for other modules"`

**C33** - O seed continua igual: todo produto semeado tem a sua linha em `stocks`, e duas execuções produzem os mesmos dados (plano, Assumptions)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds a complete demo environment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="produces the same data on every run"`

### S4 - StockResource · 6 arquivos · 10 KB · ~3k

**C34** - `GET /api/admin/stocks` e `PUT /api/admin/stocks/{stock}` devolvem cada linha com exatamente as chaves de hoje da linha de estoque. A lista tem 20 linhas por página, ordenadas por `quantity` crescente e depois por `id` (AC 30)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="renders every stock row with exactly the keys of today"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="paginates stocks by quantity ascending"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists stocks with their products"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists twenty stock rows per page by quantity and then by id"`

**C35** - `GET /api/admin/stocks` com 1 linha e com 20 linhas faz o mesmo número de queries, e a única leitura de `products` é a do `ProductCatalog`, um `where in` pelos ids da página (AC 31)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists the stock with the same query count for 1 and 20 rows"`

**C36** - `PUT /api/admin/stocks/{stock}` aumentando 5 unidades em 10 devolve `200` com `data.quantity` 15, em exatamente 3 queries, das quais exatamente uma lê `stocks`, com `for update`; reduzindo 15 devolve `data.quantity` 0 (AC 32, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="adjusts the stock reading it once with a lock"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="increases and decreases stock"`

**C37** - Levar um produto ativo a 0 unidades devolve `data.product.is_available` `false` e tira o produto da vitrine; voltar a 1 devolve `true`; um produto inativo continua `false` com 5 unidades (AC 33)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="makes a product unavailable at zero stock and available again only if active"`

**C38** - Reduzir 3 unidades de um estoque de 2 devolve `422` com `message` `"O estoque não pode ficar negativo."`, e a quantidade continua 2 (AC 34)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="never allows negative stock"`

**C39** - `PUT /api/admin/stocks/{stock}` com um id canônico desconhecido e corpo válido devolve `404`, e com corpo inválido devolve `422` em `operation` e `quantity`, para qualquer id (AC 35)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 for an unknown id in the staff and showcase routes"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="validates the body before the record in the staff routes"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="validates the stock operation"`

**C40** - Com um `ProductCatalog` trocado no container por um que não devolve nenhum produto, `GET /api/admin/stocks` devolve `200` com a linha na página e `product` `null` (AC 36)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps a stock row whose product is gone with a null product"`

**C41** - O `AdjustStockUseCase` devolve um `StockRecord`, nunca um `Stock`, com o id, o `product_id`, a quantidade gravada e o `updated_at` (AC 37, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the written stock row from the adjustment"`

### S5 - Dashboard · 11 arquivos · 30 KB · ~8k

**C42** - `GET /api/admin/dashboard` sobre 2 produtos ativos (10 e 0 unidades), 1 inativo (5), 3 contas de cliente e 2 pedidos (hoje e 3 meses atrás) devolve `200` com `data.cards` igual a `{total_products: 3, active_products: 2, inactive_products: 1, total_stock_units: 15, total_customers: 3, total_orders: 2}`, `data.stock` igual a `{products_in_stock: 2, products_out_of_stock: 1}`, 30 itens em `orders_per_day` somando 1 e 12 em `orders_per_month` somando 2 (AC 38 e 39)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns aggregated metrics"`

**C43** - `GET /api/admin/dashboard` com a loja vazia devolve `200` com os 6 cartões e os 2 números de estoque em 0, e 30 e 12 itens com `total` 0 (AC 40)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns zeros for an empty store"`

**C44** - O `CustomerMetrics` conta só as contas de `customers`: com 3 clientes e 2 membros da equipe, responde 3 (AC 41, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts only customer accounts for the dashboard"`

**C45** - As consultas dos donos respondem os contratos de métricas: o `CatalogMetrics`, a contagem por `ProductStatus` e o total; o `StockMetrics`, os produtos com unidades e o total de unidades, com 0 sem estoque; o `OrderMetrics`, o total e as contagens por dia (`Y-m-d`) e por mês (`Y-m`) desde a data dada, sem os pedidos de antes dela (door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts products by status"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts products in stock and the total units"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns zero units when there is no stock"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts orders (per day|per month) since a date"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts all the orders for the dashboard"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts the orders created exactly at the start date"`

**C46** - O `GetAdminDashboardUseCase` não existe em nenhum lugar do backend, e o `DashboardController` usa a consulta do Backoffice (AC 42)
Proof: `! grep -rn "GetAdminDashboardUseCase" backend/app backend/routes backend/tests`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="Backoffice reaches another module only through its public directories: (Catalog|Identity|Inventory|Ordering).Repositories"`

**C47** - O `DashboardService` continua montando os cartões e preenchendo com zero os dias e meses sem pedido, agora a partir das listas tipadas; as séries começam 29 dias e 11 meses antes de hoje (AC 39 e 40)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="builds the cards and fills the series gaps with zero"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="starts the daily series 29 days ago and the monthly series 11 months ago"`

### S6 - Documentação · 3 arquivos · 208 KB · ~52k

**C48** - Nenhuma linha do README, do `AGENTS.md` ou de `docs/` cita "até a HEL-6" nem o `GetAdminDashboardUseCase`, e toda linha que ainda cita `Product::stock`, `Stock::product` ou o `PurchaseAvailabilityService` cita também a HEL-6, ou seja, descreve o que a HEL-6 trocou (AC 43)
Proof: `! grep -rn "até a HEL-6" README.md AGENTS.md docs/`
Proof: `! grep -rn "GetAdminDashboardUseCase" README.md AGENTS.md docs/`
Proof: `! (grep -rnE "Product::stock|Stock::product|PurchaseAvailabilityService" README.md AGENTS.md docs/ | grep -v "HEL-6")`

**C49** - O `AGENTS.md` diz que um contrato de leitura é "implementado por uma consulta do módulo dono", e o README descreve a camada `Queries/` ao lado de `Repositories/` (AC 44)
Proof: `grep -n "implementado por uma consulta do módulo dono" AGENTS.md`
Proof: `grep -n "Queries/" README.md`

**C50** - A análise de domínio tem o problema da HEL-6 com ✅, "Disponível" com o `ProductAvailability` no Catalog e o termo "Consulta" na linguagem ubíqua, o passo da HEL-6 no plano de evolução e a HEL-6 na "Última atualização" (AC 45)
Proof: `grep -nE "^#### .*✅.*HEL-6|^#### .*HEL-6.*✅" docs/domain-analysis.md`
Proof: `grep -n "| Disponível (\`ProductAvailability\`) | Catalog |" docs/domain-analysis.md`
Proof: `grep -n "| Consulta (\`Queries\`) |" docs/domain-analysis.md`
Proof: `grep -nE "^- ✅ \*\*.*HEL-6" docs/domain-analysis.md`
Proof: `grep -n "Última atualização:.*HEL-6" docs/domain-analysis.md`

### Fechamento

**C51** - A suíte inteira e o estilo passam no branch entregue
Proof: `make test`
Proof: `docker compose exec -T api ./vendor/bin/pint --test`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| antigas exceções da HEL-6 (9) | `Backoffice→Catalog\Repositories` C2, C9 · `Backoffice→ProductStatus` C5 · `Backoffice→Identity\Repositories` C2, C9 · `Backoffice→Inventory\Repositories` C2, C9 · `Backoffice→Ordering\Repositories` C2, C9 · `Catalog\Models→Stock` C3, C9 · `Inventory\Models→Product` C3, C9 · `Catalog\Http→PurchaseAvailabilityService` C4, C9 · `Inventory\Http→PurchaseAvailabilityService` C4, C9 | - |
| módulos com `Queries` (5) | Backoffice C7 · Catalog C7 · Identity C7 · Inventory C7 · Ordering C7 | - |
| contratos e implementação no container (8) | `ProductCatalog` C8 · `StockLevels` C8 · `CatalogMetrics` C8 · `StockMetrics` C8 · `CustomerMetrics` C8 · `OrderMetrics` C8 · `StockInitializer` C8 · `StockReservation` C8 | - |
| startup config: ligações dos contratos (1 assembly) | o container da aplicação, montado pelos service providers dos módulos, que o HTTP e os testes usam C8 | - |
| vocabulário calculado das peças novas (9) | público `ProductStatus` C5 · público `ProductAvailability` C5 · público `ProductCountsByStatus` C5 · público `ProductStocks` C5 · público `ProductStock` C5 · público `StockTotals` C5 · público `OrderCountsByPeriod` C5 · privado `ProductRecord` C5 · privado `StockRecord` C5 | - |
| `ProductAvailability` (4 linhas) | C13, table-driven over all 4 | - |
| mensagens de compra, por saída (3) | nenhuma C18 · `Produto indisponível.` C15, C17, C18 · `Estoque insuficiente. Disponível: N.` C16, C17, C18 | - |
| leitores da disponibilidade (5) | `ProductResource` C14, C19 · `ProductController` C14, C23 · `StockResource` C14, C37 · `CartValidationService` C14, C15 · `CheckoutService` C14, C17 | - |
| rotas que devolvem produto com as chaves de hoje (7) | C19, table-driven over all 7 | - |
| contagens da HEL-11 (5) | abrir na vitrine 3 C22 · abrir no admin 3 C22 · editar 10 C22 · mudar o status 4 C22 · ajustar o estoque 3 C36 | - |
| contagem constante por página (2) | vitrine C21 · lista de estoque C35 | - |
| escritas que devolvem os dados gravados (4) | `CreateProductUseCase` C30 · `UpdateProductUseCase` C30 · `ChangeProductStatusUseCase` C30 · `AdjustStockUseCase` C41 | - |
| contratos de métricas (4) | `CatalogMetrics` C45 · `StockMetrics` C45 · `CustomerMetrics` C44 · `OrderMetrics` C45 | - |
| números do dashboard (10) | `total_products` C42, C43 · `active_products` C42, C43 · `inactive_products` C42, C43 · `total_stock_units` C42, C43 · `total_customers` C42, C43 · `total_orders` C42, C43 · `products_in_stock` C42, C43 · `products_out_of_stock` C42, C43 · `orders_per_day` C42, C43, C47 · `orders_per_month` C42, C43, C47 | - |
| `GET /api/products` statuses (1) | 200 C20 | - |
| `GET /api/products/{product}` statuses (3) | 200 C22 · 404 indisponível C23 · 404 desconhecido C24 | - |
| `GET /api/admin/products` statuses (1) | 200 C26 | - |
| `GET /api/admin/products/{product}` statuses (2) | 200 C22 · 404 C24 | - |
| `POST /api/admin/products` statuses (1) | 201 C28 | - |
| `PUT /api/admin/products/{product}` statuses (1) | 200 C22, C29 | - |
| `PATCH /api/admin/products/{product}/status` statuses (1) | 200 C22, C29 | - |
| `GET /api/admin/stocks` statuses (1) | 200 C34 | - |
| `PUT /api/admin/stocks/{stock}` statuses (4) | 200 C36 · 422 negativo C38 · 422 corpo inválido C39 · 404 C39 | - |
| `GET /api/admin/dashboard` statuses (1) | 200 C42 | - |
| `POST /api/cart/validate` statuses (1) | 200 C15 | - |
| `POST /api/orders` statuses (1) | 409 C17 | - |
| documentos (3) | README C48, C49 · `AGENTS.md` C48, C49 · `docs/domain-analysis.md` C48, C50 | - |

- Claims naming a status code, route or response shape: C15 a C17, C19 a C26, C28, C29, C34 a C40, C42 e C43 - each has a proof that crosses the HTTP boundary
- Claims about query counts (C21, C22, C27, C35, C36) are measured at the boundary with `queriesDuring()`, never inferred from the code
- No other check claims more than the single case its proof exercises

## Test policy

O README diz onde ficam e como rodar os testes, mas não diz qual nível prova cada tipo de código. As linhas abaixo valem para esta feature, no mesmo molde das features anteriores, e não vão para as diretrizes do repositório.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Decide e é alcançado por uma borda (`ProductAvailability`, as mensagens de compra) | uma na própria camada **e** uma na borda | um caso por linha da tabela na própria camada; o contrato na borda (vitrine, carrinho, checkout, estoque) |
| Consulta que decide (filtro, ordenação, página, agrupamento, período) | uma na própria camada, contra o banco | um caso por regra: cada ordenação, o filtro, o tamanho da página, cada agrupamento e o limite de data |
| Composição entre módulos (consulta que pede ao contrato do dono) | uma na borda, pela contagem de queries | 1 e N itens com a mesma contagem; a ausência do outro lado (C40) |
| Decide sem borda própria (`DashboardService`) | uma na própria camada | um caso por regra (início das séries, preenchimento com zero) |
| Regra de arquitetura (fronteira, consultas sem model, sem repositório e sem caso de uso) | uma por regra, as de fronteira vistas falhando com uma violação de propósito | uma violação por travessia antiga (C9) e uma para a assinatura com model (C6) |
| Instrumentação (resources, controllers, service providers) | nenhuma própria além da forma do corpo e da ligação no container | coberta por C19, C34 e C8 |

Evidence:

- `ProductAvailability`: 2 pontos de decisão (status `Active`, unidades acima de zero), 4 linhas → decide. Análogo: `PurchaseAvailabilityServiceTest` "decides availability from the product status and the stock units", na própria camada, com os mesmos casos.
- mensagens de compra: 2 pontos de decisão (indisponível, quantidade acima do disponível), 3 saídas → decide. Análogo: "explains why a quantity cannot be bought", na própria camada, com 5 casos.
- consulta do Catalog: 1 filtro, 3 ordenações com desempate por id, 2 tamanhos de página, mais novo primeiro no admin → decide. Análogo: `ProductRepositoryTest`, que prova essas regras na própria camada e passa a ser o teste da consulta.
- consulta do Inventory: 1 regra de ordenação (quantidade, depois id) e 1 ramo (produto ausente → `null`) → decide. Análogo: `StockRepositoryTest` "paginates stocks by quantity ascending".
- consultas de métricas: 1 agrupamento por status, 1 filtro de unidades acima de zero, 1 soma, 2 agrupamentos por período com limite de data, 1 filtro de contas de cliente → decide. Análogo: os testes de contagem de `OrderRepositoryTest`, `StockRepositoryTest`, `ProductRepositoryTest` e `CustomerAccountRepositoryTest`.
- `ProductResource` e `StockResource`: mapeiam campos; o único ramo novo é o `stock` nulo (C25) e o `product` nulo (C40) → provados na borda.

Cost: 5 provas novas na própria camada (C13, C31, C44, C45 com o total de pedidos, e o teste de reflexão do C6) e 10 novas na borda (C19, C21, C25, C26, C27, C28, C34, C35, C40, C43) em cerca de 12 arquivos de teste. Sem estas linhas, a disponibilidade e a composição entre módulos seriam provadas só pelo caminho que a vitrine atravessa, e a linha "produto ausente" da lista de estoque ficaria sem prova.

## Swept

- validation: C38 e C39, porque o ajuste recusa o negativo e o corpo inválido antes do registro; os form requests não mudam, e a recusa de id malformado sem query continua coberta pelos testes da HEL-11 (C24)
- failure modes: C38 e C17, porque a recusa não grava nada: o ajuste continua com a quantidade, e o checkout não cria pedido nem baixa estoque
- idempotency: n/a - nenhuma escrita nova; as consultas só leem, e as escritas mantêm as transações e os locks de hoje
- authorization: existing - o guard `staff` (admin e suporte) nas rotas do admin, a vitrine e o carrinho sem sessão e o checkout com `auth:customer` não mudam (plano, Observable)
- concurrency: C36, porque o ajuste lê `stocks` uma vez só, travada, e a resposta vem dos dados gravados, sem reler; C40, porque um produto que some entre as duas leituras da lista não derruba a página; o lock do checkout continua (C17)
- data lifecycle: C3 e C33, porque as relações saem sem mudar o esquema: a chave estrangeira com exclusão em cascata continua, e todo produto semeado tem a sua linha de estoque
- dependency failure: C40, porque a falta de resposta do contrato de outro módulo para um id vira `product` `null`, não erro; não há serviço externo nesta feature
- state transitions: C29 e C37, porque mudar o status do produto e zerar o estoque continuam mudando a disponibilidade como hoje; nenhuma transição nova
- observability: n/a - nenhum log, métrica ou trace muda; o `404` continua com o envelope `NOT_FOUND` e o `request_id` (C24)

## Handoff

- S1 = 7k (29 KB em 2 arquivos), S2 = 4k (16 KB em 7), S3 = 24k (97 KB em 27), S4 = 3k (10 KB em 6), S5 = 8k (30 KB em 11) e S6 = 52k (208 KB nos 3 documentos), por `wc -c` dividido por 4 sobre os arquivos que cada slice toca. Os cerca de 25 arquivos novos (consultas, value objects, contratos e testes) somam uma estimativa de 40 KB, ~10k. Total: ~108k, abaixo do budget de 150k
- Um builder só, sem corte
- Mechanism: one builder (o total cabe no budget)
- **Boundary:** C1-C51 fechados no código em `5f71a91`; os documentos (C48 a C50) no commit seguinte, `docs: describe the cqrs read side`
- **Settled mid-build:** nenhum esclarecimento do usuário. Decisões do builder abaixo do Landing: as consultas ficaram três por módulo (`ProductQuery`, `ProductCatalogQuery`, `CatalogMetricsQuery`; `StockLevelsQuery`, `StockListQuery`, `StockMetricsQuery`), a que responde o contrato consumido pelo outro módulo sem depender de contrato nenhum, para a vitrine e a lista de estoque não formarem um ciclo no container; o `DashboardQuery::get()` devolve `array` (Landing 7, registrado depois do código e aprovado pelo usuário em 16/10/2026 depois da rodada 1 da verificação); `ProductView`, `StockView`, `ProductCategories` e `ProductCategory` são os value objects privados que as telas leem
- **Abandoned:** o teste da única leitura de `stocks` da vitrine começou na consulta e foi para a rota (C27 fala da rota); a adaptação provisória dos resources à `ProductAvailability` (S2) foi trocada pelas consultas na S3, sem commit próprio
