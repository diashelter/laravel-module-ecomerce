# Module Layout

## Problem

Os 9 módulos de `backend/app/Modules` já têm fronteiras verificadas por teste (HEL-7, 10 e 6), mas continuam sendo pastas de um único pacote: criar um módulo exige editar à mão pelo menos cinco lugares globais (a lista de providers, o arquivo central de rotas, a pasta de migrations, a de factories e a lista de padrões de rota do `AppServiceProvider`). Dentro de cada módulo, 15 pastas por tipo técnico (`UseCases`, `Models`, `Http`, `ValueObjects`...) não dizem o que é regra de negócio, o que é banco e o que é HTTP, e o `Shared` tem 38 arquivos, 26 deles de mensageria (outbox, relay, transportes, RabbitMQ, 5 comandos). Cada feature seguinte continua tocando a raiz e engordando o `Shared`. A [HEL-12](https://linear.app/helter/issue/HEL-12/implementar-um-padrao-de-modularidade-melhor-ao-sistema) pede o padrão, e o [design](../../../.design/modular-layout.md), confirmado em 2026-10-19 com as cinco respostas do Library Spike, fixou como.

Quando isto entregar, um módulo é um pacote em `app-modules/` que o Laravel descobre sozinho, com a fachada pública (`Contracts`, `Events`) à vista na árvore e as camadas `Core`, `Persistence` e `Http` privadas; o `Messaging` é um módulo próprio e o `Shared` volta a ser um kernel pequeno. Rotas e migrations continuam centrais nesta entrega: são as slices Module Routes e Module Migrations.

## Flow

Reaproveita o `ModuleBoundariesTest` e os testes de arquitetura vizinhos (que passam a ler a árvore nova), os `ServiceProvider` de cada módulo, o `make:module` da biblioteca como molde do pacote, e o que o Library Spike provou com o `Payment`.

1. `composer` (exists) exige `internachi/modular` e o repositório `path` `app-modules/*` (door 1)
2. um script de movimentação (new, no door - ferramenta descartável, fora do repositório) move cada classe de `app/Modules/<Nome>` para `app-modules/<kebab>/src/{Contracts,Events,Core,Persistence,Http,Providers}` e reescreve o namespace e as referências em código, testes, configuração, seeders e factories (doors 2 e 3)
3. `Shared` (exists) se divide: `Messaging` (new, door 4) recebe o outbox, o relay, os transportes, o RabbitMQ, os 5 comandos e a tabela `outbox_messages`; o `Shared` fica com o kernel
4. o provider de cada módulo (exists), com os de `Shared` e `Messaging` (new, no door - placement per conventions) saindo do `AppServiceProvider`, é registrado pelo `composer.json` do pacote, e `bootstrap/providers.php` perde as 7 linhas
5. as factories vão com o model para o `database/factories` do módulo, e o `#[UseFactory]` do model aponta para o novo namespace
6. o `ModuleBoundariesTest` (exists) lê `app-modules/*/src`, ganha o `Messaging` na lista e a regra de camada nova
7. out: o `README`, o `AGENTS.md` e a `docs/domain-analysis.md` descrevem a árvore nova; `route:list`, `migrate:status` e a suíte dão o resultado de antes

## Impact

| Front | What changes |
| --- | --- |
| domain | termo novo: pacote de módulo - um módulo com `composer.json` próprio em `app-modules/<kebab>`; quem ramifica nele: `bootstrap/providers.php`, `composer.json`, o `ModuleBoundariesTest` |
| domain | termo novo: `Messaging` - o módulo de infraestrutura do outbox, relay, transportes e comandos de evento, em `Modules\Messaging` |
| domain | termo existente: `Shared` significava "kernel e mensageria", passa a significar só o kernel (erros, classes base, `RecordId`, limitador de requisições, `Http` base); quem ramifica nele: 5 módulos que importam `BaseRepository`, os controllers e requests que herdam das bases, e `bootstrap/app.php` |
| domain | termo existente: `App\Modules\<Nome>` passa a `Modules\<Nome>`; quem ramifica nele: 110 arquivos de teste, `config/auth.php`, `bootstrap/app.php`, `routes/api.php`, os seeders e as factories |
| domain | termo existente: `EventSubscriptions` era uma classe do `Shared` e passa a ser uma interface do `Messaging`; quem ramifica nele: os providers do Ordering e do Fulfillment, que inscrevem consumidores |
| domain | termo novo: as camadas `Core`, `Persistence` e `Http` de cada módulo, com `Contracts` e `Events` como fachada pública |
| stored data | nada a migrar: nenhuma tabela, coluna ou nome de migration muda |
| stored data | a fila do Redis: jobs `DeliverEvent` já enfileirados guardam a classe do consumidor com o namespace antigo e não se desserializam depois da troca; a fila é esvaziada antes. O RabbitMQ não é afetado, porque as filas usam o nome estável do consumidor |

## Relations

`None - no stored-data shape change`

## Surface

`None - nothing consumed outside`

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| a dependência e o repositório `path` | `"internachi/modular": "^3.0"` em `require` e `"repositories": [{"type": "path", "url": "app-modules/*", "options": {"symlink": true}}]` | manter `App\Modules` com providers próprios carregando rotas e migrations: ganha se o `composer install` por checkout passar a custar mais que a descoberta automática; descartado porque a HEL-12 pede a biblioteca e o spike provou que serve |
| o namespace e o caminho de cada módulo | `Modules\<Nome>` em `app-modules/<nome-em-kebab>/src`, pacote `modules/<nome-em-kebab>` (`Modules\Ordering`, `app-modules/ordering`) | `App\Modules` com a biblioteca apontada para `app/Modules`: o psr-4 `App\` da raiz ficaria sobreposto ao do módulo e o `AGENTS.md` do diretório pai deixaria de valer sozinho |
| a fachada pública é o primeiro nível de `src` | `src/Contracts` (só interfaces) e `src/Events`; tudo o mais em `src` é privado, e o vocabulário alcançado pelas assinaturas continua calculado | um atributo nas classes públicas ou uma subpasta de vocabulário em `Contracts`: já descartados na HEL-10, porque movem cerca de 20 classes e criam uma segunda fonte para a mesma informação |
| as pastas de `src` | `Contracts`, `Events`, `Core` (`UseCases`, `Services`, `Consumers`, `Jobs`, `DTOs`, `ValueObjects`, `Enums`, `Exceptions`), `Persistence` (`Models`, `Repositories`, `Queries`), `Http` (`Controllers`, `Requests`, `Resources`, `Rules`, `Middleware`), `Providers`; mais `Gateways` só no Payment e `Console` só no Messaging | `Queries` numa pasta de leitura própria: ganha se uma consulta deixar de tocar o banco; a do Backoffice já só lê contratos e fica em `Persistence` mesmo assim, por uniformidade |
| a fachada do `Messaging` | `Modules\Messaging\Contracts\{DomainEvent, EventOutbox, EventConsumer, EventSubscriptions}`, as quatro interfaces; a implementação do registro de inscrições passa a se chamar `EventSubscriptionRegistry` e é privada | deixar o registro como classe concreta pública: o teste "só interfaces em `Contracts`" a recusaria, e um módulo de negócio passaria a depender da implementação |
| o manifesto do pacote | `composer.json` com psr-4 `Modules\<Nome>\` para `src/` e `Modules\<Nome>\Database\Factories\` para `database/factories/`, e `extra.laravel.providers` apontando para `Modules\<Nome>\Providers\<Nome>ServiceProvider` | manter os psr-4 `Tests` e `Seeders` do gerador: apontam para pastas vazias e seriam uma segunda convenção de teste e seed |

- Nada mais nesta entrega é difícil de reverter

## Criteria

### S1: os módulos são pacotes que o Laravel descobre (P1)

Dez pacotes em `app-modules`, nenhuma pasta em `app/Modules`, nenhum módulo listado à mão na raiz.

**Acceptance Criteria**

1. WHEN `composer install` roda num clone novo THEN `php artisan modules:list` SHALL listar exatamente os 10 módulos `backoffice`, `catalog`, `customers`, `fulfillment`, `identity`, `inventory`, `messaging`, `ordering`, `payment` e `shared`, cada um com o namespace `Modules\<Nome>`
2. The system SHALL NOT conter o diretório `backend/app/Modules` nem a cadeia `App\Modules` em nenhum arquivo PHP, JSON, XML ou YAML de `backend/`, `load-tests/` e `.github/`
3. The system SHALL NOT listar nenhum provider de módulo em `backend/bootstrap/providers.php`, que mantém só o `AppServiceProvider` e o `HorizonServiceProvider`
4. WHEN `php artisan make:module sample` roda numa cópia descartável THEN os únicos arquivos rastreados fora de `app-modules/sample` que mudam SHALL ser `backend/composer.json` e `backend/composer.lock`

**Independent test:** clone novo, `composer install`, `modules:list` mostra os 10; `grep -r 'App\\Modules' backend` não acha nada.

### S2: a árvore e a visibilidade (P1)

**Acceptance Criteria**

5. The system SHALL manter toda classe de um módulo em exatamente uma destas pastas de `src`: `Contracts`, `Events`, `Core`, `Persistence`, `Http`, `Providers`, mais `Gateways` no `payment` e `Console` no `messaging`
6. IF um módulo usa uma classe de outro módulo que não está em `Contracts`, em `Events` nem no vocabulário alcançado pelas assinaturas de `Contracts` e `Events` do módulo dono THEN o `ModuleBoundariesTest` SHALL falhar
7. IF uma classe de `Core` ou de `Persistence` usa uma classe de `Http` do mesmo módulo THEN o `ModuleBoundariesTest` SHALL falhar
8. IF um repositório usa um serviço do módulo, ou uma consulta usa um repositório ou um caso de uso do módulo THEN o `ModuleBoundariesTest` SHALL falhar
9. IF `Shared` ou `Messaging` usa um módulo de negócio, ou `Shared` usa o `Messaging` THEN o `ModuleBoundariesTest` SHALL falhar
10. WHEN uma regra de arquitetura nova é escrita THEN ela SHALL ter uma violação de propósito que a faz falhar, registrada como teste ou fixture

**Independent test:** introduzir de propósito um import proibido por regra e ver o teste correspondente falhar.

### S3: o Messaging é um módulo (P1)

**Acceptance Criteria**

11. The system SHALL expor como fachada do `Messaging` exatamente as interfaces `DomainEvent`, `EventOutbox`, `EventConsumer` e `EventSubscriptions` em `Modules\Messaging\Contracts`, e nenhum módulo de negócio SHALL usar outra classe do `Messaging`
12. WHEN `php artisan list` roda THEN os comandos `outbox:relay`, `events:transport`, `events:consume`, `events:failed` e `events:retry` SHALL estar registrados, e o `AppServiceProvider` SHALL NOT mais registrá-los nem ligar `EventOutbox`, `EventTransport` ou o seletor
13. The system SHALL manter inalterados o nome de cada evento, o nome de cada consumidor e o nome de cada fila, provados pelos testes de nomes e de envelope que já existem

**Independent test:** `events:transport` mostra o transporte e os testes de `Outbox` e `Messaging` passam.

### S4: o comportamento não mudou (P1)

**Acceptance Criteria**

14. WHEN `make test` roda THEN nenhum teste SHALL falhar, e todo arquivo de teste fora de `tests/Unit/Architecture` SHALL ter a mesma contagem de testes da linha de base de `main` (HEAD f1dd960, 1928 testes)
15. WHEN `php artisan route:list --json` roda THEN a lista de URIs, métodos, nomes e middleware SHALL ser igual à de `main`, com as classes de ação diferindo só pelo prefixo `App\Modules` → `Modules`
16. WHEN `php artisan migrate:status` roda num banco de desenvolvimento já migrado THEN ele SHALL listar os mesmos 14 nomes como `Ran`, e `php artisan migrate` SHALL responder `Nothing to migrate.`
17. WHEN `make pint` roda THEN ele SHALL terminar com exit 0
18. WHEN `make load-test TYPE=smoke` roda THEN ele SHALL terminar com exit 0, e os containers de carga SHALL NOT criar nem alterar nenhum arquivo em `backend/bootstrap/cache`

**Independent test:** a suíte, o `route:list` e o `migrate:status` antes e depois, lado a lado.

### S5: a documentação descreve o código como ficou (P1)

**Acceptance Criteria**

19. The system SHALL NOT conter `backend/app/Modules` nem `App\Modules` no `README.md`, no `AGENTS.md` e em `docs/domain-analysis.md`, e todo link relativo desses três arquivos SHALL apontar para um arquivo que existe
20. The `AGENTS.md` SHALL declarar o critério de interface (só como fronteira entre módulos em `Contracts`, ou porta com segunda implementação ou duplo de teste real) e a árvore `Contracts`, `Events`, `Core`, `Persistence`, `Http` com a direção `Http` → `Core` → `Persistence`
21. WHEN a movimentação termina THEN a `docs/domain-analysis.md` SHALL listar os 10 módulos, mostrar o `Messaging` e o `Shared` novos, marcar o passo concluído com a data e trazer a nota de deploy sobre a fila do Redis

**Independent test:** `grep` sem achados e um verificador de links.

## Out of scope

| Excluded | Why |
| --- | --- |
| rotas por módulo e o fim do arquivo central de rotas | é a slice Module Routes; o `routes/api.php` fica, importando as classes novas |
| migrations por módulo | é a slice Module Migrations; os 14 arquivos ficam onde estão |
| a comparação de carga antes e depois | é a slice Load Baseline; aqui só o smoke precisa passar |
| fundir módulos | nenhum par passou no teste dos seis critérios (Module Map) |
| trocar a projeção `Customer` do Ordering por um contrato | acoplamento intrusivo conhecido, mudança própria |
| mover testes para dentro dos módulos | o Pest separa por tipo |
| instalar o pacote sozinho no entrypoint do container | o spike mostrou que ele não instala; a documentação manda rodar `composer install` |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| factories vão com o model, seeders ficam na raiz, testes ficam em `backend/tests`, `Queries` em `Persistence`, `Gateways` só no Payment | como no design | confirmadas no design em 2026-10-19 | y |
| camadas no `Shared` | o `Shared` não ganha `Core`, `Persistence` e `Http`: é um kernel, e mantém `Http`, `Cache`, `Enums`, `Exceptions`, `Repositories` e `Support` | as camadas separam regra, banco e HTTP de um contexto; o kernel não tem regra de negócio | n |
| camadas no `Messaging` | `Contracts`, `Core` (entrega, envelope, relay, transportes e RabbitMQ), `Persistence` (`OutboxMessage` e o repositório), `Console` (os 5 comandos), `Providers` | o comando é ponto de entrada como o `Http`, mas não é HTTP | n |
| provider de `Shared` e de `Messaging` | cada um ganha o seu `ServiceProvider`, e o `AppServiceProvider` fica só com `preventLazyLoading` e os padrões de rota | o design pede provider por módulo | n |
| padrões de UUID das rotas | continuam no `AppServiceProvider` até a Module Routes, mas no `register()` | o spike mostrou que no `boot()` não valem para rotas de módulo; no `register()` valem para as duas fases | n |
| o script de movimentação | fica fora do repositório (descartável), com o tokenizer do PHP para reescrever nomes e a lista de destino por classe | a HEL-7 fez assim e o script não tem uso depois da troca | n |
| `phpunit.xml` | não usa `modules:sync` nem inclui `app-modules` na cobertura | o Pest já descobre os testes em `backend/tests` | n |
| nome do pacote e o gerador | `modules/<kebab>`, psr-4 conforme a Landing, tabulação do gerador corrigida pelo Pint | padrão do `make:module` | n |
| profile | recomendo `standard` para esta entrega | 260 arquivos movidos e um teste de arquitetura reescrito: o `light` não injeta falhas e não percebe um teste que passa sob uma implementação errada | n |

**Open questions:** none - all resolved or logged above.

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| all `/api` routes | error shape, versioning, rate limits | existing - nada muda, provado pela igualdade do `route:list` e pela suíte inteira (AC 14 e 15) |
| comandos `artisan` (`outbox:relay`, `events:*`) | flags, saída e códigos de saída | existing - os mesmos comandos, com os mesmos nomes e testes (AC 12 e 13) |
| comando `make:module` | saída e o que ele edita | AC 4 |
| documentos `README.md`, `AGENTS.md`, `docs/domain-analysis.md` | estrutura, o que o leitor faz depois, links | AC 19, 20 e 21 |
| mensagem de falha dos testes de arquitetura | diz qual regra e qual classe violou | existing - o formato das expectativas do Pest (AC 6 a 9) |

## Sources

- [HEL-12 no Linear](https://linear.app/helter/issue/HEL-12/implementar-um-padrao-de-modularidade-melhor-ao-sistema) - pede camadas, provider por módulo, a biblioteca e o critério de interfaces.
- [.design/modular-layout.md](../../../.design/modular-layout.md) - Key decisions 1, 2, 3, 6 e 7 e as slices Module Layout e Messaging, confirmadas em 2026-10-19, com as respostas do Library Spike.
- [.specs/features/library-spike/verification.md](../library-spike/verification.md) - o que a biblioteca fez no Payment: provider em `src/Providers`, padrões no `register()`, `composer install` manual.
