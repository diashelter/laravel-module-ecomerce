# Module Isolation Pilot checks

Profile: standard
Plan: `.specs/features/module-isolation-pilot/plan.md`

39 checks em 2 slices · 7 one-way doors · 0 open

**Notação das provas**

- **`make test-filter FILTER="<regex>"`:** roda no checkout da construção, a branch `helterdias/module-isolation-pilot` na pasta principal, sobre o banco `ecommerce_testing`. O trecho é passado ao `--filter` do Pest.
- **Testes novos:** são escritos antes do código que os satisfaz e a partir deste texto, nunca lendo a implementação.
- **Testes movidos:** mantêm o nome e as asserções. Uma asserção que afirma o estado de outro módulo (por exemplo, o status do pedido num teste do Payment) não é apagada: ela vai, igual, para um teste da suíte de composição em `backend/tests`, citado na prova do check correspondente.
- **Provas de comando:** são scripts em `.specs/features/module-isolation-pilot/proofs/`. Cada um sai com 0 só quando a afirmação vale e grava a saída em `evidence/c<NN>.txt`.
- **Cópia sem um módulo:** é um `git worktree` descartável em `.claude/worktrees/removal-<nome>`, criado a partir do `HEAD`, onde:
  - os diretórios dos módulos removidos e os seus `require` saem do `composer.json`;
  - o `composer remove` refaz o estado do Composer e a descoberta de pacotes;
  - os testes rodam num container avulso do serviço `api` que monta o `backend/` da cópia, sobre o banco e o vhost `ecommerce_testing_removal_<nome>`, como o `Makefile` já faz num worktree.
- **Base da feature:** `be1c84c`.

## Checks

### S1 - Module Tests · 55 files · 123 KB · ~31k

**C1** - Os 27 arquivos da tabela *Arquivos movidos* do plano existem em `backend/app-modules/<módulo>/tests/<Tipo>/<caminho relativo>`, sendo `<Tipo>` o primeiro nível do caminho antigo, e nenhum deles existe mais em `backend/tests` (AC 1)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c01-moved-files.sh` - table-driven sobre os 27 caminhos

**C2** - Um único `make test` executa, na mesma execução e com exit 0, o caso `approves the fake approved card` (Payment, em `app-modules`) e o caso `shows the delivery estimate after the payment is approved` (composição, em `backend/tests`) (AC 2)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c02-make-test.sh`

**C3** - Um teste em `app-modules/<módulo>/tests/Feature` roda com o `TestCase` da raiz e o `RefreshDatabase`, e um teste em `app-modules/<módulo>/tests/Unit` roda com o `TestCase` e sem o `RefreshDatabase` (AC 2)
Proof: `make test-filter FILTER="runs a module feature test with the refreshed database|runs a module unit test without the database"` - `app-modules/shared/tests/Feature/FeatureHarnessTest.php` e `app-modules/shared/tests/Unit/UnitHarnessTest.php`

**C4** - Os alvos `test`, `test-filter` e `test-prepare` do `Makefile` e os passos de teste de `.github/workflows/ci.yml` estão iguais aos da base `be1c84c`: nenhum comando, flag ou variável nova (AC 2)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c04-unchanged-commands.sh`

**C5** - `make test-filter FILTER="approves the fake approved card"` executa exatamente 1 teste, o do Payment em `app-modules/payment/tests/Unit`, e sai com 0 (AC 3)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c05-test-filter.sh`

**C6** - Numa cópia com todos os módulos de negócio removidos exceto o do teste, o `php artisan migrate:fresh --force` sai com 0 e a pasta `tests` daquele módulo passa inteira. Para o `Shared` e o `Messaging`, saem todos os 8 módulos de negócio (AC 4)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c06-module-alone.sh identity`
Proof: `bash .specs/features/module-isolation-pilot/proofs/c06-module-alone.sh messaging`
Proof: `bash .specs/features/module-isolation-pilot/proofs/c06-module-alone.sh shared`
Proof: `bash .specs/features/module-isolation-pilot/proofs/c06-module-alone.sh catalog`
Proof: `bash .specs/features/module-isolation-pilot/proofs/c06-module-alone.sh payment`

**C7** - O `composer.json` de cada pacote que tem pasta `tests` mapeia `Modules\<Módulo>\Tests\` para `tests/`, os que não têm não mapeiam, e nenhuma chave `autoload` ou `autoload-dev` do `composer.json` da raiz menciona um namespace `Modules\...\Tests` (AC 5, porta 6)
Proof: `make test-filter FILTER="gives every module the manifest of the design|maps no module test namespace in the root composer"` - `tests/Unit/Architecture/ModulePackagesTest.php`

**C8** - A suíte de arquitetura acusa um arquivo em `app-modules/<A>/tests` que nomeia uma classe de outro módulo de negócio (para o `Shared` e o `Messaging`, qualquer módulo de negócio): passa com as pastas de hoje e falha com a fixture de violação proposital (AC 6)
Proof: `make test-filter FILTER="keeps the tests of every module inside the module|fails on a deliberate violation"` - `tests/Unit/Architecture/ModuleLayersTest.php`

**C9** - Cada declaração `it(`/`test(` da base `be1c84c` (628, contadas por `git grep -E '^\s*(it|test)\('` em `backend/tests`) continua existindo pelo nome, ao menos tantas vezes quanto na base, em `backend/tests` ou `backend/app-modules/*/tests`, exceto `ties payments to existing orders and keeps orders that have payments` e os dois renomeados pelos próprios checks (`keeps the fifteen frozen migration names` -> `keeps the sixteen frozen migration names`, C28; `lists exactly the ten module packages` -> `lists exactly the eleven module packages`, C11), que existem com o nome novo; e a soma das declarações no `HEAD` é maior ou igual a 627 (AC 7). Correção de 2026-10-20: o plano dizia 637, número de uma contagem errada na descoberta
Proof: `bash .specs/features/module-isolation-pilot/proofs/c09-test-count.sh`

**C10** - A identidade de teste do `Shared` entra no guard `customer` com um `Authenticatable` que só tem o `id`: sem argumento, o id é um UUID v7 canônico novo a cada chamada; com um id, é esse id; e `auth('customer')->id()` devolve o mesmo valor (AC 8, porta 7)
Proof: `make test-filter FILTER="signs a customer in by id alone"` - `app-modules/shared/tests/Feature/TestIdentityTest.php`

### S2 - Payment ↔ Ordering · 39 files · 106 KB · ~26k

**C11** - O `ModuleRegistry` lista exatamente 11 pacotes (os 10 de antes e `integration`, com namespace `Modules\Integration\`), o manifesto do `integration` registra só o `Modules\Integration\Providers\IntegrationServiceProvider`, e o `ModuleLayout` classifica o `Integration` como `COMPOSITION`, fora de `BUSINESS` e de `INFRASTRUCTURE` (porta 1)
Proof: `make test-filter FILTER="lists exactly the eleven module packages|gives every module the manifest of the design|classifies the integration package as composition"` - `tests/Unit/Architecture/ModulePackagesTest.php`

**C12** - Nenhum arquivo de `app-modules/payment` (src, routes, database, tests) contém `Modules\Ordering`, e nenhum arquivo de `app-modules/ordering` contém `Modules\Payment` (AC 9)
Proof: `make test-filter FILTER="isolates the payment module"` - `tests/Unit/Architecture/ModuleLayersTest.php`

**C13** - Um módulo isolado (hoje, só o `Payment`) não nomeia outro módulo de negócio, e nenhum outro pacote nomeia ele, exceto o `Integration`. A regra falha com a fixture de violação em cada direção (Payment nomeando Ordering; Ordering nomeando Payment) e passa com a fixture do `Integration` nomeando os dois (AC 10)
Proof: `make test-filter FILTER="lets only the integration package name an isolated module|fails on a deliberate isolation violation"` - `tests/Unit/Architecture/ModuleLayersTest.php`

**C14** - O cliente autenticado que paga o seu pedido em `awaiting_payment` com `fake_card_approved` recebe `202` com `data.order_id`, `data.status` `approved`, `data.amount_cents` igual ao total do pedido, a mensagem `Pagamento aprovado. O pedido será atualizado em instantes.` e só as chaves `id`, `order_id`, `status`, `amount_cents` em `data`; fica uma linha `approved` em `payments` e um `payment.payment_approved` com payload `{"order_id": "<id>"}` (AC 11)
Proof: `make test-filter FILTER="approves the payment of an order awaiting payment|answers the payment attempt without card data|records the approved payment and announces it once|charges the order total and ignores amounts in the request"` - `app-modules/payment/tests/Feature/PaymentTest.php`
Proof: `make test-filter FILTER="shows the delivery estimate after the payment is approved"` - `tests/Feature/DeliveryEstimateTest.php` (o mesmo pagamento pela rota, com o Ordering real atrás do `Integration`)

**C15** - Se gravar o `payment.payment_approved` falha, nenhuma linha `approved` fica em `payments` e nenhuma mensagem fica no outbox (AC 11, mesma transação)
Proof: `make test-filter FILTER="keeps no approved payment when the event cannot be recorded"` - `app-modules/payment/tests/Feature/PaymentTest.php`
Proof: `make test-filter FILTER="keeps neither the approval nor the message when the outbox fails"` - `tests/Feature/Outbox/OrderEventsOutboxTest.php`

**C16** - Para um pedido desconhecido ou de outro cliente, a rota responde `404` com o corpo de id desconhecido, chama o gateway zero vezes e não grava linha em `payments`; a porta é consultada com o id do cliente autenticado (AC 12)
Proof: `make test-filter FILTER="answers 404 to the payment of an unknown order|forbids paying an order of another customer|asks the order port with the signed-in customer"` - `app-modules/payment/tests/Feature/PaymentTest.php`
Proof: `make test-filter FILTER="answers 404 before the status for another customer order|answers 404 to the payment of an unknown order"` - `tests/Feature/Account/CustomerRecordRoutesTest.php` (o dono na busca real do Ordering)

**C17** - Um pedido que não aguarda pagamento, ou que já tem pagamento aprovado, recebe `409` com `Este pedido não está aguardando pagamento.` e o gateway é chamado zero vezes. A regra decide sobre as 4 combinações de "aguarda pagamento" × "já tem aprovado", e só "aguarda e não tem" passa (AC 13)
Proof: `make test-filter FILTER="rejects payment for orders that are not awaiting payment|rejects a second payment while the queue has not moved the order"` - `app-modules/payment/tests/Feature/PaymentTest.php`
Proof: `make test-filter FILTER="decides whether an order can be paid"` - `app-modules/payment/tests/Unit/Services/PaymentServiceTest.php`, table-driven sobre as 4 combinações

**C18** - Quando uma aprovação concorrente grava primeiro, a tentativa perdedora responde `409`, fica uma só linha `approved` e nenhum `payment.payment_approved` dela (concorrência, existente)
Proof: `make test-filter FILTER="loses the race to a concurrent approval with 409"` - `app-modules/payment/tests/Feature/PaymentTest.php`
Proof: `make test-filter FILTER="raises a conflict when a concurrent approval wins the race"` - `app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php`

**C19** - Uma cobrança recusada responde `402`, mantém a linha `declined` em `payments` e não grava `payment.payment_approved`; com o Ordering real, o pedido continua `awaiting_payment` (AC 14)
Proof: `make test-filter FILTER="declines the payment and keeps the order awaiting payment|keeps the declined payment after raising the decline"` - `app-modules/payment/tests/Feature/PaymentTest.php` e `app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php`
Proof: `make test-filter FILTER="keeps the order awaiting payment after a declined payment"` - `tests/Feature/PaymentFlowTest.php`

**C20** - Um corpo inválido recebe `422` antes de a porta de pedidos ser consultada, para qualquer id: com uma porta que falharia o teste se chamada, e com a porta indisponível (AC 15)
Proof: `make test-filter FILTER="rejects an invalid card token without charging|validates the payment body before checking the order owner|validates the body before an unavailable order port"` - `app-modules/payment/tests/Feature/PaymentTest.php`

**C21** - Com a porta de pedidos indisponível, um corpo válido recebe `503` com exatamente `{"code":"SERVICE_UNAVAILABLE","message":"Este recurso está indisponível no momento.","errors":{},"request_id":"<X-Request-ID>"}`, o gateway é chamado zero vezes e `payments` e `outbox_messages` ficam vazias. Numa cópia sem o Ordering, a porta resolvida pelo container é a indisponível e a rota responde o mesmo (AC 16, porta 4)
Proof: `make test-filter FILTER="answers 503 when the order port is unavailable"` - `app-modules/payment/tests/Feature/PaymentTest.php`
Proof: `bash .specs/features/module-isolation-pilot/proofs/c21-payment-without-ordering.sh`

**C22** - O registro de inscrições lê `payment.payment_approved` com `Modules\Ordering\Core\InboundEvents\PaymentApproved` para o consumidor `ordering.mark_order_as_paid`, e essa entrega leva um pedido `awaiting_payment` a `payment_approved` (AC 17, porta 3)
Proof: `make test-filter FILTER="subscribes the five consumers under their stable names|reads the payment approval with the class of the ordering module"` - `tests/Feature/Messaging/ConsumerNamesTest.php`
Proof: `make test-filter FILTER="redelivers PaymentApproved to MarkOrderAsPaid"` - `tests/Feature/Messaging/ConsumerRedeliveryTest.php`

**C23** - O mesmo envelope de `payment.payment_approved`, montado a partir da classe produtora `Modules\Payment\Events\PaymentApproved`, entregue duas vezes pelo `EventDelivery` ao `MarkOrderAsPaid`, deixa o pedido em `payment_approved` e exatamente 1 `ordering.order_paid` no outbox (AC 18, AD-001)
Proof: `make test-filter FILTER="redelivers PaymentApproved to MarkOrderAsPaid"` - `tests/Feature/Messaging/ConsumerRedeliveryTest.php`

**C24** - Para cada nome de evento inscrito, a classe leitora e a classe produtora (a de `Modules\<Prefixo>\Events` com o mesmo nome) declaram a mesma versão, e o payload que a produtora grava, lido pela leitora e regravado, dá o mesmo payload. Um par de fixtures que diverge numa chave é acusado (AC 19, porta 3)
Proof: `make test-filter FILTER="reads every subscribed event from the payload its producer writes|detects a reader that disagrees with its producer"` - `tests/Unit/Architecture/OrderEventPayloadTest.php`

**C25** - Nenhum arquivo de código de `app-modules/*/src` fora de `Modules\Ordering\Core\Consumers` e `Modules\Ordering\Providers` (e da própria pasta) nomeia `Modules\Ordering\Core\InboundEvents` (os testes de composição podem nomeá-la, como o C22 faz); a regra falha com a fixture de violação proposital (AC 20)
Proof: `make test-filter FILTER="keeps the inbound events inside the consumers and the provider|fails on a deliberate violation"` - `tests/Unit/Architecture/ModuleLayersTest.php`

**C26** - Depois do `migrate:fresh`, `payments.order_id` não tem chave estrangeira, e as outras 5 chaves entre módulos (`orders.customer_id`, `order_items.product_id`, `stocks.product_id`, `customer_addresses.customer_id`, `deliveries.order_id`) apontam para as mesmas tabelas de antes (AC 21, porta 5)
Proof: `make test-filter FILTER="creates the cross module foreign keys|keeps payments.order_id without a foreign key"` - `tests/Feature/Database/ModuleMigrationsForeignKeyTest.php`

**C27** - Num banco migrado com as migrations da base `be1c84c`, um `migrate` com as do `HEAD` apaga `payments_order_id_foreign`, mantém as outras 5 chaves entre módulos e deixa todas as migrations como `Ran` (AC 21, porta 5)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c27-migrate-existing.sh`

**C28** - A migration nova `2026_10_21_000001_drop_payments_order_id_foreign` mora em `app-modules/payment/database/migrations`, a `2026_10_06_000001_create_payments_table` mantém o nome, e o conjunto congelado passa a ter 16 nomes (porta 5)
Proof: `make test-filter FILTER="places each module migration in its owner package|keeps the sixteen frozen migration names"` - `tests/Unit/Architecture/ModuleMigrationsTest.php`

**C29** - Numa cópia sem o Payment, o `migrate:fresh` sai com 0, `POST /api/orders/{order}/payment` responde `404`, e a pasta `tests` de cada módulo restante que tem uma (Identity, Messaging, Shared e Catalog) passa (AC 22)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c29-without-payment.sh`

**C39** - Numa cópia sem o Payment, um pedido criado em `placed` e levado a `awaiting_payment` pelo `ordering.order_placed` entregue pelo relay continua `awaiting_payment` depois da tentativa de pagamento (`404`) e de o relay esvaziar o outbox de novo, e nenhum `payment.payment_approved` existe (o estado "Payment ausente" do design; acrescentado na rodada 2 da verificação, que achou esse membro sem prova)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c29-without-payment.sh` - o caso `keeps an order awaiting payment without the payment module installed`, copiado de `proofs/c29-WithoutPaymentTest.php`

**C30** - Com os dois módulos instalados, `OrdersToPay` resolve para o adaptador do `Integration`. Ele devolve um `OrderToPay` com o id, o id do cliente e o total do pedido, e `awaitingPayment` verdadeiro só em `awaiting_payment` (tabela sobre os 4 status). Para um pedido desconhecido ou de outro cliente, devolve `null` (AC 23)
Proof: `make test-filter FILTER="resolves the order port to the integration adapter|translates each order status into awaiting payment|answers null for an order the customer cannot pay"` - `tests/Feature/Integration/OrdersToPayAdapterTest.php`

**C31** - `Modules\Payment\Contracts\OrdersToPay` é uma interface com um só método, `findForPayment(string $customerId, string $orderId): ?OrderToPay`, e `Modules\Payment\Core\ValueObjects\OrderToPay` tem o construtor `(string $id, string $customerId, int $totalCents, bool $awaitingPayment)` (porta 2)
Proof: `make test-filter FILTER="declares the order port of the payment module"` - `tests/Unit/Architecture/IdTypesTest.php`

**C32** - Um cliente sem sessão recebe `401` no `POST /api/orders/{order}/payment`, e a 21ª tentativa no mesmo minuto recebe `429` (autorização e limite, existentes)
Proof: `make test-filter FILTER="requires a signed-in customer to pay|throttles payment attempts after 20 per minute"` - `app-modules/payment/tests/Feature/PaymentTest.php`

**C33** - O `AGENTS.md`, o `README.md`, o `docs/domain-analysis.md` e o `.design/module-isolation.md` descrevem o pacote `Integration` e a sua exceção de fronteira, a porta do consumidor (`OrdersToPay`), o `503` `SERVICE_UNAVAILABLE`, a exceção da migration editada, as pastas `app-modules/<módulo>/tests` e os 5 módulos com testes nelas, e o conjunto congelado de 16 migrations. Nenhum deles diz mais que os testes ficam só em `backend/tests`, que são 15 migrations congeladas ou que `payments.order_id` tem chave estrangeira. Os links relativos novos resolvem (AC 24)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c33-docs.sh`

**C34** - `make test` sai com 0, sem nenhum teste pulado ou incompleto (regressão; inclui o teste de log `logs each payment attempt without the card token`, que vai com o `PaymentTest`)
Proof: `bash .specs/features/module-isolation-pilot/proofs/c34-suite.sh`

**C35** - `make pint` não deixa nenhum arquivo para corrigir
Proof: `bash .specs/features/module-isolation-pilot/proofs/c35-pint.sh`

**C36** - O smoke de carga passa (`make load-test TYPE=smoke`), como o `AGENTS.md` pede para mudança de status de rota
Proof: `bash .specs/features/module-isolation-pilot/proofs/c36-load-smoke.sh`

**C37** - O `Integration` usa só as fachadas dos módulos de negócio (`Contracts`, `Events` e o vocabulário que eles alcançam), e o `ModuleBoundariesTest` continua verde com o Payment usando só `Shared` e `Messaging` (porta 1)
Proof: `make test-filter FILTER="ModuleBoundariesTest|keeps the integration package on the module facades"` - `tests/Unit/Architecture/ModuleBoundariesTest.php`

**C38** - A `PaymentFactory` cria um pagamento sem criar pedido: `Payment::factory()->create()` grava 1 linha em `payments` e 0 em `orders`, com um `order_id` UUID v7 canônico (assunção 2)
Proof: `make test-filter FILTER="creates a payment without creating an order"` - `app-modules/payment/tests/Feature/PaymentTest.php`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| arquivos movidos (27) | C1, table-driven sobre os 27 caminhos da tabela do plano | - |
| módulos com testes que sobem sozinhos (5) | `Identity` C6 · `Messaging` C6 · `Shared` C6 · `Catalog` C6 · `Payment` C6 | - |
| tipos de teste num módulo (2) | `Feature` com `RefreshDatabase` C3 · `Unit` sem banco C3 | - |
| `POST /api/orders/{order}/payment` statuses (8) | `202` C14 · `401` C32 · `402` C19 · `404` C16 · `409` C17 · `422` C20 · `429` C32 · `503` C21 | - |
| regra "pode ser pago", combinações (4) | aguarda e sem aprovado C17 · aguarda e com aprovado C17 · não aguarda e sem aprovado C17 · não aguarda e com aprovado C17 (table-driven) | - |
| `OrderStatus` -> `awaitingPayment` (4) | `placed` C30 · `awaiting_payment` C30 · `payment_approved` C30 · `delivered` C30 (table-driven) | - |
| respostas do adaptador (3) | pedido do cliente C30 · pedido desconhecido C30 · pedido de outro cliente C30 | - |
| portas da Landing (7) | 1 `Integration` C11, C37 · 2 porta do consumidor C31 · 3 classe leitora C22, C24 · 4 fornecedor ausente C21 · 5 sem chave estrangeira C26, C27, C28 · 6 testes no módulo C1, C3, C7 · 7 identidade de teste C10 | - |
| entidades da Relations (1) | `orders` 1:N `payments` sem chave C26, C27 | - |
| configurações de remoção (3) | um módulo sozinho C6 · Payment sem o Ordering C21 · sem o Payment C29 | - |
| regras de arquitetura com violação proposital (4) | teste nomeando outro módulo C8 · isolamento nas duas direções C13 · uso das classes leitoras C25 · leitora divergente da produtora C24 | - |
| estados do slice Payment ↔ Ordering no design (9) | pedido pago `202` C14 · desconhecido ou de outro cliente `404` C16 · não aguarda `409` C17 · recusado `402` C19 · Ordering ausente `503` C21 · Payment ausente, rota `404` C29 · Payment ausente, pedido em `awaiting_payment` C39 · Payment ausente, pastas restantes passam C29 · suíte do Payment sem o Ordering C6 | - |
| documentos (4) | `AGENTS.md` C33 · `README.md` C33 · `docs/domain-analysis.md` C33 · `.design/module-isolation.md` C33 | - |
| startup: ligação da porta `OrdersToPay` (2 assemblies) | harness de teste C30 · aplicação montada sem o Ordering C21 (as duas pelo mesmo `IntegrationServiceProvider`, descoberto pelo manifesto do pacote) | - |
| startup: inscrição da classe leitora (2 assemblies) | harness de teste C22 · workers (`DeliverEvent` e `events:consume`) C22 (os dois pelo mesmo `boot()` do `OrderingServiceProvider`, que todo processo monta) | - |

- Claims que nomeiam um status, uma rota ou um formato: C14, C16, C17, C19, C20, C21, C29 e C32. Cada um tem uma prova que atravessa a rota HTTP.
- O C17 afirma a tabela de 4 combinações e prova as 4 no teste de unidade, além dos dois casos de rota.
- O C30 afirma a tabela de 4 status e prova as 4.
- Nenhum outro check afirma mais que o caso que a sua prova exercita.

## Swept

- validation: C20 (o corpo é validado antes da porta, com ou sem o Ordering)
- failure modes: C15 (falha ao gravar o evento não deixa o aprovado), C21 (sem o fornecedor, nada é gravado)
- idempotency: C23 (reentrega do `payment.payment_approved`, AD-001)
- authorization: C16 (pedido de outro cliente é `404`), C32 (`401` sem sessão); o guard `auth:customer` é o de hoje
- concurrency: C18 (aprovação concorrente perde com `409`; quem arbitra é o índice único parcial de `payments`, que não muda)
- data lifecycle: C26 e C27 (a chave sai dos bancos novos e dos já migrados; nenhuma linha muda), C38 (a factory não cria pedido); o `down()` da migration nova não faz nada (assunção 6)
- dependency failure: C21 (fornecedor ausente responde `503`), C29 (o Payment ausente não derruba os outros)
- state transitions: C22 (`awaiting_payment` -> `payment_approved`), C17 (os outros status recusam o pagamento)
- observability: existing - o teste `logs each payment attempt without the card token` vai com o `PaymentTest` e roda em C34; o plano não pede log para o `503`

## Test policy

Não se aplica. O repositório já responde às duas perguntas para o que esta mudança toca:

- **Regras de decisão** vão para testes Feature com banco ou para testes Unit sem banco, como `PaymentTest`, `PaymentServiceTest` e `OrderStatusFlowTest`.
- **Arquitetura** fica em `tests/Unit/Architecture`.
- **Cobertura exigida:** um caso por linha das tabelas de decisão, que o Coverage enumera.

As duas decisões novas têm prova na própria camada:

- o adaptador do `Integration` decide sobre 1 ponto (o status é `awaiting_payment` ou não) e repassa o `null`, provado por C30 sobre os 4 status;
- o `PaymentService` passa a decidir sobre 2 booleanos (4 combinações), provado por C17.

## Handoff

Tamanho em tokens, pela conta `wc -c` dividida por quatro, sobre o que cada slice lê ou edita:

- **S1 ≈ 31k:** 55 arquivos, ≈ 123 KB. São os 28 testes movidos, as 14 fixtures do Messaging, `tests/Pest.php`, `tests/TestCase.php`, `phpunit.xml`, os 10 `composer.json` de pacote e o da raiz, `ModulePackagesTest`, `ModuleLayersTest` e `ModuleLayout`. Tudo no `backend/`.
- **S2 ≈ 26k:** 39 arquivos, ≈ 106 KB. São o pacote Payment inteiro, o consumidor, o provider, o contrato e o value object do Ordering envolvidos, o `ApiErrorCode` e o `ApiExceptionRenderer`, os 4 testes do Payment e os 9 testes de composição e de arquitetura que mudam.
- **Documentação e provas ≈ 35k:**
  - `AGENTS.md` 22 KB;
  - `.design/module-isolation.md` 28 KB;
  - as seções do README e da `docs/domain-analysis.md` que mudam, ≈ 50 KB;
  - os scripts de prova, ≈ 40 KB.
- **Total ≈ 92k**, abaixo do budget de 150k: um builder, sem pergunta.
- **Mechanism:** one builder.

Progresso do builder:

- C3, C7 e C10 verdes no `make test` do commit `test(modules): move the isolated tests into their modules` (2106 testes); os 28 arquivos já estão nos módulos, e o C1 fecha quando a sua prova rodar
- C8, C11 a C20, C21 (a primeira prova), C22 a C26, C28, C30, C31, C32, C37 e C38 verdes no `make test` do commit `feat(payment): read the order through a port of the payment module` (2253 testes); a linha `payments.order_id restricts` do `UuidKeysTest` saiu pela Key decision 5 do design, como o `Impact` do plano registra
- Renegociado com diashelter em 2026-10-20: o Inventory saiu de C1 e C6 (27 arquivos, 5 módulos), porque a prova C6 mostrou que o `migrate:fresh` dele falha sem o Catalog (chave `stocks.product_id`); o `StockServiceTest` voltou para `backend/tests` (assunção 8 do plano). A prova C6 também achou um caso de composição no `EventDeliveryTest` do Messaging, que voltou para `tests/Feature/Outbox/ModuleEventsListenersTest.php`
- C6 verde para `payment`, `identity`, `messaging`, `shared` e `catalog` (evidência em `evidence/c06-<módulo>.txt`)
- C1, C4, C5, C9, C21 (a segunda prova), C27 e C29 verdes pelos scripts de `proofs/`; o C9 foi corrigido para a contagem real da base (628) e passou a conferir cada caso pelo nome
- C2, C33, C34, C35 e C36 verdes no `HEAD` final (`make test`: 2254 testes; o smoke de carga com as 3 jornadas); C6 de novo verde para os 5 módulos

- **Boundary:** C1 a C38 fechados no commit `test(module-isolation): record the final proofs of the pilot`
- **Settled mid-build:** diashelter tirou o Inventory de C1 e C6 (o `StockServiceTest` ficou na raiz, assunção 8); o C9 passou a usar a contagem real da base (628) e a conferir cada caso pelo nome, com as duas renomeações que o C11 e o C28 pedem; a linha `payments.order_id restricts` do `UuidKeysTest` saiu pela Key decision 5; o caso de listeners do `EventDeliveryTest` voltou para a composição (`Outbox/ModuleEventsListenersTest`), e a regra do C8 passou a acusar também o apoio da suíte da raiz
- **Abandoned:** trocar o `foreignUuid('order_id')` por `uuid('order_id')` na migration editada; voltou ao literal aprovado na porta 5 (só sai o `->constrained()->restrictOnDelete()`)
- Rodada 1 da verificação: FAIL só por documentação (C33). Correções: as 6 linhas da `docs/domain-analysis.md` que ainda descreviam o Payment lendo o `PayableOrders` ou a chave `payments.order_id`, com a prova do C33 ampliada para pegá-las; a linha "Payment ausente" do design alinhada ao C29 (o Ordering ainda não tem pasta de testes); a Key decision 1 do design e o `Impact` do plano descrevendo a regra de isolamento pela varredura do `ModuleLayersTest`; os caminhos de C17, C18 e C19 e o escopo de C25 (código de `app-modules/*/src`)
- Rodada 2 da verificação: FAIL por documentação e por um membro do design sem prova. Correções: a linha da `docs/domain-analysis.md` que ainda comparava `deliveries` a um `payments` restrito, e a contagem de tipos públicos do README (30, com o `OrderToPay`), com a prova do C33 ganhando uma heurística para qualquer linha que ainda descreva `payments` como restrito; o C39 novo prova o estado "Payment ausente: o pedido fica em `awaiting_payment`", e o Coverage ganhou a linha dos estados do slice Payment ↔ Ordering
- Rodada 3 da verificação: 39/39 checks e todos os achados da rodada 2 corrigidos, FAIL por uma contagem antiga (`docs/domain-analysis.md:498`, 29 tipos públicos em vez de 30). Correção depois do limite de três rodadas: a contagem atualizada e a prova do C33 casando a contagem em qualquer redação; o C39 passou a levar o pedido a `awaiting_payment` pelo `ordering.order_placed` entregue pelo relay, para que um consumidor que mexa no pedido apareça (achado não bloqueante da rodada 3). Uma quarta rodada depende de diashelter, como o procedimento manda
- Rodada 4 da verificação (escopada, pedida por diashelter depois do limite de três): PASS em `91be57d`, 39/39 checks e 3 falhas novas, todas pegas. **Aprovado por diashelter em 2026-10-21:** o texto atual do C39, acrescentado na rodada 2 e reescrito no `83bd3e8`. A rodada 4 tinha apontado que a mudança depois da aprovação não estava registrada
- Review do PR #30 (2026-10-21, depois da rodada 4): o `ModuleLayout::testsViolations` passou a acusar a chamada de um teste de módulo a um helper do `tests/Pest.php` ou do `tests/Helpers.php` de outro módulo de negócio, com duas violações plantadas (`module-test-calls-business-helper` e `module-test-calls-composition-helper`). A violação plantada `module-test-uses-business` perdeu o `namespace Tests\...`, que sozinho já a fazia ser acusada. O relatório da verificação é anterior a esse commit
- Rodada 5 da verificação (escopada sobre `8418897..40058c5`): FAIL em `40058c5`, com os 39 checks de pé e a regra dos helpers provada, porque o R5-M5 (o Payment fora da lista do `AGENTS.md:97`) sobreviveu ao C33: a prova aceitava qualquer parágrafo com os 5 nomes e `test`. Correção: a prova do C33 exige a lista explícita dos 5 módulos com testes em cada documento e acusa toda lista que nomeie 3 ou mais deles, no máximo um módulo de fora, e não seja exatamente os 5 (uma lista com dois ou mais módulos sem testes fica de fora, para não confundir a lista dos 8 módulos de negócio). Sem mudar o claim do C33
- Rodada 6 da verificação (escopada sobre `9b2c6e5..aa10021`): PASS em `aa10021`, com 39/39 checks, 5 falhas novas e todas pegas, o R5-M5 entre elas. A frase acima foi corrigida depois da rodada 6, que achou que ela exagerava o limite da prova (`c33-docs.sh:20`)
