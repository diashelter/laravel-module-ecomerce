# AGENTS.md

Instruções para agentes de AI (e pessoas) que trabalham neste repositório.

Este projeto é a **Loja Demo**, um e-commerce de estudo com backend em Laravel 13 (API only) e frontend em Vue 3, executado via Docker. Os detalhes de stack, arquitetura e comandos estão no [README.md](README.md).

---

## Regra principal: sempre atualizar a documentação

**Toda mudança de código deve vir acompanhada da atualização da documentação afetada, na mesma entrega.**

Uma tarefa só está concluída quando a documentação descreve o código como ele ficou. Documentação desatualizada é tratada como bug.

### Quando atualizar

Atualize a documentação sempre que a mudança:

- Criar, renomear, mover ou remover classes, métodos públicos, arquivos ou pastas citados em algum documento.
- Alterar uma regra de negócio (disponibilidade, estoque, checkout, status do pedido, permissões etc.).
- Alterar endpoints, payloads, códigos HTTP ou mensagens de erro da API.
- Alterar comandos do `Makefile`, containers, variáveis de ambiente ou configurações (`config/shop.php`, `.env.example`).
- Alterar migrations, seeders ou o modelo de dados.
- Adicionar, remover ou mudar o escopo de testes.
- Resolver, alterar ou criar um item da análise de domínio.

### Onde atualizar

| Documento | O que mantém atualizado |
|---|---|
| [README.md](README.md) | Stack, arquitetura, estrutura de pastas, comandos, fluxos, regras de negócio, API, testes e decisões |
| [docs/domain-analysis.md](docs/domain-analysis.md) | Contextos delimitados, notas de coesão, problemas detectados e plano de evolução |
| `.env.example` e `backend/.env.example` | Variáveis de ambiente novas ou alteradas (o RabbitMQ: `RABBITMQ_*`) |
| Comentários no código | Docblocks que explicam o *porquê* de uma regra (em inglês) |

### Como atualizar

1. Antes de terminar, procure no repositório os nomes alterados (classes, métodos, rotas, comandos) e corrija cada menção desatualizada:

   ```bash
   grep -rn "NomeAntigo" README.md docs/ AGENTS.md
   ```

2. Na [análise de domínio](docs/domain-analysis.md):
   - Marque os problemas resolvidos com ✅, explique a solução aplicada e mantenha um link para o código novo.
   - Atualize as notas de coesão e a matriz entre contextos quando elas mudarem.
   - Marque o passo concluído no plano de evolução, com a data.
   - Atualize a linha "Última atualização" no topo.
3. Não deixe links quebrados: confira os caminhos e as âncoras de linha (`arquivo.php#L42`) depois de editar o código.
4. Se uma decisão desviar do que a documentação recomendava, registre o motivo (por exemplo, a troca de um nome sugerido).

---

## Convenções

- **Código em inglês:** classes, métodos, variáveis, arquivos técnicos, testes e mensagens técnicas.
- **Documentação em português do Brasil:** `.md`, guias, decisões e planos.
- **Textos de interface e mensagens de negócio da API** podem ser em português.
- **Módulos do backend:** cada módulo é um pacote Composer em `backend/app-modules/<módulo>` (namespace `Modules\<Módulo>`, descoberto pelo `internachi/modular`): Catalog, Inventory, Ordering, Payment, Fulfillment, Identity, Customers, Backoffice, os de infraestrutura Messaging (outbox, relay e transportes) e Shared (o kernel), e o de composição Integration (liga a porta de um módulo ao contrato de outro, ver abaixo). Todo código de negócio fica em `src/` do pacote. `php artisan make:module <nome>` cria um novo, e fora dele só mudam o `composer.json` e o `composer.lock` da raiz; depois de baixar o repositório ou de trocar de branch com módulos novos, rode `composer install` (o container `api` só instala quando falta o `vendor/autoload.php`). Antes de criar uma classe, decida a qual contexto ela pertence ([análise de domínio](docs/domain-analysis.md)). Não recrie pastas por camada na raiz de `app/`.
- **Camadas dentro de cada módulo** (ver o README): `src/` tem a fachada pública (`Contracts/`, `Events/`) e as camadas privadas `Core/` (`UseCases/`, `Services/`, `Consumers/`, `Jobs/`, `DTOs/`, `ValueObjects/`, `Enums/`, `Exceptions/`), `Persistence/` (`Models/`, `Repositories/`, `Queries/`) e `Http/` (controllers, requests, resources, regras e middleware), mais `Providers/`; a direção é `Http` → `Core` → `Persistence`, e `Core` e `Persistence` nunca usam o `Http` do próprio módulo (o `ModuleLayersTest` cobra). Só o `Payment` tem `Gateways/`, e só o `Messaging`, o `Catalog` e o `Inventory` têm `Console/` (os comandos `outbox:relay`..., `catalog:read-model-clear`, `catalog:republish` e `inventory:republish`). O `Shared` é um kernel e não tem camadas, e o `Integration` também não: tem o `Providers/` e uma pasta por módulo consumidor, com os adaptadores das portas dele. Dentro de cada camada: controllers finos, casos de uso em `UseCases/`, regras puras em `Services/` (sem banco, transação ou eventos), escrita e acesso a dados em `Repositories/`, que não usam os services do módulo, e leituras que servem uma rota ou um contrato em `Queries/`, que não usam os `Repositories` nem os `UseCases` do módulo (o `ModuleBoundariesTest` cobra).
- **Rotas e migrations de um módulo ficam no pacote.** As rotas da API ficam em `app-modules/<módulo>/routes/api.php`, dentro de `Route::prefix('api')->middleware('api')->group(...)` (a biblioteca só faz `require` do arquivo, então o prefixo e o grupo `api` são do módulo), e o arquivo só nomeia controllers do `Http\Controllers` do próprio módulo (o `ModuleRoutesTest` cobra). Não crie um arquivo de rotas da API na raiz do `backend/`. As migrations das tabelas do módulo ficam em `app-modules/<módulo>/database/migrations`; **nunca renomeie nem edite uma migration já executada**, porque a tabela `migrations` guarda o nome do arquivo (o `ModuleMigrationsTest` congela os 16 nomes), e uma migration nova precisa de um nome que ordene depois das tabelas que ela referencia. A única exceção é a da isolação dos módulos ([design](.design/module-isolation.md), Key decision 5): a migration de criação perde só a referência à tabela de outro módulo (coluna e índices ficam), e uma migration nova do mesmo módulo apaga a chave onde ela existir, para os bancos já migrados convergirem. Hoje isso vale para a `2026_10_06_000001_create_payments_table`, com a `2026_10_21_000001_drop_payments_order_id_foreign`. Ficam na raiz só `cache`, `jobs` e `features`, que são do framework.
- **Ligações explícitas:** um model novo declara a sua factory com `#[UseFactory]`; a factory fica em `database/factories` do módulo (namespace `Modules\<Módulo>\Database\Factories`) e declara `protected $model`. As convenções de namespace do Laravel (`App\Models`) não valem para os módulos.
- **Interfaces:** uma interface só existe como **fronteira entre módulos** (em `Contracts/`, a fachada que o `ModuleBoundariesTest` cobra) ou como **porta com uma segunda implementação ou um duplo de teste real** (`PaymentGateway`, `EventOutbox`, `EventTransport`, `ProductCatalog`). Uma interface nova fora desses dois casos é recusada na revisão: sem segunda implementação nem duplo de teste, a classe concreta serve. Nenhuma das 21 interfaces existentes saiu, porque cada uma cabe num dos casos (a `OrdersToPay` do Payment é uma porta de fronteira com um duplo nos testes do módulo).
- **Fronteiras entre contextos** (ver a [análise de domínio](docs/domain-analysis.md)):
  - **A facade de um módulo é o seu `Contracts/` mais os seus `Events/`.** Um módulo só usa outro por eles; as demais pastas de `src/` (`Core`, `Persistence`, `Http`, `Providers`, `Gateways`, `Console`, uma pasta nova...) são privadas, o que dentro de `Core` inclui `UseCases`, `Services`, `Jobs` e `Consumers`.
  - **De `DTOs`, `ValueObjects` e `Enums`, só o vocabulário é público:** as classes que os tipos nativos das assinaturas de `Contracts` e `Events` do próprio módulo alcançam (parâmetros, retornos e propriedades públicas, o construtor incluído), de forma transitiva. O `ModuleBoundariesTest` calcula isso a cada execução: para tornar um tipo público, ele precisa aparecer numa assinatura real de contrato ou evento do módulo dono (nunca crie um método só para isso), e um tipo citado só em docblock continua privado.
  - **O `Http` de cada módulo é privado.** Um módulo que precisa de uma regra de validação, de um trait de normalização ou de um resource de outro tem o seu adaptador no próprio `Http`, que só traduz o que o value object ou o enum decide, sem escrever limite, expressão regular ou lista de valores (o `HttpAdaptersTest` cobra os do Customers e do Fulfillment).
  - Contratos e eventos falam em **dados** (ids, value objects, enums), nunca em models ou coleções do Eloquent. Um contrato só existe para uma travessia real e leva o nome do papel de que o chamador precisa. Um contrato de leitura é implementado por uma consulta do módulo dono (`Queries/`), e um contrato que grava ou trava (`StockInitializer`, `StockReservation`) pelo repositório dele; os dois são ligados no service provider do dono. Quando o fornecedor já depende do consumidor, o consumidor define o contrato.
  - Fora do Identity, o cliente autenticado é um id (o `getAuthIdentifier()` do `Authenticatable` do framework).
  - **Nenhuma rota resolve registro.** Nem route model binding implícito, nem binding explícito de parâmetro. O controller recebe o parâmetro como `string $product`, `string $order` e assim por diante, e o id segue até quem o resolve: o use case (escrita) ou o repositório ou a consulta (leitura), e o contrato do dono quando o registro é de outro módulo. Use cases e repositórios recebem ids, nunca models vindos da rota. O corpo é validado antes da existência: corpo inválido é `422` para qualquer id, e corpo válido com id desconhecido é `404`. Uma exclusão ou um ajuste vai direto à escrita (ou à leitura com lock), sem ler o registro antes.
  - **O id é um UUID v7 em texto, na forma canônica** (minúsculas, com hífens), declarada uma vez em `Shared\Support\RecordId`. Todo parâmetro de rota de `/api` tem o `Route::pattern` dessa expressão no `register()` do service provider do módulo dono do parâmetro (no `boot()` ele não vale para as rotas de um módulo, que são criadas antes) (um id fora dela é `404` sem query), e todo id do corpo passa pela regra `CanonicalRecordId` com `bail` antes de qualquer `exists`: o PostgreSQL responde com erro, e não com "nenhuma linha", a um texto que não é UUID. Nenhum `int` chamado `id` ou terminado em `Id` existe em `Modules`.
  - **O dono entra na busca.** Nas rotas do cliente com registro (pedido, pagamento e endereço), o repositório busca com `customer_id` (`PayableOrders::findForPayment($customerId, $orderId)`). O registro de outro cliente responde `404`, com o corpo de um id desconhecido, antes de qualquer conferência de status. Não crie policy que só confira o dono nem responda `403` ao lado da busca: ela confirmaria a quem não é dono que o registro existe.
  - **O código do pedido (`code`) é só para pessoas lerem.** Nenhum contrato o recebe, nenhum evento o carrega e nenhuma URL o usa: URLs, contratos e eventos continuam com o id.
  - O `ModuleBoundariesTest` e o `RouteBindingTest` (`tests/Unit/Architecture`) cobram essas regras. O `RouteBindingTest` percorre `Route::getRoutes()` e falha se uma rota de `/api` tiver parâmetro de ação que implemente `UrlRoutable`, binding explícito ou um parâmetro sem o padrão canônico.
  - **Módulos isolados** (ver o [design](.design/module-isolation.md)): a direção é cada módulo de negócio rodar sem os outros. Um módulo isolado (hoje, só o Payment; a lista é `ModuleLayout::ISOLATED`) não nomeia outro módulo de negócio, e nenhum pacote além do `Integration` o nomeia (o `ModuleLayersTest` cobra). O que ele pede de fora é uma **porta no próprio `Contracts/`, no próprio vocabulário** (`OrdersToPay`, que devolve um `OrderToPay`), e o pacote `Integration` a liga ao contrato do dono com um adaptador (`Modules\Integration\<Consumidor>\`). Sem o dono instalado (o contrato dele não carrega), o `Integration` liga a porta a uma implementação que lança `ServiceUnavailableException`: a rota responde `503 SERVICE_UNAVAILABLE` sem gravar nada, nunca uma resposta parcial. Um evento de outro módulo é lido por uma **classe própria em `Core/InboundEvents`**, com o mesmo nome, a mesma versão e as mesmas chaves do payload, usada só pelos `Consumers` e pelo `Providers` do módulo: só o produtor grava o evento, e o `OrderEventPayloadTest` prova que cada leitora lê o que a produtora grava. Nenhum módulo aponta chave estrangeira nem lê a tabela de outro (as que restam saem com a slice de cada travessia).
  - **Não há exceção de fronteira além do `Integration`.** O `Integration` é o único pacote que usa mais de um módulo de negócio, e só pelas fachadas deles (o `ModuleBoundariesTest` o trata como mais uma origem das regras). O `ModuleBoundariesTest` não tem lista de travessias permitidas, e não se cria uma para fazer o teste passar. Uma leitura nova que junta módulos é uma **consulta** do módulo que serve a rota, que pede ao dono, pelo contrato dele, o que não é seu.
  - **Consultas** (HEL-6, ver o [design](.design/cqrs-read-side.md)): a pasta `Queries/` do módulo é privada. Uma consulta lê só as tabelas do próprio módulo, e as coleções do MongoDB dele (a vitrine lê o modelo de leitura `catalog_products` e `catalog_categories`); o que é de outro módulo ela pede ao dono pelo contrato dele, por ids e uma vez para a página inteira, para o tamanho da página nunca mudar o número de queries. Nenhuma assinatura pública de consulta declara model nem coleção do Eloquent. Nenhuma relação do Eloquent atravessa módulo. Um caso de uso de escrita devolve um value object com os dados do que gravou (`ProductRecord`, `StockRecord`), e a consulta completa a resposta sem reler o registro.
  - **A regra "pode ser comprado"** (produto ativo e com unidades) mora só no `ProductAvailability` (Catalog). O Ordering só monta as mensagens do carrinho e do checkout sobre ela; nenhuma outra classe combina o status com as unidades.
  - Fora do próprio estoque, use os contratos de `Modules\Inventory\Contracts` (`StockInitializer`, `StockLevels`, `StockMetrics`, `StockReservation`), nunca o `StockRepository`.
  - Só a parte de pedidos altera o status do pedido. Pagamento e entrega apenas publicam eventos (`PaymentApproved`, `OrderDelivered`).
- **Eventos pelo outbox transacional** (HEL-9, ver o README e o [design](.design/transactional-outbox.md)):
  - **Todo evento é gravado pelo `EventOutbox` (`Modules\Messaging\Contracts`), dentro da transação da mudança que ele anuncia.** Nunca dispare um evento pelo dispatcher do Laravel (o trait `Dispatchable` de eventos, a interface de despacho depois do commit, a facade `Event`, o helper `event()`) nem "atualize e depois dispare": gravar sem transação aberta lança `LogicException`, e o `DomainEventsTest` recusa o despacho direto em `Modules`. Um caso de uso sem estado próprio abre uma transação só para a mensagem, e um job que depende do evento entra na fila depois do commit dele.
  - **A tabela é `outbox_messages`**: `id` (UUID v7 da aplicação, também o id da mensagem), `name`, `version`, `payload` (`jsonb`), `request_id`, `occurred_at` e `published_at` (nulo enquanto pendente), sem chave estrangeira. As publicadas ficam 7 dias; as pendentes nunca são apagadas.
  - **Todo evento de módulo implementa `DomainEvent`**: o nome estável `<módulo>.<evento em snake_case>` (nunca a classe PHP), a versão (um inteiro a partir de 1), `toPayload()` com, por chave, nulo, um escalar ou uma **lista plana de escalares** (`category_ids`), em chaves snake_case (ids em texto, datas em `Y-m-d`, datas com hora em texto ISO 8601 com o fuso, `toIso8601String()`; objeto, array associativo e lista aninhada são recusados na gravação) e `fromPayload()`. Mudar um payload é subir a versão; o `DeliverEvent` recusa uma versão que a classe não declara.
  - **A entrega é ao menos uma vez, então todo consumidor (e todo job que anuncia um evento) declara a sua chave natural de idempotência e tem um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery`** (HEL-16, `ConsumerRedeliveryTest`). A chave é uma escrita condicional (`UPDATE ... WHERE status = :from`, a primeira gravação) ou uma linha única (`deliveries.order_id`, como o `payments`): **um produtor só grava o evento no outbox quando a escrita guardada mudou uma linha, na mesma transação**, e o trabalho seguinte (um job) é enfileirado depois do commit e é seguro de repetir. O `EventDelivery` não deduplica por id de mensagem e não há inbox; um consumidor sem chave natural pede um inbox como decisão nova, nunca como atalho.
  - **O relay publica pela porta `EventTransport`** (`publish(EventEnvelope)`: retornar é aceitar, lançar exceção é recusar). Casos de uso, eventos e consumidores não sabem qual é o transporte. O que o container entrega à porta é o `EventTransportSelector` (HEL-13), que lê a **flag `event-transport`** (Pennant, driver `database`; `redis`, o padrão, ou `rabbitmq`; `make event-transport [TO=redis|rabbitmq]`) e delega ao `QueueEventTransport` ou ao `RabbitMqEventTransport`; um transporte novo é uma implementação nova, escolhida ali. Nada lê a flag fora do seletor, e nada liga um transporte direto no container. O `RelayOutboxCommand` limpa o cache do Pennant a cada ciclo para obedecer a uma troca sem reiniciar.
  - **Todo consumidor tem um nome estável**, o terceiro argumento do `subscribe()`: `$subscriptions->subscribe(PaymentApproved::class, MarkOrderAsPaid::class, 'ordering.mark_order_as_paid')`. O nome segue `^[a-z]+\.[a-z_]+$`, pertence a uma só classe e uma classe tem um só nome (`LogicException` na inscrição), e nomeia a fila do consumidor no RabbitMQ (`<nome>` e `<nome>.dead`): nunca o derive da classe, porque a HEL-12 muda os namespaces e a fila ficaria órfã. Os argumentos de uma fila não mudam depois de declarada; mudar um pede apagar o volume do `rabbitmq` (ver o README).
  - **As regras de entrega moram numa classe só, o `EventDelivery`**, que o `DeliverEvent` (fila do Laravel) e o `events:consume` (RabbitMQ) chamam. Não as escreva duas vezes. O `events:consume` confirma depois do `handle()` e devolve com `basic_reject` e requeue (nunca `basic_nack`, que desde o RabbitMQ 4.3 não conta para o limite de entregas).
  - **O consumidor fica em `<módulo>/src/Core/Consumers`**, implementa `EventConsumer` e tem um único `handle()` que recebe a classe do evento. O módulo que consome o inscreve no `boot()` do seu service provider (`$subscriptions->subscribe(OrderPaid::class, ScheduleOrderDelivery::class, 'fulfillment.schedule_order_delivery')`), e o produtor nunca lista os consumidores. Cada consumidor recebe a sua entrega (`DeliverEvent`, 3 tentativas), ao menos uma vez: ele precisa ser idempotente.
  - **Eventos que alimentam um modelo de leitura levam o estado inteiro e a versão do registro** (HEL-17, ver o [design](.design/storefront-read-model.md)): `products`, `categories` e `stocks` têm a coluna `version` (`bigint`, só cresce); toda escrita que muda a vitrine trava a linha (`SELECT ... FOR UPDATE`), soma 1 na transação e grava o evento da linha travada, com a parte inteira que mudou (nunca uma diferença): `catalog.product_created`, `product_updated`, `product_status_changed`, `product_deleted`, `category_created`, `category_updated`, `category_deleted` e `inventory.stock_changed`. **O projetor** (os 8 consumidores `catalog.project_*`, os únicos que gravam em `catalog_products` e `catalog_categories`) aplica uma parte só quando a versão dela é maior que a guardada (a igual ou menor é entrega repetida e é descartada), e a exclusão deixa uma lápide (`deleted_version`): é a chave natural de idempotência dele. A vitrine decide a disponibilidade na leitura, só pelo `ProductAvailability`. `make storefront-rebuild` reconstrói o modelo de leitura (`catalog:read-model-clear`, `catalog:republish` e `inventory:republish`); o `api-read` serve a vitrine sem credencial do PostgreSQL, e o Nginx manda só o `GET` e o `HEAD` dela para ele.
  - O `request_id` da requisição vai na mensagem e volta ao `Context` do consumidor; não o passe à mão.
  - O `User` é identidade e não conhece pedidos. O lado de pedidos usa o `Customer`, que é somente leitura.
  - O Catalog não usa models nem repositories do Ordering. Quando precisar de algo dos pedidos, use o contrato que ele mesmo define (`ProductOrderHistory`), implementado pelo Ordering.
  - Os módulos `Shared` e `Messaging` são só infraestrutura e não dependem de nenhum módulo de negócio, e o `Shared` não depende do `Messaging`. Um módulo de negócio usa do `Messaging` só `DomainEvent`, `EventOutbox`, `EventConsumer` e `EventSubscriptions`.
  - O teste de arquitetura `ModuleBoundariesTest` cobra essas regras: a privacidade das pastas é gerada a partir das pastas que existem, a do vocabulário a partir das classes que existem e das assinaturas, e as regras de direção são escritas uma a uma. Ao criar uma regra nova, use **um namespace por expectativa**: com uma lista de namespaces, o `not->toUse` do Pest nunca falha. Confirme também que a regra falha com uma violação de propósito.
- **Testes de carga** (HEL-14, ver o [README da pasta](load-tests/README.md)): a pilha de carga é o projeto Compose `ecommerce-load` e compartilha com a de dev a árvore `backend/`. Nada rodado nos containers de carga pode gravar ali um arquivo que a API de dev lê: os caches ficam em `/tmp/laravel` (`APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, `APP_EVENTS_CACHE`, `APP_MODULES_CACHE`) e os logs vão para `stderr`. O `APP_MODULES_CACHE` é do projeto: a biblioteca de módulos não tem configuração para o caminho do cache, e o `AppServiceProvider` a religa a essa variável (sem ela, o caminho é `bootstrap/cache/app-modules.php`); sem o cache de módulos, cada requisição escaneia `app-modules`, e a vitrine passou de 25 ms para 273 ms de p95. O `make load-test` roda o `modules:cache` no container `api`. Nunca rode `php artisan optimize` nem `php artisan modules:cache` nesses containers sem a variável: o cache iria para `bootstrap/cache`, e a API de dev passaria a ler o banco de carga ou uma lista de módulos velha. Uma variável nova que separa as duas pilhas entra no `environment` do Compose de carga, porque a variável de ambiente prevalece sobre o `backend/.env`. O k6 só fala HTTP, como o SPA: nada de rota só de teste, acesso ao banco ou atalho de autenticação. O `make load-test TYPE=<perfil> TRANSPORT=<redis|rabbitmq>` grava a flag `event-transport` da pilha de carga antes do k6 (o padrão é `redis`), e a espera até pagar lê o pedido a cada 250 ms; a tabela da HEL-14 e os limites que saem dela não mudam, e os números por transporte entram na segunda tabela do `load-tests/README.md`.
- **Limites de requisição:** todo `throttle` de rota nomeia o seu contador no terceiro parâmetro (`throttle:10,1,customer-login`), com um nome que nenhuma outra rota usa. Sem o nome, a chave é só o cliente, e as rotas com limite dividiriam um contador (o `RateLimitTest` cobra). O alias `throttle` é o `AtomicThrottleRequests` (Shared), que conta a requisição antes de decidir, sobre o `AtomicRateLimiter`, que incrementa num só comando do Redis. Não volte ao `ThrottleRequests` do framework nem use o `throttleWithRedis()`: os dois conferem antes de contar, e uma rajada simultânea passa do limite (o `RateLimitBurstTest` cobra).
- **Testes no módulo** (ver o [design](.design/module-isolation.md)): os testes de um módulo moram em `app-modules/<módulo>/tests/Unit` (sem banco) e `tests/Feature` (com banco), no namespace `Modules\<Módulo>\Tests\` do `autoload` do pacote, e usam só aquele módulo, o `Shared` e o `Messaging`: o que vem de outro módulo é um duplo da porta do próprio módulo, e o login é a identidade de teste do `Shared` (`signInAsCustomer()`), nunca um model do Identity. Os helpers de um módulo ficam no `tests/Helpers.php` dele, e o `tests/Pest.php` da raiz carrega todos. Um teste de módulo não nomeia outro módulo de negócio, o `Integration` nem o apoio da raiz além do `Tests\TestCase`, e não chama um helper do `tests/Pest.php` nem do `tests/Helpers.php` de outro módulo de negócio (o `ModuleLayersTest` cobra as duas coisas: os helpers são funções globais, que o `tests/Pest.php` carrega para todos os testes, e a chamada a um deles não nomeia classe nenhuma). Hoje têm testes no módulo o Identity, o Messaging, o Shared, o Catalog e o Payment; o Ordering, o Fulfillment e o Inventory esperam a slice da sua travessia. O `backend/tests` é a **suíte de composição**: fluxos entre módulos reais, arquitetura, compatibilidade dos eventos, adaptadores do `Integration` e seeders. Um teste que precisa de mais de um módulo fica nela.
- **Commits** em inglês, no formato `feat:`, `fix:`, `refactor:`, `test:`, `docs:`.

---

## Verificação antes de concluir

Tudo roda via Docker:

```bash
make test
```

```bash
make pint
```

O `make test` precisa do `rabbitmq` e do `mongodb` no ar (`make up`; o CI sobe o `mongodb` com `--wait` no job `backend`, e o `load-smoke` roda com o `mongodb` e o `api-read`): cada checkout testa num banco do MongoDB próprio (`MONGODB_DATABASE`, o nome do banco de testes), e os testes que leem a vitrine chamam `rebuildStorefront()` no preparo. Os testes de `app-modules/messaging/tests/Feature/Messaging`... os testes de `app-modules/messaging/tests/Feature/Messaging` e de `tests/Feature/Messaging` usam o broker de verdade, e sem ele falham dizendo `RabbitMQ ...`, nunca são pulados.

**Num `git worktree`**, o `make test` já roda o `backend/` do próprio worktree, num container `api` avulso, no banco `ecommerce_testing_<nome do worktree>` e no vhost do RabbitMQ de mesmo nome, criados na primeira execução; duas suítes em worktrees diferentes podem rodar ao mesmo tempo (ver [Um banco de testes por checkout](README.md#um-banco-de-testes-por-checkout)). O `make test-filter`, o `make pint`, o `make shell`, o `make artisan`, o `make test-frontend` e o `make frontend-shell` também abrem o código do worktree. Não rode esses comandos pelo `docker compose exec`: os containers de dev montam o `backend/` e o `frontend/` da pasta principal. O `make artisan` do worktree fala com o banco e o Redis de dev: um `migrate` ali altera o banco que a pasta principal serve.

Depois de alterar consumidores, jobs ou o relay do outbox, reinicie o Horizon, o relay e o consumidor do RabbitMQ:

```bash
make queue-restart
```

Depois de alterar uma rota, um status, um payload ou os scripts de `load-tests/`, rode o smoke de carga:

```bash
make load-test TYPE=smoke
```

O CI (`.github/workflows/ci.yml`) sobe `db`, `redis` e `rabbitmq` (com `--wait`), roda o `make pint`, o `make test` e o smoke de carga em todo pull request, com o mesmo Docker Compose do ambiente local. Rode os três localmente antes de abrir o PR: o CI só confirma.

### Checklist final

- [ ] Os testes passam (`make test`).
- [ ] O estilo está correto (`make pint`).
- [ ] O smoke de carga passa (`make load-test TYPE=smoke`), se a mudança tocou uma rota, um payload ou `load-tests/`.
- [ ] Se mudou o ambiente (Dockerfile, `docker-compose.yml`, `Makefile`, `.env.example`), o workflow de CI continua coerente com ele.
- [ ] O README descreve o comportamento atual.
- [ ] A análise de domínio reflete o estado atual dos contextos.
- [ ] Nenhuma menção a nomes antigos ficou para trás.
