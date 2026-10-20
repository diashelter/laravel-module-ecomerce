# Module Isolation Pilot verification

**Verdict**: FAIL
**Profile**: standard
**Diff range**: be1c84c..27be65e
**Round**: 1 - full
**Verifier**: independent sub-agent (author != verifier)

Verificado em `27be65e` (branch `helterdias/module-isolation-pilot`), na pasta principal, com a pilha de dev no ar. Todas as provas foram rodadas de novo por este Verifier; nenhum resultado do autor foi reaproveitado. Os arquivos de `evidence/` foram regravados pelas provas desta rodada e não foram commitados.

O código entrega o que os checks pedem: 37 dos 38 checks valem no `HEAD`, e os 5 mutantes injetados morreram. O FAIL vem de três achados:

1. **C33 falha.** A `docs/domain-analysis.md` ainda descreve a chave estrangeira de `payments.order_id` como pendência aberta (linha 732) e ainda diz, em quatro lugares (linhas 91, 108, 346 e 674), que o Payment lê o pedido pelo `PayableOrders`/`OrderForPayment` do Ordering. A prova `c33-docs.sh` passa porque as expressões de "afirmação velha" dela (linha 19) são estreitas demais para pegar essas linhas.
2. **Um estado do design sem check.** A linha "Payment ausente" da slice Payment ↔ Ordering diz que "a suíte do Ordering passa". O Ordering não tem suíte de módulo (os testes dele ficaram na raiz pela assunção 1 do plano, não confirmada), e o C29 só cobre Identity, Messaging, Shared e Catalog.
3. **Uma divergência com a Key decision 1.** O design põe a exceção do `Integration` no `ModuleBoundariesTest`, "com uma expectativa por namespace". Os checks C12 e C13 a provam no `ModuleLayersTest`, com uma varredura de arquivos (`ModuleLayout::isolationViolations`). A regra existe, funciona nas duas direções e matou o mutante 5, mas o lugar e a forma não são os do design, e o desvio não está registrado.

## Binding sources

| Source | Opened | Contradiction | Uncovered |
| --- | --- | --- | --- |
| `.design/module-isolation.md`, Key decisions 1 a 7 | yes - lido inteiro no `HEAD`, e o diff `086a77d..27be65e` do arquivo | KD1: "O `ModuleBoundariesTest` o nomeia como a única exceção, com uma expectativa por namespace". C12 e C13 provam a exceção em `backend/tests/Unit/Architecture/ModuleLayersTest.php:36-50`, por `Support/ModuleLayout.php:178-202` (varredura de arquivos que pula `COMPOSITION`). O `ModuleBoundariesTest` só põe o `Integration` em `MODULES` (linha 76) e o prende às fachadas (linhas 585-604), sem regra de isolamento nem expectativa por namespace. O plano (`Impact`, linha dos testes de arquitetura) também previa a regra no `ModuleBoundariesTest`, e nem o design nem o plano registram o motivo da troca | - |
| `.design/module-isolation.md`, slice Module Tests | yes | none | - |
| `.design/module-isolation.md`, slice Payment ↔ Ordering | yes | none | Linha "Payment ausente": "a suíte do Ordering passa". Nenhum check a cobre: o Ordering não tem pasta `tests` de módulo, e o C29 cobre só os módulos que têm uma. O próprio design, editado em `25d37f4`, diz na slice Module Tests que os testes do Ordering esperam a slice da sua travessia, então a linha do design e o parágrafo novo se contradizem |
| `.specs/STATE.md`, AD-001 e AD-002 | yes | none | - |
| `AGENTS.md` (regras do projeto) | yes | none | - |

O que foi conferido contra o design e não é achado:

- **KD2:** a porta `OrdersToPay`/`OrderToPay` é do Payment, no vocabulário dele, e o status completo não atravessa (C31, C30).
- **KD3:** o `503` tem o corpo exato `SERVICE_UNAVAILABLE` + `Este recurso está indisponível no momento.`, e nada fica gravado (C21).
- **KD4:** o evento é lido pelo nome, com a classe `Modules\Ordering\Core\InboundEvents\PaymentApproved`, e a compatibilidade é provada na composição (C22, C23, C24).
- **KD5:** a chave sai do `migrate:fresh` e dos bancos já migrados, e a coluna e os índices ficam:
  - `create_payments_table.php:32`: `index('order_id')`, linha sem mudança;
  - `create_payments_table.php:39`: o índice único parcial, também sem mudança, afirmado por `UuidKeysTest.php:121-124`.
- **KD6:** os testes moram no módulo e passam com só ele, o `Shared` e o `Messaging` (C1, C3, C6, C7, C10).
- **KD7:** o job de remoção no CI é a slice Removal Proof, fora do escopo do plano.

O que ficou fora:

- **Identidade de teste de equipe (KD6):** o papel da equipe está no `Out of scope` do plano.
- **Raiz sem módulos de negócio (KD1):** o código de execução da raiz sem nomes de módulos de negócio é da slice Identity. O `bootstrap/app.php:7` ainda nomeia o `EnsureUserIsAdmin` do Identity, o que é esperado nesta rodada.

Três pontos em que o design só concorda com os checks porque foi editado durante a construção, em `25d37f4`:

- **27 arquivos em 5 módulos:** antes, o design falava em 29 arquivos mais os 4 do kernel. A mudança se apoia nas assunções 1 e 3 do plano, não confirmadas, e na 8, confirmada por diashelter.
- **Factories:** só a `PaymentFactory` muda nesta rodada (assunção 2, não confirmada).
- **Helpers de cada módulo:** a linha "Helper de teste de um módulo — só os testes do próprio módulo o usam" vale para os testes de módulo. Um helper de outro módulo não carrega na cópia de remoção, então o C6 o pegaria. A suíte de composição chama os helpers de todos os módulos (`backend/tests/Pest.php:27-30`), o que a KD1 permite ("a suíte de composição ... pode nomear qualquer módulo"). Fica registrado como leitura, sem contar como achado.

## Checks

Prova nomeada, uma só execução para todos os nomes:

```
make test-filter FILTER="<os 53 nomes dos checks>|ModuleBoundariesTest"
```

- O resultado foi exit 0, `Tests: 1031 passed (2343 assertions)`.
- Cada nome aparece individualmente com `✓` na saída.
- Cada nome foi achado na árvore por `rg -n -F`, um arquivo por nome, exceto 2 nomes que existem nos dois lugares, o módulo e a composição.

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | os 27 arquivos estão no módulo e nenhum em `backend/tests` | `bash proofs/c01-moved-files.sh` exit 0 ("27 files in their modules") | `proofs/c01-moved-files.sh:35` - `[ -f "$new" ] \|\| fail`; `:36` - `[ -e "$old" ] && fail`; `:40` - `[ "$count" -eq 27 ]`. A lista do script, comparada mecanicamente com a tabela *Arquivos movidos* do plano, dá 27 pares nos dois lados, com conjuntos iguais | PASS |
| C2 | um `make test` roda o caso do Payment e o de composição | `bash proofs/c02-make-test.sh` exit 0, `Tests: 2254 passed (9264 assertions)` | `proofs/c02-make-test.sh:6` - `[ "$code" -eq 0 ]`; `:8` - `grep '✓ it approves the fake approved card'`; `:10` - `grep '✓ it shows the delivery estimate after the payment is'`; casos em `backend/app-modules/payment/tests/Unit/Gateways/FakePaymentGatewayTest.php:13` e `backend/tests/Feature/DeliveryEstimateTest.php:5` | PASS |
| C3 | Feature do módulo com `TestCase` e `RefreshDatabase`; Unit sem | lote nomeado, exit 0 | `backend/app-modules/shared/tests/Feature/FeatureHarnessTest.php:12-14` - `expect($this)->toBeInstanceOf(TestCase::class)->and(class_uses_recursive($this))->toHaveKey(RefreshDatabase::class)->and(Schema::hasTable('migrations'))->toBeTrue()`; `backend/app-modules/shared/tests/Unit/UnitHarnessTest.php:11-12` - `->not->toHaveKey(RefreshDatabase::class)` | PASS |
| C4 | `Makefile` e CI iguais aos da base | `bash proofs/c04-unchanged-commands.sh` exit 0 | `proofs/c04-unchanged-commands.sh:5` - `git diff --quiet be1c84c HEAD -- "$file" \|\| fail`, para o `Makefile` e o `.github/workflows/ci.yml` inteiros, o que é mais forte que o claim | PASS |
| C5 | o filtro roda exatamente 1 teste, o do Payment em `tests/Unit` | `bash proofs/c05-test-filter.sh` exit 0, `Tests: 1 passed` | `proofs/c05-test-filter.sh:8` - `grep -qE 'Tests: +1 passed'`; `:9` - `grep 'PASS +Appmodules\\payment\\tests\\Unit\\Gateways\\FakePaymentGatewayTest'` | PASS |
| C6 | cada um dos 5 módulos migra e passa sozinho | `bash proofs/c06-module-alone.sh` para `identity`, `messaging`, `shared`, `catalog` e `payment`, todos com exit 0 | `proofs/c06-module-alone.sh:15` - `migrate:fresh \|\| fail`; `:22` - `[ "$status" -eq 0 ]`; `:24` - recusa `skipped\|incomplete\|risky\|todo`. Por módulo:<br>- identity: 55 passed;<br>- messaging: 60;<br>- shared: 6;<br>- catalog: 12;<br>- payment: 49.<br>Os números batem com os testes de cada módulo no `make test` completo (55/60/6/12/49), então cada pasta rodou inteira | PASS |
| C7 | `Modules\<Módulo>\Tests\` no pacote, nunca na raiz | lote nomeado, 11 + 1 casos | `backend/tests/Unit/Architecture/ModulePackagesTest.php:86-87` - `$expected["Modules\\{$module}\\Tests\\"] = 'tests/'` só quando a pasta existe; `:95` - `->and($manifest['autoload']['psr-4'])->toBe($expected)`; `:106` - `expect(array_filter($namespaces, fn ($namespace) => str_starts_with($namespace, 'Modules\\')))->toBe([])` | PASS |
| C8 | teste de módulo que nomeia outro módulo de negócio é acusado | lote nomeado: 11 linhas mais as 7 fixtures | `backend/tests/Unit/Architecture/ModuleLayersTest.php:33` - `expect(ModuleLayout::testsViolations(...))->toBe([])` para os 11 pacotes; `:61` com `:67` - `expect($detect(...))->not->toBe([])` na fixture `module-test-uses-business` | PASS |
| C9 | as 628 declarações da base continuam pelo nome | `bash proofs/c09-test-count.sh` exit 0: 628 na base e 654 no `HEAD`; 2 renomeadas e 1 substituída | `proofs/c09-test-count.sh:17` - `[ "$base" -eq 628 ]`; `:31` - `[ -z "$lost" ]`; `:32` - `[ "$now" -ge 627 ]` | PASS |
| C10 | identidade de teste só com o id, UUID v7, no guard `customer` | lote nomeado | `backend/app-modules/shared/tests/Feature/TestIdentityTest.php:13-16` - `RecordId::isCanonical($first)`, `$first[14]` igual a `'7'`, `auth('customer')->id()` igual a `$first`, `GenericUser`; `:20-21` - um id novo a cada chamada; `:25-26` - o id escolhido | PASS |
| C11 | 11 pacotes, manifesto do `integration`, tipo `COMPOSITION` | lote nomeado | `ModulePackagesTest.php:16-17` - `->toBe([... 'identity', 'integration', 'inventory' ...])`; `:96` - providers `toBe(["Modules\\{$module}\\Providers\\{$module}ServiceProvider"])`, na linha `Integration`; `:110-113` - `COMPOSITION toBe(['Integration'])`, fora de `BUSINESS` e de `INFRASTRUCTURE` | PASS |
| C12 | nada de `Modules\Ordering` no payment, nem de `Modules\Payment` no ordering | lote nomeado; conferido também por `rg 'Modules\\+Ordering' app-modules/payment` e `rg 'Modules\\+Payment' app-modules/ordering`, os dois com 0 ocorrências | `ModuleLayersTest.php:37` - `expect(ModuleLayout::isolationViolations(base_path('app-modules'), 'Payment'))->toBe([])` | PASS |
| C13 | só o `Integration` nomeia o Payment; a regra falha nas duas direções | lote nomeado, 1 + 3 casos | `ModuleLayersTest.php:41` - `->toBe([])` para `ISOLATED`; `:45` - `expect(isolationViolations(...fixture...) !== [])->toBe($violates)`, com as linhas `:47-49`:<br>- `payment-names-ordering`: true;<br>- `ordering-names-payment`: true;<br>- `integration-names-both`: false | PASS |
| C14 | `202`, a forma do corpo, a linha `approved` e o evento | lote nomeado: 4 casos do módulo e 1 de composição | `backend/app-modules/payment/tests/Feature/PaymentTest.php:46-50` - `assertAccepted()`, `assertJsonPath('data.order_id', $order->id)`, `('data.status', 'approved')`, `('data.amount_cents', 12345)` e a mensagem; `:60` - `toEqualCanonicalizing(['id', 'order_id', 'status', 'amount_cents'])`; `:81-83` - 1 linha `approved` com 12345; `:90-91` - 1 mensagem com payload `['order_id' => $order->id]`; `backend/tests/Feature/DeliveryEstimateTest.php:21` - `->assertAccepted()` com o Ordering real | PASS |
| C15 | falha no outbox não deixa aprovado nem mensagem | lote nomeado | `PaymentTest.php:100-103` - `assertStatus(500)` e `SERVER_ERROR`, com `approved` 0 e `outbox_messages` 0; `backend/tests/Feature/Outbox/OrderEventsOutboxTest.php:48-50` - o mesmo com o Ordering real | PASS |
| C16 | `404` para pedido desconhecido ou de outro cliente, e a porta é consultada com o cliente | lote nomeado: 3 casos do módulo e 4 de composição | `PaymentTest.php:69-72` - `assertNotFound()`, `payments` 0, `charges` vazio; `:231-236` - `assertApiError(..., 'NOT_FOUND', 'Recurso não encontrado.')` para um pedido aguardando e um não aguardando, `charges` vazio; `:246` - `expect($port->asked)->toBe([[auth('customer')->id(), $order->id]])`; `backend/tests/Feature/Account/CustomerRecordRoutesTest.php:79-81` e `:89-92` - a busca real com o dono | PASS |
| C17 | `409` e a tabela de 4 combinações | lote nomeado: 1 + 1 casos do módulo, 4 de unidade e 3 de composição pelo mesmo nome | `PaymentTest.php:171-175` - `assertConflict()`, a mensagem e `charges` vazio; `:185-189` - o mesmo para um pedido já aprovado; `backend/app-modules/payment/tests/Unit/Services/PaymentServiceTest.php:19-26` - só `[true, false, true]` passa, as outras 3 lançam `BusinessRuleException` com a mensagem; `backend/tests/Feature/PaymentFlowTest.php:29-35` - os 3 status reais. Precisão: o check cita `.../tests/Unit/PaymentServiceTest.php`, mas o arquivo está em `.../tests/Unit/Services/` | PASS |
| C18 | aprovação concorrente perde com `409` | lote nomeado | `PaymentTest.php:203-207` - `assertConflict()`, 1 `approved`, outbox vazio; `backend/app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:68-72`. Precisão: o check cita `.../tests/Feature/PayOrderUseCaseTest.php` | PASS |
| C19 | `402`, a linha `declined` fica, e o pedido real continua `awaiting_payment` | lote nomeado | `PaymentTest.php:111-119` - `assertStatus(402)` e `PAYMENT_DECLINED`, 1 linha `declined`, sem `payment.payment_approved`; `PayOrderUseCaseTest.php:49-56`; `backend/tests/Feature/PaymentFlowTest.php:17-20` - `assertStatus(402)` e `$order->fresh()->status` igual a `AwaitingPayment` | PASS |
| C20 | `422` antes da porta, para qualquer id, com ou sem porta disponível | lote nomeado | `PaymentTest.php:143-148` - `assertUnprocessable()` e `VALIDATION_FAILED`, `charges` vazio; `:255-259` - `expect($port->asked)->toBe([])` para o pedido de outro cliente; `:267-270` - `expect($port->calls)->toBe(0)` com a porta indisponível | PASS |
| C21 | `503` com o corpo exato, sem cobrar nem gravar; o mesmo numa cópia sem o Ordering | lote nomeado e `bash proofs/c21-payment-without-ordering.sh` exit 0 (`Tests: 1 passed (7 assertions)`, cópia sem os 7 outros módulos de negócio) | `PaymentTest.php:280-284` - `assertApiError($response->assertStatus(503), 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.')`, que é o `assertExactJson` com `request_id` igual ao `X-Request-ID` (`backend/app-modules/shared/tests/Helpers.php:48-53`), mais `charges` vazio e `payments` e `outbox_messages` em 0; `proofs/c21-WithoutOrderingTest.php:12-13` - `interface_exists(PayableOrders)` false e `app(OrdersToPay::class)` instância de `UnavailableOrdersToPay`; `:20-23` - o mesmo 503 e nada gravado | PASS |
| C22 | `payment.payment_approved` é lido pela classe do Ordering e move o pedido | lote nomeado | `backend/tests/Feature/Messaging/ConsumerNamesTest.php:29-31` com a linha `:34` (`'ordering.mark_order_as_paid'`, `MarkOrderAsPaid`, `'payment.payment_approved'`); `:43` - `eventOf('payment.payment_approved')` igual a `InboundPaymentApproved::class`; `backend/tests/Feature/Messaging/ConsumerRedeliveryTest.php:57` - o status vai a `PaymentApproved` | PASS |
| C23 | a reentrega deixa `payment_approved` e 1 `ordering.order_paid` | lote nomeado | `ConsumerRedeliveryTest.php:52-59`:<br>- envelope montado de `Modules\Payment\Events\PaymentApproved` e entregue duas vezes pelo `EventDelivery`;<br>- `status` `PaymentApproved`;<br>- `outboxMessages('ordering.order_paid')->toHaveCount(1)`;<br>- total de mensagens 1.<br>O teste não mudou no diff, mas passa pela leitora nova: o mutante 4 o derrubou | PASS |
| C24 | leitora e produtora concordam; um par divergente é acusado | lote nomeado | `backend/tests/Unit/Architecture/OrderEventPayloadTest.php:159` - os 5 nomes inscritos; `:165-166` - `disagreements($producer, $reader, sampleOf($producer))->toBe([])` para cada um; `:171` - a fixture `DisagreeingReader` dá `['payload']` | PASS |
| C25 | `InboundEvents` só em `Consumers` e `Providers` do Ordering | lote nomeado | `ModuleLayersTest.php:53` - `expect(ModuleLayout::inboundEventViolations(base_path('app-modules')))->toBe([])`; `:69` - a fixture `inbound-event-outside-consumers`. Falha de precisão no claim: ele diz "nenhum arquivo", mas a regra varre só `app-modules/*/src` (`ModuleLayout.php:214`). Três testes de composição nomeiam a classe, e o C22 e a KD1 os permitem: `ConsumerNamesTest.php:14`, `OrderStatusFlowTest.php:22` e `OrderEventsOutboxTest.php:12` | PASS |
| C26 | sem chave em `payments.order_id`; as outras 5 iguais | lote nomeado, 5 + 1 casos | `backend/tests/Feature/Database/ModuleMigrationsForeignKeyTest.php:25`, com as linhas `:27-31` - `expect(foreignKeyTarget($column))->toBe($references)`; `:37-42` - `foreignKeyTarget('payments.order_id')->toBeNull()` e 0 chaves estrangeiras em `payments` | PASS |
| C27 | `migrate` sobre um banco da base apaga só `payments_order_id_foreign` | `bash proofs/c27-migrate-existing.sh` exit 0: 9 chaves na base e 8 no `HEAD`; `2026_10_21_000001_drop_payments_order_id_foreign` está `Ran` | `proofs/c27-migrate-existing.sh:23` - a base cria a chave; `:27` - `grep -qx payments_order_id_foreign <<<"$after" && fail`; `:29` - as 5 chaves continuam; `:31` - antes sem a chave é igual a depois; `:35-36` - a migration nova `Ran` e nada `Pending` | PASS |
| C28 | a migration nova no Payment; 16 nomes congelados | lote nomeado, 13 + 1 casos | `backend/tests/Unit/Architecture/ModuleMigrationsTest.php:46-47` - `is_file(app-modules/{$owner}/database/migrations/{$name}.php)` true e fora da raiz, com `:29` - `'2026_10_21_000001_drop_payments_order_id_foreign' => 'payment'`; `:55` - `expect(migrationNamesIn($paths))->toBe($frozen)`, com 3 do framework e 13 de módulos | PASS |
| C29 | sem o Payment: `migrate:fresh`, `404` e as suítes restantes | `bash proofs/c29-without-payment.sh` exit 0: `Tests: 134 passed (567 assertions)`, com catalog, identity, messaging e shared mais o caso de prova | `proofs/c29-without-payment.sh:7` - `migrate:fresh \|\| fail`; `:15` - `[ "$status" -eq 0 ]`; `:16` - o caso de prova rodou; `proofs/c29-WithoutPaymentTest.php:8` - `interface_exists(OrdersToPay)` false; `:12` - `->assertNotFound()` | PASS |
| C30 | adaptador do `Integration`: tabela de 4 status e `null` | lote nomeado: 1 + 4 + 1 casos | `backend/tests/Feature/Integration/OrdersToPayAdapterTest.php:14` - `toBeInstanceOf(OrdersToPayFromOrdering::class)`; `:21-22` - `toEqual(new OrderToPay($order->id, $user->id, 15990, $awaitingPayment))` sobre as linhas `:24-27`; `:34-35` - `toBeNull()` para outro cliente e para id desconhecido | PASS |
| C31 | a forma da porta e do value object | lote nomeado | `backend/tests/Unit/Architecture/IdTypesTest.php:70-75`:<br>- `isInterface()`;<br>- métodos `['findForPayment']`;<br>- parâmetros `[['customerId', 'string'], ['orderId', 'string']]`;<br>- retorno `'?'.OrderToPay::class`;<br>- construtor `[['id', 'string'], ['customerId', 'string'], ['totalCents', 'int'], ['awaitingPayment', 'bool']]` | PASS |
| C32 | `401` sem sessão; `429` na 21ª tentativa | lote nomeado | `PaymentTest.php:292` - `assertUnauthorized()` e `UNAUTHENTICATED`; `:360-364` - 20 respostas que não são `429`, e a 21ª com `assertTooManyRequests()` | PASS |
| C33 | os 4 documentos descrevem o piloto, sem afirmação velha nenhuma | `bash proofs/c33-docs.sh` exit 0, mas a prova não pega as afirmações velhas que restam | A `docs/domain-analysis.md` ainda afirma o que o piloto desfez:<br>- `:732`, linha aberta da tabela *Pendências* (sem ✅): "Tentativas de pagamento presas ao pedido por `restrictOnDelete` \| FK `payments.order_id` \| ... mas referencia `orders`";<br>- `:91`: "Payment → Ordering: PayableOrders";<br>- `:108`: "o Payment lê o pedido pelo contrato `PayableOrders` (`OrderForPayment` ...)";<br>- `:346`, na matriz: "✅ O Payment lê o pedido pelo contrato `PayableOrders`";<br>- `:674`: "`OrderForPayment` \| Ordering (lido pelo Payment)".<br>As 5 linhas já existiam na base (`be1c84c`, linhas 91, 108, 660 e 717). A lista `stale` da prova (`proofs/c33-docs.sh:19`) só procura 5 frases fixas | FAIL |
| C34 | `make test` sai com 0, nada pulado | `bash proofs/c34-suite.sh` exit 0, `Tests: 2254 passed (9264 assertions)`, 195 s | `proofs/c34-suite.sh:7` - `[ "$code" -eq 0 ]`; `:8` - recusa `failed\|skipped\|incomplete\|risky\|todo`; `:9` - o teste de log rodou (2 linhas) | PASS |
| C35 | `make pint` sem arquivo a corrigir | `bash proofs/c35-pint.sh` exit 0 (495 arquivos) | `proofs/c35-pint.sh:6` - `[ "$code" -eq 0 ]`, sobre `Makefile:179` - `./vendor/bin/pint --test` | PASS |
| C36 | o smoke de carga passa | `bash proofs/c36-load-smoke.sh` exit 0: as 3 jornadas `backoffice`, `checkout` e `storefront` com ✓, e `checks: rate==1` | `proofs/c36-load-smoke.sh:10` - `[ "$code" -eq 0 ]`; `:11` - o `bootstrap/cache` não muda | PASS |
| C37 | o `Integration` só usa fachadas; o `ModuleBoundariesTest` fica verde | lote nomeado, com o `ModuleBoundariesTest` inteiro | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:598` - `expect($named)->not->toBeEmpty()`; `:601-602` - `expect(in_array($class, [...facadeOf($module), ...vocabularyOf($module)], true))->toBeTrue(...)`. O "Payment usando só Shared e Messaging" é cobrado no `ModuleLayersTest.php:37` (C12), não no `ModuleBoundariesTest` | PASS |
| C38 | a `PaymentFactory` não cria pedido | lote nomeado | `PaymentTest.php:329-332` - 1 linha em `payments`, 0 em `orders` (ou nenhuma tabela `orders`), `RecordId::isCanonical($payment->order_id)` e `$payment->order_id[14]` igual a `'7'` | PASS |

## Coverage

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| arquivos movidos (27) | a tabela *Arquivos movidos* do plano, que é o conjunto que o código deve cumprir. Lida mecanicamente e comparada com a lista de `c01-moved-files.sh`: 27 = 27 | os 27, cada um por `c01-moved-files.sh:35-36` | - |
| módulos com testes que sobem sozinhos (5) | o AC 4 do plano e `ls backend/app-modules/*/tests`: catalog, identity, messaging, payment e shared, os mesmos 5 | identity 55 · messaging 60 · shared 6 · catalog 12 · payment 49, todos por C6 | - |
| tipos de teste num módulo (2) | `backend/phpunit.xml:8-15` e `backend/tests/Pest.php:17-25` | Feature com `RefreshDatabase`: `FeatureHarnessTest.php:13` · Unit sem: `UnitHarnessTest.php:12` (C3) | - |
| status de `POST /api/orders/{order}/payment` (8) | o `Surface` do plano, e no código:<br>- `payment/routes/api.php:9-10` (`auth:customer`, `throttle:20,1,payment`);<br>- `PayOrderUseCase.php:43-46` (503, 404, 409 e 402);<br>- o form request (422) | `202` `PaymentTest.php:46` · `401` `:292` · `402` `:111` · `404` `:231` e `CustomerRecordRoutesTest.php:89` · `409` `:171` e `PaymentFlowTest.php:29` · `422` `:143` · `429` `:364` · `503` `:280`. Todos atravessam a rota HTTP. Fora do `Surface`: `500` quando o outbox falha, provado em `:100` (C15) | - |
| regra "pode ser pago" (4) | `PaymentService.php:21`, dois booleanos | as 4 linhas de `PaymentServiceTest.php:23-26` (C17); o mutante 3 derrubou a linha "aguarda e já aprovado" | - |
| `OrderStatus` -> `awaitingPayment` (4) | o enum `ordering/src/Core/Enums/OrderStatus.php:9-12`, com 4 casos | placed · awaiting_payment · payment_approved · delivered, em `OrdersToPayAdapterTest.php:24-27` (C30); o mutante 2 derrubou 2 linhas | - |
| status que recusam o pagamento na rota, com o Ordering real (3 status + já aprovado), o AC 13, sem linha no Coverage do autor | o AC 13 e o enum | placed, payment_approved e delivered em `PaymentFlowTest.php:35`, mais "já aprovado" em `PaymentTest.php:179-189` | - |
| respostas do adaptador (3) | `OrdersToPayFromOrdering.php:22-29` | pedido do cliente `:21-22` · desconhecido `:35` · de outro cliente `:34` (C30) | - |
| chaves estrangeiras entre módulos (5 que ficam, mais `payments.order_id` sem chave), sem linha no Coverage do autor | o `pg_constraint` de um banco migrado na base (`c27-migrate-existing.sh:22`) e as migrations do `HEAD` | as 5 em `ModuleMigrationsForeignKeyTest.php:27-31` e `c27-migrate-existing.sh:29` · `payments.order_id` em `:37-42` e `c27:27` | - |
| nomes de evento inscritos (5), sem linha no Coverage do autor | o `EventSubscriptionRegistry` em execução | os 5 em `OrderEventPayloadTest.php:159`, cada um conferido no laço `:161-167` (C24); o mutante 4 derrubou o `payment.payment_approved` | - |
| portas da Landing (7) | a tabela `Landing` do plano | 1 C11, C37 · 2 C31 · 3 C22, C24, C25 · 4 C21 · 5 C26, C27, C28 · 6 C1, C3, C7 · 7 C10 | - |
| entidades da Relations (1) | o `erDiagram` do plano | `orders` 1:N `payments`, sem chave: C26, C27 | - |
| configurações de remoção (3) | os critérios 4, 16 e 22 | um módulo sozinho C6 (5 execuções) · Payment sem os outros C21 · sem o Payment C29 | - |
| regras de arquitetura com violação proposital (4) | os ACs 6, 10, 19 e 20 | teste nomeando outro módulo `ModuleLayersTest.php:67` · isolamento nas duas direções `:47-48` · classe leitora fora do lugar `:69` · leitora divergente `OrderEventPayloadTest.php:171` | - |
| estados da slice Payment ↔ Ordering (7) | a tabela de estados do design, que é a fonte vinculante | 202 C14 · 404 C16 · 409 C17 · 402 C19 · Ordering ausente C21 · Payment ausente: 404 C29 · suíte do Payment sem o Ordering: C6 payment | "Payment ausente: a suíte do Ordering passa" (sem check; o Ordering não tem suíte de módulo) |
| documentos (4) | o AC 24 | `AGENTS.md` C33 · `README.md` C33 · `.design/module-isolation.md` C33 · `docs/domain-analysis.md` | `docs/domain-analysis.md` (as linhas 91, 108, 346, 674 e 732 contradizem o piloto) |
| startup: ligação de `OrdersToPay` (3 montagens) | os arquivos que montam cada uma:<br>- o provider: `IntegrationServiceProvider.php:23-27`;<br>- a descoberta: `backend/bootstrap/cache/packages.php:110`;<br>- o manifesto: `integration/composer.json:17` | harness de teste `OrdersToPayAdapterTest.php:14` · app sem o Ordering `c21-WithoutOrderingTest.php:13` · app de dev e produção, pela mesma descoberta (`packages.php:110`), sem ramo próprio | - |
| startup: inscrição da classe leitora (2 montagens) | `OrderingServiceProvider.php:20` e `:51`; o `DeliverEvent` e o `events:consume` sobem a mesma aplicação | harness `ConsumerNamesTest.php:43` · workers pelo mesmo `boot()`, e o `EventDelivery` resolve pelo registro (o mutante 4 mostrou isso) | - |

Linhas `Swept` que resolvem para algo que já existia, conferidas no código:

- **authorization:** `auth:customer` em `payment/routes/api.php:9`.
- **concurrency:** o índice único parcial em `create_payments_table.php:39` não mudou e é afirmado em `UuidKeysTest.php:121-124`.
- **observability:** o teste de log em `PaymentTest.php:335-355` rodou no C34 (2 linhas).
- **data lifecycle:** o `down()` vazio em `2026_10_21_000001_drop_payments_order_id_foreign.php:24`.
- **Observable, limite de requisição:** `throttle:20,1,payment` em `routes/api.php:10`.

Lacunas de precisão nos checks (achados sobre os checks, sem efeito no veredito):

- **C17, C18 e C19:** citam caminhos de arquivo que não existem. A prova por nome achou os testes certos.
- **C25:** diz "nenhum arquivo", mas a regra cobre só os `src` dos pacotes.
- **C24 e AC 19:** o AC 19 pede falha em divergência de nome, de versão e de chaves, mas a fixture só prova chaves:
  - a divergência de versão foi provada pelo mutante 4;
  - a de nome não acontece por construção, porque a leitora é achada pelo nome.
- **C16:** o caso do módulo para id desconhecido (`PaymentTest.php:69`) afirma só o status. O corpo vem do `CustomerRecordRoutesTest.php:89` e do `PaymentTest.php:231`.

## Test policy rows

O `checks.md` tem a seção `Test policy`, que diz "Não se aplica", sem linhas. Com isso, quem decide é a convenção do repositório, e o veredito sobre essa declaração é este:

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| "Não se aplica": decisão nova provada na própria camada, um caso por linha da tabela de decisão (convenção do repositório) | `integration/src/Payment/OrdersToPayFromOrdering.php`, `payment/src/Core/Services/PaymentService.php`, `integration/src/Providers/IntegrationServiceProvider.php` | Três provas:<br>- o adaptador, com 4 status em teste Feature de composição (C30);<br>- a regra, com 4 combinações em teste Unit (C17);<br>- os 3 ramos do provider: com o Ordering (C30), sem o Ordering (C21, prova de remoção) e sem o Payment (C29, prova de remoção) | yes |

## Faults injected

Cada mutante foi aplicado num worktree descartável (`git worktree add .claude/worktrees/verify-fault-<n> HEAD`, com `vendor` e `.env` copiados). A prova que o cobre rodou ali com `make test-filter`, no banco `ecommerce_testing_verify_fault_<n>`. Os 5 worktrees e os 5 bancos foram removidos depois.

O `git status --porcelain` da pasta principal ficou idêntico antes e depois. Ele só mostrava os 3 arquivos de `evidence/` regravados pelas provas C2, C5 e C34, antes da injeção. Nenhum `git stash` foi usado.

| Mutation | Location | Killed |
| --- | --- | --- |
| status do `503` trocado por `500` (`HTTP_SERVICE_UNAVAILABLE` -> `HTTP_INTERNAL_SERVER_ERROR`) | `backend/app-modules/shared/src/Exceptions/ServiceUnavailableException.php:26` | yes - `answers 503 when the order port is unavailable` falhou ("Expected response status code [503] but received 500"), C21 |
| tradução do adaptador: `=== OrderStatus::AwaitingPayment` -> `!== OrderStatus::Placed` | `backend/app-modules/integration/src/Payment/OrdersToPayFromOrdering.php:28` | yes - `translates each order status into awaiting payment` com 2 falhas (payment_approved e delivered), C30 |
| regra "pode ser pago" sem o `\|\| $hasApprovedPayment` | `backend/app-modules/payment/src/Core/Services/PaymentService.php:21` | yes - `decides whether an order can be paid` com 1 falha ("aguarda e já aprovado"), C17 |
| versão da classe leitora `1` -> `2` | `backend/app-modules/ordering/src/Core/InboundEvents/PaymentApproved.php:26` | yes - `reads every subscribed event from the payload its producer writes` ("disagrees ... 'version'"), C24, e `redelivers PaymentApproved to MarkOrderAsPaid` ("No subscribed event reads payment.payment_approved version 1"), C23 |
| detector de isolamento sem a direção de entrada (`$incoming = false`) | `backend/tests/Unit/Architecture/Support/ModuleLayout.php:192` | yes - `fails on a deliberate isolation violation` com 1 falha (`ordering-names-payment`), C13 |

O mutante 4 mostra que o `ConsumerRedeliveryTest`, embora não mude no diff, passa pela classe leitora nova.

Ficaram sem mutação, pelo teto de 5:

- a migration de criação: a asserção do C26 em `ModuleMigrationsForeignKeyTest.php:37` lê o `information_schema` direto;
- a ligação de `UnavailableOrdersToPay` no provider: só a prova de remoção do C21 a exercita, e os scripts de remoção criam a cópia a partir do `HEAD` commitado, não de um worktree mutado.

## Gate

`make test` (`proofs/c34-suite.sh`, no `HEAD` `27be65e`): 2254 passed, 0 failed, 0 skipped (9264 assertions, 195 s).

`python3 .claude/skills/tlc-spec-lean/scripts/validate_verification.py module-isolation-pilot --root .`: exit 1 (`verdict is FAIL - route the ranked gaps back as fixes, then re-verify`; 1 error, 0 warnings). É o esperado para um relatório FAIL: o script recusa a conclusão enquanto o veredito for FAIL.

### Ranked gaps

1. **C33 FAIL:** a `docs/domain-analysis.md` ainda afirma a chave estrangeira de `payments.order_id` como pendência aberta e ainda diz que o Payment lê o pedido pelo `PayableOrders`/`OrderForPayment` do Ordering. São as linhas 732, 91, 108, 346 e 674. A prova `proofs/c33-docs.sh:19` não as pega e deveria passar a procurar essas formas.
2. **Uncovered:** a linha "Payment ausente: a suíte do Ordering passa" da slice Payment ↔ Ordering não tem check, e o Ordering não tem suíte de módulo. Corrigir o design, adiando para a slice Fulfillment ↔ Ordering, com o registro da assunção 1, ou acrescentar um check.
3. **Contradiction com a KD1:** a exceção do `Integration` é cobrada no `ModuleLayersTest` por varredura de arquivos, e não no `ModuleBoundariesTest` "com uma expectativa por namespace". O comportamento é equivalente e foi provado (mutante 5 morto). Falta registrar o desvio no design, ou levar a regra ao `ModuleBoundariesTest`. Cita `backend/tests/Unit/Architecture/ModuleLayersTest.php:36-50`.
4. **Precisão (sem efeito no veredito):** os caminhos de C17, C18 e C19 estão errados, e o "nenhum arquivo" do C25 é mais largo que a regra (`backend/tests/Unit/Architecture/Support/ModuleLayout.php:214`).
