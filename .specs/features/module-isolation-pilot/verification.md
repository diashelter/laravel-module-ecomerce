# Module Isolation Pilot verification

**Verdict**: PASS
**Profile**: standard
**Diff range**: be1c84c..aa10021
**Round**: 6 - scoped
**Verifier**: independent sub-agent (author != verifier)

Verificado em `aa10021`, na pasta principal (branch `helterdias/module-isolation-pilot`), com a pilha de dev no ar: `db`, `redis`, `rabbitmq` e `api`, pelo `docker compose ps`. O `aa10021` e o `9b2c6e5` estão só no checkout local: `git branch -r --contains aa10021` sai vazio, e `origin/helterdias/module-isolation-pilot` está em `40058c5`. A rodada 5 verificou `be1c84c..40058c5` e deu FAIL. Esta sexta rodada foi pedida por diashelter depois do limite de três rodadas.

O que cada commit da correção toca (`git diff --name-status`):

- **`9b2c6e5`** (`docs(module-isolation): add the fifth verification round of the pilot`): só registros. São o relatório da rodada 5, as 19 evidências regravadas, `.specs/LESSONS.md`, `.specs/lessons.json` e as linhas `:14`, `:16` e `:18` do Handoff de `.specs/STATE.md`.
- **`aa10021`** (`test(module-isolation): match the explicit module list in the C33 proof`): três arquivos.
  - `proofs/c33-docs.sh`:
    - as linhas novas `:9-22`, com `WITH_TESTS`, `KNOWN`, a expressão `LIST` e a função `lists_of_modules_with_tests`;
    - o predicado `'the 5 modules with tests'` reescrito na `:30`;
    - o laço novo `:53-55`, que acusa toda lista vista que não seja exatamente os 5.
  - `checks.md:236`: uma linha nova no Handoff.
  - `.specs/STATE.md:16` e `:18`.

Nada além disso mudou:

- `git diff --quiet 40058c5 aa10021 -- backend AGENTS.md README.md docs .design Makefile .github docker-compose.yml load-tests docker .specs/features/module-isolation-pilot/plan.md` sai com 0;
- em `proofs/`, só o `c33-docs.sh` mudou;
- nenhum claim de C1 a C39 mudou: a diferença do `checks.md` é só a linha `:236` do Handoff, que a regra 4 da skill permite;
- a linha `Proof:` do C33 (`checks.md:138`) é a mesma. A prova ficou mais forte no ponto que a rodada 5 apontou, e nenhuma asserção foi tirada (regra 3).

O que esta rodada fez:

- **Rodou todas as provas no `HEAD`, sem carregar nenhuma.** Uma cadeia sequencial rodou:
  - `c01`, `c04`, `c09` e `c33`;
  - um único `make test-filter` com os 55 trechos do `checks.md`;
  - `c05` e `c35`;
  - `c02` e `c34`, dois `make test` inteiros;
  - as 5 cópias do `c06`, o `c21`, o `c27` e o `c29`;
  - o smoke de carga do `c36`.
- **Injetou 5 falhas** nas superfícies novas do `c33-docs.sh`, num worktree descartável no scratchpad da sessão. Uma delas é o R5-M5 recriado. Rodou também, no mesmo worktree:
  - uma varredura de 40 variantes sobre as 5 listas que a prova lê;
  - de novo, as 9 falhas carregadas que dependem do mesmo script.
- **Recalculou** a linha `documentos (4)` do Coverage, cuja prova a correção mudou, e o membro que a rodada 5 deixou sem prova.
- **Atualizou** as citações do `c33-docs.sh`. Os outros arquivos citados não mudaram desde `40058c5`, então as linhas são as mesmas.
- **Não commitou nada.**

O veredito é PASS:

1. **O R5-M5 morre.** Recriado no `AGENTS.md:97` (o Payment fora da lista), ele faz o `c33-docs.sh` do `HEAD` sair com 1, com as duas mensagens: `AGENTS.md: does not describe the 5 modules with tests` e `AGENTS.md:97: the modules with tests listed as Identity, Messaging, Shared, Catalog`. O mesmo mutante, com o `c33-docs.sh` de `40058c5` posto de volta no worktree, sai com 0. A correção fecha exatamente essa lacuna.
2. **O membro "os 5 módulos com testes nelas" tem prova nos 4 documentos.** Cada documento tem a lista exata que a `:30` exige. A varredura tirou cada um dos 5 nomes de cada uma das 5 listas e trocou ou acrescentou módulos. A prova acusou 38 das 40 variantes, entre elas todas as 25 retiradas.
3. **Os 39 checks têm prova verde no `HEAD`** e evidência localizada.

Achados que não reprovam, detalhados em Coverage:

- a linha `checks.md:236` diz mais do que a prova faz. A prova ignora uma lista que nomeie 2 ou mais módulos sem testes (`c33-docs.sh:20`). Por isso, no `.design/module-isolation.md`, que tem duas listas, uma delas pode ganhar o Ordering e o Inventory sem a prova acusar;
- o predicado novo não amarra a lista a testes.

## Binding sources

carried from 40058c5, com as origens marcadas por linha. A linha do `STATE.md` foi verified at aa10021. A correção não tocou a interface (rota, status ou corpo) nem nenhuma fonte vinculante, então o passo 1 não roda de novo:

- `git diff 40058c5..aa10021 -- .design` sai vazio;
- no `.specs/STATE.md`, os dois commits tocam só as linhas `:14`, `:16` e `:18` do Handoff.

| Source | Opened | Contradiction | Uncovered |
| --- | --- | --- | --- |
| `.design/module-isolation.md`, Key decisions 1 a 7 (carried from 8af75b1) | yes. O design não mudou desde a rodada 4 | none | - |
| `.design/module-isolation.md`, slice Module Tests (carried from 40058c5) | yes, na rodada 5 (`:136-144`). A correção não tocou o design nem o código | none | - |
| `.design/module-isolation.md`, slice Payment ↔ Ordering (carried from 91be57d) | yes, na rodada 4 (`:154-162`). A correção não tocou rota, use case nem o design | none | - |
| `.specs/STATE.md`, AD-001, AD-002 e AD-003 (verified at aa10021) | yes. As linhas `:7-9` foram relidas no `HEAD`, e `git diff 40058c5..aa10021 -- .specs/STATE.md` toca só as `:14`, `:16` e `:18` | none | - |
| `AGENTS.md`, regra "sempre atualizar a documentação" (carried from 40058c5; verified at aa10021 que a correção não a aciona) | yes | none. A correção não mudou código nem documento. `grep -rn c33 AGENTS.md README.md docs .design` sai vazio: nenhum documento cita a prova que mudou | - |

## Checks

verified at aa10021: todas as provas rodaram de novo no `HEAD`. Fora o `c33-docs.sh`, nenhum arquivo citado mudou desde `40058c5` (o `git diff --quiet` acima), então as linhas são as da rodada 5. As linhas de início dos casos foram conferidas de novo com `rg -n` no `HEAD`. As citações do `c33-docs.sh` foram atualizadas.

Prova nomeada, uma só execução para todos os nomes do `checks.md`:

```
make test-filter FILTER="<os 55 trechos das provas make test-filter do checks.md, unidos por |>"
```

- **Resultado:** exit 0, `Tests: 1034 passed (2349 assertions)`, `Duration: 53.63s`. É o mesmo total da rodada 5.
- **Saída:**
  - cada um dos 54 nomes de teste aparece com `✓`, de 1 a 13 vezes conforme os datasets (`fails on a deliberate violation` 9 vezes, `places each module migration in its owner package` 13);
  - o `ModuleBoundariesTest` aparece como `PASS Tests\Unit\Architecture\ModuleBoundariesTest`;
  - nenhuma linha `⨯` ou `FAIL`.
- **Árvore:** cada um dos 54 nomes foi achado por `rg -n -F "('<nome>'"` em `backend/tests` e `backend/app-modules/*/tests`, fora das `Fixtures`. As linhas de início são as da rodada 5, por exemplo:
  - `ModuleLayersTest.php:32` e `:60`;
  - `PaymentTest.php:42`;
  - `ConsumerRedeliveryTest.php:50`;
  - `ModuleBoundariesTest.php:585`.

  O `ModuleBoundariesTest` é o arquivo `backend/tests/Unit/Architecture/ModuleBoundariesTest.php`. Dois nomes existem no módulo e na composição:
  - `answers 404 to the payment of an unknown order`: `PaymentTest.php:63` e `CustomerRecordRoutesTest.php:84`;
  - `rejects payment for orders that are not awaiting payment`: `PaymentTest.php:165` e `PaymentFlowTest.php:23`.

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | os 27 arquivos estão no módulo e nenhum em `backend/tests` | `bash proofs/c01-moved-files.sh` em aa10021, exit 0 ("PASS: 27 files in their modules, none left in backend/tests") | `proofs/c01-moved-files.sh:35` - `[ -f "$new" ]`; `:36` - `[ -e "$old" ] && fail`; `:40` - `[ "$count" -eq 27 ]` | PASS |
| C2 | um `make test` roda o caso do Payment e o de composição | `bash proofs/c02-make-test.sh` em aa10021, exit 0: `make test exit 0: Tests: 2256 passed (9266 assertions)`, 188,86 s | `proofs/c02-make-test.sh:6` - `[ "$code" -eq 0 ]`; `:7-10` - as 4 expressões, achadas em `evidence/c02-make-test.txt:1290-1291` (`PASS Appmodules\payment\tests\Unit\Gateways\FakePaymentGatewayTest` e `✓ it approves the fake approved card`) e `:1759-1760` (`PASS Tests\Feature\DeliveryEstimateTest` e `✓ it shows the delivery estimate after the payment is approved`). Os casos estão em `backend/app-modules/payment/tests/Unit/Gateways/FakePaymentGatewayTest.php:13` e `backend/tests/Feature/DeliveryEstimateTest.php:5` | PASS |
| C3 | Feature do módulo com `TestCase` e `RefreshDatabase`; Unit sem | lote nomeado em aa10021, exit 0 | `backend/app-modules/shared/tests/Feature/FeatureHarnessTest.php:12-14` - `toBeInstanceOf(TestCase::class)`, `class_uses_recursive($this))->toHaveKey(RefreshDatabase::class)` e `Schema::hasTable('migrations')`; `backend/app-modules/shared/tests/Unit/UnitHarnessTest.php:11-12` - `->not->toHaveKey(RefreshDatabase::class)` | PASS |
| C4 | `Makefile` e CI iguais aos da base | `bash proofs/c04-unchanged-commands.sh` em aa10021, exit 0 | `proofs/c04-unchanged-commands.sh:5` - `git diff --quiet "$BASE" HEAD -- "$file"` para o `Makefile` e o `.github/workflows/ci.yml` | PASS |
| C5 | o filtro roda exatamente 1 teste, o do Payment em `tests/Unit` | `bash proofs/c05-test-filter.sh` em aa10021, exit 0, `Tests: 1 passed (4 assertions)` | `proofs/c05-test-filter.sh:8` - `grep -qE 'Tests: +1 passed'`; `:9` - `PASS +Appmodules\\payment\\tests\\Unit\\Gateways\\FakePaymentGatewayTest` | PASS |
| C6 | cada um dos 5 módulos migra e passa sozinho | `bash proofs/c06-module-alone.sh` em aa10021 para identity, messaging, shared, catalog e payment, todos com exit 0, cada cópia saída de "detached HEAD aa10021" | `proofs/c06-module-alone.sh:15` - o `migrate:fresh` da cópia sai com 0; `:22` - `[ "$status" -eq 0 ]`; `:24` - recusa `skipped`, `incomplete`, `risky` e `todo`. Por módulo (`evidence/c06-<módulo>.txt`):<br>- identity: 55 passed (202 assertions);<br>- messaging: 60 (322);<br>- shared: 6 (18);<br>- catalog: 12 (23);<br>- payment: 49 (192) | PASS |
| C7 | `Modules\<Módulo>\Tests\` no pacote, nunca na raiz | lote nomeado em aa10021: 11 + 1 casos | `backend/tests/Unit/Architecture/ModulePackagesTest.php:86-87` - `$expected["Modules\\{$module}\\Tests\\"] = 'tests/'` só quando a pasta existe; `:95` - `->toBe($expected)`; `:106` - `->toBe([])` para a raiz | PASS |
| C8 | teste de módulo que nomeia outro módulo de negócio é acusado | lote nomeado em aa10021: 11 linhas mais as 9 fixtures | `backend/tests/Unit/Architecture/ModuleLayersTest.php:33` - `expect(ModuleLayout::testsViolations(ModuleLayout::packageOf($module).'/tests', $module))->toBe([])` sobre os 11 módulos; `:61` - `->not->toBe([])` com a fixture `module-test-uses-business` (`:67`), que só tem o `use Modules\Ordering\Persistence\Models\Order;` e nenhum `namespace`. O R5-M3 da rodada 5 (carried from 40058c5) mostra que essa fixture, sozinha, prova o ramo do nome de classe (`ModuleLayout.php:158`). Além do claim, as `:69-70` plantam as chamadas a `customer()` e a `productWithStock(1)` | PASS |
| C9 | as 628 declarações da base continuam pelo nome | `bash proofs/c09-test-count.sh` em aa10021, exit 0: 628 na base e 657 no `HEAD`; 2 renomeadas e 1 substituída | `proofs/c09-test-count.sh:17` - `[ "$base" -eq 628 ]`; `:31` - `[ -z "$lost" ]`; `:32` - `[ "$now" -ge 627 ]`. Das 657, 3 são as declarações `it(` das fixtures `Offender.php`, que nunca rodam; sem elas são 654 (nota de precisão em Coverage) | PASS |
| C10 | identidade de teste só com o id, UUID v7, no guard `customer` | lote nomeado em aa10021 | `backend/app-modules/shared/tests/Feature/TestIdentityTest.php:13-16` - `RecordId::isCanonical($first)`, `$first[14]` igual a `'7'`, `auth('customer')->id()` igual a `$first` e `GenericUser` | PASS |
| C11 | 11 pacotes, manifesto do `integration`, tipo `COMPOSITION` | lote nomeado em aa10021 | `ModulePackagesTest.php:16-17` - `->toBe([... 'integration' ...])`, os 11 pacotes; `:96` - `['extra']['laravel']['providers']` igual ao provider do pacote; `:110-113` - `COMPOSITION toBe(['Integration'])`, fora de `BUSINESS` e de `INFRASTRUCTURE` | PASS |
| C12 | nada de `Modules\Ordering` no payment, nem de `Modules\Payment` no ordering | lote nomeado em aa10021 | `ModuleLayersTest.php:37` - `expect(ModuleLayout::isolationViolations(base_path('app-modules'), 'Payment'))->toBe([])` | PASS |
| C13 | só o `Integration` nomeia o Payment; a regra falha nas duas direções | lote nomeado em aa10021, 1 + 3 casos | `ModuleLayersTest.php:41` - `->toBe([])`; `:45` - `!== [])->toBe($violates)`, com as linhas `:47-49`:<br>- `payment-names-ordering`: true;<br>- `ordering-names-payment`: true;<br>- `integration-names-both`: false | PASS |
| C14 | `202`, a forma do corpo, a linha `approved` e o evento | lote nomeado em aa10021: 4 casos do módulo e 1 de composição | `backend/app-modules/payment/tests/Feature/PaymentTest.php:46-50` - `assertAccepted()`, `data.order_id`, `data.status` `approved`, `data.amount_cents` 12345 e a mensagem; `:60` - `toEqualCanonicalizing(['id', 'order_id', 'status', 'amount_cents'])`; `:81-83` - 1 linha `approved` de 12345; `:90-91` - o payload `['order_id' => $order->id]`; `backend/tests/Feature/DeliveryEstimateTest.php:21` - `->assertAccepted()` | PASS |
| C15 | falha no outbox não deixa aprovado nem mensagem | lote nomeado em aa10021 | `PaymentTest.php:100-103` - `assertStatus(500)`, nenhuma linha `approved` e `outbox_messages` vazia; `backend/tests/Feature/Outbox/OrderEventsOutboxTest.php:48-50` | PASS |
| C16 | `404` para pedido desconhecido ou de outro cliente, e a porta é consultada com o cliente | lote nomeado em aa10021: 3 casos do módulo e 4 de composição | `PaymentTest.php:69-72` - `assertNotFound()`, sem linha e sem cobrança; `:231-236` - `assertApiError(..., 'NOT_FOUND', 'Recurso não encontrado.')`, sem linha e sem cobrança; `:246` - `expect($port->asked)->toBe([[auth('customer')->id(), $order->id]])`; `backend/tests/Feature/Account/CustomerRecordRoutesTest.php:79-81` e `:89-92` | PASS |
| C17 | `409` e a tabela de 4 combinações | lote nomeado em aa10021 | `PaymentTest.php:171-175` e `:185-189` - `assertConflict()`, a mensagem e nenhuma cobrança; `backend/app-modules/payment/tests/Unit/Services/PaymentServiceTest.php:19-26` - só "aguarda e sem aprovado" passa; `backend/tests/Feature/PaymentFlowTest.php:29-35` | PASS |
| C18 | aprovação concorrente perde com `409` | lote nomeado em aa10021 | `PaymentTest.php:203-207` - `assertConflict()`, 1 `approved` e nenhum `payment.payment_approved`; `backend/app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:68-72` | PASS |
| C19 | `402`, a linha `declined` fica, e o pedido real continua `awaiting_payment` | lote nomeado em aa10021 | `PaymentTest.php:111-119` - `assertStatus(402)`, `PAYMENT_DECLINED`, 1 linha `declined` e nenhum `payment.payment_approved`; `PayOrderUseCaseTest.php:49-56`; `PaymentFlowTest.php:17-20` - `$order->fresh()->status` igual a `AwaitingPayment` | PASS |
| C20 | `422` antes da porta, para qualquer id, com ou sem porta disponível | lote nomeado em aa10021 | `PaymentTest.php:143-148` - `assertUnprocessable()` e `VALIDATION_FAILED`; `:255-259` - `expect($port->asked)->toBe([])`; `:267-270` - `expect($port->calls)->toBe(0)` | PASS |
| C21 | `503` com o corpo exato, sem cobrar nem gravar; o mesmo numa cópia sem o Ordering | lote nomeado em aa10021 (o caso do módulo) e `bash proofs/c21-payment-without-ordering.sh` em aa10021, exit 0, `Tests: 1 passed (7 assertions)`, cópia saída de "detached HEAD aa10021" | `PaymentTest.php:280-284` - `assertApiError($response->assertStatus(503), 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.')`, `$port->calls` 1, sem cobrança e com `payments` e `outbox_messages` vazias; `proofs/c21-WithoutOrderingTest.php:12-13` - o contrato do Ordering não existe e `app(OrdersToPay::class)` é `UnavailableOrdersToPay`; `:20-23` | PASS |
| C22 | `payment.payment_approved` é lido pela classe do Ordering e move o pedido | lote nomeado em aa10021 | `backend/tests/Feature/Messaging/ConsumerNamesTest.php:29-31` com `:34`; `:43` - `eventOf('payment.payment_approved')` igual a `InboundPaymentApproved::class`; `backend/tests/Feature/Messaging/ConsumerRedeliveryTest.php:57` | PASS |
| C23 | a reentrega deixa `payment_approved` e 1 `ordering.order_paid` | lote nomeado em aa10021 | `ConsumerRedeliveryTest.php:52-59` - envelope da classe produtora entregue duas vezes, status `PaymentApproved` e `outboxMessages('ordering.order_paid')->toHaveCount(1)` | PASS |
| C24 | leitora e produtora concordam; um par divergente é acusado | lote nomeado em aa10021 | `backend/tests/Unit/Architecture/OrderEventPayloadTest.php:159` - os 5 nomes; `:165-166` - `disagreements(...)->toBe([])`; `:171` - a fixture `DisagreeingReader` dá `['payload']` | PASS |
| C25 | `InboundEvents` só em `Consumers` e `Providers` do Ordering, no código de `app-modules/*/src` | lote nomeado em aa10021 | `ModuleLayersTest.php:53` - `expect(ModuleLayout::inboundEventViolations(base_path('app-modules')))->toBe([])`; `:61` com `:71` - a fixture `inbound-event-outside-consumers` | PASS |
| C26 | sem chave em `payments.order_id`; as outras 5 iguais | lote nomeado em aa10021, 5 + 1 casos | `backend/tests/Feature/Database/ModuleMigrationsForeignKeyTest.php:27-31` - `expect(foreignKeyTarget($column))->toBe($references)`; `:37-42` - `foreignKeyTarget('payments.order_id')->toBeNull()` e 0 `FOREIGN KEY` em `payments` | PASS |
| C27 | `migrate` sobre um banco da base apaga só `payments_order_id_foreign` | `bash proofs/c27-migrate-existing.sh` em aa10021, exit 0 (`evidence/c27.txt:121` - `2026_10_21_000001_drop_payments_order_id_foreign ... [2] Ran`; `:122` - o `PASS` da prova) | `proofs/c27-migrate-existing.sh:23` - a base cria a chave; `:27` - a chave sai depois do `migrate`; `:29` - as 5 chaves continuam; `:31` - nada mais mudou; `:35-36` - a migration nova `Ran` e nada `Pending` | PASS |
| C28 | a migration nova no Payment; 16 nomes congelados | lote nomeado em aa10021, 13 + 1 casos | `backend/tests/Unit/Architecture/ModuleMigrationsTest.php:46-47` - o arquivo no pacote dono e não na raiz; `:55` - `expect(migrationNamesIn($paths))->toBe($frozen)` | PASS |
| C29 | sem o Payment: `migrate:fresh`, `404` e as pastas de testes restantes | `bash proofs/c29-without-payment.sh` em aa10021, exit 0, 65 s. A cópia saiu de "detached HEAD aa10021" (`evidence/c29.txt:1`); `migrate:fresh: ok` (`:86`); pastas catalog, identity, messaging e shared (`:87`); `Tests: 135 passed (574 assertions)` (`:280`) | `proofs/c29-without-payment.sh:7` - o `migrate:fresh` da cópia sai com 0; `:15` - `[ "$status" -eq 0 ]`; `:16` - o arquivo de prova rodou; `proofs/c29-WithoutPaymentTest.php:16` - `interface_exists('Modules\Payment\Contracts\OrdersToPay')` falso; `:20` - `->assertNotFound()` para um id desconhecido. Na saída: `evidence/c29.txt:274` - `✓ it answers 404 to the payment route without the payment module inst…` | PASS |
| C30 | adaptador do `Integration`: tabela de 4 status e `null` | lote nomeado em aa10021: 1 + 4 + 1 casos | `backend/tests/Feature/Integration/OrdersToPayAdapterTest.php:14` - `toBeInstanceOf(OrdersToPayFromOrdering::class)`; `:21-22` - `toEqual(new OrderToPay(...))` sobre `:24-27`; `:34-35` - `toBeNull()` para outro cliente e para id desconhecido | PASS |
| C31 | a forma da porta e do value object | lote nomeado em aa10021 | `backend/tests/Unit/Architecture/IdTypesTest.php:70-75` - interface, o método `findForPayment`, os parâmetros `[['customerId', 'string'], ['orderId', 'string']]`, o retorno `'?'.OrderToPay::class` e o construtor | PASS |
| C32 | `401` sem sessão; `429` na 21ª tentativa | lote nomeado em aa10021 | `PaymentTest.php:292` - `assertUnauthorized()` com `UNAUTHENTICATED`; `:360-364` - 20 respostas sem `429` e a 21ª com `assertTooManyRequests()` | PASS |
| C33 | os 4 documentos descrevem o piloto, sem afirmação velha nenhuma | `bash proofs/c33-docs.sh` em aa10021, exit 0 ("PASS: the four documents describe the pilot, with no stale claim and no broken link") | `proofs/c33-docs.sh:23-32` - os 8 itens exigidos em cada documento, com `:30` - `'the 5 modules with tests': lambda t: any(sorted(members) == sorted(WITH_TESTS) for _, members in lists_of_modules_with_tests(t))`; `:53-55` - `if sorted(members) != sorted(WITH_TESTS): bad += 1` para toda lista vista (`:14-22`, que conta uma lista com 3 ou mais dos 5 e no máximo 1 outro módulo, `:20`); `:33-41` - a lista `stale`; `:56-58` - a heurística de `payments` restrito; `:59-66` - os links relativos. No `HEAD`, a prova vê uma lista em cada documento: `AGENTS.md:97`, `README.md:833`, `docs/domain-analysis.md:603` e `.design/module-isolation.md:5` e `:146`. Todas são exatamente os 5, lidas por mim. O R6-M1 (o R5-M5 recriado) e os R6-M2 a R6-M5 morrem | PASS |
| C34 | `make test` sai com 0, nada pulado | `bash proofs/c34-suite.sh` em aa10021, exit 0: `Tests: 2256 passed (9266 assertions)`, 188,31 s. O `make test` do C2, no mesmo `HEAD`, também saiu com 0 | `proofs/c34-suite.sh:7` - `[ "$code" -eq 0 ]`; `:8` - recusa `failed`, `skipped`, `incomplete`, `risky` e `todo`; `:9` - o teste de log rodou (2 linhas `✓ it logs each payment attempt without the card token` em `evidence/c34-make-test.txt`) | PASS |
| C35 | `make pint` sem arquivo a corrigir | `bash proofs/c35-pint.sh` em aa10021, exit 0 (`PASS ... 497 files`) | `proofs/c35-pint.sh:6` - `[ "$code" -eq 0 ]`, sobre o `pint --test` do `Makefile` | PASS |
| C36 | o smoke de carga passa | `bash proofs/c36-load-smoke.sh` em aa10021, exit 0, 57 s: `backoffice`, `checkout` e `storefront` com ✓, 3 iterações completas e `checks: rate==1` (`evidence/c36.txt`) | `proofs/c36-load-smoke.sh:10` - `[ "$code" -eq 0 ]`; `:11` - `diff` de `c36-cache.before` e `c36-cache.after` (iguais: o `bootstrap/cache` não mudou) | PASS |
| C37 | o `Integration` só usa fachadas; o `ModuleBoundariesTest` fica verde | lote nomeado em aa10021, com o `ModuleBoundariesTest` inteiro (`PASS Tests\Unit\Architecture\ModuleBoundariesTest`) | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:598` - `expect($named)->not->toBeEmpty()`; `:601-602` - cada classe nomeada está em `facadeOf($module)` ou `vocabularyOf($module)` | PASS |
| C38 | a `PaymentFactory` não cria pedido | lote nomeado em aa10021 | `PaymentTest.php:329-332` - 1 linha em `payments`, 0 em `orders`, `RecordId::isCanonical($payment->order_id)` e `$payment->order_id[14]` igual a `'7'` | PASS |
| C39 | sem o Payment, um pedido criado em `placed` e levado a `awaiting_payment` pelo `ordering.order_placed` entregue pelo relay continua assim depois da tentativa (`404`) e de o relay esvaziar o outbox de novo, sem `payment.payment_approved` | `bash proofs/c29-without-payment.sh` em aa10021, exit 0 (a mesma execução do C29). `evidence/c29.txt:275` - `✓ it keeps an order awaiting payment without the payment module insta…` | `proofs/c29-without-payment.sh:17` - `grep -qE '✓ it keeps an order awaiting payment without the'` ou `fail`; `proofs/c29-WithoutPaymentTest.php:25` - o pedido nasce `OrderStatus::Placed`; `:29-31` - `record(new OrderPlaced($order->id))`, `relayUntilIdle()` e `expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)`; `:35` - `->assertNotFound()` para o pedido do próprio cliente; `:36` - o relay de novo; `:38-39` - status `AwaitingPayment` e `DB::table('outbox_messages')->where('name', 'payment.payment_approved')->count()` igual a 0. O texto foi aprovado por diashelter em 2026-10-21 (`checks.md:234`) | PASS |

## Coverage

verified at aa10021 para a linha `documentos (4)`; carried from 27be65e, 91be57d e 40058c5 para as outras, marcadas.

A linha `documentos (4)` foi recalculada, porque a correção mudou a prova dela e porque o membro sem prova da rodada 5 mora nela. As provas de outras três linhas rodaram de novo em aa10021, e a fonte delas não mudou:

- `módulos com testes que sobem sozinhos`;
- `configurações de remoção`;
- `chaves estrangeiras`.

As outras linhas vêm de rodadas anteriores, marcadas. Nenhum arquivo de `src`, rota, migration, teste ou documento mudou desde `40058c5`, e a fonte delas também não.

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| arquivos movidos (27) (carried from 27be65e; a prova rodou de novo em aa10021) | a tabela *Arquivos movidos* do plano (`plan.md:151-157`) | os 27, por `c01-moved-files.sh:35-36` | - |
| módulos com testes que sobem sozinhos (5) (carried from 27be65e; as 5 provas rodaram de novo em aa10021) | o AC 4 e `ls backend/app-modules/*/tests` | identity 55 · messaging 60 · shared 6 · catalog 12 · payment 49, todos por C6 | - |
| tipos de teste num módulo (2) (carried from 27be65e) | `backend/phpunit.xml:8-15` e `backend/tests/Pest.php:17-25` | Feature `FeatureHarnessTest.php:13` · Unit `UnitHarnessTest.php:12` (C3) | - |
| status de `POST /api/orders/{order}/payment` (8) (carried from 27be65e) | o `Surface` do plano (`plan.md:87`), a rota, o use case e o form request | 202 `PaymentTest.php:46` · 401 `:292` · 402 `:111` · 404 `:231` · 409 `:171` · 422 `:143` · 429 `:364` · 503 `:280` | - |
| regra "pode ser pago" (4) (carried from 27be65e) | `PaymentService.php:21` | as 4 linhas de `PaymentServiceTest.php:23-26` (C17) | - |
| `OrderStatus` -> `awaitingPayment` (4) (carried from 27be65e) | o enum `OrderStatus.php:9-12` | `OrdersToPayAdapterTest.php:24-27` (C30) | - |
| status que recusam o pagamento com o Ordering real (3 + já aprovado) (carried from 27be65e) | o AC 13 e o enum | `PaymentFlowTest.php:35` e `PaymentTest.php:179-189` | - |
| respostas do adaptador (3) (carried from 27be65e) | `OrdersToPayFromOrdering.php:22-29` | `OrdersToPayAdapterTest.php:21-22`, `:34` e `:35` (C30) | - |
| chaves estrangeiras entre módulos (5 + `payments.order_id` sem chave) (carried from 27be65e; o C27 rodou de novo em aa10021) | o `pg_constraint` de um banco da base (`evidence/c27.txt`) e as migrations do `HEAD` | `ModuleMigrationsForeignKeyTest.php:27-31` e `:37-42`; `c27-migrate-existing.sh:27` e `:29` | - |
| nomes de evento inscritos (5) (carried from 27be65e) | o `EventSubscriptionRegistry` em execução | `OrderEventPayloadTest.php:159`, laço `:161-167` (C24) | - |
| portas da Landing (7) (carried from 27be65e) | a tabela `Landing` do plano (`plan.md:93-101`) | 1 C11, C37 · 2 C31 · 3 C22, C24, C25 · 4 C21 · 5 C26, C27, C28 · 6 C1, C3, C7 · 7 C10 | - |
| entidades da Relations (1) (carried from 27be65e) | o `erDiagram` do plano (`plan.md:76-79`) | `orders` 1:N `payments` sem chave: C26, C27 | - |
| configurações de remoção (3) (carried from 27be65e; as provas rodaram de novo em aa10021) | os critérios 4, 16 e 22 (`plan.md:116`, `:137` e `:143`) | C6 (5 execuções) · C21 · C29 | - |
| regras de arquitetura com violação proposital (carried from 40058c5): 4 regras, e a dos testes de módulo com 4 ramos | os ACs 6, 10, 19 e 20 e os ramos de `ModuleLayout::testsViolations` (`ModuleLayout.php:157-174`) | testes de módulo (AC 6):<br>- nome de classe de outro módulo de negócio: `ModuleLayersTest.php:67`, morta pelo R5-M3;<br>- apoio da suíte da raiz: `:68`;<br>- helper de outro módulo de negócio: `:69`, morta pelo R5-M1;<br>- helper do `tests/Pest.php`: `:70`, morta pelo R5-M2;<br>- o lado que passa, nas pastas reais (`:33`): morto pelo R5-M4.<br>isolamento nas duas direções (AC 10) `:47-48` · uso das classes leitoras (AC 20) `:71` · leitora divergente da produtora (AC 19) `OrderEventPayloadTest.php:171` | - |
| donos dos helpers proibidos a um teste de módulo (carried from 40058c5): 2 tipos | `ModuleLayout.php:189-192` e os arquivos `tests/Helpers.php` que existem | o `tests/Pest.php`: `ModuleLayersTest.php:70` · o `tests/Helpers.php` de outro módulo de negócio: `:69` | - |
| estados do slice Payment ↔ Ordering (7 linhas, 11 membros) (carried from 91be57d; as provas rodaram de novo em aa10021) | a tabela de estados do design (`.design/module-isolation.md:154-162`) | aprovado: pagamento e evento na mesma transação C14, C15 · o Ordering lê com a classe dele e marca pago C22, C23 · `202` C14 · desconhecido ou de outro cliente `404` C16 · não aguarda `409` C17 · recusado `402` e linha gravada C19 · Ordering ausente `503`, nada gravado C21 · Payment ausente: rota some `404` C29, pedidos ficam em `awaiting_payment` C39 e pastas restantes passam C29 · suíte do Payment sem o Ordering C6 payment e C21 | - |
| documentos (4) × itens do C33 (verified at aa10021) | o AC 24 e o claim do C33 (`checks.md:137`); os 4 documentos no `HEAD` (iguais aos de `40058c5`). Enumerei as listas que `c33-docs.sh:14-22` vê, rodando a mesma função sobre cada documento:<br>- contadas: `AGENTS.md:97`, `README.md:833`, `docs/domain-analysis.md:603` e `.design/module-isolation.md:5` e `:146`, todas exatamente os 5;<br>- ignoradas: `docs/domain-analysis.md:622` (2 dos 5) e `.design/module-isolation.md:16` (os 8 módulos de negócio, com 5 sem testes) | "os 5 módulos com testes nelas", o membro sem prova da rodada 5:<br>- `AGENTS.md`: R6-M1 e R6-M5;<br>- `README.md`: R6-M2;<br>- `docs/domain-analysis.md`: R6-M3;<br>- `.design/module-isolation.md`: R6-M4.<br>Todos morreram em `c33-docs.sh:30`, `:53-55` ou nos dois.<br>Varredura de 40 variantes, 8 por lista: tirar cada um dos 5 nomes, trocar o Payment pelo Inventory, acrescentar o Ordering, acrescentar o Ordering e o Inventory. A prova acusou 38, entre elas as 25 retiradas e as 5 trocas. As outras 2 estão em "Lacunas de precisão".<br>Os outros 7 itens (`:24-29` e `:31`, iguais aos de `40058c5`) vêm de 40058c5; a lista `stale` e a heurística, das 9 falhas carregadas, que rodaram de novo e morreram | - |
| startup: ligação de `OrdersToPay` (3 montagens) (carried from 27be65e; o C21 rodou de novo em aa10021) | `IntegrationServiceProvider.php:23-27`, `bootstrap/cache/packages.php:110` e `integration/composer.json:17` | harness `OrdersToPayAdapterTest.php:14` · app sem o Ordering `c21-WithoutOrderingTest.php:13` · app de dev e produção pela mesma descoberta | - |
| startup: inscrição da classe leitora (2 montagens) (carried from 27be65e) | `OrderingServiceProvider.php:20` e `:51` | harness `ConsumerNamesTest.php:43` · workers pelo mesmo `boot()` | - |

A linha do `checks.md` (`checks.md:170`) conta 9 membros no slice e não cita "o Ordering o lê com a classe dele e marca o pedido como pago". O membro tem prova (C22 e C23), então isso não é lacuna (carried from 1083151).

Linhas `Swept` que resolvem para algo que já existia (carried from 27be65e), relidas no `HEAD`:

- **authorization:** `auth:customer` em `backend/app-modules/payment/routes/api.php:9`.
- **concurrency:** o índice único parcial em `create_payments_table.php:39`.
- **observability:** o teste de log em `PaymentTest.php:335-355`, com 2 linhas `✓` no `make test` do C34 em aa10021.
- **data lifecycle:** o `down()` vazio em `2026_10_21_000001_drop_payments_order_id_foreign.php:24`.
- **Observable, limite:** `throttle:20,1,payment` em `routes/api.php:10`.

Lacunas de precisão:

- **C33, o limite de "outros módulos" de uma lista (verified at aa10021; não reprova):**
  - **O quê:** `c33-docs.sh:20` só conta uma lista que tenha 3 ou mais dos 5 módulos com testes e **no máximo 1** outro módulo (`len(set(members) - WITH_TESTS) <= 1`). Uma lista com 2 ou mais módulos sem testes é ignorada, e o laço `:53-55` não a vê. O limite existe para não acusar a enumeração dos módulos de negócio (`.design/module-isolation.md:16`), e o docstring da `:15` o descreve certo. Já a linha nova do Handoff (`checks.md:236`) diz que a prova "acusa toda lista que nomeie 3 ou mais deles e não seja exatamente os 5", o que não é verdade.
  - **Onde aparece:** no `.design/module-isolation.md`, o único documento com duas listas, a prova sai com 0 nos dois casos abaixo. Em cada um, a outra lista continua exata e satisfaz a `:30`:
    - a lista da `:146` com o Ordering e o Inventory acrescentados;
    - a lista da `:5` com os mesmos dois acrescentados.

    A `:146` com o Ordering, o Fulfillment e o Inventory acrescentados também sai com 0. São os três módulos que a frase seguinte diz que esperam a sua slice. Nos outros três documentos, que têm uma lista só, a mesma variante morre na `:30`.
  - **Por que não reprova:** os dois casos mantêm o claim do C33. O design continua descrevendo os 5 módulos na outra linha, e a parte negativa do claim nomeia só três afirmações velhas. Tratei-os como mutantes equivalentes em relação ao C33, e não como mutantes sobreviventes, e por isso não estão na tabela de falhas. O R6-M4 de uma linha só (o Payment tirado só da `:146`) também mantém o claim e morre no laço `:53-55`: a prova é mais estrita que o claim para uma lista com módulo a menos, e menos estrita que o Handoff para uma lista com 2 ou mais a mais.
  - **O que fecharia:**
    - subir o limite para 4: rodei a função com os limites 1, 2, 3 e 4 sobre os 4 documentos do `HEAD`, e nenhuma lista nova é acusada; a enumeração da `:16` tem 5 módulos sem testes e continua de fora;
    - ou corrigir a frase do Handoff.
- **C33, a lista não é amarrada a testes (verified at aa10021; não reprova):** o predicado antigo pedia a palavra `test` no mesmo parágrafo, o que no `AGENTS.md` era a lista inteira das Convenções. O novo aceita qualquer linha com a lista exata. No `HEAD`, as 5 listas contadas estão em frases sobre testes, lidas por mim:
  - `AGENTS.md:97` e `README.md:833`: "Hoje têm testes no módulo";
  - `docs/domain-analysis.md:603`: "Os testes de 5 módulos";
  - `.design/module-isolation.md:5`: "os testes de 5 módulos";
  - `.design/module-isolation.md:146`: "moveu 27 arquivos para 5 módulos".

  Nenhuma mudança plausível troca o assunto de uma delas mantendo a lista.
- **Alcance da regra dos helpers (carried from 40058c5; não reprova):** o `foreignHelpersOf` só conhece as funções declaradas na coluna 0 do `tests/Pest.php` e do `tests/Helpers.php` de cada outro módulo (`ModuleLayout.php:189-198`). Uma função global declarada dentro de um arquivo de teste (por exemplo `payOrder()` em `PaymentTest.php:32`) fica de fora. Os documentos descrevem a regra com esse alcance exato.
- **C9, a contagem do `HEAD` (verified at aa10021; não reprova):** o `c09-test-count.sh:13` conta as declarações `it(` de qualquer `.php`, então as 3 das fixtures `Offender.php` entram nas 657. Elas nunca rodam. Sem elas, o total é 654, ainda acima de 627.
- **C33, o padrão da rodada 3 (carried from 91be57d; linha atualizada):** o padrão, agora em `proofs/c33-docs.sh:41`, só casa quando "são" vem logo antes de "29".
- **C24 e AC 19, C16 (carried from 8af75b1):** a fixture prova só divergência de chaves; a de versão foi provada pelo mutante 4 da rodada 1. O caso do módulo para id desconhecido afirma só o status; o corpo vem do `CustomerRecordRoutesTest.php:89`.

## Test policy rows

carried from 27be65e: a linha classifica arquivos de código (`OrdersToPayFromOrdering.php`, `PaymentService.php` e `IntegrationServiceProvider.php`), e nenhuma correção depois de `27be65e` tocou nenhum deles. A correção desta rodada mudou só um script de prova. As provas de cada arquivo classificado rodaram de novo em aa10021 (C30, C17, C21 e C29).

O `checks.md` tem a seção `Test policy` com "Não se aplica", sem linhas, então vale a convenção do repositório.

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| "Não se aplica": decisão nova provada na própria camada, um caso por linha da tabela de decisão (convenção do repositório) | `integration/src/Payment/OrdersToPayFromOrdering.php`, `payment/src/Core/Services/PaymentService.php` e `integration/src/Providers/IntegrationServiceProvider.php` | o adaptador com 4 status (C30), a regra com 4 combinações (C17) e os 3 ramos do provider (C30, C21 e C29) | yes |

## Faults injected

### Verified at aa10021: as superfícies novas de `proofs/c33-docs.sh`

A correção criou três superfícies de asserção:

- o predicado exigido (`:30`), que pede uma lista exatamente igual aos 5;
- o laço de acusação (`:53-55`), que acusa toda lista vista que não seja exatamente os 5;
- a função que decide o que é uma lista (`:13-22`).

O assunto que essas asserções guardam são os 4 documentos. Então cada falha muda um documento, como o R5-M5 da rodada 5, e roda a prova. Foram 5 falhas, o teto do procedimento, cobrindo os 4 documentos e as duas asserções novas, cada uma sozinha e as duas juntas.

**Como os mutantes foram aplicados:**

- **Base:** o `git status --porcelain` da pasta principal foi gravado antes de tudo, e estava vazio. A lista de worktrees também foi gravada (11 entradas), e `git stash list` estava vazio.
- **Worktree:** `git worktree add --detach <scratchpad>/wt-r6 HEAD`. A prova do C33 é só Python sobre os documentos, então não precisou de container, banco nem `vendor`. O `lib.sh` resolve o `ROOT` pelo próprio worktree (`git rev-parse --show-toplevel`), e a prova leu os documentos dele e gravou a evidência nele.
- **Controle:** o `c33-docs.sh` do worktree sem mudança saiu com 0.
- **Aplicação:** cada mutante é uma troca de texto conferida (`assert text.count(old) == 1`). Depois de rodar a prova, voltavam com `git checkout --` o documento e o `evidence/c33.txt` do worktree, e o porcelain do worktree era conferido vazio entre um mutante e o outro.
- **R5-M5 contra a prova antiga:** o mesmo texto do R6-M1 rodou com o `c33-docs.sh` de `40058c5` posto no worktree (`git checkout 40058c5 -- proofs/c33-docs.sh`), e saiu com 0. Depois, os dois arquivos voltaram ao `HEAD`.

**Depois:**

- **Worktree:** o porcelain dele estava vazio no fim. O worktree não rodou `make test` nem `make test-filter`, então não criou banco nem vhost, e o `make test-db-drop` não se aplica. `git worktree remove --force` o removeu, e `git worktree list` voltou idêntico às 11 entradas de antes.
- **Pasta principal:** os mutantes rodaram no worktree e nenhum chegou à pasta principal. Nenhum `git stash` foi usado.
- **Cadeia de provas:** ela rodou na pasta principal, ao mesmo tempo que os mutantes, sem depender do worktree. As cópias de remoção (`.claude/worktrees/removal-*` e `proof-c27-base`) foram apagadas pelas próprias provas. `git worktree list` continuou com as 11 entradas, e nenhuma pasta `removal-*` ou `proof-c27-base` ficou em `.claude/worktrees`. Nenhum banco `removal`, `wt_` ou `proof` ficou no PostgreSQL, e os vhosts do RabbitMQ são `/` e `ecommerce_testing`. `git stash list` continuou vazio.
- **Arquivos regravados:** o porcelain final difere da base vazia só pelos 13 arquivos de `evidence/` que as provas regravaram, além deste relatório. Nenhum foi revertido. São eles:
  - `c02-make-test.txt`;
  - `c05-test-filter.txt`;
  - `c06-catalog.txt`, `c06-identity.txt`, `c06-messaging.txt`, `c06-payment.txt` e `c06-shared.txt`;
  - `c21.txt`, `c27.txt` e `c29.txt`;
  - `c34-make-test.txt`;
  - `c36-load-smoke.txt` e `c36.txt`.

  As outras evidências que as provas regravaram saíram iguais às commitadas.

| Mutation | Location | Killed |
| --- | --- | --- |
| R6-M1 (o R5-M5 recriado): a frase que lista os módulos com testes perde o Payment ("Hoje têm testes no módulo o Identity, o Messaging, o Shared, o Catalog e o Payment;" -> "Hoje têm testes no módulo o Identity, o Messaging, o Shared e o Catalog;") | `AGENTS.md:97`, prova `proofs/c33-docs.sh:30` e `:53-55` (C33) | yes. Exit 1, `FAIL: 2 problems`: `AGENTS.md: does not describe the 5 modules with tests` (`:30`) e `AGENTS.md:97: the modules with tests listed as Identity, Messaging, Shared, Catalog` (`:55`). Com o `c33-docs.sh` de `40058c5`, o mesmo texto sai com 0 |
| R6-M2: o Payment trocado pelo Ordering ("... o Catalog e o Payment." -> "... o Catalog e o Ordering.") | `README.md:833`, prova `c33-docs.sh:30` e `:53-55` (C33) | yes. Exit 1, `FAIL: 2 problems`: `README.md: does not describe the 5 modules with tests` e `README.md:833: the modules with tests listed as Identity, Messaging, Shared, Catalog, Ordering` |
| R6-M3: a lista perde o Payment ("(Identity, Messaging, Shared, Catalog e Payment, 27 arquivos)" -> "(Identity, Messaging, Shared e Catalog, 27 arquivos)") | `docs/domain-analysis.md:603`, prova `c33-docs.sh:30` e `:53-55` (C33) | yes. Exit 1, `FAIL: 2 problems`: `docs/domain-analysis.md: does not describe the 5 modules with tests` e `docs/domain-analysis.md:603: the modules with tests listed as Identity, Messaging, Shared, Catalog` |
| R6-M4: as duas listas do design perdem o Payment ("os testes de 5 módulos (Identity, Messaging, Shared, Catalog e Payment)" e "moveu 27 arquivos para 5 módulos (Identity, Messaging, Shared, Catalog e Payment)", cada uma sem o Payment) | `.design/module-isolation.md:5` e `:146`, prova `c33-docs.sh:30` e `:53-55` (C33) | yes. Exit 1, `FAIL: 3 problems`: `.design/module-isolation.md: does not describe the 5 modules with tests`, `...:5: the modules with tests listed as Identity, Messaging, Shared, Catalog` e `...:146: ...`. Só a `:146` mudada, com a `:5` intacta, sai com 1 só pelo laço (`FAIL: 1 problems`, `...:146: the modules with tests listed as Identity, Messaging, Shared, Catalog`) |
| R6-M5: a lista encolhe a dois nomes, que a `LIST` não lê como lista ("... o Identity, o Messaging, o Shared, o Catalog e o Payment;" -> "... o Identity e o Messaging;") | `AGENTS.md:97`, prova `c33-docs.sh:30` sozinha (C33) | yes. Exit 1, `FAIL: 1 problems`: só `AGENTS.md: does not describe the 5 modules with tests`. O laço não vê lista nenhuma, e a `:30` acusa sozinha |

### Verified at aa10021: as falhas carregadas que dependem do mesmo script

A correção editou o arquivo que guarda a lista `stale`, a heurística de `payments` restrito e os links. Essas asserções não mudaram de texto, mas desceram de linha (de `:19-27` para `:33-41`, de `:39-41` para `:56-58`). Rodei de novo, no mesmo worktree, as 9 falhas das rodadas 1 a 4 que dependem delas, para confirmar que a edição não as quebrou. Todas morreram de novo.

| Mutation | Location | Killed |
| --- | --- | --- |
| M1 (de 91be57d): volta a frase da rodada 3, "e depois da HEL-6 são 29 e 28" | `docs/domain-analysis.md:498`, prova `proofs/c33-docs.sh:41` | yes. `stale claim 'são 29 e 28'` |
| F1 (de 1083151): volta "restrito na exclusão (como `payments`)" | `docs/domain-analysis.md:588`, prova `c33-docs.sh:39` e `:56-58` | yes. As duas mensagens |
| F2 (de 1083151): volta "Hoje são 29 tipos públicos" | `README.md:1057`, prova `c33-docs.sh:39` e `:41` | yes. As duas mensagens |
| F3 (de 1083151): paráfrase "(como a de `payments`)" | `docs/domain-analysis.md:588`, prova `c33-docs.sh:56-58` | yes. `payments still described as restricted` |
| R1-a (de 8af75b1): volta a linha aberta "Tentativas de pagamento presas ao pedido por `restrictOnDelete`" | `docs/domain-analysis.md:733`, prova `c33-docs.sh:37` | yes |
| R1-b (de 8af75b1): volta "Payment → Ordering: PayableOrders" | `docs/domain-analysis.md:91`, prova `c33-docs.sh:35` | yes |
| R1-c (de 8af75b1): volta "o Payment lê o pedido pelo contrato `PayableOrders`" | `docs/domain-analysis.md:108`, prova `c33-docs.sh:35` | yes |
| R1-d (de 8af75b1): volta "✅ O Payment lê o pedido pelo contrato `PayableOrders`" | `docs/domain-analysis.md:346`, prova `c33-docs.sh:35` | yes |
| R1-e (de 8af75b1): volta "`findForPayment(...): ?OrderForPayment`, para o Payment" | `docs/domain-analysis.md:140`, prova `c33-docs.sh:36` | yes |

### Carried from 40058c5: a regra dos helpers do `ModuleLayout`

Nem o `ModuleLayout.php`, nem o `ModuleLayersTest.php`, nem as fixtures mudaram desde `40058c5`: `git diff --quiet 40058c5 aa10021 -- backend` sai com 0. O R5-M5 da rodada 5 não é carregado, porque o R6-M1 o substitui sobre a prova nova.

| Mutation | Location | Killed |
| --- | --- | --- |
| R5-M1: os `tests/Helpers.php` dos outros módulos saem da lista de donos | `backend/tests/Unit/Architecture/Support/ModuleLayout.php:191`, prova `ModuleLayersTest.php:61` com `:69` (C8) | yes |
| R5-M2: o `tests/Pest.php` sai da lista de donos | `ModuleLayout.php:189`, prova `ModuleLayersTest.php:61` com `:70` (C8) | yes |
| R5-M3: o ramo do nome de classe desligado | `ModuleLayout.php:158`, prova `ModuleLayersTest.php:61` com `:67` (C8) | yes |
| R5-M4: o próprio módulo entra na lista de donos | `ModuleLayout.php:191`, prova `ModuleLayersTest.php:33` (C8) | yes |

### Carried from 91be57d: o consumidor e a rota do Ordering (C39)

| Mutation | Location | Killed |
| --- | --- | --- |
| M2: o consumidor `MarkOrderAsAwaitingPayment` também roda o `MarkOrderAsPaidUseCase` | `backend/app-modules/ordering/src/Core/Consumers/MarkOrderAsAwaitingPayment.php:19-20`, prova `proofs/c29-without-payment.sh` (C39) | yes |
| M3: o Ordering responde `POST orders/{order}/payment`, marca o pedido como pago e devolve `404` | `backend/app-modules/ordering/routes/api.php:16-20`, prova `proofs/c29-without-payment.sh` (C39) | yes |

### Carried from 27be65e: o código de `src`, que nenhuma correção tocou

| Mutation | Location | Killed |
| --- | --- | --- |
| status do `503` trocado por `500` | `backend/app-modules/shared/src/Exceptions/ServiceUnavailableException.php:26` | yes. `answers 503 when the order port is unavailable` falhou (C21) |
| tradução do adaptador: `=== OrderStatus::AwaitingPayment` -> `!== OrderStatus::Placed` | `backend/app-modules/integration/src/Payment/OrdersToPayFromOrdering.php:28` | yes. `translates each order status into awaiting payment` com 2 falhas (C30) |
| regra "pode ser pago" sem o `$hasApprovedPayment` | `backend/app-modules/payment/src/Core/Services/PaymentService.php:21` | yes. `decides whether an order can be paid` com 1 falha (C17) |
| versão da classe leitora `1` -> `2` | `backend/app-modules/ordering/src/Core/InboundEvents/PaymentApproved.php:26` | yes. Falharam C24 e C23 |
| detector de isolamento sem a direção de entrada | `backend/tests/Unit/Architecture/Support/ModuleLayout.php:228` | yes. `fails on a deliberate isolation violation` com 1 falha (C13) |

## Gate

`make test` (`proofs/c34-suite.sh` no `HEAD` `aa10021`): 2256 passed, 0 failed, 0 skipped (9266 assertions, 188,31 s). O `make test` do C2, no mesmo `HEAD`, deu o mesmo: 2256 passed (9266 assertions, 188,86 s). O total é o da rodada 5, porque a correção não tocou nenhum teste. O `ConsumeEventsCommandTest` levou 12,86 s e 13,08 s e passou nas duas.

`python3 .claude/skills/tlc-spec-lean/scripts/validate_verification.py module-isolation-pilot --root /Users/helterdias/Documents/POC/laravel-module-ecomerce`: exit 0, com a saída:

```

validate_verification: 0 error(s), 0 warning(s) across [module-isolation-pilot]
```

Também conferi que o gate lê as linhas. Rodei uma cópia deste relatório num diretório temporário com dois defeitos plantados: o `Killed` do R6-M5 trocado por `no` e um `Unproven` não vazio na linha das portas da Landing. O gate saiu com 1, acusando exatamente esses dois erros.

### Lacunas, em ordem

Nenhuma bloqueia.

### Achados que não reprovam

1. **O Handoff descreve a prova nova com mais alcance do que ela tem (verified at aa10021).**
   - **O quê:** `checks.md:236` diz que a prova "acusa toda lista que nomeie 3 ou mais deles e não seja exatamente os 5". `c33-docs.sh:20` ignora a lista com 2 ou mais módulos sem testes.
   - **Onde importa:** no `.design/module-isolation.md`, uma das duas listas pode ganhar o Ordering e o Inventory, ou o Ordering, o Fulfillment e o Inventory, sem a prova acusar.
   - **Por que não reprova:** o claim do C33 continua valendo nesses casos.
   - **O que fecharia:** subir o limite para 4, que não acusa nada no `HEAD`, ou corrigir a frase.
2. **O predicado novo não amarra a lista a testes (verified at aa10021):** no `HEAD`, as 5 listas estão em frases sobre testes.
3. **O registro de handoff da rodada 5 foi corrigido (verified at aa10021).**
   - **`.specs/STATE.md:14`:** diz "enviada ao remoto até `40058c5`", e `origin/helterdias/module-isolation-pilot` está em `40058c5`.
   - **`:16`:** diz que esta rodada está sendo despachada e que o `git push` pede o ok de diashelter, o que bate com o `aa10021` só local.
   - **`:18`:** diz "Uncommitted: none", e o porcelain estava vazio no começo da rodada.
   - **`:14`, de novo:** diz "39 checks com prova verde no `HEAD`", e esta rodada confirma.
4. **Alcance da regra dos helpers, contagem do C9 e padrão do C33 da rodada 3 (carried from 40058c5 e 91be57d):** os detalhes estão em Coverage, "Lacunas de precisão".
