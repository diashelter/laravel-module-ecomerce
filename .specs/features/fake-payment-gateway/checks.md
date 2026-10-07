# Gateway de pagamento fake - checks

Profile: standard
Plan: `.specs/features/fake-payment-gateway/plan.md`

30 checks in 3 slices · 3 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. O frontend roda via `docker compose exec -T frontend npx vitest run -t "<nome do teste>"`, o mesmo Vitest do `make test-frontend`, e a checagem de tipos via `docker compose exec -T frontend npm run type-check`. Os nomes de teste não usam parênteses nem colchetes, porque o `--filter` do Pest e o `-t` do Vitest são expressões regulares.

"O gateway não é chamado" é provado com um `PaymentGateway` espião ligado no container durante o teste, que conta as chamadas. Os demais testes de feature usam o `FakePaymentGateway` real.

## Checks

### S1 - PaymentGateway fake · 8 files · 9 KB · ~2k

**C1** - `FakePaymentGateway`, cobrando com o token `fake_card_approved`, devolve `status` `approved`, `declineReason` `null`, `gateway` `fake` e um `transactionId` que começa com `fake_` (AC 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="approves the fake approved card"`

**C2** - `FakePaymentGateway` devolve `status` `declined` com o motivo de cada token. O teste é um dataset com 4 linhas: `fake_card_insufficient_funds` → `insufficient_funds`, `fake_card_declined` → `card_declined`, `tok_unknown` → `invalid_card` e `""` → `invalid_card` (AC 2, 3, 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declines each fake card with its reason"`

**C3** - Para cada um dos tokens `fake_card_approved` e `fake_card_insufficient_funds`, o `FakePaymentGateway` cobrando `0` e `9999999999` centavos devolve o mesmo `status` nas duas chamadas e dois `transactionId` diferentes (AC 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ignores the amount and issues a new transaction id per charge"`

**C4** - `app(PaymentGateway::class)` é uma instância de `FakePaymentGateway` (AC 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves the payment gateway to the fake gateway"`

**C5** - No `ModuleBoundariesTest`, três expectativas separadas, uma por namespace, afirmam que `App\Modules\Payment\UseCases`, `App\Modules\Payment\Services` e `App\Modules\Payment\Http` não usam `FakePaymentGateway`. Durante o build, cada regra foi vista falhando com um `use` proposital do fake no namespace dela (AC 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reaches the payment gateway only through its contract"`

**C6** - `App\Modules\Payment\Contracts` contém só interfaces (AC 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="the payment contracts are interfaces"`

### S2 - Pay Order · 16 files · 30 KB · ~8k

**C7** - O dono envia `POST /api/orders/{order}/payment` com `card_token` `fake_card_approved` para um pedido em `awaiting_payment` com `total_cents` `12345` e recebe `202`, com `data.id` igual ao id do pedido e `message` `"Pagamento aprovado. O pedido será atualizado em instantes."` (AC 8)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="approves the payment of an order awaiting payment"`

**C8** - Depois de C7, existe exatamente 1 linha em `payments` para o pedido, com `status` `approved`, `amount_cents` `12345`, `decline_reason` `null`, `card_token` `fake_card_approved`, `gateway` `fake` e `gateway_transaction_id` começando com `fake_`. `PaymentApproved` foi despachado exatamente 1 vez, para esse pedido (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records the approved payment and announces it once"`

**C9** - Para cada token recusado, `POST /api/orders/{order}/payment` responde `402`. O corpo tem exatamente as chaves `code`, `message`, `errors` e `request_id`, com `code` `PAYMENT_DECLINED` e a mensagem da linha. Fica 1 linha `declined` com o motivo da linha, o pedido continua em `awaiting_payment` e `PaymentApproved` não é despachado. O teste é um dataset com 3 linhas (AC 10, 11, 12):
- `fake_card_insufficient_funds` → `insufficient_funds` · `"Pagamento recusado: saldo insuficiente."`;
- `fake_card_declined` → `card_declined` · `"Pagamento recusado pelo emissor do cartão."`;
- `tok_unknown` → `invalid_card` · `"Cartão inválido."`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declines the payment and keeps the order awaiting payment"`

**C10** - Depois de um `402` com `fake_card_declined`, o dono paga o mesmo pedido com `fake_card_approved` e recebe `202`. O pedido passa a ter 2 linhas em `payments`, a primeira `declined` e a segunda `approved` (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="approves a new attempt after a declined one"`

**C11** - `card_token` inválido responde `422` com `code` `VALIDATION_FAILED` e erro em `errors.card_token`, sem nenhuma linha em `payments` e com 0 chamadas ao gateway. O teste é um dataset com 3 linhas: ausente, o inteiro `123` e um texto de 65 caracteres. No limite, um texto de 64 caracteres não é recusado pela validação e responde `402` (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects an invalid card token without charging"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="accepts a card token of exactly 64 characters"`

**C12** - Para um pedido em `placed`, `payment_approved` ou `delivered`, o pagamento com `fake_card_approved` responde `409` com `message` `"Este pedido não está aguardando pagamento."`, sem nenhuma linha em `payments` e com 0 chamadas ao gateway. O teste é um dataset com os 3 status (AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects payment for orders that are not awaiting payment"`

**C13** - Um pedido em `awaiting_payment` que já tem 1 linha `approved` em `payments` responde `409` com `message` `"Este pedido não está aguardando pagamento."`. Continua com 1 linha em `payments`, e o gateway recebe 0 chamadas (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a second payment while the queue has not moved the order"`

**C14** - Um gateway de teste grava uma linha `approved` para o pedido durante a própria cobrança, simulando a requisição concorrente que venceu, e devolve `approved`. A requisição responde `409` com `message` `"Este pedido não está aguardando pagamento."`, o pedido termina com exatamente 1 linha `approved` e `PaymentApproved` não é despachado (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="loses the race to a concurrent approval with 409"`

**C15** - Para um pedido com `total_cents` `12345`, um corpo com `card_token` `fake_card_approved`, `amount_cents` `1` e `total_cents` `1` faz o gateway espião receber `amountCents` `12345` e grava `payments.amount_cents` `12345` (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="charges the order total and ignores amounts in the request"`

**C16** - Um cliente pagando o pedido de outro cliente recebe `403`, sem nenhuma linha em `payments` e com 0 chamadas ao gateway (AC 19)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids paying an order of another customer"`

**C17** - Inserir, direto no banco, uma segunda linha `approved` em `payments` para o mesmo `order_id` lança `QueryException`. Uma linha `declined` para o mesmo pedido é aceita (AC 20)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="allows only one approved payment per order in the database"`

**C18** - O banco recusa com `QueryException` 3 linhas inválidas em `payments`, num dataset: `declined` com `decline_reason` `null`; `approved` com `decline_reason` `card_declined`; `amount_cents` `-1` (AC 21)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects inconsistent payment rows in the database"`

**C19** - Um pagamento recusado com `fake_card_declined` e um aprovado com `fake_card_approved` registram cada um 1 log `info` `"Payment attempt recorded."`. O contexto tem exatamente as chaves `order_id`, `payment_id`, `status` e `decline_reason`, com os valores da linha gravada, e não contém o `card_token` em nenhuma chave nem valor (AC 22)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs each payment attempt without the card token"`

**C20** - O mesmo usuário envia 21 requisições `POST /api/orders/{order}/payment` dentro de um minuto. As 20 primeiras não recebem `429`, e a 21ª recebe `429` (AC 23)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles payment attempts after 20 per minute"`

**C21** - Na camada do `PaymentService`, um pedido em `awaiting_payment` sem pagamento aprovado é aceito. Os outros casos lançam `BusinessRuleException` com `"Este pedido não está aguardando pagamento."`. O teste é um dataset com 5 linhas (AC 15, 16):
- aceito: `awaiting_payment` sem pagamento aprovado;
- recusados: `awaiting_payment` com pagamento aprovado, `placed`, `payment_approved` e `delivered`, todos sem pagamento aprovado.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="decides whether an order can be paid"`

**C22** - Na camada do `PayOrderUseCase`, com um gateway que devolve `declined` / `card_declined`, a execução lança a exceção de recusa com o status `402`. Depois dela, a linha `declined` está gravada em `payments`: a exceção não desfez a escrita. `PaymentApproved` não é despachado (AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the declined payment after raising the decline"`

**C29** - Acrescentado após a verificação 1, que achou a FK sem prova (plano, Relations). O banco recusa com `QueryException` um `payments` com `order_id` `999999` e o `DELETE` de um pedido que tem tentativa. Um pedido sem tentativa é apagado (door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ties payments to existing orders and keeps orders that have payments"`

**C30** - Acrescentado após a verificação 1 (Test policy: a terceira saída do use case só tinha prova de borda). Na camada do `PayOrderUseCase`, quando outra aprovação é gravada durante a cobrança, a execução lança `BusinessRuleException` com `"Este pedido não está aguardando pagamento."`. Sobra exatamente 1 linha `approved` e `PaymentApproved` não é despachado (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="raises a conflict when a concurrent approval wins the race"`

### S3 - PaymentPage · 5 files · 11 KB · ~3k

**C23** - O composable da página de pagamento expõe os 3 cartões de teste, nesta ordem: "Cartão aprovado" → `fake_card_approved`, "Recusado: saldo insuficiente" → `fake_card_insufficient_funds` e "Recusado pelo emissor" → `fake_card_declined`. Começa sem cartão selecionado e com o envio bloqueado (`canSubmit` `false`) para um pedido em `awaiting_payment` (AC 24)
Proof: `docker compose exec -T frontend npx vitest run -t "lists the three test cards with none selected"`

**C24** - Com o pedido em `awaiting_payment` e o cartão "Recusado pelo emissor" selecionado, `canSubmit` é `true` e o envio chama `orderService.pay` com o id do pedido e `fake_card_declined`. Durante a requisição pendente, `canSubmit` é `false` e o rótulo é `"Pagando..."` (AC 25, 26)
Proof: `docker compose exec -T frontend npx vitest run -t "pays with the selected card and locks the button while paying"`

**C25** - Quando `orderService.pay` resolve (`202`), o composable chama a notificação de sucesso com a mensagem da API e navega para `account.order` com `params.id` igual ao pedido e `query.paid` `"1"` (AC 27)
Proof: `docker compose exec -T frontend npx vitest run -t "navigates to the order after an approved payment"`

**C26** - Quando `orderService.pay` rejeita com `ApiError` de status `402` e mensagem `"Pagamento recusado: saldo insuficiente."`, o composable:
- expõe essa mensagem como a mensagem de recusa;
- mantém o cartão selecionado;
- volta `canSubmit` para `true`;
- não navega (AC 28).

Proof: `docker compose exec -T frontend npx vitest run -t "keeps the page and shows the decline after a 402"`

**C27** - Quando `orderService.pay` rejeita com `ApiError` de status `409`, o composable chama a notificação de erro com a mensagem e recarrega o pedido uma vez pelo `orderService.find`. Para um pedido em `placed`, `canSubmit` fica `false` mesmo com um cartão selecionado (AC 29, 30)
Proof: `docker compose exec -T frontend npx vitest run -t "reloads the order after a 409 and blocks payment while placed"`

**C28** - A página compila com o composable novo, sem erro de tipo, e não há mais referência a `approvePayment` (AC 24 a 30)
Proof: `docker compose exec -T frontend npm run type-check`
Proof: `! grep -rn "approvePayment" frontend/src`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| tokens do `FakePaymentGateway` (5 linhas da tabela) | `fake_card_approved` C1 · `fake_card_insufficient_funds` C2 · `fake_card_declined` C2 · outro token C2 · id de transação novo por cobrança C3 | - |
| `DeclineReason` (3) | `insufficient_funds` C2, C9 · `card_declined` C2, C9, C22 · `invalid_card` C2, C9 | - |
| `PaymentStatus` (2) | `approved` C1, C8 · `declined` C2, C9 | - |
| `POST /api/orders/{order}/payment` statuses (6) | 202 C7 · 402 C9 · 403 C16 · 409 C12, C13, C14 · 422 C11 · 429 C20 | - |
| entradas inválidas de `card_token` (4 bordas) | ausente C11 · não texto C11 · 65 caracteres C11 · 64 caracteres aceito C11 | - |
| status do pedido × "pode ser pago" (5) | `awaiting_payment` sem aprovado C21, C7 · `awaiting_payment` com aprovado C21, C13 · `placed` C21, C12 · `payment_approved` C21, C12 · `delivered` C21, C12 | - |
| caminhos que não chamam o gateway (4) | `422` C11 · `409` de status C12 · `409` com aprovado C13 · `403` C16 | - |
| garantias da tabela `payments`, door 1 (7) | único aprovado por pedido C17 · recusado repetido aceito C17 · `declined` exige motivo C18 · `approved` sem motivo C18 · `amount_cents >= 0` C18 · `order_id` existente C29 · pedido com tentativa não é apagado C29 | - |
| doors do `Landing` (3) | door 1 C8, C17, C18, C29 · door 2 C1, C4, C5, C6 · door 3 C9 | - |
| namespaces proibidos de usar o fake (3) | `Payment\UseCases` C5 · `Payment\Services` C5 · `Payment\Http` C5 | - |
| chaves do erro `402` (4) | `code` C9 · `message` C9 · `errors` C9 · `request_id` C9 | - |
| chaves do log de tentativa (4) | `order_id` C19 · `payment_id` C19 · `status` C19 · `decline_reason` C19 | - |
| cartões de teste da página (3) | "Cartão aprovado" C23 · "Recusado: saldo insuficiente" C23, C26 · "Recusado pelo emissor" C23, C24 | - |
| respostas tratadas pela página (3) | 202 C25 · 402 C26 · 409 C27 | - |
| estados de bloqueio do botão "Pagar" (3) | sem cartão C23 · requisição pendente C24 · pedido em `placed` C27 | - |
| startup config: ligação de `PaymentGateway` (2 assemblies) | aplicação via `bootstrap/providers.php` C4 · testes de feature, que sobem a mesma aplicação C7 | - |

- As afirmações sobre status, rota ou formato de resposta (C7 a C16 e C20) têm prova que atravessa o HTTP.
- C1 a C3, C21, C22 e C30 provam as decisões na camada da própria classe, além das provas de borda.
- C17, C18 e C29 provam as garantias no banco, sem passar pela aplicação.
- C12 e C16 reaproveitam testes que já existem em `PaymentTest`. Eles passam a enviar `card_token` e ganham as asserções de "sem linha" e "sem chamada", sem afrouxar nenhuma das atuais. Os demais nomes são testes novos.
- C23 a C27 provam a lógica da página no composable. A ligação do composable ao template é provada só pela checagem de tipos (C28), como registra a assumption do plano. O que fica sem prova automática é o arranjo visual.

## Test policy

O README diz onde ficam e como rodar os testes, mas não diz qual nível prova cada tipo de código. As linhas abaixo valem para esta feature.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Decide e é alcançado pelo HTTP (`FakePaymentGateway`, `PaymentService`, `PayOrderUseCase`) | uma na borda **e** uma na própria camada | na borda: o contrato de cada status; na camada: um caso afirmado por linha da tabela de decisão |
| Decide no frontend (composable da página) | uma na própria camada, com Vitest e o `orderService` simulado | um caso por resposta tratada e por estado de bloqueio |
| Garantia do banco (índice único parcial, `CHECK`) | uma direto no banco | um caso por restrição, aceito e recusado |
| Instrumentação (controller, Form Request, renderização da exceção, service provider) | nenhuma própria | coberta pelas provas de borda |

Evidence:

- `FakePaymentGateway`: decide sobre 4 tokens (3 conhecidos + o resto), com 4 pontos de decisão → decide
- `PaymentService`: 2 condições (status e pagamento aprovado), 5 combinações relevantes → decide
- `PayOrderUseCase`: 3 saídas (aprovado, recusado com a linha confirmada, corrida perdida) → decide
- composable da página: 3 respostas e 3 estados de bloqueio → decide
- `PaymentController`: autoriza e repassa ao use case, sem condição própria → instrumentação
- análogo no repositório: `tests/Unit/Services/PaymentServiceTest.php` e `tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php` já provam o Payment na própria camada, além do `PaymentTest` na borda; `stores/cart.test.ts` prova uma regra do frontend com Vitest

Cost: 5 provas na própria camada (C1 a C3, C21 e C22) em 3 arquivos do backend, e 5 no composable (C23 a C27). Sem estas linhas, a tabela do fake e a regra "pode ser pago" seriam provadas só pelos caminhos que o HTTP atravessa.

## Swept

- validation: C11
- failure modes: C22, porque a recusa não desfaz a linha gravada. A falha técnica do gateway está fora do escopo (plano, Out of scope)
- idempotency: C13 e C17, porque um segundo pagamento aprovado é recusado na aplicação e no banco. Um `PaymentApproved` duplicado já é ignorado pela transição idempotente do `MarkOrderAsPaid` (existing, `OrderStatusFlowTest`)
- authorization: C16; existing - `auth:sanctum` e a policy `pay`
- concurrency: C14, porque duas aprovações simultâneas deixam uma única linha aprovada e um único evento
- data lifecycle: n/a - as tentativas são permanentes, não há expiração nem arquivamento, e o `restrictOnDelete` segue a regra de pedidos, que nunca são apagados
- dependency failure: n/a - o fake não falha; simular gateway fora do ar está fora do escopo (plano, Out of scope)
- state transitions: C21, C12 e C13, porque só `awaiting_payment` sem aprovado pode ser pago. C9 garante que a recusa não muda o pedido, e C8 que a aprovação anuncia o evento que move o pedido
- observability: C19

## Handoff

- S1 = ~2k, S2 entra em ~10k, S3 em ~13k; a atualização do README e da análise de domínio (79 KB, lidos em partes) leva a ~33k, abaixo do budget de 150k - one builder
- Mechanism: one builder (cabe no orçamento, sem pergunta)
- **Boundary:** C1-C6 closed no commit `feat(payment): add the payment gateway port and the fake gateway`; cada regra de C5 foi vista falhando com um `use` proposital do fake no próprio namespace (UseCases, Services e Http, uma de cada vez)
- **Boundary:** C7-C22 closed no commit `feat(payment): charge orders through the gateway and record every attempt`. `MoneyInCentsTest` e `OrderStatusFlowTest` passaram a enviar `card_token` nas chamadas de pagamento (contrato novo), sem mudar nenhuma asserção. O teste antigo "approves the fake payment of an order awaiting payment" virou C7, que afirma o mesmo `202` e `data.id` e acrescenta a mensagem
- **Boundary:** C23-C28 closed no commit `feat(frontend): pay with a test card and retry after a decline`. Os estados visuais foram conferidos no navegador contra o ambiente local, com o worker real: três cartões sem seleção e "Pagar" desabilitado, recusa com saldo insuficiente mostrada na página com o cartão mantido, nova tentativa aprovada levando ao pedido em `payment_approved`, e duas linhas em `payments` (`declined` / `insufficient_funds` e `approved`)
- **Boundary:** a verificação 1 deu FAIL, com 28/28 checks provados e 5/5 mutantes mortos, e apontou duas lacunas: a FK `payments.order_id` sem prova e a corrida perdida sem prova na camada do use case. C29 e C30 foram acrescentados sem mudar nenhum check aprovado. Os dois foram fechados no commit `test(payment): prove the payments foreign key and the lost race`, junto com o índice em `order_id` que o design pedia e as correções no README e na análise de domínio
