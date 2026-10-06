# Value objects de e-mail e senha verification

**Verdict**: PASS
**Profile**: light
**Diff range**: 8641cd1..working tree (uncommitted)
**Round**: 2 - scoped
**Verifier**: independent sub-agent (author != verifier)

Round 2 - scoped: C1 e C23 foram re-julgados contra os testes reforçados (verified at working tree, round 2); os demais checks têm a mesma asserção da rodada 1 (carried from round 1, citações reconferidas: arquivos de teste dos demais checks não mudaram). Todas as provas rodaram de novo em uma única invocação do Pest (47 testes, todos individualmente PASS, incluindo os dois testes de C23) e a suíte completa rodou de novo como Gate. Nenhuma falha foi injetada (profile light).

## Checks

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | constraint `users_email_normalized` com a definição `CHECK (email = lower(btrim(email)))` | `pest --filter="keeps the users email normalized constraint"` exit 0, ran (verified round 2) | `tests/Feature/Auth/EmailAndPasswordTest.php:50` - `expect($definition->definition)->toBe('CHECK (((email)::text = lower(btrim((email)::text))))')`, igualdade exata com a forma canônica do Postgres. Migration: `database/migrations/0001_01_01_000000_create_users_table.php:28` | PASS |
| C2 | insert direto de `Ana@x.com` falha e nada é gravado | `--filter="rejects a non normalized email written directly"` ran | `EmailAndPasswordTest.php:57` - `->toThrow(QueryException::class, 'users_email_normalized')`; `:59` - `expect(DB::table('users')->whereRaw('lower(email) = ?', ['ana@x.com'])->count())->toBe(0)` | PASS |
| C3 | register `Ana@X.com` -> 201, `ana@x.com`, uma linha | `--filter="registers with the email normalized"` ran | `EmailAndPasswordTest.php:64-65` - `->assertCreated()->assertJsonPath('data.email', 'ana@x.com')`; `:67` - `expect(User::query()->where('email', 'ana@x.com')->count())->toBe(1)` | PASS |
| C4 | register `ANA@x.com` com `ana@x.com` existente -> 422 e contagem igual | `--filter="rejects registering an email that differs only in case"` ran | `EmailAndPasswordTest.php:76` - `->assertJsonPath('errors.email', EMAIL_IN_USE)` (const na linha 11 com a mensagem exata); `:78` - `expect(User::query()->count())->toBe($before)` | PASS |
| C5 | login `ANA@X.COM` -> 200, `data.email` `ana@x.com`, autenticado | `--filter="logs in with the email in any case"` ran | `EmailAndPasswordTest.php:85-86` - `->assertOk()->assertJsonPath('data.email', 'ana@x.com')`; `:88` - `$this->assertAuthenticatedAs($user, 'web')` | PASS |
| C6 | credenciais inválidas -> 422 genérico, sessão convidado | `--filter="rejects invalid credentials with the generic message"` ran | `EmailAndPasswordTest.php:94` - `->assertJsonPath('errors.email', ['E-mail ou senha inválidos.'])`; `:96` - `$this->assertGuest('web')` | PASS |
| C7 | perfil `ANA@x.com` -> 200, `ana@x.com` | `--filter="keeps the own email when only its case changes"` ran | `EmailAndPasswordTest.php:103-104` - `->assertOk()->assertJsonPath('data.email', 'ana@x.com')`; `:106` - `expect($user->fresh()->email)->toBe('ana@x.com')` | PASS |
| C8 | perfil `BRUNO@x.com` -> 422, e-mail de quem enviou intacto | `--filter="does not allow taking another user email in another case"` ran | `EmailAndPasswordTest.php:115` - `->assertJsonPath('errors.email', EMAIL_IN_USE)`; `:117` - `expect($user->fresh()->email)->toBe('ana@x.com')` | PASS |
| C9 | admin cria `Carla@X.com` -> 201, `carla@x.com` gravado | `--filter="creates a customer with the email normalized"` ran | `EmailAndPasswordTest.php:122-123` - `->assertCreated()->assertJsonPath('data.email', 'carla@x.com')`; `:125` - `expect(User::query()->where('email', 'carla@x.com')->exists())->toBeTrue()` | PASS |
| C10 | admin edita com e-mail de outra conta em outra caixa -> 422, alvo intacto | `--filter="does not let the admin reuse another email in another case"` ran | `EmailAndPasswordTest.php:134` - `->assertJsonPath('errors.email', EMAIL_IN_USE)`; `:136` - `expect($target->fresh()->email)->toBe('ana@x.com')` | PASS |
| C11 | `email: "ana"` -> 422 nas 5 rotas (tabela) | `--filter="rejects a malformed email on every account route"` ran, 5 datasets (register, login, admin create, admin update, profile) | `EmailAndPasswordTest.php:142` - `->assertJsonPath('errors.email', ['O campo e-mail deve ser um e-mail válido.'])`; rotas em `:24-41` | PASS |
| C12 | 256 -> 422 com mensagem de tamanho; 255 -> 201 | `--filter="bounds the email at 255 characters"` ran | `EmailAndPasswordTest.php:148` - `expect(strlen($address(1)))->toBe(255)->and(strlen($address(2)))->toBe(256)`; `:152` - `->assertJsonPath('errors.email', ['O campo e-mail não pode ter mais de 255 caracteres.'])`; `:154` - `->assertCreated()` | PASS |
| C13 | `email` array -> 422, nunca 500 | `--filter="rejects an email that is not a string"` ran | `EmailAndPasswordTest.php:159-160` - `->assertUnprocessable()->assertJsonValidationErrors('email')` | PASS |
| C14 | `Email` normaliza as 3 entradas (dataset) | `--filter="normalizes the email"` ran, 3 datasets | `tests/Unit/ValueObjects/EmailTest.php:6` - `expect((new Email($input))->value())->toBe($expected)`; dataset `:8-10` (`'ÉLISA@x.com'`->`'élisa@x.com'`, `' Ana@X.com '`->`'ana@x.com'`, `'ana@x'`->`'ana@x'`) | PASS |
| C15 | `Email` recusa `ana`, `ana@@x.com`, 256 chars (dataset) | `--filter="refuses an invalid email"` ran, 3 datasets | `EmailTest.php:19` - `->throws(InvalidArgumentException::class)`; dataset `:16-18` | PASS |
| C16 | senha de 7 caracteres -> 422 nas 4 rotas (tabela) | `--filter="rejects a password shorter than 8 characters on every route that chooses one"` ran, 4 datasets | `EmailAndPasswordTest.php:170` - `->assertJsonPath('errors.password.0', 'O campo senha deve ter pelo menos 8 caracteres.')`; entrada `:166` `abc1234` + `current_password` `:168` | PASS |
| C17 | register com `abcd1234` -> 201 e hash confere | `--filter="stores the hash of a chosen password"` ran | `EmailAndPasswordTest.php:174` - `->assertCreated()`; `:176` - `expect(Hash::check('abcd1234', User::query()->where('email', 'ana@x.com')->sole()->password))->toBeTrue()` | PASS |
| C18 | login com senha de 6 caracteres gravada direto -> 200 | `--filter="logs in with a password shorter than the current policy"` ran | `EmailAndPasswordTest.php:180` - `customer([... 'password' => Hash::make('abc123')])`; `:182` - `->postJson('/api/auth/login', [... 'password' => 'abc123'])->assertOk()` | PASS |
| C19 | perfil e admin sem `password` -> 200, hash igual | `--filter="keeps the password when none is sent on profile and admin updates"` ran | `EmailAndPasswordTest.php:189-190` - `->assertOk(); expect($user->fresh()->password)->toBe($hash)`; `:192-193` idem para `/api/admin/users/{id}` | PASS |
| C20 | perfil com senha nova + `current_password` correta -> 200, hash confere | `--filter="changes the password only with the current password"` ran (teste preexistente, não tocado pelo diff) | `tests/Feature/Account/AccountTest.php:36` - `->putJson('/api/account/profile', [...$payload, 'current_password' => 'password'])->assertOk()`; `:38` - `expect(Hash::check('newpass123', $user->fresh()->password))->toBeTrue()` | PASS |
| C21 | `Password` de 7 caracteres lança, mensagem sem o texto | `--filter="refuses a short password without echoing it"` ran | `tests/Unit/ValueObjects/PasswordTest.php:7-9` - `new Password('abc1234')` ... `catch (InvalidArgumentException $exception) { expect($exception->getMessage())->not->toContain('abc1234')`; `:14` `$this->fail(...)` se não lançar | PASS |
| C22 | senha não aparece em json_encode, serialize, var_export, print_r, var_dump; `(string)` lança `Error` | `--filter="never reveals the plain password"` ran, 6 datasets | `PasswordTest.php:44` - `expect($output)->not->toContain('segredo-123')`; `:39` - `expect(fn () => (string) $password)->toThrow(Error::class)`; canais `:25-34` | PASS |
| C23 | trace da exceção em chamada que recebeu a senha não contém o texto | `--filter="keeps the plain password out of exception traces"` ran, 2 testes individualmente PASS (verified round 2) | `tests/Unit/ValueObjects/PasswordTest.php:57-58` - `expect(json_encode($exception->getTrace()))->not->toContain('segredo-123')->and($exception->getTraceAsString())->not->toContain('segredo-123')`; e `:71-73` (teste "...when the constructor refuses it", sem marcador sensível no teste) - `expect($exception->getTrace()[0]['class'])->toBe(Password::class)->and(json_encode($exception->getTrace()))->not->toContain('segr-12')->and($exception->getTraceAsString())->not->toContain('segr-12')`. O frame do construtor está no trace (`getTrace()[0]['class']`), então só o `#[SensitiveParameter]` de `Password.php:26` pode esconder o argumento | PASS |
| C24 | 5 Form Requests sem `email`/`max:255`/`Password` do Laravel, com `EmailRule`/`PasswordRule` (Login sem `PasswordRule`) | `--filter="keeps the email and password rules out of the account form requests"` ran, 5 datasets | `tests/Feature/Requests/AccountRequestRulesTest.php:19-21` - `expect($emailRules)->not->toContain('email')->and(...not->toContain('max:255'))->and(collect($emailRules)->contains(fn ($rule) => $rule instanceof EmailRule))->toBeTrue()`; `:25-26` - `instanceof LaravelPasswordRule ... toBeFalse()->and(... instanceof PasswordRule ...)->toBe($choosesPassword)` | PASS |
| C25 | classes de `ValueObjects` são `final` e `readonly` | `--filter="identity value objects are final and readonly"` ran | `tests/Unit/Architecture/ModuleBoundariesTest.php:79-82` - `arch(...)->expect('App\Modules\Identity\ValueObjects')->toBeFinal()->toBeReadonly()` | PASS |
| C26 | seed completo roda sem violar `users_email_normalized` sobre a migration editada | `--filter="seeds a complete demo environment"` ran (teste preexistente, não tocado pelo diff) | `tests/Feature/SeederTest.php:13` - `$this->seed()` (a migration com a constraint, `create_users_table.php:28`, roda no `RefreshDatabase`); `:15` - `User::query()->where('email', 'admin@example.com')->sole()`; `:18` - `User::query()->customers()->count()` `>= 10` | PASS |

## Swept existing

Relidas contra o código:

- authorization: as provas citadas ("requires authentication for admin endpoints", "forbids customers from admin endpoints") existem na suíte completa, que passou.
- concurrency: o `unique` de `users.email` continua na migration, e o valor gravado é canônico pela constraint em `create_users_table.php:28`.
- data lifecycle: a migration editada contém `users_email_normalized` (`create_users_table.php:28`) e o seed passa por ela (C26).

## Gaps

Nenhum. Os gaps da rodada 1 (C1, C23) foram fechados.

## Gate

`docker compose exec -T api ./vendor/bin/pest` - 369 passed, 0 failed
