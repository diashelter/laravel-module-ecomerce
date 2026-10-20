# Module Layout checks

Profile: standard
Plan: `.specs/features/module-layout/plan.md`

21 checks in 5 slices · 6 one-way doors (dependência, namespace e caminho, fachada em `src`, pastas de `src`, fachada do `Messaging`, manifesto do pacote) · 0 open

Notação das provas. `BUILD` é o worktree `.claude/worktrees/hel-12-module-layout`, na branch `helterdias/hel-12-implementar-um-padrao-de-modularidade-melhor-ao-sistema`, criada de `main` em f1dd960 (a mesma linha de base de 1928 testes de `.specs/features/library-spike/evidence/baseline-make-test.txt`). `make test-filter FILTER="<regex>"` roda em `BUILD` e usa o banco `ecommerce_testing_hel_12_module_layout`. As provas de comando são scripts em `.specs/features/module-layout/proofs/`, escritos a partir do check e nunca da implementação, que saem com 0 só quando a afirmação vale e gravam a saída em `.specs/features/module-layout/evidence/c<NN>.txt`. Os testes novos nomeados abaixo são escritos antes do código que os satisfaz.

## Checks

### S1 - os módulos são pacotes que o Laravel descobre · 6 files · 90 KB · ~25k

**C1** - Os 10 pacotes `backoffice`, `catalog`, `customers`, `fulfillment`, `identity`, `inventory`, `messaging`, `ordering`, `payment` e `shared` aparecem em `modules:list`, cada um com o namespace `Modules\<Nome>` (AC 1)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="lists exactly the ten module packages"` - `tests/Unit/Architecture/ModulePackagesTest.php`
Proof: `bash .specs/features/module-layout/proofs/c01-modules-list.sh`

**C2** - Nenhum arquivo PHP, JSON, XML ou YAML de `backend/`, `load-tests/` e `.github/` contém `App\Modules`, e o diretório `backend/app/Modules` não existe (AC 2)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="keeps no App.Modules reference in the project"` - `tests/Unit/Architecture/ModulePackagesTest.php`

**C3** - `backend/bootstrap/providers.php` lista só `AppServiceProvider` e `HorizonServiceProvider` (AC 3)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="registers no module provider in bootstrap providers"` - `tests/Unit/Architecture/ModulePackagesTest.php`

**C4** - `make:module sample` numa cópia descartável muda, fora de `app-modules/sample`, só `backend/composer.json` e `backend/composer.lock` (AC 4)
Proof: `bash .specs/features/module-layout/proofs/c04-make-module.sh`

### S2 - a árvore e a visibilidade · 5 files · 70 KB · ~18k

**C5** - Toda classe de cada um dos 10 módulos está numa pasta permitida de `src`: `Contracts`, `Events`, `Core`, `Persistence`, `Http`, `Providers`, mais `Gateways` no `payment` e `Console` no `messaging`; o `shared` mantém `Http`, `Cache`, `Enums`, `Exceptions`, `Repositories`, `Support` e `Providers` (AC 5)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="keeps every class of a module in an allowed src folder"` - `tests/Unit/Architecture/ModulePackagesTest.php`, uma linha de dataset por módulo

**C6** - Um módulo que usa uma classe de outro que não está em `Contracts`, em `Events` nem no vocabulário das assinaturas do dono faz o `ModuleBoundariesTest` falhar (AC 6)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="reaches another module only through its public directories|uses only the public vocabulary of"` - `tests/Unit/Architecture/ModuleBoundariesTest.php`, uma linha por par módulo e pasta

**C7** - Uma classe de `Core` ou de `Persistence` que usa uma classe de `Http` do mesmo módulo faz o teste de camadas falhar (AC 7)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="keeps Core and Persistence from using Http"` - `tests/Unit/Architecture/ModuleLayersTest.php`, uma linha por módulo de negócio e pelo `messaging`

**C8** - Um repositório que usa um serviço do módulo, ou uma consulta que usa um repositório ou um caso de uso do módulo, faz o teste falhar (AC 8)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="repositories do not use the module services|queries do not use the module repositories and use cases"` - `tests/Unit/Architecture/ModuleBoundariesTest.php`

**C9** - `Shared` ou `Messaging` usando um módulo de negócio, e `Shared` usando o `Messaging`, fazem o teste falhar (AC 9)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="keeps the infrastructure modules from using business modules|keeps Shared from using Messaging"` - `tests/Unit/Architecture/ModuleLayersTest.php`

**C10** - Cada regra nova (pasta permitida, `Core` e `Persistence` sem `Http`, infraestrutura sem negócio, `Shared` sem `Messaging`) tem um teste que planta uma violação em fixture e só passa se o detector a acusa (AC 10)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="fails on a deliberate violation"` - `tests/Unit/Architecture/ModuleLayersTest.php`, um caso por regra nova (4)

### S3 - o Messaging é um módulo · 4 files · 60 KB · ~16k

**C11** - O `Messaging` expõe exatamente `DomainEvent`, `EventOutbox`, `EventConsumer` e `EventSubscriptions` em `Modules\Messaging\Contracts`, todas interfaces, e nenhum módulo de negócio usa outra classe dele (AC 11)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="exposes exactly four messaging contracts"` - `tests/Feature/Messaging/MessagingModuleTest.php`
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="reaches another module only through its public directories: .*Messaging"` - `tests/Unit/Architecture/ModuleBoundariesTest.php`

**C12** - `php artisan list` registra `outbox:relay`, `events:transport`, `events:consume`, `events:failed` e `events:retry` a partir do provider do `Messaging`, e o `AppServiceProvider` não registra nenhum deles nem liga `EventOutbox`, `EventTransport` ou o seletor (AC 12)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="registers the five event commands from its own provider"` - `tests/Feature/Messaging/MessagingModuleTest.php`

**C13** - O nome de cada evento, de cada consumidor e de cada fila é o de antes (AC 13)
Proof: `make -C .claude/worktrees/hel-12-module-layout test-filter FILTER="ConsumerNamesTest|OrderEventPayloadTest|EventTransportFlagTest|RabbitMqEventTransportTest"` - os arquivos existentes, sem alteração de asserção

### S4 - o comportamento não mudou · 4 files · 40 KB · ~11k

**C14** - `make test` termina com 0 falhas, e todo arquivo de teste fora de `tests/Unit/Architecture` tem a mesma contagem da linha de base de `main` (1928 testes) (AC 14)
Proof: `bash .specs/features/module-layout/proofs/c14-suite.sh`

**C15** - `route:list --json` de `BUILD` é igual ao de `main` em URI, método, nome e middleware, com as ações diferindo só pelo prefixo `App\Modules` → `Modules` (AC 15)
Proof: `bash .specs/features/module-layout/proofs/c15-route-list.sh`

**C16** - `migrate:status` do banco de desenvolvimento lista os mesmos 14 nomes como `Ran`, e `php artisan migrate --pretend` responde `Nothing to migrate.` (AC 16)
Proof: `bash .specs/features/module-layout/proofs/c16-migrations.sh`

**C17** - `make pint` em `BUILD` sai com 0 (AC 17)
Proof: `make -C .claude/worktrees/hel-12-module-layout pint`

**C18** - `make load-test TYPE=smoke` em `BUILD` sai com 0, e nenhum arquivo de `backend/bootstrap/cache` é criado ou alterado pelos containers de carga (AC 18)
Proof: `bash .specs/features/module-layout/proofs/c18-load-smoke.sh`

### S5 - a documentação descreve o código como ficou · 3 files · 300 KB · ~35k

**C19** - `README.md`, `AGENTS.md` e `docs/domain-analysis.md` não contêm `backend/app/Modules` nem `App\Modules`, e todo link relativo deles aponta para um arquivo que existe (AC 19)
Proof: `bash .specs/features/module-layout/proofs/c19-docs-links.sh`

**C20** - O `AGENTS.md` traz o critério de interface (fronteira em `Contracts`, ou porta com segunda implementação ou duplo de teste real) e a árvore `Contracts`, `Events`, `Core`, `Persistence`, `Http` com `Http` → `Core` → `Persistence` (AC 20)
Proof: `bash .specs/features/module-layout/proofs/c20-agents-rules.sh`

**C21** - A `docs/domain-analysis.md` lista os 10 módulos, descreve o `Messaging` e o `Shared` novos, marca o passo concluído com a data 19/10/2026 e traz a nota de deploy sobre a fila do Redis (AC 21)
Proof: `bash .specs/features/module-layout/proofs/c21-domain-analysis.sh`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| critérios do plano (21) | AC 1 C1 · AC 2 C2 · AC 3 C3 · AC 4 C4 · AC 5 C5 · AC 6 C6 · AC 7 C7 · AC 8 C8 · AC 9 C9 · AC 10 C10 · AC 11 C11 · AC 12 C12 · AC 13 C13 · AC 14 C14 · AC 15 C15 · AC 16 C16 · AC 17 C17 · AC 18 C18 · AC 19 C19 · AC 20 C20 · AC 21 C21 | - |
| módulos (10) | backoffice C1 C5 · catalog C1 C5 · customers C1 C5 · fulfillment C1 C5 · identity C1 C5 · inventory C1 C5 · messaging C1 C5 C7 C11 · ordering C1 C5 · payment C1 C5 · shared C1 C5 | - |
| pastas permitidas de `src` (8) | `Contracts` C5 · `Events` C5 · `Core` C5 C7 · `Persistence` C5 C7 · `Http` C5 C7 · `Providers` C5 · `Gateways` C5 · `Console` C5 | - |
| regras de arquitetura novas (4) | pasta permitida C5 C10 · `Core` e `Persistence` sem `Http` C7 C10 · infraestrutura sem negócio C9 C10 · `Shared` sem `Messaging` C9 C10 | - |
| regras de arquitetura herdadas (3) | privacidade das pastas C6 · vocabulário público C6 · repositório sem serviço e consulta sem repositório nem caso de uso C8 | - |
| fachada do `Messaging` (4) | `DomainEvent` C11 · `EventOutbox` C11 · `EventConsumer` C11 · `EventSubscriptions` C11 | - |
| comandos de evento (5) | `outbox:relay` C12 · `events:transport` C12 · `events:consume` C12 · `events:failed` C12 · `events:retry` C12 | - |
| providers fora de `bootstrap/providers.php` (7) | Catalog C3 · Customers C3 · Fulfillment C3 · Identity C3 · Inventory C3 · Ordering C3 · Payment C3 | - |
| migrations (14) | C16, table-driven over all 14 (os nomes lidos de `git ls-tree f1dd960:backend/database/migrations`) | - |
| rotas de `main` (73) | C15, table-driven over all 73 (as linhas de `route:list --json` de `main`, 47 sob `api/`) | - |
| arquivos de teste fora de `Architecture` (95) | C14, table-driven over all 95 (cada arquivo, contagem igual à de `main`) | - |
| documentos (3) | `README.md` C19 · `AGENTS.md` C19 C20 · `docs/domain-analysis.md` C19 C21 | - |
| startup configuration: descoberta dos módulos (3 assemblies) | `artisan` na linha de comando C1 C12 · aplicação montada pelo Pest C14 · pilha de carga C18 | - |

- Claims naming a status code, route or response shape: C15 (cruza o limite HTTP: as 73 rotas de `main` com a mesma superfície) e C18 (o smoke do k6 fala HTTP, como o SPA)
- No other check claims more than the single case its proof exercises

## Swept

- validation: existing - nenhum campo, limite ou formato muda; as validações dos Form Requests vão intactas e a suíte as cobre (C14)
- failure modes: C12 (comando de evento sem provider) e C18 (a pilha de carga sobe sem cache gravado em `bootstrap/cache`)
- idempotency: n/a - o `composer install` e a movimentação são repetíveis, mas não há operação de negócio nova; os consumidores seguem idempotentes pelos testes de C13
- authorization: C14 e C15 (a igualdade do `route:list` inclui `auth:customer`, `auth:staff` e `admin`; `AuthorizationTest` e `StaffRoleTest` ficam verdes)
- concurrency: existing - `RateLimitBurstTest`, `PaymentTest` (corrida de aprovação) e `StockRepositoryTest` rodam dentro de C14 sem mudança de asserção
- data lifecycle: C16 (nenhuma migration muda de nome; um banco migrado continua sem pendência)
- dependency failure: C13 (os testes do RabbitMQ real, `BrokerIsolationTest` e `RabbitMqEventTransportTest`, seguem verdes)
- state transitions: existing - `OrderStatusFlowTest` roda dentro de C14
- observability: C13 (o `request_id` do envelope volta ao `Context` do consumidor, `EventDeliveryTest`, dentro de C14)

## Out of scope

- rotas por módulo e migrations por módulo - são as slices Module Routes e Module Migrations
- a comparação de carga antes e depois - é a Load Baseline

## Handoff

Tamanho, em tokens, pela conta `wc -c` dividida por quatro, sobre o que cada slice lê ou edita à mão (os 260 arquivos de `app/Modules` e os 110 de teste são reescritos por script e não entram na leitura):

- S1 = 25k (o script ~6k, o `composer.json`, `bootstrap/providers.php`, `bootstrap/app.php`, `config/auth.php`, as 10 factories e os seeders ~19k), tudo no `backend/`
- S2 = 18k (`ModuleBoundariesTest` 26 KB ≈ 7k, os 7 testes de arquitetura vizinhos ≈ 5k, o teste de pacotes e o de camadas ≈ 6k)
- S3 = 16k (os 26 arquivos de mensageria ≈ 12k lidos para dividir camadas, o novo provider e o `AppServiceProvider` ≈ 4k)
- S4 = 11k (saída dos scripts, `route:list`, suíte e smoke)
- S5 = 35k (leitura de partes de `README.md`, `AGENTS.md` e `docs/domain-analysis.md`: 119 links a reescrever e os trechos de estrutura)
- total ≈ 105k, abaixo do budget de 150k - one builder, sem pergunta
- Mechanism: one builder
