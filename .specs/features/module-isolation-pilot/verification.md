# Module Isolation Pilot verification

**Verdict**: FAIL
**Profile**: standard
**Diff range**: be1c84c..1083151
**Round**: 3 - scoped
**Verifier**: independent sub-agent (author != verifier)

Verificado em `1083151` (branch `helterdias/module-isolation-pilot`), na pasta principal, com a pilha de dev no ar (`db`, `redis`, `rabbitmq` e `api`). A rodada 2 verificou `be1c84c..8af75b1` e deu FAIL, com três achados. A correção é o commit `1083151` (diff `8a99282..1083151`).

O que a correção tocou:

- markdown: `README.md:1057`, `docs/domain-analysis.md:588` e `checks.md` (o C39 novo e a linha de Coverage dos estados do slice);
- as provas `proofs/c33-docs.sh` (dois padrões e uma heurística por linha), `proofs/c29-without-payment.sh` e `proofs/c29-WithoutPaymentTest.php` (o caso do C39);
- `evidence/c29.txt`.

Nenhum código mudou desde a rodada 1: `git diff --stat 27be65e..1083151 -- backend Makefile .github docker-compose.yml load-tests` sai vazio. Entre as provas, só `c29-*` e `c33-docs.sh` mudaram desde `27be65e`; o `lib.sh` não.

O que esta rodada fez:

- **Rodou de novo no `HEAD`:**
  - todas as provas por nome, num só `make test-filter`;
  - a suíte inteira (`c34-suite.sh`), que traz os marcadores do C2;
  - as provas de comando `c01`, `c04`, `c05`, `c09`, `c33` e `c35`;
  - a `c29-without-payment.sh`, que agora prova o C29 e o C39.
- **Trouxe da rodada 1 (`carried from 27be65e`):** `c06` ×5, `c21`, `c27` e `c36`. Elas exercitam código e scripts que não mudaram.
- **Recalculou** o vocabulário público no container `api`, com as funções do próprio `ModuleBoundariesTest`: 30 públicos e 28 privados, com o `OrderToPay` público.
- **Injetou 5 falhas** nas superfícies que a correção criou, em worktrees descartáveis.
- **Não commitou nada.** As provas regravaram `evidence/c05-test-filter.txt`, `evidence/c29.txt` e `evidence/c34-make-test.txt`.

Os três achados da rodada 2 estão resolvidos:

1. **C33:** a `docs/domain-analysis.md:588` diz agora "(como `payments` era até a isolação dos módulos tirar aquela chave)", o que é verdade. A prova pega a linha antiga (F1) e uma paráfrase dela que só a heurística alcança (F3).
2. **"Payment ausente: os pedidos ficam em `awaiting_payment`":** o C39 prova o membro, e a asserção de status mata um mutante que move o pedido (F5).
3. **A contagem do README:** `README.md:1057` diz 30 públicos e 28 privados, o que bate com o recálculo. A prova pega a frase antiga (F2).

O FAIL vem de um achado novo, só de documentação: **a `docs/domain-analysis.md:498` ainda diz, no presente, que os tipos públicos e privados "depois da HEL-6 são 29 e 28".** É a mesma contagem que a rodada 2 achou no README, que o piloto mudou para 30 e 28. A correção só ajustou o README, e o padrão novo da prova (`Hoje são 29 tipos públicos`, `proofs/c33-docs.sh:25`) só casa com a frase do README.

## Binding sources

verified at 1083151 (as linhas que a correção tocou e as fontes de documentos e do slice Payment ↔ Ordering); carried from 8af75b1 e 27be65e (as outras, marcadas)

| Source | Opened | Contradiction | Uncovered |
| --- | --- | --- | --- |
| `.design/module-isolation.md`, Key decisions 1 a 7 (carried from 8af75b1) | yes. A correção não tocou o design (`git diff 8a99282..1083151 -- .design` vazio); relido no `HEAD` | none | - |
| `.design/module-isolation.md`, slice Module Tests (carried from 27be65e) | yes | none | - |
| `.design/module-isolation.md`, slice Payment ↔ Ordering (verified at 1083151) | yes. Tabela de estados em `.design/module-isolation.md:154-162` | none. A linha "Payment ausente" (`:161`) tem agora os três membros provados: a rota some (`404`, C29), os pedidos ficam em `awaiting_payment` (C39) e as pastas restantes passam (C29) | - |
| `.specs/STATE.md`, AD-001 e AD-002 (carried from 27be65e) | yes | none | - |
| `AGENTS.md`, regra "sempre atualizar a documentação" (verified at 1083151: os 4 documentos relidos) | yes | none | `docs/domain-analysis.md:498` - "na HEL-10 eram 22 tipos públicos e 23 privados, e depois da HEL-6 são 29 e 28". O verbo está no presente e a frase descreve o resultado da HEL-6, não o da HEL-10 dona do registro: é uma afirmação sobre o sistema atual. A HEL-6 (`d75ded4`) acrescentou essa parte para manter o número em dia. No `HEAD` são 30 e 28, recalculados no container pelas funções `vocabularyOf` e `classesOutsideVocabularyOf` do `ModuleBoundariesTest`: Catalog 6/10, Customers 0/2, Identity 5/4, Inventory 4/4, Ordering 10/7, Payment 5/1, e o `Modules\Payment\Core\ValueObjects\OrderToPay` é público. Nenhum check cobre o número (o C33 não o enumera, e o padrão de `proofs/c33-docs.sh:25` só casa com a frase do README) |

O que foi relido nos 4 documentos no `HEAD` e não é achado:

- **`payments` com chave ou restrição:** toda linha que liga `payments` a chave, `restrict`, `foreign`, `constrained`, `aponta` ou `FK` é atual ou histórica e correta:
  - `docs/domain-analysis.md:4`, `:241`, `:346`, `:588` (corrigida), `:602`, `:711` e `:733` (✅ Resolvida);
  - `README.md:354`, `:884`, `:898`, `:1028` e `:1111`;
  - `.design/module-isolation.md:189`;
  - o "como o `payments`" de `AGENTS.md:85` fala da linha única, o índice único parcial de pagamento aprovado, que continua.
- **`README.md:355` ("`deliveries.order_id` também usa `restrict`"):** o "também" volta à linha `:353` (`order_items` e `orders`), não à `:354`.
- **`PayableOrders` e `OrderForPayment`:** toda menção atual diz que só o adaptador do `Integration` usa o contrato. São elas:
  - `README.md:102`, `:117`, `:123`, `:1053` (no passado, com "hoje pela própria porta `OrdersToPay`") e `:1107`;
  - `docs/domain-analysis.md:4`, `:92`, `:108`, `:140`, `:234`, `:346`, `:599` e `:674`;
  - `.design/module-isolation.md:5` e `:179`.
  - O `AGENTS.md:72` usa o `PayableOrders::findForPayment($customerId, $orderId)` como exemplo de busca com o dono. Continua verdade: o `OrderRepository` ainda o implementa com `customer_id`.
- **Contagens que o piloto mudou:**
  - interfaces: `AGENTS.md:63` diz 21; `git grep -l '^interface '` em `backend/app-modules/*/src` dá 21 no `HEAD` e 20 na base;
  - métodos de contrato: `README.md:901` diz 10; `IdTypesTest.php:40-50` tem 10;
  - pacotes: `README.md:892` diz 11;
  - migrations: os 16 nomes estão em `AGENTS.md:61`, `README.md:1028`, `docs/domain-analysis.md:602` e `.design/module-isolation.md:5`;
  - vocabulário: `README.md:1057` diz 30 e 28, o que bate com o recálculo.
- **Registros datados de outras features, que descrevem o resultado delas e continuam verdadeiros sobre o seu momento:**
  - `docs/domain-analysis.md:573` ("nenhuma das 19 interfaces saiu", HEL-12);
  - `docs/domain-analysis.md:579` ("os 10 pacotes", HEL-12);
  - `.design/module-isolation.md:20-23` ("Números que decidiram, contados no repositório em 2026-10-20").
- **Testes só em `backend/tests` e 15 migrations:** nenhuma menção restante. `README.md:1029` diz "ficaram em `backend/tests` ... até a isolação dos módulos".

## Checks

As provas foram verificadas em 1083151. As citações dos arquivos de teste e de código vêm de 27be65e: nenhum deles mudou, e as linhas de início de cada teste que o `rg` achou no `HEAD` são as mesmas da rodada 2. As citações das provas que a correção tocou (`c29-*` e `c33-docs.sh`) foram atualizadas.

Prova nomeada, uma só execução para todos os nomes:

```
make test-filter FILTER="ModuleBoundariesTest|<os 54 nomes das provas make test-filter do checks.md>"
```

- **Resultado:** exit 0, `Tests: 1032 passed (2347 assertions)`, `Duration: 52.54s`.
- **Saída:**
  - cada um dos 54 nomes aparece com `✓`;
  - o `ModuleBoundariesTest` aparece como `PASS Tests\Unit\Architecture\ModuleBoundariesTest`;
  - o nome truncado é `✓ it reads every subscribed event from the payload its producer write…`.
- **Árvore:** cada nome foi achado por `rg -n -F "it('<nome>"` em `backend/tests` e `backend/app-modules/*/tests`, com a mesma linha de início da rodada 2. Dois nomes existem no módulo e na composição: `answers 404 to the payment of an unknown order` (`PaymentTest.php:63` e `CustomerRecordRoutesTest.php:84`) e `rejects payment for orders that are not awaiting payment` (`PaymentTest.php:165` e `PaymentFlowTest.php:23`).

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | os 27 arquivos estão no módulo e nenhum em `backend/tests` | `bash proofs/c01-moved-files.sh` em 1083151, exit 0 ("27 files in their modules, none left in backend/tests") | `proofs/c01-moved-files.sh:35` - o arquivo novo existe (`[ -f "$new" ]`); `:36` - o antigo não existe; `:40` - `[ "$count" -eq 27 ]` | PASS |
| C2 | um `make test` roda o caso do Payment e o de composição | o `make test` do C34 em 1083151, exit 0, `Tests: 2254 passed (9264 assertions)`. As 4 expressões de `proofs/c02-make-test.sh:7-10` foram achadas uma vez cada em `evidence/c34-make-test.txt`. O script do C2 não rodou à parte, para não rodar duas suítes | `proofs/c02-make-test.sh:6` - `[ "$code" -eq 0 ]`; `:8` - `✓ it approves the fake approved card`; `:10` - `✓ it shows the delivery estimate after the payment is`. Os casos estão em `backend/app-modules/payment/tests/Unit/Gateways/FakePaymentGatewayTest.php:13` e `backend/tests/Feature/DeliveryEstimateTest.php:5` | PASS |
| C3 | Feature do módulo com `TestCase` e `RefreshDatabase`; Unit sem | lote nomeado em 1083151, exit 0 | `backend/app-modules/shared/tests/Feature/FeatureHarnessTest.php:12-14` - `toBeInstanceOf(TestCase::class)`, `RefreshDatabase` em `class_uses_recursive($this)` e `Schema::hasTable('migrations')`; `backend/app-modules/shared/tests/Unit/UnitHarnessTest.php:11-12` - `->not->toHaveKey(RefreshDatabase::class)` (carried from 27be65e) | PASS |
| C4 | `Makefile` e CI iguais aos da base | `bash proofs/c04-unchanged-commands.sh` em 1083151, exit 0 | `proofs/c04-unchanged-commands.sh:5` - `git diff --quiet be1c84c HEAD -- "$file"` para o `Makefile` e o `.github/workflows/ci.yml` | PASS |
| C5 | o filtro roda exatamente 1 teste, o do Payment em `tests/Unit` | `bash proofs/c05-test-filter.sh` em 1083151, exit 0, `Tests: 1 passed (4 assertions)` | `proofs/c05-test-filter.sh:8` - `grep -qE 'Tests: +1 passed'`; `:9` - `PASS +Appmodules\\payment\\tests\\Unit\\Gateways\\FakePaymentGatewayTest` | PASS |
| C6 | cada um dos 5 módulos migra e passa sozinho | carried from 27be65e: `bash proofs/c06-module-alone.sh` para identity, messaging, shared, catalog e payment, todos com exit 0. Nem o código nem o script mudaram | `proofs/c06-module-alone.sh:15` - o `migrate:fresh` da cópia sai com 0; `:22` - `[ "$status" -eq 0 ]`; `:24` - recusa `skipped`, `incomplete`, `risky` e `todo`. Por módulo:<br>- identity: 55 passed;<br>- messaging: 60;<br>- shared: 6;<br>- catalog: 12;<br>- payment: 49 (`evidence/c06-<módulo>.txt`) | PASS |
| C7 | `Modules\<Módulo>\Tests\` no pacote, nunca na raiz | lote nomeado em 1083151: 11 + 1 casos | `backend/tests/Unit/Architecture/ModulePackagesTest.php:86-87` - `$expected["Modules\\{$module}\\Tests\\"] = 'tests/'` só quando a pasta existe; `:95` - `->toBe($expected)`; `:106` - `->toBe([])` para a raiz (carried from 27be65e) | PASS |
| C8 | teste de módulo que nomeia outro módulo de negócio é acusado | lote nomeado em 1083151: 11 linhas mais as 7 fixtures | `backend/tests/Unit/Architecture/ModuleLayersTest.php:33` - `expect(ModuleLayout::testsViolations(...))->toBe([])`; `:61` com `:67` - `->not->toBe([])` na fixture `module-test-uses-business` (carried from 27be65e) | PASS |
| C9 | as 628 declarações da base continuam pelo nome | `bash proofs/c09-test-count.sh` em 1083151, exit 0: 628 na base e 654 no `HEAD`; 2 renomeadas e 1 substituída | `proofs/c09-test-count.sh:17` - `[ "$base" -eq 628 ]`; `:31` - `[ -z "$lost" ]`; `:32` - `[ "$now" -ge 627 ]` | PASS |
| C10 | identidade de teste só com o id, UUID v7, no guard `customer` | lote nomeado em 1083151 | `backend/app-modules/shared/tests/Feature/TestIdentityTest.php:13-16` - `RecordId::isCanonical($first)`, `$first[14]` igual a `'7'` e `auth('customer')->id()` igual a `$first`; `:20-21` e `:25-26` (carried from 27be65e) | PASS |
| C11 | 11 pacotes, manifesto do `integration`, tipo `COMPOSITION` | lote nomeado em 1083151 | `ModulePackagesTest.php:16-17` - a lista com `'integration'`; `:96` - o provider do pacote; `:110-113` - `COMPOSITION toBe(['Integration'])` (carried from 27be65e) | PASS |
| C12 | nada de `Modules\Ordering` no payment, nem de `Modules\Payment` no ordering | lote nomeado em 1083151 | `ModuleLayersTest.php:37` - `expect(ModuleLayout::isolationViolations(base_path('app-modules'), 'Payment'))->toBe([])` (carried from 27be65e) | PASS |
| C13 | só o `Integration` nomeia o Payment; a regra falha nas duas direções | lote nomeado em 1083151, 1 + 3 casos | `ModuleLayersTest.php:41` - `->toBe([])`; `:45` - `->toBe($violates)`, com as linhas `:47-49`:<br>- `payment-names-ordering`: true;<br>- `ordering-names-payment`: true;<br>- `integration-names-both`: false<br>(carried from 27be65e) | PASS |
| C14 | `202`, a forma do corpo, a linha `approved` e o evento | lote nomeado em 1083151: 4 casos do módulo e 1 de composição | `backend/app-modules/payment/tests/Feature/PaymentTest.php:46-50` - `assertAccepted()`, `data.order_id`, `data.status` `approved`, `data.amount_cents` 12345 e a mensagem (relido no `HEAD`); `:60` - as 4 chaves; `:81-83` - 1 linha `approved`; `:90-91` - o payload `['order_id' => $order->id]`; `backend/tests/Feature/DeliveryEstimateTest.php:21` - `->assertAccepted()` (carried from 27be65e) | PASS |
| C15 | falha no outbox não deixa aprovado nem mensagem | lote nomeado em 1083151 | `PaymentTest.php:100-103` - `assertStatus(500)`, nenhuma linha `approved` e `outbox_messages` vazia (relido no `HEAD`); `backend/tests/Feature/Outbox/OrderEventsOutboxTest.php:48-50` (carried from 27be65e) | PASS |
| C16 | `404` para pedido desconhecido ou de outro cliente, e a porta é consultada com o cliente | lote nomeado em 1083151: 3 casos do módulo e 4 de composição | `PaymentTest.php:69-72` - `assertNotFound()`; `:231-236` - `assertApiError(..., 'NOT_FOUND', 'Recurso não encontrado.')`, sem linha e sem cobrança; `:246` - `expect($port->asked)->toBe([[auth('customer')->id(), $order->id]])`; `backend/tests/Feature/Account/CustomerRecordRoutesTest.php:79-81` e `:89-92` (carried from 27be65e) | PASS |
| C17 | `409` e a tabela de 4 combinações | lote nomeado em 1083151 | `PaymentTest.php:171-175` e `:185-189` - `assertConflict()`, a mensagem e nenhuma cobrança; `backend/app-modules/payment/tests/Unit/Services/PaymentServiceTest.php:19-26` - só "aguarda e sem aprovado" passa; `backend/tests/Feature/PaymentFlowTest.php:29-35` (carried from 27be65e) | PASS |
| C18 | aprovação concorrente perde com `409` | lote nomeado em 1083151 | `PaymentTest.php:203-207` - `assertConflict()`, 1 `approved` e nenhum `payment.payment_approved`; `backend/app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:68-72` (carried from 27be65e) | PASS |
| C19 | `402`, a linha `declined` fica, e o pedido real continua `awaiting_payment` | lote nomeado em 1083151 | `PaymentTest.php:111-119` - `assertStatus(402)`, `PAYMENT_DECLINED`, 1 linha `declined` e nenhum `payment.payment_approved`; `PayOrderUseCaseTest.php:49-56`; `PaymentFlowTest.php:17-20` - `$order->fresh()->status` igual a `AwaitingPayment` (carried from 27be65e) | PASS |
| C20 | `422` antes da porta, para qualquer id, com ou sem porta disponível | lote nomeado em 1083151 | `PaymentTest.php:143-148` - `assertUnprocessable()` e `VALIDATION_FAILED`; `:255-259` - `expect($port->asked)->toBe([])`; `:267-270` - `expect($port->calls)->toBe(0)` | PASS |
| C21 | `503` com o corpo exato, sem cobrar nem gravar; o mesmo numa cópia sem o Ordering | lote nomeado em 1083151 (o caso do módulo). A cópia sem o Ordering é carried from 27be65e: `bash proofs/c21-payment-without-ordering.sh` exit 0, `Tests: 1 passed (7 assertions)` | `PaymentTest.php:280-284` - `assertApiError($response->assertStatus(503), 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.')`, sem cobrança e com `payments` e `outbox_messages` vazias; `proofs/c21-WithoutOrderingTest.php:12-13` e `:20-23` (carried from 27be65e) | PASS |
| C22 | `payment.payment_approved` é lido pela classe do Ordering e move o pedido | lote nomeado em 1083151 | `backend/tests/Feature/Messaging/ConsumerNamesTest.php:29-31` com `:34`; `:43` - `eventOf('payment.payment_approved')` igual a `InboundPaymentApproved::class`; `backend/tests/Feature/Messaging/ConsumerRedeliveryTest.php:57` (carried from 27be65e) | PASS |
| C23 | a reentrega deixa `payment_approved` e 1 `ordering.order_paid` | lote nomeado em 1083151 | `ConsumerRedeliveryTest.php:52-59` - envelope da classe produtora entregue duas vezes, status `PaymentApproved` e `outboxMessages('ordering.order_paid')->toHaveCount(1)` (carried from 27be65e) | PASS |
| C24 | leitora e produtora concordam; um par divergente é acusado | lote nomeado em 1083151 | `backend/tests/Unit/Architecture/OrderEventPayloadTest.php:159`; `:165-166` - `disagreements(...)->toBe([])`; `:171` - a fixture `DisagreeingReader` dá `['payload']` (carried from 27be65e) | PASS |
| C25 | `InboundEvents` só em `Consumers` e `Providers` do Ordering, no código de `app-modules/*/src` | lote nomeado em 1083151 | `ModuleLayersTest.php:53` - `expect(ModuleLayout::inboundEventViolations(base_path('app-modules')))->toBe([])`; `:69` - a fixture `inbound-event-outside-consumers` (carried from 27be65e) | PASS |
| C26 | sem chave em `payments.order_id`; as outras 5 iguais | lote nomeado em 1083151, 5 + 1 casos | `backend/tests/Feature/Database/ModuleMigrationsForeignKeyTest.php:27-31` - `expect(foreignKeyTarget($column))->toBe($references)`; `:37-42` - `foreignKeyTarget('payments.order_id')->toBeNull()` (carried from 27be65e) | PASS |
| C27 | `migrate` sobre um banco da base apaga só `payments_order_id_foreign` | carried from 27be65e: `bash proofs/c27-migrate-existing.sh` exit 0 (`evidence/c27.txt`: a migration nova `[2] Ran`) | `proofs/c27-migrate-existing.sh:23` - a base cria a chave; `:27` - a chave sai depois do `migrate`; `:29` - as 5 chaves continuam; `:31` - nada mais mudou; `:35-36` - a migration nova `Ran` e nada `Pending` | PASS |
| C28 | a migration nova no Payment; 16 nomes congelados | lote nomeado em 1083151, 13 + 1 casos | `backend/tests/Unit/Architecture/ModuleMigrationsTest.php:46-47` com `:29`; `:55` - `expect(migrationNamesIn($paths))->toBe($frozen)` (carried from 27be65e) | PASS |
| C29 | sem o Payment: `migrate:fresh`, `404` e as pastas de testes restantes | `bash proofs/c29-without-payment.sh` em 1083151, exit 0, 58 s. A cópia saiu de `HEAD 1083151` (`evidence/c29.txt:1-2`); `migrate:fresh: ok`; pastas catalog, identity, messaging e shared; `Tests: 135 passed (570 assertions)` | `proofs/c29-without-payment.sh:7` - o `migrate:fresh` da cópia sai com 0; `:15` - `[ "$status" -eq 0 ]`; `:16` - o arquivo de prova rodou; `proofs/c29-WithoutPaymentTest.php:14` - `interface_exists('Modules\Payment\Contracts\OrdersToPay')` false; `:18` - `->assertNotFound()` para um id desconhecido. Na saída: `evidence/c29.txt:275` - `✓ it answers 404 to the payment route without the payment module inst…` | PASS |
| C30 | adaptador do `Integration`: tabela de 4 status e `null` | lote nomeado em 1083151: 1 + 4 + 1 casos | `backend/tests/Feature/Integration/OrdersToPayAdapterTest.php:14` - `toBeInstanceOf(OrdersToPayFromOrdering::class)`; `:21-22` - `toEqual(new OrderToPay(...))` sobre `:24-27`; `:34-35` - `toBeNull()` (carried from 27be65e) | PASS |
| C31 | a forma da porta e do value object | lote nomeado em 1083151 | `backend/tests/Unit/Architecture/IdTypesTest.php:70-75` - interface, o método `findForPayment`, os parâmetros `[['customerId', 'string'], ['orderId', 'string']]`, o retorno `'?'.OrderToPay::class` e o construtor (carried from 27be65e) | PASS |
| C32 | `401` sem sessão; `429` na 21ª tentativa | lote nomeado em 1083151 | `PaymentTest.php:292` - `assertUnauthorized()`; `:360-364` - 20 respostas sem `429` e a 21ª com `assertTooManyRequests()` | PASS |
| C33 | os 4 documentos descrevem o piloto, sem afirmação velha nenhuma | `bash proofs/c33-docs.sh` em 1083151, exit 0 ("PASS: the four documents describe the pilot, with no stale claim and no broken link") | A linha da rodada 2 foi reescrita: `docs/domain-analysis.md:588` - "(como `payments` era até a isolação dos módulos tirar aquela chave)". A releitura não achou mais nenhuma afirmação de chave em `payments.order_id`, de testes só em `backend/tests` ou de 15 migrations (lista em Binding sources). A prova pega a forma: `proofs/c33-docs.sh:25` - `r'como `payments`\)'`; `:37-39` - a heurística por linha (`payments` com `restri` e sem `GONE`), morta isolada pelo F3. A contagem de `docs/domain-analysis.md:498` fica fora do claim do C33 e é o achado da linha `AGENTS.md` em Binding sources | PASS |
| C34 | `make test` sai com 0, nada pulado | `bash proofs/c34-suite.sh` em 1083151, exit 0, `Tests: 2254 passed (9264 assertions)`, `Duration: 175.47s` | `proofs/c34-suite.sh:7` - `[ "$code" -eq 0 ]`; `:8` - recusa `failed`, `skipped`, `incomplete`, `risky` e `todo`; `:9` - o teste de log rodou (2 linhas `✓ it logs each payment attempt without the card token` em `evidence/c34-make-test.txt`) | PASS |
| C35 | `make pint` sem arquivo a corrigir | `bash proofs/c35-pint.sh` em 1083151, exit 0 (`PASS ... 495 files`) | `proofs/c35-pint.sh:6` - `[ "$code" -eq 0 ]`, sobre o `pint --test` do `Makefile` | PASS |
| C36 | o smoke de carga passa | carried from 27be65e: `bash proofs/c36-load-smoke.sh` exit 0, com `backoffice`, `checkout` e `storefront` com ✓ (`evidence/c36.txt`). Nenhuma rota, payload ou script de `load-tests/` mudou desde então | `proofs/c36-load-smoke.sh:10` - `[ "$code" -eq 0 ]`; `:11` - o `bootstrap/cache` não muda | PASS |
| C37 | o `Integration` só usa fachadas; o `ModuleBoundariesTest` fica verde | lote nomeado em 1083151, com o `ModuleBoundariesTest` inteiro (`PASS Tests\Unit\Architecture\ModuleBoundariesTest`) | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:598` - `expect($named)->not->toBeEmpty()`; `:601-602` - cada classe nomeada está em `facadeOf($module)` ou `vocabularyOf($module)` (carried from 27be65e) | PASS |
| C38 | a `PaymentFactory` não cria pedido | lote nomeado em 1083151 | `PaymentTest.php:329-332` - 1 linha em `payments`, 0 em `orders`, `RecordId::isCanonical($payment->order_id)` e `$payment->order_id[14]` igual a `'7'` | PASS |
| C39 | sem o Payment, um pedido em `awaiting_payment` continua assim depois da tentativa (`404`) e do relay, sem `payment.payment_approved` | `bash proofs/c29-without-payment.sh` em 1083151, exit 0 (a mesma execução do C29). `evidence/c29.txt:276` - `✓ it keeps an order awaiting payment without the payment module insta…` | `proofs/c29-without-payment.sh:17` - `grep -qE '✓ it keeps an order awaiting payment without the'` ou `fail`; `proofs/c29-WithoutPaymentTest.php:27` - `->assertNotFound()` para o pedido do próprio cliente; `:30` - `expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)`; `:31` - `DB::table('outbox_messages')->where('name', 'payment.payment_approved')->count()` igual a 0. O F5 mata a asserção de status. Lacuna de precisão (abaixo): o relay da `:28` esvazia um outbox que já está vazio | PASS |

## Coverage

As linhas `estados do slice Payment ↔ Ordering` e `documentos (4)` foram recalculadas em 1083151, porque a correção tocou a fonte de cada uma. As outras vêm de 27be65e (ou de 8af75b1, marcadas): nenhum arquivo de código mudou, e a fonte delas também não.

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| arquivos movidos (27) (carried from 27be65e) | a tabela *Arquivos movidos* do plano | os 27, por `c01-moved-files.sh:35-36` (rodado de novo em 1083151) | - |
| módulos com testes que sobem sozinhos (5) (carried from 27be65e) | o AC 4 e `ls backend/app-modules/*/tests` | identity 55 · messaging 60 · shared 6 · catalog 12 · payment 49, todos por C6 | - |
| tipos de teste num módulo (2) (carried from 27be65e) | `backend/phpunit.xml:8-15` e `backend/tests/Pest.php:17-25` | Feature `FeatureHarnessTest.php:13` · Unit `UnitHarnessTest.php:12` (C3) | - |
| status de `POST /api/orders/{order}/payment` (8) (carried from 27be65e) | o `Surface` do plano, a rota, o use case e o form request | 202 `PaymentTest.php:46` · 401 `:292` · 402 `:111` · 404 `:231` · 409 `:171` · 422 `:143` · 429 `:364` · 503 `:280` | - |
| regra "pode ser pago" (4) (carried from 27be65e) | `PaymentService.php:21` | as 4 linhas de `PaymentServiceTest.php:23-26` (C17) | - |
| `OrderStatus` -> `awaitingPayment` (4) (carried from 27be65e) | o enum `OrderStatus.php:9-12` | `OrdersToPayAdapterTest.php:24-27` (C30) | - |
| status que recusam o pagamento com o Ordering real (3 + já aprovado) (carried from 27be65e) | o AC 13 e o enum | `PaymentFlowTest.php:35` e `PaymentTest.php:179-189` | - |
| respostas do adaptador (3) (carried from 27be65e) | `OrdersToPayFromOrdering.php:22-29` | `OrdersToPayAdapterTest.php:21-22`, `:34` e `:35` (C30) | - |
| chaves estrangeiras entre módulos (5 + `payments.order_id` sem chave) (carried from 27be65e) | o `pg_constraint` de um banco da base e as migrations do `HEAD` | `ModuleMigrationsForeignKeyTest.php:27-31` e `:37-42`; `c27-migrate-existing.sh:27` e `:29` | - |
| nomes de evento inscritos (5) (carried from 27be65e) | o `EventSubscriptionRegistry` em execução | `OrderEventPayloadTest.php:159`, laço `:161-167` (C24) | - |
| portas da Landing (7) (carried from 27be65e) | a tabela `Landing` do plano | 1 C11, C37 · 2 C31 · 3 C22, C24, C25 · 4 C21 · 5 C26, C27, C28 · 6 C1, C3, C7 · 7 C10 | - |
| entidades da Relations (1) (carried from 27be65e) | o `erDiagram` do plano | `orders` 1:N `payments` sem chave: C26, C27 | - |
| configurações de remoção (3) (carried from 27be65e; o C29 rodou de novo em 1083151) | os critérios 4, 16 e 22 | C6 (5 execuções) · C21 · C29 | - |
| regras de arquitetura com violação proposital (4) (carried from 27be65e) | os ACs 6, 10, 19 e 20 | `ModuleLayersTest.php:67` · `:47-48` · `:69` · `OrderEventPayloadTest.php:171` | - |
| estados do slice Payment ↔ Ordering (7 linhas, 11 membros) (verified at 1083151) | a tabela de estados do design (`.design/module-isolation.md:154-162`), a fonte vinculante, e não a linha do `checks.md` | aprovado: pagamento e evento na mesma transação C14, C15 · o Ordering lê com a classe dele e marca pago C22, C23 · `202` C14 · desconhecido ou de outro cliente `404` C16 · não aguarda `409` C17 · recusado `402` e linha gravada C19 · Ordering ausente `503`, nada gravado C21 · Payment ausente: rota some `404` C29 (`c29-WithoutPaymentTest.php:18`), pedidos ficam em `awaiting_payment` C39 (`:30`) e pastas restantes passam C29 (`c29-without-payment.sh:15`) · suíte do Payment sem o Ordering C6 payment e C21 | - |
| documentos (4) (verified at 1083151) | o AC 24 e o claim do C33, sobre os 4 arquivos relidos no `HEAD` | `AGENTS.md` C33 · `README.md` C33 · `docs/domain-analysis.md` C33 (a `:588` corrigida) · `.design/module-isolation.md` C33 | - |
| startup: ligação de `OrdersToPay` (3 montagens) (carried from 27be65e) | `IntegrationServiceProvider.php:23-27`, `bootstrap/cache/packages.php:110` e `integration/composer.json:17` | harness `OrdersToPayAdapterTest.php:14` · app sem o Ordering `c21-WithoutOrderingTest.php:13` · app de dev e produção pela mesma descoberta | - |
| startup: inscrição da classe leitora (2 montagens) (carried from 27be65e) | `OrderingServiceProvider.php:20` e `:51` | harness `ConsumerNamesTest.php:43` · workers pelo mesmo `boot()` | - |

A linha do `checks.md` conta 9 membros e não cita o "o Ordering o lê com a classe dele e marca o pedido como pago" da linha do pagamento aprovado. O membro tem prova (C22 e C23), então isso não é lacuna.

Linhas `Swept` que resolvem para algo que já existia (carried from 27be65e), conferidas no código:

- **authorization:** `auth:customer` em `payment/routes/api.php:9`.
- **concurrency:** o índice único parcial em `create_payments_table.php:39`.
- **observability:** o teste de log em `PaymentTest.php:335-355`, que rodou de novo no C34 em 1083151 (2 linhas).
- **data lifecycle:** o `down()` vazio em `2026_10_21_000001_drop_payments_order_id_foreign.php:24`.
- **Observable, limite:** `throttle:20,1,payment` em `routes/api.php:10`.

Lacunas de precisão (nenhuma muda o veredito sozinha):

- **C39, nova (verified at 1083151).** O claim diz que o pedido continua em `awaiting_payment` depois de o relay esvaziar o outbox. Mas, no caso, o outbox já está vazio antes do `relayUntilIdle()` (`c29-WithoutPaymentTest.php:28`):
  - os pedidos vêm de factories, e só os use cases gravam no outbox (`rg "outbox->record"` em `app-modules/*/src`);
  - por isso nenhum consumidor do Ordering roda nesse caso;
  - o F4 (o consumidor `MarkOrderAsAwaitingPayment` também marca o pedido como pago) passa pelo C39, e só o C34 o mata, com o Payment instalado.

  Não há hoje um ponto em que uma regressão possa depender da ausência do Payment: o Ordering não pode nomear o Payment (C12), e o `Integration` só liga a porta do Payment. Por isso a lacuna não reprova. Duas saídas resolvem: criar o pedido pelo caminho do `ordering.order_placed`, para o relay ter o que entregar, ou tirar o relay do claim.
- **C33, heurística (verified at 1083151).** A regra de `proofs/c33-docs.sh:27` e `:37-39` tem dois limites:
  - isenta qualquer linha com `isola`, `nenhuma`, `não tem`, `saiu` e outras palavras;
  - não pega uma afirmação de chave sem a palavra `restri`.

  Uma conferência estática, com strings e não com arquivos, mostra que passam pela prova:
  - "restrito na exclusão, como em `payments`, que o módulo isolado mantém";
  - "`payments.order_id` aponta para `orders` com chave estrangeira";
  - a frase da `:498`.

  A releitura não achou nenhuma linha assim no `HEAD`, e o achado da `:498` está em Binding sources.
- **C24 e AC 19, C16 (carried from 8af75b1, sem efeito no veredito):** a fixture prova só divergência de chaves, e a de versão foi provada pelo mutante 4 da rodada 1. O caso do módulo para id desconhecido afirma só o status, e o corpo vem do `CustomerRecordRoutesTest.php:89`.

## Test policy rows

carried from 27be65e: a linha classifica arquivos de código, e nenhuma correção depois da rodada 1 tocou um.

O `checks.md` tem a seção `Test policy` com "Não se aplica", sem linhas. Por isso vale a convenção do repositório.

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| "Não se aplica": decisão nova provada na própria camada, um caso por linha da tabela de decisão (convenção do repositório) | `integration/src/Payment/OrdersToPayFromOrdering.php`, `payment/src/Core/Services/PaymentService.php` e `integration/src/Providers/IntegrationServiceProvider.php` | o adaptador com 4 status (C30), a regra com 4 combinações (C17) e os 3 ramos do provider (C30, C21 e C29); C30, C17 e C29 rodaram de novo em 1083151 | yes |

## Faults injected

### Verified at 1083151: as superfícies criadas pela correção

A correção criou duas superfícies:

- em `proofs/c33-docs.sh`: os padrões `como \`payments\`\)` e `Hoje são 29 tipos públicos` (`:25`) e a heurística por linha (`:27` e `:37-39`);
- a asserção do C39 (`proofs/c29-WithoutPaymentTest.php:21-32` e `proofs/c29-without-payment.sh:17`).

**Como os mutantes foram aplicados:**

- **Base:** o `git status --porcelain` da pasta principal foi gravado antes dos worktrees. Só tinha `evidence/c05-test-filter.txt`, `evidence/c29.txt` e `evidence/c34-make-test.txt`, regravados pelas provas desta rodada.
- **Documentos (F1 a F3):**
  - `git worktree add --detach .claude/worktrees/verify-r3-1 HEAD`;
  - controle: o `c33-docs.sh` do worktree, rodado de dentro dele sem mudança, saiu com 0;
  - cada mutante muda um arquivo do worktree, roda o `c33-docs.sh` de dentro dele e volta o arquivo com `git checkout`.
- **C39 (F4 e F5):**
  - `git worktree add --detach .claude/worktrees/verify-r3-2 HEAD`, com o `backend/vendor` e o `backend/.env` copiados da pasta principal (o `lib.sh` copia o `vendor` do worktree para a cópia de remoção);
  - cada mutante virou um commit no `HEAD` destacado do worktree, nunca na branch: o F4 é `3e81500`, e o F5 é `ca4cd7b`, feito depois de `git reset --hard 1083151`. Nenhum ref aponta para eles;
  - para cada um rodou o `bash .specs/features/module-isolation-pilot/proofs/c29-without-payment.sh` do próprio worktree, que monta a cópia sem o Payment a partir do `HEAD` dele (`evidence/c29.txt` do worktree: "Preparing worktree (detached HEAD 3e81500)" e "(detached HEAD ca4cd7b)");
  - o F4 também rodou `make test-filter` no worktree, com o Payment instalado.

**Depois:**

- `make test-db-drop` rodou em cada worktree e na cópia `removal-without-payment` que o F5 deixou ao falhar; foram apagados os bancos `ecommerce_testing_removal_without_payment` e `ecommerce_testing_verify_r3_2`;
- a cópia e os dois worktrees foram removidos, e `git worktree list` voltou às 11 entradas de antes;
- nenhum banco `verify_r3` ou `removal` ficou no PostgreSQL;
- o `porcelain` da pasta principal ficou idêntico ao gravado antes;
- `git stash list` continuou vazio, e nenhum `git stash` foi usado.

| Mutation | Location | Killed |
| --- | --- | --- |
| F1: volta a linha da rodada 2, "restrito na exclusão (como `payments`)" (o arquivo de `8a99282`) | `docs/domain-analysis.md:588`, prova `proofs/c33-docs.sh:25` e `:37-39` | yes. Exit 1: "stale claim 'como `payments`)'" e "docs/domain-analysis.md:588: payments still described as restricted" |
| F2: volta a contagem antiga do README, "Hoje são 29 tipos públicos" (o arquivo de `8a99282`) | `README.md:1057`, prova `proofs/c33-docs.sh:25` | yes. Exit 1: "README.md: stale claim 'Hoje são 29 tipos públicos'" |
| F3: paráfrase que só a heurística alcança, "restrito na exclusão (como a de `payments`)" | `docs/domain-analysis.md:588`, prova `proofs/c33-docs.sh:37-39` | yes. Exit 1: só "docs/domain-analysis.md:588: payments still described as restricted" (o padrão exato não casa) |
| F4: o consumidor `MarkOrderAsAwaitingPayment` também roda o `MarkOrderAsPaidUseCase`, então o pedido sai de `awaiting_payment` sem pagamento | `backend/app-modules/ordering/src/Core/Consumers/MarkOrderAsAwaitingPayment.php:20`, provas `proofs/c29-without-payment.sh` (C39) e o C34 | yes, pelo C34 e não pelo C39. A `c29-without-payment.sh` do worktree saiu com 0 (`✓ it keeps an order awaiting payment…`, 135 passed): o caso do C39 não entrega evento nenhum. O `make test-filter` no worktree, filtrando `moves a placed order to awaiting payment` e `redelivers OrderPlaced to MarkOrderAsAwaitingPayment`, saiu com 2: `OrderStatusFlowTest.php:59` e `ConsumerRedeliveryTest.php:31` falharam, e os dois rodam no `make test` do C34. Ver a lacuna de precisão do C39 |
| F5: o Ordering responde `POST orders/{order}/payment`, marca o pedido como pago pelo `MarkOrderAsPaidUseCase` e devolve `404` | `backend/app-modules/ordering/routes/api.php:17-20` (rota nova), prova `proofs/c29-without-payment.sh` (C39) | yes. Exit 1: `⨯ it keeps an order awaiting payment…` em `WithoutPaymentProofTest.php:30` (`expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)`), `Tests: 1 failed, 134 passed`. O caso do C29, com id desconhecido, continuou passando: a asserção de status do C39 é a que pega |

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

`make test` (`proofs/c34-suite.sh`, no `HEAD` `1083151`): 2254 passed, 0 failed, 0 skipped (9264 assertions, 175 s).

`python3 .claude/skills/tlc-spec-lean/scripts/validate_verification.py module-isolation-pilot --root /Users/helterdias/Documents/POC/laravel-module-ecomerce`: exit 1 (`verdict is FAIL - route the ranked gaps back as fixes, then re-verify`), o esperado para um relatório FAIL.

### Ranked gaps

1. **Uncovered pela regra de documentação do `AGENTS.md` (bloqueia).**
   - **O quê:** a `docs/domain-analysis.md:498` diz que "depois da HEL-6 são 29 e 28" (tipos públicos e privados). No `HEAD` são 30 e 28, porque o piloto pôs o `OrderToPay` no vocabulário do Payment pela porta `OrdersToPay`.
   - **Origem:** é a mesma contagem que a rodada 2 achou no `README.md`. A correção só ajustou o README, e o padrão novo da prova (`proofs/c33-docs.sh:25`, `Hoje são 29 tipos públicos`) só casa com aquela frase.
   - **Correção sugerida:** reescrever a frase, por exemplo "depois da HEL-6 eram 29 e 28, e com o `OrderToPay` da isolação dos módulos são 30 e 28". Ampliar o padrão para a contagem, e não para a frase: `\b29 tipos públicos` e `são 29 e 28`.
2. **Lacuna de precisão do C39 (não bloqueia).** O relay do caso esvazia um outbox vazio, então um consumidor do Ordering que tire o pedido de `awaiting_payment` passa pelo C39 (F4); só o C34, com o Payment instalado, o pega. Duas saídas resolvem:
   - criar o pedido pelo caminho do `ordering.order_placed` na cópia sem o Payment;
   - ou tirar "e de o relay esvaziar o outbox" do claim.
3. **Lacuna de precisão da heurística do C33 (não bloqueia).** As palavras de isenção em `proofs/c33-docs.sh:27` e a dependência de `restri` deixam passar formas de afirmação velha que a releitura manual não achou no `HEAD`.
