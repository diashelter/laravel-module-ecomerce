# Contas de equipe e contas de cliente - checks

Profile: standard
Plan: `.specs/features/staff-and-customer-accounts/plan.md`

63 checks in 5 slices · 6 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. O frontend roda via `docker compose exec -T frontend npx vitest run -t "<nome do teste>"`, o mesmo Vitest do `make test-frontend`, e a checagem de tipos via `docker compose exec -T frontend npm run type-check`. O CI não roda o frontend, então essas provas precisam rodar localmente antes do PR. Os nomes de teste não usam parênteses, colchetes nem barras, porque o `--filter` do Pest e o `-t` do Vitest são expressões regulares.

"Sessão de equipe" e "sessão de cliente" nos testes de feature são autenticações nos guards `staff` e `customer`. Quando um check afirma o que acontece "na próxima requisição", o teste esquece os guards entre as requisições (`auth()->forgetGuards()`), para que o usuário seja lido de novo da sessão, e não do objeto em memória.

Testes existentes que afirmam o comportamento antigo são substituídos pelos checks que afirmam o novo, conforme o plano aprovado, e não afrouxados:
- `AuthorizationTest` "forbids customers from admin endpoints" (`403`) → C20 (`401`);
- `AuthorizationTest` "forbids admins from placing orders" (`403`) → C6 (`401`);
- `Admin/UserTest` "lists administrators too, without orders" → C30;
- `Admin/UserTest` "does not edit administrators through the customers screen" → sai junto com a regra, porque administradores não estão mais em `customers`;
- `Admin/UserTest` "always creates customers, never admins" → C31.

## Checks

### S1 - CustomerAccount · 49 files · 131 KB · ~34k

**C1** - `POST /api/auth/register` com um e-mail novo responde `201` com `data` contendo exatamente as chaves `id`, `name`, `email`, `created_at`. Cria 1 linha em `customers` e 0 em `users`. O guard `customer` fica autenticado com essa conta, e o guard `staff` continua visitante (AC 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="registers a customer account in the customers table"`

**C2** - Com `ana@example.com` existente só em `users`, `POST /api/auth/register` com `ana@example.com` responde `201` e passa a existir 1 linha em `customers` e 1 em `users` com esse e-mail (AC 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="registers a customer with an e-mail used by a staff member"`

**C3** - Com `ana@example.com` existente em `customers`, `POST /api/auth/register` com `ana@example.com` responde `422` com erro em `errors.email`, e `customers` continua com 1 linha com esse e-mail (AC 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects registering an e-mail already used by a customer"`

**C4** - `POST /api/auth/login` com e-mail e senha de um cliente responde `200` com `data` contendo exatamente `id`, `name`, `email`, `created_at`. `GET /api/auth/me` na mesma sessão responde `200` com as mesmas 4 chaves (AC 4, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs a customer in with the customer data only"`

**C5** - Com `equipe@example.com` só em `users`, `POST /api/auth/login` com o e-mail e a senha desse membro responde `422` com `errors.email` igual a `["E-mail ou senha inválidos."]`. Os guards `customer` e `staff` continuam visitantes (AC 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses staff credentials on the store login"`

**C6** - Com uma sessão só de equipe, cada uma das 7 rotas responde `401`. O teste é um dataset com 7 linhas: `GET /api/auth/me`, `GET /api/orders`, `POST /api/orders`, `GET /api/orders/{order}`, `POST /api/orders/{order}/payment`, `GET /api/account`, `PUT /api/account/profile`. Depois do dataset, `orders` e `payments` não ganharam linhas e o pedido de teste continua no mesmo status (AC 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a staff session on every store route"`

**C7** - Um cliente que faz `POST /api/orders` gera um pedido com `orders.customer_id` igual ao seu `customers.id` (AC 7, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="stores the customer id on the placed order"`

**C8** - Direto no banco:
- o `DELETE` de um `customers` que tem pedido lança `QueryException`;
- um `customers` sem pedido é apagado;
- inserir um `orders` com `customer_id` `999999` lança `QueryException`.

(AC 8, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ties orders to existing customers and keeps customers that have orders"`

**C9** - Direto no banco, 3 escritas em `customers` lançam `QueryException`, num dataset: e-mail `Ana@example.com`, e-mail `" ana@example.com"` e um segundo `ana@example.com`. O mesmo e-mail `ana@example.com` já existente em `users` não impede a primeira linha em `customers` (AC 9, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects invalid customer e-mails in the database"`

**C10** - Com `equipe@example.com` só em `users`, um cliente que envia `PUT /api/account/profile` com esse e-mail recebe `200`, e o seu `customers.email` passa a ser `equipe@example.com` (AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lets a customer take an e-mail used by a staff member"`

**C11** - Um cliente que envia `PUT /api/account/profile` com o e-mail de outro cliente recebe `422` com erro em `errors.email`, e o próprio e-mail não muda (AC 11)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a profile e-mail already used by another customer"`

**C12** - `GET /api/account` de um cliente responde `200` com `data` contendo exatamente `customer`, `orders_count`, `last_order`, `recent_orders`. `data.customer` contém exatamente `id`, `name`, `email`, `created_at`, sem `role` (AC 12, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the account summary under the customer key without a role"`

**C13** - O `ModuleBoundariesTest` tem 3 expectativas separadas, uma por namespace, afirmando que `App\Modules\Ordering`, `App\Modules\Payment` e `App\Modules\Fulfillment` não usam `App\Modules\Identity\Models\User`. Durante o build, cada regra foi vista falhando com um `use` proposital do `User` no namespace dela (AC 13, door 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not use the staff user"`

**C14** - Depois de `db:seed`, `customers` tem exatamente 10 linhas (`UserSeeder::CUSTOMERS`), uma delas `cliente@example.com` com senha `password`, e nenhuma linha de `users` tem e-mail `cliente@example.com` (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds the demo customers into the customers table"`

**C15** - A decisão do guard do router, com o store da loja sem cliente e o store da equipe com um membro, leva `/checkout` para `{ name: 'login', query: { redirect: '/checkout' } }` (AC 15)
Proof: `docker compose exec -T frontend npx vitest run -t "sends a staff-only browser from checkout to the store login"`

**C16** - O `routes/api.php` não contém `auth:sanctum` (door 3)
Proof: `! grep -n "auth:sanctum" backend/routes/api.php`

**C17** - `POST /api/auth/register`, `POST /api/auth/login` e `POST /api/admin/auth/login` respondem `429` na 11ª requisição dentro de um minuto, e as 10 primeiras não respondem `429`. O teste é um dataset com as 3 rotas (Surface; existing `throttle:10,1`, AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="throttles registration and both logins after 10 attempts per minute"`

### S2 - Staff login · 7 files · 12 KB · ~3k

**C18** - `POST /api/admin/auth/login` com e-mail e senha de um membro da equipe responde `200` com `data` contendo exatamente `id`, `name`, `email`, `role`, `role_label`, `created_at`. O guard `staff` fica autenticado, e o guard `customer` continua visitante. `GET /api/admin/auth/me` na mesma sessão responde `200` com as mesmas chaves. O teste é um dataset com 2 linhas: `admin` → `Administrador` e `support` → `Suporte` (AC 16, door 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs a staff member into the admin"`

**C19** - Com `cli@example.com` só em `customers`, `POST /api/admin/auth/login` com o e-mail e a senha desse cliente responde `422` com `errors.email` igual a `["E-mail ou senha inválidos."]`, e nenhum guard fica autenticado (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses customer credentials on the admin login"`

**C20** - Com uma sessão só de cliente, todas as rotas registradas com URI começando por `api/admin/`, exceto `POST api/admin/auth/login`, respondem `401`. O teste percorre a tabela de rotas, preenche os parâmetros com registros existentes e afirma que achou 27 rotas (AC 19, door 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a customer session on every admin route"`

**C21** - Sem nenhuma sessão, as mesmas 27 rotas de C20 respondem `401` (AC 20)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 401 to a guest on every admin route"`

**C22** - `POST /api/admin/auth/login` com um e-mail de `users` e uma senha errada responde `422` com `errors.email` igual a `["E-mail ou senha inválidos."]` (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a wrong password on the admin login"`

**C23** - Um membro da equipe envia `POST /api/admin/auth/logout` e recebe `204`. Na próxima requisição, `GET /api/admin/auth/me` responde `401` (AC 21)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs a staff member out of the admin"`

**C24** - Direto no banco, 3 escritas em `users` lançam `QueryException`, num dataset: `role` ausente, `role` `customer` e `role` `superadmin`. As escritas com `admin` e com `support` são aceitas (AC 22, door 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="accepts only admin and support roles in the database"`

**C25** - Depois de `db:seed`, `users` tem exatamente 2 linhas: `admin@example.com` com `role` `admin` e `suporte@example.com` com `role` `support`, ambos com senha `password` (AC 23)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds the admin and the support staff members"`

**C26** - A decisão do guard do router, sem membro no store da equipe, leva cada rota de um dataset para `{ name: 'admin.login', query: { redirect: <path> } }`. O dataset tem 3 linhas: `/admin`, `/admin/products` e `/admin/customers/3`. Com um cliente no store da loja, o resultado é o mesmo (AC 24)
Proof: `docker compose exec -T frontend npx vitest run -t "sends a browser without a staff session to the admin login"`

**C27** - A decisão do guard do router, com um membro no store da equipe, leva `/admin/login` para `{ name: 'admin.dashboard' }` (AC 25)
Proof: `docker compose exec -T frontend npx vitest run -t "sends a staff member away from the admin login to the dashboard"`

**C28** - `reportGlobalError` com status `401` chama o handler de cada linha de um dataset com 5 linhas (AC 26):
- `admin/dashboard` → handler da equipe;
- `admin/users/3` → handler da equipe;
- `orders` → handler da loja;
- `auth/me` → nenhum handler;
- `admin/auth/me` → nenhum handler.

Proof: `docker compose exec -T frontend npx vitest run -t "routes each 401 to the login of its area"`

**C29** - Com o endpoint `auth/me` respondendo `401`, o store da loja termina com `isAuthenticated` `false`. O `AppNavbar` não contém o texto "Painel admin" (AC 27)
Proof: `docker compose exec -T frontend npx vitest run -t "keeps the store anonymous while only a staff session exists"`
Proof: `! grep -n "Painel admin" frontend/src/components/AppNavbar.vue`

### S3 - Admin customers · 19 files · 29 KB · ~7k

**C30** - Com 3 clientes e 2 membros da equipe, e o cliente mais novo com 2 pedidos, `GET /api/admin/customers` responde `200` com `meta.total` `3` e `meta.per_page` `15`. Os 3 ids estão em ordem do mais novo para o mais antigo, nenhum e-mail da equipe aparece, e o primeiro tem `orders_count` `2`. O teste é um dataset com as sessões `admin` e `support` (AC 28)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists only customers with their order count"`

**C31** - `POST /api/admin/customers` com um e-mail novo responde `201` e cria 1 linha em `customers` e 0 em `users`. O teste é um dataset com as sessões `admin` e `support` (AC 29)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a customer from the admin"`

**C32** - Com `equipe@example.com` só em `users`, `POST /api/admin/customers` com esse e-mail responde `201` (AC 30)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a customer with an e-mail used by a staff member"`

**C33** - O e-mail de outro cliente é recusado com `422` e erro em `errors.email`, sem nenhuma linha nova e sem mudar o cliente editado. O teste é um dataset com 2 linhas: `POST /api/admin/customers` e `PUT /api/admin/customers/{customer}` (AC 31, Surface `422`)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a customer e-mail already in use"`

**C34** - Para um cliente com 12 pedidos, `GET /api/admin/customers/{customer}` responde `200` com `data.orders` contendo 10 pedidos, os 10 mais recentes, do mais novo para o mais antigo, e `data.orders_count` `12`. O teste é um dataset com as sessões `admin` e `support` (AC 32)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows a customer with the ten most recent orders"`

**C35** - `PUT /api/admin/customers/{customer}` com `name` `Novo Nome` responde `200`, e `customers.name` passa a ser `Novo Nome`. O teste é um dataset com as sessões `admin` e `support` (AC 33)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="updates a customer from the admin"`

**C36** - `DELETE /api/admin/customers/{customer}` com sessão `admin` responde `405`, e a linha continua em `customers` (AC 34)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not delete customers"`

**C37** - Com 4 clientes e 2 membros da equipe, `GET /api/admin/dashboard` responde `data.cards.total_customers` `4` (AC 35)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="counts only customers on the dashboard"`

**C38** - O router resolve `/admin/customers`, `/admin/customers/new`, `/admin/customers/3` e `/admin/customers/3/edit` para `admin.customers`, `admin.customers.create`, `admin.customers.show` e `admin.customers.edit`. O item "Clientes" da lista do menu do admin aponta para `admin.customers` (AC 36)
Proof: `docker compose exec -T frontend npx vitest run -t "registers the admin customer pages under admin customers"`
Proof: `docker compose exec -T frontend npm run type-check`

**C39** - Com sessão `admin`, um id inexistente (`999999`) responde `404` com `code` `NOT_FOUND`. O teste é um dataset com 7 linhas (Surface `404`):
- `GET /api/admin/customers/{id}`;
- `PUT /api/admin/customers/{id}`;
- `GET /api/admin/users/{id}`;
- `PUT /api/admin/users/{id}`;
- `DELETE /api/admin/users/{id}`;
- `DELETE /api/admin/products/{id}`;
- `DELETE /api/admin/categories/{id}`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="answers 404 for unknown admin records"`

### S4 - Admin-only removal · 6 files · 16 KB · ~4k

**C40** - Com sessão `support`, `DELETE /api/admin/products/{product}` de um produto nunca vendido responde `403` com `message` `"Você não tem permissão para realizar esta ação."`, e o produto e o seu estoque continuam (AC 37, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids the support role from deleting a product"`

**C41** - Com sessão `support`, `DELETE /api/admin/categories/{category}` de uma categoria sem produtos responde `403`, e a categoria continua (AC 38, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids the support role from deleting a category"`

**C42** - Com sessão `admin`, `DELETE /api/admin/products/{product}` de um produto nunca vendido responde `204`, e o produto e o estoque somem. `DELETE /api/admin/categories/{category}` de uma categoria sem produtos responde `204`. As exclusões bloqueadas continuam respondendo `409` ao admin (AC 39, Surface `204` e `409`)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes a product without orders together with its stock"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="deletes a category without products"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="blocks deleting a product present in orders"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="blocks deleting a category with products"`

**C43** - Com sessão `support`, todas as rotas registradas com método `DELETE` e URI começando por `api/admin/` respondem `403`, e nenhum registro some. O teste percorre a tabela de rotas e afirma que achou ao menos 3 (`products`, `categories`, `users`). Durante o build, ele foi visto falhando com uma rota `DELETE` proposital fora do grupo restrito ao admin (AC 40, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids every admin delete route to the support role"`

**C44** - Com sessão `support` e entrada válida, cada rota de um dataset com 9 linhas responde o mesmo status de sucesso que responde ao `admin` (AC 41):
- `POST /api/admin/products` → `201`;
- `PUT /api/admin/products/{product}` → `200`;
- `PATCH /api/admin/products/{product}/status` → `200`;
- `POST /api/admin/categories` → `201`;
- `PUT /api/admin/categories/{category}` → `200`;
- `PUT /api/admin/stocks/{stock}` → `200`;
- `GET /api/admin/orders` → `200`;
- `GET /api/admin/orders/{order}` → `200`;
- `GET /api/admin/dashboard` → `200`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lets the support role create, edit and read in the admin"`

**C45** - A regra de permissão do store da equipe responde que pode excluir para o papel `admin` e não pode para o papel `support`. As listas de produtos e de categorias compilam com o botão "Excluir" condicionado a essa regra (AC 42)
Proof: `docker compose exec -T frontend npx vitest run -t "shows the delete action only to the admin role"`
Proof: `docker compose exec -T frontend npm run type-check`

**C46** - Na camada do middleware `admin` (`EnsureUserIsAdmin`), um membro `admin` segue para a próxima etapa, e um membro `support` recebe uma `HttpException` `403`, sem chamar a próxima etapa (door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lets only the admin role through the admin-only middleware"`

### S5 - Staff management · 5 files · 9 KB · ~2k

**C47** - Com 3 membros da equipe e 2 clientes, `GET /api/admin/users` com sessão `admin` responde `200` com `meta.total` `3` e `meta.per_page` `15`. Os ids estão do mais novo para o mais antigo, cada item tem `role` e `role_label`, e nenhum e-mail de cliente aparece (AC 43)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lists only staff members to the admin"`

**C48** - `POST /api/admin/users` com sessão `admin`, um e-mail novo e `role` `support` responde `201` com `data.role` `support`, e cria 1 linha em `users` com `role` `support` (AC 44)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a support member from the admin"`

**C49** - `POST /api/admin/users` com `role` inválido responde `422` com erro em `errors.role`, sem linha nova. O teste é um dataset com 3 linhas: ausente, `customer` e `superadmin` (AC 45)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a staff role other than admin or support"`

**C50** - O e-mail de outro membro da equipe é recusado com `422` e erro em `errors.email`, sem linha nova e sem mudar o membro editado. O teste é um dataset com 2 linhas: `POST /api/admin/users` e `PUT /api/admin/users/{user}` (AC 46)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a staff e-mail already in use"`

**C51** - Com `cli@example.com` só em `customers`, `POST /api/admin/users` com esse e-mail responde `201` (AC 47)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a staff member with an e-mail used by a customer"`

**C52** - O admin envia `PUT /api/admin/users/{user}` mudando um membro de `support` para `admin` e recebe `200`. Na próxima requisição desse membro, `DELETE /api/admin/products/{product}` de um produto nunca vendido responde `204` (AC 48)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="promotes a support member who can then delete"`

**C53** - O admin envia `PUT /api/admin/users/{user}` na própria conta com `role` `support` e recebe `409` com `code` `BUSINESS_RULE_VIOLATION` e `message` `"Você não pode alterar o próprio papel."`. O seu `role` continua `admin` (AC 49)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses changing the own role"`

**C54** - O admin envia `PUT /api/admin/users/{user}` na própria conta com `role` `admin` e `name` `Outro Nome` e recebe `200`, e `users.name` passa a ser `Outro Nome` (AC 50)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="lets the admin edit the own name keeping the role"`

**C55** - O admin envia `DELETE /api/admin/users/{user}` para outro membro e recebe `204`, e a linha some de `users`. O membro removido, que tinha entrado por `POST /api/admin/auth/login`, recebe `401` na próxima requisição a `GET /api/admin/auth/me` (AC 51)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="removes another staff member and ends their access"`

**C56** - O admin envia `DELETE /api/admin/users/{user}` na própria conta e recebe `409` com `code` `BUSINESS_RULE_VIOLATION` e `message` `"Você não pode remover a própria conta."`. A linha continua (AC 52)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses removing the own staff account"`

**C57** - Com sessão `support`, as 5 rotas de `/api/admin/users` respondem `403`, e `users` termina com as mesmas linhas e os mesmos valores. O teste é um dataset com 5 linhas: `GET` lista, `POST`, `GET` um membro, `PUT` e `DELETE` (AC 53, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="forbids the support role from managing staff"`

**C58** - Para o papel `support`, a decisão do guard do router leva `/admin/users` para `{ name: 'forbidden' }`, e a lista do menu do admin não tem o item "Usuários" (AC 54)
Proof: `docker compose exec -T frontend npx vitest run -t "hides staff management from the support role"`

**C59** - Para o papel `admin`, a lista do menu do admin tem o item "Usuários" apontando para `admin.users`, e a decisão do guard do router deixa `/admin/users` passar (AC 55)
Proof: `docker compose exec -T frontend npx vitest run -t "shows staff management to the admin role"`

**C60** - Na camada do caso de uso que atualiza um membro da equipe, num dataset com 3 linhas:
- a própria conta com papel diferente lança `BusinessRuleException` com `"Você não pode alterar o próprio papel."` e não grava;
- a própria conta com o mesmo papel grava;
- outra conta com papel diferente grava.

(AC 48, 49, 50)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="decides when a staff update may change the role"`

**C61** - Na camada do caso de uso que remove um membro da equipe, a própria conta lança `BusinessRuleException` com `"Você não pode remover a própria conta."` e mantém a linha, e outra conta é removida (AC 51, 52)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="decides when a staff member may be removed"`

**C62** - Os testes do frontend e a checagem de tipos passam com o store da equipe, as páginas novas e as rotas renomeadas, e não sobra referência a `isCustomer` nem a `requiresCustomer` no frontend (AC 15, 24 a 27, 36, 42, 54, 55)
Proof: `docker compose exec -T frontend npm run type-check`
Proof: `! grep -rnE "isCustomer|requiresCustomer" frontend/src`

**C63** - Com sessão `admin`, `GET /api/admin/users/{user}` de outro membro responde `200` com `data` contendo exatamente `id`, `name`, `email`, `role`, `role_label`, `created_at`, com os valores desse membro (Surface `200`)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="shows a staff member to the admin"`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| `POST /api/auth/register` statuses (3) | 201 C1, C2 · 422 C3 · 429 C17 | - |
| `POST /api/auth/login` statuses (3) | 200 C4 · 422 C5 · 429 C17 | - |
| `GET /api/auth/me` statuses (2) | 200 C4 · 401 C6 | - |
| `GET /api/account` statuses (2) | 200 C12 · 401 C6 | - |
| `POST /api/admin/auth/login` statuses (3) | 200 C18 · 422 C19, C22 · 429 C17 | - |
| `POST /api/admin/auth/logout` statuses (2) | 204 C23 · 401 C20, C21 | - |
| `GET /api/admin/auth/me` statuses (2) | 200 C18 · 401 C23, C55 | - |
| `GET /api/admin/customers` statuses (2) | 200 C30 · 401 C20, C21 | - |
| `POST /api/admin/customers` statuses (3) | 201 C31, C32 · 401 C20, C21 · 422 C33 | - |
| `GET /api/admin/customers/{customer}` statuses (3) | 200 C34 · 401 C20, C21 · 404 C39 | - |
| `PUT /api/admin/customers/{customer}` statuses (4) | 200 C35 · 401 C20, C21 · 404 C39 · 422 C33 | - |
| `GET /api/admin/users` statuses (3) | 200 C47 · 401 C20, C21 · 403 C57 | - |
| `POST /api/admin/users` statuses (4) | 201 C48, C51 · 401 C20, C21 · 403 C57 · 422 C49, C50 | - |
| `GET /api/admin/users/{user}` statuses (4) | 200 C63 · 401 C20, C21 · 403 C57 · 404 C39 | - |
| `PUT /api/admin/users/{user}` statuses (6) | 200 C52, C54 · 401 C20, C21 · 403 C57 · 404 C39 · 409 C53 · 422 C50 | - |
| `DELETE /api/admin/users/{user}` statuses (5) | 204 C55 · 401 C20, C21 · 403 C57, C43 · 404 C39 · 409 C56 | - |
| `DELETE /api/admin/products/{product}` statuses (5) | 204 C42, C52 · 401 C20, C21 · 403 C40, C43 · 404 C39 · 409 C42 | - |
| `DELETE /api/admin/categories/{category}` statuses (5) | 204 C42 · 401 C20, C21 · 403 C41, C43 · 404 C39 · 409 C42 | - |
| rotas da loja que recusam a sessão de equipe (7) | `GET /api/auth/me` C6 · `GET /api/orders` C6 · `POST /api/orders` C6 · `GET /api/orders/{order}` C6 · `POST /api/orders/{order}/payment` C6 · `GET /api/account` C6 · `PUT /api/account/profile` C6 | - |
| rotas `api/admin/*` que recusam cliente e visitante (27) | C20 e C21, table-driven sobre a tabela de rotas, afirmando 27 | - |
| rotas `DELETE` de `api/admin/*` restritas ao admin (3) | C43, table-driven sobre a tabela de rotas, afirmando ao menos 3, inclusive as futuras · `products` C40 · `categories` C41 · `users` C57 | - |
| rotas que o suporte usa (9) | `POST products` C44 · `PUT products` C44 · `PATCH products status` C44 · `POST categories` C44 · `PUT categories` C44 · `PUT stocks` C44 · `GET orders` C44 · `GET orders/{order}` C44 · `GET dashboard` C44 | - |
| rotas de `/api/admin/users` proibidas ao suporte (5) | `GET` lista C57 · `POST` C57 · `GET` um C57 · `PUT` C57 · `DELETE` C57 | - |
| `UserRole` (2) | `admin` C18, C24, C46 · `support` C18, C24, C46, C48 | - |
| papéis inválidos (3) | ausente C24, C49 · `customer` C24, C49 · `superadmin` C24, C49 | - |
| e-mail entre as duas tabelas (4) | cadastro da loja com e-mail da equipe C2 · perfil com e-mail da equipe C10 · cliente pelo admin com e-mail da equipe C32 · equipe com e-mail de cliente C51 | - |
| garantias de `customers` e `orders`, doors 1 e 2 (5) | e-mail canônico C9 · e-mail único em `customers` C9 · `customer_id` existente C8 · cliente com pedido não é apagado C8 · cliente sem pedido é apagado C8 | - |
| garantias de `users`, door 1 (3) | `role` obrigatório C24 · `role` só `admin` ou `support` C24 · valores válidos aceitos C24 | - |
| doors do `Landing` (6) | door 1 C9, C24 · door 2 C7, C8 · door 3 C6, C16, C20 · door 4 C43, C46, C57 · door 5 C13 · door 6 C4, C12, C18 | - |
| namespaces proibidos de usar o `User` (3) | `Ordering` C13 · `Payment` C13 · `Fulfillment` C13 | - |
| chaves do recurso do cliente (4) | `id` C1, C12 · `name` C1, C12 · `email` C1, C12 · `created_at` C1, C12 | - |
| chaves do recurso da equipe (6) | `id` C18 · `name` C18 · `email` C18 · `role` C18 · `role_label` C18 · `created_at` C18 | - |
| `role_label` (2) | `Administrador` C18 · `Suporte` C18 | - |
| regras de autoproteção do admin (4) | próprio papel recusado C53, C60 · próprio nome aceito C54, C60 · própria remoção recusada C56, C61 · outro membro removido C55, C61 | - |
| efeito na próxima requisição (3) | logout C23 · membro removido C55 · papel promovido C52 | - |
| seed (4 contas) | `admin@example.com` C25 · `suporte@example.com` C25 · `cliente@example.com` C14 · demais clientes C14 | - |
| decisões do guard do router (6) | `/checkout` só com equipe C15 · `/admin/*` sem equipe C26 · `/admin/*` com cliente C26 · `/admin/login` com equipe C27 · `/admin/users` com suporte C58 · `/admin/users` com admin C59 | - |
| destinos do `401` no frontend (5) | `admin/` → equipe C28 · `admin/users/3` → equipe C28 · loja → `/login` C28 · `auth/*` → nenhum C28 · `admin/auth/*` → nenhum C28 | - |
| itens de menu e ações por papel (4) | "Usuários" ao admin C59 · sem "Usuários" ao suporte C58 · "Excluir" ao admin C45 · sem "Excluir" ao suporte C45 | - |
| rotas do frontend dos clientes (4) | lista C38 · novo C38 · detalhe C38 · edição C38 | - |
| startup config: guards `customer` e `staff` (1 montagem compartilhada) | a aplicação e os testes de feature leem o mesmo `config/auth.php`: C1, C18 | - |

- As afirmações sobre status, rota ou formato de resposta (C1 a C12, C17 a C23, C30 a C44, C47 a C57, C63) têm prova que atravessa o HTTP.
- C8, C9 e C24 provam as garantias no banco, sem passar pela aplicação.
- C46, C60 e C61 provam as decisões na camada da própria classe, além das provas de borda.
- C15, C26 a C29, C38, C45, C58 e C59 provam a lógica do frontend sem DOM, como registra a assumption do plano. O que fica sem prova automática é o arranjo visual das telas, conferido no navegador durante a verificação.

## Test policy

O README diz onde ficam e como rodar os testes, mas não diz qual nível prova cada tipo de código. As linhas abaixo valem para esta feature.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Decide e é alcançado pelo HTTP (middleware `admin`, casos de uso de atualizar e remover membro da equipe) | uma na borda **e** uma na própria camada | na borda: o contrato de cada status; na camada: um caso afirmado por saída |
| Regra de rotas (guard por área, `DELETE` só para o admin) | uma que percorre a tabela de rotas | toda rota do grupo, com o total afirmado para que o teste não passe vazio |
| Garantia do banco (`CHECK`, `UNIQUE`, FK) | uma direto no banco | um caso por restrição, recusado e aceito |
| Decide no frontend (guard do router, destino do `401`, menu e ações por papel) | uma na própria camada, com Vitest e sem DOM | um caso por decisão |
| Instrumentação (controllers, Form Requests, recursos JSON, service provider) | nenhuma própria | coberta pelas provas de borda |

Evidence:

- `EnsureUserIsAdmin`: 1 ponto de decisão (papel `admin` ou não), 2 saídas → decide
- caso de uso de atualizar membro da equipe: 2 condições (própria conta, papel diferente), 3 combinações relevantes → decide
- caso de uso de remover membro da equipe: 1 condição (própria conta), 2 saídas → decide
- guard do router: 6 decisões nesta feature (loja sem cliente, admin sem equipe, login do admin com equipe, `/admin/users` por papel) → decide
- `reportGlobalError`: 3 destinos para o `401` → decide
- `AuthController` e o controller de login da equipe: tentam o guard e repassam ao recurso, sem condição própria além da falha de credencial → instrumentação, provada na borda
- análogo no repositório: `tests/Feature/UseCases/Payment/PayOrderUseCaseTest.php` prova um caso de uso na própria camada; `tests/Feature/AuthorizationTest.php` prova a regra de rotas por dataset; `tests/Feature/Auth/EmailAndPasswordTest.php` prova o `CHECK` direto no banco; `services/api.test.ts` prova o `reportGlobalError` com Vitest

Cost: 3 provas na própria camada no backend (C46, C60, C61) e 7 no frontend (C15, C26 a C29, C58, C59). Sem estas linhas, as regras de autoproteção e o middleware seriam provados só pelos caminhos que o HTTP atravessa, e o guard do router ficaria sem prova.

## Swept

- validation: C3, C11, C33, C49, C50; e-mail e senha seguem o `EmailAndPasswordTest` existente, que passa a valer também para `customers`
- failure modes: n/a - nenhuma operação desta feature escreve em mais de uma tabela; criar, editar e remover contas é uma escrita única
- idempotency: n/a - nenhuma rota nova tem efeito que se repita; um segundo cadastro com o mesmo e-mail é a unicidade de C3 e C9, e um segundo `DELETE` do mesmo membro é o `404` de C39
- authorization: C6, C20, C21, C40, C41, C43, C44, C46, C57; existing - `OrderPolicy` (`view`, `pay`) para pedidos de outro cliente, no `AuthorizationTest`
- concurrency: n/a - o único caso concorrente (dois admins se removendo ou se rebaixando ao mesmo tempo) foi aceito sem trava na assumption do plano
- data lifecycle: C8, porque cliente com pedido não é apagado; C55, porque o membro removido perde o acesso na próxima requisição; o banco é recriado, sem backfill (plano, Impact)
- dependency failure: n/a - a feature não chama nenhum serviço externo
- state transitions: C52, C53, C60, porque a troca de papel só vale para outra conta e muda as permissões na próxima requisição; C23, porque o logout encerra a sessão
- observability: n/a - o plano não pede log; os erros seguem o `request_id` existente do `ApiErrorResponse`

## Handoff

- S1 = ~34k, S2 entra em ~37k, S3 em ~44k, S4 em ~48k, S5 em ~50k; a atualização do README e da análise de domínio (83 KB) leva a ~71k, mais os arquivos novos (~10k), abaixo do budget de 150k - one builder
- Mechanism: one builder (cabe no orçamento, sem pergunta)
