# Module Routes and Migrations checks

Profile: standard
Plan: `.specs/features/module-routes-migrations/plan.md`

22 checks in 4 slices · 4 one-way doors (arquivo de rotas do módulo, lugar e nome das migrations, dono do padrão de UUID, caminho do cache de módulos) · 0 open

Notação das provas. `BUILD` é o worktree `.claude/worktrees/hel-12-routes-migrations`, na branch `helterdias/hel-12-module-routes-migrations`, criada em `7c530e4` (o HEAD da PR 27, `BASE` em `proofs/lib.sh`). `make -C <BUILD> test-filter FILTER="<regex>"` roda em `BUILD` e usa o banco `ecommerce_testing_hel_12_routes_migrations`. `P` é `.specs/features/module-routes-migrations/proofs`, `T` é `backend/tests`, `E` é `.specs/features/module-routes-migrations/evidence`. A linha de base de rotas é `E/route-list.base.json`, gravada com `route:list --json` em `7c530e4` antes da primeira edição de código, e a de contagem de testes é `E/baseline-make-test.txt` (o `make test` desse mesmo commit). Cada `P/cNN-*.sh` sai com 0 só quando a afirmação vale.

## Checks

### S1 - as rotas moram no módulo · 22 files · 40 KB · ~11k

**C1** - `route:list --json` lista os mesmos 73 itens da lista de `7c530e4`, comparando URI, método, nome, ação (com `App\Modules` já ausente) e middleware, sem considerar a ordem (AC 1)
Proof: `bash .specs/features/module-routes-migrations/proofs/c01-route-list.sh`

**C2** - `backend/routes/api.php` não existe e `backend/bootstrap/app.php` não passa `api:` a `withRouting` (AC 2)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="has no central api routes file"` - `tests/Unit/Architecture/ModuleRoutesTest.php`

**C3** - As rotas de `backoffice`, `catalog`, `customers`, `fulfillment`, `identity`, `inventory`, `ordering` e `payment` estão cada uma em `app-modules/<módulo>/routes/api.php`, dentro de `Route::prefix('api')->middleware('api')->group(`, e `shared` e `messaging` não têm pasta `routes` (AC 3, porta 1)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="declares the routes of the module in its own api file|keeps shared and messaging without routes"` - `tests/Unit/Architecture/ModuleRoutesTest.php`, uma linha de dataset por módulo (8 e 2)

**C4** - Um arquivo de rotas de módulo que nomeia um controller fora de `Modules\<Módulo>\Http\Controllers` desse mesmo módulo faz o teste de rotas falhar, e o detector acusa uma violação plantada em fixture (AC 4)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="uses only the controllers of its own module in its routes file|fails on a deliberate route violation"` - `tests/Unit/Architecture/ModuleRoutesTest.php`, uma linha por módulo (8) e um caso plantado

**C5** - Nenhum par método e URI se repete entre as rotas de `route:list` (AC 5)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="repeats no method and uri among the routes"` - `tests/Unit/Architecture/ModuleRoutesTest.php`

**C6** - Os padrões `product` e `category` são registrados no `register()` do `CatalogServiceProvider`, `stock` no do `InventoryServiceProvider`, `user` no do `IdentityServiceProvider`, `customer` e `address` no do `CustomersServiceProvider` e `order` no do `OrderingServiceProvider`, e o `AppServiceProvider` não chama `Route::pattern` (AC 6, porta 3)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="registers the route parameter pattern in the register method of its owner|keeps Route pattern out of the app service provider"` - `tests/Unit/Architecture/ModuleRoutesTest.php`, uma linha por parâmetro (7)

**C7** - Um parâmetro de rota de `/api` sem o padrão canônico faz o `RouteBindingTest` falhar, e a suíte atual o roda sobre as rotas dos módulos, com um id fora do padrão respondendo `404` (AC 7)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="RouteBindingTest|UuidIdsTest"` - `tests/Unit/Architecture/RouteBindingTest.php` e `tests/Feature/UuidIdsTest.php`, arquivos existentes sem alteração de asserção

### S2 - as migrations moram no módulo · 14 files · 30 KB · ~8k

**C8** - `backend/database/migrations` guarda só as migrations de `cache`, `jobs` e `features`, e as outras 11 estão em `app-modules/<módulo dono>/database/migrations` (tabela de donos da Landing), com o nome e o conteúdo byte a byte de `7c530e4` (AC 8, porta 2)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="keeps only the framework migrations at the root|places each module migration in its owner package"` - `tests/Unit/Architecture/ModuleMigrationsTest.php`, uma linha por migration (3 e 11)
Proof: `bash .specs/features/module-routes-migrations/proofs/c08-migration-bytes.sh`

**C9** - `migrate:status` num banco já migrado em `7c530e4` lista os mesmos 14 nomes como `Ran` e nenhum `Pending`, e `migrate --pretend` responde `Nothing to migrate.` (AC 9)
Proof: `bash .specs/features/module-routes-migrations/proofs/c09-migrate-status.sh`

**C10** - `migrate:fresh` num banco vazio sai com 0, lista os mesmos 14 nomes como `Ran` na mesma ordem de `7c530e4`, e existem as chaves estrangeiras `orders.customer_id`, `order_items.product_id`, `stocks.product_id`, `payments.order_id` e `customer_addresses.customer_id` (AC 10)
Proof: `bash .specs/features/module-routes-migrations/proofs/c10-migrate-fresh.sh`
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="creates the cross module foreign keys"` - `tests/Feature/Database/ModuleMigrationsForeignKeyTest.php`, uma linha por chave (5)

**C11** - O conjunto de nomes de migration dos caminhos do migrator difere dos 14 nomes congelados no teste quando um arquivo é renomeado, e o teste passa quando o conjunto é igual (AC 11, porta 2)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="keeps the fourteen frozen migration names|detects a renamed migration"` - `tests/Unit/Architecture/ModuleMigrationsTest.php`

### S3 - a carga não piorou · 4 files · 18 KB · ~5k

**C12** - `make load-test TYPE=smoke` sai com 0 e nenhum arquivo de `backend/bootstrap/cache` é criado nem alterado (AC 12)
Proof: `bash .specs/features/module-routes-migrations/proofs/c12-load-smoke.sh`

**C13** - `make load-test TYPE=load` no commit final sai com 0, o que exige p95 da vitrine até 40 ms, de `POST /api/orders` até 70 ms, da `order_payable_wait` até 2.450 ms e 0% de erro inesperado (AC 13)
Proof: `bash .specs/features/module-routes-migrations/proofs/c13-load-load.sh`

**C14** - O `load-tests/README.md` tem, na tabela "Linha de base por transporte", uma linha `redis`, `load`, que nomeia a HEL-12, com um commit que existe, e cujos três p95 diferem menos de 20% (sobre o menor) dos três p95 da rodada anterior citados na própria linha (AC 14)
Proof: `bash .specs/features/module-routes-migrations/proofs/c14-baseline-row.sh`

**C15** - O `load-tests/README.md` registra a mediana em ms de 20 execuções de `php artisan --version`, sem `modules:cache` e com ele, as duas maiores que 0 (AC 15)
Proof: `bash .specs/features/module-routes-migrations/proofs/c15-discovery-cost.sh`

### S4 - o resto não mudou e a documentação diz como ficou · 8 files · 60 KB · ~15k

**C16** - `make test` sai sem falha e todo arquivo de teste fora de `tests/Unit/Architecture` tem a mesma contagem de testes de `7c530e4` (AC 16)
Proof: `bash .specs/features/module-routes-migrations/proofs/c16-suite.sh`

**C17** - `make pint` sai com 0 (AC 17)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations pint`

**C18** - Nenhum arquivo de `README.md`, `AGENTS.md`, `docs/domain-analysis.md`, `load-tests/` e `backend/` menciona `routes/api.php` fora de um caminho `app-modules/<módulo>/routes/api.php`, e todo link relativo dos três documentos aponta para um arquivo que existe (AC 18)
Proof: `bash .specs/features/module-routes-migrations/proofs/c18-docs-links.sh`

**C19** - O `AGENTS.md` declara as rotas do módulo em `routes/api.php` do pacote com `Route::prefix('api')->middleware('api')`, o padrão de UUID no `register()` do provider do módulo dono, e as migrations do módulo no pacote, nunca renomeadas nem editadas, com `cache`, `jobs` e `features` na raiz (AC 19)
Proof: `bash .specs/features/module-routes-migrations/proofs/c19-agents-rules.sh`

**C20** - A `docs/domain-analysis.md` marca Module Routes, Module Migrations e Load Baseline como concluídos com a data, troca a decisão "Rotas centralizadas", e a linha "Última atualização" tem a data de hoje (AC 20)
Proof: `bash .specs/features/module-routes-migrations/proofs/c20-domain-analysis.sh`

**C21** - O cache de módulos lê o caminho de `APP_MODULES_CACHE` quando a variável existe e de `bootstrap/cache/app-modules.php` quando não existe, e gravar com a variável definida cria o arquivo no caminho dela e nenhum em `bootstrap/cache` (AC 21, porta 4)
Proof: `make -C .claude/worktrees/hel-12-routes-migrations test-filter FILTER="takes the module cache path from APP_MODULES_CACHE|defaults the module cache path to bootstrap cache"` - `tests/Unit/Architecture/ModuleCachePathTest.php`

**C22** - A pilha de carga define `APP_MODULES_CACHE=/tmp/laravel/app-modules.php` nos serviços `api`, `queue-worker`, `outbox-relay` e `event-consumer`, e o `make load-test` roda `modules:cache` no container `api` antes do k6 (AC 22, porta 4)
Proof: `bash .specs/features/module-routes-migrations/proofs/c22-load-modules-cache.sh`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| módulos com arquivo de rotas (8) | `backoffice` C3 · `catalog` C3 · `customers` C3 · `fulfillment` C3 · `identity` C3 · `inventory` C3 · `ordering` C3 · `payment` C3, as oito linhas do dataset de C3 e de C4 | - |
| módulos sem rotas (2) | `shared` C3 · `messaging` C3 | - |
| parâmetros de rota com padrão de UUID (7) | `product` C6 · `category` C6 · `stock` C6 · `user` C6 · `customer` C6 · `order` C6 · `address` C6, o dataset de C6, e o comportamento 404 em C7 | - |
| migrations que se movem (11) | `users` C8 · `customers` C8 · `categories` C8 · `products` C8 · `stocks` C8 · `category_product` C8 · `orders` C8 · `order_items` C8 · `payments` C8 · `customer_addresses` C8 · `outbox_messages` C8, o dataset de C8 e `git` R100 em C8 | - |
| migrations que ficam na raiz (3) | `cache` C8 · `jobs` C8 · `features` C8 | - |
| nomes de migration congelados (14) | `create_users_table` C11 · `create_cache_table` C11 · `create_jobs_table` C11 · `create_customers_table` C11 · `create_categories_table` C11 · `create_products_table` C11 · `create_stocks_table` C11 · `create_category_product_table` C11 · `create_orders_table` C11 · `create_order_items_table` C11 · `create_payments_table` C11 · `create_customer_addresses_table` C11 · `create_outbox_messages_table` C11 · `create_features_table` C11, o dataset congelado de C11, os 14 `Ran` em C9 e em ordem em C10 | - |
| chaves estrangeiras entre módulos (5) | `orders.customer_id` C10 · `order_items.product_id` C10 · `stocks.product_id` C10 · `payments.order_id` C10 · `customer_addresses.customer_id` C10 | - |
| portas de mão única da Landing (4) | arquivo de rotas do módulo C3 · lugar e nome das migrations C8 e C11 · dono do padrão de UUID C6 · caminho do cache de módulos C21 e C22 | - |
| startup configuration: descoberta das rotas, das migrations e do cache de módulos (3 assemblies) | CLI do `artisan` C1, C9 e C10 · app do Pest C5, C6, C7 e C21 · pilha de carga servida pelo nginx com `route:cache` e `modules:cache` C12, C13 e C22 | - |
| p95 do `load` (3) | vitrine C13 e C14 · `POST /api/orders` C13 e C14 · `order_payable_wait` C13 e C14 | - |
| documentos que mudam (4) | `README.md` C18 · `AGENTS.md` C18 e C19 · `docs/domain-analysis.md` C18 e C20 · `load-tests/README.md` C14 e C15 | - |
| menções a `routes/api.php` nos 5 lugares (5) | `README.md` C18 · `AGENTS.md` C18 · `docs/domain-analysis.md` C18 · `load-tests/` C18 · `backend/` C18 | - |

- A `Surface` é `None - nothing consumed outside` (nenhuma rota nova nem alterada; as 73 existentes são C1) e a `Relations` é `None - no stored-data shape change` (provado por C9 e C10)
- Claims naming a status code, route or response shape: C1, C7 - C1 compara URI, método, nome, ação e middleware das 73 rotas, e C7 roda o `UuidIdsTest` com um id fora do padrão respondendo `404`
- Nenhum outro check afirma mais que o caso único que a prova roda

## Swept

- validation: C7 - o id fora do padrão canônico responde `404` antes de qualquer query, em cada um dos 7 parâmetros (C6)
- failure modes: C10 - `migrate:fresh` falha se uma chave estrangeira apontar para uma tabela de outro módulo ainda não criada
- idempotency: C9 - `migrate` depois de migrado responde `Nothing to migrate.`, então os nomes não mudaram
- authorization: C1 - o middleware de cada rota (`auth:customer`, `auth:staff`, `admin`) entra na comparação, e `StaffRoleTest` e `AuthorizationTest` rodam em C16
- concurrency: existing - o contador nomeado do `throttle` de cada rota entra na comparação de C1, e o `RateLimitBurstTest` roda em C16
- data lifecycle: C8 e C9 - nenhuma tabela, coluna ou nome muda, sem backfill
- dependency failure: n/a - nenhuma chamada externa é criada ou alterada
- state transitions: n/a - nenhum status de entidade é criado ou alterado
- observability: n/a - nenhum log, métrica ou tracing muda; o `AssignRequestId` continua global em `bootstrap/app.php`

## Out of scope

- rotas de console por módulo, seeders por módulo, divisão do arquivo da `users`, `load` com `TRANSPORT=rabbitmq`, `modules:cache` no `make optimize` e a mesclagem da PR 27 - os motivos estão em `plan.md`.

## Handoff

Estimativa, a partir de `wc -c` dos arquivos que cada slice toca, dividido por quatro: S1 = 5,5 KB de `routes/api.php` + 2 KB de `bootstrap/app.php` + 1 KB do `AppServiceProvider` + 5 KB de 5 providers + ~14 KB de testes novos e fixture = ~28 KB, ~7k; S2 = 20 KB de migrations movidas sem leitura + ~8 KB de testes novos = ~8 KB lidos, ~2k; S3 = 16 KB do `load-tests/README.md` + scripts ~6 KB, ~6k; S4 = trechos de `README.md` (160 KB, lido por `grep` e faixas, ~20 KB), `AGENTS.md` 20 KB, `docs/domain-analysis.md` trechos ~20 KB, ~15k. Total ~30k, abaixo dos 150k do orçamento: um builder, sem pergunta.

- Mechanism: one builder
