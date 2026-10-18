# Transporte de eventos por feature flag: Redis com Horizon e RabbitMQ - checks

Profile: standard
Plan: `.specs/features/event-transport-flag/plan.md`

66 checks em 7 slices, a documentação e o fechamento · 8 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa, como na HEL-9. Antes da primeira prova, `make test-prepare` cria o banco e o vhost de testes; o `phpunit.xml` passa a trazer `RABBITMQ_VHOST=ecommerce_testing` como padrão, que o `make test` sobrepõe num worktree. As provas do ambiente rodam na raiz do repositório, no host, pelo script novo `docker/tests/environment.sh --only <caso>`, no estilo de `load-tests/tests` (cada caso sai com 1 e diz o que falhou). As provas da pilha de carga usam os scripts que já existem em `load-tests/tests`. O Pint roda via `make pint`. Os nomes de teste novos não usam parênteses, colchetes nem barras, porque o `--filter` do Pest é uma expressão regular. A base da feature é `348d335`.

Termos usados nos checks:

- **Vhost de testes:** `ecommerce_testing` na pasta principal, `ecommerce_testing_<worktree>` num worktree.
- **Vhost limpo:** só com as exchanges padrão do broker (`""` e as `amq.*`) e nenhuma fila. Todo teste que usa o broker começa assim (C6).
- **API de gestão:** a API HTTP do plugin de gestão do RabbitMQ, em `http://rabbitmq:15672/api`, com as credenciais de `RABBITMQ_USER` e `RABBITMQ_PASSWORD`. Os testes a usam para ler filas, argumentos, ligações e contagens; nenhuma prova a usa para escrever.
- **Fixtures de mensageria:** as de `tests/Feature/Outbox/Fixtures`, que a HEL-9 já usa. O evento de teste (`testing.sample_event`, versão 1) e os consumidores inscritos com os nomes `testing.spy_consumer` (espião), `testing.second_spy_consumer` (segundo espião) e `testing.failing_consumer` (sempre lança `RuntimeException('consumer down')`). O espião guarda cada evento recebido, o `Context::get('request_id')` do momento da chamada e, quando o teste pede, a contagem de mensagens não confirmadas da própria fila, lida pela API de gestão durante o `handle()`.
- **Consumir até esvaziar:** `php artisan events:consume --stop-when-empty`, que encerra com `0` quando todas as filas de consumidores estão vazias, como o `queue:work --stop-when-empty`. Só os testes usam a opção.
- **Broker fora:** a configuração da conexão apontando para a porta `1` do host `rabbitmq`, onde nada escuta.
- **Sinal de reinício:** a chave de cache `illuminate:queue:restart`, que o `php artisan queue:restart` grava.

Arquivos de teste novos: `tests/Feature/Messaging/BrokerIsolationTest.php` (C6), `tests/Feature/Messaging/EventTransportFlagTest.php` (S2), `tests/Feature/Messaging/RabbitMqEventTransportTest.php` (S3), `tests/Feature/Messaging/ConsumeEventsCommandTest.php` (S4), `tests/Feature/Messaging/DeadDeliveriesCommandsTest.php` (S5) e `tests/Feature/HorizonTest.php` (S6). Os helpers do broker (conexão, vhost limpo, API de gestão) ficam em `tests/Pest.php`, como o `relayOnce()` da HEL-9.

Testes existentes que mudam, sem afrouxar nenhuma asserção:

- `EventDeliveryTest`: cada `subscribe(SampleEvent::class, <Consumidor>::class)` ganha o nome do consumidor como terceiro argumento, porque a assinatura muda (door 3). Nenhuma outra linha muda (C41);
- `load-tests/tests/stack.sh`, caso `connection`: a lista de serviços passa a ter os 9 do AC 60, e o caso passa a conferir `RABBITMQ_HOST` igual a `rabbitmq` na `api` de carga. Caso `cache-env`: o laço passa a incluir o `event-consumer`. O plano aprovou a mudança (Impact, linha load stack);
- `load-tests/tests/payable.test.js`: o caso `polls-every-2s` passa a se chamar `polls-every-250ms` e confere leituras a cada 0,25 s; o `gives-up-after-60s` passa a contar os passos de 0,25 s até 60 s, com o mesmo limite. O plano aprovou a mudança (door 8).

## Checks

### S1 - make test com RabbitMQ · 11 arquivos · 38 KB · ~10k

**C1** - `docker compose config` mostra o serviço `rabbitmq` com a imagem `rabbitmq:4.3.5-management-alpine`, um `healthcheck`, um volume nomeado montado em `/var/lib/rabbitmq` e a porta 15672 do container publicada em `${RABBITMQ_MANAGEMENT_PORT:-15672}`. O `backend/.env.example` define `RABBITMQ_HOST=rabbitmq`, `RABBITMQ_PORT=5672`, `RABBITMQ_USER`, `RABBITMQ_PASSWORD` e `RABBITMQ_VHOST=/`, e o `.env.example` da raiz define `RABBITMQ_MANAGEMENT_PORT`, `RABBITMQ_USER` e `RABBITMQ_PASSWORD` (AC 1, door 2)
Proof: `sh docker/tests/environment.sh --only dev-rabbitmq`

**C2** - A imagem do `api`, construída de `docker/php/Dockerfile`, lista `sockets` em `php -m` (AC 2, door 2)
Proof: `docker compose run --rm --no-deps --entrypoint php api -m | grep -qx -e sockets`

**C3** - Na pasta principal, com o vhost `ecommerce_testing` apagado antes, `make test-filter FILTER="connects to the checkout vhost"` passa e o vhost `ecommerce_testing` existe depois (`rabbitmqctl list_vhosts`). O teste afirma que `env('RABBITMQ_VHOST')` é `ecommerce_testing` e que uma conexão a ele abre (AC 3, door 7)
Proof: `sh docker/tests/environment.sh --only test-vhost`

**C4** - Num worktree temporário criado pelo script (`git worktree add`), `make test-filter FILTER="connects to the checkout vhost"` passa com `RABBITMQ_VHOST` igual ao `TEST_DB_DATABASE` daquele checkout (`ecommerce_testing_<worktree>`), e o vhost com esse nome existe depois. O script remove o worktree e roda o `make test-db-drop` dele no fim, mesmo quando falha (AC 4, door 7)
Proof: `sh docker/tests/environment.sh --only worktree-vhost`

**C5** - Depois de `make test-db-drop` na pasta principal, nem o banco `ecommerce_testing` nem o vhost `ecommerce_testing` existem (AC 5, door 7)
Proof: `sh docker/tests/environment.sh --only test-db-drop`

**C6** - O teste "starts every broker test on an empty vhost" roda 2 vezes (`->repeat(2)`). Nas duas, ele afirma pela API de gestão que o vhost está limpo e depois declara uma exchange `testing.leftover` e uma fila `testing.leftover`, que ficam para trás de propósito. A segunda execução só passa se a limpeza do começo do teste as apagou (AC 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="starts every broker test on an empty vhost"`

**C7** - Com `RABBITMQ_PORT=1` no ambiente do container, `pest --filter="declares the topology before the first message"` sai com código diferente de zero, a saída contém `RabbitMQ`, e o resumo do Pest não tem nenhum teste pulado (`skipped`) (AC 7)
Proof: `sh docker/tests/environment.sh --only broker-down`

**C8** - No job `backend` de `.github/workflows/ci.yml`, um passo roda `docker compose up -d --wait db redis rabbitmq` antes do passo que roda `make test` (AC 8)
Proof: `sh docker/tests/environment.sh --only ci`

### S2 - event-transport · 7 arquivos · 28 KB · ~7k

**C9** - Num banco sem nenhuma linha `event-transport` em `features`, `Feature::globally()->value('event-transport')` devolve `redis` (AC 9, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves the event transport to redis when nothing is stored"`

**C10** - `php artisan events:transport` sem valor sai com `0` e imprime `redis`. O alvo do Makefile passa o usuário do host: `make -n event-transport` contém `events:transport --by=` (AC 10, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows the current event transport"`
Proof: `make -n event-transport | grep -q -e 'events:transport --by='`

**C11** - Com o valor `redis`, `php artisan events:transport rabbitmq --by=ana` sai com `0`, a saída contém `redis` e `rabbitmq`, `features` tem exatamente uma linha `event-transport`, de escopo global, com o valor `"rabbitmq"`, e o valor lido depois de `Feature::flushCache()` é `rabbitmq`. O alvo repassa o valor: `make -n event-transport TO=rabbitmq` contém `events:transport rabbitmq --by=` (AC 11, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="switches the event transport to rabbitmq"`
Proof: `make -n event-transport TO=rabbitmq | grep -q -e 'events:transport rabbitmq --by='`

**C12** - `events:transport` com `bogus`, `Redis` e `kafka` sai com `1`, a saída contém `redis` e `rabbitmq`, e o valor continua `redis`. O teste é table-driven sobre os 3 valores (AC 12)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an unknown event transport"`

**C13** - Com o valor `rabbitmq` gravado e o relógio adiantado 1 minuto, `events:transport rabbitmq` sai com `0`, a saída contém `já`, o `updated_at` da linha não muda, e nenhuma linha de log `info` é escrita (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="changes nothing when the event transport already has the given value"`

**C14** - `events:transport rabbitmq --by=ana` escreve exatamente uma linha `info` com o contexto `previous` `redis`, `current` `rabbitmq` e `changed_by` `ana`. O alvo passa o `USER` do host: `make -n event-transport TO=rabbitmq USER=ana` contém `--by=ana` (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs who changed the event transport"`
Proof: `make -n event-transport TO=rabbitmq USER=ana | grep -q -e '--by=ana'`

**C15** - Com o cache padrão em `redis` e a conexão Redis apontando para a porta `1`, `events:transport rabbitmq` sai com `0` e grava `rabbitmq`, e `events:transport` sem valor imprime `rabbitmq` (AC 15, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="changes the event transport with redis unreachable"`

**C16** - Com transportes espiões ligados no lugar do `QueueEventTransport` e do `RabbitMqEventTransport`, `app(EventTransport::class)->publish()` entrega o envelope só ao espião do valor da flag: `redis` ao primeiro, `rabbitmq` ao segundo. O teste é table-driven sobre os 2 valores (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="hands the envelope to the transport of the flag"`

**C17** - Numa mesma aplicação, com os dois transportes espiões: grava um evento, roda `outbox:relay --once` com a flag em `redis`, muda o valor direto na tabela `features` para `"rabbitmq"` (sem passar pelo Pennant), grava outro evento e roda `outbox:relay --once` de novo. O primeiro envelope chega só ao espião do Redis, e o segundo só ao do RabbitMQ (AC 17, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="publishes through the new transport on the next relay cycle"`

**C18** - Cinco ciclos do relay, com a flag em `redis`, `redis`, `rabbitmq`, `rabbitmq` e `redis` e uma mensagem em cada, escrevem exatamente 2 linhas `info` de troca, nesta ordem: uma com `rabbitmq` e outra com `redis`. O primeiro ciclo não escreve nenhuma (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs each transport change once"`

### S3 - RabbitMqEventTransport · 9 arquivos · 43 KB · ~11k

**C19** - O `backend/composer.json` exige `laravel/pennant` `^1.26`, `laravel/horizon` `^5.50` e `php-amqplib/php-amqplib` `^3.7`, e o `composer.lock` traz os três (door 2)
Proof: `sh docker/tests/environment.sh --only dependencies`

**C20** - As inscrições do boot ligam cada consumidor ao seu nome e ao seu evento: `ordering.mark_order_as_awaiting_payment` ao `MarkOrderAsAwaitingPayment` e a `ordering.order_placed`; `ordering.mark_order_as_paid` ao `MarkOrderAsPaid` e a `payment.payment_approved`; `ordering.record_estimated_delivery` ao `RecordEstimatedDelivery` e a `fulfillment.delivery_scheduled`; `ordering.mark_order_as_delivered` ao `MarkOrderAsDelivered` e a `fulfillment.order_delivered`; `fulfillment.schedule_order_delivery` ao `ScheduleOrderDelivery` e a `ordering.order_paid`. O teste é table-driven sobre os 5, e as inscrições não têm nenhum outro nome (AC 19, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="subscribes the five consumers under their stable names"`

**C21** - `subscribe()` lança `LogicException` para os nomes `Ordering.mark_paid`, `ordering`, `ordering.mark-paid` e `ordering.mark paid` (table-driven sobre os 4), para um nome já dado a outra classe e para uma classe que já tem outro nome. Nos três casos, a inscrição anterior continua igual (AC 20, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a consumer name out of the format"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses one consumer name for two consumers"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses two names for one consumer"`

**C22** - No vhost limpo, com o espião e o segundo espião inscritos no evento de teste e o consumidor que falha inscrito em outro evento de teste, grava um evento de teste e roda `outbox:relay --once` com a flag em `rabbitmq`. A API de gestão mostra: a exchange `domain_events` `topic` e durável, e a `domain_events.dead` `direct` e durável; para cada um dos 3 consumidores, a fila `<nome>` do tipo `quorum` com exatamente os 6 argumentos da door 4 (table-driven sobre os 6), ligada à `domain_events` pelo nome do evento que ele assina; e a fila `<nome>.dead` do tipo `quorum`, sem `x-message-ttl` nem `x-expires`, ligada à `domain_events.dead` por `<nome>`. As filas dos dois espiões têm 1 mensagem cada, o que só acontece se elas existiam antes da publicação (AC 21, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares the topology before the first message"`

**C23** - Com o relógio congelado em `2026-10-18 15:52:24.715000` UTC e `Context` com `request_id` `trace-rabbit-0001`, grava um evento de teste e roda o relay com a flag em `rabbitmq`. A mensagem lida com `basic_get` da fila `testing.spy_consumer` tem `delivery_mode` 2, `content_type` `application/json`, `message_id` igual ao `id` da linha, `type` e routing key iguais a `testing.sample_event`, e um corpo JSON com exatamente as chaves `id`, `name`, `version`, `payload`, `occurred_at` e `request_id`: `occurred_at` igual a `2026-10-18T15:52:24.715000Z`, `version` 1, `payload` igual ao `toPayload()` e `request_id` igual a `trace-rabbit-0001` (AC 22, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="publishes one persistent message with the envelope as json"`

**C24** - No cenário do C22, depois de 1 publicação, as filas `testing.spy_consumer` e `testing.second_spy_consumer` têm exatamente 1 mensagem cada, e a fila do consumidor inscrito em outro evento tem 0 (AC 23, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="copies the message once into each consumer queue"`

**C25** - `RabbitMqEventTransport::publish()` de um envelope `testing.unsubscribed_event` retorna sem exceção, e todas as filas do vhost continuam com 0 mensagens (AC 24)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="publishes an event with no consumer without error"`

**C26** - Com a flag em `rabbitmq` e o broker no ar, `outbox:relay --once` sai com `0`, e a linha em `outbox_messages` fica com `published_at` preenchido (AC 25)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="marks the message published once the broker confirms"`

**C27** - A mensagem fica pendente nos três casos de falha. Com o broker fora, `outbox:relay --once` sai com `1`, e a linha continua com `published_at` nulo. Com uma fila extra `testing.full` (`x-max-length` 0, `x-overflow` `reject-publish`) ligada à `domain_events` pelo nome do evento, o broker recusa a mensagem, o relay sai com `1` e a linha continua pendente. Na própria camada, com um canal de teste cuja espera pelas confirmações lança o timeout do php-amqplib, `publish()` lança exceção, e o canal registra a espera de 5 s (AC 26)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the message pending when the broker is unreachable"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the message pending when the broker refuses it"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throws when the broker does not confirm in five seconds"`

**C28** - `docker compose -f load-tests/docker-compose.yml config` mostra o serviço `rabbitmq` com a imagem `rabbitmq:4.3.5-management-alpine` e um `healthcheck`, e nenhum serviço de carga publica porta no host (AC 27, door 2)
Proof: `sh docker/tests/environment.sh --only load-rabbitmq`
Proof: `sh load-tests/tests/stack.sh --only no-host-ports`

### S4 - event-consumer · 9 arquivos · 42 KB · ~11k

**C29** - Nos dois Compose, o serviço `event-consumer` usa a mesma imagem e o mesmo volume do `api`, com `command: ["php", "artisan", "events:consume"]` e `restart: unless-stopped`. Na pilha de carga, ele tem as mesmas `APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, `APP_EVENTS_CACHE`, `LOG_CHANNEL` `stderr` e `LOG_LEVEL` `warning` do `api` (AC 28, door 5)
Proof: `sh docker/tests/environment.sh --only event-consumer-services`
Proof: `sh load-tests/tests/stack.sh --only cache-env`

**C30** - Com 1 mensagem na fila `testing.spy_consumer`, consumir até esvaziar sai com `0`. O espião recebe exatamente 1 evento, igual ao do `fromPayload()` do evento de teste, e durante o `handle()` a fila mostra 1 mensagem não confirmada. Depois, a fila tem 0 mensagens prontas e 0 não confirmadas (AC 29, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="delivers a message to its consumer once and acknowledges it after the handle"`

**C31** - Para um envelope com `request_id` `trace-consume-0001`, o espião vê `Context::get('request_id')` igual a `trace-consume-0001` durante o `handle()` (AC 30)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="restores the envelope request id in the consumer process"`

**C32** - Com `Context::add('request_id', 'stale-0001')` antes do comando e um envelope com `request_id` nulo, o espião vê `Context::get('request_id')` nulo durante o `handle()` (AC 31)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="clears a stale request id in the consumer process"`

**C33** - Com o consumidor que sempre lança e 1 mensagem na fila dele, consumir até esvaziar sai com `0`, o `handle()` é chamado exatamente 3 vezes, a fila `testing.failing_consumer.dead` tem 1 mensagem com o mesmo `message_id`, e todas as outras filas do vhost têm 0 mensagens (AC 32, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="moves a message to the dead queue after three failed deliveries"`

**C34** - No cenário do C33, o log recebe exatamente 3 linhas `warning`, com `message_id` igual ao da mensagem, `name` `testing.sample_event`, `consumer` `testing.failing_consumer`, `attempt` 1, 2 e 3, e `error` `consumer down` (AC 33)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs every failed delivery with its attempt"`

**C35** - Uma mensagem publicada direto na `domain_events`, com a routing key do evento de teste, nunca chega ao espião, e consumir até esvaziar a deixa em `testing.spy_consumer.dead` quando o corpo é: um envelope com `version` 2; o texto `not json`; um JSON sem a chave `payload`; ou `[]`. O teste é table-driven sobre os 4 (AC 34)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="never calls the consumer for an envelope it cannot read"`

**C36** - Pegar a mensagem da fila `testing.spy_consumer` com `basic_get` sem confirmar e fechar a conexão, 2 vezes, a deixa na fila do consumidor. Na terceira vez, ela vai para `testing.spy_consumer.dead` (AC 35, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts a closed connection as a failed delivery"`

**C37** - Com 3 mensagens na fila do espião, o espião registra 1 mensagem não confirmada durante cada um dos 3 `handle()`, e a API de gestão mostra o consumidor do processo com `prefetch_count` 1 (AC 36, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="holds one unacknowledged delivery at a time"`

**C38** - Com `Sleep::fake()` e um conector de teste que falha nas 2 primeiras tentativas e conecta na terceira, consumir até esvaziar entrega a mensagem da fila e sai com `0`. `Sleep` registra exatamente 2 esperas de 5 s, e o processo não sai antes de conectar (AC 37, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="retries the broker every five seconds until it connects"`

**C39** - Com 2 mensagens na fila do espião e um espião que muda o sinal de reinício durante o primeiro `handle()`, `events:consume` (sem `--stop-when-empty`) sai com `0`. O espião é chamado 1 vez, e a fila fica com 1 mensagem pronta e 0 não confirmadas (AC 38)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="stops after the delivery in progress when the restart signal changes"`

**C40** - Com a flag em `redis` e 1 mensagem já na fila do espião, consumir até esvaziar entrega a mensagem ao espião (AC 39)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="consumes the queued messages while the flag is redis"`

**C41** - Os 11 testes do `EventDeliveryTest` passam, e desde a base `348d335` as únicas linhas que mudaram no arquivo são chamadas de `subscribe(` (AC 40)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="EventDeliveryTest"`
Proof: `! (git diff 348d335 -- backend/tests/Feature/Outbox/EventDeliveryTest.php | grep -E -e '^[-+][^-+]' | grep -v -e 'subscribe(')`

### S5 - events-retry · 4 arquivos · 29 KB · ~7k

**C42** - Com todas as filas de mortas vazias, `php artisan events:failed` sai com `0` e imprime `Nenhuma entrega morta.`. O alvo chama o comando: `make -n events-failed` contém `events:failed` (AC 41)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reports no dead delivery"`
Proof: `make -n events-failed | grep -q -e 'events:failed'`

**C43** - Com 2 entregas mortas, em `testing.failing_consumer.dead` e `testing.second_failing_consumer.dead`, `events:failed` sai com `0` e imprime 2 linhas, cada uma com o nome do consumidor, o nome do evento, o `message_id`, o `occurred_at`, o `request_id` e a contagem de entregas. Depois, as duas filas de mortas têm as mesmas mensagens, com os mesmos `message_id` (AC 42)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists every dead delivery without taking it from the queue"`

**C44** - Com o espião e o consumidor que falha inscritos no mesmo evento e uma entrega morta só do segundo, `events:retry` sai com `0` e imprime `testing.failing_consumer: 1`. A fila `testing.failing_consumer` passa a ter 1 mensagem, a `testing.spy_consumer` continua com 0, e todas as filas de mortas ficam vazias. O alvo chama o comando: `make -n events-retry` contém `events:retry` (AC 43, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns every dead delivery to its own consumer queue"`
Proof: `make -n events-retry | grep -q -e 'events:retry'`

**C45** - Com a mensagem A morta em duas filas de mortas e a mensagem B morta numa terceira, `events:retry <id de A>` devolve cada cópia de A à fila do próprio consumidor e deixa B na fila de mortas. O alvo repassa o id: `make -n events-retry ID=abc` contém `events:retry abc` (AC 44)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns only the dead deliveries of the given message"`
Proof: `make -n events-retry ID=abc | grep -q -e 'events:retry abc'`

**C46** - `events:retry` com um id que não está em nenhuma fila de mortas sai com `1`, a saída contém `não encontrada`, e as contagens de todas as filas continuam iguais (AC 45)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses to return a message that is not dead"`

**C47** - Com 3 entregas mortas e um publicador de teste que falha ao devolver a segunda, `events:retry` sai com `1`. Cada uma das 3 mensagens está na fila de mortas, na fila do consumidor, ou nas duas, e nenhuma some (AC 46)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps every dead delivery somewhere when the retry stops halfway"`

**C48** - Uma entrega devolvida pelo `events:retry` a um consumidor que continua lançando é chamada mais 3 vezes e volta para a fila de mortas dele (AC 47)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="sends a returned delivery back to the dead queue after three more failures"`

**C49** - Com o broker fora, `events:failed` e `events:retry` saem com `1`, e a saída contém `RabbitMQ`. O teste é table-driven sobre os 2 comandos (AC 48)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses the dead delivery commands without the broker"`

### S6 - Horizon · 11 arquivos · 34 KB · ~9k

**C50** - Nos dois Compose, o serviço `queue-worker` tem `command: ["php", "artisan", "horizon"]` e continua com o nome `queue-worker` (AC 49, door 6)
Proof: `sh docker/tests/environment.sh --only horizon-services`

**C51** - `config('horizon.environments')` tem, para `local` e para `production`, exatamente 1 supervisor, com `connection` `redis`, `queue` `['default']`, `balance` `false`, `minProcesses` 1, `maxProcesses` 1, `tries` 3, `timeout` 60 e `sleep` 1. O teste é table-driven sobre os 2 ambientes (AC 50, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="configures horizon like the queue worker"`

**C52** - Um membro da equipe com o papel `admin`, autenticado no guard `staff`, recebe `200` em `GET /horizon` (AC 51, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="opens horizon for a staff admin"`

**C53** - Com o ambiente da aplicação em `local`, um membro da equipe `support` autenticado no `staff`, um cliente autenticado no `customer` e um visitante recebem `403` em `GET /horizon` e em `GET /horizon/api/stats`. O teste é table-driven sobre os 3 solicitantes e as 2 rotas (AC 52, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses horizon to anyone but a staff admin"`

**C54** - Com a pilha de dev no ar, `curl` em `http://localhost:${APP_PORT:-8080}/horizon` sem sessão recebe `403` com o header `X-Request-ID`, que só a aplicação PHP envia (AC 53, door 6)
Proof: `sh docker/tests/environment.sh --only nginx-horizon`

**C55** - `make -n queue-restart` contém `horizon:terminate` e `queue:restart` (AC 54, door 6)
Proof: `make -n queue-restart | grep -q -e 'horizon:terminate'`
Proof: `make -n queue-restart | grep -q -e 'queue:restart'`

**C56** - O Compose de dev tem o serviço `scheduler` com a imagem e o volume do `api`, `command: ["php", "artisan", "schedule:work"]` e `restart: unless-stopped`. O agendamento tem um evento com o comando `horizon:snapshot` e a expressão `*/5 * * * *` (AC 55, door 6)
Proof: `sh docker/tests/environment.sh --only scheduler`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="schedules the horizon snapshot every five minutes"`

### S7 - load-test TRANSPORT · 8 arquivos · 46 KB · ~12k

**C57** - `make load-test TYPE=smoke` sem `TRANSPORT`, e depois com `TRANSPORT=redis`, passam. Nas duas, `php artisan events:transport` na `api` de carga imprime `redis`, e os relatórios se chamam `smoke-<carimbo>` (AC 56)
Proof: `sh load-tests/tests/make.sh --only transport-default`

**C58** - `make load-test TYPE=smoke TRANSPORT=rabbitmq` passa com zero erro inesperado. Durante a rodada, `events:transport` na `api` de carga imprime `rabbitmq`, os relatórios se chamam `smoke-rabbitmq-<carimbo>.html` e `.json`, e a exchange `domain_events` do RabbitMQ de carga registrou ao menos tantas publicações quanto há linhas em `outbox_messages` no banco de carga (AC 57, door 8)
Proof: `sh load-tests/tests/make.sh --only transport-rabbitmq`

**C59** - `make load-test TYPE=smoke TRANSPORT=kafka` sai com código diferente de zero, a saída contém `redis` e `rabbitmq`, e não existe nenhum container nem volume do projeto `ecommerce-load` depois (AC 58)
Proof: `sh load-tests/tests/make.sh --only invalid-transport`

**C60** - Com o relógio falso do teste, a espera até pagar lê o pedido a cada 0,25 s e desiste depois de 60 s, com 240 leituras (AC 59, door 8)
Proof: `sh load-tests/tests/k6.sh payable.test.js --only polls-every-250ms`
Proof: `sh load-tests/tests/k6.sh payable.test.js --only gives-up-after-60s`

**C61** - O Compose de carga define exatamente os serviços `api`, `db`, `event-consumer`, `k6`, `nginx`, `outbox-relay`, `queue-worker`, `rabbitmq` e `redis`, e a `api` de carga tem `RABBITMQ_HOST` igual a `rabbitmq` (AC 60)
Proof: `sh load-tests/tests/stack.sh --only connection`

**C62** - O `load-tests/README.md` tem, depois da tabela da HEL-14, uma segunda tabela com exatamente o cabeçalho da door 8, e nela uma linha `load` com `Transporte` `redis` e outra com `rabbitmq`, no mesmo commit, que é ancestral de `HEAD`. A nota de aceitação de cada linha cita a rodada anterior do mesmo transporte, como a regra dos 20% pede (AC 61, door 8)
Proof: `sh load-tests/tests/docs.sh --only transport-rows`

**C63** - A tabela da HEL-14 e os limites que saem dela continuam como estavam: o caso `baseline-rows` passa, o `thresholds-from-baseline` passa, e `load-tests/lib/baseline.js` não mudou desde a base (AC 62, door 8)
Proof: `sh load-tests/tests/docs.sh --only baseline-rows`
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only thresholds-from-baseline`
Proof: `git diff --quiet 348d335 -- load-tests/lib/baseline.js`

### Documentação e fechamento · 4 arquivos · 253 KB · ~63k

**C64** - O README cita `event-transport`, `make event-transport`, `events:consume`, `event-consumer`, `domain_events`, `.dead`, `make events-failed`, `make events-retry`, `/horizon` e `scheduler`, e a nota de deploy que muda formato cita o volume do `rabbitmq` (plano, Impact, linha docs)
Proof: `sh docker/tests/environment.sh --only docs-readme`

**C65** - O `AGENTS.md` cita o nome estável do consumidor (`ordering.mark_order_as_paid`), a flag `event-transport` e o `rabbitmq` na verificação antes de concluir. A primeira linha "Última atualização" da análise de domínio cita a HEL-13, e a análise tem um problema `✅ Resolvido` que cita a HEL-13. O `load-tests/README.md` cita `TRANSPORT=rabbitmq` e `250 ms` (plano, Impact, linha docs)
Proof: `sh docker/tests/environment.sh --only docs-agents`
Proof: `sh docker/tests/environment.sh --only docs-domain`
Proof: `sh docker/tests/environment.sh --only docs-load`

**C66** - A suíte inteira passa no banco e no vhost de testes, o estilo está correto, e o smoke de carga passa com a flag padrão (fechamento, `AGENTS.md`)
Proof: `make test`
Proof: `make pint`
Proof: `make load-test TYPE=smoke`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| `GET /horizon` e `/horizon/*` statuses (2) | `200` C52 · `403` C53 | - |
| solicitantes do `/horizon` (4) | `admin` C52 · `support` C53 · cliente C53 · visitante C53 | - |
| one-way doors do plano (8) | flag C9 · dependências C19 · nome do consumidor C20 · topologia e mensagem C22 · processo consumidor C33 · Horizon C51 · vhost por checkout C3 · medição no k6 C60 | - |
| valores de `event-transport` (2) | `redis` C9 · `rabbitmq` C11 | - |
| desfechos do `events:transport` (5) | mostra C10 · troca C11 · valor desconhecido C12 · mesmo valor C13 · Redis fora C15 | - |
| transporte escolhido pela flag (2) | `redis` -> `QueueEventTransport` C16 · `rabbitmq` -> `RabbitMqEventTransport` C16 | - |
| nomes estáveis dos consumidores (5) | C20, table-driven over all 5 | - |
| recusas da inscrição (3) | formato C21 · um nome para duas classes C21 · dois nomes para uma classe C21 | - |
| objetos da topologia (4) | `domain_events` C22 · `domain_events.dead` C22 · fila `<nome>` C22 · fila `<nome>.dead` C22 | - |
| argumentos da fila do consumidor (6) | C22, table-driven over all 6 | - |
| propriedades da mensagem (6) | `delivery_mode` C23 · `content_type` C23 · `message_id` C23 · `type` C23 · routing key C23 · as 6 chaves do corpo C23 | - |
| desfechos da publicação (5) | confirmada C26 · broker fora C27 · recusada pelo broker C27 · sem confirmação em 5 s C27 · sem consumidor C25 | - |
| caminhos da entrega no `events:consume` (12) | entregue e confirmada C30 · `request_id` presente C31 · `request_id` nulo C32 · consumidor lança 3 vezes C33 · log da falha C34 · versão desconhecida C35 · corpo ilegível C35 · conexão fechada C36 · uma por vez C37 · broker fora C38 · sinal de reinício C39 · flag em `redis` C40 | - |
| corpos ilegíveis (4) | C35, table-driven over all 4 | - |
| desfechos do `events:failed` e do `events:retry` (8) | sem mortas C42 · lista C43 · devolve todas C44 · devolve um id C45 · id desconhecido C46 · parada no meio C47 · falha de novo C48 · broker fora C49 | - |
| valores de `TRANSPORT` no `make load-test` (4) | ausente C57 · `redis` C57 · `rabbitmq` C58 · desconhecido C59 | - |
| serviços novos ou mudados no Compose de dev (4) | `rabbitmq` C1 · `event-consumer` C29 · `scheduler` C56 · `queue-worker` C50 | - |
| serviços do Compose de carga (9) | C61, table-driven over all 9 | - |
| startup config: conexão com o RabbitMQ (4 assemblies) | `backend/.env.example` de dev C1 · `environment` do Compose de carga C61 · `make test` e `phpunit.xml` C3 · CI C8 | - |
| startup config: vhost dos testes (2 assemblies) | pasta principal C3 · worktree C4 | - |
| startup config: leitura da flag a cada ciclo (2 assemblies) | `outbox:relay --once` C17 · laço contínuo do `outbox:relay` C17, pelo mesmo código de ciclo | - |
| ambientes do Horizon (2) | `local` C51 · `production` C51 | - |
| documentos (4) | README C64 · `AGENTS.md` C65 · análise de domínio C65 · `load-tests/README.md` C65 | - |

- Claims que citam rota, status ou formato de resposta: C52 e C53 (`/horizon`), C23 (a mensagem), C42 a C46 (a saída dos comandos). Cada um tem uma prova que cruza a borda: a requisição HTTP, a mensagem lida do broker, o comando pelo `Artisan`.
- O laço contínuo do `outbox:relay` e o `--once` chamam o mesmo código de ciclo, que limpa o cache da flag; por isso o C17 vale pelas duas montagens. Um ciclo que leia a flag fora desse código falha o C18, que roda 5 ciclos.
- Nenhum outro check afirma mais do que os casos que a sua prova exercita.

## Test policy

A tabela "Em que nível testar" do README responde às duas perguntas para o código que decide e é alcançado por um comando ou pela fila (o seletor do transporte, o `RabbitMqEventTransport` alcançado pelo relay, o `events:consume`, o `events:failed`, o `events:retry` e o `events:transport`), para o HTTP (o `/horizon`) e para a instrumentação. Ela não cobre três formas que esta feature traz, e as linhas abaixo valem para elas.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Configuração declarativa do ambiente (Compose, Dockerfile, CI, Nginx e os alvos do Makefile que só repassam argumentos) | uma sobre a configuração resolvida: `docker compose config`, `make -n`, `php -m` na imagem, ou uma requisição pela porta publicada | cada serviço, comando, imagem, porta e argumento que um critério nomeia |
| Alvo do Makefile que decide (o vhost por checkout, o `TRANSPORT` do `make load-test`) | uma na borda: o alvo rodado de verdade, num script de prova | cada valor aceito, cada recusa e cada checkout (pasta principal e worktree) |
| Comportamento do broker que a topologia configura (as 3 tentativas, a fila de mortas, a cópia por consumidor, a confirmação) | uma contra o RabbitMQ real, no vhost de testes; a própria camada só onde o broker não produz o caso (o timeout da confirmação, a parada no meio do retry, a reconexão) | cada caminho da mensagem: confirmada, recusada, entregue, devolvida, morta e devolvida pelo retry |

Evidence:

- `make test` e `make test-db-drop` decidem o vhost por checkout (2 pontos: pasta principal ou worktree, vhost existe ou não), e o `make load-test` decide pelo `TRANSPORT` (3 pontos: ausente, aceito, recusado). Análogo: os alvos do `make load-test` da HEL-14, provados na borda por `load-tests/tests/make.sh`.
- O comportamento das 3 tentativas não está no código PHP: é o `x-delivery-limit` da fila, contado pelo broker, mais a escolha do `basic_reject`. Só o broker real prova as duas juntas. Análogo: as garantias do banco, que o README já manda provar direto no banco.
- Os serviços, imagens e portas não decidem nada em tempo de execução. Análogo: o C12 da HEL-9, provado por `docker compose config`.

Cost: 9 provas de ambiente num script novo (`docker/tests/environment.sh`), 3 casos novos em `load-tests/tests/make.sh` e 1 em `docs.sh`, e 22 provas contra o broker real em 3 arquivos de teste. Sem estas linhas, as 3 tentativas e a fila de mortas seriam provadas só por um transporte simulado, que passa mesmo com o `basic_nack` que deixa a mensagem em loop.

## Swept

- validation: C12, porque a flag só aceita `redis` e `rabbitmq`; C21, porque o nome do consumidor segue `^[a-z]+\.[a-z_]+$` e pertence a uma só classe; C35, porque um envelope ilegível nunca chega ao consumidor; e C59, porque o `TRANSPORT` desconhecido é recusado antes de tocar na pilha
- failure modes: C27, porque o broker fora, a recusa e a falta de confirmação deixam a mensagem pendente; C33, porque a falha repetida termina na fila de mortas; e C47, porque um retry que para no meio não perde nenhuma entrega
- idempotency: existing - a mesma mensagem entregue duas vezes, inclusive pelos dois transportes (design, Key decision 2), cai nas guardas dos consumidores provadas pela HEL-9 ("keeps the first delivery estimate" e "ignores out of order or duplicated executions"). C44, porque o retry devolve a entrega só à fila do próprio consumidor e não duplica a dos outros
- authorization: C52 e C53, porque só a equipe `admin` abre o `/horizon`, em qualquer ambiente. O RabbitMQ e a API de gestão exigem as credenciais do `.env` (C1), e a pilha de carga não publica porta (C28)
- concurrency: C37, porque o `events:consume` segura uma entrega por vez; C17, porque uma troca da flag entre dois ciclos manda cada mensagem por um transporte só. Dois relays ao mesmo tempo continuam sem pegar a mesma mensagem: existing - o `skip locked` provado pelo C22 e pelo C52 da HEL-9
- data lifecycle: C22, porque a fila de mortas não tem `x-message-ttl` nem `x-expires`, e a entrega morta fica até o retry ou o descarte à mão. A retenção de 7 dias das mensagens publicadas continua a do relay: existing - o C25 da HEL-9
- dependency failure: C27 e C38, porque o broker fora deixa a mensagem pendente e o consumidor esperando; C15, porque a flag vira com o Redis fora; e C49, porque os comandos das filas de mortas recusam sem o broker
- state transitions: C11 e C17, porque a flag passa de `redis` a `rabbitmq` e o relay segue no ciclo seguinte; C30, C33 e C48, porque a mensagem vai de pronta a não confirmada e daí a confirmada, morta ou devolvida. Os status do pedido não mudam: existing - a transição condicional de `orders.status`
- observability: C14, porque a troca da flag registra quem, de quê e para quê; C18, porque o relay registra a troca de transporte uma vez; e C34, porque cada falha de entrega registra o `message_id`, o consumidor e a tentativa. Trace e métricas do `event-consumer` ficam para a HEL-8 (plano, Out of scope)

## Handoff

- Tamanho por `wc -c` dos arquivos que cada slice toca, dividido por 4, com os arquivos novos estimados:
  - S1 = 10k: 38 KB em 11 arquivos. Existentes: `docker-compose.yml`, `docker/php/Dockerfile`, `Makefile` (10 KB), `ci.yml`, `phpunit.xml`, os dois `.env.example` e `tests/Pest.php` (8 KB), 28 KB ao todo. Novos: cerca de 10 KB, com o `docker/tests/environment.sh`, a configuração da conexão e o `BrokerIsolationTest`;
  - S2 = 7k: 28 KB em 7 arquivos. Existentes: `AppServiceProvider`, `RelayOutboxCommand` e `Makefile`, 16 KB. Novos: cerca de 12 KB, com o seletor, o `events:transport` e o `EventTransportFlagTest`;
  - S3 = 11k: 43 KB em 9 arquivos. Existentes: `EventSubscriptions`, os 2 service providers, o `EventDeliveryTest` (10 KB), o Compose de carga e o `composer.json`, 21 KB. Novos: cerca de 22 KB, com o `RabbitMqEventTransport` e o teste dele;
  - S4 = 11k: 42 KB em 9 arquivos. Existentes: `DeliverEvent`, os 2 Compose e `tests/Pest.php`, 15 KB. Novos: cerca de 27 KB, com as regras de entrega, o `events:consume`, o conector e o teste;
  - S5 = 7k: 29 KB em 4 arquivos. Existente: `Makefile`, 10 KB. Novos: cerca de 19 KB, com os 2 comandos e o teste;
  - S6 = 9k: 34 KB em 11 arquivos. Existentes: os 2 Compose, o Nginx, o `Makefile`, `routes/console.php` e `bootstrap/providers.php`, 18 KB. Novos: cerca de 16 KB, com o `config/horizon.php`, o `HorizonServiceProvider`, o `HorizonTest` e os casos do script;
  - S7 = 12k: 46 KB em 8 arquivos, todos existentes: `Makefile`, Compose de carga, `payable.js`, `payable.test.js`, `stack.sh`, `make.sh`, `docs.sh` e `load-tests/README.md` (12 KB);
  - documentação = 63k: README (135 KB), análise de domínio (102 KB) e `AGENTS.md` (16 KB).
- Total: 130k, abaixo do budget de 150k. Um builder só, sem handoff. A documentação é quase metade do total, e o README e a análise de domínio são lidos por seção, não inteiros.
- Mechanism: um builder só, sem handoff (o total cabe no budget).
- O `composer.lock` não entra na conta: o `composer require` o escreve, e ninguém o lê.
- As rodadas do `load` para a C62 levam tempo de relógio, não de contexto: duas rodadas por transporte, em segundo plano, com a máquina sem outros aplicativos pesados, como a HEL-14 pede.

- **Boundary:** C1 a C66 fechados no branch `helterdias/hel-13-event-transport-flag`; um builder só, sem handoff.
- **Settled mid-build:**
  - C23: a coluna `occurred_at` do outbox guarda segundos (decisão da HEL-9), então pelo relay o corpo sai com `.000000`, e não com `.715000`. O teste prova o relay com `2026-10-18T15:52:24.000000Z` e prova o formato com microssegundos e a conversão para UTC publicando um envelope montado no teste, direto pelo `RabbitMqEventTransport`. Nenhuma asserção foi afrouxada além disso; o desvio está na análise de domínio (problema 16).
  - C18: o seletor registra a troca de transporte (a linha `info` com a chave `transport`); o primeiro ciclo não registra nada.
  - C7: o `beforeEach` da pasta `tests/Feature/Messaging` abre uma conexão AMQP antes de esvaziar o vhost, para que todo teste do broker falhe cedo dizendo `RabbitMQ ...` quando ele está fora.
  - C37: o consumidor pede prefetch 1 por consumidor e no canal todo, para valer "uma entrega por vez" também entre filas.
  - C43: a "contagem de entregas" das mortas é fixa em 3 (limite de entregas mais a primeira): o `x-delivery-count` da fila de mortas cresce a cada leitura.
  - As contagens de não confirmadas só vêm da API de gestão, que segue o broker com até ~5 s de atraso nas filas quorum; os testes que dependem delas esperam por polling. As contagens de prontas vêm do próprio broker (declaração passiva).
- **Abandoned:** `basic_nack` com requeue (não conta para o limite de entregas no 4.3), a extensão `amqp` do PECL, e a coluna nova na tabela da HEL-14 (door 8).
