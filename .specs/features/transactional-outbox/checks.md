# Outbox transacional: eventos gravados na transação e consumidos pela camada - checks

Profile: standard
Plan: `.specs/features/transactional-outbox/plan.md`

50 checks em 4 slices, a documentação e o fechamento · 7 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. O Pint roda via `docker compose exec -T api ./vendor/bin/pint --test`, como no CI. As provas do Compose e do Makefile rodam na raiz do repositório, no host. O frontend não muda. Os nomes de teste novos não usam parênteses, colchetes nem barras, porque o `--filter` do Pest é uma expressão regular.

Termos usados nos checks:

- **Mensagem:** uma linha de `outbox_messages`. **Pendente:** com `published_at` nulo.
- **v7 canônico:** 36 caracteres, dígitos hexadecimais minúsculos e hífens no formato 8-4-4-4-12, com `7` como primeiro dígito do terceiro grupo (`Shared\Support\RecordId`).
- **Evento de teste:** uma classe de teste que implementa `DomainEvent`, com nome `testing.sample_event`, versão 1 e um payload que o teste controla. **Consumidor espião:** um consumidor de teste que guarda cada evento recebido e o `Context::get('request_id')` do momento da chamada.
- **Transporte espião:** um `EventTransport` de teste, ligado no container, que guarda os envelopes recebidos em ordem e pode lançar `RuntimeException('transport down')` numa mensagem escolhida.
- **Outbox que falha:** um `EventOutbox` de teste que grava pela implementação real e depois lança `RuntimeException('outbox down')` para o nome escolhido. Assim a mensagem chega a ser escrita, e a prova mostra que o rollback a desfaz junto com a mudança de estado.
- **Sinal de reinício:** a chave de cache `illuminate:queue:restart`, que o `php artisan queue:restart` grava.

Arquivos de teste novos: `tests/Feature/Outbox/EventOutboxTest.php` (S1), `tests/Feature/Outbox/EventDeliveryTest.php` (S2), `tests/Feature/Outbox/OutboxRelayTest.php` (S3), `tests/Feature/Outbox/OrderEventsOutboxTest.php` (C38, C40, C43, C45 e C46) e `tests/Unit/Architecture/DomainEventsTest.php` (C31). O C29 e o C30 entram no `tests/Unit/Architecture/OrderEventPayloadTest.php`. Os testes que precisam do banco ficam em `tests/Feature`, onde o `RefreshDatabase` está ligado.

Os checks marcados com "exercício temporário" provam uma propriedade de um teste de arquitetura com uma mudança de propósito no código. A mudança é revertida logo depois e nunca é commitada, como o C7 da feature `module-facades`. Durante o exercício, `git diff --quiet -- backend/tests` precisa sair com 0, ou seja, o teste não é editado.

Testes existentes que mudam, sem afrouxar nenhuma asserção:

- os `Event::fake([OrderPlaced::class])` e `Event::fake([PaymentApproved::class])` que só impediam os listeners de rodar na fila `sync` saem do `CheckoutTest`, do `CheckoutShippingTest` (7), do `MoneyInCentsTest` (2), do `PaymentTest`, do `Account/CustomerRecordRoutesTest`, do `UseCases/Order/PlaceOrderUseCaseTest` e do `UseCases/Payment/PayOrderUseCaseTest`. Sem o relay rodando, nenhum consumidor roda nesses testes;
- cada `Event::assertDispatched`, `assertDispatchedTimes` e `assertNotDispatched` dos eventos do pedido passa a conferir as linhas de `outbox_messages` pelo `name`, com a mesma contagem e o mesmo `order_id`. Os testes estão nomeados nos checks C33 a C44;
- `PayOrderUseCaseTest` "dispatches PaymentApproved for an order awaiting payment" passa a se chamar "records PaymentApproved for an order awaiting payment", e "does not dispatch PaymentApproved for orders that are not awaiting payment" passa a se chamar "records no PaymentApproved for orders that are not awaiting payment";
- `CheckoutTest` "keeps the query count of the checkout" passa de 9 para 10 queries, e a décima é `insert into "outbox_messages"`. `Account/CustomerRecordRoutesTest` "keeps the query count of the owner reads and updates" passa de 3 para 4 em `pay order`, e a quarta é `insert into "outbox_messages"`. O plano aprovou essa gravação nos AC 33 e 35;
- `OrderStatusFlowTest` "registers the listeners for the order events" dá lugar ao C32 (as inscrições) e ao C14 (o `DeliverEvent` com `$tries` 3), porque os consumidores deixam de ser listeners `ShouldQueue` (plano, Impact). "processes OrderPlaced through the queue" passa a se chamar "delivers OrderPlaced through the queue": grava a mensagem, roda o relay com `--once` sob `Queue::fake()` e confere um `DeliverEvent` para o `MarkOrderAsAwaitingPayment`, com o pedido ainda em `placed`;
- `OrderStatusFlowTest` e os demais passam a usar os consumidores pelo namespace `Consumers` (`App\Modules\Ordering\Consumers\MarkOrderAsPaid` e assim por diante);
- `OrderEventPayloadTest` "still announces OrderPaid only after the commit" dá lugar ao C31 (nenhum evento usa `ShouldDispatchAfterCommit`) e ao C40 (a transição e o `OrderPaid` entram juntos). O plano aprovou a troca (Impact, linha domain);
- `ModuleBoundariesTest` "generates the private directory rules from the folders that exist" passa a esperar `Consumers` no lugar de `Listeners` entre as pastas privadas, porque a pasta muda de nome (plano, Assumptions). Nenhuma regra de fronteira é afrouxada.

## Checks

### S1 - outbox_messages · 12 arquivos · 25 KB · ~6k

**C1** - O catálogo do PostgreSQL mostra `outbox_messages` com exatamente 7 colunas na forma da door 1 (`information_schema.columns`): `id` `uuid` não nulo; `name` `character varying` de 100 não nulo; `version` `smallint` não nulo; `payload` `jsonb` não nulo; `request_id` `character varying` de 128 nulo; `occurred_at` `timestamp without time zone` não nulo; `published_at` `timestamp without time zone` nulo. Em `pg_indexes`, `outbox_messages_pending_index` está sobre (`occurred_at`, `id`) com `WHERE (published_at IS NULL)`, e outro índice cobre só `published_at`. Em `pg_constraint`, a tabela não tem nenhuma chave estrangeira (`contype = 'f'`). O teste é table-driven sobre as 7 colunas (AC 1, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates the outbox table in the shape of door 1"`

**C2** - Com o relógio congelado, gravar `OrderPlaced` pelo `EventOutbox` dentro de `DB::transaction` cria exatamente 1 mensagem: `name` `ordering.order_placed`, `version` 1, `payload` igual a `{"order_id": "<id do pedido>"}`, `id` v7 canônico, `occurred_at` igual ao instante congelado e `published_at` nulo (AC 2, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records an event as one pending message inside a transaction"`

**C3** - Uma `DB::transaction` que grava um evento e depois lança exceção deixa `outbox_messages` vazia (AC 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="leaves no message when the recording transaction rolls back"`

**C4** - Dentro do teste, onde só a transação do `RefreshDatabase` está aberta, gravar um evento lança `LogicException`, e a tabela continua vazia. O mesmo evento, gravado dentro de `DB::transaction` no mesmo teste, entra. Isso mostra que a recusa vem da falta de transação, não do evento (AC 4, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses to record an event outside a transaction"`

**C5** - Com o evento de teste: os valores `"text"`, `7`, `1.5`, `true` e `null` são gravados e voltam iguais do `jsonb`. Um array associativo, uma lista e um `CarbonImmutable` lançam `InvalidArgumentException`, e nenhuma mensagem é gravada. Os dois testes são table-driven: 5 valores aceitos e 3 recusados (AC 5, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="accepts every scalar and null in the payload"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a payload value that is not a scalar or null"`

**C6** - Com `Context::add('request_id', 'trace-outbox-0001')`, a mensagem gravada tem `request_id` `trace-outbox-0001`. Na borda, um `POST /api/orders` com o header `X-Request-ID: trace-checkout-0001` grava a mensagem `ordering.order_placed` com esse `request_id` (AC 6, door 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records the context request id on the message"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records the request id of the checkout request"`

**C7** - Depois de `Context::flush()`, a mensagem gravada tem `request_id` nulo (AC 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records a null request id without a context request id"`

### S2 - EventConsumer · 9 arquivos · 19 KB · ~5k

**C8** - Com dois consumidores espiões inscritos no evento de teste e `Queue::fake()`, publicar um envelope pelo transporte de fila empurra exatamente 2 `DeliverEvent`, um para cada consumidor. Cada job carrega um envelope igual ao publicado (`id`, `name`, `version`, `payload`, `occurredAt` e `requestId`), e nenhum define `connection`, então os dois vão para a conexão padrão (AC 8, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="pushes one delivery per subscribed consumer with the whole envelope"`

**C9** - Publicar um envelope com um `name` sem nenhum consumidor inscrito retorna sem exceção, e `Queue::assertNothingPushed()` passa (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="publishes an envelope with no subscribed consumer without pushing a job"`

**C10** - Rodar um `DeliverEvent` chama o `handle()` do consumidor espião exatamente 1 vez, com uma instância da classe inscrita cujas propriedades são as do payload do envelope (AC 10, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rebuilds the event and calls the consumer once"`

**C11** - Um `DeliverEvent` com `requestId` `trace-delivery-0001`, despachado pela fila `sync` a partir de um `Context` sem `request_id`, faz o consumidor espião ler `trace-delivery-0001` (AC 11, door 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="restores the envelope request id while the consumer runs"`

**C12** - Um `DeliverEvent` com `requestId` nulo, despachado pela fila `sync` a partir de um `Context` com `request_id` `stale-request-0001`, faz o consumidor espião ler `null`. A fila `sync` hidrata o `Context` que estava no despacho, então quem limpa o valor é a própria entrega (AC 12, door 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="clears a stale request id when the envelope has none"`

**C13** - Um envelope com `version` 2 para uma classe que declara 1 faz o `DeliverEvent` lançar `UnexpectedValueException`, com `testing.sample_event` e `2` na mensagem, e o consumidor espião não é chamado (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an envelope version the event does not declare"`

**C14** - Com a conexão `database` como padrão e dois consumidores inscritos no evento de teste, um que sempre lança exceção e outro que conta as chamadas: publicar um envelope e rodar `queue:work database --stop-when-empty` resulta em 3 tentativas do consumidor que falha, 1 linha em `failed_jobs` com a classe dele no payload e exatamente 1 chamada do outro. `DeliverEvent::$tries` é 3 (AC 14, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="retries only the failing consumer three times and fails it alone"`

**C15** - `EventSubscriptions::subscribe` lança `LogicException` em 2 casos, table-driven: (a) uma classe que implementa `EventConsumer` com `handle()` tipado por outra classe de evento; (b) uma classe com o `handle()` certo que não implementa `EventConsumer`. Nos dois casos, nenhum consumidor fica inscrito no nome (AC 15, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a consumer that does not match the subscribed event"`

**C16** - Inscrever um consumidor numa segunda classe de evento cujo `eventName()` é `testing.sample_event`, já inscrito por outra classe, lança `LogicException`. A primeira inscrição continua sendo a única (AC 16, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses two event classes under the same name"`

**C17** - Com `Queue::fake()`: gravar uma mensagem, publicá-la, apagar a linha de `outbox_messages` e rodar o `DeliverEvent` empurrado chama o consumidor espião com o evento do envelope (AC 17, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="delivers an envelope whose message was already pruned"`

**C18** - Para cada classe em `app/Modules/*/Events`, com a lista gerada pelos arquivos (5 hoje, e o teste confere que são 5), `Event::hasListeners($class)` é `false` depois do boot da aplicação (AC 18, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="registers no laravel listener for the module events"`

### S3 - OutboxRelay · 5 arquivos · 22 KB · ~6k

**C19** - Com o relógio congelado e o transporte espião: 4 pendentes, duas delas no mesmo `occurred_at` com ids de ordem conhecida, e 1 já publicada. `php artisan outbox:relay --once` sai com 0, entrega as 4 em ordem crescente de `occurred_at` e, no empate, de `id`, não entrega a publicada e grava em `published_at` das 4 o instante congelado (AC 19, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="publishes every pending message in order with once"`

**C20** - Com 3 pendentes e o transporte espião lançando `RuntimeException('transport down')` na segunda: a primeira fica publicada, a segunda e a terceira ficam com `published_at` nulo, e `Log::warning` é chamado uma vez, com o `id` e o `name` da segunda e `transport down` (AC 20, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="stops at the first transport failure and keeps the rest pending"`

**C21** - No cenário do C20, `php artisan outbox:relay --once` sai com 1 (AC 21)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="exits with 1 when the transport fails with once"`

**C22** - No log de queries de um ciclo, a seleção das pendentes contém `where "published_at" is null`, `order by "occurred_at" asc, "id" asc`, `limit 100` e `for update skip locked` (AC 22, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="claims the pending messages with for update skip locked"`

**C23** - Com `Sleep::fake()`, nenhuma pendente e o sinal de reinício mudado durante a primeira espera, `php artisan outbox:relay` sem `--once` sai com 0, e `Sleep::assertSequence` confere exatamente uma espera de 500 ms (AC 23, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="waits 500 ms after a cycle with no pending message"`

**C24** - Com `Sleep::fake()`, uma pendente, o transporte espião lançando exceção e o sinal de reinício mudado durante a primeira espera, o relay sai com 0, e a única espera é de 5 s (AC 24, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="waits 5 seconds after a transport failure"`

**C25** - Com o relógio congelado, `php artisan outbox:relay --once` apaga a mensagem publicada há 7 dias e 1 minuto e mantém a publicada há 6 dias e 23 horas e a pendente com `occurred_at` de 30 dias atrás. Num segundo teste, sem `--once`, com `Sleep::fake()` avançando o relógio a cada espera e o sinal de reinício mudado depois de 61 minutos de relógio: o relay apaga ao iniciar, não apaga de novo nos ciclos dos primeiros 59 minutos e apaga outra vez no primeiro ciclo depois de 60 minutos. A contagem é das queries `delete from "outbox_messages"` (AC 25, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="prunes published messages older than 7 days and keeps pending ones"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="prunes again only after an hour"`

**C26** - Com uma pendente e um transporte espião que muda o sinal de reinício ao receber a mensagem, `php artisan outbox:relay` sem `--once` sai com 0 depois desse ciclo, com a mensagem publicada e uma única entrega. Num segundo caso do mesmo teste, `Artisan::call('queue:restart')` rodado durante a primeira espera também encerra o relay com 0 (AC 26, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="exits after the cycle when the queue restart signal changes"`

**C27** - No Compose resolvido, o serviço `outbox-relay` tem a mesma `image` e os mesmos `volumes` do `queue-worker`, `command` `["php", "artisan", "outbox:relay"]` e `restart` `unless-stopped` (AC 27, door 6)
Proof: `docker compose config --format json | python3 -c "import json, sys; s = json.load(sys.stdin)['services']; r, w = s['outbox-relay'], s['queue-worker']; sys.exit(0 if r['image'] == w['image'] and r['volumes'] == w['volumes'] and r['command'] == ['php', 'artisan', 'outbox:relay'] and r['restart'] == 'unless-stopped' else 1)"`

**C28** - `make logs` inclui o `outbox-relay`, e `make logs-relay` segue só os logs dele (AC 28)
Proof: `make -n logs-relay | grep -qx -e 'docker compose logs -f --tail=100 outbox-relay'`
Proof: `make -n logs | grep -q -e 'outbox-relay'`

### S4 - Order events · 32 arquivos · 129 KB · ~32k

**C29** - Para os 5 eventos, table-driven: `OrderPlaced::eventName()` é `ordering.order_placed`, `PaymentApproved` é `payment.payment_approved`, `OrderPaid` é `ordering.order_paid`, `DeliveryScheduled` é `fulfillment.delivery_scheduled` e `OrderDelivered` é `fulfillment.order_delivered`. Os 5 têm `eventVersion()` 1, e cada nome casa `^[a-z]+\.[a-z_]+$` (AC 29, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares the name and version of every order event"`

**C30** - Para os 5 eventos, table-driven: as chaves de `toPayload()` são exatamente `["order_id"]` (`OrderPlaced`, `PaymentApproved` e `OrderDelivered`), `["order_id", "delivery_business_days"]` com um `int` (`OrderPaid`) e `["order_id", "estimated_delivery_on"]` (`DeliveryScheduled`). Um `DeliveryScheduled` com `estimatedDeliveryOn` em 2026-10-09 00:00 de `America/Sao_Paulo`, que é 03:00 em UTC, gera `"2026-10-09"`. `fromPayload(toPayload())` devolve o mesmo `orderId`, os mesmos `deliveryBusinessDays` e o mesmo `Y-m-d` de `America/Sao_Paulo` (AC 30, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="maps every order event payload and rebuilds it"`

**C31** - Duas regras de arquitetura, com uma expectativa por namespace:
- (a) cada módulo com pasta `Events`, com a lista gerada pelas pastas, tem `expect('App\Modules\<Módulo>\Events')->toImplement(DomainEvent::class)`;
- (b) `App\Modules` não usa `Illuminate\Foundation\Events\Dispatchable`, `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`, `Illuminate\Support\Facades\Event` nem a função `event`, cada um numa expectativa própria.

Exercício temporário: cada violação abaixo, feita sozinha, faz a regra dela falhar. São elas: `use Dispatchable;` no `OrderPlaced`; `implements ShouldDispatchAfterCommit` no `OrderPaid`; uma chamada `Event::dispatch(...)` no `DeliverOrderUseCase`; uma chamada `event(...)` no `ScheduleDeliveryUseCase`; e a remoção de `DomainEvent` do `OrderDelivered` (AC 31, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="implements DomainEvent in every module event"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="uses no laravel event dispatching in the modules"`

**C32** - Depois do boot, as inscrições são exatamente 5 pares: `ordering.order_placed` → `MarkOrderAsAwaitingPayment`; `payment.payment_approved` → `MarkOrderAsPaid`; `fulfillment.delivery_scheduled` → `RecordEstimatedDelivery`; `fulfillment.order_delivered` → `MarkOrderAsDelivered`; `ordering.order_paid` → `ScheduleOrderDelivery`. O `OrderingServiceProvider` faz 4 inscrições, e o `FulfillmentServiceProvider`, 1 (AC 32, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="subscribes exactly the five order consumers"`
Proof: `test "$(grep -c 'subscribe(' backend/app/Modules/Ordering/OrderingServiceProvider.php)" -eq 4 && test "$(grep -c 'subscribe(' backend/app/Modules/Fulfillment/FulfillmentServiceProvider.php)" -eq 1`

**C33** - Um `POST /api/orders` com `201` deixa exatamente 1 mensagem `ordering.order_placed`, com `payload.order_id` igual a `data.id`, `version` 1 e `published_at` nulo (AC 33)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="announces the placed order by id"`

**C34** - O `409` de estoque insuficiente e o `500` da terceira colisão de código deixam 0 mensagens `ordering.order_placed`. No use case, de 0 a 2 colisões geram exatamente 1 mensagem, e 3 colisões geram 0 (AC 34)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns 409 and changes nothing when stock is insufficient"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="fails the whole order after three code collisions"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="draws the order code up to three times"`

**C35** - Um `POST /api/orders/{order}/payment` com `202` deixa exatamente 1 mensagem `payment.payment_approved`, com `payload.order_id` igual ao id do pedido. No use case, o pedido em `awaiting_payment` gera a mensagem, e os demais 3 status (`placed`, `payment_approved` e `delivered`) não geram nenhuma (AC 35)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records the approved payment and announces it once"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records PaymentApproved for an order awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records no PaymentApproved for orders that are not awaiting payment"`

**C36** - Um pagamento com `402` grava a tentativa com `status` `declined` e deixa 0 mensagens `payment.payment_approved`, nos casos de recusa do dataset atual (AC 36)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declines the payment and keeps the order awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the declined payment after raising the decline"`

**C37** - A aprovação que perde a corrida pelo índice único responde `409` na borda e lança o conflito no use case, sem deixar mensagem `payment.payment_approved` (AC 37)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="loses the race to a concurrent approval with 409"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="raises a conflict when a concurrent approval wins the race"`

**C38** - Com o outbox que falha para `payment.payment_approved`, um `POST /api/orders/{order}/payment` com o cartão aprovado responde `500` com o envelope `SERVER_ERROR` e deixa 0 pagamentos `approved` para o pedido e 0 mensagens (AC 38, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps neither the approval nor the message when the outbox fails"`

**C39** - O `MarkOrderAsPaid`, tratando um `PaymentApproved` de um pedido em `awaiting_payment` com `delivery_business_days` 4, deixa o pedido em `payment_approved` e exatamente 1 mensagem `ordering.order_paid` com `payload.delivery_business_days` 4, e nenhum `DeliverOrder` é empurrado (AC 39)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="marks the order as paid and announces it, without scheduling the delivery itself"`

**C40** - Com o outbox que falha para `ordering.order_paid`, o `MarkOrderAsPaid` lança a exceção, e o pedido continua em `awaiting_payment` com 0 mensagens `ordering.order_paid`. Com o outbox real de volta, a mesma chamada leva o pedido a `payment_approved` com exatamente 1 mensagem (AC 40, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the order awaiting payment when recording order paid fails"`

**C41** - Dois `PaymentApproved` seguidos para o mesmo pedido deixam exatamente 1 mensagem `ordering.order_paid`. Um `PaymentApproved` para um pedido em `placed`, fora de ordem, não muda o status e não grava mensagem (AC 41)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="announces OrderPaid only once for a duplicated payment approval"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ignores out of order or duplicated executions"`

**C42** - O `ScheduleOrderDelivery`, tratando um `OrderPaid` com 3 dias úteis, deixa 1 mensagem `fulfillment.delivery_scheduled` com o `estimated_delivery_on` que o `DeliveryCalendar` calcula em `Y-m-d`, e empurra 1 `DeliverOrder` para o mesmo pedido, com delay de `shop.delivery_delay_seconds` (AC 42)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="announces the delivery estimate when the order is paid"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="schedules the delivery from the business days in OrderPaid"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="schedules the delivery job with a delay when the order is paid"`

**C43** - Com o outbox que falha para `fulfillment.delivery_scheduled` e `Bus::fake([DeliverOrder::class])`, o `ScheduleOrderDelivery` lança a exceção, nenhum `DeliverOrder` é empurrado e não fica mensagem (AC 43)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="pushes no delivery job when recording the schedule fails"`

**C44** - Rodar `DeliverOrder` para um pedido em `payment_approved` deixa 1 mensagem `fulfillment.order_delivered` com o `order_id` do pedido, e o status continua `payment_approved` (AC 44)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="announces the delivery without changing the order status itself"`

**C45** - Janela 1, com a fila `sync`: depois de um `POST /api/orders` com `201`, um ciclo do relay com o transporte de fila trocado por um que lança exceção deixa o pedido em `placed` e a mensagem pendente. Com o transporte real de volta, `php artisan outbox:relay --once` sai com 0, e o pedido vai para `awaiting_payment` (AC 45)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="recovers a placed order after the transport comes back"`

**C46** - Janela 2, com a fila `sync`: depois de um pagamento com `202`, um ciclo do relay com o transporte falhando deixa o pedido em `awaiting_payment`, e um segundo `POST /api/orders/{order}/payment` responde `409`. Com o transporte real de volta, `outbox:relay --once` leva o pedido a `payment_approved` ou além (AC 46)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="recovers an approved payment after the transport comes back"`

**C47** - Ciclo completo com a fila `sync`. Primeiro, `POST /api/orders` e `outbox:relay --once`, com o pedido em `awaiting_payment`. Depois, o pagamento com o header `X-Request-ID: trace-payment-0001` e `outbox:relay --once` repetido até não haver pendente. O pedido termina em `delivered`, com `estimated_delivery_on` preenchido. As 4 mensagens gravadas a partir do pagamento (`payment.payment_approved`, `ordering.order_paid`, `fulfillment.delivery_scheduled` e `fulfillment.order_delivered`) têm `request_id` `trace-payment-0001` (AC 47, door 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="runs the whole lifecycle with a synchronous queue"`

**C48** - Os status atuais das duas rotas tocadas continuam provados (plano, Observable):
- `POST /api/orders`: `201` (C33), `401` e `422`, `401` para a sessão da equipe, `409` e `500` (C34) e `429`;
- `POST /api/orders/{order}/payment`: `202` (C35), `401` para a sessão da equipe, `402` (C36), `404`, `409`, `422` e `429`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="requires authentication and valid items"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a staff session on every store route"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles order placement after 20 requests per minute"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 to the payment of an unknown order"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects payment for orders that are not awaiting payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects an invalid card token without charging"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles payment attempts after 20 per minute"`

### Documentação e fechamento · 3 arquivos · 181 KB · ~45k

**C49** - O README, a análise de domínio e o `AGENTS.md` descrevem o estado novo e não citam mais o antigo (plano, Impact, linha docs):
- o README e o `AGENTS.md` não citam `ShouldDispatchAfterCommit`, `CallQueuedListener`, o event discovery nem a pasta `Listeners`;
- o README cita o comando `outbox:relay`, o serviço `outbox-relay` e o `make logs-relay`;
- a análise de domínio tem o problema `#### 14. ✅ Resolvido:`, e a linha "Última atualização" cita a HEL-9;
- a análise de domínio não diz mais que "o evento só é disparado depois do commit" nem que "o envelope e o outbox ficam para a HEL-9";
- o `AGENTS.md` cita o `EventOutbox` entre as regras.

Proof: `! grep -nE -e 'ShouldDispatchAfterCommit|CallQueuedListener|event discovery|Listeners' README.md AGENTS.md`
Proof: `grep -q -e 'outbox:relay' README.md && grep -q -e 'outbox-relay' README.md && grep -q -e 'make logs-relay' README.md`
Proof: `grep -qE -e '^#### 14\. ✅ Resolvido:' docs/domain-analysis.md`
Proof: `grep -m1 -e 'Última atualização' docs/domain-analysis.md | grep -q -e 'HEL-9'`
Proof: `! grep -q -e 'O evento só é disparado depois do commit' -e 'O envelope e o outbox ficam para a HEL-9' docs/domain-analysis.md`
Proof: `grep -q -e 'EventOutbox' AGENTS.md`

**C50** - A suíte inteira do backend e o estilo passam no branch terminado (`AGENTS.md`, verificação antes de concluir)
Proof: `make test`
Proof: `docker compose exec -T api ./vendor/bin/pint --test`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| colunas de `outbox_messages` (7) | C1, table-driven sobre as 7: `id` · `name` · `version` · `payload` · `request_id` · `occurred_at` · `published_at` | - |
| índices e restrições de `outbox_messages` (3) | `outbox_messages_pending_index` C1, C22 · índice de `published_at` C1 · nenhuma chave estrangeira C1 | - |
| doors do `Landing` (7) | 1 tabela C1 · 2 envelope e contrato C29, C30, C8 · 3 gravação só em transação C4, C31, C38, C40 · 4 porta do transporte C19, C45 · 5 inscrição, consumidor e entrega C8, C14, C15, C32 · 6 relay C19 a C27 · 7 `request_id` C6, C11, C12, C47 | - |
| entidades do `Relations` (1) | `outbox_messages` isolada, sem chave estrangeira C1 · pendente nunca apagada C25 | - |
| nomes e versões dos eventos (5) | `ordering.order_placed` C29 · `payment.payment_approved` C29 · `ordering.order_paid` C29 · `fulfillment.delivery_scheduled` C29 · `fulfillment.order_delivered` C29 | - |
| chaves do payload (7) | `order_id` em `OrderPlaced` C30 · `order_id` em `PaymentApproved` C30 · `order_id` em `OrderPaid` C30 · `delivery_business_days` C30 · `order_id` em `DeliveryScheduled` C30 · `estimated_delivery_on` C30 · `order_id` em `OrderDelivered` C30 | - |
| valores do payload (8) | aceitos: string C5 · int C5 · float C5 · bool C5 · null C5; recusados: array associativo C5 · lista C5 · objeto C5 | - |
| `request_id` na gravação e na entrega (5) | gravado do `Context` C6 · nulo sem `Context` C7 · restaurado na entrega C11 · limpo quando nulo C12 · levado pela cadeia C47 | - |
| inscrições (5) | `OrderPlaced` → `MarkOrderAsAwaitingPayment` C32 · `PaymentApproved` → `MarkOrderAsPaid` C32 · `DeliveryScheduled` → `RecordEstimatedDelivery` C32 · `OrderDelivered` → `MarkOrderAsDelivered` C32 · `OrderPaid` → `ScheduleOrderDelivery` C32 | - |
| recusas da inscrição (3) | `handle()` de outro evento C15 · sem `EventConsumer` C15 · nome repetido C16 | - |
| decisões da entrega (5) | N consumidores C8 · nenhum consumidor C9 · versão diferente C13 · falha isolada e 3 tentativas C14 · linha já apagada C17 | - |
| decisões do relay (13) | ordem C19 · marca depois do aceite C19 · já publicada fica de fora C19 · parada na falha C20 · `warning` C20 · saída 0 C19 · saída 1 C21 · `skip locked` C22 · 500 ms C23 · 5 s C24 · retenção nos dois lados do limite C25 · limpeza por hora C25 · sinal de reinício C26 | - |
| casos de uso que gravam (5) | `PlaceOrderUseCase` C33 · `PayOrderUseCase` C35 · `MarkOrderAsPaidUseCase` C39 · `ScheduleDeliveryUseCase` C42 · `DeliverOrderUseCase` C44 | - |
| gravação desfeita junto com a mudança (4) | checkout C34 · pagamento C37, C38 · marcação de pago C40 · agendamento da entrega C43 | - |
| janelas do Problem (3) | 1 `OrderPlaced` C45 · 2 `PaymentApproved` C46 · 3 `OrderPaid` C40 | - |
| regras de arquitetura (5) | `DomainEvent` em toda pasta `Events` C31 · sem `Dispatchable` C31 · sem `ShouldDispatchAfterCommit` C31 · sem a facade `Event` C31 · sem `event()` C31 | - |
| `POST /api/orders` statuses (6) | 201 C33 · 401 C48 · 409 C34 · 422 C48 · 429 C48 · 500 C34 | - |
| `POST /api/orders/{order}/payment` statuses (8) | 202 C35 · 401 C48 · 402 C36 · 404 C48 · 409 C37, C46, C48 · 422 C48 · 429 C48 · 500 C38 | - |
| ambiente (3) | serviço `outbox-relay` C27 · `make logs` C28 · `make logs-relay` C28 | - |
| startup config: inscrições e ligação do `EventTransport` e do `EventOutbox` (3 montagens) | `queue-worker` C14, pelo `queue:work` com o mesmo boot · `outbox-relay` C19, pelo comando com o mesmo boot · harness de teste C18, C32. As três sobem a mesma lista de `bootstrap/providers.php` e o mesmo `bootstrap/app.php`, uma montagem compartilhada | - |

- Os checks que citam um status, uma rota ou a forma de uma resposta (C6, C33 a C38, C45 a C48) têm uma prova que cruza a borda HTTP. Os do relay (C19 a C26) cruzam a borda do comando pelo `Artisan`, e os da entrega (C11, C12 e C14) passam pela fila.
- Nenhum outro check afirma mais do que o caso que a sua prova exercita.

## Test policy

A tabela "Em que nível testar" do README responde às duas perguntas para o código que decide e é alcançado pelo HTTP, para garantias do banco, regras de fronteira e instrumentação. Ela não cobre o código que decide e só é alcançado por um comando ou pela fila, que é a forma do relay, da entrega e das inscrições. As linhas abaixo valem para esta feature.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Decide e é alcançado por um comando ou pela fila (o relay, o `DeliverEvent`, o transporte de fila e as inscrições) | uma na borda: o comando pelo `Artisan`, a entrega pela fila `sync` ou `database`, a inscrição pelo service provider no boot. Ela afirma cada linha da tabela de decisão, sem um teste separado da classe interna | um caso afirmado por linha: no relay, as 13 da linha "decisões do relay"; na entrega, as 5 de "decisões da entrega" e as 3 do `request_id`; na inscrição, as 3 recusas |
| Decide e é alcançado pelo HTTP (o `EventOutbox` chamado pelos casos de uso) | README: uma na borda **e** uma na própria camada | na borda: a mensagem de cada rota (C33, C35) e a gravação desfeita (C34, C37, C38); na camada: transação, payload e `request_id` (C2 a C7) |
| Formato publicado (nome, versão e payload dos 5 eventos) | uma na própria camada, table-driven sobre os 5 | cada nome, cada chave, o tipo de cada valor e a volta pelo `fromPayload` (C29, C30) |
| Esquema (`outbox_messages`) | README: uma direto no banco | cada coluna e cada índice pelo catálogo (C1) |
| Regra de arquitetura (`DomainEvent`, despacho do Laravel) | README: uma expectativa por namespace, vista falhando com uma violação de propósito | as 5 violações do C31 |

Evidence:

- relay: 7 pontos de decisão (há pendente, o transporte aceitou, `--once`, o sinal mudou, a limpeza está vencida, a mensagem passou de 7 dias, a mensagem está publicada), 13 linhas → decide. É alcançado só pelo comando. Análogo: nenhum comando do repositório decide hoje. O mais próximo pela forma é o `DeliverOrder`, um adaptador fino provado pelo `handle()` no `OrderStatusFlowTest`. O relay não é fino, então cada linha ganha prova na borda do comando.
- `DeliverEvent`: 2 pontos (a versão confere, o `request_id` é nulo), 5 linhas com as de restauração → decide. É alcançado pela fila.
- transporte de fila: 1 ponto (quantos consumidores inscritos), 2 linhas → decide. É alcançado pelo relay.
- inscrições: 3 pontos (implementa `EventConsumer`, o tipo do `handle()` confere, o nome já existe), 3 recusas → decide. É alcançado pelo boot.
- `EventOutbox`: 3 pontos (há transação, o valor é escalar, há `request_id` no `Context`), 6 linhas → decide. É alcançado pelo HTTP através dos casos de uso.
- consumidores: só repassam o evento ao caso de uso → instrumentação, coberta pelos casos de uso (C39 a C44).

Cost: 26 provas na borda do comando e da fila (C8 a C26) em 2 arquivos de teste novos. Sem estas linhas, as 13 decisões do relay e as 5 da entrega seriam provadas só pelo caminho feliz do ciclo completo (C47), que não falha quando a parada na falha, a retenção ou a versão estão erradas.

## Swept

- validation: C5, porque o payload aceita só escalares e nulo; C13, porque uma versão desconhecida é recusada; e C15 e C16, porque a inscrição confere o tipo e o nome
- failure modes: C3, C34, C37, C38, C40 e C43, porque a mensagem e a mudança de estado são desfeitas juntas; e C20, porque a falha do transporte para o ciclo e deixa o resto pendente
- idempotency: C41, porque um `PaymentApproved` repetido não gera um segundo `OrderPaid`. A entrega repetida pelo relay (Key decision 3) cai nas guardas que já existem, provadas por "keeps the first delivery estimate" e "ignores out of order or duplicated executions" (C41)
- authorization: existing - os guards, o throttle e a busca com o dono das duas rotas não mudam (C48). O relay e a entrega não têm superfície pública
- concurrency: C22, porque dois relays não pegam a mesma mensagem ao mesmo tempo (`skip locked`); e C37, porque a corrida da aprovação não deixa mensagem
- data lifecycle: C25, porque as publicadas saem depois de 7 dias e as pendentes nunca saem; e C17, porque um `queue-retry` depois da limpeza ainda entrega
- dependency failure: C20, C21, C24, C45 e C46, porque o transporte fora deixa as mensagens pendentes e o pedido avança quando ele volta
- state transitions: C39, C40, C41, C45, C46 e C47, porque cada transição do pedido continua acontecendo uma vez, pelo consumidor do Ordering
- observability: C6, C11, C12 e C47, porque o `request_id` atravessa o relay até os logs do consumidor; e C20, porque a falha do transporte deixa um `warning` com o `id` e o `name`

## Handoff

- Tamanho por `wc -c` dos arquivos que cada slice toca, dividido por 4, com os arquivos novos estimados:
  - S1 = 6k: 25 KB em 12 arquivos, 10 KB existentes (`AppServiceProvider`, `AssignRequestId`, `BaseRepository` e `tests/Pest.php`) e cerca de 15 KB novos;
  - S2 = 5k: 19 KB em 9 arquivos, com `bootstrap/app.php` e cerca de 17 KB novos;
  - S3 = 6k: 22 KB em 5 arquivos, com `docker-compose.yml`, o `Makefile` e cerca de 15 KB novos;
  - S4 = 32k: 129 KB em 32 arquivos. São os 5 eventos, os 5 casos de uso, os 5 consumidores, os 2 service providers, o `OrderEventPayloadTest`, o `ModuleBoundariesTest` (27 KB), os 8 testes reescritos (73 KB) e cerca de 9 KB novos;
  - documentação = 45k: README (90 KB), análise de domínio (80 KB) e `AGENTS.md` (10 KB).
- Total: 94k, abaixo do budget de 150k. Um builder só, sem handoff.
