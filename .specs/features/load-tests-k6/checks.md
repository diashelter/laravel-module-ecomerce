# Testes de carga com k6 (HEL-14) - checks

Profile: standard
Plan: `.specs/features/load-tests-k6/plan.md`

94 checks em 8 slices e o fechamento (C88 a C92 entraram na rodada 1 da verificação, e C93 e C94 na rodada 3) · 7 one-way doors · 0 open

## Como rodar as provas

As provas usam cinco tipos de comando:

- **Pest:** `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no container de dev, como o `make test` e o CI.
- **Testes unitários do k6:** `sh load-tests/tests/k6.sh <arquivo> --only <caso>` roda `k6 run` com `-e CASE=<caso>` no container `k6` do projeto de carga, sem subir a pilha (`--no-deps`), sobre um arquivo de `load-tests/tests/`. O caso falha pelo limite `checks: rate==1`, e o código de saída decide.
- **Rodada de um perfil:** `sh load-tests/tests/run.sh --only <perfil>` roda `make load-test TYPE=<perfil>` e sai com o código dele. A rodada deixa a pilha de carga no ar para as provas seguintes.
- **Resultado de uma rodada:** `sh load-tests/tests/assert-check.sh <perfil> --name "<check>"` lê `load-tests/results/<perfil>-checks.txt`, que o `handleSummary` reescreve a cada rodada com uma linha `check <passes> <fails> <nome>` por check do k6 e uma linha `metric <nome> <contagem> <valor>` por métrica personalizada. A prova sai com 0 só quando o check existe, tem `passes` ≥ 1 e `fails` = 0. O `assert-metric.sh <perfil> --name <métrica> --equals <n>` (ou `--at-least <n>`) faz o mesmo com a contagem da métrica.
- **Asserções da pilha:** `sh load-tests/tests/<script>.sh --only <caso>`. O `dataset.sh` consulta o Postgres de carga pelo `psql` do container `db` do projeto de carga. O `stack.sh` inspeciona os containers de carga, o `make.sh` roda o `make` e confere o que ficou, o `isolation.sh` compara o dev antes e depois de uma rodada, o `docs.sh` confere os documentos e o `ci.sh` confere o workflow.

Antes das provas de um perfil, rode `sh load-tests/tests/run.sh --only <perfil>`. As provas do `smoke` (S3 a S5) leem a última rodada do `smoke`. As do `contention` e do `limits` (S6) leem a última rodada de cada um. Os perfis longos (`load`, `stress`, `spike`, `breakpoint` e `soak`) são provados pela forma, com os testes unitários do k6. Só o `load` e o `breakpoint` rodam de verdade, para a linha de base (C76).

Os nomes dos checks do k6 abaixo são literais: o script usa exatamente esses textos.

**Exercício temporário:** os scripts `make.sh --only fails-on-threshold` e `make.sh --only fails-on-seed` injetam uma falha numa cópia de segurança do arquivo, rodam o `make` e restauram o arquivo num `trap`, mesmo quando falham. Depois deles, `git diff --quiet -- load-tests backend/database` precisa sair com 0. Nenhum outro arquivo é editado durante um exercício.

**Interpretação do AC 66.** O plano pede "arredondado para baixo, com ao menos 1 VU por fluxo", mas aplicada aos três fluxos essa regra dá 49 VUs no `load`, e o AC 61 pede 50. Por isso a compra e a equipe são arredondadas para baixo, com mínimo de 1, e a vitrine fica com o restante. Assim o total é sempre o do perfil. O plano foi corrigido no mesmo commit destes checks.

## Checks

### S1 - LoadTestSeeder · 15 arquivos · 36 KB · ~9k

**C1** - Depois do seed, `customers` tem exatamente 1.000 linhas, com os e-mails `load-customer-0@example.com` a `load-customer-999@example.com`, e todas entram com a senha `password`. `customer_addresses` tem exatamente 1.000 linhas, uma por cliente (AC 1, door 7)
Proof: `sh load-tests/tests/dataset.sh --only customers`

**C2** - `users` tem exatamente 2 linhas: `admin@example.com` com o papel `admin` e `suporte@example.com` com o papel `support`, as duas com a senha `password` (AC 2)
Proof: `sh load-tests/tests/dataset.sh --only staff`

**C3** - `categories` tem 8 linhas e `products` tem 201: 170 ativos com 1.000.000 de unidades cada, 10 ativos com 0, 20 inativos com 100 cada e um único `Produto Disputado`, ativo e com 10 (AC 3, door 7)
Proof: `sh load-tests/tests/dataset.sh --only catalog`

**C4** - `orders` tem 10.000 linhas, todas com `created_at` nos últimos 12 meses. Cada pedido tem de 1 a 4 itens, a cópia de um endereço do próprio cliente e o frete da tabela de `config/shop.php` para a UF da cópia. Existe exatamente um pagamento aprovado para cada pedido em `payment_approved` ou `delivered`, e nenhum para os demais. `load-customer-0@example.com` tem ao menos um pedido (AC 4)
Proof: `sh load-tests/tests/dataset.sh --only orders`

**C5** - A quantidade de cada linha de `stocks` é a do C3 para o produto, e `outbox_messages` tem 0 linhas (AC 5)
Proof: `sh load-tests/tests/dataset.sh --only stock-and-outbox`

**C6** - Dois seeds seguidos, cada um depois de `migrate:fresh`, produzem a mesma impressão digital: o `md5` dos nomes dos clientes, dos nomes e preços dos produtos e dos totais dos pedidos, todos na ordem do id (AC 6, door 7)
Proof: `sh load-tests/tests/dataset.sh --only deterministic`

**C7** - O `DatabaseSeeder` não cita o `LoadTestSeeder`, e o seed de demonstração continua igual (AC 7)
Proof: `! grep -n "LoadTestSeeder" backend/database/seeders/DatabaseSeeder.php`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds a complete demo environment"`

**C8** - `migrate:fresh` seguido do `LoadTestSeeder`, dentro da `api` de carga, termina em no máximo 90 segundos de relógio (AC 8)
Proof: `sh load-tests/tests/dataset.sh --only seed-time`

### S2 - Load stack · 14 arquivos · 40 KB · ~10k

**C9** - O Compose de carga declara `name: ecommerce-load` e os serviços `db`, `redis`, `api`, `queue-worker`, `outbox-relay`, `nginx` e `k6`. A `api` de carga roda com `DB_HOST=db`, `DB_DATABASE=ecommerce_load`, `REDIS_HOST=redis`, `SESSION_DOMAIN` vazio, `SANCTUM_STATEFUL_DOMAINS=nginx` e `APP_URL=http://nginx`. O Postgres de dev não tem um banco `ecommerce_load` (door 1)
Proof: `sh load-tests/tests/stack.sh --only connection`

**C10** - `make load-test TYPE=smoke` apaga os volumes do projeto de carga antes de subir: uma categoria de marcação gravada no banco de carga antes da rodada não existe depois dela. O `setup` do k6 só começa depois do seed, o que o check `setup: dataset has 201 products` prova pela API pública (AC 9)
Proof: `sh load-tests/tests/make.sh --only recreates`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "setup: dataset has 201 products"`

**C11** - `make load-test` sem `TYPE`, e com `TYPE=bogus`, imprime os 8 perfis válidos, sai com código diferente de zero e deixa o projeto `ecommerce-load` sem nenhum container (AC 10)
Proof: `sh load-tests/tests/make.sh --only missing-type`
Proof: `sh load-tests/tests/make.sh --only invalid-type`

**C12** - Depois de `make load-test TYPE=smoke`, existem `load-tests/results/smoke-<YYYYMMDD-HHMMSS>.html` e `.json` com o mesmo carimbo, mais recentes que o início da rodada. `git status --porcelain load-tests/results` sai vazio, e `git check-ignore` reconhece os dois arquivos (AC 11)
Proof: `sh load-tests/tests/stack.sh --only reports`

**C13** - Exercício temporário: com o status esperado de uma resposta do smoke trocado, `make load-test TYPE=smoke` sai com código diferente de zero e grava mesmo assim o HTML e o JSON com o carimbo da rodada (AC 12)
Proof: `sh load-tests/tests/make.sh --only fails-on-threshold`

**C14** - Exercício temporário: com o `LoadTestSeeder` lançando `load seed failed on purpose`, `make load-test TYPE=smoke` sai com código diferente de zero, imprime essa mensagem (na saída do `db:seed`) e, depois do aviso "Logs da api de carga", as linhas do `docker compose logs` da `api` de carga, e não grava nenhum relatório novo em `results/` (AC 13)
Proof: `sh load-tests/tests/make.sh --only fails-on-seed`

**C15** - `make load-down` deixa o projeto `ecommerce-load` sem containers e sem volumes, e o projeto `ecommerce` com os mesmos containers no ar e os mesmos volumes de antes (AC 14, door 1)
Proof: `sh load-tests/tests/make.sh --only down`

**C16** - Uma rodada do `smoke` com a pilha de dev no ar deixa iguais, no banco de dev, as contagens de `customers`, `orders`, `products`, `stocks` e `outbox_messages`, e a listagem de `backend/bootstrap/cache` (AC 15, door 2)
Proof: `sh load-tests/tests/isolation.sh --only dev-untouched`

**C17** - `php artisan about --json` na `api` de carga informa `environment` `production`, `debug_mode` `false` e os logs `"stderr"`, e o ambiente dela tem `LOG_LEVEL=warning` (AC 16, door 3)
Proof: `sh load-tests/tests/stack.sh --only app-settings`

**C18** - Na `api` de carga, `opcache.validate_timestamps` é `0`. `/tmp/laravel/config.php`, `/tmp/laravel/routes-v7.php` e `/tmp/laravel/events.php` existem, e `config.php`, `routes-v7.php` e `events.php` não existem em `/var/www/backend/bootstrap/cache` (AC 17, doors 2 e 3)
Proof: `sh load-tests/tests/stack.sh --only opcache-and-caches`

**C19** - `php-fpm -tt` na `api` de carga mostra `pm = static` e `pm.max_children = 16` (AC 18, door 3)
Proof: `sh load-tests/tests/stack.sh --only fpm-pool`

**C20** - Depois de uma rodada do `contention`, com `CACHE_LIMITER=array`, 11 `POST /api/auth/login` seguidos com senha errada no `nginx` de carga recebem `422`, e nenhum recebe `429` (AC 19, door 4)
Proof: `sh load-tests/tests/k6.sh limiter.test.js --only inert`

**C21** - Depois de uma rodada do `smoke`, sem `CACHE_LIMITER`, o 11º `POST /api/auth/login` seguido com senha errada no `nginx` de carga recebe `429`. O teste do Pest de hoje continua passando (AC 20, door 4)
Proof: `sh load-tests/tests/k6.sh limiter.test.js --only real`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles registration and both logins after 10 attempts per minute"`

**C22** - `make -n load-test TYPE=<perfil>`, para cada um dos 8 perfis, passa `CACHE_LIMITER=array` em `load`, `stress`, `spike`, `breakpoint`, `soak` e `contention`, e nenhum valor em `smoke` e `limits`. A `api` de carga tem `CACHE_LIMITER` vazio depois do `smoke` e `array` depois do `contention` (AC 21, door 4)
Proof: `sh load-tests/tests/make.sh --only limiter-mapping`
Proof: `sh load-tests/tests/stack.sh --only limiter-env`

**C23** - A `api`, o `queue-worker` e o `outbox-relay` de carga têm, os três, `APP_CONFIG_CACHE=/tmp/laravel/config.php`, `APP_ROUTES_CACHE=/tmp/laravel/routes-v7.php`, `APP_EVENTS_CACHE=/tmp/laravel/events.php`, `LOG_CHANNEL=stderr` e `LOG_LEVEL=warning` (door 2)
Proof: `sh load-tests/tests/stack.sh --only cache-env`

**C24** - `config/cache.php` lê `'limiter' => env('CACHE_LIMITER')`, e no ambiente do Pest, sem a variável, `config('cache.limiter')` é `null` (door 4)
Proof: `grep -n "'limiter' => env('CACHE_LIMITER')" backend/config/cache.php`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="leaves the rate limiter on the default store without CACHE_LIMITER"`

**C25** - O serviço `k6` usa a imagem `grafana/k6` com uma tag exata da linha 2.3, nunca `latest`. O `make load-test` liga `K6_WEB_DASHBOARD=true` com `K6_WEB_DASHBOARD_EXPORT` apontando para `results/` (door 5)
Proof: `sh load-tests/tests/stack.sh --only k6-image`

**C26** - Nenhum serviço do Compose de carga publica porta no host, e o Compose de dev (`docker-compose.yml`) não muda nesta feature (door 1)
Proof: `sh load-tests/tests/stack.sh --only no-host-ports`
Proof: `git diff --quiet origin/main...HEAD -- docker-compose.yml docker/`

### S3 - Storefront · 4 arquivos · 14 KB · ~4k

**C27** - `storefront: product list is 200` passa (AC 22)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: product list is 200"`

**C28** - `storefront: invalid sort is 422 VALIDATION_FAILED` passa (AC 23)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: invalid sort is 422 VALIDATION_FAILED"`

**C29** - `storefront: page after the last is 200 and empty` passa (AC 24)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: page after the last is 200 and empty"`

**C30** - `storefront: available product is 200` passa (AC 25)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: available product is 200"`

**C31** - Os quatro casos de `404 NOT_FOUND` da vitrine passam (AC 26)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: inactive product is 404 NOT_FOUND"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: product without units is 404 NOT_FOUND"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: malformed id is 404 NOT_FOUND"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: unknown id is 404 NOT_FOUND"`

**C32** - A cotação de frete passa com `200` para uma UF conhecida e com `422` para `ZZ` (AC 27)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: known state quote is 200"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: unknown state quote is 422"`

**C33** - `storefront: available cart is 200 without problems` passa (AC 28)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: available cart is 200 without problems"`

**C34** - O carrinho com produto inativo e o carrinho com produto sem unidades passam com `200` e a linha com `is_available` `false` (AC 29)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: inactive product in cart is 200 unavailable"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: product without units in cart is 200 unavailable"`

**C35** - O cadastro passa com `201` para um e-mail novo e com `422` para um e-mail já usado (AC 30)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: new email registers 201"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "storefront: taken email is 422"`

**C36** - Nos perfis de volume, a vitrine se cadastra em exatamente 10 de 1.000 iterações seguidas (1%). No `smoke`, se cadastra em toda iteração (AC 31)
Proof: `sh load-tests/tests/k6.sh mix.test.js --only register-rate`

### S4 - Checkout · 5 arquivos · 20 KB · ~5k

**C37** - O VU `n` da compra entra como `load-customer-<m>@example.com`, com `m = ((n - 1) mod 999) + 1`: o VU 1 é o cliente 1, o 999 é o 999, o 1.000 é o 1, e nunca o cliente 0. Os checks do cookie de CSRF (`204`) e do login (`200`) passam (AC 32)
Proof: `sh load-tests/tests/k6.sh mix.test.js --only customer-for-vu`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: csrf cookie is 204"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: customer login is 200"`

**C38** - `checkout: order is 201` passa (AC 33)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: order is 201"`

**C39** - `checkout: order with a product without units is 409 INSUFFICIENT_STOCK` passa, e nos perfis de volume o pedido leva o produto sem unidades em exatamente 50 de 1.000 iterações seguidas (5%) (AC 34)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: order with a product without units is 409 INSUFFICIENT_STOCK"`
Proof: `sh load-tests/tests/k6.sh mix.test.js --only unavailable-order-rate`

**C40** - O `address_id` malformado e o endereço de outro cliente passam com `422` e `errors.address_id` (AC 35)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: malformed address_id is 422"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: address of another customer is 422"`

**C41** - A espera lê o pedido a cada 2 segundos, com o primeiro intervalo também de 2 segundos, até o status `awaiting_payment`, e grava em `order_payable_wait` o tempo desde o `201`. No `smoke`, `checkout: order became awaiting_payment` passa e a métrica tem ao menos 1 amostra (AC 36, door 6)
Proof: `sh load-tests/tests/k6.sh payable.test.js --only polls-every-2s`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: order became awaiting_payment"`
Proof: `sh load-tests/tests/assert-metric.sh smoke --name order_payable_wait --at-least 1 --value-at-least 2000 --value-at-most 60000`

**C42** - Com um pedido que nunca sai de `placed`, a espera desiste ao chegar a 60 segundos, não devolve pedido para pagar e conta 1 erro inesperado, pela função que o fluxo da compra chama (AC 37)
Proof: `sh load-tests/tests/k6.sh payable.test.js --only gives-up-after-60s`
Proof: `grep -n "countGiveUp(wait, unexpectedErrors)" load-tests/flows/checkout.js`

**C43** - O passo de pagamento só chama `POST /api/orders/{order}/payment` quando a espera devolveu o pedido como `awaiting_payment`. Sem isso, não faz nenhuma chamada. No `smoke`, o primeiro pagamento de cada pedido nunca recebe `409` (AC 38)
Proof: `sh load-tests/tests/k6.sh payable.test.js --only pays-only-when-awaiting`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: approved card is 202"`

**C44** - `checkout: approved card is 202` passa, e nos perfis de volume 900 de 1.000 pedidos seguidos pagam direto com `fake_card_approved` (AC 39)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: approved card is 202"`
Proof: `sh load-tests/tests/k6.sh mix.test.js --only card-split`

**C45** - A recusa passa com `402 PAYMENT_DECLINED` e o pagamento seguinte com `202`. Nos perfis de volume, 100 de 1.000 pedidos seguidos começam por `fake_card_declined` (AC 40)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: declined card is 402 PAYMENT_DECLINED"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: approved card after a decline is 202"`
Proof: `sh load-tests/tests/k6.sh mix.test.js --only card-split`

**C46** - Pagar de novo um pedido pago passa com `409`, e pagar sem `card_token` passa com `422` (AC 41)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: paying a paid order is 409"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: payment without card_token is 422"`

**C47** - O pedido do `load-customer-0` passa com `404`, e o próprio pedido lido sem sessão passa com `401` (AC 42)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: order of another customer is 404"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: order without session is 401"`

**C48** - A conta e a lista de pedidos passam com `200` (AC 43)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: account is 200"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: order list is 200"`

**C49** - O endereço novo passa com `201` e a exclusão dele com `204` (AC 44)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: new address is 201"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: address deletion is 204"`

**C50** - `checkout: address of another customer on PUT is 404` passa (AC 45)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "checkout: address of another customer on PUT is 404"`

### S5 - Backoffice · 2 arquivos · 6 KB · ~2k

**C51** - O VU `n` da equipe entra como `admin@example.com` quando `n` é ímpar e como `suporte@example.com` quando é par, e o login passa com `200` (AC 46)
Proof: `sh load-tests/tests/k6.sh mix.test.js --only staff-for-vu`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: staff login is 200"`

**C52** - Os cinco checks de leitura do painel passam com `200` (AC 47)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: dashboard is 200"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: order list page is 200"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: order detail is 200"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: stock list is 200"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: product list is 200"`

**C53** - `backoffice: stock adjustment is 200` passa, e nos perfis de volume o ajuste acontece em exatamente 20 de 1.000 iterações seguidas (2%) (AC 48)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: stock adjustment is 200"`
Proof: `sh load-tests/tests/k6.sh mix.test.js --only stock-adjust-rate`

**C54** - A exclusão pelo suporte passa com `403`, e o mesmo produto abre com `200` logo depois (AC 49)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: support deleting a product is 403"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: product survives the support deletion"`

**C55** - `backoffice: unknown product is 404` passa (AC 50)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: unknown product is 404"`

**C56** - A sessão de cliente no dashboard e a sessão de equipe nos pedidos da loja passam com `401` (AC 51)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: customer session on the dashboard is 401"`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "backoffice: staff session on the store orders is 401"`

### S6 - Concurrency scenarios · 3 arquivos · 12 KB · ~3k

**C57** - `make load-test TYPE=contention` sai com 0, com `contention_created` = 10, `contention_refused` = 40 (todos `409 INSUFFICIENT_STOCK`) e `contention_server_errors` = 0. Os três são limites do perfil, então o código de saída também decide (AC 52)
Proof: `sh load-tests/tests/run.sh --only contention`
Proof: `sh load-tests/tests/assert-metric.sh contention --name contention_created --equals 10`
Proof: `sh load-tests/tests/assert-metric.sh contention --name contention_refused --equals 40`
Proof: `sh load-tests/tests/assert-metric.sh contention --name contention_server_errors --equals 0`

**C58** - `contention: disputed product ends with 0 units` passa, lido pela lista de estoques do admin no `teardown` (AC 53)
Proof: `sh load-tests/tests/assert-check.sh contention --name "contention: disputed product ends with 0 units"`

**C59** - Os 50 pares de pedidos cruzados terminam com `cross_orders_created` = 100 e `cross_orders_server_errors` = 0 (AC 54)
Proof: `sh load-tests/tests/assert-metric.sh contention --name cross_orders_created --equals 100`
Proof: `sh load-tests/tests/assert-metric.sh contention --name cross_orders_server_errors --equals 0`

**C60** - No `limits`, a 11ª tentativa de login de cliente e o 11º cadastro passam com `429 TOO_MANY_REQUESTS`, e `make load-test TYPE=limits` sai com 0 (AC 55)
Proof: `sh load-tests/tests/run.sh --only limits`
Proof: `sh load-tests/tests/assert-check.sh limits --name "limits: 11th customer login is 429 TOO_MANY_REQUESTS"`
Proof: `sh load-tests/tests/assert-check.sh limits --name "limits: 11th registration is 429 TOO_MANY_REQUESTS"`

**C61** - No `limits`, os 20 primeiros pedidos e os 20 primeiros pagamentos com corpo inválido passam com `422`, e o 21º de cada um com `429` (AC 56)
Proof: `sh load-tests/tests/assert-check.sh limits --name "limits: first 20 orders are 422"`
Proof: `sh load-tests/tests/assert-check.sh limits --name "limits: 21st order is 429"`
Proof: `sh load-tests/tests/assert-check.sh limits --name "limits: first 20 payments are 422"`
Proof: `sh load-tests/tests/assert-check.sh limits --name "limits: 21st payment is 429"`

**C62** - No `limits`, a 61ª validação de carrinho e a 61ª cotação de frete passam com `429` (AC 57)
Proof: `sh load-tests/tests/assert-check.sh limits --name "limits: 61st cart validation is 429"`
Proof: `sh load-tests/tests/assert-check.sh limits --name "limits: 61st shipping quote is 429"`

**C63** - A rajada de 30 logins simultâneos conta em `rate_limit_burst_passed` os que passaram pelo limitador (todo status que não é `429`, pela decisão `passedTheLimiter`, que o cenário chama), ao menos os 10 do limite, grava a linha derivada `rate_limit_overshoot` e não tem limite de aprovação: o perfil não declara limite sobre essas métricas (AC 58, door 6). O cálculo do valor é o C91
Proof: `sh load-tests/tests/k6.sh summary.test.js --only burst-passed`
Proof: `grep -n "burstPassed.add(passedTheLimiter(response.status) ? 1 : 0)" load-tests/scenarios/limits.js`
Proof: `sh load-tests/tests/assert-metric.sh limits --name rate_limit_burst_passed --at-least 10`
Proof: `sh load-tests/tests/assert-metric.sh limits --name rate_limit_overshoot --at-least 1`
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only limits-overshoot-unbounded`

**C64** - Depois do `limits`, `orders` continua com 10.000 linhas, `customers` com 1.000, `customer_addresses` com 1.000, e `payments` com uma linha por pedido em `payment_approved` ou `delivered` (AC 59)
Proof: `sh load-tests/tests/dataset.sh --only untouched`

### S7 - Test profiles · 5 arquivos · 18 KB · ~5k

**C65** - O `smoke` roda 1 VU por fluxo e 1 iteração de cada, com os limites `checks: rate==1` e zero erros inesperados, sem limite de latência. A rodada sai com 0, e todo nome de `load-tests/tests/smoke-checks.txt` (os checks de S3, S4 e S5 acima) aparece com `fails` = 0 (AC 60)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only smoke`
Proof: `sh load-tests/tests/run.sh --only smoke`
Proof: `sh load-tests/tests/assert-check.sh smoke --name all-from-smoke-checks`

**C66** - O `load` sobe de 0 a 50 VUs em 1 min, fica 5 min em 50 e desce a 0 em 1 min (AC 61)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only load`

**C67** - O `stress` sobe de 0 a 150 em 2 min, fica 5 min em 150 e desce em 2 min (AC 62)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only stress`

**C68** - O `spike` fica em 50, sobe a 300 em 10 s, fica 1 min em 300, volta a 50 em 10 s e fica 2 min em 50 (AC 63)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only spike`

**C69** - O `breakpoint` usa `ramping-arrival-rate` de 5 a 500 iterações/s em 15 min, com limites `abortOnFail` em erro inesperado acima de 5% e p95 acima de 2.000 ms, e `delayAbortEval` de 30 s (AC 64)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only breakpoint`

**C70** - O `soak` fica 1 hora em 50 VUs (AC 65)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only soak`

**C71** - A divisão dos perfis de volume dá, para 50 VUs, 38 de vitrine, 10 de compra e 2 de equipe; para 150, 113, 30 e 7; para 300, 225, 60 e 15. A taxa de 500 iterações/s do `breakpoint` é dividida na mesma proporção, em 375, 100 e 25. A compra e a equipe são arredondadas para baixo, com mínimo de 1, e a vitrine fica com o restante (AC 66, interpretação acima)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only split`

**C72** - O `load`, o `stress` e o `soak` declaram os limites `unexpected_errors: rate<0.01`, `http_req_duration{flow:storefront}: p(95)<500`, `http_req_duration{name:POST /api/orders}: p(95)<1000` e `order_payable_wait: p(95)<10000`, até a primeira linha de base (AC 67)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only volume-thresholds`

**C73** - O `spike` declara só o limite `unexpected_errors: rate<0.05` (AC 68)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only spike-thresholds`

**C74** - O ajudante de requisição troca `{order}`, `{product}`, `{address}` e `{stock}` pelo id na URL e põe o template na tag `name`; o `send()`, montado sobre um cliente HTTP falso no teste, entrega ao k6 a URL com o id, a tag com o template, o corpo, o XSRF do jar e os status esperados; e nenhum arquivo de `load-tests/flows` ou `load-tests/scenarios` chama `http.get`, `http.post`, `http.put`, `http.patch`, `http.del` ou `http.request` direto (AC 69, door 6)
Proof: `sh load-tests/tests/k6.sh http.test.js --only name-tag`
Proof: `! grep -rnE "http\.(get|post|put|patch|del|request)\(" load-tests/flows load-tests/scenarios`
Proof: `grep -n "tags: request.tags" load-tests/lib/http.js`

**C75** - Toda requisição leva um `X-Request-ID` no formato `<perfil>-vu<n>-it<n>-<sequência>`, que casa com `^[A-Za-z0-9._-]{8,128}$`, e a API o devolve igual: `smoke: api echoes the request id` passa (AC 70)
Proof: `sh load-tests/tests/k6.sh http.test.js --only request-id`
Proof: `sh load-tests/tests/assert-check.sh smoke --name "smoke: api echoes the request id"`

**C76** - `load-tests/README.md` tem a tabela da linha de base com as 12 colunas do door 6 e uma linha do `load` e uma do `breakpoint`, cada uma com um commit da HEL-14 (alcançável da ponta da branch, `746797a^2`, e não da `main` anterior, `746797a^1`) a partir do qual nada do que a rodada mede mudou até o merge `746797a` do PR #19, o commit que fecha a feature: `backend/`, `docker/`, o `Makefile` e o Compose e o JavaScript de `load-tests/`, exceto `lib/baseline.js`, que só guarda os números registrados. `HEAD` contém esse merge (AC 71, door 6; a prova foi reescrita depois do merge, ver o `Handoff`)
Proof: `sh load-tests/tests/docs.sh --only baseline-rows`

**C77** - Depois da linha do `load`, os limites de latência do C72 são, no `load`, no `stress` e no `soak`, o p95 registrado vezes 1,2, arredondado para cima até a dezena de ms seguinte. O teste lê o `README.md` da pasta e compara com `lib/baseline.js` (AC 72)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only thresholds-from-baseline`

### Rodada 1 da verificação · 6 arquivos · 20 KB · ~5k

**C88** - A `api` de carga roda com `BCRYPT_ROUNDS=12` e `ORDER_DELIVERY_DELAY_SECONDS=10`, e a configuração em cache lê o mesmo atraso (door 3)
Proof: `sh load-tests/tests/stack.sh --only bcrypt-and-delivery`

**C89** - O `contention` declara 50 VUs de uma iteração na disputa e 100 nos pedidos cruzados, e os limites `checks: rate==1`, `unexpected_errors: rate==0`, `contention_created: count==10`, `contention_refused: count==40`, `contention_server_errors: count==0`, `cross_orders_created: count==100` e `cross_orders_server_errors: count==0` (AC 52 e AC 54, na própria camada)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only contention-thresholds`

**C90** - A taxa na parada do `breakpoint` é 5 + 495 × (segundos desde o fim do `setup()` ÷ 900), entre 5 e 500: 5 em 0 s, 252,5 em 450 s e 500 a partir de 900 s. O `handleSummary` grava a linha `breakpoint_rate_at_stop` só no `breakpoint` (AC 64)
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only breakpoint-rate`
Proof: `sh load-tests/tests/k6.sh summary.test.js --only breakpoint-rate`

**C91** - `rate_limit_overshoot` vale `max(0, rate_limit_burst_passed − 10)`: 1 com 11 que passaram, 0 com 10 e 0 com 8, e não existe sem a rajada (AC 58)
Proof: `sh load-tests/tests/k6.sh summary.test.js --only overshoot`

**C92** - Nos perfis de volume, os casos de erro sem fração no plano rodam em exatamente 100 de 1.000 iterações seguidas de um VU (1 em 10). No `smoke`, rodam em toda iteração
Proof: `sh load-tests/tests/k6.sh mix.test.js --only error-paths-rate`

### Rodada 3 da verificação · 2 arquivos · 12 KB · ~3k

**C93** - O `send()` que o `createSender` monta conta 1 erro inesperado na métrica que recebe para um status fora de `expect`, o `0` do timeout incluído, e nenhum para um status dentro dele; sem `expect`, só o `200` é esperado. O `send` dos fluxos é montado sobre o `k6/http` e a métrica `unexpected_errors` (S3 a S6, "qualquer outro status conta como erro", que vale para os AC 22 a AC 51; Key decision 6)
Proof: `sh load-tests/tests/k6.sh http.test.js --only unexpected-status`
Proof: `grep -nF "export const send = createSender(http, unexpectedErrors);" load-tests/lib/http.js`

**C94** - O `verify()` que o `createVerifier` monta conta 1 erro inesperado para um status esperado com o corpo errado ou com um predicado que lança exceção, e nenhum para o corpo certo; o check do k6 com o nome dado passa só no corpo certo; e um status fora da lista falha o check sem contar de novo, porque o `send()` já contou. O `verify` dos fluxos é montado sobre o `check` do k6 e a métrica `unexpected_errors` (Key decision 6: "um check do corpo que falhou")
Proof: `sh load-tests/tests/k6.sh http.test.js --only body-check`
Proof: `grep -nF "export const verify = createVerifier(check, unexpectedErrors);" load-tests/lib/http.js`

### S8 - CI smoke · 2 arquivos · 3 KB · ~1k

**C78** - O workflow de CI tem um job que não é o `backend`, roda em `pull_request` e em `push` na `main` (os gatilhos do workflow) e chama `make load-test TYPE=smoke`. Depois do push autorizado, a última execução do workflow nesta branch termina com `success` (AC 73)
Proof: `sh load-tests/tests/ci.sh --only job`
Proof: `gh run list --branch helterdias/hel-14-implementar-testes-de-performance-e-carga-utilizando-o-k6 --workflow CI --limit 1 --json conclusion --jq '.[0].conclusion' | grep -qx success`

**C79** - O passo do smoke no job roda o `make` sem redirecionar a saída, então o resumo do k6 aparece no log do passo que falha (AC 74)
Proof: `sh load-tests/tests/ci.sh --only summary-in-log`

**C80** - O job tem um passo `if: failure()` que imprime os logs do projeto de carga (AC 75)
Proof: `sh load-tests/tests/ci.sh --only failure-logs`

**C81** - O job tem um passo `if: always()` que roda `make load-down` (AC 76)
Proof: `sh load-tests/tests/ci.sh --only always-down`

### Fechamento · 6 arquivos · 135 KB · ~34k

**C82** - O README tem a seção "Testes de carga (k6)", com os 8 perfis, os limites de aprovação e o link para `load-tests/README.md`. Ela entra no sumário. A tabela de comandos do `Makefile` tem `load-test` e `load-down`, a configuração do `.env` cita `CACHE_LIMITER`, e a seção de containers cita o projeto `ecommerce-load`
Proof: `sh load-tests/tests/docs.sh --only readme`

**C83** - O `AGENTS.md` proíbe gravar caches na árvore compartilhada a partir do container de carga, e o checklist final cita o smoke de carga
Proof: `sh load-tests/tests/docs.sh --only agents`

**C84** - `backend/.env.example` documenta `CACHE_LIMITER` vazio, com o comentário de que só a pilha de carga a define
Proof: `grep -nE "^CACHE_LIMITER=$" backend/.env.example`

**C85** - `load-tests/README.md` explica como rodar cada perfil, o que cada limite de aprovação significa e como aceitar uma linha (duas rodadas no mesmo commit com p95 a menos de 20% uma da outra)
Proof: `sh load-tests/tests/docs.sh --only load-readme`

**C86** - O estilo do PHP alterado passa no Pint
Proof: `docker compose exec -T api ./vendor/bin/pint --test database/seeders/LoadTestSeeder.php config/cache.php tests/Feature/RateLimiterStoreTest.php`

**C87** - A suíte inteira continua passando, inclusive o `ModuleBoundariesTest` e o `SeederTest`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ModuleBoundariesTest|SeederTest|AuthTest|RateLimiterStoreTest"`
Proof: `make test`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| perfis (8) | `smoke` C11, C22, C65 · `load` C22, C66 · `stress` C22, C67 · `spike` C22, C68 · `breakpoint` C22, C69 · `soak` C22, C70 · `contention` C22, C57 · `limits` C22, C60 | - |
| `CACHE_LIMITER` por perfil (8) | C22, table-driven over all 8; a `api` de carga confere `smoke` vazio e `contention` `array` | - |
| efeito do `CACHE_LIMITER` (2) | `array` inerte C20 · sem valor real C21 | - |
| entrada `TYPE` (3) | ausente C11 · desconhecido C11 · válido C10 | - |
| saídas do `make load-test` (4) | sucesso com relatórios C12 · limite cruzado C13 · pilha que não sobe C14 · `make load-down` C15 | - |
| volumes do `LoadTestSeeder` (10) | clientes 1.000 C1 · endereços 1.000 C1 · equipe 2 C2 · categorias 8 C3 · ativos com unidades 170 C3 · ativos sem unidades 10 C3 · inativos 20 C3 · `Produto Disputado` 1 C3 · pedidos 10.000 C4 · pagamentos aprovados C4 | - |
| startup config: caminhos de cache e log (3 containers) | `api` C23 · `queue-worker` C23 · `outbox-relay` C23 | - |
| startup config: armazenamento do limitador (2 montagens) | pilha de carga C20, C21, C22 · Pest, dev e o job `backend` do CI, que montam a aplicação do mesmo jeito sem a variável C24 | - |
| doors do plano (7) | 1 C9, C15, C26 · 2 C16, C18, C23 · 3 C17, C18, C19, C88 · 4 C20, C21, C22, C24 · 5 C25 · 6 C41, C63, C74, C76, C91 · 7 C1, C3, C6 | - |
| estados da vitrine (15) | lista C27 · ordem inválida C28 · página além da última C29 · produto disponível C30 · inativo C31 · sem unidades C31 · id malformado C31 · id desconhecido C31 · frete conhecido C32 · frete `ZZ` C32 · carrinho disponível C33 · carrinho com inativo C34 · carrinho sem unidades C34 · cadastro novo C35 · e-mail usado C35 | - |
| estados da compra (19) | cookie CSRF C37 · login C37 · pedido C38 · produto sem unidades C39 · `address_id` malformado C40 · endereço alheio C40 · pagável C41 · cartão aprovado C44 · recusado C45 · aprovado depois da recusa C45 · pagar de novo C46 · sem `card_token` C46 · pedido alheio C47 · sem sessão C47 · conta C48 · lista de pedidos C48 · endereço novo C49 · exclusão do endereço C49 · `PUT` em endereço alheio C50 | - |
| estados da equipe (11) | login C51 · dashboard C52 · lista de pedidos C52 · detalhe do pedido C52 · lista de estoque C52 · lista de produtos C52 · ajuste de estoque C53 · exclusão pelo suporte C54 · produto sobrevive C54 · produto desconhecido C55 · sessões trocadas C56 | - |
| frações da mistura (6) | cadastro 1% C36 · pedido sem unidades 5% C39 · cartão aprovado 90% C44 · cartão recusado 10% C45 · ajuste de estoque 2% C53 · casos de erro 10% C92 | - |
| mapeamento de VU para conta (2) | cliente C37 · equipe C51 | - |
| espera até pagar (3) | `placed` consulta de novo C41 · `awaiting_payment` paga C41, C43 · 60 s desiste C42 | - |
| o que conta como erro inesperado (4) | status fora de `expect` C93 · timeout (status `0`) C93 · check do corpo que falhou com status esperado C94 · pedido que não ficou pagável em 60 s C42 | - |
| divisão dos VUs (4) | 50 VUs C71 · 150 VUs C71 · 300 VUs C71 · taxa de 500/s C71 | - |
| limites de aprovação (6 grupos) | `smoke` C65 · `load`, `stress` e `soak` C72, C77 · `spike` C73 · `breakpoint` C69 · `contention` C89 · `limits` sem limite de rajada C63 | - |
| resultados do `contention` (5) | `201` × 10 C57, C89 · `409` × 40 C57, C89 · `5xx` 0 C57, C89 · estoque final 0 C58 · pedidos cruzados 100 C59, C89 | - |
| rotas limitadas no `limits` (6) | login de cliente C60 · cadastro C60 · pedido C61 · pagamento C61 · validação de carrinho C62 · cotação de frete C62 | - |
| saídas do job de CI (4) | passa C78 · status inesperado C79 · pilha não sobe C80 · `make load-down` sempre C81 | - |
| linhas derivadas no fim da rodada (2) | `rate_limit_overshoot` C63, C91 · `breakpoint_rate_at_stop` C90 | - |
| documentos (5) | README C82 · `AGENTS.md` C83 · `backend/.env.example` C84 · `load-tests/README.md` C76, C85 · `.gitignore` C12 | - |

- O plano não tem rota em `Surface`: nenhuma rota muda. Os status que o k6 confere estão nas linhas de estados acima.
- Afirmações sobre status, rota ou forma de resposta (C20, C21, C27 a C62, C75) são provadas atravessando a fronteira, contra o `nginx` de carga. As decisões dos scripts (frações, mapeamentos, espera, divisão, limites e tags) têm uma prova própria no teste unitário do k6.
- Nenhum outro check afirma mais do que o caso que a sua prova exercita.

## Test policy

O README ("Em que nível testar") responde às duas perguntas para o código PHP, mas não para o que esta feature cria: `Makefile`, Compose, shell e JavaScript do k6. As linhas abaixo valem só para esse código.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Decisão em JavaScript do k6, sem rede (frações, mapeamentos, espera, divisão de VUs, forma e limites dos perfis, tags) | uma no próprio nível, com `k6 run` sobre `load-tests/tests/*.test.js` | um caso por linha da tabela de decisão, com o valor literal |
| Fluxo do k6 que fala com a API | uma na fronteira: a rodada do perfil contra a pilha de carga | um check por estado do plano, com o status e o `code` quando o plano dá o `code` |
| Alvo do `Makefile` que decide (perfil válido, limitador por perfil, ordem dos passos) | uma na fronteira, rodando o `make` (ou `make -n` para o mapeamento) | cada perfil, cada entrada recusada e cada saída de erro |
| Configuração de containers e do PHP (Compose, ini, pool) | uma na fronteira, inspecionando o container no ar | cada container que monta a configuração |
| `LoadTestSeeder` | uma na fronteira, por SQL no banco de carga depois do seed real | cada volume e a impressão digital de determinismo |
| Instrumentação (a chave `limiter` em `config/cache.php`) | nenhuma própria, além da leitura do valor padrão no Pest | coberta pelo C20 e pelo C21 |

Evidence:

- `Makefile` (alvo `load-test`): decide sobre 8 perfis válidos e 2 entradas recusadas, e mapeia 8 perfis para o limitador, com 10 pontos de decisão
- os perfis do k6: 6 formas de carga, 4 grupos de limites e 1 regra de divisão, com 11 pontos
- a mistura do k6: 2 mapeamentos de VU e 6 frações, com 8 pontos
- o `handleSummary`: 2 linhas derivadas (o excesso da rajada e a taxa na parada), com 3 pontos
- a espera até pagar: 3 saídas (consulta de novo, paga, desiste), com 3 pontos
- o ajudante de requisição: 4 parâmetros de rota trocados e o formato do `X-Request-ID`, com 5 pontos
- o que conta como erro inesperado (rodada 3 da verificação): o status fora de `expect` no `send()` e o corpo que falha com status esperado no `verify()`, sem contar duas vezes o status inesperado, com 3 pontos (C93, C94)
- análogo mais próximo no repositório: nenhum para JavaScript de carga, `Makefile` ou Compose. O precedente aqui é o "exercício temporário" do `ModuleBoundariesTest` (features `module-facades` C7 e `uuid-record-ids` C62), que prova uma propriedade da infraestrutura de teste com uma falha de propósito, revertida

Cost: os arquivos de teste novos em `load-tests/tests` (6 em JavaScript, contando o `harness.js`, e 10 scripts de shell, contando os ajudantes) e 1 teste Pest. Sem estas linhas, as 5 frações, os 2 mapeamentos e a espera seriam provados só pelo `smoke`, que percorre um caso de cada e não falha se a fração estiver errada.

Estas linhas não entram nas diretrizes do repositório, a não ser que diashelter peça.

## Swept

- validation: C11, porque o `make` recusa o `TYPE` ausente ou desconhecido antes de subir qualquer container; e C28, C32, C35, C40 e C46, porque o k6 confere sob carga as validações da API de hoje
- failure modes: C13 e C14, porque o limite cruzado e a pilha que não sobe saem com erro sem perder o relatório nem rodar o k6 em vão; e C42, porque o pedido que nunca fica pagável vira um erro contado, não um VU preso
- idempotency: C6 e C10, porque toda rodada recria os volumes e o mesmo conjunto de dados, e duas rodadas seguidas partem do mesmo estado
- authorization: existing - os guards `customer` e `staff`, o middleware `admin` e o dono na busca não mudam; C47, C54 e C56 os conferem sob carga, com `404`, `403` e `401`
- concurrency: C57 a C59, porque 50 compradores disputam 10 unidades e 50 pares cruzam a ordem dos itens; e C60 a C63, porque os limites são conferidos em sequência e numa rajada simultânea
- data lifecycle: C12, porque os relatórios ficam fora do git; C15 e C16, porque os volumes de carga somem no `load-down` e o banco de dev não muda; e C64, porque o `limits` não grava nada
- dependency failure: C14, porque a pilha de carga que não sobe nunca chega ao k6; e C42, porque um relay ou um worker lento demais vira a desistência em 60 s, sem travar a rodada
- state transitions: C41, C43 e C46, porque o pedido só é pago depois de `awaiting_payment`, e pagar de novo um pedido pago continua `409`
- observability: C41 (`order_payable_wait`), C63 (`rate_limit_overshoot`), C74 (a tag `name`), C75 (o `X-Request-ID` que liga a requisição do k6 aos logs) e C76 (a linha de base). Os p95 e as taxas de erro são metas de serviço: viram limites do perfil em C69, C72 e C73, e não critérios

## Handoff

- Por `wc -c` dividido por 4 sobre os arquivos que cada slice toca, contando como novos os scripts estimados:
  - S1 = ~9k (36 KB: as 10 factories e os 7 seeders lidos, o seeder novo e o `dataset.sh`);
  - S2 = ~10k (40 KB: `Makefile`, `config/cache.php`, `backend/.env.example`, `.gitignore`, o Compose, o ini e o pool novos, 4 scripts de prova e o teste Pest);
  - S3 = ~4k, S4 = ~5k, S5 = ~2k, S6 = ~3k e S7 = ~5k (o JavaScript do k6 e os testes dele, ~76 KB no total);
  - S8 = ~1k (`ci.yml` e `ci.sh`);
  - Fechamento = ~34k (o README, de 113 KB, e o `AGENTS.md`, de 13 KB).
- Total: ~73k, abaixo do budget de 150k. Um builder só, sem corte.
- Mechanism: one builder (o total cabe no budget).
- Bloqueio: C60 a C62 esperam `helterdias/fix-per-route-throttle` na `main` e a `main` nesta branch (pergunta aberta 1 do plano). O resto não depende disso. O C78 também espera o push desta branch, que precisa de autorização.
- **Boundary:** C1 a C87 fechados no código em `e467086` (pilha, seeder e scripts), `25fb9dc` (CI), `86126c1` (taxa na parada do `breakpoint`), `462ee68`, `2c1ea8f` (limites da linha de base) e `ae0ceef` (documentos). C60 a C62, C64, C35, C54 e C65 dependiam do PR [#18](https://github.com/diashelter/laravel-module-ecomerce/pull/18): sem ele, as rotas limitadas de um mesmo IP somavam num contador só. Eles passaram depois do merge da `main` em `d851b01`: o `smoke` com os limites reais teve 48 de 48 checks, e o `limits` teve 13 de 13. Só a segunda prova do C78 (a execução do CI) espera o push desta branch.
- **Settled mid-build:**
  - O AC 66 foi lido com a vitrine ficando com o restante (ver "Interpretação do AC 66").
  - O `about --json` mostra os logs como `"stderr"`, não como lista: o C17 foi corrigido antes do código.
  - `CACHE_LIMITER` vazio quebrava a API (o cache não tem store `""`), então a configuração lê `env('CACHE_LIMITER') ?: null`.
  - O painel do k6 só exporta o HTML com 3 períodos de dados, então `K6_WEB_DASHBOARD_PERIOD=2s`.
  - O `dataset.sh` para o worker e o relay durante o `migrate:fresh`, porque o relay cai quando a tabela some.
  - Os casos de erro sem fração no plano rodam em 1 de 10 iterações dos perfis de volume.
  - O `breakpoint` mantém as pausas de 1 a 3 s, com no máximo 1.000 VUs.
  - As duas primeiras rodadas do `breakpoint` ficaram em commits diferentes; as linhas foram medidas de novo em `09760e2`, com as duas rodadas de cada perfil no mesmo commit (ver a rodada 1 da verificação abaixo).
  - A rajada de 30 logins simultâneos deixou passar 11, um além do limite: o limitador confere e depois conta. O plano manda registrar sem reprovar (AC 58), e o achado vira uma issue própria. Corrigido na branch `helterdias/fix-throttle-race` (`AtomicThrottleRequests` e `AtomicRateLimiter`): depois do merge com esta branch, o `limits` teve 13 de 13 checks, `rate_limit_burst_passed` = 10 e `rate_limit_overshoot` = 0, e as provas do C63 continuam passando.
  - O door 6 chama `rate_limit_overshoot` de contador, mas ele é uma linha derivada no fim da rodada (`rate_limit_burst_passed − 10`, nunca negativa): uma rodada do k6 não consegue gravar uma métrica depois de terminar. O contador de verdade é o `rate_limit_burst_passed` (C63, C91).
- **Rodada 1 da verificação (FAIL, 4 mutantes vivos):** o Verifier achou as lacunas abaixo, corrigidas em `52bebb6` e `301a7f8`.
  - O teste da tag olhava o `requestName`, não o que o `send()` manda ao k6: o pedido agora sai de um `buildRequest` puro, testado (C74).
  - A contagem do erro da desistência estava só no fluxo: ela foi para o `countGiveUp`, testado (C42).
  - Os logs da `api` impressos na falha do seed não eram conferidos: a prova agora exige linhas do `docker compose logs` depois do aviso (C14).
  - O C77 conferia só o `load`: agora confere também o `stress` e o `soak`.
  - Ganharam caso próprio os limites do `contention` (C89), a taxa na parada do `breakpoint` (C90), o excesso da rajada (C91) e a fração dos casos de erro (C92). Os valores do door 3 que faltavam ganharam o C88.
  - 42 pedidos do seed caíam no futuro: o seeder limita a data a agora, e o `dataset.sh` confere (C4).
  - O valor da `order_payable_wait` passou a ser conferido (C41). O C26 compara com `origin/main...HEAD`, e não com a `main` local desatualizada. As provas do README e do aceite de 20% ficaram mais justas (C82, C85).
  - As linhas da linha de base tinham sido medidas antes do merge `d851b01`. Elas foram medidas de novo em `09760e2`, com as duas rodadas de cada perfil no mesmo commit. Os números registrados foram para `lib/baseline.js`, fora do código medido, e o C76 passou a exigir que o código medido não mude entre o commit da linha e `HEAD`.
- **Abandoned:**
  - Guardar o `X-Request-ID` enviado na resposta do k6: a resposta é um objeto do host e não aceita propriedade nova. Ficou uma variável do módulo.
  - Medir o primeiro `breakpoint` sem a taxa na parada: a taxa passou a ser calculada no `handleSummary` a partir do fim do `setup()`.
- **Rodada 2 da verificação (FAIL, 4 mutantes vivos):** corrigida em `51897fb`.
  - O `send()` passou a ser montado sobre um cliente HTTP (`createSender`), e o teste usa um cliente falso para provar o que ele entrega ao k6 (C74, mutante F3c).
  - O `countGiveUp` passou a receber a própria métrica, e a prova fixa a chamada exata no fluxo (C42, F1b).
  - A rajada conta pela decisão `passedTheLimiter`, testada, com a chamada exata fixada (C63).
  - A regra dos 20% é conferida pela frase inteira (C85). A `order_payable_wait` ganhou um teto de 60 s (C41).
  - O texto do C14 diz de onde vem a mensagem. O `make.sh` cria `results/`, e os scripts recusam uma opção sem valor em vez de travar.
  - As 4 variantes foram mortas num worktree antes da rodada 3 (F3c com saída 99).
- **Linha de base:** a correção da rodada 2 mudou o código medido, e as linhas em `09760e2` deixaram de valer pelo C76. Em `d563093`, com a máquina ocupada (carga média de 3 a 6), cinco rodadas do `load` variaram até 33% entre pares seguidos, e nenhuma foi aceita. A pedido de diashelter, a medição foi refeita com a máquina sem outros aplicativos pesados. Em `f58ff69`, os dois pares cumpriram a regra dos 20% sobre o menor p95: no `load`, 30,3 e 30,0 ms na vitrine e 50,5 e 55,4 ms em `POST /api/orders`; no `breakpoint`, a parada em 54,8 e 49,4 it/s. As linhas e o `lib/baseline.js` são dessa medição.
- **Rodada 3 da verificação (FAIL, 1 mutante vivo):** a HEL-14 entrou na `main` assim (PR [#19](https://github.com/diashelter/laravel-module-ecomerce/pull/19), `746797a`), por decisão de diashelter. A correção veio depois, em `aa594f7`, a partir da `main` com o PR [#20](https://github.com/diashelter/laravel-module-ecomerce/pull/20) (`0e5a0eb`).
  - O `createSender` passou a receber a métrica em que conta os status inesperados, e o `verify()` passou a ser montado por um `createVerifier`, que recebe o `check` do k6 e a mesma métrica, como o `countGiveUp` já fazia. Os casos `unexpected-status` e `body-check` do `http.test.js` leem o que foi contado (C93 e C94, lacunas 1 e 2 da rodada 3), e as duas ligações reais são fixadas pela linha exata, como no C42.
  - Mutantes vistos falhando antes da rodada 4: F7b (`errors.add(false)` no `send()`), a contagem invertida do `send()`, `errors.add(false)` e a contagem removida no `verify()` e a condição `if (!bodyOk)`, que contaria de novo o status inesperado, todos com saída 99; e as duas ligações trocadas por `{ add: () => {} }`, com o `grep` saindo 1.
  - O `k6.sh` rodado logo depois de um `git checkout` do arquivo pode sair 107 (`couldn't be found on local disk`): o checkout apaga e recria o arquivo, e o compartilhamento de arquivos do Docker demora a enxergá-lo. Repetida depois de alguns segundos, a rodada sai com o código da mutação.
  - **C76 reescrito, a pedido de diashelter (2026-10-17):** a prova comparava o commit da linha com `origin/main` e `HEAD`, então só valia antes do merge. Depois do PR #19, ela saía 1 com "f58ff69 já está na main", e qualquer mudança posterior no código medido (o PR #20 no `backend/`, este fix em `load-tests/lib/http.js`) também a reprovaria. Agora o commit da linha precisa estar na branch da HEL-14 e fora da `main` anterior, e o código medido é comparado até o merge `746797a`, o commit que fecha a feature (AC 71). A prova nova passa em `HEAD`.
  - A segunda prova do C78 passou: a última execução do CI na branch da HEL-14 (`0b150ea`) terminou com `success`.
- **Lições:** L-019 a L-025 foram gravadas como candidatas a partir das rodadas 1 e 2.
