# Value objects de e-mail e senha

## Problem

As regras de e-mail e senha não têm dono. Elas aparecem copiadas nos Form Requests das contas e viajam como `string` dos requests até o banco:

- a regra de e-mail (`string`, `email`, `max:255`) está escrita em 5 Form Requests: `RegisterRequest`, `LoginRequest`, `StoreCustomerRequest`, `UpdateCustomerRequest` e `UpdateProfileRequest`;
- a política de senha (`Password::min(8)`) está escrita em 4 deles. Mudar a política hoje significa caçar as cópias;
- o e-mail não é normalizado em lugar nenhum, e `users.email` compara diferenciando maiúsculas de minúsculas. No banco local, `ADMIN@example.com` encontra 0 linhas e `admin@example.com` encontra 1. Por isso `Ana@x.com` e `ana@x.com` podem virar duas contas, e o login com o e-mail digitado em outra caixa responde "E-mail ou senha inválidos.".

A fonte é a discovery [.design/value-objects-and-typed-lists.md](../../../.design/value-objects-and-typed-lists.md), que registrou a atividade como já decidida e definiu que e-mails que só diferem na caixa são a mesma conta.

Quando isso estiver entregue, o e-mail é uma conta só, qualquer que seja a caixa digitada, e cada regra está escrita uma única vez, no value object.

## Flow

Reaproveita os Form Requests, os DTOs, os use cases e o `UserRepository` que já existem, o formato de erro do `ApiFormRequest`, as traduções de `lang/pt_BR/validation.php` e o cast `hashed` do `User`. Nenhum fluxo novo é criado.

1. in: `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/admin/users`, `PUT /api/admin/users/{user}` e `PUT /api/account/profile` -> Form Request do Identity ou do Customers (exists). O `email` é normalizado pelo `Email` (door 2) antes da validação, como o `CategoryRequest` faz com o slug, e o formato e o tamanho são validados pelas regras que delegam ao value object (door 3).
2. Form Request (exists) -> `CreateUserDTO`, `LoginCredentialsDTO` e `UpdateUserProfileDTO` (exist), agora levando `Email` e `Password` (door 2). O login leva `Email` e a senha digitada como texto, sem política.
3. `RegisterCustomerUseCase`, `CreateCustomerUseCase`, `UpdateCustomerUseCase` e `UpdateOwnProfileUseCase` (exist), com `UserService` (exists) -> `UserRepository` (exists). Os value objects viram `string` só aqui, nos atributos do Eloquent, e são gravados em `users` (door 1). O `AuthController` (exists) busca pelo e-mail normalizado no `Auth::attempt`.
4. out: `UserResource` e `CustomerSummaryResource` (exist), com `data.email` já normalizado.

## Impact

| Front | What changes |
| --- | --- |
| domain | novo termo `Email`: e-mail válido e normalizado (sem espaços nas pontas, em minúsculas, até 255 caracteres). Fica no Identity |
| domain | novo termo `Password`: senha escolhida que cumpre a política (mínimo de 8 caracteres) e nunca revela o texto. Fica no Identity |
| domain | `CreateUserDTO` e `UpdateUserProfileDTO` passam de `string` para `Email`/`Password` (`?Password` na edição). Quem lê hoje: `RegisterCustomerUseCase`, `CreateCustomerUseCase`, `UpdateCustomerUseCase`, `UpdateOwnProfileUseCase`, `UserService`, `RequestToDtoTest`, `UserUseCasesTest` e `UserServiceTest` |
| domain | `LoginCredentialsDTO` passa a levar `Email`, e a senha continua texto. Quem lê hoje: `AuthController` |
| stored data | `users.email` ganha o `CHECK` da door 1. A migration original é editada no lugar e quem tem banco local roda `make fresh`. O `UserSeeder` (`admin@example.com`, `cliente@example.com`) e a `UserFactory` (`safeEmail`, que já sai em minúsculas) não violam o `CHECK` |
| API | `data.email` nas respostas passa a vir normalizado. Nenhuma chave, código ou mensagem muda |
| docs | README: a camada `ValueObjects` entra em "Arquitetura" e em "Estrutura de pastas", e a normalização do e-mail entra em "Escopo e decisões". A análise de domínio ganha a nota do Identity |

## Relations

None - nenhuma entidade ou cardinalidade muda. O `CHECK` em `users.email` é a door 1.

## Surface

None - nenhuma rota muda de assinatura: entradas, chaves de saída e códigos continuam os mesmos. A única mudança visível é o valor de `data.email` normalizado, que está nos critérios 3, 7 e 9.

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| 1. e-mail canônico no banco | `ALTER TABLE users ADD CONSTRAINT users_email_normalized CHECK (email = lower(btrim(email)))`, na migration original de `users`, no padrão dos `CHECK` nomeados de `products` e `stocks`. O `unique` de `email` continua como está | índice único em `lower(email)`: aceita gravar `Ana@x.com`, que depois a busca exata do login não encontra. Só normalizar no PHP: factory, seeder e tinker escapam |
| 2. camada `ValueObjects` (precedente que os próximos módulos copiam) | `App\Modules\Identity\ValueObjects\Email` e `App\Modules\Identity\ValueObjects\Password`, `final readonly class`. A construção com um valor inválido lança `InvalidArgumentException`; não há `__toString`, e o texto sai só por um acessor explícito usado na gravação e na busca | `Shared\ValueObjects`: o Shared é só infraestrutura e e-mail de conta é conceito do Identity. Pasta `DTOs`: DTO é transporte e não guarda invariante |
| 3. origem única das regras | as regras de validação `App\Modules\Identity\Http\Rules\EmailRule` e `PasswordRule` (`ValidationRule` do Laravel) delegam ao value object e reprovam com as chaves de tradução atuais (`validation.email`, `validation.max.string`, `validation.min.string`). Os cinco Form Requests usam essas regras no lugar de `'email'`, `'max:255'` e `Password::min(8)`. `required`, `string`, `unique`, `confirmed` e `current_password` continuam nos requests | um método no value object que devolve o array de regras do Laravel: coloca o framework dentro do value object, e o formato seria checado duas vezes, por implementações que podem divergir |

- Nothing else in this change is hard to reverse

## Criteria

### S1: E-mail como conta única (P1)

A caixa do e-mail digitado deixa de criar contas diferentes ou de impedir o login.

**Acceptance Criteria**

1. The system SHALL keep the constraint `users_email_normalized` (`CHECK (email = lower(btrim(email)))`) on `users`
2. IF a write sets `users.email` to `Ana@x.com` without going through `Email` THEN the database SHALL reject it and no row SHALL be written
3. WHEN a visitor sends `POST /api/auth/register` with `email: "Ana@X.com"` and a valid name and password THEN the system SHALL return `201` with `data.email` equal to `"ana@x.com"` and persist `ana@x.com`
4. IF `POST /api/auth/register` is sent with `email: "ANA@x.com"` while the account `ana@x.com` exists THEN the system SHALL return `422` with `errors.email` equal to `["O valor informado para e-mail já está em uso."]` and SHALL NOT create an account
5. WHEN `POST /api/auth/login` is sent with `email: "ANA@X.COM"` and the correct password of the account `ana@x.com` THEN the system SHALL return `200` with `data.email` equal to `"ana@x.com"`
6. IF `POST /api/auth/login` is sent with an e-mail and password that match no account THEN the system SHALL return `422` with `errors.email` equal to `["E-mail ou senha inválidos."]`
7. WHEN the authenticated account `ana@x.com` sends `PUT /api/account/profile` with `email: "ANA@x.com"` THEN the system SHALL return `200` with `data.email` equal to `"ana@x.com"`
8. IF `PUT /api/account/profile` is sent with `email: "BRUNO@x.com"` while `bruno@x.com` belongs to another account THEN the system SHALL return `422` with `errors.email` equal to `["O valor informado para e-mail já está em uso."]`
9. WHEN an admin sends `POST /api/admin/users` with `email: "Carla@X.com"` and valid data THEN the system SHALL return `201` with `data.email` equal to `"carla@x.com"`
10. IF an admin sends `PUT /api/admin/users/{user}` with an e-mail that differs only in case from another account's THEN the system SHALL return `422` with `errors.email` equal to `["O valor informado para e-mail já está em uso."]`
11. IF `email` is `"ana"` on `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/admin/users`, `PUT /api/admin/users/{user}` or `PUT /api/account/profile` THEN the system SHALL return `422` with `errors.email` equal to `["O campo e-mail deve ser um e-mail válido."]`
12. IF `email` has 256 characters on `POST /api/auth/register` THEN the system SHALL return `422` with `errors.email` containing `"O campo e-mail não pode ter mais de 255 caracteres."`
13. IF `email` is an array (`["ana@x.com"]`) on `POST /api/auth/register` THEN the system SHALL return `422` on `email` and never `500`
14. WHEN `Email` is built from `"ÉLISA@x.com"`, `" Ana@X.com "` and `"ana@x"` THEN it SHALL hold `"élisa@x.com"`, `"ana@x.com"` and `"ana@x"` respectively
15. IF `Email` is built from `"ana"`, `"ana@@x.com"` or a 256-character address THEN it SHALL throw `InvalidArgumentException`

**Independent test:** cadastrar `Ana@X.com`, tentar de novo com `ANA@x.com` (`422`) e entrar com `ANA@X.COM` (`200`).

### S2: Senha com a política em um lugar só (P1)

A política vale quando alguém escolhe uma senha e nunca no login. O texto da senha não vaza.

**Acceptance Criteria**

16. IF `password` and `password_confirmation` have 7 characters on `POST /api/auth/register`, `POST /api/admin/users`, `PUT /api/admin/users/{user}` or `PUT /api/account/profile` THEN the system SHALL return `422` with `errors.password` containing `"O campo senha deve ter pelo menos 8 caracteres."`
17. WHEN `POST /api/auth/register` is sent with an 8-character password and its confirmation THEN the system SHALL return `201` and the stored hash SHALL verify against that password
18. WHEN `POST /api/auth/login` is sent with the correct 6-character password of an account whose hash was stored directly THEN the system SHALL return `200`
19. WHEN `PUT /api/account/profile` or `PUT /api/admin/users/{user}` is sent without `password` THEN the system SHALL return `200` and the stored hash SHALL be unchanged
20. WHEN `PUT /api/account/profile` is sent with a new valid password, its confirmation and the correct `current_password` THEN the system SHALL return `200` and the stored hash SHALL verify against the new password
21. IF `Password` is built from a 7-character string THEN it SHALL throw `InvalidArgumentException` whose message does not contain that string
22. The `Password` built from `"segredo-123"` SHALL NOT expose `segredo-123` through `json_encode`, `serialize`, `var_export`, `print_r` or `var_dump`, and casting it to `string` SHALL throw `Error`
23. IF an exception is thrown in a call that received `"segredo-123"` as the argument that builds a `Password` THEN the exception's trace arguments SHALL NOT contain `segredo-123`

**Independent test:** cadastrar com senha de 7 caracteres (`422`), cadastrar com 8 (`201`) e entrar com uma senha antiga de 6 gravada direto (`200`).

### S3: Regra escrita uma vez (P2)

Nenhum Form Request de conta reescreve as regras que pertencem aos value objects.

**Acceptance Criteria**

24. The Form Requests `RegisterRequest`, `LoginRequest`, `StoreCustomerRequest`, `UpdateCustomerRequest` and `UpdateProfileRequest` SHALL contain neither the `email` nor the `max:255` rule for `email`, nor `Password::min`, and SHALL use `EmailRule` for `email` and `PasswordRule` for `password` (`LoginRequest` uses only `EmailRule`)
25. The classes in `App\Modules\Identity\ValueObjects` SHALL be `final` and `readonly`

**Independent test:** o teste de arquitetura falha ao reintroduzir `Password::min(8)` em `RegisterRequest`.

## Out of scope

| Excluded | Why |
| --- | --- |
| `Email` como cast do Eloquent em `User::email` | nada no código lê o e-mail para fazer algo com ele (discovery, Shape) |
| política de senha mais forte | decidido manter o mínimo de 8 |
| redefinição de senha e verificação de e-mail | não existem no produto hoje |
| normalizar o e-mail no frontend | o backend é a fonte da regra, e o contrato não muda |
| corrida entre dois cadastros simultâneos do mesmo e-mail | o `unique` de `users.email` já impede a segunda linha. Responder `422` em vez de erro de banco é outro trabalho |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| e-mail de login com mais de 255 caracteres | `422` com a mensagem de tamanho em `email`, em vez de "E-mail ou senha inválidos." | o login passa a usar a mesma `EmailRule`. Nenhuma conta pode ter esse e-mail, então o resultado continua sendo uma recusa | n |
| letras maiúsculas fora do ASCII (`É`) | minúsculas Unicode no PHP | o banco usa `en_US.utf8`, e `lower('élisa')` devolve o mesmo texto (conferido), então o `CHECK` aceita o que o `Email` grava | n |
| espaços nas pontas do e-mail | o `Email` também remove, embora o middleware `TrimStrings` já faça isso no HTTP | o value object não pode depender do middleware: factory, seeder e testes o constroem direto | n |
| ordem dos pull requests | este plano vai em um pull request próprio, separado das listas tipadas | a discovery definiu os dois grupos como independentes | y |

**Open questions:** none - all resolved or logged above.

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| API `POST /api/auth/register` · `POST /api/admin/users` | error shape and codes | AC 4, 9, 11, 12, 13, 16 |
| API `POST /api/auth/login` | error shape and codes | AC 5, 6, 11, 18 |
| API `PUT /api/account/profile` · `PUT /api/admin/users/{user}` | error shape and codes | AC 7, 8, 10, 16, 19, 20 |
| all five routes | response shape | existing - `UserResource` and `CustomerSummaryResource` keep their keys; only the `email` value changes (AC 3, 7, 9) |
| all five routes | who may call them | existing - public auth routes, `auth:sanctum`, the `admin` middleware and `UserPolicy`, unchanged |
| all five routes | rate limits | existing - `throttle:10,1` on register and login, unchanged |
| all five routes | versioning | n/a - the contract does not change and the only consumer is the frontend in this repository |

## Sources

- [.design/value-objects-and-typed-lists.md](../../../.design/value-objects-and-typed-lists.md) - slices Email and Password, Key decisions 1 a 4 e 7
