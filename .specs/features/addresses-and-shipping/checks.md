# Endereços e frete - checks

Profile: standard
Plan: `.specs/features/addresses-and-shipping/plan.md`

82 checks in 5 slices · 5 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. O frontend roda via `docker compose exec -T frontend npx vitest run -t "<nome do teste>"`, o mesmo Vitest do `make test-frontend`, e a checagem de tipos via `docker compose exec -T frontend npm run type-check`. O CI não roda o frontend, então essas provas precisam rodar localmente antes do PR. Os nomes de teste não usam parênteses, colchetes nem barras, porque o `--filter` do Pest e o `-t` do Vitest são expressões regulares.

A fila roda em modo `sync` nos testes (README, seção Testes). Os checks que afirmam um horário congelam o relógio com `travelTo`. "Sessão de cliente" e "sessão de equipe" são autenticações nos guards `customer` e `staff`, como nos testes atuais.

Testes existentes que afirmam o comportamento antigo são substituídos pelos checks que afirmam o novo, conforme o plano aprovado, e não afrouxados:
- `CheckoutTest` "stores an order total equal to the sum of its item subtotals" → C37 e C38 (total = linhas + frete);
- os testes que chamam `POST /api/orders` (`CheckoutTest` 4, `MoneyInCentsTest` 2, `OrderStatusFlowTest` 1) passam a enviar um `address_id` válido, sem perder nenhuma asserção;
- a dataset `store routes behind the customer login` do `AuthorizationTest` ganha as 4 rotas do caderno (C18);
- o objeto `Order` de exemplo do `usePayment.test.ts` ganha os campos novos do tipo, sem mudar nenhuma asserção.

## Checks

### S1 - Caderno de endereços · ~30 arquivos · ~64 KB · ~16k

**C1** - Um cliente sem endereços envia `GET /api/account/addresses` e recebe `200` com `data` igual a `[]` (AC 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists no addresses for a new customer"`

**C2** - Um cliente envia `POST /api/account/addresses` com `recipient_name` `"Ana Souza"`, `postal_code` `"01310-100"`, `street` `"Avenida Paulista"`, `number` `"1000"`, `complement` `"Apto 12"`, `district` `"Bela Vista"`, `city` `"São Paulo"`, `state` `"SP"` e recebe `201` com `message` `"Endereço cadastrado com sucesso."`. O `data` contém exatamente as chaves `id`, `recipient_name`, `postal_code`, `street`, `number`, `complement`, `district`, `city`, `state` e `created_at`, com `postal_code` `"01310100"` e os demais valores iguais aos enviados. `customer_addresses` passa a ter 1 linha com o `customer_id` desse cliente (AC 2, doors 1 e 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates an address in the customer address book"`

**C3** - Num dataset de 3 linhas, o cadastro responde `201` e grava: `state` `" sp "` → `"SP"`; `complement` ausente → `null`; `complement` `""` → `null` (AC 3, AC 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes the state and an empty complement"`

**C4** - Com dois endereços do cliente criados um depois do outro e um de outro cliente, `GET /api/account/addresses` devolve exatamente 2 itens, o criado por último primeiro, e nenhum do outro cliente (AC 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists only the customer addresses, most recent first"`

**C5** - Num dataset de 6 linhas para `postal_code`: `"1234567"`, `"0131-0100"`, `"0131010a"` e `"01310 100"` respondem `422` `VALIDATION_FAILED` com erro em `errors.postal_code` e não gravam linha; `"01310100"` e `"01310-100"` respondem `201` e gravam `"01310100"` (AC 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="validates the postal code format"`

**C6** - Num dataset de 3 linhas, `state` `"XX"`, `""` e ausente respondem `422` com erro em `errors.state` e não gravam linha (AC 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a state outside the 27 states"`

**C7** - Num dataset de 10 linhas, cada um de `recipient_name`, `street`, `number`, `district` e `city`, ausente e vazio, responde `422` com erro sob esse campo e não grava linha (AC 8)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="requires every address field but the complement"`

**C8** - Num dataset de 6 campos, o valor no limite é aceito (`201`) e o valor com 1 caractere a mais responde `422` sob o campo: `recipient_name` 120, `street` 150, `number` 20, `complement` 100, `district` 100, `city` 100 (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="limits the length of each address field"`

**C9** - Pela rota: um cliente com 9 endereços cadastra o 10º (`201`, total 10); um cliente com 10 recebe `409` com `code` `BUSINESS_RULE_VIOLATION` e `message` `"Você pode cadastrar até 10 endereços."`, e continua com 10 (AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an eleventh address"`

**C10** - Na própria camada, o caso de uso de cadastrar endereço grava o 10º endereço de um cliente que tem 9 (Test policy)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="accepts the tenth address in the use case"`

**C11** - Na própria camada, o caso de uso de cadastrar endereço lança `BusinessRuleException` com `"Você pode cadastrar até 10 endereços."` para um cliente que tem 10, e não grava linha (Test policy)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses the eleventh address in the use case"`

**C12** - O dono envia `PUT /api/account/addresses/{address}` com o corpo completo, trocando `state` para `"RJ"` e `city` para `"Rio de Janeiro"`, e recebe `200` com esses valores em `data` e `message` `"Endereço atualizado com sucesso."`. A linha passa a ter os valores novos (AC 11)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="updates an address of the customer"`

**C13** - O dono envia `PUT /api/account/addresses/{address}` sem `city` e recebe `422` com erro em `errors.city`, e a linha não muda (Surface `PUT` `422`; Assumptions, substituição completa)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="requires the full address on update"`

**C14** - O dono envia `DELETE /api/account/addresses/{address}` e recebe `204`, e a linha deixa de existir (AC 12)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes an address of the customer"`

**C15** - Num dataset de 2 linhas (`PUT` e `DELETE`), um cliente que mira o endereço de outro cliente recebe `403`, e o endereço continua igual (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids changing an address of another customer"`

**C16** - Num dataset de 2 linhas (`PUT` e `DELETE`), o id `999999` responde `404` (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 to an unknown address"`

**C17** - Sem sessão, as 4 rotas de `/api/account/addresses` respondem `401`, num dataset de 4 linhas, e `customer_addresses` não muda (AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a guest on every address route"`

**C18** - Com uma sessão só de equipe, a dataset `store routes behind the customer login` tem 11 linhas (as 7 atuais mais as 4 do caderno) e todas respondem `401`; `customer_addresses` não ganha linha (AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a staff session on every store route"`

**C19** - Direto no banco: apagar um `customers` apaga as suas 2 linhas de `customer_addresses`; inserir um `customer_addresses` com `customer_id` `999999` lança `QueryException` (AC 16, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="removes the address book together with the customer account"`

**C20** - Direto no banco, num dataset: `postal_code` `'0131010A'` e `'1234567'` e `state` `'XX'` e `'sp'` lançam `QueryException`; uma linha com `'01310100'` e `'SP'` é aceita (AC 17, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects invalid postal codes and states in the address book table"`

**C21** - O menu da área do cliente lista, nesta ordem, "Dashboard", "Meus pedidos", "Endereços" e "Meu perfil", com "Endereços" apontando para `{ name: 'account.addresses' }` (AC 18)
Proof: `docker compose exec -T frontend npx vitest run -t "lists Endereços right after Meus pedidos in the account menu"`

**C22** - O router resolve `/account/addresses` para a rota `account.addresses`, filha da rota com `requiresShopper` (AC 18)
Proof: `docker compose exec -T frontend npx vitest run -t "registers the address book under the customer account"`

**C23** - O composable do caderno fica com `loading` `true` enquanto a listagem não volta e, quando o service rejeita, termina com o erro `"Não foi possível carregar os endereços."` e `loading` `false` (AC 19)
Proof: `docker compose exec -T frontend npx vitest run -t "reports a failed address book load"`

**C24** - Com o service devolvendo `[]`, o composable termina com `isEmpty` `true`; com 2 endereços, `false`, na ordem em que a API os devolveu (AC 20, AC 21)
Proof: `docker compose exec -T frontend npx vitest run -t "flags an empty address book and keeps the API order"`

**C25** - A formatação de CEP transforma `"01310100"` em `"01310-100"` (AC 21)
Proof: `docker compose exec -T frontend npx vitest run -t "formats a postal code with a hyphen"`

**C26** - A lista de UFs do formulário tem 27 siglas em ordem alfabética, começando por `AC` e terminando em `TO` (AC 22)
Proof: `docker compose exec -T frontend npx vitest run -t "offers the 27 states in alphabetical order"`

**C27** - Ao salvar, o composable transforma um `422` com `errors.postal_code` e `errors.city` em uma mensagem por campo, e um `409` em `formMessage` igual à `message` da API (AC 23)
Proof: `docker compose exec -T frontend npx vitest run -t "maps address form errors from the API"`

**C28** - Ao excluir o endereço de `"Ana Souza"`, o composable chama `window.confirm` com `Excluir o endereço de "Ana Souza"?`. Cancelado, nenhum `delete` é chamado; confirmado, o service recebe o `delete` e o endereço sai da lista (AC 24)
Proof: `docker compose exec -T frontend npx vitest run -t "asks before deleting an address"`

### S2 - Orçamento de frete · ~8 arquivos · ~12 KB · ~3k

**C29** - Na própria camada, a tabela de frete tem exatamente 27 entradas, uma por caso de `BrazilianState`, e a dataset de 27 linhas afirma preço e prazo de cada UF: SP `1500`/`2`; RJ, MG, ES `2200`/`4`; PR, SC, RS `2500`/`5`; DF, GO, MT, MS `3000`/`6`; BA, SE, AL, PE, PB, RN, CE, PI, MA `3800`/`8`; PA, AP, AM, RR, AC, RO, TO `4500`/`10` (AC 25)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="quotes every state from the shipping rate table"`

**C30** - `GET /api/shipping/quote?state=BA` responde `200` com `data` igual a `{ "state": "BA", "price_cents": 3800, "delivery_business_days": 8 }`, sem sessão e com sessão de cliente (AC 26, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="quotes the shipping for a state without a session"`

**C31** - `GET /api/shipping/quote?state=sp` responde `200` com `state` `"SP"`, `price_cents` `1500` e `delivery_business_days` `2` (AC 27)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes the state of the quote"`

**C32** - Num dataset de 2 linhas, sem `state` e com `state=XX`, a rota responde `422` com erro em `errors.state` (AC 28)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a quote without a valid state"`

**C33** - Do mesmo cliente, as 60 primeiras requisições a `GET /api/shipping/quote?state=SP` num minuto não respondem `429`, e a 61ª responde `429` (AC 29)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles the shipping quote after 60 requests per minute"`

**C34** - O container resolve `ShippingQuoter` para uma classe de `App\Modules\Fulfillment`, e numa dataset de 27 UFs o `ShippingQuote` dela é igual ao `price_cents` e ao `delivery_business_days` de `GET /api/shipping/quote` (AC 30, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves the shipping quoter to the fulfillment rate table"`

**C35** - O `ModuleBoundariesTest` afirma que `App\Modules\Fulfillment` não usa `App\Modules\Customers`. Durante o build, a regra foi vista falhando com um `use` proposital (AC 31)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="fulfillment does not know customers"`

### S3 - Checkout com endereço e frete · ~30 arquivos · ~83 KB · ~21k

**C36** - Com 2 unidades de um produto de `10000` e o `address_id` de um endereço do próprio cliente no RJ, `POST /api/orders` responde `201` com `data.items_total_cents` `20000`, `data.shipping_cents` `2200`, `data.total_cents` `22200`, `data.delivery.business_days` `4`, `data.delivery.estimated_on` `null` e `data.delivery.address` com exatamente as 8 chaves `recipient_name`, `postal_code`, `street`, `number`, `complement`, `district`, `city`, `state`, iguais às do endereço (AC 32, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="places an order with the delivery address and the shipping of its state"`

**C37** - O pedido de C36 grava em `orders` `shipping_cents` `2200`, `delivery_business_days` `4`, `total_cents` `22200` e cada coluna `delivery_*` igual ao campo do endereço (AC 33, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="stores the delivery copy and the shipping on the order row"`

**C38** - O `total_cents` gravado é a soma dos `subtotal_cents` dos itens mais `shipping_cents`, num pedido de 3 produtos diferentes para SP (door 2; substitui "stores an order total equal to the sum of its item subtotals")
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="stores an order total equal to the items plus the shipping"`

**C39** - O pedido de C36, pago com `card_token` `fake_card_approved`, grava `payments.amount_cents` `22200` (AC 34)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="charges the order total with the shipping"`

**C40** - Sem `address_id`, `POST /api/orders` responde `422` com `errors.address_id` igual a `["Escolha um endereço de entrega."]`; `orders` continua sem linha e o estoque do produto não muda (AC 35)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="requires a delivery address to place an order"`

**C41** - Num dataset de 2 linhas (endereço de outro cliente e id `999999`), `POST /api/orders` responde `422` com `errors.address_id` igual a `["Endereço de entrega não encontrado."]`; `orders` continua sem linha e o estoque não muda (AC 36)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a delivery address that is not in the customer address book"`

**C42** - O corpo de C36 com `shipping_cents: 0`, `total_cents: 1` e `delivery_state: "SP"` a mais gera o pedido com `shipping_cents` `2200`, `delivery_state` `RJ` e `total_cents` `22200` (AC 37)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ignores shipping and totals sent by the client"`

**C43** - Num dataset de 2 linhas, depois de o cliente mudar o endereço para SP (`PUT`) ou excluí-lo (`DELETE`), `GET /api/orders/{order}` devolve o `delivery.address`, o `shipping_cents` `2200` e o `delivery.business_days` `4` originais (AC 38, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the delivery copy when the address changes later"`

**C44** - Com estoque insuficiente e um `address_id` válido, `POST /api/orders` responde `409` com `code` `INSUFFICIENT_STOCK` e não cria pedido; o teste existente passa a enviar `address_id` sem perder nenhuma asserção (AC 39)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns 409 and changes nothing when stock is insufficient"`

**C45** - Direto no banco, num dataset, estas escritas em `orders` lançam `QueryException`: cada uma das 7 colunas `delivery_recipient_name`, `delivery_postal_code`, `delivery_street`, `delivery_number`, `delivery_district`, `delivery_city` e `delivery_state` nula; `shipping_cents` `-1`; `delivery_business_days` `0`; `total_cents` `100` com `shipping_cents` `200`; `delivery_state` `'XX'`; `delivery_postal_code` `'0131010A'`. Uma linha com `delivery_complement` e `estimated_delivery_on` nulos é aceita (AC 40, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="guards the delivery copy and the shipping in the orders table"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="every money column"`

**C46** - O container resolve `DeliveryAddressBook` para uma classe de `App\Modules\Customers`, e `App\Modules\Ordering\Contracts` contém só interfaces (AC 41, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves the delivery address book to the customers module"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="the ordering contracts are interfaces"`

**C47** - Na própria camada, a implementação do `DeliveryAddressBook`, numa dataset de 3 linhas: o endereço do próprio cliente vira um `DeliveryAddress` com os 8 campos e `state` `BrazilianState::RJ`; o endereço de outro cliente devolve `null`; o id `999999` devolve `null` (door 3; Test policy)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="finds a delivery address only in the customer own book"`

**C48** - O `ModuleBoundariesTest` afirma que `App\Modules\Ordering` não usa `App\Modules\Customers` e, com uma expectativa por namespace, que não usa cada namespace de `App\Modules\Fulfillment` além de `Events`. A lista vem dos diretórios do módulo e o teste afirma ao menos 4 namespaces. Durante o build, as duas regras foram vistas falhando com um `use` proposital (AC 42, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ordering does not know customers"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ordering reaches fulfillment only through its events"`

**C49** - Na própria camada, o `PlaceOrderUseCase` com um `ShippingQuoter` simulado que devolve `999`/`3` e um `DeliveryAddressBook` simulado que devolve um endereço em MG cria o pedido com `shipping_cents` `999`, `delivery_business_days` `3`, `delivery_state` `MG` e `total_cents` igual às linhas mais `999` (Test policy)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="places the order with the quote of the delivery state"`

**C50** - Na própria camada, o `PlaceOrderUseCase` com um `DeliveryAddressBook` simulado que devolve `null` lança a validação em `address_id` com `"Endereço de entrega não encontrado."`, sem chamar o `ShippingQuoter`, sem travar nem baixar o estoque e sem criar pedido (Test policy)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an unknown delivery address before touching the stock"`

**C51** - Do mesmo cliente, as 20 primeiras requisições a `POST /api/orders` num minuto não respondem `429`, e a 21ª responde `429` (Surface `POST /api/orders` `429`)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles order placement after 20 requests per minute"`

**C52** - As rotas de leitura do pedido mantêm as recusas com os campos novos: num dataset de 2 linhas, `GET /api/orders/999999` com sessão de cliente e `GET /api/admin/orders/999999` com sessão de equipe respondem `404`; `GET /api/orders/{order}` de outro cliente responde `403` (teste existente); sem sessão, `GET /api/admin/orders/{order}` responde `401` (teste existente, que percorre a tabela de rotas) (Surface)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 to an unknown order"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids a customer from seeing another customer order"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a guest on every admin route"`

**C53** - Com o service de endereços devolvendo `[]`, o composable do checkout termina com o formulário aberto e `canConfirm` `false` (AC 43)
Proof: `docker compose exec -T frontend npx vitest run -t "opens the address form when the customer has no address"`

**C54** - Ao salvar o formulário no checkout, o composable chama a criação de endereço, seleciona o endereço devolvido e pede o orçamento da UF dele (AC 44)
Proof: `docker compose exec -T frontend npx vitest run -t "selects and quotes an address saved during checkout"`

**C55** - Com 2 endereços, o primeiro da API em `RJ`, o composable seleciona o primeiro e pede o orçamento de `RJ`; "Adicionar outro endereço" abre o formulário (AC 45)
Proof: `docker compose exec -T frontend npx vitest run -t "preselects the most recent address and quotes it"`

**C56** - Com a validação do carrinho em `20000` e o orçamento em `2200`/`4`, o composable expõe subtotal `20000`, frete `2200`, total `22200` e o texto `"Entrega em até 4 dias úteis após a aprovação do pagamento"` (AC 46)
Proof: `docker compose exec -T frontend npx vitest run -t "shows the subtotal, the shipping and the total"`

**C57** - Ao selecionar outro endereço, em `BA`, o composable pede o orçamento de `BA` e passa a expor frete `3800` e total `23800` (AC 47)
Proof: `docker compose exec -T frontend npx vitest run -t "quotes again when another address is selected"`

**C58** - Com o orçamento pendente, `canConfirm` é `false`. Quando ele falha, o composable expõe `"Não foi possível calcular o frete."` e `canConfirm` continua `false`; "Tentar novamente" pede o orçamento de novo (AC 48)
Proof: `docker compose exec -T frontend npx vitest run -t "blocks the confirmation while the shipping is unknown"`

**C59** - Ao confirmar, o composable chama `orderService.place` com os itens do carrinho e o `address_id` selecionado e, no `201`, navega para `{ name: 'payment', params: { orderId } }` (AC 49)
Proof: `docker compose exec -T frontend npx vitest run -t "places the order with the selected address"`

**C60** - Quando `orderService.place` rejeita com `422` e erro em `errors.address_id`, o composable expõe essa mensagem e pede a lista de endereços de novo (AC 50)
Proof: `docker compose exec -T frontend npx vitest run -t "reloads the addresses when the delivery address is refused"`

**C61** - A checagem de tipos do frontend passa com o tipo `Order` ganhando `items_total_cents`, `shipping_cents` e `delivery` e com as páginas do caderno, do checkout e do detalhe do pedido ligadas aos composables (AC 43 a 50, AC 65)
Proof: `docker compose exec -T frontend npm run type-check`

### S4 - Data prevista e bloco de entrega · ~16 arquivos · ~28 KB · ~7k

**C62** - Na própria camada, o cálculo da data prevista do Fulfillment, numa dataset de 5 linhas: quarta 2026-10-07 10:00 em `America/Sao_Paulo` com 2 dias → 2026-10-09; sexta 2026-10-09 com 2 → 2026-10-13; sábado 2026-10-10 com 2 → 2026-10-13; 2026-10-08 02:30 UTC com 2 → 2026-10-09; quarta 2026-10-07 com 10 → 2026-10-21 (AC 51 a 54)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts the delivery estimate in business days in Sao Paulo time"`

**C63** - Com o relógio em 2026-10-07 10:00 de `America/Sao_Paulo`, o `ScheduleOrderDelivery` tratando o `OrderPaid` de um pedido com `delivery_business_days` `2` despacha `DeliveryScheduled` com esse pedido e a data 2026-10-09 (AC 51, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="announces the delivery estimate when the order is paid"`

**C64** - O `ScheduleOrderDelivery` continua despachando o `DeliverOrder` com delay; o teste existente continua igual (AC 55)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="schedules the delivery job with a delay when the order is paid"`

**C65** - O Ordering escuta `DeliveryScheduled` com um listener que implementa `ShouldQueue` e tem `$tries` `3`; o teste existente de registro dos listeners ganha essa linha (door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="registers the listeners for the order events"`

**C66** - O Ordering, tratando `DeliveryScheduled` com 2026-10-09 para um pedido em `payment_approved` sem data, grava `estimated_delivery_on` 2026-10-09, e o status continua `payment_approved` (AC 56, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records the delivery estimate without changing the status"`

**C67** - Tratando de novo `DeliveryScheduled` para o mesmo pedido com 2026-10-12, `estimated_delivery_on` continua 2026-10-09 (AC 57, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the first delivery estimate"`

**C68** - Tratando `DeliveryScheduled` com 2026-10-09 para um pedido já `delivered` sem data, `estimated_delivery_on` passa a 2026-10-09 e o status continua `delivered` (AC 58)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="records the estimate of an order already delivered"`

**C69** - Ao agendar a entrega, o Fulfillment grava no log `info` a mensagem `"Delivery scheduled."` com exatamente as chaves `order_id` e `estimated_delivery_on` (`"2026-10-09"`), e nenhum valor `delivery_*` do pedido aparece no contexto (AC 59)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs the scheduled delivery without the address"`

**C70** - Com o relógio em 2026-10-07 10:00 de `America/Sao_Paulo`, um pedido para um endereço em SP tem `delivery.estimated_on` `null` antes do pagamento e, depois de pago com `fake_card_approved` e com a fila em `sync`, `GET /api/orders/{order}` devolve `delivery.estimated_on` `"2026-10-09"` (AC 60)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows the delivery estimate after the payment is approved"`

**C71** - Para o mesmo pedido, `GET /api/admin/orders/{order}` devolve `items_total_cents`, `shipping_cents` e `delivery` iguais aos de `GET /api/orders/{order}` (AC 61, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows the same delivery block to the admin"`

**C72** - A decisão do texto de entrega, numa dataset de 3 linhas: `estimated_on` `null` com status `awaiting_payment` e 2 dias → `"Entrega em até 2 dias úteis após a aprovação do pagamento"`; `"2026-10-09"` com status `payment_approved` → `"Entrega prevista: 09/10/2026"`; status `delivered` → `"Pedido entregue"` (AC 62, AC 64)
Proof: `docker compose exec -T frontend npx vitest run -t "describes the delivery forecast of an order"`

**C73** - Com `process.env.TZ` em `America/Sao_Paulo`, a formatação de data só-dia transforma `"2026-10-09"` em `"09/10/2026"` (AC 63)
Proof: `docker compose exec -T frontend npx vitest run -t "formats a date without shifting it to the previous day"`

**C77** - Para um pedido para o RJ, `GET /api/orders` e `GET /api/admin/orders` devolvem na listagem `items_total_cents`, `shipping_cents` `2200`, `total_cents` igual às linhas mais `2200` e `delivery` (com `business_days` `4` e `address.state` `RJ`) iguais aos do detalhe (rodada 1 da verificação; plano, Impact e Surface)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows the delivery block in the store and admin order lists"`

**C78** - Direto no banco, a escrita com nulo é recusada com `QueryException` em cada uma das 8 colunas obrigatórias de `customer_addresses` (`customer_id`, `recipient_name`, `postal_code`, `street`, `number`, `district`, `city`, `state`) e em `orders.delivery_business_days` (rodada 1 da verificação; Test policy "Garantia do banco")
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="requires every column of the address book table but the complement|guards the delivery copy and the shipping in the orders table"`

**C79** - O `ModuleBoundariesTest` afirma, com uma expectativa por classe, que `App\Modules\Ordering` não usa as classes da raiz de `App\Modules\Fulfillment` (hoje o `FulfillmentServiceProvider`), e afirma que a lista contém o `FulfillmentServiceProvider`. Uma classe do Ordering que usa o provider faz a regra falhar (AC 42, rodada 1 da verificação)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ordering reaches fulfillment only through its events: FulfillmentServiceProvider|ordering reaches fulfillment only through its events: the module root is covered"`

**C80** - O `ScheduleOrderDelivery` despacha o `DeliverOrder` com o delay exatamente igual a `shop.delivery_delay_seconds` (90 no teste) a partir de `now()` (AC 55; precisão que o C64 deixou aberta)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="schedules the delivery job with a delay when the order is paid"`

**C81** - No caderno de endereços, um `409` ao salvar deixa `formMessage` preenchida e uma exclusão bem-sucedida em seguida a deixa `null`; uma exclusão que falha deixa o endereço na lista e põe a mensagem da API em `formMessage` (AC 23, 24; rodada 2 da verificação)
Proof: `docker compose exec -T frontend npx vitest run -t "clears a previous form message when a delete succeeds|keeps the address and reports a failed delete"`

**C82** - No caderno de endereços: um `save` bem-sucedido depois de um `409` deixa `formMessage` e `fieldErrors` vazios; `clearFormErrors` zera os dois; um `load` bem-sucedido depois de uma falha zera `loadError`; um `404` ao salvar (`update`) põe a mensagem da API em `formMessage` (AC 19, 23; rodada 3 da verificação)
Proof: `docker compose exec -T frontend npx vitest run -t "drops the form message once a later save succeeds|clears the form message when the form is reopened|clears the load error when a reload succeeds|shows the API message when saving an address that no longer exists"`

### S5 - Dados de demonstração · ~4 arquivos · ~12 KB · ~3k

**C74** - Depois do `db:seed`, todo cliente semeado tem ao menos 1 endereço, e `cliente@example.com` tem um endereço com `state` `SP` (AC 66)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds an address for every customer"`

**C75** - Depois do `db:seed`, todo pedido tem as colunas `delivery_*` iguais a um endereço do seu cliente, `shipping_cents` e `delivery_business_days` iguais aos da tabela de frete para o `delivery_state`, e `total_cents` igual à soma dos itens mais `shipping_cents` (AC 67)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds orders with the delivery copy and the shipping of the rate table"`

**C76** - Depois do `db:seed`, os pedidos em `payment_approved` e `delivered` têm `estimated_delivery_on` preenchida, e os em `placed` e `awaiting_payment` a têm nula (AC 68)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds the delivery estimate only for paid orders"`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| `GET /api/account/addresses` statuses (2) | 200 C1, C4 · 401 C17, C18 | - |
| `POST /api/account/addresses` statuses (4) | 201 C2, C3, C9 · 401 C17, C18 · 409 C9 · 422 C5, C6, C7, C8 | - |
| `PUT /api/account/addresses/{address}` statuses (5) | 200 C12 · 401 C17, C18 · 403 C15 · 404 C16 · 422 C13 | - |
| `DELETE /api/account/addresses/{address}` statuses (4) | 204 C14 · 401 C17, C18 · 403 C15 · 404 C16 | - |
| `GET /api/shipping/quote` statuses (3) | 200 C30, C31 · 422 C32 · 429 C33 | - |
| `POST /api/orders` statuses (5) | 201 C36 · 401 C18 · 409 C44 · 422 C40, C41 · 429 C51 | - |
| `GET /api/orders/{order}` statuses (4) | 200 C43, C70 · 401 C18 · 403 C52 · 404 C52 | - |
| `GET /api/admin/orders/{order}` statuses (3) | 200 C71 · 401 C52 · 404 C52 | - |
| chaves de `CustomerAddress` (10) | `id` C2 · `recipient_name` C2 · `postal_code` C2 · `street` C2 · `number` C2 · `complement` C2 · `district` C2 · `city` C2 · `state` C2 · `created_at` C2 | - |
| chaves do orçamento (3) | `state` C30 · `price_cents` C30 · `delivery_business_days` C30 | - |
| campos novos de `Order` (5) | `items_total_cents` C36 · `shipping_cents` C36 · `delivery.business_days` C36 · `delivery.estimated_on` C36, C70 · `delivery.address` C36 | - |
| chaves de `delivery.address` (8) | `recipient_name` C36 · `postal_code` C36 · `street` C36 · `number` C36 · `complement` C36 · `district` C36 · `city` C36 · `state` C36 | - |
| entradas de `postal_code` (6) | `"1234567"` C5 · `"0131-0100"` C5 · `"0131010a"` C5 · `"01310 100"` C5 · `"01310100"` aceito C5 · `"01310-100"` aceito C2, C5 | - |
| entradas de `state` no caderno (4) | `" sp "` → `SP` C3 · `"XX"` C6 · `""` C6 · ausente C6 | - |
| campos obrigatórios do endereço (5) | `recipient_name` C7 · `street` C7 · `number` C7 · `district` C7 · `city` C7 | - |
| limites de tamanho, 6 campos com 2 bordas (12) | `recipient_name` 120 aceito C8 · `recipient_name` 121 recusado C8 · `street` 150 aceito C8 · `street` 151 recusado C8 · `number` 20 aceito C8 · `number` 21 recusado C8 · `complement` 100 aceito C8 · `complement` 101 recusado C8 · `district` 100 aceito C8 · `district` 101 recusado C8 · `city` 100 aceito C8 · `city` 101 recusado C8 | - |
| `complement` vazio (2) | ausente C3 · `""` C3 | - |
| borda do limite de 10 endereços (2) | 10º aceito C9, C10 · 11º recusado C9, C11 | - |
| rotas do caderno que recusam visitante e sessão de equipe (4) | `GET` C17, C18 · `POST` C17, C18 · `PUT` C17, C18 · `DELETE` C17, C18 | - |
| tabela de frete, uma linha por UF (27) | C29, table-driven sobre as 27, afirmando 27 entradas · C34, table-driven sobre as 27 contra a rota | - |
| entradas de `state` no orçamento (4) | `BA` C30 · `sp` → `SP` C31 · ausente C32 · `XX` C32 | - |
| saídas do `DeliveryAddressBook` (3) | endereço próprio C47 · endereço de outro cliente C47, C41 · id inexistente C47, C41 | - |
| saídas do `PlaceOrderUseCase` (4) | pedido com frete C49, C36 · `address_id` ausente C40 · endereço não encontrado C50, C41 · estoque insuficiente C44 | - |
| dados do cliente ignorados (3) | `shipping_cents` C42 · `total_cents` C42 · `delivery_state` C42 | - |
| mudança do endereço depois da compra (2) | edição C43 · exclusão C43 | - |
| garantias de `customer_addresses`, door 1 (4) | `customer_id` existente C19 · apagado junto com o cliente C19 · CEP com 8 dígitos C20 · UF entre as 27 C20 | - |
| garantias de `orders`, door 2 (12) | `delivery_recipient_name` C45 · `delivery_postal_code` C45 · `delivery_street` C45 · `delivery_number` C45 · `delivery_district` C45 · `delivery_city` C45 · `delivery_state` C45 · `shipping_cents >= 0` C45 · `delivery_business_days > 0` C45 · `total_cents >= shipping_cents` C45 · UF entre as 27 C45 · CEP com 8 dígitos C45 | - |
| casos do calendário de dias úteis (5) | quarta + 2 C62, C63, C70 · sexta + 2 C62 · sábado + 2 C62 · 23h30 de Brasília em UTC C62 · quarta + 10 C62 | - |
| saídas do registro da data no Ordering (3) | primeira gravação C66 · repetição mantém C67 · pedido já entregue C68 | - |
| chaves do log de agendamento (2) | `order_id` C69 · `estimated_delivery_on` C69 | - |
| contratos e ligações (2) | `ShippingQuoter` → Fulfillment C34 · `DeliveryAddressBook` → Customers C46 | - |
| regras de fronteira (3) | `Fulfillment` sem `Customers` C35 · `Ordering` sem `Customers` C48 · `Ordering` só com `Fulfillment\Events` C48, uma expectativa por namespace, afirmando ao menos 4 | - |
| doors do `Landing` (5) | door 1 C2, C19, C20 · door 2 C37, C38, C43, C45 · door 3 C34, C46, C47, C48 · door 4 C63, C65, C66, C67 · door 5 C2, C30, C36, C71 | - |
| listagens que devolvem os campos novos de `Order` (2) | `GET /api/orders` C77 · `GET /api/admin/orders` C77 | - |
| `NOT NULL` do banco sem caso direto (9) | `customer_addresses.customer_id` C78 · `recipient_name` C78 · `postal_code` C78 · `street` C78 · `number` C78 · `district` C78 · `city` C78 · `state` C78 · `orders.delivery_business_days` C78 | - |
| namespace raiz do Fulfillment proibido ao Ordering (1) | `FulfillmentServiceProvider` C79 | - |
| valor do delay do `DeliverOrder` (1) | `shop.delivery_delay_seconds` C80 | - |
| mensagem do formulário do caderno em exclusões (2) | exclusão que falha mostra a mensagem C81 · exclusão que dá certo limpa a mensagem C81 | - |
| ciclo de `formMessage` e `loadError` do caderno (4) | limpa ao salvar com sucesso C82 · limpa ao reabrir o formulário C82 · `loadError` limpo ao recarregar C82 · `404` ao salvar vira mensagem C82 | - |
| textos de entrega no pedido (3) | prazo em dias C72 · data prevista C72, C73 · entregue C72 | - |
| decisões do composable do checkout (8) | sem endereço C53 · salvo no checkout C54 · pré-seleção C55 · subtotal, frete e total C56 · troca de endereço C57 · orçamento pendente ou com falha C58 · confirmação C59 · `422` em `address_id` C60 | - |
| decisões do composable do caderno (5) | carregando e falha C23 · vazio C24 · ordem da API C24 · erros do formulário C27 · confirmação de exclusão C28 | - |
| seed (3) | endereços C74 · cópia e frete dos pedidos C75 · data prevista por status C76 | - |
| startup config: ligações dos contratos (1 montagem compartilhada) | a aplicação e os testes de feature sobem o mesmo `bootstrap/providers.php`: C34, C46 | - |
| startup config: tabela de frete (1 montagem compartilhada) | a aplicação e os testes leem a mesma configuração do Fulfillment: C29, C30 | - |

- As afirmações sobre status, rota ou formato de resposta (C1 a C9, C12 a C18, C30 a C34, C36, C39 a C44, C51, C52, C70, C71) têm prova que atravessa o HTTP.
- C10, C11, C29, C47, C49, C50, C62 e C66 a C68 provam as decisões na camada da própria classe, além das provas de borda.
- C19, C20 e C45 provam as garantias no banco, sem passar pela aplicação.
- C21 a C28, C53 a C60, C72 e C73 provam a lógica do frontend sem DOM, como registra a assumption do plano. A ligação dos composables aos templates é provada só pela checagem de tipos (C61). O que fica sem prova automática é o arranjo visual das telas (AC 18 a 24, 43 a 50, 62 a 65), conferido no navegador durante a verificação.
- C18, C44, C64 e C65 estendem testes existentes sem afrouxar asserções, e C52 reaproveita dois testes existentes. Os demais nomes são testes novos.

## Test policy

O README diz onde ficam e como rodar os testes, mas não diz qual nível prova cada tipo de código. As linhas abaixo valem para esta feature.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Decide e é alcançado pelo HTTP (caso de uso de cadastrar endereço, `PlaceOrderUseCase`, implementação do `DeliveryAddressBook`) | uma na borda **e** uma na própria camada | na borda: o contrato de cada status; na camada: um check por saída |
| Regra pura do Fulfillment (tabela de frete, calendário de dias úteis) | uma na própria camada, por dataset | uma linha por UF; uma linha por caso do calendário |
| Reação a evento (agendamento no Fulfillment, registro da data no Ordering) | uma na própria camada, com o evento entregue à mão ao listener, como o `OrderStatusFlowTest` | um caso por saída |
| Garantia do banco (`CHECK`, FK, `NOT NULL`) | uma direto no banco | um caso por restrição, recusado e aceito |
| Regra de fronteira entre módulos | uma expectativa de arquitetura por namespace, vista falhando durante o build | toda regra nova |
| Decide no frontend (composables do caderno e do checkout, texto de entrega, formatação de CEP e de data, menu, rota) | uma na própria camada, com Vitest e sem DOM | um caso por decisão |
| Instrumentação (controllers, Form Requests, recursos JSON, service providers, páginas) | nenhuma própria | coberta pelas provas de borda e pela checagem de tipos |

Evidence:

- caso de uso de cadastrar endereço: 1 condição (o cliente já tem 10), 2 saídas → decide
- `PlaceOrderUseCase`: ganha 1 condição (endereço encontrado ou não) sobre a condição de estoque que já existe, 3 saídas → decide
- implementação do `DeliveryAddressBook`: 2 condições (existe, é do cliente), 3 saídas → decide
- tabela de frete: tabela de 27 linhas → decide
- calendário de dias úteis: fim de semana, fuso e contagem, 5 casos relevantes → decide
- registro da data no Ordering: 1 condição (data vazia), e o status não pode mudar, 3 casos → decide
- composable do checkout: 8 decisões; composable do caderno: 5 → decide
- controllers do caderno e do orçamento: autorizam e repassam, sem condição própria → instrumentação
- análogo no repositório: `tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php` prova um caso de uso na própria camada; `tests/Unit/Gateways/FakePaymentGatewayTest.php` prova uma tabela de decisão por linha; `tests/Feature/OrderStatusFlowTest.php` entrega eventos à mão aos listeners; `tests/Feature/Auth/EmailAndPasswordTest.php` prova `CHECK` direto no banco; `composables/usePayment.test.ts` prova um composable com os services simulados; `router/guards.test.ts` prova o registro de rotas

Cost: 10 provas na própria camada no backend (C10, C11, C29, C47, C49, C50, C62, C66 a C68) e 15 no frontend (C21 a C28, C53 a C60, C72, C73, menos as que já atravessam a borda). Sem estas linhas, o limite de 10, a tabela de frete e o calendário seriam provados só pelos caminhos que o HTTP atravessa, e os composables ficariam sem prova.

## Swept

- validation: C5, C6, C7, C8, C13, C32, C40, C41, C42; os `CHECK` do banco em C20 e C45
- failure modes: C50, porque o endereço recusado não chega a travar o estoque; C41 e C44, porque nenhum pedido sobra quando a criação falha; C58, porque a falha do orçamento no navegador bloqueia a confirmação. A criação do pedido continua numa transação só (C37, door 2)
- idempotency: C67, porque um `DeliveryScheduled` repetido não muda a data; C64, porque o retry do agendamento continua seguro com a entrega idempotente que já existe. Pedido duplicado por duplo clique fica como hoje, sem chave de idempotência (fora do plano)
- authorization: C15, C17, C18; C30, porque o orçamento é público; existing - `OrderPolicy` (`view`) para pedido de outro cliente, no `AuthorizationTest`
- concurrency: C68, porque o `DeliveryScheduled` pode chegar depois do `OrderDelivered`; o 11º endereço por cadastros simultâneos e o endereço excluído entre a leitura e o commit foram aceitos sem trava no plano (Assumptions e discovery); a corrida de estoque segue provada por "locks the stock rows with SELECT ... FOR UPDATE"
- data lifecycle: C19, porque o caderno é apagado com a conta; C43, porque a cópia do pedido sobrevive à edição e à exclusão; o banco é recriado pelo `make fresh`, sem backfill (plano, Impact)
- dependency failure: n/a - a feature não chama nenhum serviço externo; o orçamento é uma regra local do Fulfillment, e a falha de rede no navegador é C58
- state transitions: C66 e C68, porque registrar a data não muda o status; C64 e o `OrderStatusFlowTest` existente para as transições do pedido, que não mudam
- observability: C69

## Handoff

- S1 = ~16k (Customers, rotas e frontend do caderno); S2 entra no Fulfillment em ~19k; S3 cruza Ordering, Customers e o checkout em ~40k; S4 em ~47k; S5 em ~50k; a atualização do README e da análise de domínio (98 KB) leva a ~74k, abaixo do budget de 150k - one builder
- Mechanism: one builder (cabe no orçamento, sem pergunta)
