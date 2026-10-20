# Module Routes and Migrations

## Problem

A PR 27 fez de cada módulo um pacote em `backend/app-modules`, mas a [HEL-12](https://linear.app/helter/issue/HEL-12/implementar-um-padrao-de-modularidade-melhor-ao-sistema) ainda não vale: ela diz que cada módulo é responsável pelas suas rotas e pelas suas migrations, e hoje ele não é. Os 8 módulos com rotas (`backoffice`, `catalog`, `customers`, `fulfillment`, `identity`, `inventory`, `ordering`, `payment`) continuam num único `backend/routes/api.php` de 40 declarações, que importa 18 controllers; as 7 regras de UUID dos parâmetros (`product`, `category`, `stock`, `user`, `customer`, `order`, `address`) continuam no `register()` do `AppServiceProvider`; e as 14 migrations continuam todas em `backend/database/migrations`, 11 delas de tabelas de um módulo só. Quem cria o oitavo ou o décimo primeiro módulo ainda edita a raiz em três lugares (o arquivo de rotas, a lista de padrões e a pasta de migrations), que é o custo que a HEL-12 queria acabar. A PR 27 deixou isso de fora de propósito (slices Module Routes e Module Migrations do [design](../../../.design/modular-layout.md)), e a comparação de carga antes e depois (slice Load Baseline) também não foi feita.

Evidência: 40 declarações em um arquivo, 18 controllers de 8 módulos, 7 padrões, 11 de 14 migrations de módulo (contados no repositório em 2026-10-19, HEAD 7c530e4). Não há medida de dor de equipe: o repositório tem um só autor.

Quando isto entregar, um módulo novo nasce sem editar a raiz além do `composer.json` e do `composer.lock`, e o mapa das rotas e do banco de cada contexto está na pasta dele.

## Flow

Reaproveita o `RoutesPlugin` e o `MigratorPlugin` do `internachi/modular`, que já descobrem `app-modules/*/routes/*.php` e `app-modules/*/database/migrations` (o spike da Library Spike provou os dois com o Payment), e os testes que percorrem `Route::getRoutes()` como oráculo.

Rotas:

1. request `/api/...` -> `bootstrap/app.php` (exists) - deixa de apontar para um arquivo de rotas da API; mantém `health` e `commands`
2. `RoutesPlugin` (exists, biblioteca) - faz `require` de `app-modules/<módulo>/routes/*.php` em ordem de módulo, antes do `boot()` dos providers da aplicação
3. `app-modules/<módulo>/routes/api.php` (new, door 1) - declara `/api` e o grupo `api`, e as rotas do módulo com o mesmo middleware, nome e contador de `throttle`
4. `<Módulo>ServiceProvider::register()` (exists) - registra o padrão de UUID dos parâmetros que o módulo é dono (door 3)
5. out: o controller de `Http/Controllers` do mesmo módulo (exists), sem mudança

Migrations:

1. `php artisan migrate` -> `Migrator` (exists) - recebe os caminhos da raiz e de cada módulo pelo `MigratorPlugin` (exists, biblioteca)
2. `app-modules/<módulo>/database/migrations` (new, door 2) - guarda os arquivos das tabelas do módulo, com o nome de hoje
3. out: o migrator ordena todos os caminhos juntos pelo nome, e a tabela `migrations` (exists) reconhece os 14 nomes

Load Baseline: `make load-test` (exists) -> `lib/baseline.js` (exists) -> tabela por transporte do `load-tests/README.md` (exists).

## Impact

| Front | What changes |
| --- | --- |
| domain | termo existente: "arquivo central de rotas" (`routes/api.php`) deixa de existir; quem ramifica nele: `bootstrap/app.php`, o README (4 menções), o `docs/domain-analysis.md` (a decisão "Rotas centralizadas"), um comentário em `load-tests/scenarios/limits.js` |
| domain | termo existente: os padrões de UUID eram do `AppServiceProvider` e passam a ser do provider do módulo dono do parâmetro; quem ramifica nele: `RouteBindingTest`, `UuidIdsTest`, o `AGENTS.md` (regra do id) |
| domain | termo existente: "pasta global de migrations" passa a ser "a pasta do módulo dono", com 3 arquivos de framework na raiz (`cache`, `jobs`, `features`); quem ramifica nele: o README, o `AGENTS.md`, a ordem das 5 chaves estrangeiras entre módulos |
| stored data | nada a migrar: nenhuma tabela, coluna ou nome de migration muda. A tabela `migrations` guarda o nome do arquivo, e um nome renomeado reexecutaria o SQL; os 11 arquivos movidos mantêm nome e conteúdo |

## Relations

`None - no stored-data shape change`

## Surface

`None - nothing consumed outside`

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| o arquivo de rotas do módulo | `app-modules/<kebab>/routes/api.php` com `Route::prefix('api')->middleware('api')->group(function (): void { ... });` e as rotas dentro, sem `Route::pattern`; o `api` não vem do framework porque a biblioteca só faz `require` do arquivo | um helper no `Shared` que embrulha o grupo: esconde as duas linhas que decidem prefixo e `statefulApi` de cada módulo, e cada arquivo de rota passaria a depender do `Shared`; ganha se mais de uns 10 módulos repetirem o envelope |
| o lugar e o nome de cada migration | `app-modules/<kebab>/database/migrations/<nome de hoje>.php`, movido com `git mv` e sem editar; os 11 nomes e donos estão em `Key placement` abaixo | renomear com timestamp novo ou dividir o arquivo `0001_01_01_000000_create_users_table` (3 tabelas): a tabela `migrations` guarda o nome, então reexecutaria o SQL numa base já migrada |
| o dono do padrão de UUID | `Route::pattern('<parâmetro>', RecordId::PATTERN)` no `register()` do `<Módulo>ServiceProvider` dono (`product` e `category` no Catalog, `stock` no Inventory, `user` no Identity, `customer` e `address` no Customers, `order` no Ordering) | no `boot()`: o spike mostrou que a rota copia os padrões globais ao ser criada e a biblioteca a cria antes do `boot()` (14 testes falharam). Na lista do `AppServiceProvider`: some a vantagem de o módulo ser dono do que declara |

Key placement (as 11 migrations que se movem, por tabela): `users`, `password_reset_tokens`, `sessions` e `customers` no Identity; `categories`, `products`, `category_product` no Catalog; `stocks` no Inventory; `orders`, `order_items` no Ordering; `payments` no Payment; `customer_addresses` no Customers; `outbox_messages` no Messaging. Ficam na raiz `cache`, `jobs` e `features`.
| o caminho do cache de módulos | o `AppServiceProvider::register()` religa o `InterNACHI\Modular\Support\Cache` com o caminho `APP_MODULES_CACHE` quando a variável existe, e `bootstrap/cache/app-modules.php` quando não existe (o mesmo critério do `APP_CONFIG_CACHE` do framework); a pilha de carga define `APP_MODULES_CACHE=/tmp/laravel/app-modules.php` e roda `modules:cache` | rodar `modules:cache` sem desviar o caminho: grava `bootstrap/cache/app-modules.php`, que a API de dev também lê, e um módulo novo sumiria do dev até alguém limpar o cache. Tirar a descoberta do caminho quente por outro meio (opcache preload, classmap): não corta o `Finder` do filesystem a cada boot, e não foi medido. Ganha o cache sem desvio se o dev também rodasse `modules:cache` a cada troca de branch, o que o `AGENTS.md` não pede. *Porta acrescentada na rodada 2 (2026-10-20), depois do Load Baseline reprovar (ver `load-tests/README.md`).* |

- Nada mais nesta entrega é difícil de reverter

## Criteria

### S1: as rotas moram no módulo (P1)

Oito arquivos de rotas em `app-modules`, nenhum arquivo central, e a API responde igual.

**Acceptance Criteria**

1. WHEN `php artisan route:list --json` roda THEN a lista SHALL conter os mesmos 73 itens da lista de `7c530e4`, comparando URI, método, nome, ação e middleware, sem considerar a ordem
2. The system SHALL NOT conter `backend/routes/api.php`, e `backend/bootstrap/app.php` SHALL NOT passar `api:` a `withRouting`
3. The system SHALL declarar as rotas de `backoffice`, `catalog`, `customers`, `fulfillment`, `identity`, `inventory`, `ordering` e `payment` cada uma em `backend/app-modules/<módulo>/routes/api.php`, e `shared` e `messaging` SHALL NOT ter pasta `routes`
4. IF um arquivo de rotas de módulo nomeia um controller fora de `Modules\<Módulo>\Http\Controllers` desse mesmo módulo THEN um teste de arquitetura SHALL falhar
5. The system SHALL NOT repetir nenhum par método e URI entre as rotas listadas por `route:list`
6. The system SHALL registrar os padrões `product` e `category` no `register()` do `CatalogServiceProvider`, `stock` no do `InventoryServiceProvider`, `user` no do `IdentityServiceProvider`, `customer` e `address` no do `CustomersServiceProvider` e `order` no do `OrderingServiceProvider`, e o `AppServiceProvider` SHALL NOT chamar `Route::pattern`
7. IF um parâmetro de rota de `/api` não tem o padrão canônico THEN o `RouteBindingTest` SHALL falhar, e a suíte atual SHALL continuar rodando-o sobre as rotas dos módulos

**Independent test:** apagar o `backend/routes/api.php`, rodar `route:list --json` e comparar com a lista de `7c530e4`; mudar um padrão para o `boot()` e ver o `RouteBindingTest` e o `UuidIdsTest` falharem.

### S2: as migrations moram no módulo (P1)

Onze arquivos em `app-modules/<módulo>/database/migrations`, três na raiz, o banco igual.

**Acceptance Criteria**

8. The system SHALL manter em `backend/database/migrations` só `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php` e `2026_10_18_170032_create_features_table.php`, e as outras 11 SHALL estar em `app-modules/<módulo dono>/database/migrations` com o nome e o conteúdo byte a byte de `7c530e4`
9. WHEN `php artisan migrate:status` roda num banco já migrado em `7c530e4` THEN ele SHALL listar os mesmos 14 nomes como `Ran`, nenhum `Pending`, e `php artisan migrate` SHALL responder `Nothing to migrate.`
10. WHEN `php artisan migrate:fresh` roda num banco vazio THEN ele SHALL terminar com exit 0, listar os mesmos 14 nomes como `Ran` na mesma ordem de `7c530e4`, e existirem as chaves estrangeiras `orders.customer_id`, `order_items.product_id`, `stocks.product_id`, `payments.order_id` e `customer_addresses.customer_id`
11. IF o conjunto de nomes de migration dos caminhos de migration difere dos 14 nomes congelados num teste THEN esse teste SHALL falhar

**Independent test:** `migrate:status` num banco migrado antes e depois do `git mv`, e `migrate:fresh` numa base nova.

### S3: a carga não piorou (P1)

**Acceptance Criteria**

12. WHEN `make load-test TYPE=smoke` roda THEN ele SHALL terminar com exit 0 e os containers de carga SHALL NOT criar nem alterar nenhum arquivo em `backend/bootstrap/cache`
13. WHEN `make load-test TYPE=load` roda no commit final THEN ele SHALL terminar com exit 0, o que exige p95 da vitrine até 40 ms, de `POST /api/orders` até 70 ms, da `order_payable_wait` até 2.450 ms e 0% de erro inesperado
14. The `load-tests/README.md` SHALL ganhar na tabela "Linha de base por transporte" uma linha `redis`, `load`, do commit final, só com duas rodadas seguidas cujo p95 difere menos de 20% sobre o menor, e a coluna "Mudou desde a série anterior" SHALL nomear a HEL-12
15. The `load-tests/README.md` SHALL registrar o custo de descobrir os módulos a cada boot sem o cache de módulos, como a mediana em ms de 20 execuções de `php artisan --version` sem `modules:cache` e com ele, medidas num worktree descartável
21. The system SHALL ler o caminho do cache de módulos de `APP_MODULES_CACHE` quando a variável existe e de `bootstrap/cache/app-modules.php` quando não existe, e `modules:cache` com a variável definida SHALL gravar o arquivo nesse caminho e nenhum arquivo em `bootstrap/cache`
22. WHILE a pilha de carga está de pé, o `Compose` de carga SHALL definir `APP_MODULES_CACHE=/tmp/laravel/app-modules.php` nos 4 serviços PHP e o `make load-test` SHALL rodar `modules:cache` no container `api` antes do k6


**Independent test:** `make load-test TYPE=load` duas vezes seguidas e comparar com a linha de `99094d7`.

### S4: o resto não mudou e a documentação diz como ficou (P1)

**Acceptance Criteria**

16. WHEN `make test` roda THEN nenhum teste SHALL falhar, e todo arquivo de teste fora de `tests/Unit/Architecture` SHALL ter a mesma contagem de testes de `7c530e4` (2014 testes na soma)
17. WHEN `make pint` roda THEN ele SHALL terminar com exit 0
18. The system SHALL NOT mencionar `backend/routes/api.php` nem `routes/api.php` fora de um caminho `app-modules/<módulo>/routes/api.php` no `README.md`, no `AGENTS.md`, em `docs/domain-analysis.md`, em `load-tests/` e em `backend/`, e todo link relativo do `README.md`, do `AGENTS.md` e de `docs/domain-analysis.md` SHALL apontar para um arquivo que existe
19. The `AGENTS.md` SHALL declarar que as rotas de um módulo ficam em `routes/api.php` do pacote dentro de `Route::prefix('api')->middleware('api')`, que o padrão de UUID de um parâmetro é registrado no `register()` do provider do módulo dono, e que as migrations de um módulo ficam no pacote e nunca são renomeadas nem editadas, com `cache`, `jobs` e `features` na raiz
20. The `docs/domain-analysis.md` SHALL marcar os passos Module Routes, Module Migrations e Load Baseline do plano de evolução como concluídos com a data, trocar a decisão "Rotas centralizadas" pela nova, e atualizar a linha "Última atualização"

**Independent test:** `make test`, `make pint` e um `grep` por `routes/api.php` fora do caminho do módulo.

## Out of scope

| Excluded | Why |
| --- | --- |
| rotas de console por módulo (`routes/console.php`) | não é HTTP, e o arquivo é do framework; a Out do design fica |
| seeders por módulo | o `DatabaseSeeder` e os seeders ficam na raiz (decisão do design) |
| dividir o arquivo da `users` para tirar `sessions` do Identity | mudaria uma migration já executada; o README anota que `sessions` mora no Identity só por causa do arquivo |
| medir `load` com `TRANSPORT=rabbitmq` de novo | a mudança desta entrega não toca o transporte; a linha do rabbitmq de `99094d7` continua valendo |
| `modules:cache` no `make optimize` | o ganho é uma otimização que a medida do AC 15 vai dizer se vale. (Na pilha de carga ele entrou na rodada 2, 2026-10-20, pela porta 4 da Landing: o Load Baseline reprovou sem ele.) |
| mesclar a branch da PR 27 ou fechar a HEL-12 no Linear | o merge e o estado da issue são do dono, depois da revisão |
| mover os testes para dentro dos módulos | o Pest separa por tipo (Out do design) |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| profile | `standard` | 11 arquivos movidos, 8 arquivos de rotas escritos e um teste de arquitetura novo: o `light` não percebe um teste que passa sob uma implementação errada | n |
| linha de base de rotas, migrations e testes | o HEAD `7c530e4` da PR 27, e não o `f1dd960` de `main` | a árvore de `7c530e4` já está verificada contra `main` (PASS da spec `module-layout`), e ele é o ponto de partida desta branch | n |
| entrega | branch `helterdias/hel-12-module-routes-migrations`, empilhada sobre a da PR 27, uma spec e uma PR contra a branch da PR 27 | resposta do dono, 2026-10-19 | y |
| `sessions` e `password_reset_tokens` | ficam com `users` no Identity | decisão do design, 2026-10-19 (Module Migrations, default tomado) | y |
| nome do arquivo de rotas | `routes/api.php` em cada pacote | o nome que o Laravel usa, e a biblioteca carrega qualquer `*.php` da pasta | n |
| ordem de carga das rotas | a ordem dos módulos da biblioteca, sem depender dela | o AC 5 recusa par método e URI repetido, então a ordem só mudaria o `route:list`, e o AC 1 ignora a ordem | n |
| a linha do `load` | o `redis`, no commit final, comparada com o limite da `lib/baseline.js` (AC 13) e a linha de `99094d7` (AC 14) | o limite é o p95 de 2026-10-16 vezes 1,2, e a tabela por transporte é a que a HEL-13 abriu | n |
| a máquina da medição | a mesma Apple M1 com 4 GB de VM, sem outros aplicativos pesados, com a pilha de dev parada | a regra do `load-tests/README.md` para aceitar uma linha | n |

**Open questions:** none - all resolved or logged above.

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| all `/api` routes | error shape and codes, versioning, rate limits, quem pode chamar | existing - nada muda, provado pela igualdade do `route:list` (AC 1) e pela suíte inteira (AC 16) |
| comando `php artisan route:list` | saída | existing - AC 1 |
| comando `php artisan migrate` e `migrate:fresh` | saída e exit code | AC 9 e 10 |
| comando `make load-test` | saída e exit code | AC 12 e 13 |
| documentos `README.md`, `AGENTS.md`, `docs/domain-analysis.md`, `load-tests/README.md` | estrutura, o que o leitor faz depois, links | AC 14, 15, 18, 19 e 20 |
| mensagem de falha dos testes de arquitetura | diz qual regra e qual arquivo violou | AC 4, 6 e 11, no formato das expectativas do Pest |

## Sources

- [HEL-12 no Linear](https://linear.app/helter/issue/HEL-12/implementar-um-padrao-de-modularidade-melhor-ao-sistema) - pede rotas e migrations por módulo.
- [.design/modular-layout.md](../../../.design/modular-layout.md) - Key decisions 4 e 5 e as slices Module Routes, Module Migrations e Load Baseline, confirmadas em 2026-10-19; rodada 2 registra esta entrega.
- [.specs/features/module-layout/plan.md](../module-layout/plan.md) e [verification.md](../module-layout/verification.md) - a base verificada (HEAD 7c530e4) e as provas de rotas e migrations que esta spec repete.
