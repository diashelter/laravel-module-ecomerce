# Library Spike checks

Profile: light
Plan: `.specs/features/library-spike/plan.md`

18 checks in 7 slices · 1 one-way door (a dependência, só na branch descartável) · 1 open, que bloqueia e já foi resolvido (Docker iniciado)

Notação das provas. `SPIKE` é o worktree `.claude/worktrees/spike-hel-12-library` na branch `spike/hel-12-library`, criada de `main` em f1dd960. Cada prova é um script em `.specs/features/library-spike/proofs/`, escrito a partir do check e nunca da implementação; ele sai com 0 só quando a afirmação vale e grava a saída em `.specs/features/library-spike/evidence/c<NN>.txt`. Os checks de resposta (C4, C15) saem com 0 quando existe uma resposta definida, `ANSWER: yes <detalhe>` ou `ANSWER: no <detalhe>`, com a evidência ao lado; a resposta em si pode ser a que trava a biblioteca.

## Checks

### S1 - ambiente do spike · 3 files · 12 KB · ~3k

**C1** - `composer install` no worktree resolve `internachi/modular` 3.x e `php artisan modules:list` lista `payment` (AC 1)
Proof: `bash .specs/features/library-spike/proofs/c01-install.sh`

**C2** - O `migrate:status` do banco de desenvolvimento é idêntico antes e depois do spike, e todo comando `artisan` do spike rodou com `DB_DATABASE=ecommerce_testing_spike_hel_12_library` (AC 2)
Proof: `bash .specs/features/library-spike/proofs/c02-dev-db-untouched.sh`

### S2 - cache de módulos · 2 files · 6 KB · ~2k

**C3** - `php artisan modules:cache` grava o arquivo e o caminho absoluto fica registrado (AC 3)
Proof: `bash .specs/features/library-spike/proofs/c03-cache-path.sh`

**C4** - Redirecionar o cache de módulos só por configuração tem resposta `yes <nome exato>` com o `ls` em `/tmp/laravel`, ou `no` com o `ls` de `bootstrap/cache` (AC 4)
Proof: `bash .specs/features/library-spike/proofs/c04-cache-redirect.sh`

### S3 - rotas do módulo · 4 files · 18 KB · ~5k

**C5** - Com `prefix('api')` e o grupo `api` no arquivo do módulo, `POST /api/orders/{order}/payment` responde 401 sem autenticação e 404 para um id desconhecido (AC 5)
Proof: `make -C .claude/worktrees/spike-hel-12-library test-filter FILTER="module payment route"` - os dois testes `answers 401 to a guest on the module payment route` e `answers 404 to an unknown order on the module payment route`, em `tests/Feature/Spike/ModulePaymentRouteTest.php`

**C6** - Sem prefixo nem grupo, `route:list` mostra a URI `orders/{order}/payment` e `POST /api/orders/{order}/payment` responde 404 (AC 6)
Proof: `bash .specs/features/library-spike/proofs/c06-routes-unwrapped.sh`

**C7** - Com o grupo `api` declarado, `route:list -v` mostra `EnsureFrontendRequestsAreStateful` para `POST api/orders/{order}/payment` (AC 7)
Proof: `bash .specs/features/library-spike/proofs/c07-stateful-middleware.sh`

### S4 - migrations de vários caminhos · 3 files · 9 KB · ~3k

**C8** - `migrate:fresh` com a migration de `payments` no módulo sai com 0, e `migrate:status` lista os 14 nomes de arquivo de `main`, todos `Ran`, na ordem de `main` (AC 8)
Proof: `bash .specs/features/library-spike/proofs/c08-migrate-fresh.sh`

**C9** - Com o banco migrado e a migration movida para o módulo, `php artisan migrate` responde `Nothing to migrate.` (AC 9)
Proof: `bash .specs/features/library-spike/proofs/c09-migrate-after-move.sh`

**C10** - Depois do `migrate:fresh`, `payments.order_id` tem chave estrangeira em `information_schema` (AC 10)
Proof: `bash .specs/features/library-spike/proofs/c10-foreign-key.sh`

### S5 - factory · 2 files · 5 KB · ~2k

**C11** - Com a `PaymentFactory` em `database/factories` do módulo e `#[UseFactory]` no model, `Payment::factory()->create()` grava uma linha em `payments` (AC 11)
Proof: `make -C .claude/worktrees/spike-hel-12-library test-filter FILTER="creates a payment through the module factory"` - `tests/Feature/Spike/ModulePaymentFactoryTest.php`

### S6 - infraestrutura de checkout · 3 files · 14 KB · ~4k

**C12** - `make test` no worktree passa em todo teste, menos os testes de arquitetura nomeados em `evidence/c12-expected-failures.txt` com a causa de cada um; o total é 1928 (linha de base de `main`) + 3 (testes do spike) − 75 (linhas de dataset que `DomainEventsTest` perde, 1, e `ModuleBoundariesTest` perde, 74, por enumerarem `app/Modules`), e todo outro arquivo de teste tem a contagem da linha de base (AC 12; cláusula da contagem trocada em 2026-10-19 por decisão do usuário, a original exigia a contagem total igual à da linha de base)
Proof: `bash .specs/features/library-spike/proofs/c12-suite.sh`

**C13** - `make pint` no worktree sai com 0 (AC 13)
Proof: `make -C .claude/worktrees/spike-hel-12-library pint`

**C14** - `composer install` num clone novo da branch do spike, sem `vendor/`, cria `backend/vendor/modules/payment` como symlink para `app-modules/payment` (AC 14)
Proof: `bash .specs/features/library-spike/proofs/c14-clean-clone.sh`

**C15** - Com `vendor/autoload.php` existindo e o symlink do módulo ausente, a resposta sobre o entrypoint do container `api` é `ANSWER: yes` (instala) ou `ANSWER: no` (não instala), com a saída do container (AC 15)
Proof: `bash .specs/features/library-spike/proofs/c15-entrypoint.sh`

### S7 - a resposta entra no design · 2 files · 8 KB · ~2k

**C16** - A seção `Library Spike` de `.design/modular-layout.md` traz exatamente cinco respostas, cada uma com `Comando:`, `Resultado:` e `Efeito:` (a Key decision ou slice confirmada ou trocada) (AC 16)
Proof: `bash .specs/features/library-spike/proofs/c16-design-answers.sh`

**C17** - Se alguma resposta é `bloqueia`, a Module Layout, a Module Routes e a Module Migrations estão marcadas no design como trocadas para a alternativa leve; se nenhuma bloqueia, a seção diz `nenhuma resposta bloqueia` (AC 17)
Proof: `bash .specs/features/library-spike/proofs/c17-fallback-marked.sh`

**C18** - Depois do cleanup, `git worktree list` não tem o spike, a branch `spike/hel-12-library` não existe e `git rev-parse main` é f1dd960 (AC 18)
Proof: `bash .specs/features/library-spike/proofs/c18-cleanup.sh`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| as cinco perguntas do design (5) | cache C3 C4 · rotas C5 C6 C7 · migrations C8 C9 C10 · factory C11 · infraestrutura C12 C13 C14 C15 | - |
| critérios do plano (18) | AC 1 C1 · AC 2 C2 · AC 3 C3 · AC 4 C4 · AC 5 C5 · AC 6 C6 · AC 7 C7 · AC 8 C8 · AC 9 C9 · AC 10 C10 · AC 11 C11 · AC 12 C12 · AC 13 C13 · AC 14 C14 · AC 15 C15 · AC 16 C16 · AC 17 C17 · AC 18 C18 | - |
| migrations de `main` (14) | os 14 nomes são lidos de `ls backend/database/migrations` em `main` e comparados em C8, table-driven sobre todos | - |
| variantes de rota do módulo (2) | com prefixo e grupo C5 C7 · sem prefixo nem grupo C6 | - |
| porta da `Landing` (1) | dependência `internachi/modular` C1 | - |
| startup configuration: descoberta do módulo (2 assemblies) | `artisan` na linha de comando C1 · aplicação montada pelo Pest C5 | - |

- Claims naming a status code, route or response shape: C5, C6 - C5 cruza o limite HTTP com `postJson`; C6 usa o kernel HTTP (`Request::create`)
- No other check claims more than the single case its proof exercises

## Swept

- validation: n/a - o spike não valida entrada de ninguém; as rotas do `Payment` mantêm a validação que já têm
- failure modes: C15 (o entrypoint com `vendor/` existente e sem o symlink), C17 (resposta que bloqueia)
- idempotency: C9 (rodar `migrate` de novo sobre um banco migrado não faz nada)
- authorization: C5 (401 sem login na rota do módulo)
- concurrency: n/a - o spike não toca em escrita concorrente; o teste de corrida do `PaymentTest` roda dentro de C12
- data lifecycle: C9 e C10 (banco já migrado e chave estrangeira depois do `migrate:fresh`)
- dependency failure: n/a - a biblioteca roda no mesmo processo; sem chamada externa
- state transitions: n/a - sem máquina de estados
- observability: n/a - o spike não muda log, métrica nem trace; a evidência vai para `evidence/`

## Handoff

- S1 = 3k, S2 = 2k, S3 = 5k, S4 = 3k, S5 = 2k, S6 = 4k, S7 = 2k: ~21k em arquivos novos de prova e teste, mais ~20 KB lidos do módulo Payment (~5k) e dos testes que mudam de namespace (~10k); total ~36k, tudo no worktree e no `Payment`, abaixo do budget de 150k - one builder
- Mechanism: one builder (cabe no budget, sem pergunta)

- **Boundary:** C1-C11 e C13-C17 fechados na branch do spike em `8c4bf14` (worktree ainda existe); C12 vermelho só na cláusula da contagem (ver abaixo); C18 roda por último, depois do Verifier
- **Settled mid-build:** nenhum esclarecimento do usuário; achado do spike - os padrões de UUID no `boot()` não valem para rotas de módulo, movidos para o `register()` (commit `8c4bf14`), registrado no design
- **Abandoned:** a primeira tentativa, com os padrões ainda no `boot()` do `AppServiceProvider`: 14 testes vermelhos, 4 deles de comportamento (`RouteBindingTest` e 3 casos de `UuidIdsTest`)
