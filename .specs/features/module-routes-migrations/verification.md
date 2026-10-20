# Module Routes and Migrations verification

**Verdict**: PASS
**Profile**: standard
**Diff range**: 7c530e4..b89346e
**Round**: 3 - scoped
**Verifier**: independent sub-agent (author != verifier)

Resumo da rodada 3 (verified at b89346e). A correção `b89346e` toca só `proofs/c20-domain-analysis.sh`, `evidence/c08.txt` e `evidence/c20.txt` (`git diff --stat df39090..b89346e`). Nenhum código de produto mudou desde `7a325cb`.

- **Gap 1 da rodada 2 (C20) resolvido.** A prova exige, em cada um dos 3 passos do plano de evolução, um `Concluído em dd/mm/yyyy` próprio. Os 3 passos são `✅ **Módulos como pacotes`, `✅ **Rotas e migrations por módulo` (que cobre as slices Module Routes e Module Migrations) e `✅ **Load Baseline`. A mensagem da linha 4 corresponde à expressão. Injetei 4 falhas numa cópia do documento: tirar a data de cada passo e trocar a "Última atualização". As 4 foram pegas, e o controle sem mutação saiu com 0.
- **Gap 2 da rodada 2 (evidência do C8) resolvido.** O `evidence/c08.txt` versionado é idêntico, byte a byte, à saída da prova que eu rodei no HEAD (`diff` vazio).
- As provas rodaram de novo, por inteiro, em `b89346e` (o C13 uma vez, `ROUND=1`). Usei uma cópia de `proofs/` cujo `lib.sh` só aponta `EVID` para o rascunho e `ROOT` para um caminho literal, então nenhuma evidência versionada foi reescrita. O `git status --porcelain` da árvore real, antes e depois, mostra só `?? .specs/features/module-routes-migrations/verification.md`, este arquivo.

## Binding sources

carried from 7a325cb: as correções das rodadas 2 e 3 não tocam interface, design nem documentos de produto.

| Source | Opened | Contradiction | Uncovered |
| --- | --- | --- | --- |
| `.design/modular-layout.md` Key decision 4 (prefixo `/api` e grupo `api` no módulo, padrões de UUID no `register()`) | yes - linha 48 | none | - |
| `.design/modular-layout.md` Key decision 5 (nenhuma migration renomeada nem editada, as 5 FKs entre módulos) | yes - linha 49 | none | - |
| `.design/modular-layout.md` slice Module Routes | yes - linhas 173-182 | none | - |
| `.design/modular-layout.md` slice Module Migrations | yes - linhas 184-230 | none | - |
| `.design/modular-layout.md` slice Load Baseline | yes - linhas 232-241 | none | - |
| `AGENTS.md` da BUILD | yes | none | - |

O estado "Depois de mover: diferença menor que o ruído" do Load Baseline continua coberto pelo C13. O meu `load` em `b89346e` deu 23,88/35,16/1.357,9 ms, contra o controle em `main` de 24,8/38,1/1.352 ms (`load-tests/README.md:170`).

## Checks

verified at b89346e. Comando Pest único, em lote, na BUILD (o filtro com as 17 alternativas dos checks C2-C8, C10, C11 e C21), exit 0, `Tests: 89 passed (498 assertions)`, 89 linhas `✓`. Nenhum arquivo de teste mudou desde `7a325cb`, então as citações em `backend/tests` não se moveram.

| Check | Claim | Proof run | Evidence | Result |
| --- | --- | --- | --- | --- |
| C1 | `route:list --json` igual aos 73 itens de `7c530e4` | `bash proofs/c01-route-list.sh` exit 0: "73 of 73 routes identical" | `proofs/c01-route-list.sh:10` - `assert len(base) == 73`; `:15-16` - `if len(base) != len(build) or bad: sys.exit(1)`. A lista base regerada por mim em `7c530e4` é carried from 7a325cb | PASS |
| C2 | sem `backend/routes/api.php`, `bootstrap/app.php` sem `api:` | lote Pest `✓` | `backend/tests/Unit/Architecture/ModuleRoutesTest.php:19-20` - `expect($files)->toBe(['console.php'])->and(file_get_contents(base_path('bootstrap/app.php')))->not->toMatch('/\bapi\s*:/')` | PASS |
| C3 | 8 módulos com `routes/api.php` e o envelope; `shared` e `messaging` sem `routes` | lote Pest, 8 + 2 `✓` | `ModuleRoutesTest.php:26-27` - `->toContain("Route::prefix('api')->middleware('api')->group(")`; `:31` - `expect(is_dir(...'/routes'))->toBeFalse()` | PASS |
| C4 | controller de outro módulo num arquivo de rotas falha | lote Pest, 8 + 1 `✓` | `ModuleRoutesTest.php:37` - `expect(ModuleLayout::routeViolations($file, $module))->toBe([])`; `:47` - `->toBe(['Modules\Catalog\Http\Controllers\ProductController'])` | PASS |
| C5 | nenhum par método e URI repetido | lote Pest `✓` | `ModuleRoutesTest.php:59-60` - `->and(array_keys(array_filter(array_count_values($seen), fn (int $count) => $count > 1)))->toBe([])` | PASS |
| C6 | os 7 padrões no `register()` do dono, nenhum no `AppServiceProvider` | lote Pest, 7 + 1 `✓` | `ModuleRoutesTest.php:83-84` - `expect($fresh->getPatterns())->toHaveKey($parameter)->and($fresh->getPatterns()[$parameter])->toBe(RecordId::PATTERN)`; `:96` - `->not->toContain('Route::pattern')` | PASS |
| C7 | parâmetro sem padrão faz o `RouteBindingTest` falhar; id fora do padrão responde `404` | lote Pest, 3 + 36 `✓` | `backend/tests/Unit/Architecture/RouteBindingTest.php:50` - `expect($route->wheres[$name] ?? null)->toBe(RecordId::PATTERN, ...)`; `backend/tests/Feature/UuidIdsTest.php:194` - `->toContain('product', 'category', 'stock', 'customer', 'user', 'order', 'address')`; `:205-206` - `assertApiError($response->assertNotFound(), 'NOT_FOUND', ...)` e `expect($queries)->toBeEmpty(...)` | PASS |
| C8 | só `cache`, `jobs` e `features` na raiz; 11 migrations no dono, com nome e bytes de `7c530e4` | lote Pest (1 + 11 `✓`) e `bash proofs/c08-migration-bytes.sh` exit 0, stderr vazio: `owner pairs checked: 11`, `R100 renames: 11`, `other changes in migration folders: 0`. A saída é idêntica ao `evidence/c08.txt` versionado | `proofs/c08-migration-bytes.sh:19` - `grep -qxF -- "$want" <<<"$diff_out" (senão `fail`) "no byte-identical rename for $name -> $owner"` (11 voltas); `:23` - `[ "$checked" = "11" ]`; `:25` - `[ "$renames" = "11" ]`; `:27` - `[ "$other" = "0" ]`; `ModuleMigrationsTest.php:40` - `expect($names)->toBe(FRAMEWORK_MIGRATIONS)`; `:44-45` - o arquivo existe no dono e não existe na raiz | PASS |
| C9 | `migrate:status` no banco migrado: 14 `Ran`, nenhum `Pending`, `Nothing to migrate.` | `bash proofs/c09-migrate-status.sh` exit 0 | `proofs/c09-migrate-status.sh:6` - `grep -q 'Pending' && fail`; `:8` - `[ "$actual" = "$expected" ]`; `:10` - `grep -q 'Nothing to migrate'` | PASS |
| C10 | `migrate:fresh` exit 0, 14 `Ran` na ordem, as 5 FKs | `bash proofs/c10-migrate-fresh.sh` exit 0; lote Pest, 5 `✓` | `proofs/c10-migrate-fresh.sh:4` - `art migrate:fresh --force ... (senão `fail`)`; `:9` - `[ "$actual" = "$expected" ]`; `backend/tests/Feature/Database/ModuleMigrationsForeignKeyTest.php:20` - `expect($row?->referenced)->toBe($references)` | PASS |
| C11 | os nomes dos caminhos do migrator são os 14 congelados; um renome é detectado | lote Pest, 2 `✓` | `ModuleMigrationsTest.php:53` - `expect(migrationNamesIn($paths))->toBe($frozen)`; `:67` - `->not->toBe($frozen)` | PASS |
| C12 | `make load-test TYPE=smoke` exit 0, `bootstrap/cache` intacto | `bash proofs/c12-load-smoke.sh` exit 0 | `proofs/c12-load-smoke.sh:9` - `[ "$code" = "0" ]`; `:10` - `diff c12-cache.before c12-cache.after` | PASS |
| C13 | `make load-test TYPE=load` exit 0, p95 até 40/70/2.450 ms, 0% de erro inesperado | `ROUND=1 bash proofs/c13-load-load.sh` exit 0 (uma rodada) | `proofs/c13-load-load.sh:6` - `[ "$code" = "0" ]`; o k6 imprimiu `ok` nos 4 limites: vitrine p95 23,88, `POST /api/orders` p95 35,16, `order_payable_wait` p95 1.357,9, `unexpected_errors` 0,00. `git diff 7c530e4..b89346e -- load-tests/lib/baseline.js` está vazio | PASS |
| C14 | linha `redis`/`load` com HEL-12, commit existente, dois p95 a menos de 20% | `bash proofs/c14-baseline-row.sh` exit 0 (3,3%, 2,1%, 0,5%) | `proofs/c14-baseline-row.sh:9` - `assert len(rows) == 1`; `:11` - `assert cells[3] == "redis" and cells[4] == "load"`; `:13` - `git cat-file -e 299af92^{commit}`; `:23-25` - `if diff >= 0.20: bad += 1` e `sys.exit(1 if bad else 0)`. A conferência com `evidence/c13-fixed-round1/2.summary.txt` é carried from 7a325cb; `299af92..b89346e` não tem código de produto | PASS |
| C15 | mediana de 20 execuções sem e com `modules:cache`, as duas > 0 | `bash proofs/c15-discovery-cost.sh` exit 0 (222,2 e 190,3 ms) | `proofs/c15-discovery-cost.sh:6` - `assert "mediana" in text and "20 execuções" in text`; `:12` - `assert vals[key] > 0` | PASS |
| C16 | `make test` sem falha; contagem de `7c530e4` em todo arquivo fora de `tests/Unit/Architecture` | `bash proofs/c16-suite.sh` exit 0: `Tests: 2064 passed (8817 assertions)`, 96 arquivos iguais | `proofs/c16-suite.sh:5` - `grep -qE '[0-9]+ failed' && fail`; `:18` - `[ "$bad" = "0" ]`. A base `2014 passed` refeita por mim em `7c530e4` é carried from 7a325cb | PASS |
| C17 | `make pint` exit 0 | `make -C <BUILD> pint` exit 0, `PASS ... 461 files` | `Makefile:179` - `./vendor/bin/pint --test` | PASS |
| C18 | nenhum `routes/api.php` fora de `app-modules/<módulo>/`; links relativos existem | `bash proofs/c18-docs-links.sh` exit 0 | `proofs/c18-docs-links.sh:19-20` - `if not re.search(r'app-modules/[^/\s`]+/$', ...)`: `bad += 1`; `:29-30` - `if not os.path.exists(...)`: `bad += 1`; `:32` - `sys.exit(1 if bad else 0)` | PASS |
| C19 | regras de rotas, padrões e migrations no `AGENTS.md` | `bash proofs/c19-agents-rules.sh` exit 0 | `proofs/c19-agents-rules.sh:5` - `grep -qF -- "$needle" "$f" (senão `fail`)`; o texto está em `AGENTS.md:61` e `:71` | PASS |
| C20 | `docs/domain-analysis.md` marca os passos como concluídos com a data, troca a decisão e atualiza "Última atualização" | `bash proofs/c20-domain-analysis.sh` exit 0: `step 'Módulos como pacotes' done with a date`, `step 'Rotas e migrations por módulo' done with a date`, `step 'Load Baseline' done with a date`; 4 falhas injetadas pegas | `proofs/c20-domain-analysis.sh:4` - `grep -qE "Última atualização: [0-3][0-9]/10/2026"`; `:8-9` - `line=$(grep -E "✅ \*\*$step" <<<"$steps" ...)` (a primeira linha que casa) e `[ -n "$line" ]` (senão `fail`); `:10` - `grep -qE 'Concluído em [0-3][0-9]/[01][0-9]/20[0-9]{2}' <<<"$line" (senão `fail`)`, por passo; `:13-14` - a decisão nova presente e a antiga ausente. Os passos estão em `docs/domain-analysis.md:683-685` | PASS |
| C21 | o cache de módulos segue `APP_MODULES_CACHE` e, sem a variável, usa `bootstrap/cache/app-modules.php` | lote Pest, 2 `✓` | `backend/tests/Unit/Architecture/ModuleCachePathTest.php:30-31` - `expect(is_file($file))->toBeTrue()->and(is_file($default))->toBe($hadDefault)`; `:45` - `expect($path)->toBe($default)` | PASS |
| C22 | `APP_MODULES_CACHE` nos 4 serviços PHP de carga; `modules:cache` no `api` antes do k6 | `bash proofs/c22-load-modules-cache.sh` exit 0 | `proofs/c22-load-modules-cache.sh:10` - `[ "$value" = "/tmp/laravel/app-modules.php" ]` para os 4 serviços; `:12` - `exec -T api test -s /tmp/laravel/app-modules.php`; `:13` - `[ ! -e .../bootstrap/cache/app-modules.php ]`; configuração em `load-tests/docker-compose.yml:18` e `Makefile:227` | PASS |

## Coverage

As linhas que vêm de código de produto são carried from 7a325cb, porque nenhuma correção tocou as fontes delas. As provas de todas rodaram de novo em `b89346e`. A linha dos passos datados é verified at b89346e. A linha das migrations que se movem é carried from df39090, porque a prova c08 não mudou depois disso.

| Set (size) | Recomputed from | Member -> proof | Unproven |
| --- | --- | --- | --- |
| módulos (10): 8 com rotas e 2 sem (carried from 7a325cb) | `ls backend/app-modules`, `ls -d backend/app-modules/*/routes` | os 8 -> C3 e C4; `shared`, `messaging` -> C3 | - |
| rotas da API (73) (carried from 7a325cb) | `route:list --json` em `7c530e4` e no HEAD | as 73 -> C1; sem duplicata -> C5 | - |
| contadores de `throttle` nomeados (7) (carried from 7a325cb) | middleware do `route:list` | os 7 -> C1 | - |
| parâmetros de rota com padrão de UUID (7) (carried from 7a325cb) | parâmetros das URIs `api/...` | os 7 -> C6 e C7 | - |
| migrations que se movem (11) (carried from df39090) | `git ls-tree 7c530e4:backend/database/migrations` menos as 3 do framework | os 11 -> C8: `ModuleMigrationsTest.php:16-28` e `c08-migration-bytes.sh:17-23` (11 voltas, cada uma com o dono) | - |
| migrations na raiz (3) (carried from 7a325cb) | `ls backend/database/migrations` | `cache`, `jobs`, `features` -> C8 | - |
| nomes de migration congelados (14) (carried from 7a325cb) | `git ls-tree 7c530e4:backend/database/migrations` | os 14 -> C11, C9, C10 | - |
| chaves estrangeiras entre módulos (5) (carried from 7a325cb) | `foreignUuid`/`constrained` nas 14 migrations | as 5 -> C10 | - |
| serviços PHP da pilha de carga (4) (carried from 7a325cb) | `load-tests/docker-compose.yml` | `api`, `queue-worker`, `outbox-relay`, `event-consumer` -> C22 | - |
| portas de mão única da Landing (4) (carried from 7a325cb) | `plan.md` Landing | C3; C8 e C11; C6; C21 e C22 | - |
| configuração de partida (3 assemblies) (carried from 7a325cb) | `bootstrap/app.php:13-16`, `tests/Pest.php:33-35`, `Makefile:227` e `load-tests/docker-compose.yml:18` | CLI -> C1, C9, C10; Pest -> C5, C6, C7, C21; pilha de carga -> C12, C13, C22 | - |
| p95 do `load` (3) (verified at b89346e) | `lib/baseline.js` e os limites do k6 | os 3 -> C13 e C14 | - |
| passos do plano de evolução concluídos com data (verified at b89346e) | AC 20 e `docs/domain-analysis.md:683-685` | Module Routes e Module Migrations (o passo `Rotas e migrations por módulo`, linha 684, que nomeia as duas slices) -> C20 `:10`; Load Baseline (linha 685) -> C20 `:10`; o passo extra `Módulos como pacotes` (linha 683, a PR 27) -> C20 `:10` | - |
| documentos que mudam (4) e lugares sem `routes/api.php` (5) (carried from 7a325cb) | `git diff --name-status` | C14, C15, C18, C19, C20 | - |

## Test policy rows

carried from 7a325cb. O `checks.md` não tem seção `Test policy`, então a convenção do repositório decide, e ela foi seguida: estrutura em `tests/Unit/Architecture`, o que precisa do banco em `tests/Feature/Database` (`RefreshDatabase`, `tests/Pest.php:33-35`). As correções só tocam scripts de prova e evidência.

## Faults injected

As linhas 1 a 5 são carried from 7a325cb. Elas atacam código de produto, e nenhum arquivo delas mudou desde então. A linha 6 é carried from df39090, porque a prova c08 não mudou em `b89346e`. As linhas 7 a 10 são verified at b89346e, na superfície que a correção tocou: cópias de `docs/domain-analysis.md` no rascunho, lidas pela prova C20 do HEAD. Uma cópia sem mutação saiu com 0 (`OK`). A árvore real não foi alterada.

| Mutation | Location | Killed |
| --- | --- | --- |
| 1. o arquivo de rotas do Ordering importa `Modules\Catalog\Http\Controllers\ProductController` (carried from 7a325cb) | `backend/app-modules/ordering/routes/api.php:6` (linha nova) | yes - C4 |
| 2. `register()` do `InventoryServiceProvider` virou `boot()` (carried from 7a325cb) | `backend/app-modules/inventory/src/Providers/InventoryServiceProvider.php:32` | yes - C6 e C7 |
| 3. `git mv` de `2026_10_05_000005_create_orders_table` para `..._000009_...` (carried from 7a325cb) | `backend/app-modules/ordering/database/migrations/` | yes - C11 |
| 4. leitura de `Env::get('APP_MODULES_CACHE')` removida (carried from 7a325cb) | `backend/app/Providers/AppServiceProvider.php:24` | yes - C21 |
| 5. `&& php artisan modules:cache` removido do alvo `load-test` (carried from 7a325cb) | `Makefile:227` | yes - C22 |
| 6. o par de dono das `payments` virou `ordering` numa cópia da prova c08 (carried from df39090) | cópia de `proofs/c08-migration-bytes.sh:13` | yes - exit 1, `no byte-identical rename for 2026_10_06_000001_create_payments_table -> ordering` |
| 7. `Concluído em 19/10/2026` virou `Concluído` no passo `Módulos como pacotes` | cópia de `docs/domain-analysis.md:683` | yes - C20 exit 1: `the plan step 'Módulos como pacotes' carries no 'Concluído em dd/mm/yyyy' date` |
| 8. `Concluído em 19/10/2026` virou `Concluído` no passo `Rotas e migrations por módulo` (Module Routes e Module Migrations) | cópia de `docs/domain-analysis.md:684` | yes - C20 exit 1: `the plan step 'Rotas e migrations por módulo' carries no 'Concluído em dd/mm/yyyy' date` |
| 9. `Concluído em 20/10/2026` virou `Concluído` no passo `Load Baseline` | cópia de `docs/domain-analysis.md:685` | yes - C20 exit 1: `the plan step 'Load Baseline' carries no 'Concluído em dd/mm/yyyy' date` |
| 10. `Última atualização: 20/10/2026` virou `06/09/2026` | cópia de `docs/domain-analysis.md:4` | yes - C20 exit 1: `the 'Última atualização' line carries no October 2026 date` |

## Gate

verified at b89346e. `make -C <BUILD> test` (via `proofs/c16-suite.sh`): 2064 passed, 0 failed (8817 assertions). Lote Pest filtrado: 89 passed, 0 failed. Scripts de prova: 13 de 13 com exit 0 (c01, c08, c09, c10, c12, c13 ROUND=1, c14, c15, c16, c18, c19, c20, c22). `make pint`: exit 0 (461 files).

### Notas que não bloqueiam

- **AC 16** (carried from 7a325cb): "(2014 testes na soma)" é o total da suíte em `7c530e4`. A soma dos arquivos fora de `tests/Unit/Architecture` é 1.103. A linha do plano foi aprovada e não é reescrita, e o C16 não usa o número.
- **Condição da máquina de carga**: está registrada na linha do README (`load-tests/README.md:157`). As rodadas 2 e 3 deram p95 a menos de 3% (rodada 2) e 11% (rodada 3, `POST /api/orders` 35,16 contra 39,2) da linha. Nas duas, os valores ficaram dentro dos limites.
- **C20 com um ano fixo**: a "Última atualização" é presa a `/10/2026` (`proofs/c20-domain-analysis.sh:4`). Uma atualização futura do documento, por outro trabalho, vai exigir ajustar a prova. É uma escolha da prova, não um defeito da entrega.
