# Library Spike

## Problem

A HEL-12 pede o `internachi/modular`, e o design [modular-layout](../../../.design/modular-layout.md) assume cinco coisas sobre ele que ninguém executou neste repositório: onde o cache de módulos grava, se as rotas do módulo precisam declarar o prefixo `/api`, se o migrator ordena as migrations de vários caminhos pelo nome global, se a factory declarada em `#[UseFactory]` continua resolvendo de dentro do módulo, e se o `make test` de um worktree, o entrypoint do container e o CI funcionam depois de adicionar o repositório `path`. Cada resposta muda uma Key decision (1, 4 ou 5) ou o Docker e o Makefile. Quem paga é a Module Layout: ela move 260 arquivos por script, e uma suposição errada descoberta no meio obriga a refazer a movimentação. Evidência da fonte: a documentação consultada (Context7, que pode não ser a oficial) diz que as rotas do módulo só passam por `require`; nada foi rodado.

Quando o spike terminar, a Module Layout começa com as cinco respostas escritas no design, cada uma com o comando que a provou, e com a decisão confirmada ou trocada.

## Flow

Reaproveita o `Payment` como está, o menor módulo que tem model, migration, rota, factory e evento, e os alvos `make test`, `make pint` e `make artisan` que já existem.

1. worktree descartável na branch `spike/hel-12-library` -> `composer` (exists) exige `internachi/modular` e o repositório `path` em `app-modules/*`
2. `Payment` (exists) vai inteiro para `app-modules/payment` (new, no door - placement descartável), com o namespace `Modules\Payment` e as referências a ele reescritas no resto do código
3. a biblioteca (door 1, a dependência) descobre o provider, as rotas, a migration e a factory do pacote `Payment` no boot do Laravel
4. `make test` (exists), `make pint` (exists) e comandos `artisan` (exists) rodam no worktree, sempre contra o banco `ecommerce_testing_<worktree>`; a saída de cada um é guardada
5. out: as cinco respostas entram em `.design/modular-layout.md`, a branch e o worktree são descartados, e nada do spike chega à `main`

## Impact

| Front | What changes |
| --- | --- |
| domain | termo novo: pacote de módulo - um módulo com `composer.json` próprio em `app-modules/`; só existe na branch do spike |
| domain | termo existente: `App\Modules\Payment` passa a `Modules\Payment` na branch - quem ramifica nele hoje: `OrderingServiceProvider`, `ModuleBoundariesTest`, `Pest.php`, `PaymentTest`, `PaymentGateway`-related tests e as factories |
| stored data | nada a migrar: as migrations rodam só no banco de testes do worktree; o banco de desenvolvimento e a pilha de carga não são tocados |

## Relations

`None - no stored-data shape change`

## Surface

`None - nothing consumed outside`

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| a dependência `internachi/modular` | `"internachi/modular": "^3.0"` em `require`, mais `"repositories": {"modules": {"type": "path", "url": "app-modules/*", "options": {"symlink": true}}}` - só na branch descartável do spike; a porta real é da Module Layout | manter `App\Modules` com providers próprios carregando rotas e migrations: ganha se uma das cinco respostas for impeditiva, e é a alternativa que a Shape do design guarda |

- Nada mais neste spike é difícil de reverter: ele não pousa código na `main`

## Criteria

### S1: o ambiente do spike existe e é seguro (P1)

Um worktree com o `Payment` movido, rodando sem tocar nos bancos de desenvolvimento nem de carga.

**Acceptance Criteria**

1. WHEN `composer install` roda no worktree depois de adicionar a dependência THEN o Composer SHALL resolver `internachi/modular` na versão 3.x e `php artisan modules:list` SHALL listar `payment`
2. IF um comando do spike alcançaria o banco de desenvolvimento (`make artisan`) THEN ele SHALL rodar com `DB_DATABASE=ecommerce_testing_<worktree>`, e o `migrate:status` do banco de desenvolvimento SHALL ser idêntico antes e depois do spike

**Independent test:** `modules:list` mostra `payment` e o `migrate:status` de desenvolvimento não mudou.

### S2: cache de módulos (P1)

**Acceptance Criteria**

3. WHEN `php artisan modules:cache` roda THEN o spike SHALL registrar o caminho absoluto do arquivo gravado
4. WHEN o cache de módulos é redirecionado só por configuração ou variável de ambiente THEN o spike SHALL registrar `sim` com o nome exato da configuração e o `ls` do arquivo em `/tmp/laravel`, ou `não` com o `ls` de `bootstrap/cache` mostrando o arquivo lá

**Independent test:** o relatório da resposta cita o caminho e o `ls`.

### S3: rotas do módulo (P1)

**Acceptance Criteria**

5. WHEN o arquivo de rotas do `Payment` declara o prefixo `api` e o grupo `api` THEN `POST /api/orders/{order}/payment` SHALL responder 401 sem autenticação e 404 para um id desconhecido
6. WHEN o mesmo arquivo de rotas não declara prefixo nem grupo THEN `route:list` SHALL mostrar a URI `orders/{order}/payment` sem `api`, e `POST /api/orders/{order}/payment` SHALL responder 404
7. WHEN o arquivo declara o grupo `api` THEN o middleware de `statefulApi()` SHALL aparecer em `route:list -v` para essa rota

**Independent test:** `route:list` das duas variantes e os dois `postJson`.

### S4: migrations de vários caminhos (P1)

**Acceptance Criteria**

8. WHEN `migrate:fresh` roda no banco de testes com a migration de `payments` dentro do módulo THEN ele SHALL terminar com exit 0 e `migrate:status` SHALL listar os 14 nomes de arquivo de hoje, todos `Ran`, na mesma ordem de hoje
9. WHEN o banco já foi migrado com a migration no lugar antigo e o arquivo passa a existir só no módulo THEN `php artisan migrate` SHALL responder `Nothing to migrate.`
10. IF a migration de `payments` for movida para o módulo THEN a chave estrangeira `payments.order_id` SHALL existir em `information_schema` depois do `migrate:fresh`

**Independent test:** `migrate:fresh` e `migrate` em sequência, com `migrate:status` guardado.

### S5: factory (P2)

**Acceptance Criteria**

11. WHEN a `PaymentFactory` está em `database/factories` do módulo e o model mantém `#[UseFactory]` THEN `Payment::factory()->create()` SHALL gravar uma linha em `payments`

**Independent test:** um teste Pest isolado que cria o pagamento.

### S6: infraestrutura de checkout (P1)

**Acceptance Criteria**

12. WHEN `make test` roda no worktree depois do `composer install` THEN todo teste SHALL passar, menos os testes de arquitetura que leem `app/Modules` e que o spike lista pelo nome com a causa de cada falha; a contagem de testes SHALL ser a da linha de base medida na `main` antes do spike
13. WHEN `make pint` roda no worktree THEN ele SHALL terminar com exit 0
14. WHEN `composer install` roda num clone sem `vendor/` THEN `vendor/modules/payment` SHALL existir como symlink para `app-modules/payment`
15. IF `vendor/autoload.php` já existe e o symlink do módulo não THEN o spike SHALL registrar se o entrypoint do container `api` instala o pacote, com a saída do container

**Independent test:** `make test`, `make pint` e um clone novo com `composer install`.

### S7: a resposta entra no design (P1)

**Acceptance Criteria**

16. WHEN o spike termina THEN a seção `Library Spike` de `.design/modular-layout.md` SHALL ser substituída por cinco respostas, cada uma com o comando que a provou, o resultado e a Key decision ou slice que ela confirma ou muda
17. IF uma resposta mostra que a biblioteca não serve (cache impossível de redirecionar sem editar o pacote, rota sem como receber o grupo, ordem de migrations por módulo) THEN o spike SHALL marcar a Module Layout, a Module Routes e a Module Migrations como trocadas para a alternativa leve
18. WHEN o spike termina THEN o worktree e a branch `spike/hel-12-library` SHALL ser removidos e `git log main` SHALL ter o mesmo HEAD de antes

**Independent test:** `git worktree list` sem o worktree do spike e a seção do design reescrita.

## Out of scope

| Excluded | Why |
| --- | --- |
| mover qualquer módulo além do `Payment` | o spike para ao responder as cinco perguntas; mover os outros é a Module Layout |
| corrigir os testes de arquitetura que quebram | quebram porque leem `app/Modules`; reescrevê-los é a Module Layout |
| as camadas `Core`, `Persistence` e `Http` | decisão 3 do design, sem relação com a biblioteca |
| o módulo `Messaging` | slice própria |
| comparar desempenho | é a Load Baseline |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| módulo do spike | `Payment` | é o menor com model, migration, rota, factory e evento; o design já o nomeia | y |
| onde roda | worktree na branch `spike/hel-12-library`, descartada ao fim | a `main` e o banco de desenvolvimento ficam intactos | n |
| versão da biblioteca | a mais recente 3.x (3.0.2 em 2026-03-23) | é a que declara Laravel 13 | n |
| linha de base de testes | contagem de `make test` na `main` (HEAD f1dd960) antes do spike | critério 12 compara contra ela | n |
| profile | `light` | o `AGENTS.md` não declara outro | n |

**Open questions:**

| # | Kind | Question | Until answered |
| --- | --- | --- | --- |
| 1 | blocks | o Docker Desktop está parado neste computador (o socket não existe): ele precisa ser iniciado por você | todos os critérios, que rodam `composer`, `migrate` e `make test` em container |

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| documento `Library Spike` no design | estrutura, profundidade e o que o leitor faz depois | AC 16 |
| comandos `artisan` e `make` do spike | saída, flags e código de saída | n/a - são ferramentas do spike, a saída é guardada como evidência e nenhuma é publicada |

## Sources

- [HEL-12 no Linear](https://linear.app/helter/issue/HEL-12/implementar-um-padrao-de-modularidade-melhor-ao-sistema) - pede a biblioteca.
- [.design/modular-layout.md](../../../.design/modular-layout.md) - a slice Library Spike e as Key decisions 1, 4 e 5 que ela confirma ou troca.
