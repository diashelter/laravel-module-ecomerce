# Testes de carga com k6 (HEL-14)

## Problem

Hoje ninguém sabe quanto tempo cada rota da API leva sob concorrência, nem quantas requisições ela atende antes de degradar. A única medição é a de um `curl` isolado em 2026-10-16: `GET /api/products` leva de 25 a 40 ms com a aplicação aquecida.

O Pest prova as regras uma requisição por vez, então três garantias nunca foram observadas com concorrência real:

- o lock de estoque do checkout, com vários clientes disputando as últimas unidades;
- os limites de requisição sob rajada;
- o atraso do outbox entre o pedido criado e o pedido que pode ser pago.

Quem paga é o próprio projeto. A HEL-12 vai reorganizar rotas, consultas e migrations de todos os módulos, e sem uma medição anterior não há como dizer se ela mudou o desempenho. A HEL-6 afirmou que o tamanho da página não muda o número de queries, mas o tempo nunca foi medido. A issue não traz números, porque é um objetivo de estudo.

Quando isto estiver entregue:
- um comando roda cada tipo de teste a partir de um estado limpo, sem tocar nos dados de dev, e mostra o p95, as req/s e os erros inesperados por rota;
- a primeira linha de base fica registrada no repositório;
- o checkout, o limitador e o outbox ficam provados sob concorrência;
- o smoke roda no CI e falha quando uma rota ou um payload muda sem o script acompanhar.

## Flow

Reaproveita, em vez de duplicar, a imagem `ecommerce-php` e o seu entrypoint (exists), o estilo do `Makefile` (exists), as factories com o `ShippingQuoter` e o `DeliveryCalendar` (exist) que o `OrderSeeder` já usa para montar pedidos, o `AssignRequestId` (exists), que aceita o `X-Request-ID` do cliente, e o padrão do CI (exists) de rodar o mesmo Compose e o mesmo `Makefile` do ambiente local.

1. `make load-test TYPE=<perfil>` -> `Makefile` (exists): recusa um perfil desconhecido sem subir nada, apaga os containers e os volumes do projeto Compose `ecommerce-load` (door 1) e sobe o Postgres e o Redis dele.
2. `api` do projeto de carga (door 1): roda as migrations do zero e o `LoadTestSeeder` (new, no door - placement per conventions; a forma dos dados é o door 7) no banco de carga. Depois grava os caches de config, rotas e eventos fora da árvore compartilhada (door 2).
3. `api`, `queue-worker`, `outbox-relay` e `nginx` do projeto de carga (door 1) sobem com a configuração de produção (door 3) e com o `CACHE_LIMITER` que o perfil escolhe (door 4).
4. O `k6` do projeto de carga (door 5) roda o perfil contra o `nginx` de carga. Os perfis de volume dividem os VUs em 75% vitrine, 20% compra e 5% equipe. O `contention` e o `limits` rodam cenários próprios. As métricas seguem o door 6.
5. Os módulos `Catalog`, `Ordering`, `Payment`, `Customers`, `Identity`, `Inventory`, `Fulfillment` e `Backoffice` (exist) atendem como hoje. O checkout grava o `OrderPlaced` no outbox, e o `outbox-relay` e o `queue-worker` de carga (exist) levam o pedido a `awaiting_payment`. O k6 consulta o pedido até lá.
6. out:
   - o resumo no terminal;
   - os relatórios HTML e JSON na pasta `results` (new, no door - placement per conventions), mais o `<perfil>-checks.txt` que as provas leem;
   - o código de saída do `make`, diferente de zero quando um limite de aprovação cai;
   - a tabela da linha de base em `load-tests/README.md` (door 6), preenchida à mão com os resultados aceitos.
7. CI: um job novo no `.github/workflows/ci.yml` (exists), paralelo ao `backend`, roda o passo 1 com `TYPE=smoke` e apaga a pilha no fim.

## Impact

| Front | What changes |
| --- | --- |
| config | `config/cache.php` ganha a chave `limiter`, lida de `CACHE_LIMITER`. Sem a variável ela é `null` e o `RateLimiter` usa o armazenamento padrão, como hoje. Quem depende do limitador hoje: as sete rotas com `throttle` e o teste "throttles registration and both logins after 10 attempts per minute" do `AuthTest`, que não muda |
| env | `backend/.env.example` documenta `CACHE_LIMITER`, vazia em dev. O `.env.example` da raiz não muda: o projeto de carga declara as próprias variáveis |
| env | a árvore compartilhada: a `api` de carga lê o mesmo `backend/.env` de dev. Toda variável que separa as pilhas (banco, Redis, sessão, Sanctum, debug, log, caches e limitador) precisa vir do Compose de carga, porque a variável de ambiente prevalece sobre o `.env` |
| docker | pasta nova `load-tests/`: o Compose do projeto `ecommerce-load`, o ini do PHP e o pool do FPM de carga, os scripts do k6, o `README.md` e `results/`, que fica ignorada pelo git. O `docker-compose.yml` de dev e o `docker/` não mudam |
| Makefile | dois alvos novos, `load-test` e `load-down`, com `##`. Nenhum alvo existente muda |
| CI | um job novo no `.github/workflows/ci.yml`. O job `backend` não muda |
| seeders | `LoadTestSeeder` novo. O `DatabaseSeeder`, o `OrderSeeder`, o `SeederTest` e o `make fresh` não mudam |
| stored data | nothing to migrate: nenhuma tabela, coluna ou índice muda, e o banco de carga é descartável a cada rodada |
| docs | README: comandos do `Makefile`, configuração do `.env` (`CACHE_LIMITER`), testes (uma seção "Testes de carga (k6)" com os oito perfis, os limites de aprovação e o link da linha de base), containers (o projeto de carga) e o sumário. `AGENTS.md`: a regra de não gravar na árvore compartilhada a partir do container de carga, e o smoke de carga no checklist. `load-tests/README.md` novo, com a tabela da linha de base. A análise de domínio não muda, porque nenhum contexto muda |
| branches | as correções do `X-Forwarded-For` e do contador por rota entraram na `main` pelos PRs [#17](https://github.com/diashelter/laravel-module-ecomerce/pull/17) e [#18](https://github.com/diashelter/laravel-module-ecomerce/pull/18) e foram trazidas para esta branch no merge `d851b01`, com os conflitos do README e do `AGENTS.md` resolvidos mantendo os dois lados |

## Relations

None - no stored-data shape change. O `LoadTestSeeder` grava nas tabelas que já existem, com as relações de hoje.

## Surface

None - nothing consumed outside changes. Nenhuma rota da API muda de entrada, saída ou código. A interface nova é o comando `make load-test`, que está em `## Observable` e no S2.

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| 1. a pilha de carga isolada | Projeto Compose `ecommerce-load` (`name:` no arquivo `load-tests/docker-compose.yml`), com os serviços `db`, `redis`, `api`, `queue-worker`, `outbox-relay`, `nginx` e `k6`, a rede e os volumes nomeados próprios. Compartilha com dev só a imagem `ecommerce-php:latest` (mesmo `build` de `docker/php/Dockerfile`) e o bind mount `../backend`. Na `api` de carga, `DB_HOST=db`, `REDIS_HOST=redis`, `SESSION_DOMAIN` vazio, `SANCTUM_STATEFUL_DOMAINS=nginx` e `APP_URL=http://nginx` | Perfis do Compose no `docker-compose.yml` de dev: a rede e o `down -v` passam a ser compartilhados, e um `make down` de dev derruba a carga. Um segundo banco no Postgres de dev: a carga disputa o servidor com os dados de dev, e o Redis (filas, sessões e cache) continuaria compartilhado |
| 2. nada gravado na árvore que dev lê | Na `api`, no `queue-worker` e no `outbox-relay` de carga: `APP_CONFIG_CACHE=/tmp/laravel/config.php`, `APP_ROUTES_CACHE=/tmp/laravel/routes-v7.php`, `APP_EVENTS_CACHE=/tmp/laravel/events.php`, `LOG_CHANNEL=stderr` e `LOG_LEVEL=warning`. Os caches são gerados por `config:cache`, `route:cache` e `event:cache` dentro do container, nunca por `optimize` com os caminhos padrão | O `optimize` de sempre: grava `bootstrap/cache/config.php` na árvore, e a API de dev passaria a ler o banco de carga. Rodar sem cache: cada requisição monta a configuração de novo, o que produção não faz |
| 3. a configuração de produção da linha de base | `APP_ENV=production` (o `preventLazyLoading` fica desligado, como em produção), `APP_DEBUG=false`, `BCRYPT_ROUNDS=12`, `ORDER_DELIVERY_DELAY_SECONDS=10`, `opcache.validate_timestamps=0` num ini montado depois do `zz-app.ini`, e o pool do FPM com `pm = static` e `pm.max_children = 16` num arquivo montado depois do `zz-docker.conf` | A configuração de dev: o pool de 5 workers, o debug e o opcache revalidando a cada requisição no bind mount do macOS dominariam o tempo de qualquer rota. Uma imagem de produção imutável com limites de CPU: só ganha para comparar máquinas ou estimar produção, e nenhuma das duas está no escopo |
| 4. o armazenamento do limitador por ambiente | `'limiter' => env('CACHE_LIMITER')` em `config/cache.php`. O projeto de carga usa `CACHE_LIMITER=array` em `load`, `stress`, `spike`, `breakpoint`, `soak` e `contention`, e deixa a variável sem valor em `smoke` e `limits`. Os `throttle` das rotas continuam literais | Limitadores nomeados com valores da configuração: reescrevem as sete rotas que a HEL-12 vai mover, para um valor que só a carga muda. `CACHE_STORE=array`: desliga também o sinal do `queue:restart` e tudo o mais que usa o cache. Forjar o `X-Forwarded-For` para espalhar as chaves: é a falha corrigida em `helterdias/fix-forged-client-ip` |
| 5. o k6 como dependência | A imagem `grafana/k6:2.3.0` como o serviço `k6` do projeto de carga, com os scripts em JavaScript em `load-tests/`. O relatório HTML sai de `K6_WEB_DASHBOARD=true` com `K6_WEB_DASHBOARD_EXPORT`, e o JSON do `handleSummary`. Nenhuma extensão `xk6` | Instalar o k6 na máquina: o `AGENTS.md` pede tudo no Docker. Grafana com InfluxDB ou Prometheus: são três containers a mais que nenhuma pergunta desta rodada pede |
| 6. os nomes que a linha de base compara | As métricas por template de rota, na tag `name` (por exemplo `GET /api/orders/{order}`), nunca pela URL. A tendência personalizada `order_payable_wait`, em ms, e o contador `rate_limit_overshoot`. Na tabela de `load-tests/README.md`, as colunas: data, commit, máquina, perfil, VUs ou taxa, req/s, p95 da vitrine, p95 de `POST /api/orders`, p95 de `order_payable_wait`, erro inesperado, ponto de ruptura e o que mudou desde a série anterior | Métricas por URL: uma série por UUID, e nenhuma soma comparável entre rodadas. Renomear depois da primeira linha tira a comparação |
| 7. o conjunto de dados da linha de base | `LoadTestSeeder` com `fake()->seed(2026)`, como o `DatabaseSeeder`, e os volumes de S1. Mudar um volume abre uma série nova na tabela | O seed de dev: 10 clientes e 30 produtos, o estoque acaba em segundos, e as rodadas não partem do mesmo estado |

- Nothing else in this change is hard to reverse: o nome dos arquivos de script, a divisão dos módulos JS, o caminho do ini e do pool, o nome do job de CI e o texto do `README.md` da pasta ficam para o diff.

## Criteria

### S1: LoadTestSeeder (P1)

Toda rodada parte do mesmo conjunto de dados, grande o bastante para a vitrine, a compra e o painel.

**Acceptance Criteria**

1. WHEN the `LoadTestSeeder` runs on an empty database THEN the system SHALL hold exactly 1,000 customers with the emails `load-customer-0@example.com` to `load-customer-999@example.com` and the password `password`, each with exactly one address
2. WHEN the `LoadTestSeeder` runs THEN the system SHALL hold exactly two staff members, `admin@example.com` with the `admin` role and `suporte@example.com` with the `support` role, with the password `password`
3. WHEN the `LoadTestSeeder` runs THEN the system SHALL hold 8 categories and 201 products: 170 active with 1,000,000 units each, 10 active with 0 units, 20 inactive with 100 units each and one active product named `Produto Disputado` with exactly 10 units
4. WHEN the `LoadTestSeeder` runs THEN the system SHALL hold 10,000 orders created in the last 12 months, each with 1 to 4 items, the copy of an address of its customer, the shipping of the rate table, and an approved payment when the status is `payment_approved` or `delivered`, and at least one of them SHALL belong to `load-customer-0@example.com`
5. WHEN the `LoadTestSeeder` runs THEN the stock of every product SHALL equal the quantity stated in AC 3, and `outbox_messages` SHALL hold 0 rows
6. WHEN the `LoadTestSeeder` runs twice on empty databases THEN both runs SHALL produce the same customer names, product names, prices and order totals in the same order
7. The `DatabaseSeeder` SHALL not call the `LoadTestSeeder`
8. The `LoadTestSeeder` SHALL finish in at most 90 seconds inside the load project

**Independent test:** rodar `migrate:fresh` e o `LoadTestSeeder` no banco de carga e contar as linhas de cada tabela.

### S2: Load stack (P1)

Um comando sobe a pilha de carga ao lado da de dev, roda um perfil e entrega os relatórios sem tocar em nada de dev.

**Acceptance Criteria**

9. WHEN `make load-test TYPE=<profile>` runs with a profile among `smoke`, `load`, `stress`, `spike`, `breakpoint`, `soak`, `contention` and `limits` THEN the system SHALL remove the containers and volumes of the Compose project `ecommerce-load`, start its `db` and `redis`, run `migrate:fresh` and the `LoadTestSeeder` on its database, and only then start k6
10. IF `make load-test` runs with `TYPE` missing or not among the 8 profiles THEN the system SHALL print the 8 valid profiles, start no container and exit with a non-zero code
11. WHEN a profile run ends THEN the system SHALL write `load-tests/results/<profile>-<YYYYMMDD-HHMMSS>.html` and `load-tests/results/<profile>-<YYYYMMDD-HHMMSS>.json`, and `git status` SHALL list no file under `load-tests/results/`
12. IF a threshold of the profile fails THEN `make load-test` SHALL exit with a non-zero code and SHALL still write both report files
13. IF the load project's migration or seeding fails THEN `make load-test` SHALL not start k6, SHALL print the logs of the load project's `api` and SHALL exit with a non-zero code
14. WHEN `make load-down` runs THEN the system SHALL remove every container and volume of the project `ecommerce-load` and SHALL leave every container and volume of the project `ecommerce` in place
15. WHEN any profile run ends THEN the dev database SHALL hold the same number of rows in `customers`, `orders`, `products`, `stocks` and `outbox_messages` as before the run, and `backend/bootstrap/cache` SHALL list the same files as before the run
16. WHILE the load project runs, its `api` SHALL report `APP_ENV` `production`, `APP_DEBUG` `false` and the log channel `stderr` at level `warning`
17. WHILE the load project runs, its `api` SHALL report `opcache.validate_timestamps` `0`, and its configuration, route and event caches SHALL exist under `/tmp/laravel` and not under `/var/www/backend/bootstrap/cache`
18. WHILE the load project runs, its PHP-FPM pool SHALL run with `pm = static` and `pm.max_children = 16`
19. WHERE `CACHE_LIMITER` is `array` the system SHALL answer the 11th `POST /api/auth/login` from the same client within one minute with a status other than `429`
20. WHERE `CACHE_LIMITER` is unset the system SHALL answer the 11th `POST /api/auth/login` from the same client within one minute with `429`
21. WHEN `make load-test` runs the profiles `load`, `stress`, `spike`, `breakpoint`, `soak` or `contention` THEN the load project's `api` SHALL run with `CACHE_LIMITER=array`, and WHEN it runs `smoke` or `limits` THEN `CACHE_LIMITER` SHALL be unset

**Independent test:** com a pilha de dev no ar, contar as linhas do banco de dev, rodar `make load-test TYPE=smoke`, ver os dois relatórios em `results/` e contar de novo.

### S3: Storefront (P1)

O visitante anônimo passa pela vitrine, e cada resposta é conferida pelo status que ela deveria ter. Em S3 a S6, "aceitar" quer dizer que a resposta não conta como erro inesperado (Key decision 6 do design), e qualquer outro status conta como erro.

**Acceptance Criteria**

22. WHEN the storefront flow lists `GET /api/products` with a valid page, category and sort THEN k6 SHALL accept only `200`
23. WHEN the storefront flow sends `GET /api/products?sort=preco` THEN k6 SHALL accept only `422` with `code` `VALIDATION_FAILED`
24. WHEN the storefront flow asks a page after the last one THEN k6 SHALL accept only `200` with an empty `data` list
25. WHEN the storefront flow opens an active product with units THEN k6 SHALL accept only `200`
26. WHEN the storefront flow opens an inactive product, a product with 0 units, the malformed id `1` or an unknown canonical UUID THEN k6 SHALL accept only `404` with `code` `NOT_FOUND`
27. WHEN the storefront flow asks `GET /api/shipping/quote` for a known state, or for `ZZ`, THEN k6 SHALL accept only `200`, or only `422`
28. WHEN the storefront flow validates a cart of available products THEN k6 SHALL accept only `200` with no problem on any line
29. WHEN the storefront flow validates a cart holding an inactive product or a product with 0 units THEN k6 SHALL accept only `200` with that line holding `is_available` `false`
30. WHEN the storefront flow registers a new email, or an email already registered, THEN k6 SHALL accept only `201`, or only `422`
31. WHILE a volume profile runs, the storefront flow SHALL register in 1% of its iterations

**Independent test:** rodar o `smoke` e ler no JSON um check por linha acima, todos com 100%.

### S4: Checkout (P1)

O cliente compra como o frontend compra, e a espera até poder pagar vira um número.

**Acceptance Criteria**

32. WHEN a checkout VU starts its first iteration THEN it SHALL fetch `GET /sanctum/csrf-cookie` and log in as `load-customer-<n>@example.com` with `n` between 1 and 999, and SHALL keep that session until the run ends
33. WHEN the checkout flow places an order of available products to its own address THEN k6 SHALL accept from `POST /api/orders` only `201`
34. WHEN the checkout flow places an order holding a product with 0 units, in 5% of its iterations, THEN k6 SHALL accept only `409` with `code` `INSUFFICIENT_STOCK`
35. WHEN the checkout flow sends a malformed `address_id`, or the address of another customer, THEN k6 SHALL accept only `422` with `errors.address_id`
36. WHEN an order was created THEN the checkout flow SHALL read `GET /api/orders/{order}` every 2 seconds until its `status` is `awaiting_payment`, and SHALL record the time from the `201` to that read in `order_payable_wait`
37. IF an order is not `awaiting_payment` 60 seconds after its `201` THEN the checkout flow SHALL stop that order, SHALL not pay it and SHALL count one unexpected error
38. The checkout flow SHALL never send `POST /api/orders/{order}/payment` for an order it has not read as `awaiting_payment`
39. WHEN the checkout flow pays with `fake_card_approved`, in 90% of its orders, THEN k6 SHALL accept only `202`
40. WHEN the checkout flow pays with `fake_card_declined` and then with `fake_card_approved`, in 10% of its orders, THEN k6 SHALL accept only `402` with `code` `PAYMENT_DECLINED` and then `202`
41. WHEN the checkout flow pays an order it already paid, or sends no `card_token`, THEN k6 SHALL accept only `409`, or only `422`
42. WHEN the checkout flow reads an order of `load-customer-0@example.com`, or reads its own order with no session, THEN k6 SHALL accept only `404`, or only `401`
43. WHEN the checkout flow reads `GET /api/account` and `GET /api/orders` THEN k6 SHALL accept only `200`
44. WHEN the checkout flow adds an address and then deletes it THEN k6 SHALL accept only `201` and then `204`
45. WHEN the checkout flow sends `PUT /api/account/addresses/{address}` for an address of another customer THEN k6 SHALL accept only `404`

**Independent test:** rodar o `smoke` e ver no JSON a `order_payable_wait` com ao menos uma amostra e os checks acima com 100%.

### S5: Backoffice (P2)

A equipe usa o painel ao mesmo tempo que a loja compra.

**Acceptance Criteria**

46. WHEN a backoffice VU starts its first iteration THEN it SHALL log in through `POST /api/admin/auth/login`, as `admin@example.com` on odd VUs and as `suporte@example.com` on even VUs
47. WHEN the backoffice flow reads `GET /api/admin/dashboard`, pages 1 to 3 of `GET /api/admin/orders`, one `GET /api/admin/orders/{order}`, `GET /api/admin/stocks` and `GET /api/admin/products` THEN k6 SHALL accept, for each response, only `200`
48. WHEN the backoffice flow adds 1 unit to the stock of an available product, in 2% of its iterations, THEN k6 SHALL accept from `PUT /api/admin/stocks/{stock}` only `200`
49. WHEN the support member sends `DELETE /api/admin/products/{product}` THEN k6 SHALL accept only `403`, and the product SHALL still open with `200`
50. WHEN the backoffice flow opens an unknown canonical UUID in `GET /api/admin/products/{product}` THEN k6 SHALL accept only `404`
51. WHEN a customer session reads `GET /api/admin/dashboard`, or a staff session reads `GET /api/orders`, THEN k6 SHALL accept only `401`

**Independent test:** rodar o `smoke` e ver os checks da equipe com 100%.

### S6: Concurrency scenarios (P1)

Duas garantias que só aparecem com concorrência real ficam provadas: o estoque não é vendido além do que existe, e cada rota limitada recusa o excesso.

**Acceptance Criteria**

52. WHEN 50 VUs of 50 different customers, all logged in before the burst, each place one order of 1 unit of `Produto Disputado` at the same time THEN exactly 10 responses SHALL be `201`, exactly 40 SHALL be `409` with `code` `INSUFFICIENT_STOCK`, and none SHALL be `5xx`
53. WHEN the `contention` profile ends THEN `GET /api/admin/stocks` SHALL show `Produto Disputado` with 0 units
54. WHEN 50 pairs of orders are placed at the same time, each pair holding the same two available products in opposite item order THEN all 100 responses SHALL be `201` and none SHALL be `5xx`
55. WHILE the `limits` profile runs, the system SHALL answer the 11th `POST /api/auth/login` with a wrong password, and the 11th `POST /api/auth/register` with an invalid body, from the same client within one minute, with `429` and `code` `TOO_MANY_REQUESTS`
56. WHILE the `limits` profile runs, the system SHALL answer 20 `POST /api/orders` with an invalid body from the same customer within one minute with `422`, and the 21st with `429`, and the same for `POST /api/orders/{order}/payment`
57. WHILE the `limits` profile runs, the system SHALL answer the 61st `POST /api/cart/validate`, and the 61st `GET /api/shipping/quote`, from the same client within one minute with `429`
58. WHEN the `limits` profile fires 30 `POST /api/auth/login` at the same time from the same client THEN it SHALL record in `rate_limit_overshoot` how many were not `429` beyond 10, and SHALL not fail the run on that number
59. The `limits` profile SHALL write no row to `orders`, `payments`, `customers` or `customer_addresses`

**Independent test:** rodar `make load-test TYPE=contention` e `TYPE=limits` e ver os dois com código de saída 0.

### S7: Test profiles (P1)

Os oito perfis têm a forma de carga e os limites de aprovação decididos, e o resultado aceito fica registrado.

**Acceptance Criteria**

60. WHEN the `smoke` profile runs THEN it SHALL run 1 VU per flow for one iteration each, through every state of S3, S4 and S5, and SHALL pass only with 0 unexpected errors and 100% of the checks, with no latency threshold
61. WHEN the `load` profile runs THEN it SHALL ramp from 0 to 50 VUs in 1 minute, hold 50 VUs for 5 minutes and ramp down to 0 in 1 minute
62. WHEN the `stress` profile runs THEN it SHALL ramp from 0 to 150 VUs in 2 minutes, hold 150 for 5 minutes and ramp down in 2 minutes
63. WHEN the `spike` profile runs THEN it SHALL hold 50 VUs, jump to 300 in 10 seconds, hold 300 for 1 minute, return to 50 in 10 seconds and hold 50 for 2 minutes
64. WHEN the `breakpoint` profile runs THEN it SHALL raise the arrival rate from 5 to 500 iterations per second over 15 minutes with the `ramping-arrival-rate` executor, and SHALL abort once unexpected errors exceed 5% or the p95 exceeds 2 seconds, evaluated after a 30-second delay
65. WHEN the `soak` profile runs THEN it SHALL hold 50 VUs for 1 hour
66. WHILE a volume profile runs, it SHALL split its VUs into 20% checkout and 5% backoffice, each rounded down with at least 1 VU, and give the storefront the remainder (about 75%), so the total is the profile's
67. WHILE the `load`, `stress` or `soak` profile runs, it SHALL fail when unexpected errors reach 1%, when the p95 of the storefront reads reaches 500 ms, when the p95 of `POST /api/orders` reaches 1 second, or when the p95 of `order_payable_wait` reaches 10 seconds
68. WHILE the `spike` profile runs, it SHALL fail when unexpected errors reach 5%
69. The k6 metrics SHALL tag every request with its route template in the `name` tag, and SHALL never tag it with a URL holding an id
70. The k6 scripts SHALL send on every request an `X-Request-ID` that names the profile, the VU and the iteration
71. The file `load-tests/README.md` SHALL hold a baseline table with the columns of door 6, and one row each for `load` and `breakpoint` measured at the commit that closes this feature
72. WHEN the first `load` row is recorded THEN the latency thresholds of AC 67 SHALL become the recorded p95 times 1.2, rounded up to the next 10 ms

**Independent test:** rodar o `load` duas vezes no mesmo commit, registrar a linha e rodar o `breakpoint`.

### S8: CI smoke (P2)

Uma rota ou um payload que muda sem o script acompanhar quebra o pull request.

**Acceptance Criteria**

73. WHEN a pull request is opened or updated, or a commit is pushed to `main`, THEN the CI workflow SHALL run a job, separate from the `backend` job, that runs `make load-test TYPE=smoke`
74. IF the smoke profile meets an unexpected status THEN that CI job SHALL fail and its log SHALL show the k6 summary
75. IF the load project does not start THEN that CI job SHALL fail and SHALL print the logs of the load project
76. WHEN that CI job ends, passing or failing, THEN it SHALL run `make load-down`

**Independent test:** rodar localmente os passos do job, numa cópia limpa da árvore, e ver o smoke passar.

## Out of scope

| Excluded | Why |
| --- | --- |
| Otimizar o que os testes encontrarem (pool do FPM de dev, índices, N+1) | Cada achado vira uma issue própria; esta rodada só mede |
| Métricas do servidor (CPU, memória, Postgres, Redis) | São da HEL-8 |
| Imagem de produção imutável | Só serve para comparar máquinas ou estimar produção (Landing 3) |
| A correção do `X-Forwarded-For` e do contador compartilhado entre rotas | Já estão em `helterdias/fix-forged-client-ip` e `helterdias/fix-per-route-throttle`, fora da HEL-14 |
| Desempenho do frontend | O k6 mede a API |
| Painéis em Grafana, InfluxDB ou k6 Cloud | O relatório HTML do k6 basta, sem custo |
| Geração de carga distribuída | Não há segunda máquina |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| A VM do Docker tem 4 GB, e as duas pilhas, o k6 e o pico de 300 VUs podem não caber | Rodar o `spike` como em AC 63 e, se um container for morto por falta de memória, baixar o pico ou aumentar a VM, registrando a decisão na tabela | O design deixou isso para a primeira rodada (Test profiles, default 2) | n |
| A tag `grafana/k6:2.3.0` | Confirmar no Docker Hub ao construir e fixar a patch mais recente da 2.3 | Só fontes secundárias apontam a 2.3.0 como a última em 2026-10-16 | n |
| O soak de 1 hora não roda dentro do build | O build prova a forma do `soak` pelas opções do script, e a primeira rodada completa fica para quando diashelter quiser | Uma hora de rodada não muda nenhuma decisão desta entrega | n |
| O tempo de seed medido para AC 8 | Medir na VM de dev de 8 CPUs e 4 GB; no CI vale só como aviso | A máquina do CI não é a da linha de base | n |

**Open questions:**

| # | Kind | Question | Until answered |
| --- | --- | --- | --- |
| 1 | resolvida | As branches das duas correções entram na `main` antes de S6? Sim: #17 e #18 foram integradas em 2026-10-16 e a `main` entrou nesta branch em `d851b01`. Sem a segunda, as rotas limitadas de um mesmo IP somavam num contador só, o que também quebrava o `smoke` com os limites reais | nada: AC 55 a 57 e o `smoke` passam desde o merge |

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| command `make load-test` | output format and verbosity | AC 11, AC 12: o resumo do k6 no terminal, mais o HTML e o JSON em `results/` |
| command `make load-test` | every flag and its default | AC 9, AC 10, AC 21: `TYPE` é obrigatório, sem default, entre os 8 perfis |
| command `make load-test` | exit codes | AC 10, AC 12, AC 13 |
| command `make load-test` | what it prints when it fails halfway | AC 13: os logs da `api` de carga |
| command `make load-down` | output, flags, exit codes | AC 14; sem flags |
| command `make load-down` | what it prints when it fails halfway | n/a - repassa a saída do `docker compose down`, que já diz o que não removeu |
| CI job do smoke | exit codes and failure output | AC 74, AC 75, AC 76 |
| document `load-tests/README.md` | structure and what the reader does next | AC 71: como rodar cada perfil, os limites de aprovação e a tabela; o leitor compara a sua rodada com a última linha |
| document README (seção de carga) | structure, depth | Impact docs: a seção aponta para `load-tests/README.md` em vez de repetir a tabela |
| all API routes | response shape, error shape, versioning, rate limits | n/a - nenhuma rota muda; o k6 só confere as respostas de hoje (S3 a S6) |

## Sources

- [HEL-14](https://linear.app/helter/issue/HEL-14/implementar-testes-de-performance-e-carga-utilizando-o-k6): o k6, os tipos de teste, os caminhos que não são felizes e a pasta na raiz.
- [.design/load-tests-k6.md](../../../.design/load-tests-k6.md): o design aprovado por diashelter em 2026-10-16; as Key decisions 1 a 8 viram os doors 1 a 7.
