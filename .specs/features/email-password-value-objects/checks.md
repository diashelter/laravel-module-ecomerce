# Value objects de e-mail e senha - checks

Profile: light
Plan: `.specs/features/email-password-value-objects/plan.md`

## Intent

26 checks in 3 slices · 3 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. Nenhuma prova depende do frontend. Os nomes de teste não usam parênteses nem colchetes, porque o `--filter` do Pest é uma expressão regular.

## Checks

### S1 - E-mail como conta única · 20 files · 30 KB · ~8k

**C1** - O catálogo do Postgres mostra a constraint `users_email_normalized` em `users`, com a definição `CHECK (email = lower(btrim(email)))` (AC 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the users email normalized constraint"`

**C2** - Um insert direto em `users` com `email` `Ana@x.com` falha com a constraint `users_email_normalized`, e nenhuma linha com esse e-mail existe depois (AC 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a non normalized email written directly"`

**C3** - `POST /api/auth/register` com `email: "Ana@X.com"` responde `201` com `data.email` `"ana@x.com"`, e `users` tem exatamente uma linha com `email` `ana@x.com` (AC 3)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="registers with the email normalized"`

**C4** - Com a conta `ana@x.com` já criada, `POST /api/auth/register` com `email: "ANA@x.com"` responde `422` com `errors.email` igual a `["O valor informado para e-mail já está em uso."]`, e a contagem de `users` não muda (AC 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects registering an email that differs only in case"`

**C5** - `POST /api/auth/login` com `email: "ANA@X.COM"` e a senha correta da conta `ana@x.com` responde `200` com `data.email` `"ana@x.com"`, e a sessão fica autenticada (AC 5)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs in with the email in any case"`

**C6** - `POST /api/auth/login` com e-mail e senha que não batem com nenhuma conta responde `422` com `errors.email` igual a `["E-mail ou senha inválidos."]`, e a sessão continua de convidado (AC 6)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects invalid credentials with the generic message"`

**C7** - A conta autenticada `ana@x.com` envia `PUT /api/account/profile` com `email: "ANA@x.com"`, recebe `200` com `data.email` `"ana@x.com"`, e a linha continua com `ana@x.com` (AC 7)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the own email when only its case changes"`

**C8** - Com `bruno@x.com` pertencendo a outra conta, `PUT /api/account/profile` com `email: "BRUNO@x.com"` responde `422` com `errors.email` igual a `["O valor informado para e-mail já está em uso."]`, e o e-mail de quem enviou não muda (AC 8)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not allow taking another user email in another case"`

**C9** - O admin envia `POST /api/admin/users` com `email: "Carla@X.com"` e recebe `201` com `data.email` `"carla@x.com"`; a linha é gravada com `carla@x.com` (AC 9)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="creates a customer with the email normalized"`

**C10** - O admin envia `PUT /api/admin/users/{user}` com o e-mail de outra conta em outra caixa e recebe `422` com `errors.email` igual a `["O valor informado para e-mail já está em uso."]`; o cliente editado não muda (AC 10)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not let the admin reuse another email in another case"`

**C11** - Com `email: "ana"`, cada uma das 5 rotas de conta (`POST /api/auth/register`, `POST /api/auth/login`, `POST /api/admin/users`, `PUT /api/admin/users/{user}` e `PUT /api/account/profile`) responde `422` com `errors.email` igual a `["O campo e-mail deve ser um e-mail válido."]`. O teste percorre as 5 rotas como tabela (AC 11)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a malformed email on every account route"`

**C12** - `POST /api/auth/register` com um e-mail de 256 caracteres responde `422` com `errors.email` contendo `"O campo e-mail não pode ter mais de 255 caracteres."`, e um e-mail válido de exatamente 255 caracteres responde `201` (AC 12)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="bounds the email at 255 characters"`

**C13** - `POST /api/auth/register` com `email: ["ana@x.com"]` responde `422` com erro em `email`, nunca `500` (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects an email that is not a string"`

**C14** - `Email` construído com `"ÉLISA@x.com"`, `" Ana@X.com "` e `"ana@x"` guarda `"élisa@x.com"`, `"ana@x.com"` e `"ana@x"`, nessa ordem. O teste é um dataset com as 3 entradas (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes the email"`

**C15** - `Email` construído com `"ana"`, `"ana@@x.com"` ou com um endereço de 256 caracteres lança `InvalidArgumentException`. O teste é um dataset com as 3 entradas (AC 15)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses an invalid email"`

**C26** - O seed completo (`UserSeeder` com `admin@example.com` e `cliente@example.com`, e os clientes da `UserFactory`) roda sem violar `users_email_normalized` sobre a migration editada (AC 1)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="seeds a complete demo environment"`

### S2 - Senha com a política em um lugar só · 10 files · 18 KB · ~5k

**C16** - Com `password` e `password_confirmation` de 7 caracteres, cada uma das 4 rotas que escolhem senha (`POST /api/auth/register`, `POST /api/admin/users`, `PUT /api/admin/users/{user}` e `PUT /api/account/profile`, esta com `current_password` correta) responde `422` com `errors.password` contendo `"O campo senha deve ter pelo menos 8 caracteres."`. O teste percorre as 4 rotas como tabela (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a password shorter than 8 characters on every route that chooses one"`

**C17** - `POST /api/auth/register` com a senha de 8 caracteres `abcd1234` e a confirmação responde `201`, e `Hash::check('abcd1234', <hash gravado>)` é `true` (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="stores the hash of a chosen password"`

**C18** - Para uma conta cujo hash da senha de 6 caracteres `abc123` foi gravado direto pela factory, `POST /api/auth/login` com `abc123` responde `200` (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="logs in with a password shorter than the current policy"`

**C19** - `PUT /api/account/profile` e `PUT /api/admin/users/{user}` sem `password` respondem `200`, e o hash gravado é idêntico ao anterior, nas duas rotas (AC 19)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the password when none is sent on profile and admin updates"`

**C20** - `PUT /api/account/profile` com a senha nova `newpass123`, a confirmação e a `current_password` correta responde `200`, e o hash gravado confere com `newpass123` (AC 20)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="changes the password only with the current password"`

**C21** - `Password` construído com `"abc1234"` (7 caracteres) lança `InvalidArgumentException`, e a mensagem da exceção não contém `abc1234` (AC 21)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="refuses a short password without echoing it"`

**C22** - Para um `Password` construído com `"segredo-123"`, as saídas de `json_encode`, `serialize`, `var_export`, `print_r` e `var_dump` não contêm `segredo-123`, e `(string)` lança `Error`. O teste é um dataset com os 6 canais (AC 22)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="never reveals the plain password"`

**C23** - Uma exceção lançada dentro de uma função que recebeu `"segredo-123"` como argumento que constrói o `Password` tem `getTrace()` e `getTraceAsString()` sem `segredo-123` (AC 23)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the plain password out of exception traces"`

### S3 - Regra escrita uma vez · 7 files · 8 KB · ~2k

**C24** - Nas `rules()` dos 5 Form Requests de conta (`RegisterRequest`, `LoginRequest`, `StoreCustomerRequest`, `UpdateCustomerRequest` e `UpdateProfileRequest`):
- `email` não contém as strings `email` nem `max:255` e contém uma instância de `EmailRule`;
- nenhum deles usa `Illuminate\Validation\Rules\Password`;
- os 4 que escolhem senha têm uma instância de `PasswordRule` em `password`;
- `LoginRequest` não tem `PasswordRule`.

O teste percorre os 5 requests como tabela (AC 24).
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the email and password rules out of the account form requests"`

**C25** - As classes de `App\Modules\Identity\ValueObjects` são `final` e `readonly` (AC 25)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="identity value objects are final and readonly"`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| rotas de conta com e-mail malformado (5) | `POST /api/auth/register` C11 · `POST /api/auth/login` C11 · `POST /api/admin/users` C11 · `PUT /api/admin/users/{user}` C11 · `PUT /api/account/profile` C11 | - |
| rotas que escolhem senha (4) | `POST /api/auth/register` C16 · `POST /api/admin/users` C16 · `PUT /api/admin/users/{user}` C16 · `PUT /api/account/profile` C16 | - |
| entradas normalizadas pelo `Email` (3) | `"ÉLISA@x.com"` C14 · `" Ana@X.com "` C14 · `"ana@x"` C14 | - |
| entradas recusadas pelo `Email` (3) | `"ana"` C15 · `"ana@@x.com"` C15 · 256 caracteres C15 | - |
| limite do e-mail (2 bordas) | 255 C12 · 256 C12 | - |
| caixa diferente de uma conta existente (4 caminhos de escrita) | cadastro C4 · admin criando C10 · admin editando C10 · próprio perfil C8 | - |
| busca do login pelo e-mail normalizado (1) | C5 | - |
| canais em que a senha pode vazar (7) | `json_encode` C22 · `serialize` C22 · `var_export` C22 · `print_r` C22 · `var_dump` C22 · `(string)` C22 · trace da exceção C23 | - |
| Form Requests de conta (5) | `RegisterRequest` C24 · `LoginRequest` C24 · `StoreCustomerRequest` C24 · `UpdateCustomerRequest` C24 · `UpdateProfileRequest` C24 | - |
| doors do `Landing` (3) | door 1 C1, C2 · door 2 C14, C15, C25 · door 3 C24 | - |
| `POST /api/auth/register` statuses (2) | 201 C3 · 422 C4 | - |
| `POST /api/auth/login` statuses (2) | 200 C5 · 422 C6 | - |
| `PUT /api/account/profile` statuses (2) | 200 C7 · 422 C8 | - |
| `POST /api/admin/users` statuses (2) | 201 C9 · 422 C11 | - |
| `PUT /api/admin/users/{user}` statuses (2) | 200 C19 · 422 C10 | - |
| startup config: banco com a constraint (2 assemblies) | `make test` e o Pest com `--filter`, via `RefreshDatabase` na migration editada C1 · `make fresh` com seed C26 | - |

- As afirmações sobre status, rota ou formato de resposta (C3 a C13, C16 a C20) têm prova que atravessa o HTTP.
- C14, C15 e C21 a C23 provam as regras na camada do value object, além das provas de borda.
- C20 e C26 reaproveitam testes que já existem e já fazem a asserção pedida. Os demais nomes são testes novos.

## Swept

- validation: C11, C12, C13, C15, C16, C21
- failure modes: C2 (o banco recusa e não grava), C13 (entrada fora do tipo responde `422`, não `500`)
- idempotency: n/a - nenhum caminho de escrita novo. Repetir o cadastro com o mesmo e-mail em outra caixa é recusado (C4), e o login não grava nada
- authorization: existing - as rotas públicas de auth, o `auth:sanctum`, o middleware `admin` e o `UserPolicy` não mudam, provados por "requires authentication for admin endpoints" e "forbids customers from admin endpoints"
- concurrency: existing - dois cadastros simultâneos do mesmo e-mail continuam barrados pelo `unique` de `users.email`. Como o valor gravado já é canônico (C1), as caixas diferentes caem no mesmo índice. A resposta da corrida fica fora do escopo (plan, Out of scope)
- data lifecycle: C1, C26 - a migration é editada no lugar e o `make fresh` recria o banco. O seed passa pela constraint
- dependency failure: n/a - o fluxo de conta não chama nenhum serviço externo
- state transitions: n/a - conta não tem status. O `role` não muda nesta feature
- observability: C22, C23 - o texto da senha não aparece em dump, serialização nem trace. Nenhum log novo é adicionado

## Handoff

- S1 = 8k, no Identity, no Customers e na migration de `users`; S2 entra na senha com 13k acumulados; S3 fecha com 15k. Fica abaixo do orçamento padrão de 150k, então um builder só.
- Mechanism: one builder (cabe no orçamento, sem pergunta)
