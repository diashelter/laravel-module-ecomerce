# Testes de carga (k6)

Testes de desempenho e de carga da API com o [k6](https://grafana.com/docs/k6/latest/) (HEL-14). O design está em [.design/load-tests-k6.md](../.design/load-tests-k6.md), e o plano e os checks em [.specs/features/load-tests-k6](../.specs/features/load-tests-k6/).

Os números servem de **linha de base**: comparam uma rodada com a próxima, na mesma máquina, com a mesma configuração e o mesmo conjunto de dados. Eles não dizem quanto um servidor de produção aguentaria.

## Como rodar

Tudo roda no Docker; só é preciso ter o Docker e o `make`.

```bash
make load-test TYPE=smoke
```

```bash
make load-down
```

Cada `make load-test` faz, em ordem:

1. apaga os containers e os volumes do projeto Compose `ecommerce-load`;
2. sobe o Postgres e o Redis de carga, roda as migrations do zero e o `LoadTestSeeder`;
3. gera os caches de config, rotas e eventos dentro do container;
4. sobe a API, o worker, o relay e o Nginx de carga;
5. roda o perfil no container `k6`.

A pilha fica no ar depois da rodada, para inspeção. O `make load-down` a remove junto com os volumes.

Um perfil desconhecido ou ausente é recusado antes de subir qualquer container.

## A pilha de carga

O projeto `ecommerce-load` (`load-tests/docker-compose.yml`) roda ao lado da pilha de dev, que não precisa ser parada. As duas pilhas compartilham só a imagem do PHP e a árvore `backend/`.

- **Banco e Redis próprios.** O banco é o `ecommerce_load`, recriado a cada rodada. Os dados de dev nunca mudam.
- **Configuração de produção.** A pilha roda com:
  - `APP_ENV=production` e `APP_DEBUG=false`;
  - logs de `warning` para cima na saída padrão;
  - opcache sem revalidação de arquivos;
  - config, rotas e eventos em cache;
  - PHP-FPM com `pm = static` e 16 workers (o de dev tem 5 dinâmicos);
  - bcrypt com custo 12, como em dev.
- **Nada gravado na árvore que a pilha de dev lê.** Os caches ficam em `/tmp/laravel` dentro do container, e os logs vão para a saída padrão. Nunca rode `php artisan optimize` no container de carga: o cache de configuração iria para `backend/bootstrap/cache`, e a API de dev passaria a ler o banco de carga.
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
- **`order_payable_wait`:** o tempo entre o `201` do pedido e a leitura que o mostra em `awaiting_payment`. O k6 consulta o pedido a cada 2 segundos, como o frontend faz, então a resolução da métrica é de 2 segundos. Ela mede o relay do outbox e o worker sob carga.
- **`rate_limit_overshoot`:** quantos logins da rajada simultânea do `limits` passaram além do limite de 10. A métrica é só registrada, sem limite de aprovação. O esperado é 0: o `throttle` conta a requisição antes de decidir (`AtomicThrottleRequests`, ver o [README do projeto](../README.md)), e passam exatamente 10. Com o `throttle` do framework, que confere antes de contar, passavam 11.

Antes da primeira linha de base, os limites de latência eram 500 ms na vitrine, 1 s em `POST /api/orders` e 10 s em `order_payable_wait`. Agora eles são o p95 da linha do `load` vezes 1,2, arredondado para cima até a dezena de ms seguinte: 40 ms, 70 ms e 2.450 ms (`BASELINE` em `lib/baseline.js`, que muda junto com a linha). O `stress` e o `soak` usam os mesmos limites, então eles costumam reprovar: o relatório mostra quanto a API degradou.

## Os relatórios

Cada rodada grava em `load-tests/results/`, que fica fora do git:

- `<perfil>-<data e hora>.html`: o relatório do painel do k6, com gráficos;
- `<perfil>-<data e hora>.json`: o resumo completo;
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
  - o relógio da `order_payable_wait`, que saiu do fluxo da compra para o `placeAndWaitUntilPayable` de `lib/payable.js`, testado com um relógio falso: as requisições, os checks e as métricas são os mesmos, e o relógio começa logo depois do `verify` do `201`, e não logo antes, e para logo antes do check da espera, e não logo depois, uma diferença de uma leitura de JSON e de um check do k6 em cada ponta.
- **Rodadas não aceitas, antes desta medição.** Com a máquina ocupada (carga média de 3 a 6), cinco rodadas seguidas do `load` em `d563093`, o mesmo código medido, variaram até 33% entre pares seguidos. O p95 de `POST /api/orders` foi de 47,9 a 74,8 ms, e nenhuma linha entrou. É a razão da regra dos 20%: numa máquina ocupada, a diferença entre duas rodadas é maior que a de uma mudança no código.

## As provas dos scripts

As decisões dos scripts (frações da mistura, divisão dos VUs, forma e limites dos perfis, espera até pagar, tags) são provadas pelo próprio k6, sem rede:

```bash
sh load-tests/tests/k6.sh profiles.test.js --only load
```

Os outros scripts de `load-tests/tests` conferem uma rodada (`assert-check.sh`, `assert-metric.sh`), o banco de carga (`dataset.sh`), os containers (`stack.sh`), o `make` (`make.sh`), o isolamento da pilha de dev (`isolation.sh`), os documentos (`docs.sh`) e o workflow de CI (`ci.sh`). Cada check de [.specs/features/load-tests-k6/checks.md](../.specs/features/load-tests-k6/checks.md) diz qual deles o prova.
