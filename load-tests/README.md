# Testes de carga (k6)

Testes de desempenho e de carga da API com o [k6](https://grafana.com/docs/k6/latest/) (HEL-14). O design está em [.design/load-tests-k6.md](../.design/load-tests-k6.md), e o plano e os checks em [.specs/features/load-tests-k6](../.specs/features/load-tests-k6/).

Os números servem de **linha de base**: comparam uma rodada com a próxima, na mesma máquina, com a mesma configuração e o mesmo conjunto de dados. Eles não dizem quanto um servidor de produção aguentaria.

## Como rodar

Tudo roda no Docker; só é preciso ter o Docker e o `make`.

```bash
make load-test TYPE=smoke
```

Para rodar com o relay publicando pelo RabbitMQ, em vez da fila do Laravel (Redis), que é o padrão:

```bash
make load-test TYPE=smoke TRANSPORT=rabbitmq
```

```bash
make load-down
```

Cada `make load-test` faz, em ordem:

1. apaga os containers e os volumes do projeto Compose `ecommerce-load`;
2. sobe o Postgres, o Redis e o RabbitMQ de carga, roda as migrations do zero e o `LoadTestSeeder`;
3. gera os caches de config, rotas e eventos dentro do container;
4. grava o transporte escolhido (`TRANSPORT`, `redis` por padrão) na flag `event-transport` da pilha, e sobe a API, o worker (Horizon), o relay, o consumidor do RabbitMQ e o Nginx de carga;
5. roda o perfil no container `k6`.

A pilha fica no ar depois da rodada, para inspeção. O `make load-down` a remove junto com os volumes.

Um perfil desconhecido ou ausente é recusado antes de subir qualquer container.

## A pilha de carga

O projeto `ecommerce-load` (`load-tests/docker-compose.yml`) roda ao lado da pilha de dev, que não precisa ser parada. As duas pilhas compartilham só a imagem do PHP e a árvore `backend/`.

- **Banco, Redis e RabbitMQ próprios.** O banco é o `ecommerce_load`, recriado a cada rodada, e o RabbitMQ usa o vhost `/`, sem porta no host. Os dados de dev nunca mudam.
- **Configuração de produção.** A pilha roda com:
  - `APP_ENV=production` e `APP_DEBUG=false`;
  - logs de `warning` para cima na saída padrão;
  - opcache sem revalidação de arquivos;
  - config, rotas, eventos e módulos em cache;
  - PHP-FPM com `pm = static` e 16 workers (o de dev tem 5 dinâmicos);
  - bcrypt com custo 12, como em dev.
- **Nada gravado na árvore que a pilha de dev lê.** Os caches ficam em `/tmp/laravel` dentro do container, e os logs vão para a saída padrão. Nunca rode `php artisan optimize` no container de carga: o cache de configuração iria para `backend/bootstrap/cache`, e a API de dev passaria a ler o banco de carga. O cache de módulos (`modules:cache`, que o `make load-test` roda) também fica em `/tmp/laravel`, pela variável `APP_MODULES_CACHE`, que o `AppServiceProvider` liga ao cache da biblioteca de módulos.
- **Sem porta no host.** O k6 fala com o Nginx de carga pela rede do Compose.
- **Autenticação como a do SPA.** O k6 usa o cookie de CSRF, a sessão por cookie e o `Origin` de um domínio stateful (`nginx`). Não há token nem rota só de teste, e o k6 descobre os ids pela API, nunca pelo banco.

### O conjunto de dados

O `LoadTestSeeder` produz sempre os mesmos dados, a partir da semente fixa do Faker:

- 1.000 clientes, de `load-customer-0@example.com` a `load-customer-999@example.com`, com a senha `password` e um endereço cada;
- `admin@example.com` e `suporte@example.com`;
- 8 categorias e 201 produtos:
  - 170 ativos com 1.000.000 de unidades;
  - 10 ativos sem unidades;
  - 20 inativos;
  - o `Produto Disputado`, ativo, com 10 unidades;
- 10.000 pedidos de histórico.

O `load-customer-0` não é usado por nenhum VU: os pedidos e o endereço dele são o "outro cliente" dos casos de erro. O seed leva cerca de 5 segundos.

### Os limites de requisição

As rotas mantêm os seus `throttle`. Nos perfis de volume e no `contention`, a pilha roda com `CACHE_LIMITER=array`: com o PHP-FPM, esse armazenamento dura uma requisição, então nenhum limite é atingido, e o k6 mede a API, não o limitador. O `smoke` e o `limits` rodam com os limites reais.

## Os perfis

Os perfis de volume dividem os VUs entre três fluxos:

- **Vitrine** (cerca de 75%): o visitante anônimo navega e valida o carrinho.
- **Compra** (20%): o cliente compra, espera o pedido ficar pagável e paga.
- **Equipe** (5%): o painel do admin.

| Perfil | Forma da carga | Limites de requisição | Limites de aprovação |
|---|---|---|---|
| `smoke` | 1 VU por fluxo, uma iteração, passando por todos os casos de erro | reais | nenhum erro inesperado e 100% dos checks |
| `load` | 0 a 50 VUs em 1 min, 5 min em 50, 1 min descendo | inertes | erro inesperado < 1%; p95 da vitrine, de `POST /api/orders` e de `order_payable_wait` abaixo dos limites da linha de base (hoje 40 ms, 70 ms e 2.450 ms) |
| `stress` | 0 a 150 VUs em 2 min, 5 min em 150, 2 min descendo | inertes | os mesmos do `load` |
| `spike` | 50 VUs por 1 min, salto para 300 em 10 s, 1 min em 300, volta a 50 em 10 s e 2 min em 50 | inertes | erro inesperado < 5% |
| `breakpoint` | taxa de chegada de 5 a 500 iterações/s em 15 min (`ramping-arrival-rate`) | inertes | aborta com erro inesperado > 5% ou p95 > 2 s, avaliado depois de 30 s |
| `soak` | 50 VUs por 1 hora | inertes | os mesmos do `load` |
| `contention` | 50 clientes compram ao mesmo tempo 1 unidade do `Produto Disputado`, e 50 pares de pedidos cruzam a ordem dos itens | inertes | exatamente 10 pedidos criados e 40 recusados (`409`), nenhum `5xx`, estoque final 0 e os 100 pedidos cruzados criados |
| `limits` | cada rota limitada até o limite e mais uma, e uma rajada de 30 logins simultâneos | reais | o pedido além do limite recebe `429` em cada rota |

```bash
make load-test TYPE=smoke
make load-test TYPE=load
make load-test TYPE=stress
make load-test TYPE=spike
make load-test TYPE=breakpoint
make load-test TYPE=soak
make load-test TYPE=contention
make load-test TYPE=limits
```

### Limites de aprovação

Quando um limite cai, o `make load-test` sai com código diferente de zero, e os relatórios são gravados mesmo assim.

- **Erro inesperado** (`unexpected_errors`): cada requisição declara os status que espera. Um `404` para o pedido de outro cliente ou um `409` para quem perde a disputa de estoque são acertos. Contam como erro só:
  - um status que a requisição não esperava;
  - um timeout;
  - um check do corpo que falhou;
  - um pedido que não ficou pagável em 60 segundos.
- **`order_payable_wait`:** o tempo entre o `201` do pedido e a leitura que o mostra em `awaiting_payment`. O k6 consulta o pedido a cada 250 ms (o frontend consulta a cada 2 s), então a resolução da métrica é de 250 ms. Ela mede o relay do outbox e o worker, ou o consumidor do RabbitMQ, sob carga. Até a HEL-13 o k6 consultava a cada 2 s, e a linha de base da HEL-14 tem essa resolução: com 2 s, os dois transportes caíam na mesma leitura.
- **`rate_limit_overshoot`:** quantos logins da rajada simultânea do `limits` passaram além do limite de 10. A métrica é só registrada, sem limite de aprovação. O esperado é 0: o `throttle` conta a requisição antes de decidir (`AtomicThrottleRequests`, ver o [README do projeto](../README.md)), e passam exatamente 10. Com o `throttle` do framework, que confere antes de contar, passavam 11.

Antes da primeira linha de base, os limites de latência eram 500 ms na vitrine, 1 s em `POST /api/orders` e 10 s em `order_payable_wait`. Agora eles são o p95 da linha do `load` vezes 1,2, arredondado para cima até a dezena de ms seguinte: 40 ms, 70 ms e 2.450 ms (`BASELINE` em `lib/baseline.js`, que muda junto com a linha). O `stress` e o `soak` usam os mesmos limites, então eles costumam reprovar: o relatório mostra quanto a API degradou.

## Os relatórios

Cada rodada grava em `load-tests/results/`, que fica fora do git:

- `<perfil>-<data e hora>.html`: o relatório do painel do k6, com gráficos;
- `<perfil>-<data e hora>.json`: o resumo completo. Numa rodada com `TRANSPORT=rabbitmq`, os dois se chamam `<perfil>-rabbitmq-<data e hora>`;
- `<perfil>-checks.txt`: uma linha por check e por métrica personalizada, lida pelas provas.

As métricas de tempo são agrupadas por template de rota, na tag `name` (por exemplo `GET /api/orders/{order}`), nunca pela URL com o id. Toda requisição leva um `X-Request-ID` no formato `<perfil>-vu<n>-it<n>-<sequência>`, que a API grava nos logs dela.

## Linha de base

Uma linha só entra na tabela quando duas rodadas seguidas do mesmo perfil, no mesmo commit, têm o p95 a menos de 20% uma da outra. A diferença é medida sobre o menor dos dois p95, e feche os outros aplicativos pesados antes de medir. Acima disso, a diferença é ruído da máquina, e a linha não serve para comparar.

Mudar a máquina, a configuração da pilha, o `LoadTestSeeder`, o pool do FPM ou a versão do k6 abre uma série nova. A coluna "Mudou desde a série anterior" diz o que mudou.

O k6 roda na mesma VM do Docker que a API e disputa CPU com ela. Por isso, os números só se comparam com rodadas da mesma máquina.

| Data | Commit | Máquina | Perfil | VUs ou taxa | req/s | p95 vitrine (ms) | p95 POST /api/orders (ms) | p95 order_payable_wait (ms) | Erro inesperado | Ponto de ruptura | Mudou desde a série anterior |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 2026-10-16 | f58ff69 | Apple M1, VM do Docker com 8 CPUs e 4 GB | load | 50 VUs | 47.3 | 30.0 | 55.4 | 2035 | 0% | - | primeira série |
| 2026-10-16 | f58ff69 | Apple M1, VM do Docker com 8 CPUs e 4 GB | breakpoint | 5 a 500 it/s | 127.3 | - | - | 3978 | 0% | 49.4 it/s (p95 acima de 2 s aos 82 s) | primeira série |

Como as linhas foram aceitas:

- **`load`:** a rodada anterior, no mesmo commit, teve p95 de 30,3 ms na vitrine, 50,5 ms em `POST /api/orders` e 2.034 ms em `order_payable_wait`. Todos ficaram a menos de 20% da linha registrada, medido sobre o menor p95. A espera até pagar fica perto de 2 segundos porque é a resolução da consulta: o pedido já está pagável na primeira leitura.
- **`breakpoint`:** a rodada anterior, no mesmo commit, parou em 54,8 it/s, a menos de 20% de 49,4. A ruptura vem da fila do PHP-FPM: com 16 workers e as pausas de 1 a 3 s de cada passo, a taxa de chegada acumula centenas de VUs antes do aborto.
- As quatro rodadas foram feitas com a máquina sem outros aplicativos pesados abertos (carga média perto de 3 no início de cada uma). O código medido (`backend/`, `docker/`, o `Makefile` e o Compose e o JavaScript desta pasta) não mudou entre `f58ff69` e o merge `746797a` do PR #19, onde a linha de base fica ancorada; até esse merge, depois da medição, mudaram só esta tabela e `lib/baseline.js`. As mudanças posteriores no código medido, as da própria HEL-14 incluídas, não reescrevem estas linhas: elas são os números daquele commit. Nenhuma prova avisa quando o código medido muda depois da medição, então quem o muda compara uma rodada nova com a linha. Por exemplo:
  - o `throttle` atômico do PR #20 (`AtomicThrottleRequests` e `AtomicRateLimiter`): nos perfis de volume, com o limitador em `array`, o `AtomicRateLimiter` continua usando o incremento do framework, e só o middleware mudou;
  - a métrica injetada no `createSender` e no `createVerifier` (`aa594f7`), uma refatoração do harness que não muda o que a rodada executa;
  - o relógio da `order_payable_wait`, que saiu do fluxo da compra para o `placeAndWaitUntilPayable` de `lib/payable.js`, testado com um relógio falso: as requisições, os checks e as métricas são os mesmos, e o relógio começa logo depois do `verify` do `201`, e não logo antes, e para logo antes do check da espera, e não logo depois, uma diferença de uma leitura de JSON e de um check do k6 em cada ponta. Uma rodada do `load` em `5fc8199`, com a máquina ocupada (carga média de 6,7 no início), teve p95 de 2.047 ms em `order_payable_wait`, a 0,6% da linha. A vitrine (38,9 ms) e `POST /api/orders` (66,1 ms), que essa mudança não toca, variaram mais, como nas rodadas recusadas abaixo.
- **Rodadas não aceitas, antes desta medição.** Com a máquina ocupada (carga média de 3 a 6), cinco rodadas seguidas do `load` em `d563093`, o mesmo código medido, variaram até 33% entre pares seguidos. O p95 de `POST /api/orders` foi de 47,9 a 74,8 ms, e nenhuma linha entrou. É a razão da regra dos 20%: numa máquina ocupada, a diferença entre duas rodadas é maior que a de uma mudança no código.

### Linha de base por transporte

A HEL-13 mede o mesmo perfil `load` com o relay publicando por cada transporte (`make load-test TYPE=load TRANSPORT=<redis|rabbitmq>`), com a espera até pagar lida a cada 250 ms. É uma tabela à parte porque a da HEL-14 tem 12 colunas, travadas pelas provas `docs.sh baseline-rows` e `profiles.test.js`, e porque os limites de aprovação continuam sendo os dela (2.450 ms na espera): a série nova não os recalcula. A regra para aceitar uma linha é a mesma de cima.

| Data | Commit | Máquina | Transporte | Perfil | VUs ou taxa | req/s | p95 vitrine (ms) | p95 POST /api/orders (ms) | p95 order_payable_wait (ms) | Erro inesperado | Mudou desde a série anterior |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 2026-10-18 | 99094d7 | Apple M1, VM do Docker com 8 CPUs e 4 GB | redis | load | 50 VUs | 50.6 | 35.6 | 50.2 | 1382 | 0% | primeira série. Aceita pela regra dos 20%: a rodada anterior do redis, no mesmo commit, teve p95 de 30,4 ms na vitrine, 49,4 ms em `POST /api/orders` e 1.369 ms em `order_payable_wait` |
| 2026-10-18 | 99094d7 | Apple M1, VM do Docker com 8 CPUs e 4 GB | rabbitmq | load | 50 VUs | 50.1 | 32.3 | 52.2 | 558 | 0% | primeira série. Aceita pela regra dos 20%: a rodada anterior do rabbitmq, no mesmo commit, teve p95 de 29,7 ms na vitrine, 47,5 ms em `POST /api/orders` e 555 ms em `order_payable_wait` |
| 2026-10-20 | 299af92 | Apple M1, VM do Docker com 8 CPUs e 4 GB | redis | load | 50 VUs | 51.4 | 24.1 | 39.2 | 1353 | 0% | módulos como pacotes do `internachi/modular` (HEL-12), com o cache de módulos da pilha de carga em `/tmp/laravel`. Aceita pela regra dos 20%: a rodada anterior do redis, no mesmo commit, teve p95 de 24,9 ms na vitrine, 38,4 ms em `POST /api/orders` e 1.360 ms em `order_payable_wait`; as duas ficaram dentro dos limites da `lib/baseline.js`. A máquina não estava na condição pedida (carga média de 3 a 4, com o `db`, o `redis` e o `rabbitmq` da pilha de dev no ar). |

- **O que a tabela mostra.** Com a vitrine e `POST /api/orders` iguais dentro do ruído, a espera até pagar do p95 caiu de cerca de 1,4 s no Redis para cerca de 0,56 s no RabbitMQ. A causa não foi medida; a hipótese é o `sleep` de 1 s do supervisor do Horizon quando a fila está vazia, contra a entrega por push do consumidor do RabbitMQ. Nenhum dos dois transportes teve erro inesperado.
- **Como foram feitas.** Quatro rodadas no commit `99094d7`, com a pilha de dev parada: duas do rabbitmq em sequência (15:43 e 15:50) e duas do redis em sequência (16:17 e 16:24). O código medido não mudou até o commit que grava esta tabela, que só mexe nos documentos.
- **Rodadas descartadas, todas do mesmo dia.** A máquina (8 GB, com 4,7 a 4,9 GB de swap em uso e uma VM do Docker que chegou a 300% de CPU sem nenhum container no ar) deixou a maioria das rodadas fora dos 20%: o p95 da vitrine foi de 24 a 949 ms entre rodadas do mesmo transporte e do mesmo código, e o do `POST /api/orders`, de 35 a 1.372 ms. As rodadas do redis às 14:29 e às 14:37 diferiram 29% na vitrine; as do rabbitmq às 15:10 e às 15:18, 83% na espera. Só entraram os pares que passaram nos três p95.
- **Duas rodadas do rabbitmq caíram no arranque** com `Error when reading /var/lib/rabbitmq/.erlang.cookie: eacces`, com o host a mais de 15 de carga. O `make load-test` agora tenta subir o broker de novo uma vez.

### Os módulos como pacotes (HEL-12)

A HEL-12 trocou os módulos de `App\Modules` por pacotes do `internachi/modular`. O `load` no commit `0966d0b`, duas rodadas seguidas, ficou **fora dos limites** da `lib/baseline.js` (a vitrine e o `POST /api/orders`), e o mesmo `load` em `main` (`f1dd960`), medido logo antes na mesma máquina, ficou dentro. A causa foi achada e corrigida (abaixo), e a linha do `redis` no commit `299af92` entrou na tabela por transporte, acima.

| Rodada | Commit | p95 vitrine (ms) | p95 POST /api/orders (ms) | p95 order_payable_wait (ms) | Limites |
|---|---|---|---|---|---|
| controle, `main` | f1dd960 | 24.8 | 38.1 | 1352 | dentro |
| módulos como pacotes, rodada 1 | 0966d0b | 272.8 | 297.2 | 1550.5 | vitrine e `POST /api/orders` acima |
| módulos como pacotes, rodada 2 | 0966d0b | 275.7 | 274.6 | 1520.0 | vitrine e `POST /api/orders` acima |
| com o cache de módulos, rodada 1 | 299af92 | 24.9 | 38.4 | 1360.4 | dentro |
| com o cache de módulos, rodada 2 | 299af92 | 24.1 | 39.2 | 1353.0 | dentro |

As duas rodadas dos pacotes diferem menos de 20% entre si, então o resultado não é ruído da máquina. As quatro rodadas foram feitas com a carga média da máquina entre 4 e 6 e com o `db`, o `redis` e o `rabbitmq` da pilha de dev no ar, o que não é a condição pedida acima; o controle foi medido nas mesmas condições.

**A causa medida.** A biblioteca descobre os módulos no sistema de arquivos a cada boot da aplicação quando não existe o cache de módulos, e cada requisição do PHP-FPM faz esse boot. Numa pilha de carga recém-criada, 100 requisições seguidas a `/api/products`, sem concorrência:

| Descoberta de módulos | Mediana por requisição |
|---|---|
| requisição sem `modules:cache` | 54.4 ms |
| requisição com `modules:cache` | 16.2 ms |

Custo de descobrir os módulos a cada boot do `artisan`, como a mediana de 20 execuções de `php artisan --version` num worktree descartável:

| Descoberta de módulos | Mediana de 20 execuções |
|---|---|
| sem `modules:cache` | 222.2 ms |
| com `modules:cache` | 190.3 ms |

O CLI mostra só uma parte do custo (cerca de 32 ms), porque o PHP-FPM lê a mesma árvore de dentro de um volume montado do macOS, e o custo se multiplica com 50 VUs. Em produção, sem volume montado, o custo deve ser menor, mas isso não foi medido. **A correção.** O `modules:cache` grava `bootstrap/cache/app-modules.php`, que a pilha de carga não pode escrever (regra do `AGENTS.md`), e a biblioteca não aceita outro caminho. O `AppServiceProvider` religa o `Cache` da biblioteca ao caminho de `APP_MODULES_CACHE` (sem a variável, o caminho continua `bootstrap/cache/app-modules.php`), a pilha de carga define a variável como `/tmp/laravel/app-modules.php`, e o `make load-test` roda o `modules:cache` no container `api`, com os outros caches. Os containers de fila, relay e consumidor definem a variável e não têm o arquivo: descobrem os módulos a cada boot, que acontece uma vez por processo.

## As provas dos scripts

As decisões dos scripts (frações da mistura, divisão dos VUs, forma e limites dos perfis, espera até pagar, tags) são provadas pelo próprio k6, sem rede:

```bash
sh load-tests/tests/k6.sh profiles.test.js --only load
```

Os outros scripts de `load-tests/tests` conferem uma rodada (`assert-check.sh`, `assert-metric.sh`), o banco de carga (`dataset.sh`), os containers (`stack.sh`), o `make` (`make.sh`), o isolamento da pilha de dev (`isolation.sh`), os documentos (`docs.sh`) e o workflow de CI (`ci.sh`). Cada check de [.specs/features/load-tests-k6/checks.md](../.specs/features/load-tests-k6/checks.md) diz qual deles o prova.
