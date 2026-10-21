# Module Isolation Pilot verification

**Verdict**: FAIL
**Profile**: standard
**Diff range**: be1c84c..40058c5
**Round**: 5 - scoped
**Verifier**: independent sub-agent (author != verifier)

Verificado em `40058c5` (branch `helterdias/module-isolation-pilot`, já no remoto: `git branch -r --contains 40058c5` dá `origin/helterdias/module-isolation-pilot`), na pasta principal, com a pilha de dev no ar (`db`, `redis`, `rabbitmq` e `api`, pelo `docker compose ps`). A rodada 4 verificou `be1c84c..91be57d` e deu PASS; o `8418897` é o relatório dela. Esta quinta rodada foi pedida por diashelter depois do limite de três rodadas.

A correção é um commit só, o `40058c5` (`fix(architecture): catch module tests calling outside helpers`), feito depois que a review do PR #30 achou que o `ModuleLayout::testsViolations` não via a chamada de um teste de módulo a um helper global de outro módulo. Pelo `git diff --name-status 8418897..40058c5`, ela toca só:

- `backend/tests/Unit/Architecture/Support/ModuleLayout.php:144-206`: o docblock, a chamada a `foreignHelpersOf` (`:154`), a varredura de chamadas (`:169-174`) e o método novo `foreignHelpersOf` (`:180-206`);
- `backend/tests/Unit/Architecture/ModuleLayersTest.php:69-70`: dois datasets novos no `fails on a deliberate violation`;
- as violações plantadas em `backend/tests/Unit/Architecture/Fixtures/Layout/`: duas novas (`module-test-calls-business-helper`, que chama `customer()`, e `module-test-calls-composition-helper`, que chama `productWithStock(1)`) e a `module-test-uses-business` reescrita sem `namespace`;
- `AGENTS.md:97`, `README.md:833` e `:893`, `docs/domain-analysis.md:4` e `:603`: a regra nova descrita, e a "Última atualização";
- `.specs/features/module-isolation-pilot/checks.md:235`, uma linha nova no Handoff, e `.specs/STATE.md:16`.

Nenhum outro código mudou: `git diff --stat 91be57d..40058c5 -- backend Makefile .github docker-compose.yml load-tests docker` lista só os 5 arquivos acima, e `git diff --stat 91be57d..40058c5 -- .specs/features/module-isolation-pilot/proofs` sai vazio. Nenhum claim de C1 a C39 mudou: a diferença do `checks.md` é a linha `:235` do Handoff, o que a regra 4 da skill permite.

O que esta rodada fez:

- **Rodou todas as provas no `HEAD`, sem carregar nenhuma.** Uma cadeia sequencial rodou as provas de comando, um único `make test-filter` com os 55 trechos do `checks.md`, o `c05`, o `c35`, o `c02` e o `c34` (dois `make test` inteiros), as 5 cópias do `c06`, o `c21`, o `c27`, o `c29` e o smoke de carga do `c36`.
- **Injetou 5 falhas** nas superfícies que a correção tocou ou criou, num worktree descartável no scratchpad da sessão. Repetiu uma delas no `8418897` para conferir a frase do commit sobre a fixture antiga.
- **Recalculou** as linhas de Coverage cuja autoridade a correção tocou: as regras com violação plantada, os donos dos helpers proibidos (conjunto novo) e os documentos.
- **Não commitou nada.**

O veredito é FAIL por um único achado, que a correção não criou mas cuja superfície ela tocou:

1. **Um mutante sobrevive ao C33 (R5-M5).** O `AGENTS.md:97` passa a dizer "Hoje têm testes no módulo o Identity, o Messaging, o Shared e o Catalog" (sem o Payment), e o `c33-docs.sh` sai com 0. O predicado `'the 5 modules with tests'` de `proofs/c33-docs.sh:16` aceita qualquer parágrafo (`split('\n\n')`) que tenha os 5 nomes e a palavra `test`. No `AGENTS.md`, o único parágrafo que casa é a lista inteira das Convenções, que começa na `:56` e cita o Payment em muitas outras linhas. Rodei o mesmo predicado sobre os 4 documentos, com o Payment tirado da frase que lista os 5 módulos (`AGENTS.md:97`, `README.md:833`, `docs/domain-analysis.md:603` e `.design/module-isolation.md:5` e `:146`). Ele continua verdadeiro nos 4. O item "os 5 módulos com testes nelas" do C33 (AC 24) não tem, portanto, prova que o afirme em nenhum documento. O predicado é o mesmo desde `25d37f4`.

A correção em si se sustenta:

- os mutantes R5-M1 a R5-M4 morreram, cada um numa asserção diferente;
- o R5-M3 (o ramo do nome de classe desligado) sobrevive no `8418897`, com `Tests: 36 passed`, e morre no `40058c5`. Isso confirma a frase do commit: a fixture `module-test-uses-business` antiga era acusada só pelo `namespace Tests\...`. A evidência do C8 nas rodadas 1 a 4 (`ModuleLayersTest.php:61` com `:67`) não provava o ramo que o claim do C8 descreve. A correção fechou essa lacuna.

## Binding sources

verified at 40058c5 (o slice Module Tests, nos estados que a correção passou a cobrar, e a regra de documentação do `AGENTS.md` para os 3 documentos que a correção tocou); carried from 91be57d, 27be65e e 8af75b1 (as outras, marcadas). A correção não tocou a interface (nenhuma rota, status ou corpo), então o passo 1 não roda de novo para o slice Payment ↔ Ordering.

| Source | Opened | Contradiction | Uncovered |
| --- | --- | --- | --- |
| `.design/module-isolation.md`, Key decisions 1 a 7 (carried from 8af75b1) | yes. O design não mudou desde a rodada 4 (`git diff 8418897..40058c5 -- .design` vazio) | none | - |
| `.design/module-isolation.md`, slice Module Tests (verified at 40058c5) | yes. Tabela de estados em `.design/module-isolation.md:136-144`, relida no `HEAD` | none. A correção passa a cobrar na suíte de arquitetura dois estados que antes só a remoção (C6) cobrava: "nenhuma factory, model ou helper de outro módulo" (`:139`, e a Key decision 6 na `:99`) e "Helper de teste de um módulo: só os testes do próprio módulo o usam" (`:144`). A `:144` é lida como "nenhum teste de outro módulo o usa": a suíte de composição chama os helpers dos módulos (`backend/tests/Pest.php:27`), e o design a define como a suíte que roda com todos os módulos (`:100`, `:141`). A regra nova não acusa a composição: ela só varre `app-modules/<módulo>/tests` (`ModuleLayersTest.php:33`) | - |
| `.design/module-isolation.md`, slice Payment ↔ Ordering (carried from 91be57d) | yes, na rodada 4 (`:154-162`). A correção não tocou rota, use case nem o design | none | - |
| `.specs/STATE.md`, AD-001, AD-002 e AD-003 (carried from 91be57d) | yes. As linhas `:7-9` não mudaram (`git diff 8418897..40058c5 -- .specs/STATE.md` toca só a `:16`) | none | - |
| `AGENTS.md`, regra "sempre atualizar a documentação" (verified at 40058c5 para `AGENTS.md`, `README.md` e `docs/domain-analysis.md`; carried from 91be57d para `.design/module-isolation.md`) | yes | none. A regra nova está descrita onde a regra antiga estava: `AGENTS.md:97`, `README.md:833`, `README.md:893` (a linha do `ModuleLayersTest` na tabela de testes) e `docs/domain-analysis.md:603`. A "Última atualização" de `docs/domain-analysis.md:4` ganhou a data 21/10/2026, como o `AGENTS.md` manda. O que os documentos dizem bate com o código: o `tests/Pest.php` e o `tests/Helpers.php` de outro módulo de negócio (`ModuleLayout.php:189-192`), e a frase "funções globais que o `tests/Pest.php` carrega" bate com `backend/tests/Pest.php:28-30`. O código também lê o `tests/Helpers.php` do `Integration`, que não existe hoje, então não há diferença observável | - |

## Checks

verified at 40058c5: todas as provas rodaram de novo no `HEAD`, e as citações foram relidas nele. Os arquivos de teste e de código, fora o `ModuleLayersTest.php`, o `ModuleLayout.php` e as fixtures, não mudaram desde `27be65e`, então as linhas são as mesmas da rodada 4 (relidas com `sed -n` no `HEAD`). No `ModuleLayersTest.php`, as linhas `:1-68` não mudaram; o dataset `inbound-event-outside-consumers` desceu de `:69` para `:71`.

Prova nomeada, uma só execução para todos os nomes do `checks.md`:

```
make test-filter FILTER="<os 55 trechos das provas make test-filter do checks.md, unidos por |>"
```

- **Resultado:** exit 0, `Tests: 1034 passed (2349 assertions)`, `Duration: 58.16s`. A rodada 4 teve 1032; a diferença são os 2 datasets novos.
- **Saída:**
  - cada um dos 54 nomes de teste aparece com `✓`, de 1 a 13 vezes conforme os datasets (`fails on a deliberate violation` 9 vezes, eram 7);
  - o `ModuleBoundariesTest` aparece como `PASS Tests\Unit\Architecture\ModuleBoundariesTest`;
  - nenhuma linha `⨯`.
- **Árvore:** cada um dos 54 nomes foi achado por `rg -n -F "('<nome>'"` em `backend/tests` e `backend/app-modules/*/tests`, fora das `Fixtures`, nas mesmas linhas de início da rodada 4 (por exemplo, `ModuleLayersTest.php:32` e `:60`, `PaymentTest.php:42`, `ConsumerRedeliveryTest.php:50`). O `ModuleBoundariesTest` é o arquivo `backend/tests/Unit/Architecture/ModuleBoundariesTest.php`. Dois nomes existem no módulo e na composição: `answers 404 to the payment of an unknown order` (`PaymentTest.php:63` e `CustomerRecordRoutesTest.php:84`) e `rejects payment for orders that are not awaiting payment` (`PaymentTest.php:165` e `PaymentFlowTest.php:23`).

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | os 27 arquivos estão no módulo e nenhum em `backend/tests` | `bash proofs/c01-moved-files.sh` em 40058c5, exit 0 ("PASS: 27 files in their modules, none left in backend/tests") | `proofs/c01-moved-files.sh:35` - `[ -f "$new" ]`; `:36` - `[ -e "$old" ] && fail`; `:40` - `[ "$count" -eq 27 ]` | PASS |
| C2 | um `make test` roda o caso do Payment e o de composição | `bash proofs/c02-make-test.sh` em 40058c5, exit 0: `make test exit 0: Tests: 2256 passed (9266 assertions)`, 190,34 s | `proofs/c02-make-test.sh:6` - `[ "$code" -eq 0 ]`; `:7-10` - as 4 expressões, achadas em `evidence/c02-make-test.txt:1290-1291` (`PASS Appmodules\payment\tests\Unit\Gateways\FakePaymentGatewayTest` e `✓ it approves the fake approved card`) e `:1759-1760` (`PASS Tests\Feature\DeliveryEstimateTest` e `✓ it shows the delivery estimate after the payment is approved`). Os casos estão em `backend/app-modules/payment/tests/Unit/Gateways/FakePaymentGatewayTest.php:13` e `backend/tests/Feature/DeliveryEstimateTest.php:5` | PASS |
| C3 | Feature do módulo com `TestCase` e `RefreshDatabase`; Unit sem | lote nomeado em 40058c5, exit 0 | `backend/app-modules/shared/tests/Feature/FeatureHarnessTest.php:12-14` - `toBeInstanceOf(TestCase::class)`, `class_uses_recursive($this))->toHaveKey(RefreshDatabase::class)` e `Schema::hasTable('migrations')`; `backend/app-modules/shared/tests/Unit/UnitHarnessTest.php:11-12` - `->not->toHaveKey(RefreshDatabase::class)` | PASS |
| C4 | `Makefile` e CI iguais aos da base | `bash proofs/c04-unchanged-commands.sh` em 40058c5, exit 0 | `proofs/c04-unchanged-commands.sh:5` - `git diff --quiet "$BASE" HEAD -- "$file"` para o `Makefile` e o `.github/workflows/ci.yml` | PASS |
| C5 | o filtro roda exatamente 1 teste, o do Payment em `tests/Unit` | `bash proofs/c05-test-filter.sh` em 40058c5, exit 0, `Tests: 1 passed (4 assertions)` | `proofs/c05-test-filter.sh:8` - `grep -qE 'Tests: +1 passed'`; `:9` - `PASS +Appmodules\\payment\\tests\\Unit\\Gateways\\FakePaymentGatewayTest` | PASS |
| C6 | cada um dos 5 módulos migra e passa sozinho | `bash proofs/c06-module-alone.sh` em 40058c5 para identity, messaging, shared, catalog e payment, todos com exit 0, cada cópia saída de "detached HEAD 40058c5" | `proofs/c06-module-alone.sh:15` - o `migrate:fresh` da cópia sai com 0; `:22` - `[ "$status" -eq 0 ]`; `:24` - recusa `skipped`, `incomplete`, `risky` e `todo`. Por módulo (`evidence/c06-<módulo>.txt`):<br>- identity: 55 passed;<br>- messaging: 60;<br>- shared: 6;<br>- catalog: 12;<br>- payment: 49 | PASS |
| C7 | `Modules\<Módulo>\Tests\` no pacote, nunca na raiz | lote nomeado em 40058c5: 11 + 1 casos | `backend/tests/Unit/Architecture/ModulePackagesTest.php:86-87` - `$expected["Modules\\{$module}\\Tests\\"] = 'tests/'` só quando a pasta existe; `:95` - `->toBe($expected)`; `:106` - `->toBe([])` para a raiz | PASS |
| C8 | teste de módulo que nomeia outro módulo de negócio é acusado | lote nomeado em 40058c5: 11 linhas mais as 9 fixtures | `backend/tests/Unit/Architecture/ModuleLayersTest.php:33` - `expect(ModuleLayout::testsViolations(ModuleLayout::packageOf($module).'/tests', $module))->toBe([])` sobre os 11 módulos; `:61` - `->not->toBe([])` com a fixture `module-test-uses-business` (`:67`), que agora só tem o `use Modules\Ordering\Persistence\Models\Order;` (`Fixtures/Layout/module-test-uses-business/tests/Feature/Offender.php:3`) e nenhum `namespace`. O R5-M3 mostra que essa fixture, sozinha, prova o ramo do claim (`ModuleLayout.php:158`). Além do claim, as `:69-70` plantam as chamadas a `customer()` (Identity) e a `productWithStock(1)` (composição) | PASS |
| C9 | as 628 declarações da base continuam pelo nome | `bash proofs/c09-test-count.sh` em 40058c5, exit 0: 628 na base e 657 no `HEAD`; 2 renomeadas e 1 substituída | `proofs/c09-test-count.sh:17` - `[ "$base" -eq 628 ]`; `:31` - `[ -z "$lost" ]`; `:32` - `[ "$now" -ge 627 ]`. Das 657, 3 são as declarações `it(` das fixtures `Offender.php`, que nunca rodam (`evidence/c09-names.head:382-384`); sem elas são 654, como na rodada 4 (nota de precisão em Coverage) | PASS |
| C10 | identidade de teste só com o id, UUID v7, no guard `customer` | lote nomeado em 40058c5 | `backend/app-modules/shared/tests/Feature/TestIdentityTest.php:13-16` - `RecordId::isCanonical($first)`, `$first[14]` igual a `'7'`, `auth('customer')->id()` igual a `$first` e `GenericUser` | PASS |
| C11 | 11 pacotes, manifesto do `integration`, tipo `COMPOSITION` | lote nomeado em 40058c5 | `ModulePackagesTest.php:16-17` - `->toBe([... 'integration' ...])`, os 11 pacotes; `:96` - `['extra']['laravel']['providers']` igual ao provider do pacote; `:110-113` - `COMPOSITION toBe(['Integration'])` e fora de `BUSINESS` e `INFRASTRUCTURE` | PASS |
| C12 | nada de `Modules\Ordering` no payment, nem de `Modules\Payment` no ordering | lote nomeado em 40058c5 | `ModuleLayersTest.php:37` - `expect(ModuleLayout::isolationViolations(base_path('app-modules'), 'Payment'))->toBe([])` | PASS |
| C13 | só o `Integration` nomeia o Payment; a regra falha nas duas direções | lote nomeado em 40058c5, 1 + 3 casos | `ModuleLayersTest.php:41` - `->toBe([])`; `:45` - `!== [])->toBe($violates)`, com as linhas `:47-49`:<br>- `payment-names-ordering`: true;<br>- `ordering-names-payment`: true;<br>- `integration-names-both`: false | PASS |
| C14 | `202`, a forma do corpo, a linha `approved` e o evento | lote nomeado em 40058c5: 4 casos do módulo e 1 de composição | `backend/app-modules/payment/tests/Feature/PaymentTest.php:46-50` - `assertAccepted()`, `data.order_id`, `data.status` `approved`, `data.amount_cents` 12345 e a mensagem; `:60` - `toEqualCanonicalizing(['id', 'order_id', 'status', 'amount_cents'])`; `:81-83` - 1 linha `approved` de 12345; `:90-91` - o payload `['order_id' => $order->id]`; `backend/tests/Feature/DeliveryEstimateTest.php:21` - `->assertAccepted()` | PASS |
| C15 | falha no outbox não deixa aprovado nem mensagem | lote nomeado em 40058c5 | `PaymentTest.php:100-103` - `assertStatus(500)`, nenhuma linha `approved` e `outbox_messages` vazia; `backend/tests/Feature/Outbox/OrderEventsOutboxTest.php:48-50` | PASS |
| C16 | `404` para pedido desconhecido ou de outro cliente, e a porta é consultada com o cliente | lote nomeado em 40058c5: 3 casos do módulo e 4 de composição | `PaymentTest.php:69-72` - `assertNotFound()`, sem linha e sem cobrança; `:231-236` - `assertApiError(..., 'NOT_FOUND', 'Recurso não encontrado.')`, sem linha e sem cobrança; `:246` - `expect($port->asked)->toBe([[auth('customer')->id(), $order->id]])`; `backend/tests/Feature/Account/CustomerRecordRoutesTest.php:79-81` e `:89-92` | PASS |
| C17 | `409` e a tabela de 4 combinações | lote nomeado em 40058c5 | `PaymentTest.php:171-175` e `:185-189` - `assertConflict()`, a mensagem e nenhuma cobrança; `backend/app-modules/payment/tests/Unit/Services/PaymentServiceTest.php:19-26` - só "aguarda e sem aprovado" passa; `backend/tests/Feature/PaymentFlowTest.php:29-35` | PASS |
| C18 | aprovação concorrente perde com `409` | lote nomeado em 40058c5 | `PaymentTest.php:203-207` - `assertConflict()`, 1 `approved` e nenhum `payment.payment_approved`; `backend/app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:68-72` | PASS |
| C19 | `402`, a linha `declined` fica, e o pedido real continua `awaiting_payment` | lote nomeado em 40058c5 | `PaymentTest.php:111-119` - `assertStatus(402)`, `PAYMENT_DECLINED`, 1 linha `declined` e nenhum `payment.payment_approved`; `PayOrderUseCaseTest.php:49-56`; `PaymentFlowTest.php:17-20` - `$order->fresh()->status` igual a `AwaitingPayment` | PASS |
| C20 | `422` antes da porta, para qualquer id, com ou sem porta disponível | lote nomeado em 40058c5 | `PaymentTest.php:143-148` - `assertUnprocessable()` e `VALIDATION_FAILED`; `:255-259` - `expect($port->asked)->toBe([])`; `:267-270` - `expect($port->calls)->toBe(0)` | PASS |
| C21 | `503` com o corpo exato, sem cobrar nem gravar; o mesmo numa cópia sem o Ordering | lote nomeado em 40058c5 (o caso do módulo) e `bash proofs/c21-payment-without-ordering.sh` em 40058c5, exit 0, `Tests: 1 passed (7 assertions)`, cópia saída de "detached HEAD 40058c5" | `PaymentTest.php:280-284` - `assertApiError($response->assertStatus(503), 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.')`, `$port->calls` 1, sem cobrança e com `payments` e `outbox_messages` vazias; `proofs/c21-WithoutOrderingTest.php:12-13` - o contrato do Ordering não existe e `app(OrdersToPay::class)` é `UnavailableOrdersToPay`; `:20-23` | PASS |
| C22 | `payment.payment_approved` é lido pela classe do Ordering e move o pedido | lote nomeado em 40058c5 | `backend/tests/Feature/Messaging/ConsumerNamesTest.php:29-31` com `:34`; `:43` - `eventOf('payment.payment_approved')` igual a `InboundPaymentApproved::class`; `backend/tests/Feature/Messaging/ConsumerRedeliveryTest.php:57` | PASS |
| C23 | a reentrega deixa `payment_approved` e 1 `ordering.order_paid` | lote nomeado em 40058c5 | `ConsumerRedeliveryTest.php:52-59` - envelope da classe produtora entregue duas vezes, status `PaymentApproved` e `outboxMessages('ordering.order_paid')->toHaveCount(1)` | PASS |
| C24 | leitora e produtora concordam; um par divergente é acusado | lote nomeado em 40058c5 | `backend/tests/Unit/Architecture/OrderEventPayloadTest.php:159` - os 5 nomes; `:165-166` - `disagreements(...)->toBe([])`; `:171` - a fixture `DisagreeingReader` dá `['payload']` | PASS |
| C25 | `InboundEvents` só em `Consumers` e `Providers` do Ordering, no código de `app-modules/*/src` | lote nomeado em 40058c5 | `ModuleLayersTest.php:53` - `expect(ModuleLayout::inboundEventViolations(base_path('app-modules')))->toBe([])`; `:61` com `:71` - a fixture `inbound-event-outside-consumers` (era a `:69`) | PASS |
| C26 | sem chave em `payments.order_id`; as outras 5 iguais | lote nomeado em 40058c5, 5 + 1 casos | `backend/tests/Feature/Database/ModuleMigrationsForeignKeyTest.php:27-31` - `expect(foreignKeyTarget($column))->toBe($references)`; `:37-42` - `foreignKeyTarget('payments.order_id')->toBeNull()` e 0 `FOREIGN KEY` em `payments` | PASS |
| C27 | `migrate` sobre um banco da base apaga só `payments_order_id_foreign` | `bash proofs/c27-migrate-existing.sh` em 40058c5, exit 0 (`evidence/c27.txt:121` - `2026_10_21_000001_drop_payments_order_id_foreign ... [2] Ran`; `:122` - o `PASS` da prova) | `proofs/c27-migrate-existing.sh:23` - a base cria a chave; `:27` - a chave sai depois do `migrate`; `:29` - as 5 chaves continuam; `:31` - nada mais mudou; `:35-36` - a migration nova `Ran` e nada `Pending` | PASS |
| C28 | a migration nova no Payment; 16 nomes congelados | lote nomeado em 40058c5, 13 + 1 casos | `backend/tests/Unit/Architecture/ModuleMigrationsTest.php:46-47` - o arquivo no pacote dono e não na raiz; `:55` - `expect(migrationNamesIn($paths))->toBe($frozen)` | PASS |
| C29 | sem o Payment: `migrate:fresh`, `404` e as pastas de testes restantes | `bash proofs/c29-without-payment.sh` em 40058c5, exit 0, 90 s. A cópia saiu de "detached HEAD 40058c5" (`evidence/c29.txt:1`); `migrate:fresh: ok` (`:86`); pastas catalog, identity, messaging e shared (`:87`); `Tests: 135 passed (574 assertions)` (`:283`) | `proofs/c29-without-payment.sh:7` - o `migrate:fresh` da cópia sai com 0; `:15` - `[ "$status" -eq 0 ]`; `:16` - o arquivo de prova rodou; `proofs/c29-WithoutPaymentTest.php:16` - `interface_exists('Modules\Payment\Contracts\OrdersToPay')` falso; `:20` - `->assertNotFound()` para um id desconhecido. Na saída: `evidence/c29.txt:274` - `✓ it answers 404 to the payment route without the payment module inst…` | PASS |
| C30 | adaptador do `Integration`: tabela de 4 status e `null` | lote nomeado em 40058c5: 1 + 4 + 1 casos | `backend/tests/Feature/Integration/OrdersToPayAdapterTest.php:14` - `toBeInstanceOf(OrdersToPayFromOrdering::class)`; `:21-22` - `toEqual(new OrderToPay(...))` sobre `:24-27`; `:34-35` - `toBeNull()` para outro cliente e para id desconhecido | PASS |
| C31 | a forma da porta e do value object | lote nomeado em 40058c5 | `backend/tests/Unit/Architecture/IdTypesTest.php:70-75` - interface, o método `findForPayment`, os parâmetros `[['customerId', 'string'], ['orderId', 'string']]`, o retorno `'?'.OrderToPay::class` e o construtor | PASS |
| C32 | `401` sem sessão; `429` na 21ª tentativa | lote nomeado em 40058c5 | `PaymentTest.php:292` - `assertUnauthorized()` com `UNAUTHENTICATED`; `:360-364` - 20 respostas sem `429` e a 21ª com `assertTooManyRequests()` | PASS |
| C33 | os 4 documentos descrevem o piloto, sem afirmação velha nenhuma | `bash proofs/c33-docs.sh` em 40058c5, exit 0 ("PASS: the four documents describe the pilot, with no stale claim and no broken link") | `proofs/c33-docs.sh:9-18` - os 8 itens exigidos em cada documento; `:19-27` - a lista `stale`; `:39-41` - a heurística de `payments` restrito; `:43-49` - os links relativos. No `HEAD`, os 4 documentos listam os 5 módulos com testes (`AGENTS.md:97`, `README.md:833`, `docs/domain-analysis.md:603`, `.design/module-isolation.md:146`), lidos por mim. O claim vale no `HEAD`; a prova, porém, não acusa a perda de um dos 5 (R5-M5 sobrevive, e o membro fica sem prova em Coverage) | PASS |
| C34 | `make test` sai com 0, nada pulado | `bash proofs/c34-suite.sh` em 40058c5, exit 0: `Tests: 2256 passed (9266 assertions)`, 179,66 s. O `make test` do C2, no mesmo `HEAD`, também saiu com 0 | `proofs/c34-suite.sh:7` - `[ "$code" -eq 0 ]`; `:8` - recusa `failed`, `skipped`, `incomplete`, `risky` e `todo`; `:9` - o teste de log rodou (2 linhas `✓ it logs each payment attempt without the card token` em `evidence/c34-make-test.txt`) | PASS |
| C35 | `make pint` sem arquivo a corrigir | `bash proofs/c35-pint.sh` em 40058c5, exit 0 (`PASS ... 497 files`; eram 495, mais as 2 fixtures novas) | `proofs/c35-pint.sh:6` - `[ "$code" -eq 0 ]`, sobre o `pint --test` do `Makefile` | PASS |
| C36 | o smoke de carga passa | `bash proofs/c36-load-smoke.sh` em 40058c5, exit 0, 66 s: `backoffice`, `checkout` e `storefront` com ✓, 3 iterações completas (`evidence/c36.txt`) | `proofs/c36-load-smoke.sh:10` - `[ "$code" -eq 0 ]`; `:11` - `diff` de `c36-cache.before` e `c36-cache.after` (iguais: o `bootstrap/cache` não mudou) | PASS |
| C37 | o `Integration` só usa fachadas; o `ModuleBoundariesTest` fica verde | lote nomeado em 40058c5, com o `ModuleBoundariesTest` inteiro (`PASS Tests\Unit\Architecture\ModuleBoundariesTest`) | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:598` - `expect($named)->not->toBeEmpty()`; `:601-602` - cada classe nomeada está em `facadeOf($module)` ou `vocabularyOf($module)` | PASS |
| C38 | a `PaymentFactory` não cria pedido | lote nomeado em 40058c5 | `PaymentTest.php:329-332` - 1 linha em `payments`, 0 em `orders`, `RecordId::isCanonical($payment->order_id)` e `$payment->order_id[14]` igual a `'7'` | PASS |
| C39 | sem o Payment, um pedido criado em `placed` e levado a `awaiting_payment` pelo `ordering.order_placed` entregue pelo relay continua assim depois da tentativa (`404`) e de o relay esvaziar o outbox de novo, sem `payment.payment_approved` | `bash proofs/c29-without-payment.sh` em 40058c5, exit 0 (a mesma execução do C29). `evidence/c29.txt:275` - `✓ it keeps an order awaiting payment without the payment module insta…` | `proofs/c29-without-payment.sh:17` - `grep -qE '✓ it keeps an order awaiting payment without the'` ou `fail`; `proofs/c29-WithoutPaymentTest.php:25` - o pedido nasce `OrderStatus::Placed`; `:29-31` - `record(new OrderPlaced($order->id))`, `relayUntilIdle()` e `expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)`; `:35` - `->assertNotFound()` para o pedido do próprio cliente; `:36` - o relay de novo; `:38-39` - status `AwaitingPayment` e `DB::table('outbox_messages')->where('name', 'payment.payment_approved')->count()` igual a 0. O texto foi aprovado por diashelter em 2026-10-21 (`checks.md:234`) | PASS |

## Coverage

verified at 40058c5 (as 3 linhas marcadas); carried from 27be65e e 91be57d (as outras, marcadas).

Três linhas foram recalculadas em 40058c5, porque a correção tocou a autoridade delas:

- `regras de arquitetura com violação proposital`: a correção acrescentou membros;
- `donos dos helpers proibidos a um teste de módulo`: conjunto novo, que a correção criou;
- `documentos (4)`: a correção tocou 3 dos 4.

As provas de `módulos com testes que sobem sozinhos`, `configurações de remoção` e `chaves estrangeiras` rodaram de novo em 40058c5, e a fonte delas não mudou. As outras linhas vêm de 27be65e ou de 91be57d, marcadas: nenhum arquivo de `src`, rota, migration ou teste de comportamento mudou desde então, e a fonte delas também não.

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| arquivos movidos (27) (carried from 27be65e; a prova rodou de novo em 40058c5) | a tabela *Arquivos movidos* do plano (`plan.md:151-157`) | os 27, por `c01-moved-files.sh:35-36` | - |
| módulos com testes que sobem sozinhos (5) (carried from 27be65e; as 5 provas rodaram de novo em 40058c5) | o AC 4 e `ls backend/app-modules/*/tests` | identity 55 · messaging 60 · shared 6 · catalog 12 · payment 49, todos por C6 | - |
| tipos de teste num módulo (2) (carried from 27be65e) | `backend/phpunit.xml:8-15` e `backend/tests/Pest.php:17-25` | Feature `FeatureHarnessTest.php:13` · Unit `UnitHarnessTest.php:12` (C3) | - |
| status de `POST /api/orders/{order}/payment` (8) (carried from 27be65e) | o `Surface` do plano (`plan.md:87`), a rota, o use case e o form request | 202 `PaymentTest.php:46` · 401 `:292` · 402 `:111` · 404 `:231` · 409 `:171` · 422 `:143` · 429 `:364` · 503 `:280` | - |
| regra "pode ser pago" (4) (carried from 27be65e) | `PaymentService.php:21` | as 4 linhas de `PaymentServiceTest.php:23-26` (C17) | - |
| `OrderStatus` -> `awaitingPayment` (4) (carried from 27be65e) | o enum `OrderStatus.php:9-12` | `OrdersToPayAdapterTest.php:24-27` (C30) | - |
| status que recusam o pagamento com o Ordering real (3 + já aprovado) (carried from 27be65e) | o AC 13 e o enum | `PaymentFlowTest.php:35` e `PaymentTest.php:179-189` | - |
| respostas do adaptador (3) (carried from 27be65e) | `OrdersToPayFromOrdering.php:22-29` | `OrdersToPayAdapterTest.php:21-22`, `:34` e `:35` (C30) | - |
| chaves estrangeiras entre módulos (5 + `payments.order_id` sem chave) (carried from 27be65e; o C27 rodou de novo em 40058c5) | o `pg_constraint` de um banco da base (`evidence/c27.txt`) e as migrations do `HEAD` | `ModuleMigrationsForeignKeyTest.php:27-31` e `:37-42`; `c27-migrate-existing.sh:27` e `:29` | - |
| nomes de evento inscritos (5) (carried from 27be65e) | o `EventSubscriptionRegistry` em execução | `OrderEventPayloadTest.php:159`, laço `:161-167` (C24) | - |
| portas da Landing (7) (carried from 27be65e) | a tabela `Landing` do plano (`plan.md:93-101`) | 1 C11, C37 · 2 C31 · 3 C22, C24, C25 · 4 C21 · 5 C26, C27, C28 · 6 C1, C3, C7 · 7 C10 | - |
| entidades da Relations (1) (carried from 27be65e) | o `erDiagram` do plano (`plan.md:76-79`) | `orders` 1:N `payments` sem chave: C26, C27 | - |
| configurações de remoção (3) (carried from 27be65e; as provas rodaram de novo em 40058c5) | os critérios 4, 16 e 22 (`plan.md:116`, `:137` e `:143`) | C6 (5 execuções) · C21 · C29 | - |
| regras de arquitetura com violação proposital (verified at 40058c5): 4 regras, e a dos testes de módulo tem agora 4 ramos | os ACs 6, 10, 19 e 20 e os ramos de `ModuleLayout::testsViolations` (`ModuleLayout.php:157-174`), lidos no `HEAD` | testes de módulo (AC 6):<br>- nome de classe de outro módulo de negócio (`:158`): fixture `ModuleLayersTest.php:67`, morta pelo R5-M3;<br>- apoio da suíte da raiz (`:163-167`): `:68` (carried from 27be65e; a correção não tocou o ramo nem a fixture);<br>- helper de outro módulo de negócio (`:172-174`, dono em `:191-192`): `:69`, morta pelo R5-M1;<br>- helper do `tests/Pest.php` (`:189`): `:70`, morta pelo R5-M2;<br>- o lado que passa, nas pastas reais (`:33`), com o próprio módulo fora da lista (`:191`): morto pelo R5-M4.<br>isolamento nas duas direções (AC 10) `:47-48` · uso das classes leitoras (AC 20) `:71` · leitora divergente da produtora (AC 19) `OrderEventPayloadTest.php:171` (carried from 27be65e) | - |
| donos dos helpers proibidos a um teste de módulo (verified at 40058c5): 2 tipos | `ModuleLayout.php:189-192` e os arquivos que existem no `HEAD` (`backend/tests/Pest.php` e `app-modules/{identity,messaging,payment,shared}/tests/Helpers.php`) | o `tests/Pest.php` (a suíte de composição): `ModuleLayersTest.php:70` (`productWithStock`) · o `tests/Helpers.php` de outro módulo de negócio: `:69` (`customer`, do Identity). Os helpers do `Shared` e do `Messaging` ficam permitidos, porque o laço só lê `BUSINESS` e `COMPOSITION` (`:191`); o `Integration` não tem `tests/Helpers.php` | - |
| estados do slice Payment ↔ Ordering (7 linhas, 11 membros) (carried from 91be57d; as provas rodaram de novo em 40058c5) | a tabela de estados do design (`.design/module-isolation.md:154-162`) | aprovado: pagamento e evento na mesma transação C14, C15 · o Ordering lê com a classe dele e marca pago C22, C23 · `202` C14 · desconhecido ou de outro cliente `404` C16 · não aguarda `409` C17 · recusado `402` e linha gravada C19 · Ordering ausente `503`, nada gravado C21 · Payment ausente: rota some `404` C29, pedidos ficam em `awaiting_payment` C39 e pastas restantes passam C29 · suíte do Payment sem o Ordering C6 payment e C21 | - |
| documentos (4) (verified at 40058c5) | o AC 24 e o claim do C33; `AGENTS.md`, `README.md` e `docs/domain-analysis.md` relidos no `HEAD`; o `.design/module-isolation.md` não mudou desde 91be57d | `AGENTS.md` C33 · `README.md` C33 · `docs/domain-analysis.md` C33 · `.design/module-isolation.md` C33, para 7 dos 8 itens de `c33-docs.sh:10-17` | o item "os 5 módulos com testes nelas" nos 4 documentos: o predicado de `c33-docs.sh:16` continua verdadeiro com o Payment tirado da frase que lista os 5 (`AGENTS.md:97`, rodado: R5-M5; `README.md:833`, `docs/domain-analysis.md:603` e `.design/module-isolation.md:5` e `:146`, pelo mesmo predicado em Python) |
| startup: ligação de `OrdersToPay` (3 montagens) (carried from 27be65e; o C21 rodou de novo em 40058c5) | `IntegrationServiceProvider.php:23-27`, `bootstrap/cache/packages.php:110` e `integration/composer.json:17` | harness `OrdersToPayAdapterTest.php:14` · app sem o Ordering `c21-WithoutOrderingTest.php:13` · app de dev e produção pela mesma descoberta | - |
| startup: inscrição da classe leitora (2 montagens) (carried from 27be65e) | `OrderingServiceProvider.php:20` e `:51` | harness `ConsumerNamesTest.php:43` · workers pelo mesmo `boot()` | - |

A linha do `checks.md` (`checks.md:170`) conta 9 membros no slice e não cita "o Ordering o lê com a classe dele e marca o pedido como pago". O membro tem prova (C22 e C23), então isso não é lacuna (carried from 1083151).

Linhas `Swept` que resolvem para algo que já existia (carried from 27be65e), relidas no `HEAD`:

- **authorization:** `auth:customer` em `backend/app-modules/payment/routes/api.php:9`.
- **concurrency:** o índice único parcial em `create_payments_table.php:39`.
- **observability:** o teste de log em `PaymentTest.php:335-355`, com 2 linhas `✓` no `make test` do C34 em 40058c5.
- **data lifecycle:** o `down()` vazio em `2026_10_21_000001_drop_payments_order_id_foreign.php:24`.
- **Observable, limite:** `throttle:20,1,payment` em `routes/api.php:10`.

Lacunas de precisão:

- **C33, "os 5 módulos com testes" (verified at 40058c5; bloqueia):**
  - **O quê:** o predicado de `proofs/c33-docs.sh:16` é `any(all(m in p for m in (...5 nomes...)) and 'test' in p for p in t.split('\n\n'))`. Ele aceita qualquer parágrafo com os 5 nomes e a palavra `test`.
  - **Onde falha:**
    - no `AGENTS.md`, o único parágrafo que casa é a lista inteira das Convenções (da `:56` à `:98`);
    - no `README.md`, casam 4 parágrafos (`:109`, `:833`, `:837` e `:1020`);
    - na `docs/domain-analysis.md`, 3 (`:569`, `:596` e `:710`);
    - no `.design/module-isolation.md`, 2 (`:3` e `:146`).
  - **Consequência:** tirar um módulo da frase que lista os 5 não muda o resultado em nenhum dos 4 documentos.
  - **O que fecharia:** exigir a lista explícita, por exemplo uma expressão como `Identity, (o )?Messaging, (o )?Shared, (o )?Catalog e (o )?Payment` por documento.
- **Alcance da regra nova (verified at 40058c5; não reprova):**
  - **O que a regra lê:** o `foreignHelpersOf` só conhece as funções declaradas na coluna 0 do `tests/Pest.php` e do `tests/Helpers.php` de cada outro módulo (`ModuleLayout.php:189-198`).
  - **O que fica de fora:** uma função global declarada dentro de um arquivo de teste. Exemplos: `payOrder()` em `app-modules/payment/tests/Feature/PaymentTest.php:32` e `assertNoLegacyMoneyKeys()` em `backend/tests/Feature/MoneyInCentsTest.php:18`. Um teste de outro módulo que chamasse uma delas passaria pelo `ModuleLayersTest` e pelo `make test`, que carrega todos os arquivos, e só a cópia de remoção do C6 o pegaria.
  - **Por que não reprova:** os documentos descrevem a regra com esse alcance exato ("do `tests/Pest.php` ou do `tests/Helpers.php`"), e nenhum check a afirma.
  - **Como foi conferido:** leitura do código, sem mutante.
- **C9, a contagem do `HEAD` (verified at 40058c5; não reprova):** o `c09-test-count.sh:13` conta as declarações `it(` de qualquer `.php` de `backend/tests`, então as 3 das fixtures `Offender.php` entram nas 657. Elas nunca rodam, porque o `phpunit.xml` só carrega `*Test.php`. Sem elas, o total é 654, ainda acima de 627, e nenhum nome da base depende delas.
- **C33, o padrão da rodada 3 (carried from 91be57d):** o padrão de `proofs/c33-docs.sh:27` só casa quando "são" vem logo antes de "29". A linha `checks.md:233` diz "em qualquer redação".
- **C24 e AC 19, C16 (carried from 8af75b1):** a fixture prova só divergência de chaves; a de versão foi provada pelo mutante 4 da rodada 1. O caso do módulo para id desconhecido afirma só o status; o corpo vem do `CustomerRecordRoutesTest.php:89`.

## Test policy rows

carried from 27be65e: a linha classifica arquivos de código (`OrdersToPayFromOrdering.php`, `PaymentService.php` e `IntegrationServiceProvider.php`), e a correção não tocou nenhum deles. O `ModuleLayout.php`, que ela mudou, é apoio da suíte de arquitetura e nenhuma linha o classifica. As provas de cada arquivo classificado rodaram de novo em 40058c5 (C30, C17, C21 e C29).

O `checks.md` tem a seção `Test policy` com "Não se aplica", sem linhas, então vale a convenção do repositório.

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| "Não se aplica": decisão nova provada na própria camada, um caso por linha da tabela de decisão (convenção do repositório) | `integration/src/Payment/OrdersToPayFromOrdering.php`, `payment/src/Core/Services/PaymentService.php` e `integration/src/Providers/IntegrationServiceProvider.php` | o adaptador com 4 status (C30), a regra com 4 combinações (C17) e os 3 ramos do provider (C30, C21 e C29) | yes |

## Faults injected

### Verified at 40058c5: as superfícies que a correção tocou ou criou

A correção criou o ramo das chamadas a helpers, com os dois lados da lista de donos, mudou a fixture que prova o ramo do nome de classe e tocou 3 documentos guardados pelo C33. Uma falha por superfície de asserção, 5 no total, o teto do procedimento:

| Superfície | Asserção | Mutante |
| --- | --- | --- |
| a fixture do helper de outro módulo de negócio | `ModuleLayersTest.php:61` com `:69` | R5-M1 |
| a fixture do helper da composição | `:61` com `:70` | R5-M2 |
| a fixture do nome de classe | `:61` com `:67` | R5-M3 |
| o lado que passa, nas pastas reais | `:33` | R5-M4 |
| o documento | `c33-docs.sh:16` | R5-M5 |

**Como os mutantes foram aplicados:**

- **Base:** o `git status --porcelain` da pasta principal foi gravado antes de tudo, e estava vazio. A lista de worktrees também foi gravada (11 entradas), e `git stash list` estava vazio.
- **Worktree:** `git worktree add --detach <scratchpad>/wt-r5 HEAD`, com o `backend/vendor` e o `backend/.env` copiados da pasta principal. O `make test-filter` do worktree roda num container `api` avulso, sobre o banco e o vhost `ecommerce_testing_wt_r5`.
- **Controle:**
  - `make test-filter FILTER="ModuleLayersTest"` sem mudança: exit 0, `Tests: 38 passed (38 assertions)`;
  - o `c33-docs.sh` do worktree sem mudança: exit 0.
- **Aplicação:** cada mutante é uma troca de texto conferida (`assert s.count(old) == 1`). Rodou a prova mais estreita e depois `git checkout --` do arquivo, com o porcelain do worktree vazio entre um mutante e o outro.
- **R5-M3 em `8418897`:** o mesmo mutante rodou com o worktree em `git checkout --detach 8418897`. Depois o worktree voltou para `40058c5`.

**Depois:**

- **Banco:** o `make test-db-drop` do worktree apagou o banco e o vhost `ecommerce_testing_wt_r5`.
- **Worktree:** `git worktree remove --force` o removeu, e `git worktree list` voltou idêntico às 11 entradas de antes.
- **Pasta principal:** o porcelain dela continuou vazio depois dos mutantes, e `git stash list` também; nenhum `git stash` foi usado. Os mutantes rodaram antes da cadeia de provas, e nenhum chegou à pasta principal.
- **Cadeia de provas:** ela rodou depois, na pasta principal. As cópias de remoção (`.claude/worktrees/removal-*` e `proof-c27-base`) foram apagadas pelas próprias provas: `git worktree list` continuou com as 11 entradas, nenhum banco `removal`, `wt_` ou `proof` ficou no PostgreSQL, e os vhosts do RabbitMQ são `/` e `ecommerce_testing`.
- **Arquivos regravados:** o porcelain final difere da base só pelos 19 arquivos de `evidence/` que as provas regravaram, além deste relatório. Nenhum foi revertido. São eles:
  - `c02-make-test.txt` e `c02.txt`;
  - `c05-test-filter.txt`;
  - `c06-catalog.txt`, `c06-identity.txt`, `c06-messaging.txt`, `c06-payment.txt` e `c06-shared.txt`;
  - `c09-names.head` e `c09.txt`;
  - `c21.txt`, `c27.txt` e `c29.txt`;
  - `c34-make-test.txt` e `c34.txt`;
  - `c35-pint.txt` e `c35.txt`;
  - `c36-load-smoke.txt` e `c36.txt`.

| Mutation | Location | Killed |
| --- | --- | --- |
| R5-M1: os `tests/Helpers.php` dos outros módulos saem da lista de donos (`foreach (array_diff([...self::BUSINESS, ...self::COMPOSITION], [$module]) as $owner)` -> `foreach ([] as $owner)`) | `backend/tests/Unit/Architecture/Support/ModuleLayout.php:191`, prova `ModuleLayersTest.php:61` com `:69` (C8) | yes. Exit 2, `Tests: 1 failed, 37 passed`: `⨯ it fails on a deliberate violation with dataset "a module test call…"`, o 7º dataset (`:69`), com "Expecting [] not to be []" em `ModuleLayersTest.php:61` |
| R5-M2: o `tests/Pest.php` sai da lista de donos (`$files = [base_path('tests/Pest.php') => 'the composition suite'];` -> `$files = [];`) | `ModuleLayout.php:189`, prova `ModuleLayersTest.php:61` com `:70` (C8) | yes. Exit 2, `Tests: 1 failed, 37 passed`: o 8º dataset (`:70`) com `⨯` |
| R5-M3: o ramo do nome de classe desligado (`if ($referencedModule !== $module && ...)` -> `if (false && $referencedModule !== $module && ...)`) | `ModuleLayout.php:158`, prova `ModuleLayersTest.php:61` com `:67` (C8) | yes. Exit 2, `Tests: 1 failed, 37 passed`: o 5º dataset (`:67`, `module-test-uses-business`) com `⨯`. O mesmo mutante no `8418897` sai com 0, `Tests: 36 passed (36 assertions)`: a fixture antiga era acusada só pelo `namespace Tests\...` (ramo da `:163`), como diz o commit |
| R5-M4: o próprio módulo entra na lista de donos (`array_diff([...self::BUSINESS, ...self::COMPOSITION], [$module])` -> `[...self::BUSINESS, ...self::COMPOSITION]`) | `ModuleLayout.php:191`, prova `ModuleLayersTest.php:33` (C8, "passa com as pastas de hoje") | yes. Exit 2, `Tests: 2 failed, 36 passed`: `⨯ it keeps the tests of every module inside the module` para o Identity e o Payment, com `'Feature/Auth/AuthTest.php -> customer() of Identity'` entre as violações novas |
| R5-M5: a frase que lista os módulos com testes perde o Payment ("Hoje têm testes no módulo o Identity, o Messaging, o Shared, o Catalog e o Payment;" -> "Hoje têm testes no módulo o Identity, o Messaging, o Shared e o Catalog;") | `AGENTS.md:97`, a linha que a correção tocou, prova `proofs/c33-docs.sh:16` (C33) | no. Exit 0: "PASS: the four documents describe the pilot, with no stale claim and no broken link". O predicado casa a lista inteira das Convenções (`AGENTS.md:56-98`), que cita o Payment em outras linhas. O documento passa a dizer que 4 módulos têm testes, e a prova não acusa |

### Carried from 91be57d: as superfícies da correção da rodada 3

Nem os arquivos nem as provas dessas superfícies mudaram desde `91be57d`: `git diff 91be57d..40058c5` não toca `docs/domain-analysis.md:498`, `proofs/` nem `app-modules/ordering`.

| Mutation | Location | Killed |
| --- | --- | --- |
| M1: volta a frase da rodada 3, "e depois da HEL-6 são 29 e 28" | `docs/domain-analysis.md:498`, prova `proofs/c33-docs.sh:27` | yes |
| M2: o consumidor `MarkOrderAsAwaitingPayment` também roda o `MarkOrderAsPaidUseCase` | `backend/app-modules/ordering/src/Core/Consumers/MarkOrderAsAwaitingPayment.php:19-20`, prova `proofs/c29-without-payment.sh` (C39) | yes |
| M3: o Ordering responde `POST orders/{order}/payment`, marca o pedido como pago e devolve `404` | `backend/app-modules/ordering/routes/api.php:16-20`, prova `proofs/c29-without-payment.sh` (C39) | yes |

### Carried from 1083151: os padrões da rodada 2 e a heurística em `proofs/c33-docs.sh`

| Mutation | Location | Killed |
| --- | --- | --- |
| F1: volta a linha da rodada 2, "restrito na exclusão (como `payments`)" | `docs/domain-analysis.md:588`, prova `proofs/c33-docs.sh:25` e `:39-41` | yes |
| F2: volta a contagem antiga do README, "Hoje são 29 tipos públicos" | `README.md:1057`, prova `proofs/c33-docs.sh:25` | yes |
| F3: paráfrase que só a heurística alcança, "restrito na exclusão (como a de `payments`)" | `docs/domain-analysis.md:588`, prova `proofs/c33-docs.sh:39-41` | yes |

### Carried from 8af75b1: a lista `stale` da rodada 1 em `proofs/c33-docs.sh`

| Mutation | Location | Killed |
| --- | --- | --- |
| volta a linha aberta "Tentativas de pagamento presas ao pedido por `restrictOnDelete`" | `docs/domain-analysis.md:733`, prova `proofs/c33-docs.sh:23` | yes |
| volta "Payment → Ordering: PayableOrders" | `docs/domain-analysis.md:91-92`, prova `proofs/c33-docs.sh:21` | yes |
| volta "o Payment lê o pedido pelo contrato `PayableOrders`" | `docs/domain-analysis.md:108`, prova `proofs/c33-docs.sh:21` | yes |
| volta "✅ O Payment lê o pedido pelo contrato `PayableOrders`" | `docs/domain-analysis.md:346`, prova `proofs/c33-docs.sh:21` | yes |
| volta "`findForPayment(orderId): ?OrderForPayment`, para o Payment" | `docs/domain-analysis.md:140`, prova `proofs/c33-docs.sh:22` | yes |

### Carried from 27be65e: o código de `src`, que nenhuma correção tocou

| Mutation | Location | Killed |
| --- | --- | --- |
| status do `503` trocado por `500` | `backend/app-modules/shared/src/Exceptions/ServiceUnavailableException.php:26` | yes. `answers 503 when the order port is unavailable` falhou (C21) |
| tradução do adaptador: `=== OrderStatus::AwaitingPayment` -> `!== OrderStatus::Placed` | `backend/app-modules/integration/src/Payment/OrdersToPayFromOrdering.php:28` | yes. `translates each order status into awaiting payment` com 2 falhas (C30) |
| regra "pode ser pago" sem o `$hasApprovedPayment` | `backend/app-modules/payment/src/Core/Services/PaymentService.php:21` | yes. `decides whether an order can be paid` com 1 falha (C17) |
| versão da classe leitora `1` -> `2` | `backend/app-modules/ordering/src/Core/InboundEvents/PaymentApproved.php:26` | yes. Falharam C24 e C23 |
| detector de isolamento sem a direção de entrada | `backend/tests/Unit/Architecture/Support/ModuleLayout.php:228` (era a `:192`; a linha desceu com o método novo, e o código é o mesmo) | yes. `fails on a deliberate isolation violation` com 1 falha (C13) |

## Gate

`make test` (`proofs/c34-suite.sh` no `HEAD` `40058c5`): 2256 passed, 0 failed, 0 skipped (9266 assertions, 179,66 s). O `make test` do C2, no mesmo `HEAD`, deu o mesmo: 2256 passed (9266 assertions, 190,34 s). Os 2 testes a mais que na rodada 4 são os datasets novos de `ModuleLayersTest.php:69-70`. Nesta rodada, as duas execuções inteiras passaram, e o `ConsumeEventsCommandTest` levou 14,67 s e 11,05 s.

`python3 .claude/skills/tlc-spec-lean/scripts/validate_verification.py module-isolation-pilot --root /Users/helterdias/Documents/POC/laravel-module-ecomerce`: exit 1, com a saída:

```
  ERROR module-isolation-pilot: verdict is FAIL - route the ranked gaps back as fixes, then re-verify

validate_verification: 1 error(s), 0 warning(s) across [module-isolation-pilot]
```

O único erro é o próprio veredito FAIL. O script para de ler as tabelas depois de um FAIL, então o exit 1 é o esperado para este relatório, e não um defeito dele. Rodei também uma cópia do relatório com o veredito trocado para PASS, num diretório temporário, para conferir que o gate lê as linhas. Ele acusa exatamente as duas linhas que bloqueiam: o `Unproven` de `documentos (4)` e o `Killed` `no` do R5-M5. As 39 linhas de check, a de Test policy e as de Binding sources não acusam nada.

### Lacunas, em ordem

1. **Mutante sobrevivente no C33 (bloqueia; verified at 40058c5).** R5-M5, `AGENTS.md:97` contra `proofs/c33-docs.sh:16`. O item "os 5 módulos com testes nelas" do C33 (AC 24) não tem prova que o afirme em nenhum dos 4 documentos (Coverage, linha `documentos (4)`).
   - **Por que bloqueia:** o veredito segue a regra do procedimento para um mutante sobrevivente e um membro sem prova.
   - **De onde vem:** a lacuna é anterior à correção (o predicado é o mesmo desde `25d37f4`). A correção editou exatamente as linhas que guardam essa frase em 3 documentos, e a próxima slice vai editá-la de novo.
   - **O que fecharia:**
     - trocar o predicado da `:16` por um que exija a lista explícita em cada documento, sem mudar o claim do C33;
     - rodar de novo o R5-M5.

### Achados que não reprovam

1. **A evidência do C8 nas rodadas 1 a 4 era mais fraca que o relatado (verified at 40058c5; resolvido pela correção).**
   - **O quê:** o R5-M3 sobrevive no `8418897`, com `Tests: 36 passed`. A fixture `module-test-uses-business` antiga era acusada pelo ramo do apoio da raiz, por causa do próprio `namespace Tests\...`, e não pelo ramo do nome de classe que o claim do C8 descreve.
   - **O que mudou:** no `40058c5`, a fixture prova o ramo sozinha, e o mutante morre.
   - **Como ler os relatórios anteriores:** as rodadas 1 a 4 citaram `ModuleLayersTest.php:61` com `:67` como evidência suficiente. Nenhuma delas injetou falha nesse ramo.
2. **Registro de handoff desatualizado (verified at 40058c5).** O `.specs/STATE.md:14` diz "enviada ao remoto até `91be57d`", e a `:16` diz que a correção está "no commit local" e que "falta o ok de diashelter para o `git push`". Mas `origin/helterdias/module-isolation-pilot` já está em `40058c5`. O texto era verdadeiro quando o commit foi feito; é um registro fora dos checks.
3. **Alcance da regra nova e contagem do C9 (verified at 40058c5):**
   - o `foreignHelpersOf` não conhece funções globais declaradas dentro de arquivos de teste;
   - o C9 conta as 3 declarações `it(` das fixtures novas;
   - os detalhes estão em Coverage, "Lacunas de precisão".
4. **O padrão do C33 da rodada 3 (carried from 91be57d):** continua casando só "são 29 ...".
