# Gateway de pagamento fake verification

**Verdict**: PASS
**Profile**: standard
**Diff range**: c7e323149c4a225895446ff030a3ca37dd6ec71b..970ae47 (HEAD; commits c7a51b1, 00af1b5, 400eca0, b805bd1, 3cd4012, 65832d6, 970ae47)
**Round**: 2 - scoped
**Verifier**: independent sub-agent (author != verifier)

A rodada 1 (no HEAD `65832d6`) deu FAIL por duas lacunas: a FK `payments.order_id` sem prova e a corrida perdida sem prova na camada do `PayOrderUseCase`. O commit de correção `970ae47` fecha as duas com C29 e C30, e os dois novos mutantes de cada superfície foram mortos. Agora os 30 checks estão provados, nenhum membro de cobertura fica sem prova, as 4 linhas de Test policy estão cumpridas e os 8 mutantes (5 da rodada 1 e 3 desta) foram mortos.

### Escopo desta rodada

O diff de `970ae47` toca 6 arquivos:

- `backend/tests/Feature/PaymentTest.php`: teste novo de C29, inserido em `:236`, o que desloca para baixo as linhas de C19 e C20.
- `backend/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php`: teste novo de C30, em `:58`.
- `backend/database/migrations/2026_10_06_000001_create_payments_table.php`: `index('order_id')` em `:31`, o que desloca para baixo as linhas dos `CHECK` e do índice único.
- `checks.md`: acrescenta C29 e C30 e muda a linha de cobertura das garantias da tabela para 7 membros. Conferi o diff: nenhum texto de C1 a C28 mudou.
- `README.md:302` e `docs/domain-analysis.md:471`: correções de documentação.

Nenhum arquivo de código de produção da aplicação (`app/`) nem do frontend mudou.

Por isso:

- as provas rodaram de novo por inteiro;
- as citações dos dois arquivos de teste e da migration foram atualizadas;
- as linhas de cobertura da tabela `payments` e dos doors foram recalculadas;
- a linha de Test policy parcial foi julgada de novo;
- os mutantes foram injetados só nas superfícies novas (C29 e C30).

O resto vem da rodada 1 e diz de onde veio.

Execuções (todas no HEAD `970ae47`; porcelain da árvore real antes e depois: só `?? .specs/features/fake-payment-gateway/verification.md`, este relatório):

- **B1**: uma única chamada Pest com a alternância dos 25 nomes de prova do backend (os 23 da rodada 1 mais `ties payments to existing orders and keeps orders that have payments` e `raises a conflict when a concurrent approval wins the race`). Resultado: **44 passed (165 assertions)**, exit 0. Cada teste e cada linha de dataset apareceu individualmente no output, inclusive os dois novos.
- **F1**: uma única chamada Vitest com os 5 nomes de C23 a C27. Resultado: **5 passed**, exit 0, cada nome listado em `src/composables/usePayment.test.ts`.
- **T**: `docker compose exec -T frontend npm run type-check`, exit 0.
- **G**: `! grep -rn "approvePayment" frontend/src`, exit 0, sem saída.
- Existência dos testes novos: `backend/tests/Feature/PaymentTest.php:236` e `backend/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:58`, os dois no diff de `970ae47`.

## Binding sources

Carried from 65832d6. O passo 1 é do profile `ui` e não roda em `standard`. O design `.design/fake-payment-gateway.md` continua sendo a autoridade do recálculo de cobertura. A tabela de colunas dele pede um índice em `order_id`, que agora existe (`create_payments_table.php:31`; `payments_order_id_index` em `pg_indexes` do `ecommerce_testing`).

## Checks

Verified at 970ae47: todas as provas rodaram de novo neste HEAD (B1, F1, T e G). As citações dos arquivos que `970ae47` tocou foram atualizadas: C17, C18, C19 e C20, mais C29 e C30, que são novos. As citações dos arquivos que o fix não tocou vêm de 65832d6 e continuam exatas, porque esses arquivos não mudaram.

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | `fake_card_approved` → `approved`, motivo `null`, gateway `fake`, id `fake_*` | B1 `approves the fake approved card`, passed | `backend/tests/Unit/Gateways/FakePaymentGatewayTest.php:16-19` - `expect($result->status)->toBe(PaymentStatus::Approved)->and($result->declineReason)->toBeNull()->and($result->gateway)->toBe('fake')->and($result->transactionId)->toStartWith('fake_')` | PASS |
| C2 | 4 tokens recusados com o motivo de cada um | B1 `declines each fake card with its reason` x4, passed | `backend/tests/Unit/Gateways/FakePaymentGatewayTest.php:25-26` - `expect($result->status)->toBe(PaymentStatus::Declined)->and($result->declineReason)->toBe($reason)`; dataset `:28-31` | PASS |
| C3 | valor `0` e `9999999999`: mesmo status e ids diferentes | B1 `ignores the amount and issues a new transaction id per charge` x2, passed | `backend/tests/Unit/Gateways/FakePaymentGatewayTest.php:40-41` - `expect($zero->status)->toBe($huge->status)->and($zero->transactionId)->not->toBe($huge->transactionId)`; dataset `:42` | PASS |
| C4 | `app(PaymentGateway::class)` é `FakePaymentGateway` | B1 `resolves the payment gateway to the fake gateway`, passed | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:89` - `expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class)`; ligação em `backend/app/Modules/Payment/PaymentServiceProvider.php:17-19` e `backend/bootstrap/providers.php:12` | PASS |
| C5 | UseCases, Services e Http não usam o fake, uma expectativa por namespace | B1 3x `payment {layer} reaches the payment gateway only through its contract`, passed | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:63-66` - `arch(...)->expect("App\\Modules\\Payment\\{$layer}")->not->toUse(FakePaymentGateway::class)`, um namespace por `expect` | PASS |
| C6 | `Payment\Contracts` só tem interfaces | B1 `the payment contracts are interfaces`, passed | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:69-71` - `->expect('App\Modules\Payment\Contracts')->toBeInterfaces()` | PASS |
| C7 | `202`, `data.id` e mensagem de aprovação | B1 `approves the payment of an order awaiting payment`, passed | `backend/tests/Feature/PaymentTest.php:42-44` - `->assertAccepted()->assertJsonPath('data.id', $order->id)->assertJsonPath('message', 'Pagamento aprovado. O pedido será atualizado em instantes.')` | PASS |
| C8 | 1 linha `approved` com os 6 campos; `PaymentApproved` 1 vez, para o pedido | B1 `records the approved payment and announces it once`, passed | `backend/tests/Feature/PaymentTest.php:55-61` - `toHaveCount(1)`, `status` `'approved'`, `amount_cents` `12345`, `decline_reason` `toBeNull()`, `card_token` `'fake_card_approved'`, `gateway` `'fake'`, `gateway_transaction_id` `toStartWith('fake_')`; `:63-64` - `Event::assertDispatchedTimes(PaymentApproved::class, 1)` e `assertDispatched(..., fn ($event) => $event->order->is($order))` | PASS |
| C9 | `402` com corpo exato, 1 linha `declined`, pedido parado, sem evento, x3 | B1 `declines the payment and keeps the order awaiting payment` x3, passed | `backend/tests/Feature/PaymentTest.php:72` - `->assertStatus(402)`; `:74` - `assertApiError($response, 'PAYMENT_DECLINED', $message)` (`assertExactJson` de `code`, `message`, `errors` `[]`, `request_id`, em `backend/tests/Pest.php:57-62`); `:77-80` - 1 linha `declined` com `decline_reason` `toBe($reason)` e o pedido em `AwaitingPayment`; `:82` - `Event::assertNotDispatched(PaymentApproved::class)`; mensagens em `:84-86` | PASS |
| C10 | `402` e depois `202`; 2 linhas, `declined` e depois `approved` | B1 `approves a new attempt after a declined one`, passed | `backend/tests/Feature/PaymentTest.php:94-95` - `->assertStatus(402)` e depois `->assertAccepted()`; `:98-100` - `toHaveCount(2)`, `[0]` `'declined'`, `[1]` `'approved'` | PASS |
| C11 | `422` `VALIDATION_FAILED` em `card_token`, sem linha e sem chamada (x3); 64 caracteres → `402` | B1 `rejects an invalid card token without charging` x3 e `accepts a card token of exactly 64 characters`, passed | `backend/tests/Feature/PaymentTest.php:110-112` - `->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonValidationErrors('card_token')`; `:114-115` - linhas e `charges` `toBeEmpty()`; dataset `:117-119`; `:127-129` - 64 caracteres → `->assertStatus(402)->assertJsonPath('code', 'PAYMENT_DECLINED')` | PASS |
| C12 | `409` com a mensagem para os 3 status; sem linha e sem chamada | B1 `rejects payment for orders that are not awaiting payment` x3, passed | `backend/tests/Feature/PaymentTest.php:138-139` - `->assertConflict()->assertJsonPath('message', 'Este pedido não está aguardando pagamento.')`; `:141-142` - `toBeEmpty()` x2; dataset `:144` | PASS |
| C13 | já com `approved`: `409`, continua 1 linha, 0 chamadas | B1 `rejects a second payment while the queue has not moved the order`, passed | `backend/tests/Feature/PaymentTest.php:154-155` - `->assertConflict()->assertJsonPath('message', ...)`; `:157-158` - `toHaveCount(1)` e `$gateway->charges` `toBeEmpty()` | PASS |
| C14 | corrida perdida no HTTP: `409`, 1 `approved`, sem evento | B1 `loses the race to a concurrent approval with 409`, passed | `backend/tests/Feature/PaymentTest.php:174-175` - `->assertConflict()->assertJsonPath('message', ...)`; `:177` - `where('status', 'approved')` `toHaveCount(1)`; `:178` - `Event::assertNotDispatched(PaymentApproved::class)` | PASS |
| C15 | valores do corpo ignorados; gateway e linha com 12345 | B1 `charges the order total and ignores amounts in the request`, passed | `backend/tests/Feature/PaymentTest.php:190-192` - `charges` `toHaveCount(1)`, `charges[0]->amountCents` `toBe(12345)`, `paymentRows($order)[0]->amount_cents` `toBe(12345)`; corpo com `amount_cents` e `total_cents` `1` em `:187` | PASS |
| C16 | pedido de outro cliente: `403`, sem linha e sem chamada | B1 `forbids paying an order of another customer`, passed | `backend/tests/Feature/PaymentTest.php:199-200` - `->assertForbidden()`; `:202-203` - `toBeEmpty()` x2 | PASS |
| C17 | 2º `approved` no banco → `QueryException`; `declined` aceito | B1 `allows only one approved payment per order in the database`, passed | `backend/tests/Feature/PaymentTest.php:211-212` - `expect(fn () => DB::transaction(fn () => Payment::factory()->for($order)->approved()->create()))->toThrow(QueryException::class)`; `:214-216` - `declined()` criado e `toHaveCount(2)`. Índice: `backend/database/migrations/2026_10_06_000001_create_payments_table.php:38` (citação atualizada) | PASS |
| C18 | 3 linhas inválidas recusadas pelo banco | B1 `rejects inconsistent payment rows in the database` x3, passed | `backend/tests/Feature/PaymentTest.php:222-229` - `expect(fn () => DB::table('payments')->insert([...]))->toThrow(QueryException::class)`; dataset `:231-233`. Constraints: `create_payments_table.php:34` e `:36` (citações atualizadas) | PASS |
| C19 | 1 log `info` por tentativa, 4 chaves exatas, sem o token | B1 `logs each payment attempt without the card token` x2, passed | `backend/tests/Feature/PaymentTest.php:261-273` - `Log::shouldHaveReceived('info')->with('Payment attempt recorded.', Mockery::on(...))->once()`, com `$keys === ['decline_reason', 'order_id', 'payment_id', 'status']` (`:266`), valores da linha (`:267-270`) e `! str_contains(json_encode($context), $cardToken)` (`:271`); dataset `:274` (citações atualizadas: o arquivo ganhou 16 linhas em `:236`) | PASS |
| C20 | 20 requisições sem `429`; a 21ª com `429` | B1 `throttles payment attempts after 20 per minute`, passed | `backend/tests/Feature/PaymentTest.php:281-283` - `foreach (range(1, 20) ...) expect(payOrder(...)->status())->not->toBe(429)`; `:285` - `->assertTooManyRequests()` (citações atualizadas); rota `backend/routes/api.php:46` | PASS |
| C21 | `PaymentService`: 1 combinação aceita e 4 recusadas | B1 `decides whether an order can be paid` x5, passed | `backend/tests/Unit/Services/PaymentServiceTest.php:20-22` - `not->toThrow(BusinessRuleException::class)` ou `toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.')`; dataset `:24-28` | PASS |
| C22 | use case: recusa com `402` e a linha `declined` mantida; sem evento | B1 `keeps the declined payment after raising the decline`, passed | `backend/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:47-48` - `expect($declined)->not->toBeNull()->and($declined->render()->getStatusCode())->toBe(402)`; `:51-53` - 1 linha `declined` com `card_declined`; `:54` - `Event::assertNotDispatched(PaymentApproved::class)` (linhas inalteradas: o teste novo foi acrescentado no fim do arquivo) | PASS |
| C23 | 3 cartões em ordem; nenhum selecionado; `canSubmit` `false` | F1 `lists the three test cards with none selected`, passed | `frontend/src/composables/usePayment.test.ts:45-49` - `expect(payment.cards.map((card) => [card.label, card.token])).toEqual([['Cartão aprovado', 'fake_card_approved'], ['Recusado: saldo insuficiente', 'fake_card_insufficient_funds'], ['Recusado pelo emissor', 'fake_card_declined']])`; `:50-51` - `selectedToken` `toBeNull()`, `canSubmit` `toBe(false)` | PASS |
| C24 | com cartão: `canSubmit` `true`; chama `pay(7, 'fake_card_declined')`; durante a requisição, `false` e `"Pagando..."` | F1 `pays with the selected card and locks the button while paying`, passed | `frontend/src/composables/usePayment.test.ts:60` - `canSubmit` `toBe(true)`; `:64` - `toHaveBeenCalledWith(7, 'fake_card_declined')`; `:65-66` - `canSubmit` `toBe(false)` e `submitLabel` `toBe('Pagando...')` | PASS |
| C25 | `202`: notificação de sucesso e `push` com `paid` `'1'` | F1 `navigates to the order after an approved payment`, passed | `frontend/src/composables/usePayment.test.ts:84-85` - `expect(success).toHaveBeenCalledWith('Pagamento aprovado. O pedido será atualizado em instantes.')`; `expect(push).toHaveBeenCalledWith({ name: 'account.order', params: { id: 7 }, query: { paid: '1' } })` | PASS |
| C26 | `402`: mensagem exposta, cartão mantido, `canSubmit` `true`, sem navegar | F1 `keeps the page and shows the decline after a 402`, passed | `frontend/src/composables/usePayment.test.ts:97-100` - `declineMessage` `toBe('Pagamento recusado: saldo insuficiente.')`, `selectedToken` `toBe('fake_card_insufficient_funds')`, `canSubmit` `toBe(true)`, `push` `not.toHaveBeenCalled()` | PASS |
| C27 | `409`: notificação de erro e `find` 1 vez; `placed` bloqueia | F1 `reloads the order after a 409 and blocks payment while placed`, passed | `frontend/src/composables/usePayment.test.ts:113-116` - `error` `toHaveBeenCalledWith('Este pedido não está aguardando pagamento.')`, `find` `toHaveBeenCalledTimes(1)` e `toHaveBeenCalledWith(7)`; `:118-120` - `placed`, com cartão, `canSubmit` `toBe(false)` | PASS |
| C28 | type-check limpo e nenhum `approvePayment` | T exit 0; G exit 0 | `frontend/src/pages/public/PaymentPage.vue:18` - `usePayment(props.orderId, order)`; `frontend/src/services/orderService.ts:22` - `async pay(id: number, cardToken: string)`; busca por `approvePayment` vazia | PASS |
| C29 | FK: `order_id` `999999` recusado; pedido com tentativa não é apagado; pedido sem tentativa é apagado | B1 `ties payments to existing orders and keeps orders that have payments`, passed | `backend/tests/Feature/PaymentTest.php:241-242` - `expect(fn () => DB::transaction(fn () => Payment::factory()->create(['order_id' => 999999])))->toThrow(QueryException::class)`; `:243-244` - `expect(fn () => DB::transaction(fn () => DB::table('orders')->where('id', $paid->id)->delete()))->toThrow(QueryException::class)`; `:246` - `DB::table('orders')->where('id', $unpaid->id)->delete()`; `:248-249` - `$unpaid` `exists()` `toBeFalse()` e `$paid` `toBeTrue()`. O par `$paid`/`$unpaid` (`:237-239`) vem da mesma factory e só difere pela tentativa, então a recusa só pode vir da FK de `payments`. Restrição: `create_payments_table.php:20` | PASS |
| C30 | corrida perdida na camada do use case: `BusinessRuleException` com a mensagem, 1 `approved`, sem evento | B1 `raises a conflict when a concurrent approval wins the race`, passed | `backend/tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php:60-64` - o gateway espião grava o `approved` concorrente e devolve `approved`; `:66-67` - `expect(fn () => app(PayOrderUseCase::class)->execute($order, new PayOrderDTO('fake_card_approved')))->toThrow(BusinessRuleException::class, 'Este pedido não está aguardando pagamento.')`; `:69` - `->where('status', 'approved')->count())->toBe(1)`; `:70` - `Event::assertNotDispatched(PaymentApproved::class)` | PASS |

## Coverage

Verified at 970ae47 para as 2 linhas cuja autoridade o fix tocou: a migration e a linha do checks.md. As demais vêm de 65832d6: a autoridade delas (enums, `PayOrderRequest`, `routes/api.php`, design, composable, `bootstrap/providers.php`) não mudou no diff de `970ae47`, e as provas foram rodadas de novo neste HEAD.

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| garantias da tabela `payments`, door 1 (7) - verified at 970ae47 | Relations e Landing do plano; `backend/database/migrations/2026_10_06_000001_create_payments_table.php:20,34,36,38`; `pg_constraint` e `pg_indexes` do `ecommerce_testing` | único `approved` por pedido C17 (`PaymentTest.php:211-212`) · `declined` repetido aceito C17 (`:214-216`) · `declined` exige motivo C18 · `approved` sem motivo C18 · `amount_cents >= 0` C18 · `order_id` aponta para pedido existente C29 (`:241-242`) · pedido com tentativa não é apagado C29 (`:243-244`), com o contraste do pedido sem tentativa apagado (`:246-249`) | - |
| doors do Landing (3) - verified at 970ae47 | Landing do plano | door 1: C8, C17, C18, C29 · door 2: C1-C6, formato literal conferido em `ChargeRequest.php:11` e `ChargeResult.php:13` · door 3: C9 | - |
| tokens do fake (5 linhas) - carried from 65832d6 | design, slice PaymentGateway | `fake_card_approved` C1 · `fake_card_insufficient_funds` C2 · `fake_card_declined` C2 · qualquer outro C2 · id novo e valor ignorado C3 | - |
| `DeclineReason` (3) e mensagens (3) - carried from 65832d6 | `backend/app/Modules/Payment/Enums/DeclineReason.php:12-14,20-22` | os 3 motivos: C2, C9; as 3 mensagens por igualdade exata em C9 (`PaymentTest.php:84-86`) | - |
| `PaymentStatus` (2) - carried from 65832d6 | `backend/app/Modules/Payment/Enums/PaymentStatus.php:12-13` | `approved` C1, C8 · `declined` C2, C9 | - |
| statuses de `POST /api/orders/{order}/payment` (8) - carried from 65832d6 | Surface do plano + `backend/routes/api.php:41,46` | 202 C7 · 402 C9 · 403 C16 · 409 C12, C13, C14 (e C30 na camada do use case) · 422 C11 · 429 C20 · 401 `backend/tests/Feature/MoneyInCentsTest.php:263` · 404 `MoneyInCentsTest.php:288`. Os dois últimos rodaram de novo no `make test` deste HEAD | - |
| regras de `card_token` (3 + borda) - carried from 65832d6 | `backend/app/Modules/Payment/Http/Requests/PayOrderRequest.php:17` | `required`, `string`, `max:64` e 64 aceito: C11 | - |
| status do pedido × "pode ser pago" (5) - carried from 65832d6 | `OrderStatus` (4 valores) × `PaymentService.php:22` | as 5 combinações relevantes: C21; borda C7, C12, C13 | - |
| caminhos que não chamam o gateway (4) - carried from 65832d6 | ordem em `PayOrderRequest` → `PaymentController.php:23` → `PayOrderUseCase.php:41` | 422 C11 · 403 C16 · 409 de status C12 · 409 com aprovado C13 | - |
| contrato do erro `402` (4 chaves + código) - carried from 65832d6 | Landing door 3; `ApiErrorCode.php:24` | C9 por `assertExactJson` (`Pest.php:57-62`) | - |
| chaves do log (4) - carried from 65832d6 | AC 22; `PayOrderUseCase.php:55-60` | C19 (citação atualizada: `PaymentTest.php:266`) | - |
| estados da PaymentPage (5) - carried from 65832d6 | design, slice PaymentPage | `awaiting_payment` C23 · 202 C25 · 402 C26 · 409 C27 · `placed` C27 | - |
| cartões de teste (3) - carried from 65832d6 | design, slice PaymentPage | C23 | - |
| bloqueios do "Pagar" (3) - carried from 65832d6 | `frontend/src/composables/usePayment.ts:35-37` | sem cartão C23 · requisição pendente C24 · `placed` C27 | - |
| startup config da ligação (assemblies) - carried from 65832d6 | `backend/bootstrap/providers.php:12`, lido diretamente | aplicação (`api` e `queue-worker`, mesmo bootstrap) `providers.php:12` → `PaymentServiceProvider.php:17-19` · testes C4 | - |

## Test policy rows

Verified at 970ae47 para a linha que era parcial ("Decide e é alcançado pelo HTTP") e para a linha de garantia do banco, que classifica a migration tocada. As outras duas vêm de 65832d6: o fix não tocou nenhum arquivo que elas classificam.

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| Decide e é alcançado pelo HTTP - verified at 970ae47 | `FakePaymentGateway.php`, `PaymentService.php`, `PayOrderUseCase.php` | borda **e** própria camada; na camada, um caso por linha da tabela de decisão | yes - `FakePaymentGateway`: borda C9 e camada C1-C3, as 5 linhas. `PaymentService`: borda C12, C13 e camada C21, as 5 combinações. `PayOrderUseCase`, as 3 saídas na camada: aprovado (`PayOrderUseCaseTest.php:18-25`), recusado com a linha confirmada (C22, `:47-54`) e corrida perdida (C30, `:66-70`). Na borda, as mesmas 3 saídas: C7/C8, C9 e C14 |
| Decide no frontend - carried from 65832d6 | `frontend/src/composables/usePayment.ts` | própria camada, Vitest com `orderService` simulado | yes - um caso por resposta (C25, C26, C27) e por estado de bloqueio (C23, C24, C27) |
| Garantia do banco (índice único parcial, `CHECK`) - verified at 970ae47 | `create_payments_table.php:34,36,38` | uma direto no banco, aceito e recusado por restrição | yes - índice: recusado `PaymentTest.php:211-212`, aceito `:209` e `:214`. `CHECK` do motivo: recusado C18, aceito `:209` e `:214`. `CHECK` do valor: recusado C18, aceito `:209` (factory com 0). A FK, que esta linha não classifica, agora também tem os dois lados: recusado em `:241-244` e aceito em `:246-249` (C29) |
| Instrumentação - carried from 65832d6 | `PaymentController.php`, `PayOrderRequest.php`, `PaymentDeclinedException.php`, `PaymentServiceProvider.php` | nenhuma própria; coberta pela borda | yes - C7, C11, C9, C4 |

## Faults injected

Rodada 2 (verified at 970ae47): os mutantes foram injetados só nas superfícies que `970ae47` criou, que são as asserções de C29 e C30. Isolamento: `git worktree add <scratchpad>/wt2 HEAD`, com uma prova sem mutação rodada antes, verde (2 passed). Cada mutante rodou num container descartável: `docker run --rm --entrypoint "" --network ecommerce_default -v <wt2>/backend:/var/www/backend -v <real>/backend/vendor:/var/www/backend/vendor:ro ecommerce-php:latest ./vendor/bin/pest --filter=...`.

Porcelain da árvore real: o mesmo antes e depois (`?? .specs/features/fake-payment-gateway/verification.md`, só este relatório). A worktree foi removida com `git worktree remove --force` e `git worktree prune`. Depois, o `make test` recriou o schema do `ecommerce_testing` a partir da migration real (`payments_order_id_index` e `payments_order_id_approved_unique` presentes em `pg_indexes`).

Rodada 1 (carried from 65832d6): os 5 mutantes das superfícies originais foram mortos. A mutação 4 citava a linha `:36` da migration, que agora é `:38`. Nenhum desses arquivos de produção mudou em `970ae47`, e os testes que os mataram passaram de novo em B1.

| Mutation | Location | Killed |
| --- | --- | --- |
| `restrictOnDelete()` → `cascadeOnDelete()` (rodada 2) | `backend/database/migrations/2026_10_06_000001_create_payments_table.php:20` | yes - C29 falha na 2ª asserção (`1 failed (2 assertions)`): o `DELETE` do pedido com tentativa deixou de lançar (`PaymentTest.php:243-244`) |
| remover a FK: `foreignId('order_id')` sem `constrained()` (rodada 2) | `backend/database/migrations/2026_10_06_000001_create_payments_table.php:20` | yes - C29 falha na 1ª asserção (`1 failed (1 assertions)`): o `order_id` `999999` foi aceito (`PaymentTest.php:241-242`) |
| no `catch` da corrida, trocar `ensureCanBePaid($order, hasApprovedPayment: true)` por `return $order->load('items')` (a corrida perdida engolida como sucesso) (rodada 2) | `backend/app/Modules/Payment/UseCases/PayOrderUseCase.php:52` | yes - C30 falha (`1 failed (1 assertions)`): o `toThrow(BusinessRuleException::class, ...)` de `PayOrderUseCaseTest.php:66-67` |
| `fake_card_declined` → `InsufficientFunds` (rodada 1, carried from 65832d6) | `backend/app/Modules/Payment/Gateways/FakePaymentGateway.php:25` | yes - C2 |
| sem `\|\| $hasApprovedPayment` (rodada 1, carried from 65832d6) | `backend/app/Modules/Payment/Services/PaymentService.php:22` | yes - C21 e C13 |
| recusa lançada dentro do `DB::transaction` (rodada 1, carried from 65832d6) | `backend/app/Modules/Payment/UseCases/PayOrderUseCase.php:49` | yes - C22 e as 3 linhas de C9 |
| sem o índice único parcial (rodada 1, carried from 65832d6) | `create_payments_table.php:38` (era `:36` em 65832d6) | yes - C17 e C14 |
| no `402`, `declineMessage` trocada por `notifications.error` (rodada 1, carried from 65832d6) | `frontend/src/composables/usePayment.ts:52` | yes - C26 |

O índice simples novo em `order_id` (`create_payments_table.php:31`) não recebeu mutante. Ele é desempenho, sem asserção de comportamento, e nenhum check o cobre. Removê-lo não mudaria nenhum resultado observável.

## Swept - linhas "existing" relidas no código

Carried from 65832d6. O fix não tocou `OrderStatusFlowTest.php`, `MarkOrderAsPaidUseCase.php`, `routes/api.php`, `OrderPolicy.php`, `PaymentController.php` nem `PaymentApproved.php`.

- idempotency: `OrderStatusFlowTest.php:85` e `:97`, mais `MarkOrderAsPaidUseCase.php:24`. Confirmado.
- authorization: `routes/api.php:41`, `OrderPolicy.php:23-25` e `PaymentController.php:23`. Confirmado.
- `PaymentApproved` `ShouldDispatchAfterCommit`: `PaymentApproved.php:16`. Confirmado.

## Documentação (regra do AGENTS.md)

Verified at 970ae47. As duas imprecisões da rodada 1 foram corrigidas:

1. `README.md:301-302`: a frase do `restrict` de produtos ficou separada. A linha nova diz que `payments.order_id` "também usa `restrict`: um pedido com tentativas de pagamento não pode ser apagado", o que confere com `create_payments_table.php:20` e com C29.
2. `docs/domain-analysis.md:471`: nova linha em "Pendências depois do plano" para a FK `payments.order_id` com `restrictOnDelete`, ao lado da linha análoga de `stocks.product_id`.

O índice novo em `order_id` não muda nenhuma afirmação do README: a tabela de modelo de dados (`README.md:295`) descreve as garantias, não os índices de apoio. A busca por nomes antigos (`approvePayment`, "Aprovar pagamento", "não foi implementado", "aprovado na hora") em `README.md`, `docs/` e `AGENTS.md` continua vazia.

## Notas de precisão e de nível (não reprovam)

1. **C17, C18 e C29 afirmam `QueryException` sem o nome da restrição.** Em C18, cada linha preenche as outras colunas de forma válida. Em C29, o contraste com o pedido sem tentativa isola a FK de `payments`. Os mutantes do índice e da FK foram mortos. Citar o nome da restrição (`payments_order_id_foreign`, `payments_decline_reason_matches_status`) deixaria a asserção autoexplicativa. Carried from 65832d6, ampliada para C29.
2. **Sem `CHECK` no domínio de `status`.** O Landing não promete esse `CHECK`. Carried from 65832d6.
3. **C5, "vista falhando no build".** Não foi reproduzido pelo Verifier, porque o limite de faults da rodada 1 foi gasto em superfícies de comportamento. A forma de um namespace por `expect` está em `ModuleBoundariesTest.php:65`. Carried from 65832d6.
4. **`PayOrderUseCase.php:50-53`** depende de `ensureCanBePaid(..., true)` sempre lançar. Agora C30 cobra isso na própria camada: o mutante que engole a corrida foi morto. Atualizada em 970ae47.
5. **Arranjo visual da PaymentPage** sem prova automática, como registra a assumption do plano. A ligação do template é provada só pelo type-check (C28). Carried from 65832d6.
6. **Resolvida:** a falta do índice em `order_id`, que o design pedia, foi corrigida em `create_payments_table.php:31`.

## Gate

- B1 (provas do backend, uma chamada, 25 nomes): 44 passed (165 assertions), 0 failed
- F1 (provas do frontend, uma chamada): 5 passed, 0 failed
- `docker compose exec -T frontend npm run type-check`: exit 0
- `! grep -rn "approvePayment" frontend/src`: exit 0
- `make test` (depois dos mutantes): 456 passed (1463 assertions), 0 failed, exit 0
- `docker compose exec -T api ./vendor/bin/pint --test`: PASS, 239 files
- `python3 .claude/skills/tlc-spec-lean/scripts/validate_verification.py fake-payment-gateway`: 0 error(s), 0 warning(s), exit 0
