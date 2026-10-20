# Event Idempotency checks

Profile: standard
Plan: `.specs/features/event-idempotency/plan.md`

23 checks em 3 slices · 4 one-way doors (a chave de "já agendei", o estado "entregue", o padrão do produtor, o nome da migration) · 0 open

Notação das provas. `make test-filter FILTER="<regex>"` roda no checkout da construção (no worktree, o Makefile já usa o código e o banco de testes dele) e passa no `--filter` do Pest o trecho do nome do teste. Os testes novos nomeados abaixo são escritos antes do código que os satisfaz e a partir deste texto, nunca lendo a implementação. As provas de comando são scripts em `.specs/features/event-idempotency/proofs/`, que saem com 0 só quando a afirmação vale e gravam a saída em `.specs/features/event-idempotency/evidence/c<NN>.txt`. Nos testes de reentrega, "entregar duas vezes" é chamar o `EventDelivery` duas vezes com o mesmo `EventEnvelope`, como o `DeliverEvent` e o `events:consume` fazem, e nunca o `handle()` direto.

## Checks

### S1 - Delivery: o agendamento anuncia uma vez · 6 files · 36 KB · ~9k

**C1** - O primeiro `OrderPaid` de um pedido grava uma linha em `deliveries` com o `order_id`, a data prevista calculada dos `deliveryBusinessDays` do evento e `delivered_at` nulo, e um `DeliveryScheduled` em `outbox_messages` com a mesma data (AC 1)
Proof: `make test-filter FILTER="schedules the first OrderPaid of an order"` - `tests/Feature/Fulfillment/DeliverySchedulingTest.php`

**C2** - O primeiro `OrderPaid` enfileira um `DeliverOrder` com o atraso de `shop.delivery_delay_seconds`, e só depois do commit da linha e do evento (AC 1)
Proof: `make test-filter FILTER="queues the delivery after the commit with the configured delay"` - `tests/Feature/Fulfillment/DeliverySchedulingTest.php`

**C3** - Um `OrderPaid` repetido para um pedido com `delivered_at` nulo grava zero linhas em `deliveries` e zero em `outbox_messages`, mantém a data da primeira linha (o relógio avançado um dia entre as duas entregas) e enfileira um `DeliverOrder` (AC 2)
Proof: `make test-filter FILTER="repeats nothing but the queued job for an undelivered order"` - `tests/Feature/Fulfillment/DeliverySchedulingTest.php`

**C4** - Um `OrderPaid` repetido para um pedido com `delivered_at` preenchido grava zero linhas, zero mensagens e enfileira zero jobs (AC 3)
Proof: `make test-filter FILTER="does nothing for an order already delivered"` - `tests/Feature/Fulfillment/DeliverySchedulingTest.php`

**C5** - Se gravar o `DeliveryScheduled` falha, a linha de `deliveries` não existe depois, nenhum `DeliverOrder` foi enfileirado e a exceção chega a quem entregou (o `EventDelivery`); a mesma mensagem entregue depois, com o outbox sadio, termina com uma linha, uma mensagem e um job (AC 4)
Proof: `make test-filter FILTER="rolls back the row when the event cannot be recorded"` - `tests/Feature/Fulfillment/DeliverySchedulingTest.php`

**C6** - Se o enfileiramento do `DeliverOrder` falha depois do commit, a linha e o `DeliveryScheduled` continuam gravados, a exceção chega a quem entregou, e a segunda entrega do mesmo `OrderPaid` enfileira o job com zero linhas e zero mensagens novas (AC 5)
Proof: `make test-filter FILTER="keeps the row and the event when queueing fails and repairs on redelivery"` - `tests/Feature/Fulfillment/DeliverySchedulingTest.php`

**C7** - Quando outra entrega grava a linha do mesmo pedido entre a leitura e a escrita da primeira, a primeira não lança exceção e termina com uma só linha e um só `DeliveryScheduled` para o pedido (AC 6). A corrida é simulada na mesma conexão (o teste insere a linha rival quando o INSERT em `deliveries` está para executar), porque o `RefreshDatabase` envolve o teste numa transação e dois processos não enxergam os mesmos dados
Proof: `make test-filter FILTER="loses a race on the same order cleanly"` - `tests/Feature/Fulfillment/DeliverySchedulingTest.php`

**C8** - O banco recusa uma segunda linha de `deliveries` para o mesmo `order_id` (violação de unicidade) e recusa apagar um pedido que tem linha (violação de chave estrangeira) (AC 7, porta 1)
Proof: `make test-filter FILTER="refuses a second delivery for the order and the deletion of an order with a delivery"` - `tests/Feature/Fulfillment/DeliveryTableTest.php`

**C9** - `deliveries.estimated_delivery_on` não aceita nulo e `deliveries.delivered_at` aceita, e `deliveries.id` é uuid (porta 2)
Proof: `make test-filter FILTER="declares the delivered state as a nullable delivered_at"` - `tests/Feature/Fulfillment/DeliveryTableTest.php`

**C10** - A migration `2026_10_20_000001_create_deliveries_table` mora em `app-modules/fulfillment/database/migrations`, o migrator a encontra junto das outras e o conjunto de nomes congelados passa a ter 15, com `deliveries` na lista de donos como `fulfillment` (porta 4)
Proof: `make test-filter FILTER="places each module migration in its owner package|keeps the fifteen frozen migration names"` - `tests/Unit/Architecture/ModuleMigrationsTest.php`

### S2 - DeliverOrder: a entrega anuncia uma vez · 4 files · 28 KB · ~7k

**C11** - O `DeliverOrder` para um pedido com `delivered_at` nulo preenche `delivered_at` e grava um `OrderDelivered` em `outbox_messages` (AC 8)
Proof: `make test-filter FILTER="delivers an order once and announces it"` - `tests/Feature/Fulfillment/DeliverOrderTest.php`

**C12** - O `DeliverOrder` executado de novo (retry ou job duplicado) mantém o `delivered_at` da primeira execução (o relógio avançado entre as duas) e grava zero mensagens (AC 9)
Proof: `make test-filter FILTER="keeps the first delivered_at and announces nothing on a second run"` - `tests/Feature/Fulfillment/DeliverOrderTest.php`

**C13** - Se gravar o `OrderDelivered` falha depois de `delivered_at` ser preenchido, `delivered_at` volta a nulo e nenhuma mensagem fica; o retry seguinte, com o outbox sadio, entrega o pedido (AC 10)
Proof: `make test-filter FILTER="rolls back delivered_at when the event cannot be recorded"` - `tests/Feature/Fulfillment/DeliverOrderTest.php`

**C14** - O `DeliverOrder` para um pedido sem linha cria a linha com `delivered_at` preenchido e `estimated_delivery_on` igual à data de hoje, e grava um `OrderDelivered` (AC 11)
Proof: `make test-filter FILTER="creates the delivered row for an order that has none"` - `tests/Feature/Fulfillment/DeliverOrderTest.php`

**C15** - Depois de agendar e entregar um pedido pelo Fulfillment, `orders.status` e `orders.updated_at` são os de antes (o Fulfillment nunca altera o pedido) (AC 12)
Proof: `make test-filter FILTER="leaves the order row untouched"` - `tests/Feature/Fulfillment/DeliverOrderTest.php`
Proof: `make test-filter FILTER="ModuleBoundariesTest"` - `tests/Unit/Architecture/ModuleBoundariesTest.php` continua verde com o Fulfillment usando a camada `Persistence`

### S3 - Redelivery: a promessa vale e tem prova · 5 files · 60 KB · ~15k

**C16** - O mesmo envelope de `OrderPlaced` entregue duas vezes a `MarkOrderAsAwaitingPayment` deixa o pedido em `awaiting_payment`, com o `updated_at` da primeira entrega e a contagem de `outbox_messages` igual (AC 13)
Proof: `make test-filter FILTER="redelivers OrderPlaced to MarkOrderAsAwaitingPayment"` - `tests/Feature/Messaging/ConsumerRedeliveryTest.php`

**C17** - O mesmo envelope de `PaymentApproved` entregue duas vezes a `MarkOrderAsPaid` deixa o pedido em `payment_approved` e grava um só `ordering.order_paid` (AC 14)
Proof: `make test-filter FILTER="redelivers PaymentApproved to MarkOrderAsPaid"` - `tests/Feature/Messaging/ConsumerRedeliveryTest.php`

**C18** - O mesmo envelope de `DeliveryScheduled` entregue duas vezes a `RecordEstimatedDelivery` mantém a primeira data em `orders.estimated_delivery_on` (mesmo quando o segundo envelope traz outra data) e o status inalterado (AC 15)
Proof: `make test-filter FILTER="redelivers DeliveryScheduled to RecordEstimatedDelivery"` - `tests/Feature/Messaging/ConsumerRedeliveryTest.php`

**C19** - O mesmo envelope de `OrderDelivered` entregue duas vezes a `MarkOrderAsDelivered` deixa o pedido em `delivered` com o `updated_at` da primeira entrega (AC 16)
Proof: `make test-filter FILTER="redelivers OrderDelivered to MarkOrderAsDelivered"` - `tests/Feature/Messaging/ConsumerRedeliveryTest.php`

**C20** - O mesmo envelope de `OrderPaid` entregue duas vezes a `ScheduleOrderDelivery` pelo `EventDelivery` grava uma só linha em `deliveries` e um só `fulfillment.delivery_scheduled` (AC 17)
Proof: `make test-filter FILTER="redelivers OrderPaid to ScheduleOrderDelivery"` - `tests/Feature/Messaging/ConsumerRedeliveryTest.php`

**C21** - No ciclo completo pela fila síncrona, com um `OrderPaid` reentregue entre dois ciclos do relay, o pedido termina `delivered` e existe exatamente uma mensagem de cada um de `payment.payment_approved`, `ordering.order_paid`, `fulfillment.delivery_scheduled` e `fulfillment.order_delivered` (AC 18)
Proof: `make test-filter FILTER="runs the lifecycle once when OrderPaid is delivered twice"` - `tests/Feature/OrderStatusFlowTest.php`

**C22** - Com a flag `event-transport` em `rabbitmq`, o mesmo envelope de `OrderPaid` publicado duas vezes e consumido pelo `events:consume` deixa uma só linha em `deliveries` e um só `fulfillment.delivery_scheduled` (AC 19)
Proof: `make test-filter FILTER="consumes OrderPaid twice from rabbitmq without announcing twice"` - `tests/Feature/Messaging/ConsumeEventsCommandTest.php`

**C23** - O README descreve a tabela `deliveries` e a guarda do Fulfillment e não diz mais que o retry do `DeliverOrder` "anuncia a entrega de novo"; o `AGENTS.md` e a `docs/domain-analysis.md` citam a regra de um teste de reentrega por consumidor e a tabela (AC 20)
Proof: `bash .specs/features/event-idempotency/proofs/c23-docs.sh`

**C24** - A suíte inteira e o Pint passam, e o smoke de carga passa (sem regressão em nenhum consumidor, rota ou migration)
Proof: `make test`
Proof: `make pint`
Proof: `make load-test TYPE=smoke`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| pontos assíncronos com reentrega (6) | `MarkOrderAsAwaitingPayment` C16 · `MarkOrderAsPaid` C17 · `RecordEstimatedDelivery` C18 · `MarkOrderAsDelivered` C19 · `ScheduleOrderDelivery` C20 · job `DeliverOrder` C12 | - |
| eventos do outbox (5) | `OrderPlaced` C16 · `PaymentApproved` C17 · `DeliveryScheduled` C18 · `OrderDelivered` C19 · `OrderPaid` C20 | - |
| estados do slice Delivery (6) | primeiro `OrderPaid` C1, C2 · repetido sem entrega C3 · repetido já entregue C4 · falha ao gravar C5 · falha ao enfileirar C6 · duas entregas simultâneas C7 | - |
| estados do slice DeliverOrder (4) | primeira execução C11 · retry C12 · falha antes do commit C13 · pedido sem linha C14 | - |
| portas da Landing (4) | `UNIQUE (order_id)` e `ON DELETE RESTRICT` C8 · `delivered_at` e `estimated_delivery_on` C9 · padrão do produtor C3, C12 · nome da migration C10 | - |
| entidades da Relations (1) | `orders` 1:0..1 `deliveries` C8 | - |
| transportes de evento (2) | fila do Laravel C21 · RabbitMQ C22 | - |
| migrations congeladas (15) | C10, table-driven sobre os 15 nomes (os 14 que já existiam e `2026_10_20_000001_create_deliveries_table`) | - |
| startup: descoberta da migration (2 assemblies) | `make migrate` C10 · harness de teste C10 (os dois usam a mesma descoberta da biblioteca por `app('migrator')->paths()`, uma montagem compartilhada) | - |
| estados do `OrderStatus` que o Fulfillment pode tocar (0) | nenhum: `orders.status` fica como estava em C15 | - |

- Claims que nomeiam um estado, um código ou um formato: C5, C6, C8, C9, C14 - cada um tem uma prova que atravessa a fronteira (o banco recusa, a exceção sobe, a linha existe)
- Nenhum outro check afirma mais que o caso único que a sua prova exercita; C7 afirma só a corrida simulada na mesma conexão, e C24 é a regressão da suíte e não prova nenhum comportamento novo

## Swept

- validation: n/a - os payloads de `OrderPaid`, `DeliveryScheduled` e `OrderDelivered` e as suas versões não mudam, e o `fromPayload()` já os valida
- failure modes: C5, C6, C13
- idempotency: C3, C4, C12, C16, C17, C18, C19, C20, C21, C22
- authorization: n/a - nenhuma rota nem contrato novo; os consumidores e o job rodam nos workers
- concurrency: C7 (simulada na mesma conexão, não em dois processos; a unicidade do banco em C8 é o que arbitra de verdade)
- data lifecycle: C8 (o pedido com linha não pode ser apagado), C14 (a linha do pedido que estava na fila antes da mudança); a poda de 7 dias do outbox não afeta a guarda, que é a linha
- dependency failure: C6 (a fila fora do ar depois do commit)
- state transitions: C1, C3, C4, C11, C12, C15
- observability: n/a - o plano não pede log novo; os logs de hoje ("Delivery scheduled." e "Carrier delivered the order.") continuam, e se eles saem numa reentrega que não fez nada é decisão do diff

## Test policy

Não se aplica: o repositório já responde às duas perguntas para o que esta mudança toca. As regras de decisão vão para testes Feature com banco (os consumidores e os casos de uso gravam em tabelas e no outbox, como `OrderStatusFlowTest` e `PaymentTest`), a arquitetura vai para `tests/Unit/Architecture`, e a cobertura exigida é um caso por linha das tabelas de estado acima, que o Coverage enumera. Os casos de uso e a repository novos do Fulfillment ficam provados pelo caminho de borda (`EventDelivery` e job), e o `ScheduleDeliveryUseCase` decide em três ramos (primeira vez, repetido sem entrega, repetido já entregue), todos com teste próprio em C1, C3 e C4.

## Handoff

Tamanho, em tokens, pela conta `wc -c` dividida por quatro, sobre o que cada slice lê ou edita à mão:

- S1 = 9k: os 5 arquivos do Fulfillment ≈ 4,2 KB, os 4 moldes do Payment (model, repository, factory, migration) ≈ 5,7 KB, `tests/Pest.php` 14,5 KB, `ModuleMigrationsTest` 3,1 KB e os 2 testes novos ≈ 8 KB: ≈ 36 KB ≈ 9k, tudo no `backend/`
- S2 = 7k: o job e o caso de uso de entrega ≈ 1,5 KB, o `DeliverOrderTest` novo ≈ 6 KB e a leitura do `OrderStatusFlowTest` 11,4 KB: ≈ 28 KB ≈ 7k, no mesmo módulo de S1
- S3 = 15k: `ConsumerRedeliveryTest` novo ≈ 8 KB, `OrderStatusFlowTest` e `ConsumeEventsCommandTest` 21,6 KB, `EventDeliveryTest` 10 KB, trechos do README, do `AGENTS.md` e da `docs/domain-analysis.md` ≈ 20 KB: ≈ 60 KB ≈ 15k, entra nos testes de mensageria e na documentação
- total ≈ 31k, abaixo do budget de 150k - one builder, sem pergunta
- Mechanism: one builder
