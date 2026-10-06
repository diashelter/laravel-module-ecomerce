# Dinheiro em centavos inteiros verification

**Verdict**: PASS
**Profile**: light
**Diff range**: 0f37c32..e4377f2 (HEAD; commits 604c5d7, dcd6fe9, e4377f2)
**Round**: 1 - full
**Verifier**: independent sub-agent (author != verifier)

Execuções (todas no HEAD `e4377f2`, árvore limpa fora de `.design/` e `.specs/`):

- **B1** - uma única chamada Pest com alternância dos 28 nomes de prova do backend (`docker compose exec -T api ./vendor/bin/pest --filter="stores every money column as bigint not null` ... `approves the fake payment of an order awaiting payment"`): **86 passed (457 assertions)**. Cada teste nomeado apareceu individualmente no output (incluindo cada linha de dataset: 4 colunas em C1/C2, 14 entradas em C5, 5 campos x 2 rotas em C15, 13 rotas em C20, 8 em C21, 8 em C22).
- **F1** - uma única chamada Vitest: `docker compose exec -T frontend npx vitest run src/utils/money.test.ts src/stores/cart.test.ts --reporter=verbose -t "<9 nomes em alternância>"`: **9 passed**, cada nome listado individualmente.
- **G** - provas estruturais `grep` de C18, C32, C34, C35 e C36 executadas da raiz; todas saíram `0` (as negadas não imprimiram nenhuma linha).
- Existência de cada teste nomeado mostrada com `rg -n --fixed-strings "<nome>" backend/tests` (linhas citadas abaixo). Todos os arquivos de prova estão no diff, exceto `AuthorizationTest.php` e `PaymentTest.php` (provas de status pré-existentes, legítimas para C21/C25, que afirmam comportamento inalterado).

## Checks

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | 4 colunas de dinheiro `bigint not null` | B1 `stores every money column as bigint not null` x4 datasets, passed | `backend/tests/Feature/MoneyInCentsTest.php:47` - `expect($definition->data_type)->toBe('bigint')->and($definition->is_nullable)->toBe('NO')` sobre `information_schema.columns`; dataset das 4 colunas em `:14-19`. Código: `backend/database/migrations/2026_10_05_000002_create_products_table.php:16` | PASS |
| C2 | `-1` rejeitado com a constraint nomeada, valor intacto | B1 `rejects a negative value in every money column` x4, passed | `backend/tests/Feature/MoneyInCentsTest.php:62` - `->toThrow(QueryException::class, "{$table}_{$column}_non_negative")`; `:64` - `->value($column))->toBe($original)`. Constraints: `create_products_table.php:25`, `create_orders_table.php:24`, `create_order_items_table.php:25-26` | PASS |
| C3 | POST produto 201, `price_cents` int 129990, sem `price` | B1 `creates a product with its price in cents`, passed | `backend/tests/Feature/Admin/ProductTest.php:24-26` - `->assertCreated()->assertJsonPath('data.price_cents', 129990)->assertJsonMissingPath('data.price')` (`assertJsonPath` é estrito, então o tipo inteiro é cobrado) | PASS |
| C4 | PUT produto 200, `price_cents` 19990 e persistido | B1 `updates the product price in cents`, passed | `backend/tests/Feature/MoneyInCentsTest.php:74-77` - `->assertOk()->assertJsonPath('data.price_cents', 19990)`; `expect($product->fresh()->price_cents)->toBe(19990)` | PASS |
| C5 | 7 entradas inválidas -> 422 em `price_cents`, em POST e PUT | B1 `rejects an invalid price_cents` x14 datasets, passed | `backend/tests/Feature/MoneyInCentsTest.php:89` - `->assertUnprocessable()->assertJsonValidationErrors('price_cents')`; dataset das 7 entradas x 2 métodos em `:93-107`. Regra: `backend/app/Modules/Catalog/Http/Requests/Admin/ProductRequest.php:21` | PASS |
| C6 | `9999999999` aceito com 201 | B1 `accepts the maximum price_cents`, passed | `backend/tests/Feature/MoneyInCentsTest.php:112-113` - `->assertCreated()->assertJsonPath('data.price_cents', 9999999999)` | PASS |
| C7 | catálogo público expõe `price_cents` int, sem `price` | B1 `exposes price_cents as an integer on the public catalog`, passed | `backend/tests/Feature/MoneyInCentsTest.php:138-139` - `expect($payload['price_cents'])->toBe(129990)`; `assertNoLegacyMoneyKeys($payload)` (helper em `:34-38`, `not->toHaveKey` para `price`, `unit_price`, `subtotal`, `total`) para lista e detalhe | PASS |
| C8 | 3 rotas admin de produto expõem `price_cents` int, sem `price` | B1 `exposes price_cents on every admin product route`, passed | `backend/tests/Feature/MoneyInCentsTest.php:125-126` - `expect($payload['price_cents'])->toBe(129990)`; `assertNoLegacyMoneyKeys($payload)` sobre index, show e PATCH status (`:119-121`) | PASS |
| C9 | sort por `price_cents` asc/desc com desempate por `id` | B1 `sorts the catalog with id as tie breaker` x3 e `sorts products` x3, passed | `backend/tests/Feature/Repositories/ProductRepositoryTest.php:29` - `expect($page->pluck('name')->all())->toBe($expectedNames)` com `price_asc` -> `['C','D','B','A']` e `price_desc` -> `['A','B','C','D']` (C e D empatados em 1000, `:32-33`); HTTP: `backend/tests/Feature/ProductCatalogTest.php:39` (`expect($names)->toBe($expected)`). Código: `ProductRepository.php:38-39` | PASS |
| C10 | `CheckoutService` monta 5970 + 30 = 6000 em int | B1 `builds the order lines and total in cents`, passed | `backend/tests/Unit/Services/CheckoutServiceTest.php:53-57` - `expect($result['total_cents'])->toBe(6000)->and($result['lines'])->toBe([... 'unit_price_cents' => 1990, ... 'subtotal_cents' => 5970], [... 'unit_price_cents' => 10, ... 'subtotal_cents' => 30])` | PASS |
| C11 | cart/validate 1990 x 3 -> 1990/5970/5970 int | B1 `recalculates the cart in cents from database prices`, passed | `backend/tests/Feature/CartValidationTest.php:13-15` - `->assertJsonPath('data.total_cents', 5970)->assertJsonPath('data.items.0.unit_price_cents', 1990)->assertJsonPath('data.items.0.subtotal_cents', 5970)` | PASS |
| C12 | produto inexistente -> nulls, fora do total, `is_valid` false | B1 `returns null cents for a product that does not exist`, passed | `backend/tests/Feature/MoneyInCentsTest.php:147-150` - `assertJsonPath('data.items.0.unit_price_cents', null)`, `...subtotal_cents', null)`, `('data.total_cents', 0)`, `('data.is_valid', false)` | PASS |
| C13 | linha indisponível -> `subtotal_cents` int, fora do total | B1 `keeps an unavailable line out of total_cents`, passed | `backend/tests/Feature/MoneyInCentsTest.php:164-166` - `expect($lines[$inactive->id]['subtotal_cents'])->toBe(5000)->and($response->json('data.total_cents'))->toBe(1000)->and(...is_valid)->toBeFalse()` | PASS |
| C14 | POST orders 2x9990 + 3x25000 -> 201, 94980, itens gravados | B1 `places an order with every value in cents, decrements stock and stores a snapshot of the items`, passed | `backend/tests/Feature/CheckoutTest.php:33-35` - `->assertCreated()->assertJsonPath('data.total_cents', 94980)`; `:45-49` - `unit_price_cents->toBe(9990)`, `subtotal_cents->toBe(19980)`, `unit_price_cents->toBe(25000)`, `subtotal_cents->toBe(75000)` | PASS |
| C15 | 5 campos de dinheiro do cliente ignorados nas 2 rotas | B1 `ignores money fields sent by the client` x5 e `... on checkout` x5, passed | `backend/tests/Feature/MoneyInCentsTest.php:177-180` - `assertJsonPath('data.items.0.unit_price_cents', 1990)`, `subtotal_cents 5970`, `total_cents 5970` com `->with(['price','unit_price','total','unit_price_cents','total_cents'])`; checkout `:200-204` - `->assertCreated()->assertJsonPath('data.total_cents', 5970)` com o mesmo dataset | PASS |
| C16 | snapshot de `unit_price_cents` sobrevive à mudança do produto | B1 `keeps the historical snapshot when the product changes later`, passed | `backend/tests/Feature/CheckoutTest.php:62` - `expect($item->product_name)->toBe('Old name')->and($item->unit_price_cents)->toBe(1000)` depois de `update(['price_cents' => 9900])` (`:59`) | PASS |
| C17 | 7 rotas de pedido expõem `*_cents` int, sem chaves antigas | B1 `exposes order money in cents on every order route`, passed | `backend/tests/Feature/MoneyInCentsTest.php:213-218` - `expect($payload['total_cents'])->toBe(94980)`; `assertNoLegacyMoneyKeys($payload)`; itens `unit_price_cents->toBe(9990)`, `subtotal_cents->toBe(19980)`; aplicado às 7 rotas em `:223-231` (com `assertJsonCount(1, 'data.items')` em show e admin show) | PASS |
| C18 | nenhum `BcMath` em `backend/app` e `backend/database` | G: negação de `grep -rn "BcMath" backend/app backend/database`, exit 0, sem saída | `backend/app/Modules/Ordering/Services/CheckoutService.php:1` - busca vazia em toda a árvore de `backend/app` e `backend/database` (o diff removeu os usos em `CheckoutService.php` e `CartValidationService.php`) | PASS |
| C19 | seed: total = soma dos subtotais e subtotal = unit x qty | B1 `seeds orders whose total_cents is the sum of their items`, passed | `backend/tests/Feature/MoneyInCentsTest.php:238` - `expect($order->total_cents)->toBe($order->items->sum('subtotal_cents'))`; `:241` - `expect($item->subtotal_cents)->toBe($item->unit_price_cents * $item->quantity)`. Assembly `make fresh` lido diretamente: SQL no banco de desenvolvimento após o seed, 0 de 24 pedidos e 0 itens violando | PASS |
| C20 | 401 para visitante nas 13 rotas autenticadas | B1 `keeps 401 for guests on the money routes` x13, passed | `backend/tests/Feature/MoneyInCentsTest.php:269` - `$this->json($method, $uri)->assertUnauthorized()`; dataset das 13 rotas em `:247-261` | PASS |
| C21 | 403 para cliente nas 8 rotas admin | B1 `keeps 403 for customers on the admin money routes` x8, `forbids a customer from seeing another customer order`, `forbids paying an order of another customer`, passed | `backend/tests/Feature/MoneyInCentsTest.php:278` - `->assertForbidden()` sobre as 8 rotas de `:280-287`; `backend/tests/Feature/AuthorizationTest.php:33` e `backend/tests/Feature/PaymentTest.php:35` - `->assertForbidden()` | PASS |
| C22 | 404 para id 999999 nas 8 rotas com parâmetro | B1 `keeps 404 for unknown ids on the money routes` x8, passed | `backend/tests/Feature/MoneyInCentsTest.php:294` - `$this->json($method, $uri)->assertNotFound()`; `str_replace('/1', '/999999', $uri)` em `:292`; 8 rotas em `:296-303` | PASS |
| C23 | 422 com formato padrão de erro nas 3 rotas | B1 `keeps 422 for invalid payloads on the money routes`, passed; suplementares `validates the cart payload` e `requires authentication and valid items`, passed | `backend/tests/Feature/MoneyInCentsTest.php:312-316` - PATCH status `->assertUnprocessable()->assertJsonValidationErrors('status')`; orders e cart só `->assertUnprocessable()`. Formato coberto fora da prova nomeada: `backend/tests/Feature/CartValidationTest.php:40` (mesmo payload, `assertJsonValidationErrors(['items.0.product_id','items.0.quantity'])`) e `backend/tests/Feature/CheckoutTest.php:148` (`assertJsonValidationErrors('items')`, payload diferente). Ver lacuna de precisão 1 | PASS |
| C24 | 61ª chamada a cart/validate -> 429 | B1 `throttles cart validation at 60 requests per minute`, passed | `backend/tests/Feature/MoneyInCentsTest.php:184-188` - 60 x `->assertOk()` e então `->assertStatus(429)` | PASS |
| C25 | 409 sem gravar no estoque insuficiente; 202 no pagamento | B1 `returns 409 and changes nothing when stock is insufficient` e `approves the fake payment of an order awaiting payment`, passed | `backend/tests/Feature/CheckoutTest.php:86` - `->assertConflict()`; `:92-94` - `Order::query()->count())->toBe(0)` e estoques intactos; `backend/tests/Feature/PaymentTest.php:15` - `->assertAccepted()` | PASS |
| C26 | `make test-frontend` roda `vitest run` no container e sai 0 | `make test-frontend` com `pipefail`, exit 0, 2 arquivos / 9 testes | `Makefile:121-122` - `test-frontend:` / `$(EXEC_NODE) npm test` (`EXEC_NODE = $(DC) exec frontend`, `Makefile:8`); `frontend/package.json:14` - `"test": "vitest run"`; `frontend/package.json:32` - `"vitest": "^4.0.0"` | PASS |
| C27 | `formatCents` 129990/5/0 em BRL pt-BR | F1 `formats cents as BRL`, passed | `frontend/src/utils/money.test.ts:9-11` - `expect(formatCents(129990)).toBe(\`R$${nbsp}1.299,90\`)`, `formatCents(5)` -> `R$ 0,05`, `formatCents(0)` -> `R$ 0,00` (nbsp = U+00A0, `:5`) | PASS |
| C28 | `—` para null e undefined | F1 `renders a dash for a missing value`, passed | `frontend/src/utils/money.test.ts:15-16` - `expect(formatCents(null)).toBe('—')`; `expect(formatCents(undefined)).toBe('—')` | PASS |
| C29 | `parseReaisInput` aceita os 4 formatos | F1 `parses reais input into cents`, passed | `frontend/src/utils/money.test.ts:20-23` - `toBe(19990)` para `199,90`, `199.90`, `199,9` e `toBe(19900)` para `199` | PASS |
| C30 | recusa as 5 entradas e expõe a mensagem | F1 `rejects invalid reais input` e `exposes the invalid price message`, passed | `frontend/src/utils/money.test.ts:27-28` - `for (const text of ['', '19,999', '1.299,90', '-10', 'abc']) expect(parseReaisInput(text), text).toBeNull()`; `:33` - `expect(INVALID_PRICE_MESSAGE).toBe('Informe um preço válido, por exemplo 199,90.')` | PASS |
| C31 | `centsToReaisInput` 19990/5/129990 | F1 `renders cents as reais input`, passed | `frontend/src/utils/money.test.ts:37-39` - `toBe('199,90')`, `toBe('0,05')`, `toBe('1299,90')` | PASS |
| C32 | formulário ligado a parse, mensagem, preenchimento e erro do backend | G: os 4 `grep -q`, exit 0; mais confirmação no navegador (ver Runtime) | `frontend/src/pages/admin/ProductFormPage.vue:59` - `parseReaisInput(form.price)`; `:61` - `priceError.value = INVALID_PRICE_MESSAGE`; `:46` - `price: centsToReaisInput(product.value.price_cents)`; `:124` - `<FieldError :message="priceError ?? first('price_cents')" />` | PASS |
| C33 | chave `cart` removida; só `cart-v2` gravada com `price_cents` int | F1 `discards the legacy cart key` e `persists the cart only under cart-v2`, passed | `frontend/src/stores/cart.test.ts:47-48` - `expect(storage.has('cart')).toBe(false)`; `expect(cart.isEmpty).toBe(true)`; `:56-57` - `expect([...storage.keys()]).toEqual(['cart-v2'])` e `toMatchObject({ product_id: 1, price_cents: 1990, quantity: 2 })` | PASS |
| C34 | total 1990x3 + 25000x1 = 30970; nenhum `toCents` | F1 `totals price_cents times quantity`, passed; G: negação de `grep -rn "toCents" frontend/src`, exit 0 | `frontend/src/stores/cart.test.ts:65` - `expect(cart.totalCents).toBe(30970)`; código `frontend/src/stores/cart.ts:57` - `return item.price_cents * item.quantity` | PASS |
| C35 | type-check 0; nenhum campo de dinheiro `string` | `docker compose exec -T frontend npm run type-check` (`vue-tsc -b`), exit 0; G: negação do `grep -nE` em `types/index.ts` e `stores/cart.ts`, exit 0 | `frontend/src/types/index.ts:26` - `price_cents: number`; `:57` `unit_price_cents: number`; `:59` `subtotal_cents: number`; `:71` `total_cents: number`; `:106-116` linhas do carrinho `number` ou `number or null`; `frontend/src/stores/cart.ts:9` - `price_cents: number`. Os `total: number` restantes (`:87`, `:129`) são `PaginationMeta.total` e `ChartPoint.total`, contagens fora do escopo pelo plano | PASS |
| C36 | README descreve bigint em centavos e a decisão; sem BcMath/decimal | G: os 3 `grep -q` e a negação, todos exit 0 | `README.md:289` - `price_cents bigint` (centavos inteiros); `README.md:292` - `total_cents bigint`; `README.md:293` - `subtotal_cents bigint`; `README.md:634` - `**Dinheiro**: centavos inteiros do banco à tela ...`; busca por `BcMath` e `decimal(10,2)`/`decimal(12,2)` vazia em `README.md` | PASS |

## Runtime - formulário de produto (ACs 24 a 26)

O navegador embutido estava disponível e foi usado em `http://localhost:8080`, logado como o admin do seed (`backend/database/seeders/UserSeeder.php:19`, senha de teste do `UserFactory`).

**`make fresh` foi executado** antes do teste: o banco de desenvolvimento já tinha o schema novo (`products.price_cents`), mas estava com 0 usuários e 0 produtos, então o login era impossível. Depois do seed: 11 usuários e 30 produtos.

| Passo | Esperado | Observado | Resultado |
| --- | --- | --- | --- |
| Novo produto, preço vazio, Salvar | bloqueia e mostra a mensagem | `Informe um preço válido, por exemplo 199,90.` sob o campo; nenhuma requisição a `/api/admin/products` no log de rede | PASS |
| Preço `19,999`, Salvar | idem | mensagem presente; nenhuma requisição | PASS |
| Preço `abc`, Salvar | idem | valor do input `abc`, mensagem presente; nenhuma requisição | PASS |
| Preço `1299,90`, Salvar | cria e grava 129990 | uma única `POST /api/admin/products -> 201`; banco `products.id=31` com `price_cents=129990`; o redirecionamento para edição mostra `1299,90` | PASS |
| Vitrine `/products/31` | `R$ 1.299,90` | `R$ 1.299,90` | PASS |
| Editar produto do seed (`price_cents` 28090), carga completa da página | campo no formato `199,90` | `280,90` | PASS |
| Editar o produto 31 para `199,90`, salvar e recarregar | grava 19990 e mostra `199,90` | `PUT -> 200`, banco `19990`, campo `199,90` após recarga | PASS |
| Preço `999999999999` (aceito pelo front, acima do teto do back) | 422 mostrado sob o campo (AC 26) | `PUT -> 422`; sob o campo: `O campo price cents não pode ser maior que 9999999999.` | PASS (ver nota 2) |

O produto de teste `Verifier Monitor` (id 31, `price_cents` 19990) ficou no banco de desenvolvimento.

## Notas de nível e amostragem (não reprovam)

1. **Lacuna de precisão em C23.** A prova nomeada só cobra o formato de erro (`assertJsonValidationErrors`) no `PATCH .../status`; em `POST /api/orders` e `POST /api/cart/validate` ela afirma só o status `422`. O formato está provado por testes existentes (`CartValidationTest.php:40`, com o mesmo payload, e `CheckoutTest.php:148`, com outro payload do mesmo FormRequest), que rodei à parte e passaram. Fica a recomendação de cobrar o formato na própria prova de C23.
2. **Mensagem de 422 do preço em inglês técnico.** `backend/lang/pt_BR/validation.php:40` ainda traduz o atributo `price` => `preço`, e não `price_cents`. Por isso o admin vê `O campo price cents não pode ser maior que 9999999999.`: o nome do campo cru e o limite em centavos, enquanto ele digita reais. Antes da mudança a mensagem dizia `O campo preço ...`. O AC 26 (mostrar a mensagem sob o campo) é cumprido, mas é uma regressão de texto que nenhum check cobre.
3. **Desempate por `id` (C9).** Provado na camada do repositório (`ProductRepositoryTest.php:21`). A prova HTTP (`ProductCatalogTest.php:32`) não tem empate de preço. A ordenação não é afirmação de status/rota/formato, então não é lacuna de nível.
4. **Amostragem em C17.** `GET /api/account` é verificado só em `data.last_order` (não em `recent_orders`), e `GET /api/admin/users/{user}` só em `data.orders.0`. Os dois usam o mesmo `OrderResource`, o que torna o risco baixo.
5. **C12** prova "fora do `total_cents`" com uma única linha (`total_cents` 0). O C13 cobre o caso misto.
6. **C14** afirma os itens no banco, não na resposta. Os itens na resposta ficam cobertos por C15 e C17.

## Swept - linhas "existing" relidas no código

- failure modes: rollback do checkout. `backend/app/Modules/Ordering/UseCases/PlaceOrderUseCase.php:38` - `DB::transaction(...)` envolve trava, checagem, cálculo e gravação; o teste `CheckoutTest.php:77` existe e passou em B1. Confirmado.
- concurrency: `PlaceOrderUseCase.php:44` - `lockForProducts` (SELECT ... FOR UPDATE) antes de `buildOrderLines` (`:57`), dentro da mesma transação; o teste `locks the stock rows with SELECT ... FOR UPDATE` existe em `CheckoutTest.php:123`. Confirmado.
- authorization: citado como C20/C21, middlewares inalterados (o diff não toca `routes/` nem policies). Confirmado.
- idempotency (n/a): o teste citado existe em `backend/tests/Feature/OrderStatusFlowTest.php:97` e roda no `make test` completo.

## Sobras de nomes antigos fora dos checks (notas)

- `backend/lang/pt_BR/validation.php:40` - atributo `price` sem equivalente `price_cents` (nota 2).
- Backend e frontend: a varredura `rg` por `price`, `unit_price`, `subtotal` e `total` como identificadores, em `backend/app`, `backend/database`, `backend/routes` e `frontend/src`, encontra só contagens (`total` das séries do dashboard e da paginação, `count(*) as total` em `OrderRepository`/`ProductRepository`), o campo local `form.price` do formulário (texto digitado, convertido por `parseReaisInput`) e comentários em prosa. Nenhum valor monetário com o nome antigo.
- Testes: os usos restantes de `price`/`unit_price`/`total` são intencionais (payloads de entrada maliciosa e o helper de chaves proibidas).
- `README.md`: nenhuma sobra; inclui `make test-frontend` (`:250`, `:557`, `:560`).
- `docs/domain-analysis.md`: só prosa de linguagem ubíqua (`:104` "total, snapshot de preço", `:110` "com total e status"). Nada desatualizado, e o documento não foi tocado no diff, o que parece coerente, já que nenhum contexto ou fronteira mudou.
- `backend/composer.json:9` `"php": "^8.3"` e `bcmath` em `docker/php/Dockerfile:6` permanecem, como a premissa do plano decidiu.

## Gate

- `make test` - 318 passed (1022 assertions), 0 failed, exit 0
- `docker compose exec -T api ./vendor/bin/pint --test` - PASS, 203 files, exit 0
- B1 (provas do backend, uma chamada) - 86 passed (457 assertions), 0 failed
- Suplementares de C23 - `validates the cart payload` 1 passed; `requires authentication and valid items` 1 passed
- `make test-frontend` - 2 files, 9 passed, 0 failed, exit 0
- F1 (provas do frontend, uma chamada) - 9 passed, 0 failed
- `docker compose exec -T frontend npm run type-check` - exit 0
- Provas estruturais `grep` (C18, C32 x4, C34, C35, C36 x4) - todas exit 0
- `make fresh` - executado uma vez para viabilizar o login no navegador (banco de dev vazio)
