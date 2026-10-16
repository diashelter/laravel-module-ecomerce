# Lado de leitura com consultas (HEL-6) - verificação

**Verdict**: PASS
**Profile**: standard
**Diff range**: 6d19dd9..HEAD (HEAD = 3afdd27; correção da rodada 1 em `d75ded4..837c990`; `1846b74` e `3afdd27` só mudam o `checks.md` e uma palavra do README)
**Round**: 3 - scoped
**Verifier**: independent sub-agent (author != verifier)

Histórico:

- **Rodada 1** (`d75ded4`): FAIL em C7, C20, C29, C34, C40 e C44, além do AC 15 e de dois mutantes sobreviventes (F4 e F5).
- **Rodada 2** (`1846b74`): fechou tudo isso, menos o C34. O teste certo existia, mas nenhuma linha `Proof:` do C34 o nomeava.
- **Rodada 3** (`3afdd27`, `git diff --stat 1846b74..HEAD` mostra só `checks.md` e `README.md`):
  - o C34 ganhou a linha `Proof:` do teste `lists twenty stock rows per page by quantity and then by id`;
  - o C45 ganhou a do teste `counts the orders created exactly at the start date`;
  - o texto do C44 passou a dizer "3 clientes e 2 membros da equipe, responde 3";
  - o `README.md:644` passou a dizer "ou à consulta".

Nenhum arquivo de `backend/` mudou desde `837c990`.

Resultado da rodada 3: os 51 checks estão provados com evidência localizada. As provas do C34, do C44 e do C45 rodaram de novo em `3afdd27` e passaram, com cada teste nomeado individualmente. A suíte inteira e o Pint passaram em `3afdd27`. Tudo o mais vem de `1846b74`, porque nenhum código nem teste mudou desde então.

## Binding sources

*Carried from d75ded4.* O passo 1 só roda no perfil `ui`; este feature é `standard`. O passo 5 não se aplica (backend).

## Checks

*Verified at 3afdd27 for C34, C44 and C45; carried from 1846b74 for the others* (nenhum arquivo de `backend/` mudou desde `837c990`). Em `3afdd27`, as provas do C34, do C44 e do C45, como o `checks.md` as lista agora, rodaram numa invocação só: `docker compose exec -T api ./vendor/bin/pest --filter="<os 11 filtros do C34, C44 e C45>"`, com **exit 0, 12 testes, 43 asserções**. Cada um apareceu como `✓`:

- C34: `it lists stocks with their products`, `it renders every stock row with exactly the keys of today`, `it lists twenty stock rows per page by quantity and then by id`, `it paginates stocks by quantity ascending`;
- C44: `it counts only customer accounts for the dashboard`;
- C45: `it counts products by status`, `it counts products in stock and the total units`, `it returns zero units when there is no stock`, `it counts orders per day since a date`, `it counts orders per month since a date`, `it counts all the orders for the dashboard`, `it counts the orders created exactly at the start date`.

Os testes novos existem em `backend/tests/Feature/Admin/StockTest.php:94` e `backend/tests/Feature/Queries/OrderMetricsQueryTest.php:45`.

Rodada 2 (`1846b74`): as provas rodaram de novo por inteiro, numa invocação só na árvore real: `docker compose exec -T api ./vendor/bin/pest --filter="<alternação dos 82 filtros distintos do checks.md>"`, com **exit 0, 247 testes, 891 asserções, 0 falhas** (na rodada 1 eram 241; os 6 a mais são os 5 `queries do not use the module use cases: <M>` e o novo `keeps a stock row whose product is gone with a null product` do `StockTest`). Cada filtro foi casado contra os nomes do junit para listar os testes que cada prova rodou; nenhum filtro casou zero. As provas por `grep` (C1, C14, C46, C48 a C50) saíram com 0. As citações foram atualizadas nos arquivos que a correção tocou (`ModuleBoundariesTest`, `CartValidationTest`, `ProductCatalogTest`, `Admin/ProductTest`, `Admin/StockTest`, `CustomerMetricsQueryTest` e `OrderMetricsQueryTest`). As outras vêm de `d75ded4`, porque esses arquivos não mudaram.

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | sem `BOUNDARY_EXCEPTIONS`, `->ignoring(` e `HEL-6`; teste de runtime | grep exit 0; 1 teste PASS | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:318` - `expect(array_filter($constants, fn (string $name) => str_contains($name, 'EXCEPTION')))->toBe([])` | PASS |
| C2 | Backoffice x 4 `Repositories`; laço do `StockRepository` com o Backoffice | 4 + 1 testes gerados PASS | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:194-196` - `->not->toUse('App\\Modules\\'.$target.'\\'.$directory)`; `:425-428` `->not->toUse(StockRepository::class)` | PASS |
| C3 | Catalog x `Inventory\Models`; Inventory x `Catalog\Models` | 2 testes gerados PASS | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:194-196` | PASS |
| C4 | Catalog e Inventory x `Ordering\Services` | 2 testes gerados PASS | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:194-196` | PASS |
| C5 | vocabulário das peças novas | 1 teste PASS | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:293-297` - `vocabularyOf('Catalog'))->toContain(ProductStatus::class, ProductAvailability::class, ...)`, `->not->toContain(ProductRecord::class)`, `->not->toContain(StockRecord::class)` | PASS |
| C6 | reflexão nas consultas nomeia classe e método | 1 teste PASS; exercício carried from d75ded4 (falhou com `ProductCatalogQuery::probe speaks the Eloquent type ...Product`) | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:402-403` - `->toBeFalse("{$query}::{$method->getName()} speaks the Eloquent type {$name}")` | PASS |
| C7 | 5 módulos x (`repositories`, `use cases`) + lista de módulos | o filtro do checks.md agora casa **10** testes (`queries do not use the module repositories: <M>` e `... use cases: <M>`), todos PASS; `every module with queries is covered` PASS. **Exercício refeito** com o filtro citado: uma violação `UseCases` em `ProductQuery` -> `1 failed, 9 passed` (`Expecting 'App\Modules\Catalog\Queries' not to use 'App\Modules\Catalog\UseCases'`); revertido -> 10 passed | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:377-380` - `foreach (['repositories' => 'Repositories', 'use cases' => 'UseCases'] as $label => $layer) { arch("queries do not use the module {$label}: {$module}")->expect(...Queries)->not->toUse(...{$layer}) }`; `:385` `->toBe(['Backoffice', 'Catalog', 'Identity', 'Inventory', 'Ordering'])` | PASS |
| C8 | ligações no container | 6 + 2 casos PASS | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:413-414` `->toStartWith("App\\Modules\\{$module}\\Queries\\")`; `:565` `->toBeInstanceOf(StockRepository::class)` | PASS |
| C9 | 8 violações falham e voltam a passar; `ProductStatus` permitido | os 2 filtros: 4 + 4 testes PASS; exercícios (a)-(h) carried from d75ded4 (o laço gerado em `:191-199` não mudou na correção) | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:194-196`; `backend/app/Modules/Backoffice/Services/DashboardService.php:7` e `:293` | PASS |
| C10 | as outras regras continuam | 2+6+1+6+57+1+7 testes PASS | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:204-211`, `:234-238`, `:325-327`, `:353-354`, `:547-549`, `:304-306`, `:577-580`, `:591-593` | PASS |
| C11 | métricas e `StockLevels` sem `array` | 21 casos PASS | `backend/tests/Unit/Architecture/TypedListSignaturesTest.php:36-37` `->toBeFalse("... declares an array")`; dataset `:48-53`, `:58` | PASS |
| C12 | nenhum `int` id | 1 teste PASS | `backend/tests/Unit/Architecture/IdTypesTest.php:120-121` - `expect($read)->toBeGreaterThanOrEqual(200)->and($violations)->toBe([])` | PASS |
| C13 | `ProductAvailability`, 4 linhas | 4 casos PASS | `backend/tests/Unit/ValueObjects/ProductAvailabilityTest.php:10-11` `->isAvailable())->toBe($available)->and(...->availableQuantity())->toBe($quantity)`; `:13-16` | PASS |
| C14 | só o `ProductAvailability` decide | 2 testes PASS; 2 greps exit 0; o código não mudou desde o grep da rodada 1 (`ProductAvailability.php:25` é a única combinação) | `backend/tests/Unit/ValueObjects/ProductAvailabilityTest.php:22-23` `property_exists($product, 'isActive'))->toBeFalse()`; `backend/tests/Unit/Services/PurchaseAvailabilityServiceTest.php:22-24` `->not->toUse(ProductStatus::class)` | PASS |
| C15 | carrinho: inativo -> `problem` (e AC 15: `is_available` false); sem linha -> 0/false/problem | 2 testes PASS | `backend/tests/Feature/CartValidationTest.php:34` `->assertOk()`, `:39-40` `expect($lines[$inactive->id]['is_available'])->toBeFalse()->and($lines[$inactive->id]['available_quantity'])->toBe(10)`, `:42` `toBe('Produto indisponível.')`; `:53-58` `toMatchArray([... 'available_quantity' => 0, 'is_available' => false, 'problem' => 'Produto indisponível.'])` | PASS |
| C16 | 5 de 2 -> 2 e a mensagem; chaves de hoje | 2 testes PASS | `backend/tests/Feature/CartValidationTest.php:38` `expect($lines[$lowStock->id]['available_quantity'])->toBe(2)`, `:41` `toBe('Estoque insuficiente. Disponível: 2.')`; `:132-136` `array_keys(...)->toBe([...])` | PASS |
| C17 | checkout `409`, mensagens, nada muda | 2 testes PASS | `backend/tests/Feature/CheckoutTest.php:142-147` `->assertConflict()->json('errors'))->toBe(["items.{$inactive->id}" => ['Produto indisponível.']])`; `:123`, `:127`; `:130-132`, `:149-151` | PASS |
| C18 | mensagens, 5 casos | 5 casos PASS | `backend/tests/Unit/Services/PurchaseAvailabilityServiceTest.php:12` `->purchaseProblem(new ProductAvailability($status, $stock), $quantity))->toBe($expected)`; `:14-18` (a linha `without a stock row` continua igual a `out of stock`; o caso fica provado na borda em `CartValidationTest.php:53-58`) | PASS |
| C19 | 7 rotas, chaves e ordem | 7 casos PASS | `backend/tests/Feature/ProductResponseShapeTest.php:19-22` `array_keys($item))->toBe(['id', 'name', ..., 'updated_at'])`, `:23`, `:27`; dataset `:30-36` | PASS |
| C20 | vitrine: inativos, disponibilidade, 12/página, filtro, 3 ordenações, `links` e `meta` | 1+1+3+1+3+1+1 testes PASS | `backend/tests/Feature/ProductCatalogTest.php:15-17`, `:26-29`, `:37-43`, `:55-61` - `assertJsonCount(3, 'data')->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.per_page', 12)->assertJsonPath('meta.total', 15)->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']])->assertJsonPath('links.next', null)->assertJsonPath('links.prev', url('/api/products').'?page=1')`; `backend/tests/Feature/Queries/ProductQueryTest.php:19,30,58-60` | PASS |
| C21 | mesma contagem com 1 e 12 | 1 teste PASS | `backend/tests/Feature/ProductCatalogTest.php:103-104` - `expect($single)->not->toBeEmpty()->and(count($full))->toBe(count($single))` | PASS |
| C22 | 3/3/10/4 com `200` | 4 dos 10 casos PASS | `backend/tests/Feature/Admin/StaffRecordRoutesTest.php:252` `assertOk()`, `:254` `expect($queries)->toHaveCount($expected, ...)`; `:256-259` | PASS |
| C23 | inativo ou sem unidades -> `404` | 2 casos PASS | `backend/tests/Feature/ProductCatalogTest.php:77-79` - `->assertNotFound()->assertJsonPath('message', 'Produto indisponível.')` | PASS |
| C24 | id desconhecido -> `404` `NOT_FOUND` | 15 casos PASS | `backend/tests/Feature/Admin/StaffRecordRoutesTest.php:109` `assertApiError($response->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.')`; `:14-15` | PASS |
| C25 | sem linha -> `stock` null, false, 0 | 1 teste PASS | `backend/tests/Feature/ProductCatalogTest.php:127-129` - `expect($item['stock'])->toBeNull()->and($item['is_available'])->toBeFalse()->and($item['available_quantity'])->toBe(0)` | PASS |
| C26 | admin 15/página, mais novo primeiro | 2 testes PASS | `backend/tests/Feature/ProductResponseShapeTest.php:45-48`; `backend/tests/Feature/Queries/ProductQueryTest.php:69` | PASS |
| C27 | uma leitura de `stocks`, `where in`, sem `join` | 1 teste PASS | `backend/tests/Feature/ProductCatalogTest.php:114-116` - `expect($stockReads)->toHaveCount(1)->and($stockReads[0])->toContain('"product_id" in (')->and(... 'join' ...)->toBeEmpty()` | PASS |
| C28 | `POST` `201`, estoque 7, disponível, 2 categorias | 3 testes PASS | `backend/tests/Feature/ProductResponseShapeTest.php:57-60`, `:63-64`; `backend/tests/Feature/Admin/ProductTest.php:25`, `:34-36` | PASS |
| C29 | `PUT` `200` com dados novos, categorias trocadas no corpo e estoque igual; `PATCH` status | 3 testes PASS | `backend/tests/Feature/Admin/ProductTest.php:44-48` - `$response = $this->putJson(...)->assertOk(); expect(collect($response->json('data.categories'))->pluck('id')->sort()->values()->all())->toBe(collect([$c2->id, $c3->id])->sort()->values()->all())`, `:49-50` (banco); `:67-68` `assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.stock.quantity', 4)`; `:76`, `:80` `data.is_available` false/true. F9 morto | PASS |
| C30 | 3 escritas devolvem `ProductRecord` | 2 testes PASS | `backend/tests/Feature/UseCases/Product/ProductUseCasesTest.php:57-64` `->toBeInstanceOf(ProductRecord::class)->and($record->id)->toBe($row->id)...`; `:67-70`; `:38-41` | PASS |
| C31 | `stocksFor` numa query sem lock; `quantitiesFor` igual | 2 testes PASS | `backend/tests/Feature/Queries/StockLevelsQueryTest.php:19-23` `->toEqual(new ProductStock(stockOf($first)->id, 4))`, `->toBeNull()`, `toHaveCount(1)`, `->not->toContain('for update')`; `:33-36` | PASS |
| C32 | `ProductCatalog` com `ProductStatus`, sem ids desconhecidos | 1 teste PASS | `backend/tests/Feature/Queries/ProductCatalogQueryTest.php:19-22` | PASS |
| C33 | seed completo e determinístico | 2 testes PASS | `backend/tests/Feature/SeederTest.php:22`, `:65` | PASS |
| C34 | chaves da linha de estoque; **20 por página**, `quantity` e **depois `id`** | em `3afdd27`, os 4 filtros citados (inclusive o novo `lists twenty stock rows per page by quantity and then by id`) rodaram 4 testes, todos PASS, exit 0 | chaves: `backend/tests/Feature/Admin/StockTest.php:71-72` `array_keys($row))->toBe(['id', 'quantity', 'product', 'updated_at'])`; página e desempate: `StockTest.php:101-106` - `expect($response->json('data'))->toHaveCount(20)->and($response->json('meta.per_page'))->toBe(20)...->and(collect($response->json('data'))->skip(1)->pluck('id')->all())->toBe($ids->take(19)->all())->and(collect($second->json('data'))->pluck('id')->all())->toBe($ids->skip(19)->values()->all())`; ordem por `quantity`: `backend/tests/Feature/Queries/StockListQueryTest.php:16`. F6, F7 e F7b mortos por este teste (rodada 2) | PASS |
| C35 | 1 x 20 linhas, uma leitura de `products` | 1 teste PASS | `backend/tests/Feature/Admin/StockTest.php:89-91` - `expect(count($full))->toBe(count($single))->and($productReads)->toHaveCount(1)->and($productReads[0])->toContain('"id" in (')` | PASS |
| C36 | 3 queries, uma leitura de `stocks` com `for update`; 15 e 0 | 2 testes PASS | `backend/tests/Feature/Admin/StaffRecordRoutesTest.php:139`, `:143-145` `toHaveCount(3)`, `$reads)->toHaveCount(1)`, `->toContain('for update')`; `backend/tests/Feature/Admin/StockTest.php:22,25` | PASS |
| C37 | zero -> false e fora da vitrine; 1 -> true; inativo false | 1 teste PASS | `backend/tests/Feature/Admin/StockTest.php:53-61` - `assertJsonPath('data.product.is_available', false)`, `->assertNotFound()`, `... true`, `->assertOk()`, inativo `false` | PASS |
| C38 | `422` negativo, quantidade 2 | 1 teste PASS | `backend/tests/Feature/Admin/StockTest.php:32-35` - `->assertUnprocessable()->assertJsonPath('message', 'O estoque não pode ficar negativo.')`; `->toBe(2)` | PASS |
| C39 | `404` id desconhecido; `422` corpo inválido | 15 + 12 + 1 PASS | `backend/tests/Feature/Admin/StaffRecordRoutesTest.php:109` (`:22`), `:117` (`:122`); `backend/tests/Feature/Admin/StockTest.php:42-43` `assertJsonValidationErrors(['operation', 'quantity'])` | PASS |
| C40 | `ProductCatalog` trocado: `GET /api/admin/stocks` `200` com a linha e `product` null | o filtro agora casa **2** testes, os dois PASS: o da consulta e o novo de HTTP | `backend/tests/Feature/Admin/StockTest.php:119-124` - `$this->getJson('/api/admin/stocks')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $stock->id)->assertJsonPath('data.0.quantity', 3)->assertJsonPath('data.0.product', null)` (com o container trocado em `:111-117`); `backend/tests/Feature/Queries/StockListQueryTest.php:32-34`. F8 morto | PASS |
| C41 | `AdjustStockUseCase` devolve `StockRecord` | 1 teste PASS | `backend/tests/Feature/UseCases/Stock/AdjustStockUseCaseTest.php:16-21` | PASS |
| C42 | dashboard do AC 38 | 1 teste PASS | `backend/tests/Feature/Admin/DashboardTest.php:14`, `:16-24` `assertJsonPath('data.cards', [...])`, `:25`, `:27-30` | PASS |
| C43 | loja vazia | 1 teste PASS | `backend/tests/Feature/Admin/DashboardTest.php:34-49` | PASS |
| C44 | `CustomerMetrics` conta só `customers`: 3 clientes e 2 da equipe -> 3 | em `3afdd27`, 1 teste PASS | `backend/tests/Feature/Queries/CustomerMetricsQueryTest.php:12` - `expect(app(CustomerMetrics::class)->totalCustomers())->toBe(3)`, com 3 clientes e 2 da equipe (`:6-10`), igual ao texto atual do check. F4 morto (rodada 2: `Failed asserting that 2 is identical to 3`) | PASS |
| C45 | métricas dos donos, inclusive o limite inclusivo da data | em `3afdd27`, 1+1+1+2+1+1 testes PASS | `backend/tests/Feature/Queries/CatalogMetricsQueryTest.php:13-15`; `backend/tests/Feature/Queries/StockMetricsQueryTest.php:12-13`, `:19-20`; `backend/tests/Feature/Queries/OrderMetricsQueryTest.php:23-27`, `:38-42`, `:8`, `:12`; `:51-52` - `expect(app(OrderMetrics::class)->countPerDaySince($day)->of($day->toDateString()))->toBe(1)->and(app(OrderMetrics::class)->countPerMonthSince($month)->of($month->format('Y-m')))->toBe(1)`, agora citado no check. F5a e F5b mortos (rodada 2) | PASS |
| C46 | sem `GetAdminDashboardUseCase`; controller usa a consulta | grep exit 0; 4 testes PASS | `backend/app/Modules/Backoffice/Http/Controllers/Admin/DashboardController.php:13-15`; `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:194-196` | PASS |
| C47 | `DashboardService` | 2 testes PASS | `backend/tests/Unit/Services/DashboardServiceTest.php:14-15`, `:31-46` | PASS |
| C48 | docs sem nomes antigos como atuais | 3 greps exit 0 | `README.md:566`; `docs/domain-analysis.md:529` | PASS |
| C49 | `AGENTS.md` e README | 2 greps exit 0 | `AGENTS.md:66`; `README.md:90` | PASS |
| C50 | análise de domínio | 5 greps exit 0 | `docs/domain-analysis.md:529`, `:603`, `:606`, `:645`, `:4` | PASS |
| C51 | suíte inteira e Pint | `make test` equivalente exit 0; Pint exit 0 | junit `tests=1832 failures=0 errors=0 skipped=0`; Pint `PASS ... 404 files` | PASS |

## Coverage

Rodada 3: *carried from 1846b74*, porque nenhum código nem teste mudou. Na rodada 2: *verified at 1846b74* nas linhas cuja autoridade ou prova a correção tocou; as demais, *carried from d75ded4* (nenhum arquivo de `backend/app`, rota, provider ou migration mudou).

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| antigas exceções da HEL-6 (9) - carried | `git show 6d19dd9:...ModuleBoundariesTest.php` | exercícios (a)-(h) e C5 | - |
| módulos com `Queries` (5) - verified | `ls -d backend/app/Modules/*/Queries` | `ModuleBoundariesTest.php:377-380` (10 testes, todos agora alcançados pela prova do C7), `:385` | - |
| ligações de contrato (8) - carried | os 4 service providers | `ModuleBoundariesTest.php:413-414`, `:565` | - |
| startup config (1) - carried | `backend/bootstrap/providers.php:12-20` | C8 | - |
| vocabulário (9; 29/28) - carried | cálculo próprio no container | C5 | - |
| `ProductAvailability` (4) - carried | `ProductAvailability.php:23-31` | C13 | - |
| mensagens de compra (3) - carried | `PurchaseAvailabilityService.php:21-29` | C18; borda C15-C17 | - |
| leitores da disponibilidade (5) - carried | `rg` em `backend/app` | C19/C25, C23, C37, C15/C16, C17 | - |
| rotas que devolvem produto (7) - carried | `route:list` | `ProductResponseShapeTest.php:30-36` | - |
| contagens da HEL-11 (5) - carried | plano | `StaffRecordRoutesTest.php:256-259`, `:143-145` | - |
| contagem constante (2) - verified | plano | `ProductCatalogTest.php:104`; `StockTest.php:89` | - |
| escritas que devolvem dados (4) - carried | `UseCases` | C30, C41 | - |
| contratos de métricas (4) - verified | `Contracts/*Metrics.php` | Catalog e Stock C45; Order C45 e `OrderMetricsQueryTest.php:51-52`; Customer `CustomerMetricsQueryTest.php:12` (agora distingue) | - |
| números do dashboard (10) - carried | `DashboardService.php:49-64` | C42, C43, C47 | - |
| status das rotas tocadas - verified | `route:list` e controllers | os mesmos da rodada 1, com as linhas novas: `GET /api/admin/stocks` 200 também em `StockTest.php:98-99,120`; `PUT /api/admin/products/{p}` 200 `ProductTest.php:45`; `POST /api/cart/validate` 200 `CartValidationTest.php:34` | - |
| chaves de cada corpo - verified | resources e `DashboardService` | produto C19; estoque `StockTest.php:71-72`; carrinho C16; dashboard `cards`/`stock` na borda, itens das séries no serviço (`DashboardServiceTest.php:41,45`) | - |
| página e ordem da vitrine (AC 19) - verified | plano AC 19 | 12/página `ProductCatalogTest.php:57`; `links` `:59-61`; `meta` `:56-58`; filtro e ordenações C20 | - |
| página e ordem da lista de estoque (AC 30) - verified | `StockListQuery.php:29-32`, `StockController.php:18` | 20/página e desempate por `id` `StockTest.php:101-106` (F6, F7 e F7b mortos); `quantity` `StockListQueryTest.php:16`. Desde `3afdd27`, citado nas provas do C34 | - |
| ramos nulos novos dos resources (2) - verified | `ProductResource.php:32`, `StockResource.php:22` | `stock` nulo C25; `product` nulo na borda `StockTest.php:124` (F8 morto) | - |
| categorias no corpo das escritas (3) - verified | plano, Assumption 4 | `POST` `ProductResponseShapeTest.php:63-64`; `PUT` `ProductTest.php:47-48` (F9 morto); `PATCH` não muda categorias, e a forma é C19 | - |
| ACs 1..45 -> checks - verified | `plan.md` Criteria | todos com check; o AC 15 agora em `CartValidationTest.php:39-40` | - |
| documentos (3) - verified | Impact `docs` | C48-C50; frases corrigidas (ver abaixo) | - |

## Test policy rows

Rodada 3: *carried from 1846b74*. Na rodada 2: *verified at 1846b74* nas três linhas que estavam abertas e nas que classificam arquivos tocados; as demais, *carried from d75ded4*.

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| Decide e é alcançado por uma borda (carried) | `ProductAvailability.php`, `PurchaseAvailabilityService.php` | camada própria `ProductAvailabilityTest.php:13-16`, `PurchaseAvailabilityServiceTest.php:14-18` · borda `ProductCatalogTest.php:15-17`, `CartValidationTest.php:38-42`, `CheckoutTest.php:142-147`, `StockTest.php:53-61` | yes |
| Consulta que decide (verified) | `ProductQuery.php`, `StockListQuery.php`, `*MetricsQuery.php` | filtro, ordenações, páginas: `ProductQueryTest.php`; desempate por `id` e 20/página: `StockTest.php:101-106` (pela borda, contra o banco); limite inclusivo da data: `OrderMetricsQueryTest.php:51-52`; só contas de cliente: `CustomerMetricsQueryTest.php:12` | yes |
| Composição entre módulos (verified) | `ProductQuery.php`, `StockListQuery.php` | borda: `ProductCatalogTest.php:104`, `StockTest.php:89-91`; ausência do outro lado na borda: `StockTest.php:119-124` | yes |
| Decide sem borda própria (carried) | `DashboardService.php` | `DashboardServiceTest.php:14-15`, `:31-46` | yes |
| Regra de arquitetura (verified) | `ModuleBoundariesTest.php` | (a)-(h) e C6 carried; as regras de consulta vistas falhando pelo filtro citado do C7 nesta rodada | yes |
| Instrumentação (verified) | `ProductResource.php`, `StockResource.php`, controllers, providers | C19, `StockTest.php:71-72`, C8; ramo `product` nulo na borda `StockTest.php:124` | yes |

## Faults injected

Rodada 3: *carried from 1846b74*, porque nenhum código nem teste mudou (sem novas superfícies, nenhuma falta nova). Na rodada 2: *verified at 1846b74*, as faltas foram injetadas de novo nas superfícies que a correção tocou (F4, F5) e nas que ela criou (página e desempate da lista, `product` nulo, categorias do `PUT`, `links`). São mais de cinco por pedido do orquestrador. Cada uma rodou com a prova mais estreita, numa worktree descartável. Os F1 a F3 são *carried from d75ded4*: nem o código nem os testes dessas superfícies mudaram. Os F4 e F5 da rodada 1, que sobreviveram, foram substituídos pelas novas injeções abaixo.

| Mutation | Location | Killed |
| --- | --- | --- |
| F1 `&&` -> `\|\|` em `isAvailable` (carried) | `backend/app/Modules/Catalog/ValueObjects/ProductAvailability.php:25` | yes - C13 |
| F2 o ajuste relê `stocks` (carried) | `backend/app/Modules/Inventory/UseCases/AdjustStockUseCase.php:37` | yes - C36 |
| F3 a lista descarta a linha sem produto (carried) | `backend/app/Modules/Inventory/Queries/StockListQuery.php:36` | yes - C40 |
| F4 conta `User` no lugar de `CustomerAccount` | `backend/app/Modules/Identity/Queries/CustomerMetricsQuery.php:17` | yes - C44 (`Failed asserting that 2 is identical to 3`) |
| F5a `>=` -> `>` por dia | `backend/app/Modules/Ordering/Queries/OrderMetricsQuery.php:25` | yes - `counts the orders created exactly at the start date` (`0 is identical to 1`) |
| F5b `>=` -> `>` por mês | `backend/app/Modules/Ordering/Queries/OrderMetricsQuery.php:34` | yes - mesmo teste |
| F6 página da lista de estoque 20 -> 21 | `backend/app/Modules/Inventory/Http/Controllers/Admin/StockController.php:18` | yes - `lists twenty stock rows ...` (`actual size 21 matches expected size 20`) |
| F7 sem o desempate por `id` | `backend/app/Modules/Inventory/Queries/StockListQuery.php:31` | yes - mesmo teste (`two arrays are identical`) |
| F7b desempate invertido (`orderByDesc('id')`) | `backend/app/Modules/Inventory/Queries/StockListQuery.php:31` | yes - mesmo teste |
| F8 produto ausente vira `[]` no lugar de `null` | `backend/app/Modules/Inventory/Http/Resources/StockResource.php:22` | yes - C40 HTTP (`Array &0 [] is identical to null`; o teste da consulta passa, como esperado) |
| F9 o `PUT` responde com as categorias antigas | `backend/app/Modules/Catalog/Http/Controllers/Admin/ProductController.php:47` | yes - C29 (`two arrays are identical`) |
| F10 `links` da vitrine montados em outro caminho | `backend/app/Modules/Catalog/Http/Controllers/ProductController.php:25` | yes - C20 `paginates products` (`two strings are identical`) |

Em todas, `git diff --quiet -- backend/tests` saiu com 0 durante a falta, e cada arquivo voltou a passar depois do `git checkout --`.

### Exercícios temporários

- C7: *verified at 1846b74*, ver a linha do C7 (o filtro citado agora vê a violação de `UseCases`).
- C6 e C9 (a)-(h): *carried from d75ded4*. As regras geradas em `ModuleBoundariesTest.php:191-199` e `:388-408` não mudaram.

### Isolamento

- O baseline desta rodada era `?? .specs/features/cqrs-read-side/verification.md` (o relatório da rodada 1). Ao final, o `git status --porcelain` é igual a ele.
- A worktree foi criada com `git worktree add <scratch>/verifier/wt HEAD`, que estava em `1846b74`. Os testes rodaram num `docker run --rm --network ecommerce_default` da imagem `ecommerce-php:latest`, com a `backend/` da worktree montada e o `vendor` e o `.env` reais montados só para leitura, uma falta de cada vez. Depois, `git worktree remove --force` e `git worktree prune`.

## Swept (re-lidos no código)

*Carried from d75ded4*: nenhuma rota, migration ou repository mudou na correção. authorization `backend/routes/api.php:40-49`, `:67-78`; data lifecycle `backend/database/migrations/2026_10_05_000003_create_stocks_table.php:15`; concurrency `backend/app/Modules/Inventory/Repositories/StockRepository.php:45`, `:54`.

## Landing, Flow e Impact

*Verified at 1846b74* nos pontos tocados; *carried from 1846b74* na rodada 3 (nenhum código mudou).

- Door 6: o nome gerado agora segue a forma literal do plano (`queries do not use the module repositories: M`, em `ModuleBoundariesTest.php:378`).
- Door 7 (**aprovada pelo usuário, segundo o orquestrador**): o código casa com a door 7 como ela está escrita. `backend/app/Modules/Backoffice/Queries/DashboardQuery.php:29` declara `get(): array`, devolve o que o `DashboardService::build` monta, e o controller o põe em `data` (`DashboardController.php:15`). O orquestrador informa que o usuário aprovou numa mensagem própria no chat ("Aprovo o Landing 7..."), e a aprovação está registrada no Handoff do `checks.md` (`1846b74`). Não é um item em aberto.
- Doors 1 a 5, Flow e Impact: *carried from d75ded4* (nenhum código mudou).

## Documentação

*Verified at 3afdd27* para o `README.md:644`; o resto vem *carried from 1846b74*.

- `AGENTS.md:68`: agora diz "o use case (escrita) ou o repositório ou a consulta (leitura)". Confere com `ProductQuery.php:61-63`.
- `README.md:644`: em `3afdd27`, diz "ao repositório ou à consulta (leitura)". Confere, com a crase.
- `README.md:885` e `docs/domain-analysis.md:538`: agora dizem que "sem linha é zero" fica no `StockQuantities` (carrinho e checkout) e no `ProductView` (vitrine e cadastro). Confere com `ProductView.php:24`.
- Os greps do C48 a C50 continuam saindo com 0.

## Gate

*Verified at 3afdd27*: `docker compose exec -T api php artisan config:clear --quiet && docker compose exec -T api ./vendor/bin/pest` (equivalente ao `make test`) na árvore real deu **1832 passed, 0 failed** (7736 asserções; junit `tests=1832 failures=0 errors=0 skipped=0`). O mesmo resultado em `1846b74` e em `837c990`. `docker compose exec -T api ./vendor/bin/pint --test`: exit 0, `PASS 404 files`.

Isolamento da rodada 3: só rodei testes na árvore real, sem mutações. O `git status --porcelain` é igual ao baseline da rodada (só `?? .specs/features/cqrs-read-side/verification.md`).

## Ranked gaps

Nenhum. A lacuna que sobrou da rodada 2 (as provas citadas do C34 não rodavam o teste de página e desempate) foi fechada em `3afdd27`. As notas de precisão do C44 e do C45 também.
