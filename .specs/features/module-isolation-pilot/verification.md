# Module Isolation Pilot verification

**Verdict**: PASS
**Profile**: standard
**Diff range**: be1c84c..91be57d
**Round**: 4 - scoped
**Verifier**: independent sub-agent (author != verifier)

Verificado em `91be57d` (branch `helterdias/module-isolation-pilot`), na pasta principal, com a pilha de dev no ar (`db`, `redis`, `rabbitmq` e `api`). A rodada 3 verificou `be1c84c..1083151` e deu FAIL por um achado bloqueante: a contagem de `docs/domain-analysis.md:498`. Ela também deixou duas lacunas de precisão, a do C39 e a da heurística do C33. O limite de três rodadas foi atingido na rodada 3; esta quarta rodada foi pedida por diashelter.

A correção é o commit `83bd3e8`. O `683d32e` é o relatório da rodada 3, e o `91be57d` é o handoff do `STATE.md`. Pelo `git diff --stat 1083151..91be57d`, a correção toca só:

- `docs/domain-analysis.md:498`, a contagem do vocabulário;
- `checks.md:125`, o texto do C39, e `checks.md:233`, uma linha nova no Handoff;
- `proofs/c33-docs.sh:26-27`, um padrão novo;
- `proofs/c29-WithoutPaymentTest.php:5`, `:7` e `:25-31`: o pedido do C39 nasce `placed` e chega a `awaiting_payment` pelo consumidor do próprio Ordering;
- `.specs/STATE.md:9` (AD-003) e `:13-19` (Handoff);
- `evidence/c05-test-filter.txt`, `evidence/c29.txt`, `evidence/c34-make-test.txt` e o relatório.

Nenhum código mudou: `git diff --stat 27be65e..91be57d -- backend Makefile .github docker-compose.yml load-tests docker` sai vazio, e `git diff --stat 1083151..91be57d -- AGENTS.md README.md .design` também.

O que esta rodada fez:

- **Rodou todas as provas no `HEAD`, sem carregar nenhuma.** Isso inclui `c06` ×5, `c21`, `c27` e `c36`, que a rodada 3 trouxe de `27be65e`: o procedimento manda as provas rodarem por inteiro no `HEAD` novo. Uma cadeia sequencial rodou as provas de comando, um único `make test-filter` com os 55 nomes do `checks.md`, o `c02` e o `c34` (dois `make test` inteiros) e depois as cópias de remoção e o smoke de carga.
- **Recalculou o vocabulário público** num worktree descartável, com as funções `vocabularyOf` e `classesOutsideVocabularyOf` do próprio `ModuleBoundariesTest`.
- **Injetou 3 falhas** nas superfícies que a correção tocou, em worktrees descartáveis no scratchpad da sessão.
- **Não commitou nada.** As provas da cadeia regravaram 13 arquivos de `evidence/` (lista em Faults injected); nenhum foi revertido.

Os achados da rodada 3 estão resolvidos:

1. **A contagem (bloqueava).** A `docs/domain-analysis.md:498` diz agora "depois da HEL-6 eram 29 e 28, e com o `OrderToPay` da isolação dos módulos são 30 e 28". O recálculo em `91be57d` dá 30 públicos e 28 privados: Catalog 6/10, Customers 0/2, Identity 5/4, Inventory 4/4, Ordering 10/7, Payment 5/1, Backoffice e Fulfillment 0/0, com o `Modules\Payment\Core\ValueObjects\OrderToPay` público. O `README.md:1057` diz o mesmo. A frase antiga é pega pela prova (M1).
2. **A lacuna do C39.** O mutante que passava pelo C39 na rodada 3 (o F4: o consumidor `MarkOrderAsAwaitingPayment` também marca o pedido como pago) agora é morto pela prova do próprio C39, em `c29-WithoutPaymentTest.php:31` (M2).

O veredito é PASS, com quatro achados que não reprovam (detalhes em Gate):

1. **Um teste instável.** Uma das três execuções inteiras do `make test` no `HEAD` falhou no `ConsumeEventsCommandTest.php:55`, um teste do broker que nem a feature nem a correção mudaram.
2. **A regra 4 da skill.** O texto do C39 foi reescrito depois da aprovação, sem registro de que diashelter aprovou.
3. **Uma lacuna de precisão.** O padrão novo do C33 não pega a contagem "em qualquer redação", como a linha `checks.md:233` afirma.
4. **Um registro impreciso.** O `STATE.md:14` resume as três rodadas como "só achados de documentação".

## Binding sources

verified at 91be57d (o slice Payment ↔ Ordering, o `STATE.md` e a regra de documentação do `AGENTS.md` para o arquivo que a correção tocou); carried from 8af75b1, 27be65e e 1083151 (as outras, marcadas)

| Source | Opened | Contradiction | Uncovered |
| --- | --- | --- | --- |
| `.design/module-isolation.md`, Key decisions 1 a 7 (carried from 8af75b1) | yes. O design não mudou desde a rodada 3 (`git diff 1083151..91be57d -- .design` vazio) | none | - |
| `.design/module-isolation.md`, slice Module Tests (carried from 27be65e) | yes | none | - |
| `.design/module-isolation.md`, slice Payment ↔ Ordering (verified at 91be57d) | yes. Tabela de estados em `.design/module-isolation.md:154-162`, relida no `HEAD` | none. A linha "Payment ausente" (`:161`) diz que "os pedidos ficam em `awaiting_payment`". O texto novo do C39 (`checks.md:125`) e a prova nova levam o pedido a esse estado pelo consumidor real do Ordering (`c29-WithoutPaymentTest.php:29-31`) antes da tentativa, o que fica mais perto da linha do design que a factory de antes | - |
| `.specs/STATE.md`, AD-001 e AD-002 (carried from 27be65e) | yes. As linhas `:7-8` não mudaram | none | - |
| `.specs/STATE.md`, AD-003 (verified at 91be57d) | yes. `.specs/STATE.md:9`, nova nesta correção | none. A porta no `Contracts` do módulo isolado, a ligação pelo `Integration`, o `503` sem o dono, a classe leitora em `Core/InboundEvents`, a ausência de chave estrangeira e os testes no módulo batem com as Key decisions 1 a 7 e com C12, C13, C21, C22, C26 e C6. O "passam com só ele, o `Shared` e o `Messaging`" repete o `plan.md:28`: as cópias do C6 mantêm o `Integration` (`evidence/c06-payment.txt`: "packages left: integration messaging payment shared"), e o AC 4 só remove módulos de negócio | - |
| `AGENTS.md`, regra "sempre atualizar a documentação" (verified at 91be57d para a `docs/domain-analysis.md`; carried from 1083151 para `AGENTS.md`, `README.md` e `.design/module-isolation.md`, que não mudaram) | yes | none. A `docs/domain-analysis.md:498` bate com o recálculo (30 e 28). A varredura das contagens da `docs/domain-analysis.md` no `HEAD` (tipos, interfaces, pacotes, módulos, chaves, testes e arquivos) achou só registros datados de outras features (`:569`, `:573`, `:579`, `:596`, `:622`, `:633`, `:705`, `:712`) e as contagens do piloto que a rodada 3 já conferiu (`:4`, `:602-604`, `:711`) | - |

## Checks

verified at 91be57d: todas as provas rodaram de novo no `HEAD`, e as citações foram relidas nele. Os arquivos de teste e de código não mudaram desde `27be65e`, então as linhas são as mesmas da rodada 3; as das provas que a correção tocou (`c29-WithoutPaymentTest.php` e `c33-docs.sh`) foram atualizadas.

Prova nomeada, uma só execução para todos os nomes do `checks.md`:

```
make test-filter FILTER="<os 55 trechos das provas make test-filter do checks.md, unidos por |>"
```

- **Resultado:** exit 0, `Tests: 1032 passed (2347 assertions)`, `Duration: 54.85s`.
- **Saída:**
  - cada um dos 54 nomes de teste aparece com `✓`, de 1 a 13 vezes conforme os datasets;
  - o `ModuleBoundariesTest` aparece como `PASS Tests\Unit\Architecture\ModuleBoundariesTest`;
  - o nome truncado é `✓ it reads every subscribed event from the payload its producer write…`, logo depois de `PASS Tests\Unit\Architecture\OrderEventPayloadTest`;
  - nenhuma linha `⨯`.
- **Árvore:** cada nome foi achado por `rg -n -F "it('<nome>"` em `backend/tests` e `backend/app-modules/*/tests`, nas mesmas linhas de início da rodada 3. Dois nomes existem no módulo e na composição: `answers 404 to the payment of an unknown order` (`PaymentTest.php:63` e `CustomerRecordRoutesTest.php:84`) e `rejects payment for orders that are not awaiting payment` (`PaymentTest.php:165` e `PaymentFlowTest.php:23`).

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | os 27 arquivos estão no módulo e nenhum em `backend/tests` | `bash proofs/c01-moved-files.sh` em 91be57d, exit 0 ("27 files in their modules, none left in backend/tests") | `proofs/c01-moved-files.sh:35` - `[ -f "$new" ]`; `:36` - `[ -e "$old" ] && fail`; `:40` - `[ "$count" -eq 27 ]` | PASS |
| C2 | um `make test` roda o caso do Payment e o de composição | `bash proofs/c02-make-test.sh` em 91be57d, exit 0: `make test exit 0: Tests: 2254 passed (9264 assertions)` | `proofs/c02-make-test.sh:6` - `[ "$code" -eq 0 ]`; `:7-10` - as 4 expressões, achadas em `evidence/c02-make-test.txt:1288-1289` (`PASS Appmodules\payment\tests\Unit\Gateways\FakePaymentGatewayTest` e `✓ it approves the fake approved card`) e `:1757-1758` (`PASS Tests\Feature\DeliveryEstimateTest` e `✓ it shows the delivery estimate after the payment is approved`). Os casos estão em `backend/app-modules/payment/tests/Unit/Gateways/FakePaymentGatewayTest.php:13` e `backend/tests/Feature/DeliveryEstimateTest.php:5` | PASS |
| C3 | Feature do módulo com `TestCase` e `RefreshDatabase`; Unit sem | lote nomeado em 91be57d, exit 0 | `backend/app-modules/shared/tests/Feature/FeatureHarnessTest.php:12-14` - `toBeInstanceOf(TestCase::class)`, `class_uses_recursive($this))->toHaveKey(RefreshDatabase::class)` e `Schema::hasTable('migrations')`; `backend/app-modules/shared/tests/Unit/UnitHarnessTest.php:11-12` - `->not->toHaveKey(RefreshDatabase::class)` | PASS |
| C4 | `Makefile` e CI iguais aos da base | `bash proofs/c04-unchanged-commands.sh` em 91be57d, exit 0 | `proofs/c04-unchanged-commands.sh:5` - `git diff --quiet "$BASE" HEAD -- "$file"` para o `Makefile` e o `.github/workflows/ci.yml` | PASS |
| C5 | o filtro roda exatamente 1 teste, o do Payment em `tests/Unit` | `bash proofs/c05-test-filter.sh` em 91be57d, exit 0, `Tests: 1 passed (4 assertions)` | `proofs/c05-test-filter.sh:8` - `grep -qE 'Tests: +1 passed'`; `:9` - `PASS +Appmodules\\payment\\tests\\Unit\\Gateways\\FakePaymentGatewayTest` | PASS |
| C6 | cada um dos 5 módulos migra e passa sozinho | `bash proofs/c06-module-alone.sh` em 91be57d para identity, messaging, shared, catalog e payment, todos com exit 0, cada cópia saída de "detached HEAD 91be57d" | `proofs/c06-module-alone.sh:15` - o `migrate:fresh` da cópia sai com 0; `:22` - `[ "$status" -eq 0 ]`; `:24` - recusa `skipped`, `incomplete`, `risky` e `todo`. Por módulo (`evidence/c06-<módulo>.txt`):<br>- identity: 55 passed;<br>- messaging: 60;<br>- shared: 6;<br>- catalog: 12;<br>- payment: 49 | PASS |
| C7 | `Modules\<Módulo>\Tests\` no pacote, nunca na raiz | lote nomeado em 91be57d: 11 + 1 casos | `backend/tests/Unit/Architecture/ModulePackagesTest.php:86-87` - `$expected["Modules\\{$module}\\Tests\\"] = 'tests/'` só quando a pasta existe; `:95` - `->toBe($expected)`; `:106` - `->toBe([])` para a raiz | PASS |
| C8 | teste de módulo que nomeia outro módulo de negócio é acusado | lote nomeado em 91be57d: 11 linhas mais as 7 fixtures | `backend/tests/Unit/Architecture/ModuleLayersTest.php:33` - `expect(ModuleLayout::testsViolations(...))->toBe([])`; `:61` com `:67` - `->not->toBe([])` na fixture `module-test-uses-business` | PASS |
| C9 | as 628 declarações da base continuam pelo nome | `bash proofs/c09-test-count.sh` em 91be57d, exit 0: 628 na base e 654 no `HEAD`; 2 renomeadas e 1 substituída | `proofs/c09-test-count.sh:17` - `[ "$base" -eq 628 ]`; `:31` - `[ -z "$lost" ]`; `:32` - `[ "$now" -ge 627 ]` | PASS |
| C10 | identidade de teste só com o id, UUID v7, no guard `customer` | lote nomeado em 91be57d | `backend/app-modules/shared/tests/Feature/TestIdentityTest.php:13-16` - `RecordId::isCanonical($first)`, `$first[14]` igual a `'7'`, `auth('customer')->id()` igual a `$first` e `GenericUser` | PASS |
| C11 | 11 pacotes, manifesto do `integration`, tipo `COMPOSITION` | lote nomeado em 91be57d | `ModulePackagesTest.php:16-17` - `->toBe([... 'integration' ...])`, os 11 pacotes; `:96` - `['extra']['laravel']['providers']` igual ao provider do pacote; `:110-113` - `COMPOSITION toBe(['Integration'])` e fora de `BUSINESS` e `INFRASTRUCTURE` | PASS |
| C12 | nada de `Modules\Ordering` no payment, nem de `Modules\Payment` no ordering | lote nomeado em 91be57d | `ModuleLayersTest.php:37` - `expect(ModuleLayout::isolationViolations(base_path('app-modules'), 'Payment'))->toBe([])` | PASS |
| C13 | só o `Integration` nomeia o Payment; a regra falha nas duas direções | lote nomeado em 91be57d, 1 + 3 casos | `ModuleLayersTest.php:41` - `->toBe([])`; `:45` - `!== [])->toBe($violates)`, com as linhas `:47-49`:<br>- `payment-names-ordering`: true;<br>- `ordering-names-payment`: true;<br>- `integration-names-both`: false | PASS |
| C14 | `202`, a forma do corpo, a linha `approved` e o evento | lote nomeado em 91be57d: 4 casos do módulo e 1 de composição | `backend/app-modules/payment/tests/Feature/PaymentTest.php:46-50` - `assertAccepted()`, `data.order_id`, `data.status` `approved`, `data.amount_cents` 12345 e a mensagem; `:60` - `toEqualCanonicalizing(['id', 'order_id', 'status', 'amount_cents'])`; `:81-83` - 1 linha `approved` de 12345; `:90-91` - o payload `['order_id' => $order->id]`; `backend/tests/Feature/DeliveryEstimateTest.php:21` - `->assertAccepted()` | PASS |
| C15 | falha no outbox não deixa aprovado nem mensagem | lote nomeado em 91be57d | `PaymentTest.php:100-103` - `assertStatus(500)`, nenhuma linha `approved` e `outbox_messages` vazia; `backend/tests/Feature/Outbox/OrderEventsOutboxTest.php:48-50` | PASS |
| C16 | `404` para pedido desconhecido ou de outro cliente, e a porta é consultada com o cliente | lote nomeado em 91be57d: 3 casos do módulo e 4 de composição | `PaymentTest.php:69-72` - `assertNotFound()`; `:231-236` - `assertApiError(..., 'NOT_FOUND', 'Recurso não encontrado.')`, sem linha e sem cobrança; `:246` - `expect($port->asked)->toBe([[auth('customer')->id(), $order->id]])`; `backend/tests/Feature/Account/CustomerRecordRoutesTest.php:79-81` e `:89-92` | PASS |
| C17 | `409` e a tabela de 4 combinações | lote nomeado em 91be57d | `PaymentTest.php:171-175` e `:185-189` - `assertConflict()`, a mensagem e nenhuma cobrança; `backend/app-modules/payment/tests/Unit/Services/PaymentServiceTest.php:19-26` - só "aguarda e sem aprovado" passa; `backend/tests/Feature/PaymentFlowTest.php:29-35` | PASS |
| C18 | aprovação concorrente perde com `409` | lote nomeado em 91be57d | `PaymentTest.php:203-207` - `assertConflict()`, 1 `approved` e nenhum `payment.payment_approved`; `backend/app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:68-72` | PASS |
| C19 | `402`, a linha `declined` fica, e o pedido real continua `awaiting_payment` | lote nomeado em 91be57d | `PaymentTest.php:111-119` - `assertStatus(402)`, `PAYMENT_DECLINED`, 1 linha `declined` e nenhum `payment.payment_approved`; `PayOrderUseCaseTest.php:49-56`; `PaymentFlowTest.php:17-20` - `$order->fresh()->status` igual a `AwaitingPayment` | PASS |
| C20 | `422` antes da porta, para qualquer id, com ou sem porta disponível | lote nomeado em 91be57d | `PaymentTest.php:143-148` - `assertUnprocessable()` e `VALIDATION_FAILED`; `:255-259` - `expect($port->asked)->toBe([])`; `:267-270` - `expect($port->calls)->toBe(0)` | PASS |
| C21 | `503` com o corpo exato, sem cobrar nem gravar; o mesmo numa cópia sem o Ordering | lote nomeado em 91be57d (o caso do módulo) e `bash proofs/c21-payment-without-ordering.sh` em 91be57d, exit 0, `Tests: 1 passed (7 assertions)` | `PaymentTest.php:280-284` - `assertApiError($response->assertStatus(503), 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.')`, `$port->calls` 1, sem cobrança e com `payments` e `outbox_messages` vazias; `proofs/c21-WithoutOrderingTest.php:12-13` - o contrato do Ordering não existe e `app(OrdersToPay::class)` é `UnavailableOrdersToPay`; `:20-23` | PASS |
| C22 | `payment.payment_approved` é lido pela classe do Ordering e move o pedido | lote nomeado em 91be57d | `backend/tests/Feature/Messaging/ConsumerNamesTest.php:29-31` com `:34`; `:43` - `eventOf('payment.payment_approved')` igual a `InboundPaymentApproved::class`; `backend/tests/Feature/Messaging/ConsumerRedeliveryTest.php:57` | PASS |
| C23 | a reentrega deixa `payment_approved` e 1 `ordering.order_paid` | lote nomeado em 91be57d | `ConsumerRedeliveryTest.php:52-59` - envelope da classe produtora entregue duas vezes, status `PaymentApproved` e `outboxMessages('ordering.order_paid')->toHaveCount(1)` | PASS |
| C24 | leitora e produtora concordam; um par divergente é acusado | lote nomeado em 91be57d | `backend/tests/Unit/Architecture/OrderEventPayloadTest.php:159` - os 5 nomes; `:165-166` - `disagreements(...)->toBe([])`; `:171` - a fixture `DisagreeingReader` dá `['payload']` | PASS |
| C25 | `InboundEvents` só em `Consumers` e `Providers` do Ordering, no código de `app-modules/*/src` | lote nomeado em 91be57d | `ModuleLayersTest.php:53` - `expect(ModuleLayout::inboundEventViolations(base_path('app-modules')))->toBe([])`; `:69` - a fixture `inbound-event-outside-consumers` | PASS |
| C26 | sem chave em `payments.order_id`; as outras 5 iguais | lote nomeado em 91be57d, 5 + 1 casos | `backend/tests/Feature/Database/ModuleMigrationsForeignKeyTest.php:27-31` - `expect(foreignKeyTarget($column))->toBe($references)`; `:37-42` - `foreignKeyTarget('payments.order_id')->toBeNull()` e 0 `FOREIGN KEY` em `payments` | PASS |
| C27 | `migrate` sobre um banco da base apaga só `payments_order_id_foreign` | `bash proofs/c27-migrate-existing.sh` em 91be57d, exit 0 (`evidence/c27.txt:121` - `2026_10_21_000001_drop_payments_order_id_foreign ... [2] Ran`) | `proofs/c27-migrate-existing.sh:23` - a base cria a chave; `:27` - a chave sai depois do `migrate`; `:29` - as 5 chaves continuam; `:31` - nada mais mudou; `:35-36` - a migration nova `Ran` e nada `Pending` | PASS |
| C28 | a migration nova no Payment; 16 nomes congelados | lote nomeado em 91be57d, 13 + 1 casos | `backend/tests/Unit/Architecture/ModuleMigrationsTest.php:46-47` - o arquivo no pacote dono e não na raiz; `:55` - `expect(migrationNamesIn($paths))->toBe($frozen)` | PASS |
| C29 | sem o Payment: `migrate:fresh`, `404` e as pastas de testes restantes | `bash proofs/c29-without-payment.sh` em 91be57d, exit 0, 55 s. A cópia saiu de "detached HEAD 91be57d" (`evidence/c29.txt:1`); `migrate:fresh: ok`; pastas catalog, identity, messaging e shared; `Tests: 135 passed (574 assertions)` | `proofs/c29-without-payment.sh:7` - o `migrate:fresh` da cópia sai com 0; `:15` - `[ "$status" -eq 0 ]`; `:16` - o arquivo de prova rodou; `proofs/c29-WithoutPaymentTest.php:16` - `interface_exists('Modules\Payment\Contracts\OrdersToPay')` falso; `:20` - `->assertNotFound()` para um id desconhecido. Na saída: `evidence/c29.txt:275` - `✓ it answers 404 to the payment route without the payment module inst…` | PASS |
| C30 | adaptador do `Integration`: tabela de 4 status e `null` | lote nomeado em 91be57d: 1 + 4 + 1 casos | `backend/tests/Feature/Integration/OrdersToPayAdapterTest.php:14` - `toBeInstanceOf(OrdersToPayFromOrdering::class)`; `:21-22` - `toEqual(new OrderToPay(...))` sobre `:24-27`; `:34-35` - `toBeNull()` para outro cliente e para id desconhecido | PASS |
| C31 | a forma da porta e do value object | lote nomeado em 91be57d | `backend/tests/Unit/Architecture/IdTypesTest.php:70-75` - interface, o método `findForPayment`, os parâmetros `[['customerId', 'string'], ['orderId', 'string']]`, o retorno `'?'.OrderToPay::class` e o construtor | PASS |
| C32 | `401` sem sessão; `429` na 21ª tentativa | lote nomeado em 91be57d | `PaymentTest.php:292` - `assertUnauthorized()` com `UNAUTHENTICATED`; `:360-364` - 20 respostas sem `429` e a 21ª com `assertTooManyRequests()` | PASS |
| C33 | os 4 documentos descrevem o piloto, sem afirmação velha nenhuma | `bash proofs/c33-docs.sh` em 91be57d, exit 0 ("PASS: the four documents describe the pilot, with no stale claim and no broken link") | `proofs/c33-docs.sh:9-18` - os 8 itens exigidos em cada documento; `:19-27` - a lista `stale`, com o padrão novo da `:27`; `:39-41` - a heurística de `payments` restrito; `:43-49` - os links relativos. A `docs/domain-analysis.md:498` diz agora "... depois da HEL-6 eram 29 e 28, e com o `OrderToPay` da isolação dos módulos são 30 e 28", que o recálculo confirma. A contagem fica fora do claim do C33; o padrão novo é uma guarda a mais (M1 o mata, e a lacuna de alcance está em Gate) | PASS |
| C34 | `make test` sai com 0, nada pulado | `bash proofs/c34-suite.sh` em 91be57d, duas vezes. A 1ª saiu com 1, `Tests: 1 failed, 2253 passed (9262 assertions)`, com `⨯ it delivers a message to its consumer once and acknowledges it aft…` (31,49 s). A 2ª, depois da cadeia, saiu com 0: `Tests: 2254 passed (9264 assertions)`, 186 s. O `make test` do C2, no mesmo `HEAD`, também saiu com 0. A instabilidade está em Gate | `proofs/c34-suite.sh:7` - `[ "$code" -eq 0 ]`; `:8` - recusa `failed`, `skipped`, `incomplete`, `risky` e `todo`; `:9` - o teste de log rodou (2 linhas `✓ it logs each payment attempt without the card token` em `evidence/c34-make-test.txt`, que guarda a 2ª execução) | PASS |
| C35 | `make pint` sem arquivo a corrigir | `bash proofs/c35-pint.sh` em 91be57d, exit 0 (`PASS ... 495 files`) | `proofs/c35-pint.sh:6` - `[ "$code" -eq 0 ]`, sobre o `pint --test` do `Makefile` | PASS |
| C36 | o smoke de carga passa | `bash proofs/c36-load-smoke.sh` em 91be57d, exit 0, 79 s: `backoffice`, `checkout` e `storefront` com ✓, 3 iterações completas (`evidence/c36.txt`) | `proofs/c36-load-smoke.sh:10` - `[ "$code" -eq 0 ]`; `:11` - `diff` de `c36-cache.before` e `c36-cache.after` (iguais: o `bootstrap/cache` não mudou) | PASS |
| C37 | o `Integration` só usa fachadas; o `ModuleBoundariesTest` fica verde | lote nomeado em 91be57d, com o `ModuleBoundariesTest` inteiro (`PASS Tests\Unit\Architecture\ModuleBoundariesTest`) | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:598` - `expect($named)->not->toBeEmpty()`; `:601-602` - cada classe nomeada está em `facadeOf($module)` ou `vocabularyOf($module)` | PASS |
| C38 | a `PaymentFactory` não cria pedido | lote nomeado em 91be57d | `PaymentTest.php:329-332` - 1 linha em `payments`, 0 em `orders`, `RecordId::isCanonical($payment->order_id)` e `$payment->order_id[14]` igual a `'7'` | PASS |
| C39 | sem o Payment, um pedido criado em `placed` e levado a `awaiting_payment` pelo `ordering.order_placed` entregue pelo relay continua assim depois da tentativa (`404`) e de o relay esvaziar o outbox de novo, sem `payment.payment_approved` (texto de `83bd3e8`) | `bash proofs/c29-without-payment.sh` em 91be57d, exit 0 (a mesma execução do C29). `evidence/c29.txt:276` - `✓ it keeps an order awaiting payment without the payment module insta…` | `proofs/c29-without-payment.sh:17` - `grep -qE '✓ it keeps an order awaiting payment without the'` ou `fail`; `proofs/c29-WithoutPaymentTest.php:25` - o pedido nasce `OrderStatus::Placed`; `:29-31` - `record(new OrderPlaced($order->id))`, `relayUntilIdle()` e `expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)`; `:35` - `->assertNotFound()` para o pedido do próprio cliente; `:36` - o relay de novo; `:38-39` - status `AwaitingPayment` e `DB::table('outbox_messages')->where('name', 'payment.payment_approved')->count()` igual a 0. O M2 morre na `:31`, e o M3 na `:38`. O texto de `1083151` também vale, porque o novo o implica. A troca de texto é o achado 2 em Gate | PASS |

## Coverage

As linhas `documentos (4)` e `estados do slice Payment ↔ Ordering` foram recalculadas em 91be57d, porque a correção tocou a fonte ou a prova de cada uma. As provas de `configurações de remoção (3)` rodaram de novo em 91be57d, e a fonte delas não mudou. As outras vêm de 27be65e, marcadas: nenhum arquivo de código mudou desde então, e a fonte delas também não.

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| arquivos movidos (27) (carried from 27be65e; a prova rodou de novo em 91be57d) | a tabela *Arquivos movidos* do plano (`plan.md:151-157`) | os 27, por `c01-moved-files.sh:35-36` | - |
| módulos com testes que sobem sozinhos (5) (carried from 27be65e; as 5 provas rodaram de novo em 91be57d) | o AC 4 e `ls backend/app-modules/*/tests` | identity 55 · messaging 60 · shared 6 · catalog 12 · payment 49, todos por C6 | - |
| tipos de teste num módulo (2) (carried from 27be65e) | `backend/phpunit.xml:8-15` e `backend/tests/Pest.php:17-25` | Feature `FeatureHarnessTest.php:13` · Unit `UnitHarnessTest.php:12` (C3) | - |
| status de `POST /api/orders/{order}/payment` (8) (carried from 27be65e) | o `Surface` do plano (`plan.md:87`), a rota, o use case e o form request | 202 `PaymentTest.php:46` · 401 `:292` · 402 `:111` · 404 `:231` · 409 `:171` · 422 `:143` · 429 `:364` · 503 `:280` | - |
| regra "pode ser pago" (4) (carried from 27be65e) | `PaymentService.php:21` | as 4 linhas de `PaymentServiceTest.php:23-26` (C17) | - |
| `OrderStatus` -> `awaitingPayment` (4) (carried from 27be65e) | o enum `OrderStatus.php:9-12` | `OrdersToPayAdapterTest.php:24-27` (C30) | - |
| status que recusam o pagamento com o Ordering real (3 + já aprovado) (carried from 27be65e) | o AC 13 e o enum | `PaymentFlowTest.php:35` e `PaymentTest.php:179-189` | - |
| respostas do adaptador (3) (carried from 27be65e) | `OrdersToPayFromOrdering.php:22-29` | `OrdersToPayAdapterTest.php:21-22`, `:34` e `:35` (C30) | - |
| chaves estrangeiras entre módulos (5 + `payments.order_id` sem chave) (carried from 27be65e; o C27 rodou de novo em 91be57d) | o `pg_constraint` de um banco da base (`evidence/c27.txt`, 9 chaves na base e 8 no `HEAD`) e as migrations do `HEAD` | `ModuleMigrationsForeignKeyTest.php:27-31` e `:37-42`; `c27-migrate-existing.sh:27` e `:29` | - |
| nomes de evento inscritos (5) (carried from 27be65e) | o `EventSubscriptionRegistry` em execução | `OrderEventPayloadTest.php:159`, laço `:161-167` (C24) | - |
| portas da Landing (7) (carried from 27be65e) | a tabela `Landing` do plano (`plan.md:93-101`) | 1 C11, C37 · 2 C31 · 3 C22, C24, C25 · 4 C21 · 5 C26, C27, C28 · 6 C1, C3, C7 · 7 C10 | - |
| entidades da Relations (1) (carried from 27be65e) | o `erDiagram` do plano (`plan.md:76-79`) | `orders` 1:N `payments` sem chave: C26, C27 | - |
| configurações de remoção (3) (carried from 27be65e; as provas rodaram de novo em 91be57d) | os critérios 4, 16 e 22 (`plan.md:116`, `:137` e `:143`) | C6 (5 execuções) · C21 · C29 | - |
| regras de arquitetura com violação proposital (4) (carried from 27be65e) | os ACs 6, 10, 19 e 20 | `ModuleLayersTest.php:67` · `:47-48` · `:69` · `OrderEventPayloadTest.php:171` | - |
| estados do slice Payment ↔ Ordering (7 linhas, 11 membros) (verified at 91be57d) | a tabela de estados do design (`.design/module-isolation.md:154-162`), a fonte vinculante, e não a linha do `checks.md` | aprovado: pagamento e evento na mesma transação C14, C15 · o Ordering lê com a classe dele e marca pago C22, C23 · `202` C14 · desconhecido ou de outro cliente `404` C16 · não aguarda `409` C17 · recusado `402` e linha gravada C19 · Ordering ausente `503`, nada gravado C21 · Payment ausente: rota some `404` C29 (`c29-WithoutPaymentTest.php:20`), pedidos ficam em `awaiting_payment` C39 (`:31` e `:38`, depois do consumidor real do Ordering) e pastas restantes passam C29 (`c29-without-payment.sh:15`) · suíte do Payment sem o Ordering C6 payment e C21 | - |
| documentos (4) (verified at 91be57d) | o AC 24 e o claim do C33; a `docs/domain-analysis.md` relida no `HEAD`, e os outros 3 sem mudança desde a releitura de 1083151 | `AGENTS.md` C33 · `README.md` C33 · `docs/domain-analysis.md` C33 (a `:498` corrigida, com 30 e 28 confirmados pelo recálculo) · `.design/module-isolation.md` C33 | - |
| startup: ligação de `OrdersToPay` (3 montagens) (carried from 27be65e; o C21 rodou de novo em 91be57d) | `IntegrationServiceProvider.php:23-27`, `bootstrap/cache/packages.php:110` e `integration/composer.json:17` | harness `OrdersToPayAdapterTest.php:14` · app sem o Ordering `c21-WithoutOrderingTest.php:13` · app de dev e produção pela mesma descoberta | - |
| startup: inscrição da classe leitora (2 montagens) (carried from 27be65e) | `OrderingServiceProvider.php:20` e `:51` | harness `ConsumerNamesTest.php:43` · workers pelo mesmo `boot()` | - |

A linha do `checks.md` (`checks.md:170`) conta 9 membros no slice e não cita "o Ordering o lê com a classe dele e marca o pedido como pago". O membro tem prova (C22 e C23), então isso não é lacuna (carried from 1083151).

Linhas `Swept` que resolvem para algo que já existia (carried from 27be65e), relidas no `HEAD`:

- **authorization:** `auth:customer` em `backend/app-modules/payment/routes/api.php:9`.
- **concurrency:** o índice único parcial em `create_payments_table.php:39`.
- **observability:** o teste de log em `PaymentTest.php:335-355`, com 2 linhas `✓` no `make test` do C34 em 91be57d.
- **data lifecycle:** o `down()` vazio em `2026_10_21_000001_drop_payments_order_id_foreign.php:24`.
- **Observable, limite:** `throttle:20,1,payment` em `routes/api.php:10`.

Lacunas de precisão:

- **C39, a da rodada 3: resolvida (verified at 91be57d).** O caso cria o pedido em `placed` e grava o `ordering.order_placed`; o `relayUntilIdle()` da `:30` o entrega ao consumidor real. Assim, um consumidor do Ordering que leve o pedido adiante falha na `:31`. O M2, que é o F4 da rodada 3, prova isso.
- **C33, o padrão novo (verified at 91be57d):** o achado 3 de Gate.
- **C33, a heurística (carried from 1083151):** as palavras de isenção em `proofs/c33-docs.sh:29` e a dependência de `restri` na `:40` continuam iguais, só duas linhas abaixo. A releitura não achou nenhuma linha assim no `HEAD`.
- **C24 e AC 19, C16 (carried from 8af75b1, sem efeito no veredito):** a fixture prova só divergência de chaves, e a de versão foi provada pelo mutante 4 da rodada 1. O caso do módulo para id desconhecido afirma só o status; o corpo vem do `CustomerRecordRoutesTest.php:89`.

## Test policy rows

carried from 27be65e: a linha classifica arquivos de código, e nenhuma correção depois da rodada 1 tocou um. As provas de cada um rodaram de novo em 91be57d (C30, C17, C21 e C29).

O `checks.md` tem a seção `Test policy` com "Não se aplica", sem linhas, então vale a convenção do repositório.

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| "Não se aplica": decisão nova provada na própria camada, um caso por linha da tabela de decisão (convenção do repositório) | `integration/src/Payment/OrdersToPayFromOrdering.php`, `payment/src/Core/Services/PaymentService.php` e `integration/src/Providers/IntegrationServiceProvider.php` | o adaptador com 4 status (C30), a regra com 4 combinações (C17) e os 3 ramos do provider (C30, C21 e C29) | yes |

## Faults injected

### Verified at 91be57d: as superfícies que a correção tocou

A correção tocou duas superfícies de prova:

- o padrão novo de `proofs/c33-docs.sh:27`, com a frase de `docs/domain-analysis.md:498` que ele guarda;
- a asserção nova do C39 (`proofs/c29-WithoutPaymentTest.php:25-31`), mais a asserção final (`:38-39`), que mudou de contexto.

**Como os mutantes foram aplicados:**

- **Base:** o `git status --porcelain` da pasta principal foi gravado antes de tudo, e estava vazio. A lista de worktrees também foi gravada (11 entradas).
- **Documentos (M1):**
  - `git worktree add --detach <scratchpad>/wt-docs HEAD`;
  - controle: o `c33-docs.sh` do worktree, rodado de dentro dele sem mudança, saiu com 0;
  - o mutante troca o arquivo pelo de `1083151`, roda o `c33-docs.sh` de dentro do worktree e volta o arquivo com `git checkout`.
- **C39 (M2 e M3):**
  - `git worktree add --detach <scratchpad>/wt-c29 HEAD`, com o `backend/vendor` e o `backend/.env` copiados da pasta principal (o `lib.sh` copia o `vendor` do worktree para a cópia de remoção);
  - cada mutante virou um commit no `HEAD` destacado do worktree, nunca na branch: o M2 é `95fe6a9`, e o M3 é `e77f7a6`, ambos filhos de `91be57d`. Nenhum ref aponta para eles;
  - para cada um rodou o `c29-without-payment.sh` do próprio worktree, que monta a cópia sem o Payment a partir do `HEAD` dele. O `evidence/c29.txt` do worktree diz "Preparing worktree (detached HEAD 95fe6a9)" e "(detached HEAD e77f7a6)".

**Depois:**

- **Bancos:** a cópia `removal-without-payment` que os mutantes deixaram ao falhar foi apagada com o `make test-db-drop` dela (banco e vhost `ecommerce_testing_removal_without_payment`). O `make test-db-drop` também rodou nos dois worktrees (`ecommerce_testing_wt_docs`, usado pelo recálculo do vocabulário, e `ecommerce_testing_wt_c29`).
- **Worktrees:** os dois foram removidos, e `git worktree list` voltou idêntico às 11 entradas de antes. Nenhum banco `removal`, `wt_` ou `proof` ficou no PostgreSQL, e os vhosts do RabbitMQ são `/` e `ecommerce_testing`.
- **Stash:** `git stash list` continuou vazio, e nenhum `git stash` foi usado.
- **Porcelain:** o da pasta principal só difere da base pelos 13 arquivos de `evidence/` que as provas da cadeia regravaram ali. São eles:
  - `c02-make-test.txt` e `c05-test-filter.txt`;
  - `c06-catalog.txt`, `c06-identity.txt`, `c06-messaging.txt`, `c06-payment.txt` e `c06-shared.txt`;
  - `c21.txt`, `c27.txt` e `c29.txt`;
  - `c34-make-test.txt`, `c36-load-smoke.txt` e `c36.txt`;
  - além deste relatório.
- **Nenhum mutante chegou à pasta principal:** o `evidence/c29.txt` dela começa com "Preparing worktree (detached HEAD 91be57d)", com 135 passed, e as saídas dos mutantes ficaram nos worktrees descartáveis.

| Mutation | Location | Killed |
| --- | --- | --- |
| M1: volta a frase da rodada 3, "e depois da HEL-6 são 29 e 28" (o arquivo de `1083151`) | `docs/domain-analysis.md:498`, prova `proofs/c33-docs.sh:27` | yes. Exit 1: "docs/domain-analysis.md: stale claim 'são 29 e 28'". Só o padrão novo casa com essa frase |
| M2: o F4 da rodada 3 de novo. O consumidor `MarkOrderAsAwaitingPayment` também roda o `MarkOrderAsPaidUseCase`, e o pedido sai de `awaiting_payment` sem pagamento | `backend/app-modules/ordering/src/Core/Consumers/MarkOrderAsAwaitingPayment.php:19-20` (commit `95fe6a9`), prova `proofs/c29-without-payment.sh` (C39) | yes, pela prova do C39. Exit 1: `⨯ it keeps an order awaiting payment…` em `WithoutPaymentProofTest.php:31`, com `Delivered` em vez de `AwaitingPayment`, e `Tests: 1 failed, 134 passed`. Na rodada 3, o mesmo mutante passava pelo C39 e só o C34 o pegava |
| M3: o F5 da rodada 3 de novo. O Ordering responde `POST orders/{order}/payment`, marca o pedido como pago pelo `MarkOrderAsPaidUseCase` e devolve `404` | `backend/app-modules/ordering/routes/api.php:16-20` (rota nova, commit `e77f7a6`), prova `proofs/c29-without-payment.sh` (C39) | yes. Exit 1: `⨯ it keeps an order awaiting payment…` em `WithoutPaymentProofTest.php:38`, com `Delivered` em vez de `AwaitingPayment`, e `Tests: 1 failed, 134 passed`. O caso do C29, com id desconhecido, continuou com `✓`: a asserção final do C39 é a que pega |

### Carried from 1083151: os padrões da rodada 2 e a heurística em `proofs/c33-docs.sh`

As linhas desses padrões não mudaram, só desceram duas posições: os padrões estão na `:25`, e a heurística nas `:39-41`.

| Mutation | Location | Killed |
| --- | --- | --- |
| F1: volta a linha da rodada 2, "restrito na exclusão (como `payments`)" | `docs/domain-analysis.md:588`, prova `proofs/c33-docs.sh:25` e `:39-41` | yes |
| F2: volta a contagem antiga do README, "Hoje são 29 tipos públicos" | `README.md:1057`, prova `proofs/c33-docs.sh:25` (e agora também a `:27`) | yes |
| F3: paráfrase que só a heurística alcança, "restrito na exclusão (como a de `payments`)" | `docs/domain-analysis.md:588`, prova `proofs/c33-docs.sh:39-41` | yes |

### Carried from 8af75b1: a lista `stale` da rodada 1 em `proofs/c33-docs.sh`

Os padrões continuam iguais nas `:21-23`.

| Mutation | Location | Killed |
| --- | --- | --- |
| volta a linha aberta "Tentativas de pagamento presas ao pedido por `restrictOnDelete`" | `docs/domain-analysis.md:733`, prova `proofs/c33-docs.sh:23` | yes |
| volta "Payment → Ordering: PayableOrders" | `docs/domain-analysis.md:91-92`, prova `proofs/c33-docs.sh:21` | yes |
| volta "o Payment lê o pedido pelo contrato `PayableOrders`" | `docs/domain-analysis.md:108`, prova `proofs/c33-docs.sh:21` | yes |
| volta "✅ O Payment lê o pedido pelo contrato `PayableOrders`" | `docs/domain-analysis.md:346`, prova `proofs/c33-docs.sh:21` | yes |
| volta "`findForPayment(orderId): ?OrderForPayment`, para o Payment" | `docs/domain-analysis.md:140`, prova `proofs/c33-docs.sh:22` | yes |

### Carried from 27be65e: o código, que nenhuma correção tocou

| Mutation | Location | Killed |
| --- | --- | --- |
| status do `503` trocado por `500` | `backend/app-modules/shared/src/Exceptions/ServiceUnavailableException.php:26` | yes. `answers 503 when the order port is unavailable` falhou (C21) |
| tradução do adaptador: `=== OrderStatus::AwaitingPayment` -> `!== OrderStatus::Placed` | `backend/app-modules/integration/src/Payment/OrdersToPayFromOrdering.php:28` | yes. `translates each order status into awaiting payment` com 2 falhas (C30) |
| regra "pode ser pago" sem o `$hasApprovedPayment` | `backend/app-modules/payment/src/Core/Services/PaymentService.php:21` | yes. `decides whether an order can be paid` com 1 falha (C17) |
| versão da classe leitora `1` -> `2` | `backend/app-modules/ordering/src/Core/InboundEvents/PaymentApproved.php:26` | yes. Falharam C24 e C23 |
| detector de isolamento sem a direção de entrada | `backend/tests/Unit/Architecture/Support/ModuleLayout.php:192` | yes. `fails on a deliberate isolation violation` com 1 falha (C13) |

## Gate

`make test` (`proofs/c34-suite.sh`, 2ª execução no `HEAD` `91be57d`): 2254 passed, 0 failed, 0 skipped (9264 assertions, 186 s). Das três execuções inteiras no `HEAD` (C2, C34 1ª e C34 2ª), a 1ª do C34 saiu com 1 (achado 1 abaixo).

`python3 .claude/skills/tlc-spec-lean/scripts/validate_verification.py module-isolation-pilot --root /Users/helterdias/Documents/POC/laravel-module-ecomerce`: exit 0, com a saída `validate_verification: 0 error(s), 0 warning(s) across [module-isolation-pilot]`.

### Achados que não reprovam (em ordem)

1. **Teste instável na suíte de composição (verified at 91be57d).**
   - **O quê:** a 1ª execução do `c34-suite.sh` falhou em `backend/tests/Feature/Messaging/ConsumeEventsCommandTest.php:55`, o `awaitQueue('testing.spy_consumer', ...)` depois do `ack`. A mensagem foi "The queue testing.spy_consumer never reached the expected state: {"consumers":0,"messages_ready":0,"messages_unacknowledged":1}", lançada em `app-modules/messaging/tests/Helpers.php:190` ao fim da janela de 20 s.
   - **Por que não vem da feature:** o caso levou 15,09 s no `make test` do C2 e 11,13 s na 2ª execução do C34. Nas evidências das rodadas anteriores, levou de 10,65 s a 15,38 s (`27be65e`, `8af75b1`, `1083151` e `83bd3e8`). O corpo do teste e o `awaitQueue` são os mesmos da base `be1c84c`: só os `use` das fixtures mudaram, e o `backend/app-modules/messaging/src` não mudou. É a latência das contagens da fila quorum na API de gestão contra uma janela fixa.
   - **Onde está a saída:** `/private/tmp/claude-501/-Users-helterdias-Documents-POC-laravel-module-ecomerce/8113b562-c320-4e45-8a4c-c778ab255ae1/scratchpad/c34-run1-make-test.txt`; o `evidence/c34-make-test.txt` guarda a 2ª execução.
   - **Por que não reprova:** o C34 passou na nova execução, e o C2 no mesmo `HEAD`.
   - **O risco:** o CI pode falhar sem regressão nenhuma.
2. **O C39 foi reescrito depois da aprovação (verified at 91be57d).**
   - **O quê:** a regra 4 da skill diz que os checks ficam fixos depois de aprovados, e a regra 3 que um check errado pede "stop-and-ask", não uma edição. O `83bd3e8` reescreveu o claim do C39 (`checks.md:125`). Não há registro de aprovação de diashelter no `checks.md`, no `STATE.md` nem na mensagem do commit. O C39 já tinha entrado em `1083151` da mesma forma: em comparação, a troca do Inventory foi registrada como "Renegociado com diashelter em 2026-10-20" (`checks.md:223`).
   - **Por que não reprova:** a mudança só fortalece. O texto novo implica o de `1083151`, e a prova de agora cobre os dois.
   - **O que falta:** diashelter aprovar o texto atual do C39, ou pedir a volta ao anterior.
3. **Lacuna de precisão no padrão novo do C33 (verified at 91be57d).**
   - **O quê:** a linha `checks.md:233` diz que a prova passou a casar a contagem "em qualquer redação". O padrão de `proofs/c33-docs.sh:27` só casa quando "são" vem logo antes de "29", seguido de "tipos públicos" ou de "e 28".
   - **O que passa pela prova:** uma conferência estática, com strings e não com arquivos. Passam "depois da HEL-6 há 29 tipos públicos e 28 privados", "o vocabulário tem 29 tipos públicos e 28 privados" e "são 29 públicos e 28 privados". A rodada 3 tinha sugerido casar `29 tipos públicos` em qualquer contexto.
   - **Por que não reprova:** a contagem fica fora do claim do C33, e os documentos estão certos no `HEAD`, como o recálculo confirma.
4. **Registro impreciso no `STATE.md` (verified at 91be57d).**
   - **O quê:** o `.specs/STATE.md:14` resume as três rodadas como "só achados de documentação". Mas a rodada 2 também achou um membro do design sem prova, e o C39 nasceu dele (`checks.md:232`).
   - **Por que não reprova:** é um registro de handoff, fora dos checks.
   - **O que foi conferido:** o "make migrate já rodou" do `:16` vale. O `migrate:status` do banco de dev mostra `2026_10_21_000001_drop_payments_order_id_foreign ... [4] Ran`, e `payments_order_id_foreign` não existe nele. O `make queue-restart` não foi conferido.
