# Module Isolation Pilot verification

**Verdict**: FAIL
**Profile**: standard
**Diff range**: be1c84c..8af75b1
**Round**: 2 - scoped
**Verifier**: independent sub-agent (author != verifier)

Verificado em `8af75b1` (branch `helterdias/module-isolation-pilot`), na pasta principal, com a pilha de dev no ar (`db`, `redis`, `rabbitmq` e `api`). A rodada 1 verificou `be1c84c..27be65e` e deu FAIL. A correção é o commit `8af75b1` (diff `0c1ae0e..8af75b1`).

O que a correção tocou:

- markdown: `docs/domain-analysis.md`, `.design/module-isolation.md`, `plan.md` e `checks.md`;
- um script de prova, `proofs/c33-docs.sh`;
- os arquivos de `evidence/` regravados na rodada 1.

Nenhum código mudou: `git diff --stat 27be65e..8af75b1 -- backend Makefile .github docker-compose.yml load-tests` sai vazio.

O que esta rodada fez:

- **Rodou de novo no `HEAD`:**
  - todas as provas por nome, num só `make test-filter`;
  - a suíte inteira (`c34-suite.sh`);
  - as provas de comando `c01`, `c04`, `c05`, `c09`, `c33` e `c35`.
- **Trouxe da rodada 1 (`carried from 27be65e`):** as provas de remoção, de migration e de carga (`c06` ×5, `c21`, `c27`, `c29` e `c36`). Elas exercitam código que a correção não tocou.
- **Não commitou nada.** As provas regravaram `evidence/c05-test-filter.txt` e `evidence/c34-make-test.txt`.

As quatro correções da rodada 1 valem:

- as 6 linhas da `docs/domain-analysis.md` foram reescritas, e a prova do C33 pega cada uma delas: 5 mutantes mortos e 1 conferido de forma estática;
- a linha "Payment ausente" do design não fala mais da suíte do Ordering;
- a Key decision 1 descreve a varredura do `ModuleLayersTest`;
- os caminhos de C17, C18 e C19 e o escopo de C25 batem com a árvore.

O FAIL vem de três achados novos, todos de documentação ou de cobertura do design. Nenhum é de código.

1. **O C33 falha.** A `docs/domain-analysis.md:588` ainda compara a chave `deliveries.order_id` com a de `payments`: "`order_id` único e restrito na exclusão (como `payments`)". Isso afirma que `payments.order_id` restringe a exclusão, ou seja, que tem chave estrangeira com `restrictOnDelete`. A migration do `HEAD` não tem mais essa chave (`create_payments_table.php:21`, `foreignUuid('order_id')` sem `constrained()`). O AC 24 pede que não reste menção à chave de `payments.order_id`. A lista `stale` da prova (`proofs/c33-docs.sh:19-23`) só tem as frases exatas das 6 linhas da rodada 1, então não pega esta.
2. **Um membro do estado "Payment ausente" do design continua sem prova.** O texto é "os pedidos ficam em `awaiting_payment`" (`.design/module-isolation.md:161`). O C29 prova o `404` e as pastas de testes restantes, mas nenhuma prova cria um pedido numa cópia sem o Payment e confere que ele continua aguardando pagamento. A rodada 1 não levantou este membro. Ele está na mesma linha que a rodada 1 reprovou e que a correção editou, por isso foi julgado de novo.
3. **Uma afirmação do README ficou velha com o piloto, sem check que a cubra.** É a regra do `AGENTS.md` ("Documentação desatualizada é tratada como bug"). O `README.md:1057` diz "Hoje são 29 tipos públicos ... e 28 privados". O piloto tornou público o `OrderToPay` (alcançado pela assinatura de `Modules\Payment\Contracts\OrdersToPay`). As funções do `ModuleBoundariesTest` (`facadeOf`, `reachedFrom` e `vocabularyOf`), reproduzidas no container `api` no `HEAD`, contam **30 públicos e 28 privados**.

## Binding sources

verified at 8af75b1 (as linhas que a correção tocou e as que a rodada 1 reprovou); carried from 27be65e (as outras, marcadas)

| Source | Opened | Contradiction | Uncovered |
| --- | --- | --- | --- |
| `.design/module-isolation.md`, Key decisions 1 a 7 (verified at 8af75b1) | yes. Lido inteiro no `HEAD`, mais o diff `0c1ae0e..8af75b1` do arquivo | none. A KD1 (`:77`) diz agora que o `ModuleBoundariesTest` trata o `Integration` "como mais uma origem das regras de fachada" e que o `ModuleLayersTest` o nomeia como o único pacote que pode nomear um módulo isolado, "numa varredura dos arquivos de cada pacote", e registra o motivo da troca. Confere com o código:<br>- `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:76` - `const MODULES = [... 'Integration' ...]`;<br>- `ModuleBoundariesTest.php:585` - `keeps the integration package on the module facades`;<br>- `backend/tests/Unit/Architecture/Support/ModuleLayout.php:185-187` - pula o `COMPOSITION`;<br>- `ModuleLayout.php:189` - `phpFilesIn($package)` varre o pacote inteiro, com `src`, `routes`, `database` e `tests`.<br>O `Impact` do plano (`plan.md:69`) diz o mesmo | - |
| `.design/module-isolation.md`, slice Module Tests (carried from 27be65e) | yes | none | - |
| `.design/module-isolation.md`, slice Payment ↔ Ordering (verified at 8af75b1) | yes. Tabela de estados em `:154-162` | none. A linha "Payment ausente" (`:161`) diz agora "a pasta de testes de cada módulo restante passa", que é o que o C29 roda (catalog, identity, messaging e shared), e registra que o Ordering ainda não tem pasta de testes (assunção 1 do plano) | "Payment ausente: os pedidos ficam em `awaiting_payment`" (`:161`). Nenhum check o cobre: o C29 (`proofs/c29-WithoutPaymentTest.php:12`) só afirma o `404`, sem pedido nenhum. Um caso de composição numa cópia sem o Payment também não existe |
| `.specs/STATE.md`, AD-001 e AD-002 (carried from 27be65e) | yes | none | - |
| `AGENTS.md`, regra "sempre atualizar a documentação" (verified at 8af75b1: os 4 documentos relidos) | yes | none | `README.md:1057` - "Hoje são 29 tipos públicos (...) e 28 privados". No `HEAD` são 30 públicos e 28 privados: o piloto pôs o `Modules\Payment\Core\ValueObjects\OrderToPay` no vocabulário, pela assinatura de `OrdersToPay::findForPayment`. Nenhum check cobre o número |

O que foi relido nos 4 documentos e não é achado:

- **`PayableOrders` e `OrderForPayment` nos 4 documentos:** toda menção atual diz que só o adaptador do `Integration` usa o contrato. São elas:
  - `docs/domain-analysis.md:91-92`, `:108`, `:140`, `:234`, `:346`, `:599`, `:674-675`;
  - `README.md:102`, `:117`, `:123`, `:1053` ("lia ... (hoje pela própria porta `OrdersToPay`)"), `:1107`;
  - `.design/module-isolation.md:5`, `:179`.
- **O `findForPayment(customerId, orderId)` da `:140`** confere com `backend/app-modules/ordering/src/Contracts/PayableOrders.php:19`.
- **Menções históricas que continuam verdadeiras sobre o seu momento:** `docs/domain-analysis.md:241`, `:479`, `:512`, `:579` e `:712`. A `:241` ganhou a frase da isolação.
- **`payments.order_id`:** toda menção atual diz que não há chave:
  - `docs/domain-analysis.md:4`, `:241`, `:346`, `:602`, `:711`, `:733`;
  - `README.md:354`, `:884`, `:898`, `:1028`, `:1111`;
  - `.design/module-isolation.md:189`.
  - A exceção é a `docs/domain-analysis.md:588` (achado 1).
  - O "como o `payments`" do `AGENTS.md:85` fala da linha única (o índice único parcial de pagamento aprovado, `create_payments_table.php:39`), que continua.
- **Testes só em `backend/tests` e 15 migrations:** nenhuma menção restante.
  - Os 16 nomes estão em `AGENTS.md:61`, `README.md:1028`, `docs/domain-analysis.md:602` e `.design/module-isolation.md:5`.
  - As pastas dos módulos estão em `AGENTS.md:97`, `README.md:833`, `:1029`, `:1112` e `docs/domain-analysis.md:659`.
- **Contagem de interfaces:** o `AGENTS.md:63` passou de 19 para 21, o que bate com o `HEAD` (`git grep -l '^interface '` em `app-modules/*/src`: 21; na base, 20). A `docs/domain-analysis.md:573` é o registro datado da HEL-12.

## Checks

As provas foram verificadas em 8af75b1. As citações de arquivos de código vêm de 27be65e, porque nenhum arquivo de código mudou; as dos arquivos que a correção tocou foram atualizadas.

Prova nomeada, uma só execução para todos os nomes:

```
make test-filter FILTER="ModuleBoundariesTest|<os 54 nomes das provas make test-filter do checks.md>"
```

- **Resultado:** exit 0, `Tests: 1032 passed (2347 assertions)`, 52 s.
- **Saída:** cada um dos 54 nomes aparece individualmente com `✓`, e o `ModuleBoundariesTest` aparece como `PASS Tests\Unit\Architecture\ModuleBoundariesTest`. O nome `reads every subscribed event from the payload its producer writes` aparece truncado pelo Pest, na linha `✓ it reads every subscribed event from the payload its producer write…`.
- **Árvore:** cada nome foi achado por `rg -n -F "it('<nome>"` em `backend/tests` e `backend/app-modules/*/tests`. Dois nomes existem no módulo e na composição: `answers 404 to the payment of an unknown order` e `rejects payment for orders that are not awaiting payment`.

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | os 27 arquivos estão no módulo e nenhum em `backend/tests` | `bash proofs/c01-moved-files.sh` em 8af75b1, exit 0 ("27 files in their modules") | `proofs/c01-moved-files.sh:35` - o arquivo novo existe (`[ -f "$new" ]`); `:36` - o antigo não existe (`[ -e "$old" ] && fail`); `:40` - `[ "$count" -eq 27 ]`. A comparação da lista com a tabela do plano (27 = 27) vem de 27be65e | PASS |
| C2 | um `make test` roda o caso do Payment e o de composição | o `make test` do C34 em 8af75b1, exit 0, `Tests: 2254 passed (9264 assertions)`. As 4 expressões de `proofs/c02-make-test.sh:7-10` foram achadas na saída (`evidence/c34-make-test.txt`). O script do C2 não rodou à parte, para não rodar duas suítes | `proofs/c02-make-test.sh:6` - `[ "$code" -eq 0 ]`; `:8` - `✓ it approves the fake approved card`; `:10` - `✓ it shows the delivery estimate after the payment is`. Os casos estão em `backend/app-modules/payment/tests/Unit/Gateways/FakePaymentGatewayTest.php:13` e `backend/tests/Feature/DeliveryEstimateTest.php:5` | PASS |
| C3 | Feature do módulo com `TestCase` e `RefreshDatabase`; Unit sem | lote nomeado em 8af75b1, exit 0 | `backend/app-modules/shared/tests/Feature/FeatureHarnessTest.php:12-14` - `toBeInstanceOf(TestCase::class)`, `class_uses_recursive($this)` com `RefreshDatabase` e `Schema::hasTable('migrations')`; `backend/app-modules/shared/tests/Unit/UnitHarnessTest.php:11-12` - `->not->toHaveKey(RefreshDatabase::class)` (carried from 27be65e) | PASS |
| C4 | `Makefile` e CI iguais aos da base | `bash proofs/c04-unchanged-commands.sh` em 8af75b1, exit 0 | `proofs/c04-unchanged-commands.sh:5` - `git diff --quiet be1c84c HEAD -- "$file"` para o `Makefile` e o `.github/workflows/ci.yml` inteiros | PASS |
| C5 | o filtro roda exatamente 1 teste, o do Payment em `tests/Unit` | `bash proofs/c05-test-filter.sh` em 8af75b1, exit 0, `Tests: 1 passed (4 assertions)` | `proofs/c05-test-filter.sh:8` - `grep -qE 'Tests: +1 passed'`; `:9` - `PASS +Appmodules\\payment\\tests\\Unit\\Gateways\\FakePaymentGatewayTest` | PASS |
| C6 | cada um dos 5 módulos migra e passa sozinho | carried from 27be65e: `bash proofs/c06-module-alone.sh` para identity, messaging, shared, catalog e payment, todos com exit 0. A correção não tocou código nem o script | `proofs/c06-module-alone.sh:15` - o `migrate:fresh` da cópia precisa sair com 0; `:22` - `[ "$status" -eq 0 ]`; `:24` - recusa `skipped`, `incomplete`, `risky` e `todo`. Por módulo:<br>- identity: 55 passed;<br>- messaging: 60;<br>- shared: 6;<br>- catalog: 12;<br>- payment: 49 (`evidence/c06-<módulo>.txt`) | PASS |
| C7 | `Modules\<Módulo>\Tests\` no pacote, nunca na raiz | lote nomeado em 8af75b1: 11 + 1 casos | `backend/tests/Unit/Architecture/ModulePackagesTest.php:86-87` - `$expected["Modules\\{$module}\\Tests\\"] = 'tests/'` só quando a pasta existe; `:95` - `->toBe($expected)`; `:106` - `->toBe([])` para a raiz (carried from 27be65e) | PASS |
| C8 | teste de módulo que nomeia outro módulo de negócio é acusado | lote nomeado em 8af75b1: 11 linhas mais as 7 fixtures | `backend/tests/Unit/Architecture/ModuleLayersTest.php:33` - `expect(ModuleLayout::testsViolations(...))->toBe([])`; `:61` com `:67` - `->not->toBe([])` na fixture `module-test-uses-business` (carried from 27be65e) | PASS |
| C9 | as 628 declarações da base continuam pelo nome | `bash proofs/c09-test-count.sh` em 8af75b1, exit 0: 628 na base e 654 no `HEAD`; 2 renomeadas e 1 substituída | `proofs/c09-test-count.sh:17` - `[ "$base" -eq 628 ]`; `:31` - `[ -z "$lost" ]`; `:32` - `[ "$now" -ge 627 ]` | PASS |
| C10 | identidade de teste só com o id, UUID v7, no guard `customer` | lote nomeado em 8af75b1 | `backend/app-modules/shared/tests/Feature/TestIdentityTest.php:13-16` - `RecordId::isCanonical($first)`, `$first[14]` igual a `'7'` e `auth('customer')->id()` igual a `$first`; `:20-21` e `:25-26` (carried from 27be65e) | PASS |
| C11 | 11 pacotes, manifesto do `integration`, tipo `COMPOSITION` | lote nomeado em 8af75b1 | `ModulePackagesTest.php:16-17` - a lista com `'integration'`; `:96` - o provider do pacote; `:110-113` - `COMPOSITION toBe(['Integration'])` (carried from 27be65e) | PASS |
| C12 | nada de `Modules\Ordering` no payment, nem de `Modules\Payment` no ordering | lote nomeado em 8af75b1 | `ModuleLayersTest.php:37` - `expect(ModuleLayout::isolationViolations(base_path('app-modules'), 'Payment'))->toBe([])` (carried from 27be65e) | PASS |
| C13 | só o `Integration` nomeia o Payment; a regra falha nas duas direções | lote nomeado em 8af75b1, 1 + 3 casos | `ModuleLayersTest.php:41` - `->toBe([])`; `:45` - `->toBe($violates)`, com as linhas `:47-49`:<br>- `payment-names-ordering`: true;<br>- `ordering-names-payment`: true;<br>- `integration-names-both`: false<br>(carried from 27be65e) | PASS |
| C14 | `202`, a forma do corpo, a linha `approved` e o evento | lote nomeado em 8af75b1: 4 casos do módulo e 1 de composição | `backend/app-modules/payment/tests/Feature/PaymentTest.php:46-50` - `assertAccepted()`, `data.order_id`, `data.status` `approved`, `data.amount_cents` 12345 e a mensagem; `:60` - as 4 chaves; `:81-83` - 1 linha `approved`; `:90-91` - o payload `['order_id' => $order->id]`; `backend/tests/Feature/DeliveryEstimateTest.php:21` - `->assertAccepted()` (carried from 27be65e) | PASS |
| C15 | falha no outbox não deixa aprovado nem mensagem | lote nomeado em 8af75b1 | `PaymentTest.php:100-103` - `assertStatus(500)`, nenhuma linha `approved` e nenhuma mensagem no outbox; `backend/tests/Feature/Outbox/OrderEventsOutboxTest.php:48-50` (carried from 27be65e) | PASS |
| C16 | `404` para pedido desconhecido ou de outro cliente, e a porta é consultada com o cliente | lote nomeado em 8af75b1: 3 casos do módulo e 4 de composição | `PaymentTest.php:69-72` - `assertNotFound()`; `:231-236` - `assertApiError(..., 'NOT_FOUND', 'Recurso não encontrado.')`; `:246` - `expect($port->asked)->toBe([[auth('customer')->id(), $order->id]])`; `backend/tests/Feature/Account/CustomerRecordRoutesTest.php:79-81` e `:89-92` (carried from 27be65e) | PASS |
| C17 | `409` e a tabela de 4 combinações | lote nomeado em 8af75b1 | `PaymentTest.php:171-175` e `:185-189` - `assertConflict()`, a mensagem e nenhuma cobrança; `backend/app-modules/payment/tests/Unit/Services/PaymentServiceTest.php:19-26` - só "aguarda e sem aprovado" passa; `backend/tests/Feature/PaymentFlowTest.php:29-35` (carried from 27be65e). O caminho do check foi corrigido (`checks.md:83`) e é o do arquivo (`rg`: `PaymentServiceTest.php:16`) | PASS |
| C18 | aprovação concorrente perde com `409` | lote nomeado em 8af75b1 | `PaymentTest.php:203-207` - `assertConflict()`, 1 `approved` e o outbox vazio; `backend/app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:68-72` (carried from 27be65e). O caminho do check foi corrigido (`checks.md:87`) e é o do arquivo (`rg`: `PayOrderUseCaseTest.php:59`) | PASS |
| C19 | `402`, a linha `declined` fica, e o pedido real continua `awaiting_payment` | lote nomeado em 8af75b1 | `PaymentTest.php:111-119` - `assertStatus(402)`, `PAYMENT_DECLINED` e 1 linha `declined`; `PayOrderUseCaseTest.php:49-56`; `PaymentFlowTest.php:17-20` - `$order->fresh()->status` igual a `AwaitingPayment` (carried from 27be65e). O caminho do check foi corrigido (`checks.md:90`) | PASS |
| C20 | `422` antes da porta, para qualquer id, com ou sem porta disponível | lote nomeado em 8af75b1 | `PaymentTest.php:143-148` - `assertUnprocessable()` e `VALIDATION_FAILED`; `:255-259` - `expect($port->asked)->toBe([])`; `:267-270` - `expect($port->calls)->toBe(0)` (carried from 27be65e) | PASS |
| C21 | `503` com o corpo exato, sem cobrar nem gravar; o mesmo numa cópia sem o Ordering | lote nomeado em 8af75b1 (o caso do módulo). A cópia sem o Ordering é carried from 27be65e: `bash proofs/c21-payment-without-ordering.sh` exit 0, `Tests: 1 passed (7 assertions)` | `PaymentTest.php:280-284` - `assertApiError($response->assertStatus(503), 'SERVICE_UNAVAILABLE', 'Este recurso está indisponível no momento.')`, sem cobrança e com `payments` e `outbox_messages` vazias; `proofs/c21-WithoutOrderingTest.php:12-13` e `:20-23` (carried from 27be65e) | PASS |
| C22 | `payment.payment_approved` é lido pela classe do Ordering e move o pedido | lote nomeado em 8af75b1 | `backend/tests/Feature/Messaging/ConsumerNamesTest.php:29-31` com `:34`; `:43` - `eventOf('payment.payment_approved')` igual a `InboundPaymentApproved::class`; `backend/tests/Feature/Messaging/ConsumerRedeliveryTest.php:57` (carried from 27be65e) | PASS |
| C23 | a reentrega deixa `payment_approved` e 1 `ordering.order_paid` | lote nomeado em 8af75b1 | `ConsumerRedeliveryTest.php:52-59` - envelope da classe produtora entregue duas vezes, status `PaymentApproved` e `outboxMessages('ordering.order_paid')->toHaveCount(1)` (carried from 27be65e) | PASS |
| C24 | leitora e produtora concordam; um par divergente é acusado | lote nomeado em 8af75b1 | `backend/tests/Unit/Architecture/OrderEventPayloadTest.php:159`; `:165-166` - `disagreements(...)->toBe([])`; `:171` - a fixture `DisagreeingReader` dá `['payload']` (carried from 27be65e) | PASS |
| C25 | `InboundEvents` só em `Consumers` e `Providers` do Ordering, no código de `app-modules/*/src` | lote nomeado em 8af75b1 | `ModuleLayersTest.php:53` - `expect(ModuleLayout::inboundEventViolations(base_path('app-modules')))->toBe([])`; `:69` - a fixture `inbound-event-outside-consumers` (carried from 27be65e). O claim agora diz "código de `app-modules/*/src`" (`checks.md:110`), o mesmo escopo da regra (`ModuleLayout.php:214`, `glob("{$packages}/*/src")`) | PASS |
| C26 | sem chave em `payments.order_id`; as outras 5 iguais | lote nomeado em 8af75b1, 5 + 1 casos | `backend/tests/Feature/Database/ModuleMigrationsForeignKeyTest.php:27-31` - `expect(foreignKeyTarget($column))->toBe($references)`; `:37-42` - `foreignKeyTarget('payments.order_id')->toBeNull()` (carried from 27be65e) | PASS |
| C27 | `migrate` sobre um banco da base apaga só `payments_order_id_foreign` | carried from 27be65e: `bash proofs/c27-migrate-existing.sh` exit 0 | `proofs/c27-migrate-existing.sh:23` - a base cria a chave; `:27` - a chave não está mais lá depois do `migrate`; `:29` - as 5 chaves continuam; `:31` - nada mais mudou; `:35-36` - a migration nova `Ran` e nada `Pending` | PASS |
| C28 | a migration nova no Payment; 16 nomes congelados | lote nomeado em 8af75b1, 13 + 1 casos | `backend/tests/Unit/Architecture/ModuleMigrationsTest.php:46-47` com `:29`; `:55` - `expect(migrationNamesIn($paths))->toBe($frozen)` (carried from 27be65e) | PASS |
| C29 | sem o Payment: `migrate:fresh`, `404` e as suítes restantes | carried from 27be65e: `bash proofs/c29-without-payment.sh` exit 0, `Tests: 134 passed (567 assertions)` (catalog, identity, messaging e shared, mais o caso de prova) | `proofs/c29-without-payment.sh:7` - o `migrate:fresh` da cópia precisa sair com 0; `:15` - `[ "$status" -eq 0 ]`; `:16` - o caso de prova rodou; `proofs/c29-WithoutPaymentTest.php:8` - `interface_exists(OrdersToPay)` false; `:12` - `->assertNotFound()` | PASS |
| C30 | adaptador do `Integration`: tabela de 4 status e `null` | lote nomeado em 8af75b1: 1 + 4 + 1 casos | `backend/tests/Feature/Integration/OrdersToPayAdapterTest.php:14` - `toBeInstanceOf(OrdersToPayFromOrdering::class)`; `:21-22` - `toEqual(new OrderToPay(...))` sobre `:24-27`; `:34-35` - `toBeNull()` (carried from 27be65e) | PASS |
| C31 | a forma da porta e do value object | lote nomeado em 8af75b1 | `backend/tests/Unit/Architecture/IdTypesTest.php:70-75` - interface, o método `findForPayment`, os parâmetros `[['customerId', 'string'], ['orderId', 'string']]`, o retorno `'?'.OrderToPay::class` e o construtor (carried from 27be65e) | PASS |
| C32 | `401` sem sessão; `429` na 21ª tentativa | lote nomeado em 8af75b1 | `PaymentTest.php:292` - `assertUnauthorized()`; `:360-364` - 20 respostas sem `429` e a 21ª com `assertTooManyRequests()` (carried from 27be65e) | PASS |
| C33 | os 4 documentos descrevem o piloto, sem afirmação velha nenhuma | `bash proofs/c33-docs.sh` em 8af75b1 exit 0, mas a prova não pega a afirmação velha que resta | As 6 linhas da rodada 1 foram reescritas e afirmam o estado do `HEAD`: `docs/domain-analysis.md:91-92`, `:108`, `:140`, `:346`, `:674-675` e `:733` (✅, problema 19 em `:594`). Resta a `docs/domain-analysis.md:588`: "`order_id` único e restrito na exclusão (como `payments`)". A frase afirma que `payments.order_id` restringe a exclusão, mas no `HEAD`:<br>- a migration de criação só tem `foreignUuid('order_id')` sem `constrained()` (`create_payments_table.php:21`);<br>- a `deliveries` é que tem `->constrained()->restrictOnDelete()` (`create_deliveries_table.php:21`).<br>A linha já existia na base. A lista `stale` (`proofs/c33-docs.sh:19-23`) só tem as frases exatas das linhas da rodada 1 | FAIL |
| C34 | `make test` sai com 0, nada pulado | `bash proofs/c34-suite.sh` em 8af75b1, exit 0, `Tests: 2254 passed (9264 assertions)`, 182 s | `proofs/c34-suite.sh:7` - `[ "$code" -eq 0 ]`; `:8` - recusa `failed`, `skipped`, `incomplete`, `risky` e `todo`; `:9` - o teste de log rodou (2 linhas `✓ it logs each payment attempt without the card token`) | PASS |
| C35 | `make pint` sem arquivo a corrigir | `bash proofs/c35-pint.sh` em 8af75b1, exit 0 (495 arquivos) | `proofs/c35-pint.sh:6` - `[ "$code" -eq 0 ]`, sobre o `pint --test` do `Makefile` | PASS |
| C36 | o smoke de carga passa | carried from 27be65e: `bash proofs/c36-load-smoke.sh` exit 0, com `backoffice`, `checkout` e `storefront` com ✓ e `checks: rate==1` | `proofs/c36-load-smoke.sh:10` - `[ "$code" -eq 0 ]`; `:11` - o `bootstrap/cache` não muda | PASS |
| C37 | o `Integration` só usa fachadas; o `ModuleBoundariesTest` fica verde | lote nomeado em 8af75b1, com o `ModuleBoundariesTest` inteiro (`PASS Tests\Unit\Architecture\ModuleBoundariesTest`) | `ModuleBoundariesTest.php:598` - `expect($named)->not->toBeEmpty()`; `:601-602` - cada classe nomeada está na fachada ou no vocabulário do módulo (carried from 27be65e) | PASS |
| C38 | a `PaymentFactory` não cria pedido | lote nomeado em 8af75b1 | `PaymentTest.php:329-332` - 1 linha em `payments`, 0 em `orders`, `RecordId::isCanonical($payment->order_id)` e `$payment->order_id[14]` igual a `'7'` (carried from 27be65e) | PASS |

## Coverage

As linhas `documentos (4)` e `estados da slice Payment ↔ Ordering (7)` foram recalculadas em 8af75b1: a correção tocou a fonte de cada uma, e a rodada 1 as reprovou. As outras vêm de 27be65e: nenhum arquivo de código mudou, e a fonte delas também não.

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| arquivos movidos (27) (carried from 27be65e) | a tabela *Arquivos movidos* do plano | os 27, por `c01-moved-files.sh:35-36` (rodado de novo em 8af75b1) | - |
| módulos com testes que sobem sozinhos (5) (carried from 27be65e) | o AC 4 e `ls backend/app-modules/*/tests` (os mesmos 5 em 8af75b1) | identity 55 · messaging 60 · shared 6 · catalog 12 · payment 49, todos por C6 | - |
| tipos de teste num módulo (2) (carried from 27be65e) | `backend/phpunit.xml:8-15` e `backend/tests/Pest.php:17-25` | Feature `FeatureHarnessTest.php:13` · Unit `UnitHarnessTest.php:12` (C3) | - |
| status de `POST /api/orders/{order}/payment` (8) (carried from 27be65e) | o `Surface` do plano e a rota, o use case e o form request | 202 `PaymentTest.php:46` · 401 `:292` · 402 `:111` · 404 `:231` · 409 `:171` · 422 `:143` · 429 `:364` · 503 `:280` | - |
| regra "pode ser pago" (4) (carried from 27be65e) | `PaymentService.php:21` | as 4 linhas de `PaymentServiceTest.php:23-26` (C17) | - |
| `OrderStatus` -> `awaitingPayment` (4) (carried from 27be65e) | o enum `OrderStatus.php:9-12` | `OrdersToPayAdapterTest.php:24-27` (C30) | - |
| status que recusam o pagamento com o Ordering real (3 + já aprovado) (carried from 27be65e) | o AC 13 e o enum | `PaymentFlowTest.php:35` e `PaymentTest.php:179-189` | - |
| respostas do adaptador (3) (carried from 27be65e) | `OrdersToPayFromOrdering.php:22-29` | `OrdersToPayAdapterTest.php:21-22`, `:34`, `:35` (C30) | - |
| chaves estrangeiras entre módulos (5 + `payments.order_id` sem chave) (carried from 27be65e) | o `pg_constraint` de um banco da base e as migrations do `HEAD` | `ModuleMigrationsForeignKeyTest.php:27-31` e `:37-42`; `c27-migrate-existing.sh:27` e `:29` | - |
| nomes de evento inscritos (5) (carried from 27be65e) | o `EventSubscriptionRegistry` em execução | `OrderEventPayloadTest.php:159`, laço `:161-167` (C24) | - |
| portas da Landing (7) (carried from 27be65e) | a tabela `Landing` do plano | 1 C11, C37 · 2 C31 · 3 C22, C24, C25 · 4 C21 · 5 C26, C27, C28 · 6 C1, C3, C7 · 7 C10 | - |
| entidades da Relations (1) (carried from 27be65e) | o `erDiagram` do plano | `orders` 1:N `payments` sem chave: C26, C27 | - |
| configurações de remoção (3) (carried from 27be65e) | os critérios 4, 16 e 22 | C6 (5 execuções) · C21 · C29 | - |
| regras de arquitetura com violação proposital (4) (carried from 27be65e) | os ACs 6, 10, 19 e 20 | `ModuleLayersTest.php:67` · `:47-48` · `:69` · `OrderEventPayloadTest.php:171` | - |
| estados da slice Payment ↔ Ordering (7) (verified at 8af75b1) | a tabela de estados do design (`.design/module-isolation.md:154-162`), a fonte vinculante | 202 C14 · 404 C16 · 409 C17 · 402 C19 · Ordering ausente C21 · Payment ausente: rota sem módulo `404` C29 (`c29-WithoutPaymentTest.php:12`) e pasta de testes de cada módulo restante C29 (`c29-without-payment.sh:9-15`: catalog, identity, messaging e shared; o Ordering não tem pasta, como a linha registra) · suíte do Payment sem o Ordering C6 payment e C21 | "Payment ausente: os pedidos ficam em `awaiting_payment`" (`.design/module-isolation.md:161`), sem prova: nenhum pedido existe na cópia do C29 |
| documentos (4) (verified at 8af75b1) | o AC 24 e o claim do C33, sobre os 4 arquivos relidos no `HEAD` | `AGENTS.md` C33 · `README.md` C33 · `.design/module-isolation.md` C33 · `docs/domain-analysis.md` | `docs/domain-analysis.md` (`:588`, "restrito na exclusão (como `payments`)") |
| startup: ligação de `OrdersToPay` (3 montagens) (carried from 27be65e) | `IntegrationServiceProvider.php:23-27`, `bootstrap/cache/packages.php:110` e `integration/composer.json:17` | harness `OrdersToPayAdapterTest.php:14` · app sem o Ordering `c21-WithoutOrderingTest.php:13` · app de dev e produção pela mesma descoberta | - |
| startup: inscrição da classe leitora (2 montagens) (carried from 27be65e) | `OrderingServiceProvider.php:20` e `:51` | harness `ConsumerNamesTest.php:43` · workers pelo mesmo `boot()` | - |

Linhas `Swept` que resolvem para algo que já existia (carried from 27be65e), conferidas no código:

- **authorization:** `auth:customer` em `payment/routes/api.php:9`.
- **concurrency:** o índice único parcial em `create_payments_table.php:39`.
- **observability:** o teste de log em `PaymentTest.php:335-355` rodou de novo no C34 em 8af75b1 (2 linhas).
- **data lifecycle:** o `down()` vazio em `2026_10_21_000001_drop_payments_order_id_foreign.php:24`.
- **Observable, limite:** `throttle:20,1,payment` em `routes/api.php:10`.

Lacunas de precisão da rodada 1, julgadas de novo em 8af75b1:

- **C17, C18 e C19 (resolvidas):** os caminhos citados existem (`checks.md:83`, `:87`, `:90`). O `rg` acha os testes em:
  - `app-modules/payment/tests/Unit/Services/PaymentServiceTest.php:16`;
  - `app-modules/payment/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:37` e `:59`.
- **C25 (resolvida):** o claim diz "código de `app-modules/*/src`" (`checks.md:110`), o escopo de `ModuleLayout.php:214`.
- **C24 e AC 19, C16 (carried from 27be65e, sem efeito no veredito):** a fixture prova só divergência de chaves, e a de versão foi provada pelo mutante 4 da rodada 1. O caso do módulo para id desconhecido afirma só o status, e o corpo vem do `CustomerRecordRoutesTest.php:89`.
- **C33, nova:** a prova procura frases fixas. Ela só pega uma afirmação velha que alguém já achou, e por isso a da `:588` passou.

## Test policy rows

carried from 27be65e: a linha classifica arquivos de código, e a correção não tocou nenhum.

O `checks.md` tem a seção `Test policy` com "Não se aplica", sem linhas. Por isso vale a convenção do repositório.

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| "Não se aplica": decisão nova provada na própria camada, um caso por linha da tabela de decisão (convenção do repositório) | `integration/src/Payment/OrdersToPayFromOrdering.php`, `payment/src/Core/Services/PaymentService.php`, `integration/src/Providers/IntegrationServiceProvider.php` | o adaptador com 4 status (C30), a regra com 4 combinações (C17) e os 3 ramos do provider (C30, C21 e C29); C30 e C17 rodaram de novo em 8af75b1 | yes |

## Faults injected

### Verified at 8af75b1: a superfície criada pela correção

A correção acrescentou 6 expressões à lista `stale` de `proofs/c33-docs.sh:20-23` e a flag `re.M` em `:31`.

**Como os mutantes foram aplicados:**

- **Base:** o `git status --porcelain` da pasta principal foi gravado antes. Ele só mostrava `evidence/c05-test-filter.txt` e `evidence/c34-make-test.txt`, regravados pelas provas desta rodada.
- **Worktree:** `git worktree add --detach .claude/worktrees/verify-r2-1 HEAD`.
- **Controle:** a cópia do `c33-docs.sh` do worktree rodou primeiro sem mudança, com exit 0.
- **Cada mutante:** devolve à `docs/domain-analysis.md` do worktree um só trecho da versão de `0c1ae0e`, pelo hunk do diff `0c1ae0e..8af75b1`. Depois o `c33-docs.sh` do worktree roda de dentro do worktree, e o arquivo é restaurado com `git checkout`.
- **Teto:** 5 mutantes. O sexto trecho (`:674`, expressão `Ordering \(lido pelo Payment\)`) ficou de fora. Uma conferência estática mostra que a expressão casa com a linha antiga, mas isso não conta como mutante.

**Depois:**

- o worktree foi removido;
- o `porcelain` da pasta principal ficou idêntico ao gravado antes;
- `git stash list` continuou vazio, e nenhum `git stash` foi usado.

| Mutation | Location | Killed |
| --- | --- | --- |
| volta a linha aberta da tabela *Pendências* "Tentativas de pagamento presas ao pedido por `restrictOnDelete`" (testa a âncora `^` com `re.M`) | `docs/domain-analysis.md:733`, prova `proofs/c33-docs.sh:23` e `:31` | yes. Exit 1: "stale claim" na linha de tabela que começa por "Tentativas de pagamento presas ao pedido por `restrictOnDelete`" |
| volta "Payment → Ordering: PayableOrders" no mapa de contextos | `docs/domain-analysis.md:91-92`, prova `proofs/c33-docs.sh:21` | yes. Exit 1: "stale claim 'Payment → Ordering: PayableOrders'" |
| volta "o Payment lê o pedido pelo contrato `PayableOrders`" na tabela de relações | `docs/domain-analysis.md:108`, prova `proofs/c33-docs.sh:21` | yes. Exit 1, com a frase achada |
| volta "✅ O Payment lê o pedido pelo contrato `PayableOrders`" na matriz | `docs/domain-analysis.md:346`, prova `proofs/c33-docs.sh:21` | yes. Exit 1: "stale claim 'O Payment lê o pedido pelo contrato'" |
| volta "`findForPayment(orderId): ?OrderForPayment`, para o Payment" | `docs/domain-analysis.md:140`, prova `proofs/c33-docs.sh:22` | yes. Exit 1: "stale claim 'OrderForPayment`, para o Payment'" |

### Carried from 27be65e: o código, que a correção não tocou

| Mutation | Location | Killed |
| --- | --- | --- |
| status do `503` trocado por `500` | `backend/app-modules/shared/src/Exceptions/ServiceUnavailableException.php:26` | yes. `answers 503 when the order port is unavailable` falhou (C21) |
| tradução do adaptador: `=== OrderStatus::AwaitingPayment` -> `!== OrderStatus::Placed` | `backend/app-modules/integration/src/Payment/OrdersToPayFromOrdering.php:28` | yes. `translates each order status into awaiting payment` com 2 falhas (C30) |
| regra "pode ser pago" sem o `$hasApprovedPayment` | `backend/app-modules/payment/src/Core/Services/PaymentService.php:21` | yes. `decides whether an order can be paid` com 1 falha (C17) |
| versão da classe leitora `1` -> `2` | `backend/app-modules/ordering/src/Core/InboundEvents/PaymentApproved.php:26` | yes. Falharam C24 e C23 |
| detector de isolamento sem a direção de entrada | `backend/tests/Unit/Architecture/Support/ModuleLayout.php:192` | yes. `fails on a deliberate isolation violation` com 1 falha (C13) |

## Gate

`make test` (`proofs/c34-suite.sh`, no `HEAD` `8af75b1`): 2254 passed, 0 failed, 0 skipped (9264 assertions, 182 s).

`python3 .claude/skills/tlc-spec-lean/scripts/validate_verification.py module-isolation-pilot --root /Users/helterdias/Documents/POC/laravel-module-ecomerce`: exit 1 (`verdict is FAIL - route the ranked gaps back as fixes, then re-verify`). É o esperado para um relatório FAIL.

### Ranked gaps

1. **C33 FAIL.** A `docs/domain-analysis.md:588` diz que o `order_id` de `deliveries` é "restrito na exclusão (como `payments`)", o que afirma uma chave `restrict` em `payments.order_id` que o piloto removeu.
   - Corrigir a comparação, por exemplo "(como `payments` era até a isolação dos módulos)", ou tirá-la.
   - Ampliar a prova para a forma da afirmação, e não só para frases exatas: por exemplo, `payments` perto de `restri` na mesma frase.
2. **Uncovered no design, slice Payment ↔ Ordering.** "Payment ausente: os pedidos ficam em `awaiting_payment`" (`.design/module-isolation.md:161`) não tem prova. Há dois caminhos:
   - acrescentar um check: numa cópia sem o Payment e com o Ordering, um pedido em `awaiting_payment` continua assim depois do `POST` que responde `404`;
   - ou reescrever a linha do design como consequência do `404` e de não haver produtor de `payment.payment_approved`, registrando o motivo.
3. **Uncovered pela regra de documentação do `AGENTS.md`.** O `README.md:1057` diz "Hoje são 29 tipos públicos (...) e 28 privados". No `HEAD` são 30 e 28: o `OrderToPay` entrou no vocabulário do Payment pela porta `OrdersToPay`. Atualizar o número, e de preferência citar o `OrderToPay` entre os exemplos.
