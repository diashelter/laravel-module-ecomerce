# Contas de equipe e contas de cliente - verification

**Verdict**: PASS
**Profile**: standard
**Diff range**: b40d4ee..a4faa5a (5460bd7 backend, 39f45da frontend, a4faa5a docs)
**Round**: 1 - full
**Verifier**: independent sub-agent (author != verifier)

Todas as seções foram verificadas em `a4faa5a`. Nada foi herdado de rodadas anteriores.

## Invocações de prova

| Id | Comando | Resultado |
| --- | --- | --- |
| B1 | `docker compose exec -T api ./vendor/bin/pest --filter="<alternância dos 55 nomes de teste do backend em checks.md>"` (uma invocação) | 99 passed (423 assertions); cada nome aparece individualmente na saída, inclusive cada linha de dataset |
| B2 | `docker compose exec -T api ./vendor/bin/pest` (suíte completa) | 540 passed (1841 assertions) |
| P | `docker compose exec -T api ./vendor/bin/pint --test` | PASS, 259 files |
| V | `docker compose exec -T frontend npx vitest run --reporter=verbose` (suíte completa, nomes lidos um a um) | 32 passed, 7 files |
| T | `docker compose exec -T frontend npm run type-check` | exit 0 |
| G | `! grep -n "auth:sanctum" backend/routes/api.php` · `! grep -n "Painel admin" frontend/src/components/AppNavbar.vue` · `! grep -rnE "isCustomer\|requiresCustomer" frontend/src` | os três exit 0 (nenhuma ocorrência) |

Cada nome de teste foi localizado com `rg -n --fixed-strings` em `backend/tests` e `frontend/src`. Todos existem. Todos os arquivos de teste estão no diff, com uma exceção: `tests/Feature/Admin/ProductTest.php` e `tests/Feature/Admin/CategoryTest.php` (provas de C42) não foram alterados. Eles rodam com `beforeEach(fn () => $this->actingAs(admin()))` (`ProductTest.php:7`, `CategoryTest.php:5`), por meio do `admin()` e do `actingAs` alterados no diff (`tests/Pest.php:27-30`, `tests/TestCase.php:17-19`), e contra as rotas `DELETE` que o diff moveu para o grupo `admin` (`routes/api.php:78-82`). C42 afirma um comportamento que **continua** igual para o admin, então um teste de regressão que já existia é a prova certa. O nome "deletes a product without orders together with its stock" também casa com `tests/Feature/UseCases/Product/ProductUseCasesTest.php:94`, que rodou e passou (prova extra, sem prejuízo).

## Binding sources

| Source | Opened | Contradiction | Uncovered |
| --- | --- | --- | --- |
| `.design/staff-and-customer-accounts.md` (as 7 Key decisions, as tabelas de estado das 5 slices, os contratos) | sim, lido inteiro (231 linhas) | none | - |
| `AGENTS.md` (fronteiras, `ModuleBoundariesTest` com um namespace por expectativa) | sim (contexto do projeto) | none | - |

Comparação estreita (o passo 1 só é obrigatório no perfil `ui`, mas foi feito). Os 401 e 403 por área (KD 2, 6), o 422 "E-mail ou senha inválidos." para credenciais da outra tabela (KD 2), o 409 com as duas mensagens (KD 7), o mesmo e-mail nas duas tabelas (KD 1), `customer_id` com `restrictOnDelete` (KD 4), o papel `admin` ou `support` sem default (KD 5), o teste que percorre a tabela de rotas `DELETE` (KD 6), o `/admin/*` com sessão de cliente indo para `/admin/login` e não para "Acesso negado", e o checkout pedindo o login de cliente para um membro da equipe: todos batem com os checks C1 a C63. A linha de design "Admin ou suporte abre um pedido: nome e e-mail do comprador vêm de `customers`" não virou AC no plano. Ela é coberta pela prova existente `tests/Feature/Admin/OrderTest.php:14`, que afirma a estrutura `customer => ['name']` e foi tocada no diff (`data.customer.id` → `customer_id`).

## Checks

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | register: 201, 4 chaves exatas, 1 em customers, 0 em users, guard customer, staff visitante | B1 "registers a customer account in the customers table" ✓ | `backend/tests/Feature/Auth/AuthTest.php:17` `->assertCreated()`; `:19` `array_keys(data)->toEqualCanonicalizing(CUSTOMER_KEYS)` (`:9` = created_at,email,id,name); `:20-21` customers count `1`, `User::count()` `0`; `:22` `assertAuthenticatedAs(..., 'customer')`; `:23` `assertGuest('staff')` | PASS |
| C2 | register com e-mail só da equipe: 201, 1 em cada tabela | B1 ✓ | `backend/tests/Feature/Auth/AuthTest.php:29` `assertCreated()`; `:31-32` customers `1` e users `1` com `ana@example.com` | PASS |
| C3 | register com e-mail de cliente: 422 em email, continua 1 linha | B1 ✓ | `backend/tests/Feature/Auth/AuthTest.php:39-40` `assertUnprocessable()->assertJsonValidationErrors('email')`; `:42` count `toBe(1)` | PASS |
| C4 | login da loja 200 com 4 chaves; me 200 com as mesmas 4 | B1 ✓ | `backend/tests/Feature/Auth/AuthTest.php:59` `assertOk()`; `:62` me `assertOk()`; `:64-65` as duas respostas `toEqualCanonicalizing(CUSTOMER_KEYS)` | PASS |
| C5 | credenciais da equipe no login da loja: 422 com a mensagem exata, os dois guards visitantes | B1 ✓ | `backend/tests/Feature/Auth/AuthTest.php:73-74` `assertJsonPath('errors.email', ['E-mail ou senha inválidos.'])`; `:76-77` `assertGuest('customer')`, `assertGuest('staff')` | PASS |
| C6 | sessão de equipe: 401 nas 7 rotas da loja, nada gravado | B1 ✓ (7 linhas de dataset) | `backend/tests/Feature/AuthorizationTest.php:95` `actingAs(admin())->json(...)->assertUnauthorized()`; `:97-99` orders `1`, payments `0`, status inalterado; dataset `:22-30` (7 rotas) | PASS |
| C7 | pedido grava orders.customer_id = customers.id | B1 ✓ | `backend/tests/Feature/CheckoutTest.php:192` `assertCreated()`; `:194` `Order::sole()->customer_id)->toBe($account->id)` | PASS |
| C8 | FK: delete com pedido recusado, sem pedido apagado, customer_id inexistente recusado | B1 ✓ | `backend/tests/Feature/Models/CustomerAccountTest.php:25-26` delete recusado e a linha continua; `:27` delete do cliente sem pedido `toBe(1)`; `:28` insert com `customer_id` `999999` recusado (QueryException capturada em `:15`) | PASS |
| C9 | CHECK e UNIQUE de customers.email; o mesmo e-mail em users não impede | B1 ✓ (3 linhas) | `backend/tests/Feature/Models/CustomerAccountTest.php:35-36` `admin(['email'=>'ana@example.com'])` e depois a primeira inserção `toBeTrue()`; `:37` `refused(...)->toBeTrue()`; `:38` count `1`; dataset `:40-42` maiúscula, com espaço, duplicado | PASS |
| C10 | perfil com e-mail da equipe: 200 e grava | B1 ✓ | `backend/tests/Feature/Account/AccountTest.php:57` `assertOk()`; `:59` `fresh()->email)->toBe('equipe@example.com')` | PASS |
| C11 | perfil com e-mail de outro cliente: 422 em email, sem mudança | B1 ✓ | `backend/tests/Feature/Account/AccountTest.php:67-68` `assertUnprocessable()->assertJsonValidationErrors('email')`; `:70` email continua `mine@example.com` | PASS |
| C12 | account: chaves exatas em data e em data.customer, sem role | B1 ✓ | `backend/tests/Feature/Account/AccountTest.php:78` `toEqualCanonicalizing(['customer','last_order','orders_count','recent_orders'])`; `:79` data.customer `toEqualCanonicalizing(['created_at','email','id','name'])` | PASS |
| C13 | 3 expectativas de arquitetura, uma por namespace | B1 ✓ "Ordering/Payment/Fulfillment does not use the staff user" (3 linhas) | `backend/tests/Unit/Architecture/ModuleBoundariesTest.php:48-51` `foreach (['Ordering','Payment','Fulfillment'])` → `arch(...)->expect("App\\Modules\\{$module}")->not->toUse(User::class)`, um namespace por expectativa | PASS |
| C14 | seed: 10 customers, cliente@example.com com senha password, nenhum em users | B1 ✓ | `backend/tests/Feature/SeederTest.php:50` `where('email','cliente@example.com')->sole()`; `:52` count `toBe(UserSeeder::CUSTOMERS)`, sendo `backend/database/seeders/UserSeeder.php:15` `CUSTOMERS = 10`; `:53` `Hash::check('password', ...)`; `:54` nenhum em users | PASS |
| C15 | guard: só equipe → /checkout vai para o login com redirect | V ✓ "sends a staff-only browser from checkout to the store login" | `frontend/src/router/guards.test.ts:19` `toEqual({ name: 'login', query: { redirect: '/checkout' } })` com `staffOnly('admin')` (`:8`, customer false) | PASS |
| C16 | routes/api.php sem auth:sanctum | G exit 0 | grep sem ocorrência; os grupos declaram o guard em `backend/routes/api.php:31` `auth:customer`, `:44` `auth:customer`, `:59` `auth:staff`; reforço que percorre a tabela de rotas: `backend/tests/Feature/AuthorizationTest.php:119-121` `expect($sanctumRoutes)->toHaveCount(0)` (passou em B2) | PASS |
| C17 | 429 na 11ª requisição nas 3 rotas; as 10 primeiras não | B1 ✓ (3 linhas) | `backend/tests/Feature/Auth/AuthTest.php:111` `->not->toBe(429)` em 10 tentativas; `:114` `assertStatus(429)`; dataset `:115` com as 3 URIs | PASS |
| C18 | login da equipe 200, 6 chaves, guard staff, customer visitante, me na sessão relida, admin→Administrador, support→Suporte | B1 ✓ (2 linhas) | `backend/tests/Feature/Auth/StaffAuthTest.php:15-17` `assertOk()`, `assertAuthenticatedAs($member,'staff')`, `assertGuest('customer')`; `:20-21` `forgetGuards()` e depois me `assertOk()`; `:23-27` as duas respostas `toEqualCanonicalizing(STAFF_KEYS)`, role e `role_label` `$label`; dataset `:29-30` | PASS |
| C19 | credenciais de cliente no login do admin: 422 com a mensagem exata, nenhum guard | B1 ✓ | `backend/tests/Feature/Auth/StaffAuthTest.php:37-38` `assertJsonPath('errors.email', ['E-mail ou senha inválidos.'])`; `:40-41` `assertGuest('staff')`, `assertGuest('customer')` | PASS |
| C20 | sessão de cliente: 401 em todas as 27 rotas admin pela tabela de rotas | B1 ✓ | `backend/tests/Feature/AuthorizationTest.php:73` `expect($routes)->toHaveCount(27)`; `:76` `status())->toBe(401, ...)` por rota; enumeração `:51-60` | PASS |
| C21 | visitante: 401 nas mesmas 27 | B1 ✓ | `backend/tests/Feature/AuthorizationTest.php:83` `toHaveCount(27)`; `:86` `toBe(401, ...)` | PASS |
| C22 | senha errada no login do admin: 422 com a mensagem exata | B1 ✓ | `backend/tests/Feature/Auth/StaffAuthTest.php:48-49` `assertUnprocessable()->assertJsonPath('errors.email', ['E-mail ou senha inválidos.'])` | PASS |
| C23 | logout 204; o próximo me 401 | B1 ✓ | `backend/tests/Feature/Auth/StaffAuthTest.php:60` `postJson('/api/admin/auth/logout')->assertNoContent()`; `:62-63` `forgetGuards()` e depois me `assertUnauthorized()` | PASS |
| C24 | CHECK e NOT NULL de users.role: ausente, customer e superadmin recusados; admin e support aceitos | B1 ✓ (3 linhas) | `backend/tests/Feature/Auth/StaffAuthTest.php:74-75` `->toThrow(QueryException::class)`; `:77-78` inserções com admin e support `toBeTrue()`; dataset `:80-82` | PASS |
| C25 | seed: exatamente 2 na equipe com papéis e senha | B1 ✓ | `backend/tests/Feature/SeederTest.php:42` `toHaveCount(2)`; `:43` `pluck('role','email')` `toBe(['admin@example.com'=>Admin,'suporte@example.com'=>Support])`; `:44` `Hash::check('password', ...)` | PASS |
| C26 | sem equipe: /admin, /admin/products, /admin/customers/3 → admin.login com redirect, também com cliente | V ✓ (3 linhas) | `frontend/src/router/guards.test.ts:22-26` `toEqual({ name: 'admin.login', query: { redirect: path } })` para `visitor` e para `{ customer: true, staffRole: null }` | PASS |
| C27 | com equipe: /admin/login → admin.dashboard | V ✓ | `frontend/src/router/guards.test.ts:30` `toEqual({ name: 'admin.dashboard' })` | PASS |
| C28 | destino do 401 nas 5 linhas | V ✓ (5 linhas) | `frontend/src/services/api.test.ts:28-33` dataset; `:41` `onStaffUnauthorized` `toHaveBeenCalledTimes(area === 'staff' ? 1 : 0)`; `:42` `onUnauthorized` `toHaveBeenCalledTimes(area === 'store' ? 1 : 0)` | PASS |
| C29 | me 401 → loja anônima; navbar sem "Painel admin" | V ✓ "keeps the store anonymous while only a staff session exists"; G exit 0 | `frontend/src/stores/auth.test.ts:17` `expect(auth.isAuthenticated).toBe(false)`; grep sem ocorrência; "Entrar" fica no `v-else` de `auth.isAuthenticated` em `frontend/src/components/AppNavbar.vue:44-51` | PASS |
| C30 | lista de clientes: total 3, 15 por página, do mais novo ao mais antigo, sem equipe, orders_count 2; admin e support | B1 ✓ (2 linhas) | `backend/tests/Feature/Admin/CustomerTest.php:23-24` `meta.total` `3`, `meta.per_page` `15`; `:25` ids `toBe([$newest,$middle,$oldest])`; `:26` sem `equipe@example.com`; `:27` `data.0.orders_count` `2`; dataset `:8-10` | PASS |
| C31 | admin cria cliente: 201, 1 em customers, 0 em users; os dois papéis | B1 ✓ (2 linhas) | `backend/tests/Feature/Admin/CustomerTest.php:54` `assertCreated()->assertJsonMissingPath('data.role')`; `:56-57` customers `1`, users inalterado | PASS |
| C32 | admin cria cliente com e-mail da equipe: 201 | B1 ✓ | `backend/tests/Feature/Admin/CustomerTest.php:69` `assertCreated()` | PASS |
| C33 | e-mail de cliente em uso: 422 em POST e PUT, sem linha nova, cliente editado intacto | B1 ✓ (POST, PUT) | `backend/tests/Feature/Admin/CustomerTest.php:84` `assertJsonValidationErrors('email')`; `:86-88` count inalterado, email e nome originais; dataset `:89` | PASS |
| C34 | detalhe com 12 pedidos: os 10 mais recentes em ordem, orders_count 12; os dois papéis | B1 ✓ (2 linhas) | `backend/tests/Feature/Admin/CustomerTest.php:98` `orders_count` `12`; `:99` ids `toBe($orders->reverse()->take(10)...)` | PASS |
| C35 | PUT do cliente grava o nome; os dois papéis | B1 ✓ (2 linhas) | `backend/tests/Feature/Admin/CustomerTest.php:109-110` `assertOk()->assertJsonPath('data.name','Novo Nome')`; `:113` `fresh()->name)->toBe('Novo Nome')` | PASS |
| C36 | DELETE de cliente: 405, a linha continua | B1 ✓ | `backend/tests/Feature/Admin/CustomerTest.php:120` `assertStatus(405)`; `:122` `exists())->toBeTrue()` | PASS |
| C37 | o dashboard conta só customers | B1 ✓ | `backend/tests/Feature/Admin/CustomerTest.php:130` `assertJsonPath('data.cards.total_customers', 4)` com 4 clientes e 2 na equipe (`:126-128`) | PASS |
| C38 | rotas do frontend dos clientes e menu "Clientes" | V ✓; T exit 0 | `frontend/src/router/guards.test.ts:49-54` nomes `toEqual(['admin.customers','admin.customers.create','admin.customers.show','admin.customers.edit'])`; `:55` Clientes `.to` `toEqual({ name: 'admin.customers' })` | PASS |
| C39 | 404 com NOT_FOUND nas 7 rotas | B1 ✓ (7 linhas) | `backend/tests/Feature/Admin/StaffRoleTest.php:96` `assertNotFound()`; `:98` `code` `toBe('NOT_FOUND')`; dataset `:100-106` | PASS |
| C40 | suporte remove produto: 403 com a mensagem exata, produto e estoque ficam | B1 ✓ | `backend/tests/Feature/Admin/StaffRoleTest.php:17` `assertApiError(...->assertForbidden(), 'FORBIDDEN', NOT_ALLOWED)` (`:12` = "Você não tem permissão para realizar esta ação."); `:19-20` produto e estoque existem | PASS |
| C41 | suporte remove categoria: 403, a categoria fica | B1 ✓ | `backend/tests/Feature/Admin/StaffRoleTest.php:26` `assertForbidden()`; `:28` `exists())->toBeTrue()` | PASS |
| C42 | admin: 204 no produto (com o estoque) e na categoria; 409 nas exclusões bloqueadas | B1 ✓ (4 nomes) | `backend/tests/Feature/Admin/ProductTest.php:77` `assertNoContent()`, `:79-80` `assertModelMissing` e estoque ausente; `:87` `assertConflict()`; `backend/tests/Feature/Admin/CategoryTest.php:35` `assertNoContent()`; `:44` `assertConflict()` (sessão admin por `beforeEach`, ver a nota acima) | PASS |
| C43 | suporte: 403 em todo DELETE de api/admin pela tabela de rotas, pelo menos 3, nada some | B1 ✓ | `backend/tests/Feature/Admin/StaffRoleTest.php:45` `toBeGreaterThanOrEqual(3)`; `:50` `deleteJson($uri)->status())->toBe(403, ...)`; `:53-55` counts de produto, categoria e users intactos; a falha com uma rota proposital foi reproduzida pela fault F3 | PASS |
| C44 | suporte tem o mesmo status de sucesso do admin nas 9 rotas | B1 ✓ (9 linhas) | `backend/tests/Feature/Admin/StaffRoleTest.php:79` admin `assertStatus($status)`; `:80` support `assertStatus($status)`; dataset `:82-90` (201, 200, ...) | PASS |
| C45 | canDelete: true para admin e false para support; as listas condicionam "Excluir" | V ✓ "shows the delete action only to the admin role"; T exit 0 | `frontend/src/stores/staff.test.ts:21-22` `canDelete` `toBe(true)` e `toBe(false)`; `frontend/src/pages/admin/ProductListPage.vue:79` e `frontend/src/pages/admin/CategoryListPage.vue:110` `v-if="staff.canDelete"` | PASS |
| C46 | middleware: admin passa, support recebe 403 sem chamar next | B1 ✓ | `backend/tests/Feature/Middleware/EnsureUserIsAdminTest.php:24-25` `getContent()` `toBe('next')`, `$reached` `1`; `:31-32` `getStatusCode()` `toBe(403)`, `$reached` continua `1` | PASS |
| C47 | lista da equipe: total 3, 15 por página, do mais novo ao mais antigo, role e role_label, sem cliente | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:29-30` `meta.total` `3`, `per_page` `15`; `:31` ordem dos ids; `:32` `isset(role, role_label)`; `:33` sem e-mail de cliente | PASS |
| C48 | cria support: 201, data.role support, linha com Support | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:51-52` `assertCreated()->assertJsonPath('data.role','support')`; `:54` `role)->toBe(UserRole::Support)` | PASS |
| C49 | role inválido: 422 em role, sem linha | B1 ✓ (3 linhas) | `backend/tests/Feature/Admin/StaffTest.php:65` `assertJsonValidationErrors('role')`; `:67` `exists())->toBeFalse()`; dataset `:68` ausente, customer, superadmin | PASS |
| C50 | e-mail da equipe em uso: 422 em POST e PUT, sem linha nova, membro intacto | B1 ✓ (POST, PUT) | `backend/tests/Feature/Admin/StaffTest.php:80` `assertJsonValidationErrors('email')`; `:82-84` count, email e nome inalterados | PASS |
| C51 | equipe com e-mail de cliente: 201 | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:90` `assertCreated()` | PASS |
| C52 | promove support para admin: 200; na próxima requisição o DELETE do produto dá 204 | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:98` PUT `role` admin `assertOk()`; `:101-106` `forgetGuards`, login, `forgetGuards` e depois DELETE `assertNoContent()`; `:108` o produto sumiu | PASS |
| C53 | o próprio papel: 409 BUSINESS_RULE_VIOLATION com a mensagem, papel mantido | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:114` `assertStatus(409)`; `:116-118` code `BUSINESS_RULE_VIOLATION`, message `'Você não pode alterar o próprio papel.'`, role continua `Admin` | PASS |
| C54 | o próprio nome com o mesmo papel: 200 e grava | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:124` `assertOk()`; `:126` `name)->toBe('Outro Nome')` | PASS |
| C55 | remove outro membro: 204, a linha some, a sessão dele dá 401 | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:137` `assertNoContent()`; `:139` `exists())->toBeFalse()`; `:141-142` `forgetGuards()` e depois me com a sessão do membro `assertUnauthorized()` | PASS |
| C56 | remove a própria conta: 409 BUSINESS_RULE_VIOLATION com a mensagem, a linha fica | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:148` `assertStatus(409)`; `:150-152` code, message `'Você não pode remover a própria conta.'`, `exists())->toBeTrue()` | PASS |
| C57 | suporte: 403 nas 5 rotas de users, users idêntica | B1 ✓ (5 linhas) | `backend/tests/Feature/Admin/StaffTest.php:162` `assertForbidden()`; `:164` snapshot `toBe($before)`; dataset `:166-170` | PASS |
| C58 | support: /admin/users → forbidden; o menu sem "Usuários" | V ✓ | `frontend/src/router/guards.test.ts:35` `toEqual({ name: 'forbidden' })`; `:36` `not.toContain('Usuários')` | PASS |
| C59 | admin: o menu tem "Usuários" → admin.users; o guard deixa passar | V ✓ | `frontend/src/router/guards.test.ts:42` `menu?.to` `toEqual({ name: 'admin.users' })`; `:43` `toBeUndefined()` | PASS |
| C60 | caso de uso de atualizar: própria conta com outro papel lança e não grava; própria com o mesmo papel grava; outra com outro papel grava | B1 ✓ (3 linhas) | `backend/tests/Feature/UseCases/User/StaffUseCasesTest.php:25-26` papel e nome gravados; `:31-33` `toThrow(BusinessRuleException::class, 'Você não pode alterar o próprio papel.')`, papel e nome inalterados; dataset `:35-37` | PASS |
| C61 | caso de uso de remover: a própria conta lança e mantém; outra é removida | B1 ✓ | `backend/tests/Feature/UseCases/User/StaffUseCasesTest.php:44-46` `toThrow(BusinessRuleException::class, 'Você não pode remover a própria conta.')`, a linha existe; `:50` a outra `toBeFalse()` | PASS |
| C62 | frontend: testes e tipos passam; sem isCustomer nem requiresCustomer | T exit 0; V 32 passed; G exit 0 | grep sem ocorrência em `frontend/src`; o meta novo fica em `frontend/src/router/guards.ts:13-17` (`requiresShopper`, `guestOnly`, `requiresStaff`, `staffGuestOnly`, `requiresAdminRole`) | PASS |
| C63 | GET de um membro: 200 com 6 chaves exatas e os valores dele | B1 ✓ | `backend/tests/Feature/Admin/StaffTest.php:41` `toEqualCanonicalizing(['created_at','email','id','name','role','role_label'])`; `:42-46` id, name, email, role `support`, role_label `Suporte` | PASS |

Checks proven: 63/63, each with a located assertion.

## Coverage

Recalculada a partir das fontes de autoridade: a tabela de rotas veio de `docker compose exec -T api php artisan route:list --path=api --except-vendor` (42 rotas); as restrições, das migrations; os status, do `Surface` do `plan.md`.

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| rotas `api/admin/*` menos o login (27) | `route:list`: auth/logout, auth/me (2) · categories index/store/show/update/destroy (5) · customers index/store/show/update (4) · dashboard (1) · orders index/show (2) · products index/store/show/update/destroy/status (6) · stocks index/update (2) · users index/store/show/update/destroy (5) = 27 | C20 e C21, que percorrem `Route::getRoutes()` e afirmam `toHaveCount(27)` (`AuthorizationTest.php:73,83`) | - |
| rotas `DELETE` de `api/admin/*` (3) | `route:list`: categories/{category}, products/{product}, users/{user}; nenhum DELETE de customers (`routes/api.php:72` `except('destroy')`) | C43 percorre a tabela de rotas (`>= 3`) · products C40 · categories C41 · users C57 | - |
| rotas da loja atrás do guard customer (8 em `routes/api.php:31-34,44-52`) | `routes/api.php` | as 7 do AC 6 em C6 (dataset `AuthorizationTest.php:22-30`). `POST /api/auth/logout` fica fora do AC 6 e do Surface do plano, por decisão do plano, e não é membro do conjunto do Surface | - |
| status do Surface, 18 rotas | `plan.md` Surface | register 201 C1 · 422 C3 · 429 C17; login 200 C4 · 422 C5 · 429 C17; me 200 C4 · 401 C6; account 200 C12 · 401 C6; admin login 200 C18 · 422 C19,C22 · 429 C17; admin logout 204 C23 · 401 C20,C21; admin me 200 C18 · 401 C23,C55; customers index 200 C30 · 401 C20,C21; customers store 201 C31 · 401 · 422 C33; customers show 200 C34 · 401 · 404 C39; customers update 200 C35 · 401 · 404 C39 · 422 C33; users index 200 C47 · 401 · 403 C57; users store 201 C48 · 401 · 403 C57 · 422 C49,C50; users show 200 C63 · 401 · 403 C57 · 404 C39; users update 200 C52,C54 · 401 · 403 C57 · 404 C39 · 409 C53 · 422 C50; users destroy 204 C55 · 401 · 403 C57 · 404 C39 · 409 C56; products destroy 204 C42,C52 · 401 · 403 C40 · 404 C39 · 409 C42; categories destroy 204 C42 · 401 · 403 C41 · 404 C39 · 409 C42 (os 401 por C20 e C21, que percorrem a tabela de rotas) | - |
| formato de saída do Surface: chaves do recurso do cliente (4) | `plan.md` Surface e door 6 | `id`, `name`, `email` e `created_at` exatos em C1, C4 e C12 | - |
| formato de saída do Surface: chaves do recurso da equipe (6) | `plan.md` Surface e door 6 | exatas em C18 (login e me) e C63 (show); a lista usa o mesmo `StaffMemberResource::collection` (`StaffMemberController.php:28`), com role e role_label em C47 | - |
| formato de saída do Surface: resumo do cliente no admin (cliente + `orders_count` [+ `orders`]) | `plan.md` Surface | composto em `CustomerSummaryResource.php:25` `...CustomerAccountResource::make(...)`, cujas 4 chaves exatas C1 e C12 provam · id C30 · email C30,C34 · name C35 · sem role C31 (`assertJsonMissingPath('data.role')`) · orders_count C30,C34,C35 · orders C34. Nota de precisão: nenhum teste afirma o conjunto exato de chaves nas rotas `/api/admin/customers`, e `created_at` ali só vem pela composição | - |
| restrições de `customers` (migration `2026_10_05_000000`) (3) | `create_customers_table.php:19` unique, `:26` CHECK `customers_email_normalized`, NOT NULL nas colunas | UNIQUE C9 (duplicado) · CHECK C9 (maiúscula, com espaço) · aceita a canônica C9 | - |
| restrições de `orders.customer_id` (2) | `create_orders_table.php:15` `foreignId('customer_id')->constrained()->restrictOnDelete()` | FK existente C8 (999999 recusado) · restrict C8 (com pedido recusado, sem pedido apagado) | - |
| restrições de `users.role` (2) | `create_users_table.php:22` `string('role',20)` sem default nem nullable · `:30` CHECK `users_role_valid` | NOT NULL C24 (ausente) · CHECK C24 (customer, superadmin) · aceitos admin e support C24 | - |
| `UserRole` (2) | `UserRole.php` | admin C18,C24,C46 · support C18,C24,C46,C48 | - |
| `role_label` (2) | `UserRole::label` | Administrador C18 · Suporte C18,C63 | - |
| e-mail entre as duas tabelas (4) | plano AC 2, 10, 30, 47 | C2 · C10 · C32 · C51 | - |
| autoproteção do admin (4) | plano AC 49 a 52 e KD 7 | próprio papel C53,C60 · próprio nome C54,C60 · própria remoção C56,C61 · outro removido C55,C61 | - |
| efeito na próxima requisição (3) | plano AC 21, 48, 51 | logout C23 · promovido C52 · removido C55 | - |
| rotas que o suporte usa (9) | plano AC 41 | as 9 em C44 | - |
| namespaces proibidos de usar o `User` (3) | plano door 5 e AC 13 | Ordering, Payment, Fulfillment em C13 | - |
| seed (4 contas) | plano AC 14 e 23 | admin e suporte C25 · cliente@ e os demais C14 | - |
| decisões do guard do router (6) | `guards.ts:29-43` (5 ramos) e plano AC 15, 24, 25, 54, 55 | requiresShopper C15 · requiresStaff C26 (visitante e cliente) · staffGuestOnly C27 · requiresAdminRole support C58 · admin passa C59. O ramo `guestOnly` (`guards.ts:32`) já existia e não é decisão desta feature | - |
| destinos do 401 (5) | `api.ts` `reportGlobalError` e AC 26 | as 5 linhas de C28 | - |
| menu e ações por papel (4) | AC 42, 54, 55 | C45 ×2 · C58 · C59 | - |
| rotas do frontend dos clientes (4) | AC 36 | C38 | - |
| guards de inicialização `customer` e `staff` (1 montagem) | `backend/config/auth.php` (guards `customer` → provider `customers` → `CustomerAccount`, `staff` → `users` → `User`, default `customer`); o app e o Pest leem o mesmo arquivo, sem config de teste à parte | C1 (`assertAuthenticatedAs(...,'customer')`) · C18 (`'staff'`) | - |

Varredura de conjuntos sem linha: a lista de 8 rotas da loja atrás do guard `customer` não tinha linha em checks.md. Ela foi acrescentada acima, e o único membro fora do AC 6 (`POST /api/auth/logout`) também fica fora do Surface do plano. As chaves do resumo de cliente no admin também não tinham linha e foram acrescentadas, com a nota de precisão. Não sobrou nenhum membro sem prova.

## Test policy rows

| Row | Files it classifies | Required proof | Expectation met |
| --- | --- | --- | --- |
| Decide e é alcançado pelo HTTP | `EnsureUserIsAdmin.php`, `UpdateStaffMemberUseCase.php`, `DeleteStaffMemberUseCase.php` | middleware: borda C40,C41,C43,C57 · camada C46. Atualizar: borda C52,C53,C54 · camada C60 (3 combinações). Remover: borda C55,C56 · camada C61 (2 saídas) | yes |
| Regra de rotas | `routes/api.php` | guard do admin: C20 e C21 percorrem a tabela, total 27. DELETE só para o admin: C43 percorre a tabela, `>= 3`, o que impede passar vazio e alcança rotas futuras. Guard por área sem o fallback do Sanctum: `AuthorizationTest.php:118-121` percorre a tabela e afirma 0 rotas com `auth:sanctum` | yes |
| Garantia do banco | migrations `customers`, `users`, `orders` | um caso por restrição, recusado e aceito: C8, C9, C24 (ver Coverage) | yes |
| Decide no frontend | `router/guards.ts`, `services/api.ts`, `stores/staff.ts`, `utils/adminMenu.ts` | Vitest sem DOM: C15, C26, C27, C58, C59, C28, C45, C38 | yes |
| Instrumentação | controllers, Form Requests, recursos, `config/auth.php` | nenhuma própria, cobertas pelas provas de borda (C1, C18, C31, C49 etc.) | yes |

Swept existing, relido no código: `OrderPolicy` `view` e `pay` existem em `backend/app/Modules/Ordering/Policies/OrderPolicy.php:18-26`, provados por `AuthorizationTest.php:106` e `PaymentTest.php:195`. O `EmailAndPasswordTest` vale para as duas tabelas (`EmailAndPasswordTest.php:52` `->with(['users', 'customers'])`, `:58` `customers_email_normalized`).

## Faults injected

Baseline do `git status --porcelain`: `?? .specs/features/staff-and-customer-accounts/`. Cada mutação foi feita na árvore real, porque o container monta o diretório principal. Cada arquivo foi restaurado com `git checkout -- <file>` logo depois do teste, e o porcelain voltou à baseline depois de cada fault. Nenhum `git stash` foi usado.

| Mutation | Location | Killed |
| --- | --- | --- |
| F1 `abort_unless($request->user('staff')?->isAdmin(), 403)` → `abort_unless($request->user('staff') !== null, 403)` (o suporte passa) | `backend/app/Modules/Identity/Http/Middleware/EnsureUserIsAdmin.php:19` | yes - C46 "lets only the admin role through the admin-only middleware" ⨯, C43 "forbids every admin delete route to the support role" ⨯ |
| F2 a regra do próprio papel desligada (`if (false && ...)`) | `backend/app/Modules/Identity/UseCases/UpdateStaffMemberUseCase.php:29` | yes - C60 linha "own account, different role" ⨯, C53 "refuses changing the own role" ⨯ |
| F3 `apiResource('categories')->only('destroy')` movido para fora do grupo `admin`, mas dentro de `auth:staff` | `backend/routes/api.php:80` → depois de `:67` | yes - C43 ⨯ (`DELETE /api/admin/categories/2` respondeu diferente de 403), C41 "forbids the support role from deleting a category" ⨯ |
| F4 `Auth::guard('staff')->attempt` → `Auth::guard('customer')->attempt` | `backend/app/Modules/Identity/Http/Controllers/Admin/StaffAuthController.php:23` | yes - C18 ⨯ nas duas linhas, C19 "refuses customer credentials on the admin login" ⨯ |
| F5 a checagem `requiresAdminRole` desligada (`if (false && ...)`) | `frontend/src/router/guards.ts:41` | yes - C58 "hides staff management from the support role" ⨯ |

O porcelain final é igual à baseline (`?? .specs/features/staff-and-customer-accounts/`). Rodei `php artisan route:clear` uma vez, antes da F3: o comando só limpa cache e não deixa arquivo rastreado.

## Gate

- `docker compose exec -T api ./vendor/bin/pest`: 540 passed, 0 failed
- `docker compose exec -T api ./vendor/bin/pint --test`: PASS (259 files)
- `docker compose exec -T frontend npx vitest run`: 32 passed, 0 failed
- `docker compose exec -T frontend npm run type-check`: exit 0

## Observações (não bloqueiam)

1. Precisão: nenhuma prova afirma o conjunto exato de chaves das respostas de `/api/admin/customers*`. `created_at` ali só é garantido pela composição com o `CustomerAccountResource` (`CustomerSummaryResource.php:25`). Trocar o spread por campos explícitos sem `created_at` não seria pego.
2. `POST /api/auth/logout` com uma sessão só de equipe responde 401 pelo grupo `auth:customer` (`routes/api.php:31-32`), mas nenhum teste afirma isso. A rota fica fora do AC 6 e do Surface.
3. A F5 cobre só o guard do router. A F3 cobre a superfície do roteamento do backend. O roteamento do 401 no `api.ts` (C28) não recebeu fault, por causa do limite de 5. A asserção dele compara a contagem de chamadas de cada handler por linha (`api.test.ts:41-42`), então mandar os 401 de `admin/` para o handler da loja falharia nas linhas `admin/dashboard` e `admin/users/3`.
