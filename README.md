# Loja Demo — e-commerce de estudo (Laravel 13 + Vue 3)

Aplicação de e-commerce **pequena, mas tecnicamente completa**, criada para estudo e testes técnicos. O foco não é ter muitas funcionalidades, e sim implementar corretamente conceitos importantes de engenharia de software:

- API REST com Laravel (Form Requests, API Resources, Middleware)
- Autenticação SPA com **Laravel Sanctum** (cookie HTTP-only, sem token no `localStorage`)
- Contas separadas para a **equipe** e os **clientes**, um guard de sessão por área e papéis `admin` / `support` na equipe
- Relacionamentos **1:1** (`Product` ↔ `Stock`) e **N:N** (`Product` ↔ `Category`)
- Carrinho com **Pinia** e checkout validado no backend
- **Transação atômica** + `lockForUpdate()` para controle de **concorrência** de estoque
- **Eventos pelo outbox transacional**, consumidores idempotentes e jobs assíncronos, com o transporte escolhido por **feature flag**: fila **Redis** (Horizon) ou **RabbitMQ**
- **CQRS**: a vitrine é um **modelo de leitura no MongoDB**, projetado pelos eventos e servido por um processo próprio (`api-read`)
- **Monólito modular**: um pacote Composer por contexto de negócio, com as fronteiras verificadas por testes de arquitetura
- Endereços de entrega, **frete por UF** calculado pelo servidor e **data prevista** de entrega
- Dashboard administrativo com dados agregados no backend
- Limite de requisições por rota, contado de forma **atômica** no Redis
- Testes automatizados com **Pest** e testes de carga com **k6**
- Ambiente 100% **Docker** e automação com **Makefile**
- Dados de demonstração com **Factories, Seeders e Faker**

A história de como a plataforma chegou até aqui, entrega por entrega, com o problema que cada uma resolveu, as alternativas e um roteiro de escala e disponibilidade, está no guia [Evolução da plataforma](docs/platform-evolution.md).

## Documentos do projeto

| Documento | O que tem |
| --- | --- |
| Este README | Stack, arquitetura, comandos, fluxos, regras de negócio, API, testes e decisões |
| [docs/platform-evolution.md](docs/platform-evolution.md) | O guia de estudo da evolução: a linha do tempo dos commits, o problema e a solução de cada entrega, exemplos, alternativas com vantagens e desvantagens, os sinais de que a arquitetura precisa evoluir e o roteiro de escala e disponibilidade |
| [docs/domain-analysis.md](docs/domain-analysis.md) | Os contextos delimitados (DDD), a coesão, os problemas detectados e o plano de evolução |
| [`.design/`](.design/) | Um design por entrega: situação, problema, decisões-chave e as alternativas descartadas |
| [`.specs/features/`](.specs/features/) | O plano, os critérios, as provas e a verificação independente de cada entrega |
| [load-tests/README.md](load-tests/README.md) | Os testes de carga com k6, os perfis e a linha de base |
| [AGENTS.md](AGENTS.md) | As regras para quem altera o código (pessoas e agentes) |

---

## Sumário

1. [Stack](#stack)
2. [Arquitetura](#arquitetura)
3. [Estrutura de pastas](#estrutura-de-pastas)
4. [Pré-requisitos](#pré-requisitos)
5. [Início rápido](#início-rápido)
6. [Configuração do `.env`](#configuração-do-env)
7. [Comandos do Makefile](#comandos-do-makefile)
8. [Banco de dados, migrations, seeders e Faker](#banco-de-dados-migrations-seeders-e-faker)
9. [Usuários de teste e dados disponíveis](#usuários-de-teste-e-dados-disponíveis)
10. [Fluxo de checkout](#fluxo-de-checkout)
11. [Transação e concorrência de estoque](#transação-e-concorrência-de-estoque)
12. [O modelo de leitura da vitrine](#o-modelo-de-leitura-da-vitrine) e [Eventos, outbox, consumidores e filas](#eventos-outbox-consumidores-e-filas)
13. [Regra de disponibilidade do produto](#regra-de-disponibilidade-do-produto)
14. [API REST](#api-rest)
15. [Frontend](#frontend)
16. [Testes](#testes)
17. [Testes de carga (k6)](#testes-de-carga-k6)
18. [Containers](#containers)
19. [Fluxo completo para demonstração](#fluxo-completo-para-demonstração)
20. [Escopo e decisões](#escopo-e-decisões)

---

## Stack

| Camada | Tecnologias |
| --- | --- |
| Backend | PHP 8.5, Laravel 13, Laravel Sanctum 4, PestPHP 4 |
| Banco / cache / fila | PostgreSQL 18 (a escrita), MongoDB 8.2 (o modelo de leitura da vitrine), Redis 8, Laravel Queue (driver `redis`) sob o Laravel Horizon, RabbitMQ 4.3 (php-amqplib) e feature flags com o Laravel Pennant |
| Frontend | Vue.js 3, Pinia 4, Vue Router 5, Axios, Vite 8, TypeScript, Tailwind CSS 4, Chart.js |
| Infraestrutura | Docker, Docker Compose, Nginx, Node.js 24 LTS, Makefile |

---

## Arquitetura

```
                      Navegador  →  http://localhost:8080
                            │
                     ┌──────▼──────────────────┐
                     │          nginx          │  ponto único de entrada (o gateway)
                     └──┬─────────┬─────────┬──┘
 /api/*, /sanctum/*,    │         │         │  GET e HEAD de /api/products
 /horizon/*             │         │         │  e de /api/products/{product}
                        │         │         └───────────────────────────────────┐
                        │         │ /* (todo o resto)                           │
                 ┌──────▼─────┐ ┌─▼──────────┐                           ┌──────▼──────┐
                 │ api        │ │ frontend   │                           │  api-read   │  a vitrine, sem
                 │ PHP-FPM    │ │ Vite (Vue) │                           │  PHP-FPM    │  credencial do PostgreSQL
                 └──┬──────┬──┘ └────────────┘                           └──────┬──────┘
                    │      │                                                    │
            ┌───────▼┐   ┌─▼──────┐      ┌──────────────┐                ┌──────▼──────┐
            │   db   │   │ redis  │◄─────┤ queue-worker ├───────────────►│   mongodb   │  modelo de leitura
            │ PG 18  │   │ fila / │      │ php artisan  │                │  catalog_   │  da vitrine
            │escrita │   │ sessão │      │   horizon    │                │  products,  │
            └▲───▲───┘   └───▲────┘      └──────┬───────┘                │  catalog_   │
             │   │           │                  │                        │  categories │
             │   │           │                  │                        └──────▲──────┘
             │   └───────────┼──────────────────┘                               │
             │           ┌───┴──────────┐   flag event-transport = rabbitmq     │
             └───────────┤ outbox-relay │ ─────────────────────────┐            │
                         │ php artisan  │                          │            │
                         │ outbox:relay │ ◄── flag = redis: fila   │            │
                         └──────────────┘     do Laravel (redis)   │            │
                                                            ┌──────▼─────┐   ┌──┴─────────────┐
                                                            │  rabbitmq  │◄──┤ event-consumer │  php artisan events:consume
                                                            │ domain_    │   │ (mesma imagem  │
                                                            │ events     │   │   da api)      │
                                                            └────────────┘   └────────────────┘
 scheduler: php artisan schedule:work  (horizon:snapshot a cada 5 minutos)
 o projetor da vitrine (os 8 consumidores catalog.project_*) roda no queue-worker (flag redis)
 ou no event-consumer (flag rabbitmq) e é o único que grava no mongodb
```

- **Frontend e backend no mesmo host** (`localhost:8080`): o Nginx encaminha o `GET` e o `HEAD` da vitrine (`/api/products` e `/api/products/{product}`) para o `api-read`, o resto de `/api/*`, `/sanctum/*` e `/horizon/*` (o painel da fila) para o `api` e o restante para o Vite. Isso permite autenticação por cookie de sessão sem CORS.
- **Backend em Laravel organizado em módulos** (`app-modules/<módulo>`, um pacote Composer por contexto de negócio, descoberto pelo `internachi/modular`). Continua sendo **um monólito**, com um único deploy e sem microsserviços: o `api` e o `api-read` são o mesmo código em dois processos. A escrita tem um banco só, o PostgreSQL. A separação entre leitura e escrita tem dois níveis: as leituras que cruzam módulos são consultas (camada `Queries/`, abaixo), lidas do PostgreSQL e sempre exatas; e a vitrine é um **modelo de leitura projetado** pelos eventos no MongoDB, servido pelo `api-read` com consistência eventual (HEL-17, ver [O modelo de leitura da vitrine](#o-modelo-de-leitura-da-vitrine)). Os contextos e as fronteiras entre eles estão em [docs/domain-analysis.md](docs/domain-analysis.md).
- **A facade de cada módulo é o seu `Contracts` mais os seus `Events`** (HEL-7, design em [.design/module-facades.md](.design/module-facades.md)). Um módulo usa outro só por interfaces de `Contracts/`, injetadas no construtor e ligadas, no service provider do módulo dono, a uma consulta dele (um contrato que só lê) ou a um repositório dele (um contrato que grava ou trava), e pelos eventos dele. Contratos e eventos falam em dados (ids, value objects, enums), nunca em models do Eloquent. Todas as outras pastas são privadas, inclusive uma pasta nova e o `Http`. De `DTOs`, `ValueObjects` e `Enums`, só é público o **vocabulário** do módulo (HEL-10, design em [.design/module-vocabulary.md](.design/module-vocabulary.md)): as classes dele que os tipos nativos das assinaturas dos seus `Contracts` e `Events` alcançam (parâmetros, retornos e propriedades públicas, o construtor incluído), de forma transitiva. O vocabulário é calculado a cada execução do teste, sem lista guardada: um tipo citado só em docblock não conta, e o contrato de um módulo não torna público um tipo interno de outro. Desde a HEL-6 o teste não declara nenhuma exceção.
- **Dentro de cada módulo, as mesmas camadas**: validação em Form Requests e respostas com API Resources.
  - **Controllers** (`Http/Controllers`): só HTTP (request, autorização, resposta). Leituras simples, que não cruzam módulo, chamam um repository direto.
  - **Casos de uso** (`UseCases/`): cada um representa uma intenção do usuário ou do sistema (ex.: `PlaceOrderUseCase`) e orquestra o fluxo: transação, repositories, services, eventos e jobs.
  - **Services** (`Services/`): regras de negócio **puras**, sem acesso a banco, transação ou eventos.
  - **Repositories** (`Repositories/`): a camada que grava no banco e faz as leituras que não cruzam módulo. Não usa os services do próprio módulo: as regras são aplicadas antes de chamá-lo (o `ModuleBoundariesTest` cobra).
  - **Consultas** (`Queries/`, HEL-6, design em [.design/cqrs-read-side.md](.design/cqrs-read-side.md)): a classe de leitura do módulo que serve uma rota ou responde um contrato que só lê. Devolve value objects, listas tipadas ou um `LengthAwarePaginator` deles, nunca um model nem uma coleção do Eloquent, e nunca grava. Lê só as tabelas do próprio módulo; o que é de outro módulo ela pede ao dono, pelo contrato dele, por ids e uma vez para a página inteira, então o tamanho da página não muda o número de queries. Não usa os `Repositories` nem os `UseCases` do próprio módulo (o `ModuleBoundariesTest` cobra). O caso de uso de escrita devolve os dados do que gravou (`ProductRecord` e `StockRecord`, privados), e a consulta completa a resposta sem reler o registro. Existem no Catalog (`ProductQuery`, `ProductCatalogQuery`, `CatalogMetricsQuery`, e, desde a HEL-17, a `StorefrontQuery`, que lê a vitrine do MongoDB, e a `RepublicationQuery`, que lê o que a reconstrução republica), no Inventory (`StockLevelsQuery`, `StockListQuery`, `StockMetricsQuery`), no Identity (`CustomerMetricsQuery`), no Ordering (`OrderMetricsQuery`) e no Backoffice (`DashboardQuery`); as outras leituras (pedidos, clientes, endereços, categorias, pagamento) ficam nos repositories até serem tocadas.
  - **Value objects** (`ValueObjects/`): conceitos com invariante própria, `final readonly`, que lançam `InvalidArgumentException` se construídos com um valor inválido. Hoje existem no Identity: `Email` e `Password`. Os Form Requests os usam pelas regras `EmailRule` e `PasswordRule`, que cada módulo com formulário de conta tem no seu `Http/Rules` (Identity e Customers, porque o `Http` de um módulo é privado) e que só traduzem o que o value object decide, e eles viram `string` só na gravação, no `toArray()` do `CreateUserDTO` e do `UpdateUserProfileDTO` (este deixa a senha de fora quando nenhuma nova é enviada).
  - **Listas tipadas** (também em `ValueObjects/`): as listas de domínio que atravessam camadas não são `array`. Cada uma é `final readonly`, implementa `IteratorAggregate` e `Countable`, é construída com parâmetro variádico tipado (um elemento de outro tipo lança `TypeError`) e calcula os próprios totais. No Catalog: `ProductIds` e `CategoryIds` (ids no formato canônico do UUID e sem repetição, senão `InvalidArgumentException`) e `CatalogProducts`/`CatalogProduct` (o que o contrato `ProductCatalog` devolve: id, nome, imagem, preço e o `ProductStatus`, com a `availability()` do produto) e `ProductCountsByStatus` (o que o `CatalogMetrics` devolve, com zero para o status sem produtos). O `ProductAvailability` é a regra "pode ser comprado", e `ProductView`, `ProductCategories`/`ProductCategory` e `ProductRecord` são o que a consulta do Catalog monta para as telas de produto. No Inventory: `StockQuantities` (quantidade por produto, 0 para produto sem linha de estoque), `ProductStocks`/`ProductStock` (o id da linha de estoque e a quantidade de cada produto, `null` para o produto sem linha) e `StockTotals`; `StockView` e `StockRecord` são o que a lista de estoque monta. No Ordering: `ProductQuantities` (quantidade por produto, em ordem de `product_id`), `OrderLines`/`OrderLine` (linhas do pedido novo e o total derivado), `ValidatedCart`/`ValidatedCartLine` (resultado da validação do carrinho, com `total_cents` e `is_valid` derivados), `CustomerIds`, `OrderCountsByCustomer` (responde zero para cliente sem pedidos) e `OrderCountsByPeriod` (a contagem por dia ou mês do `OrderMetrics`, zero para o período sem pedidos). Também no Ordering, `DeliveryAddress` (a cópia de um endereço que o pedido guarda) e `ShippingQuote` (preço e prazo de uma UF) são o vocabulário dos contratos `DeliveryAddressBook` e `ShippingQuoter`, `OrderForPayment` é o que o `PayableOrders` devolve e `OrderSummaries`/`OrderSummary` (id, status, total e data) são o histórico que o `CustomerOrderHistory` devolve. No Identity, `CustomerProfile` (a conta sem credenciais) é o que o `CustomerAccounts` devolve. O `CartDTO` é a lista tipada de `CartItemDTO`. O `array` só aparece onde o Laravel o exige: `whereIn`, `sync`, `createMany` e o `ValidatedCartResource`.
- **Fronteiras verificadas por teste**: o `ModuleBoundariesTest` (teste de arquitetura do Pest) é uma lista do que é permitido. Ele gera uma expectativa para cada pasta privada de cada módulo (o `Http` incluído), a partir das pastas que existem, e uma para cada classe de `DTOs`, `ValueObjects` e `Enums` fora do vocabulário do módulo dono, a partir dos arquivos que existem, e falha se outro módulo usar uma delas ou a classe da raiz de um módulo (o service provider). Também confere que `Contracts` só tem interfaces, que nenhuma assinatura de contrato usa model ou coleção do Eloquent e que nenhum repository usa os services do próprio módulo, além das regras de direção entre módulos. Para as consultas, confere que nenhuma assinatura pública declara model nem coleção do Eloquent e que nenhuma usa os repositories nem os casos de uso do próprio módulo. Não há lista de exceções: o único pacote que usa mais de um módulo de negócio é o `Integration`, e o mesmo teste o segura nas fachadas deles.
- **Módulos isolados** (design em [.design/module-isolation.md](.design/module-isolation.md)): o objetivo é que cada módulo de negócio seja uma unidade completa, que roda sem os outros. O primeiro é o **Payment**: ele não nomeia nenhum outro módulo de negócio, e nenhum outro o nomeia. O que ele precisa do pedido é uma porta no próprio `Contracts`, no próprio vocabulário (`OrdersToPay`, que devolve um `OrderToPay` com o id, o id do cliente, o total em centavos e se o pedido aguarda pagamento). Quem liga essa porta ao `PayableOrders` do Ordering é o pacote de composição **`Integration`** (`app-modules/integration`), com um adaptador por porta. Sem o Ordering instalado, o `Integration` liga a porta a uma implementação que responde `503` `SERVICE_UNAVAILABLE` antes de cobrar ou gravar qualquer coisa. O Ordering lê o `payment.payment_approved` com uma classe própria (`Core/InboundEvents/PaymentApproved`), usada só pelos consumidores e pelo provider: o formato publicado de um evento é o nome, a versão e as chaves do payload, e o `OrderEventPayloadTest` prova que cada leitora lê o que a produtora grava. O `ModuleLayersTest` cobra o isolamento (`ModuleLayout::ISOLATED`) e a prova de remoção roda os testes de um módulo numa cópia sem os outros (`.specs/features/module-isolation-pilot/proofs`).
- **Processamento assíncrono**: mudanças de status do pedido são feitas por consumidores e jobs executados pelo container `queue-worker`. Os eventos chegam a eles pelo **outbox transacional**: o caso de uso grava o evento na mesma transação da mudança, e o container `outbox-relay` o publica pelo transporte que a feature flag `event-transport` escolhe: a fila do Laravel (Redis, executada pelo `queue-worker` sob o Horizon) ou o RabbitMQ (lido pelo `event-consumer`). Ver [Eventos, outbox, consumidores e filas](#eventos-outbox-consumidores-e-filas).

---

## Estrutura de pastas

```
/
├── backend/                         Laravel 13 (API only)
│   ├── app/Providers/               AppServiceProvider (a trava de lazy loading) e HorizonServiceProvider (o painel `/horizon`, aberto só à equipe `admin`)
│   ├── app-modules/                 um pacote Composer por módulo, descoberto pelo `internachi/modular`; cada `src/` tem a fachada (Contracts/, Events/) e as camadas Core/, Persistence/ e Http/, mais Providers/; os testes de um módulo ficam no `tests/` dele; fora do `src/`, as rotas do módulo (`app-modules/<módulo>/routes/api.php`) e `database/` (as migrations e as factories das tabelas dele)
│   │   ├── catalog/             produtos e categorias (vitrine e cadastro); Queries/ (leituras que servem as rotas e os contratos, a vitrine lê o MongoDB); Persistence/ReadModel (a conexão com o MongoDB e a escrita condicional do projetor); Console/ (`catalog:read-model-clear` e `catalog:republish`); Core/Consumers (o projetor da vitrine); publica Contracts/ (ProductCatalog, CatalogMetrics) e define ProductOrderHistory; dono do ProductAvailability; CatalogServiceProvider
│   │   ├── inventory/           estoque; Console/ (`inventory:republish`); Queries/ (StockLevels, StockMetrics e a lista de estoque); publica Contracts/ (StockInitializer, StockLevels, StockMetrics, StockReservation) e InventoryServiceProvider
│   │   ├── messaging/           eventos: o outbox transacional, o relay, a porta EventTransport (fila do Laravel e RabbitMQ), a flag `event-transport` e os comandos outbox:relay, events:transport, events:consume, events:failed e events:retry; publica Contracts/ (DomainEvent, EventOutbox, EventConsumer, EventSubscriptions); MessagingServiceProvider
│   │   ├── ordering/            carrinho, checkout, pedidos, Customer (comprador, somente leitura) — núcleo do negócio; define Contracts/ (DeliveryAddressBook, ShippingQuoter), publica PayableOrders, CustomerOrderHistory e OrderMetrics (este por uma consulta), e guarda o vocabulário que eles trocam
│   │   ├── payment/             lê o pedido do cliente pela própria porta OrdersToPay, cobra pela porta PaymentGateway (gateway fake), grava as tentativas e publica PaymentApproved; isolado: não nomeia outro módulo de negócio
│   │   ├── fulfillment/         tabela de frete por UF (ShippingRateTable), calendário de dias úteis, entrega fake: agenda o DeliverOrder e publica DeliveryScheduled e OrderDelivered, cada um uma só vez por pedido pela linha de `deliveries` (Delivery, DeliveryRepository); FulfillmentServiceProvider
│   │   ├── identity/            as duas contas (User = equipe, CustomerAccount = comprador), papéis, logins (Sanctum, um guard por área), Email e Password (ValueObjects), EnsureUserIsAdmin; publica Contracts/ (CustomerAccounts, CustomerMetrics) e IdentityServiceProvider
│   │   ├── customers/           "Minha conta", o caderno de endereços (`customer_addresses`) e a tela Clientes do admin (CustomerSummaryDTO), pelas contas do CustomerAccounts e pelo histórico do CustomerOrderHistory, com os próprios adaptadores HTTP de e-mail, senha, UF e conta; CustomersServiceProvider
│   │   ├── backoffice/          dashboard do admin (DashboardQuery, que lê os quatro contratos de métricas: CatalogMetrics, StockMetrics, CustomerMetrics e OrderMetrics)
│   │   ├── integration/         pacote de composição: liga a porta de um módulo ao contrato de outro (OrdersToPay do Payment -> PayableOrders do Ordering), ou a uma porta que responde 503 quando o dono não está instalado; o único que usa mais de um módulo de negócio
│   │   └── shared/              kernel de infraestrutura, sem camadas: erros da API, ApiFormRequest, BaseRepository, Controller, X-Request-ID, RecordId e o `throttle` que conta antes de decidir (Http/Middleware/AtomicThrottleRequests, Cache/AtomicRateLimiter); SharedServiceProvider
│   ├── config/shop.php              configurações da loja (delay da entrega, paginação, tabela de frete por UF)
│   ├── config/horizon.php, pennant.php, rabbitmq.php   o supervisor do Horizon, o driver das feature flags e a conexão com o RabbitMQ
│   ├── database/
│   │   ├── migrations/              só as do framework (cache, jobs e features); as demais ficam em `app-modules/<módulo>/database/migrations`
│   │   └── seeders/
│   ├── lang/pt_BR/validation.php    mensagens de validação em português
│   ├── routes/console.php           comandos agendados; as rotas da API ficam em `app-modules/<módulo>/routes/api.php`
│   └── tests/                       a suíte de composição (Pest): os fluxos que juntam módulos reais, a arquitetura, a compatibilidade dos eventos, os adaptadores e os seeders; o `Pest.php` configura também os `tests/` dos módulos
│
├── frontend/                        Vue 3 + Vite
│   └── src/
│       ├── components/              componentes reutilizáveis (ProductCard, OrderTimeline, gráficos...)
│       ├── composables/             usePolling, useFormErrors, usePayment, useAddressBook, useCheckout
│       ├── layouts/                 PublicLayout, AuthLayout, CustomerLayout, AdminLayout
│       ├── pages/                   public/, auth/, account/, admin/
│       ├── router/                  rotas + guards de autenticação/autorização
│       ├── services/                api.ts (Axios central) + serviços por recurso
│       ├── stores/                  Pinia: auth (cliente), staff (equipe), cart, notifications
│       ├── types/                   tipos TypeScript da API
│       └── utils/                   dinheiro, datas, slug, CEP, UFs, menu da conta, texto de entrega
│
├── docker/
│   ├── nginx/default.conf
│   ├── node/Dockerfile
│   ├── php/Dockerfile, php.ini, entrypoint.sh
│   ├── postgres/init/               cria o banco de testes
│   └── tests/environment.sh         provas do ambiente: Compose, imagem, alvos do Makefile, CI e documentos (`sh docker/tests/environment.sh --only <caso>`)
├── docs/                            a análise de domínio (`domain-analysis.md`) e o guia de estudo da evolução da plataforma (`platform-evolution.md`)
├── .design/                         um design por entrega: o problema, as decisões-chave e as alternativas descartadas
├── .specs/features/                 o plano, os critérios, as provas e a verificação de cada entrega
├── load-tests/                      testes de carga com k6 (ver Testes de carga): o Compose do projeto `ecommerce-load`, os fluxos, os perfis, as provas dos scripts e a linha de base
├── storage-dumps/                   destino do `make db-dump`
├── docker-compose.yml
├── Makefile
├── .env.example                     variáveis do Docker Compose
├── AGENTS.md                        as regras para quem altera o código
└── README.md
```

---

## Pré-requisitos

Apenas **Docker** (com Docker Compose v2) e **make**. PHP, Composer e Node **não** precisam estar instalados na máquina: tudo roda dentro dos containers.

Portas usadas no host (configuráveis no `.env` da raiz):

- `8080` — aplicação (Nginx)
- `5433` — PostgreSQL (para conectar um cliente de banco, opcional)

---

## Início rápido

```bash
make setup
```

O `make setup`:

1. cria `.env` e `backend/.env` a partir dos `.env.example` (se ainda não existirem);
2. constrói as imagens e instala as dependências do frontend (`npm install`);
3. sobe os containers e aguarda PostgreSQL, Redis e RabbitMQ ficarem saudáveis;
4. instala as dependências PHP (`composer install`) e gera a `APP_KEY`;
5. executa `migrate:fresh --seed` (**recria o banco de desenvolvimento**);
6. reinicia o Horizon, o relay e o consumidor do RabbitMQ.

Depois acesse **http://localhost:8080**.

> ⚠️ Como o `make setup` executa `migrate:fresh`, rodá-lo novamente apaga os dados do banco de desenvolvimento e recria os dados de demonstração.

---

## Configuração do `.env`

Existem dois arquivos:

| Arquivo | Para que serve |
| --- | --- |
| `.env` (raiz) | Variáveis do Docker Compose: `APP_PORT`, credenciais do PostgreSQL, `DB_FORWARD_PORT`, as do MongoDB (`MONGODB_USERNAME`, `MONGODB_PASSWORD` e `MONGODB_FORWARD_PORT`, a porta de um cliente no host, 27018) e as do RabbitMQ (`RABBITMQ_USER`, `RABBITMQ_PASSWORD` e `RABBITMQ_MANAGEMENT_PORT`, a porta do painel de gestão em `http://localhost:15672`). E, opcional, `TEST_DB_DATABASE`, lida só pelo Makefile: o banco dos testes deste checkout (ver [Um banco de testes por checkout](#um-banco-de-testes-por-checkout)). |
| `backend/.env` | Configuração do Laravel: banco (`DB_*`), Redis, RabbitMQ (`RABBITMQ_*`), fila, sessão, Sanctum, locale, delay da entrega. |

Variáveis importantes do `backend/.env`:

| Variável | Valor padrão | Observação |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8080` | URL servida pelo Nginx |
| `DB_HOST` / `DB_DATABASE` | `db` / `ecommerce` | Devem bater com o `.env` da raiz |
| `QUEUE_CONNECTION` | `redis` | Fila consumida pelo `queue-worker` (o Horizon) |
| `MONGODB_HOST` / `MONGODB_PORT` | `mongodb` / `27017` | O MongoDB do modelo de leitura da vitrine |
| `MONGODB_DATABASE` | `ecommerce` | O banco do modelo de leitura. O `make test` usa um por checkout (o do banco de testes) |
| `MONGODB_USERNAME` / `MONGODB_PASSWORD` | `ecommerce` / `secret` | Devem bater com o `.env` da raiz. A conexão desiste de um MongoDB que não responde em 2 s (a vitrine responde `503`) |
| `RABBITMQ_HOST` / `RABBITMQ_PORT` | `rabbitmq` / `5672` | O broker do transporte `rabbitmq` |
| `RABBITMQ_USER` / `RABBITMQ_PASSWORD` | `ecommerce` / `secret` | Devem bater com o `.env` da raiz |
| `RABBITMQ_VHOST` | `/` | O vhost do ambiente de dev e da pilha de carga. O `make test` usa um vhost por checkout (ver [Um banco de testes por checkout](#um-banco-de-testes-por-checkout)) |
| `SESSION_DRIVER` | `redis` | Sessão (cookie HTTP-only) |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:8080,...` | Domínios que autenticam por cookie. **Se mudar `APP_PORT`, atualize aqui.** |
| `APP_FAKER_LOCALE` | `pt_BR` | Locale do Faker |
| `ORDER_DELIVERY_DELAY_SECONDS` | `10` | Delay do job de entrega simulada |
| `CACHE_LIMITER` | vazio | Armazenamento das contagens do limitador de requisições. Vazio usa o armazenamento padrão, e os limites das rotas valem. Só a pilha de carga define (`array`); ver [Testes de carga (k6)](#testes-de-carga-k6) |

---

## Comandos do Makefile

Execute `make` (ou `make help`) para listar todos os comandos.

Num `git worktree`, os alvos que leem o código do checkout (`make test`, `make test-filter`, `make pint`, `make shell`, `make artisan`, `make test-frontend` e `make frontend-shell`) rodam num container avulso que monta o `backend/` ou o `frontend/` do próprio worktree; os demais continuam nos containers de dev. Ver [Um banco de testes por checkout](#um-banco-de-testes-por-checkout).

### Ambiente

| Comando | Descrição |
| --- | --- |
| `make setup` | Sobe e prepara todo o projeto (recria o banco) |
| `make up` | Inicia todos os containers (`docker compose up -d`) |
| `make down` | Para os containers (`docker compose down`) |
| `make build` | Reconstrói as imagens (`docker compose build`) |
| `make rebuild` | Reconstrói as imagens e inicia os containers |
| `make ps` | Mostra o status dos containers |
| `make logs` | Logs principais (api, queue-worker, outbox-relay, event-consumer, nginx, frontend) |
| `make logs-api` | Logs da API |
| `make logs-worker` | Logs do worker de filas |
| `make logs-relay` | Logs do relay do outbox (`outbox-relay`) |
| `make logs-consumer` | Logs do consumidor do RabbitMQ (`event-consumer`) |

### Laravel

| Comando | Descrição |
| --- | --- |
| `make shell` | Abre um shell no container `api` (num worktree, num `api` avulso com o `backend/` dele) |
| `make artisan CMD="..."` | Executa um comando Artisan no `backend/` deste checkout. Ex.: `make artisan CMD="route:list"` |
| `make migrate` | Executa as migrations |
| `make migrate-fresh` | `php artisan migrate:fresh` |
| `make seed` | Executa os seeders |
| `make fresh` | `php artisan migrate:fresh --seed` — **comando principal para recriar o banco** |
| `make optimize` | `php artisan optimize` (cache de config, rotas e eventos) |
| `make clear` | `php artisan optimize:clear` (limpa os caches) |

### Banco de dados

| Comando | Descrição |
| --- | --- |
| `make db-shell` | Abre o `psql` no container `db` |
| `make db-reset` | Recria o banco (migrations + seeders) |
| `make db-dump` | Gera `storage-dumps/dump-AAAAMMDD-HHMMSS.sql` |
| `make db-restore FILE="..."` | Restaura um dump. Ex.: `make db-restore FILE=storage-dumps/dump-20261005-120000.sql` |

### Filas

| Comando | Descrição |
| --- | --- |
| `make queue` | Executa `php artisan queue:work redis` em primeiro plano (útil para ver o processamento ao vivo; o container `queue-worker` roda o Horizon) |
| `make queue-restart` | Reinicia o Horizon (`horizon:terminate`), o relay do outbox e o consumidor do RabbitMQ (`queue:restart`) (**necessário após alterar código de jobs, consumidores ou do relay**) |
| `make event-transport` | Mostra o transporte dos eventos (a flag `event-transport`). `make event-transport TO=rabbitmq` (ou `TO=redis`) o troca, sem deploy, e registra no log quem trocou |
| `make events-failed` | Lista as entregas mortas das filas do RabbitMQ (as que falharam 3 vezes) |
| `make events-retry` | Devolve as entregas mortas ao consumidor de cada uma (ou só as de uma mensagem: `make events-retry ID=<uuid>`) |
| `make queue-failed` | Lista os jobs que falharam |
| `make queue-retry` | Reprocessa todos os jobs falhos (ou um: `make queue-retry ID=<uuid>`) |

### Vitrine (modelo de leitura)

| Comando | Descrição |
| --- | --- |
| `make storefront-rebuild` | Reconstrói o modelo de leitura da vitrine: roda `catalog:read-model-clear`, `catalog:republish` e `inventory:republish`, nessa ordem. O relay e os consumidores (`make up`) projetam as mensagens em alguns segundos. O `make fresh` e o `make seed` terminam com ele |

### Testes

| Comando | Descrição |
| --- | --- |
| `make test` | Executa todos os testes (Pest) no banco, no vhost do RabbitMQ e no banco do MongoDB de testes deste checkout, criados se ainda não existirem (ver [Testes](#testes)). O `rabbitmq` e o `mongodb` precisam estar no ar |
| `make test-filter FILTER="..."` | Executa testes filtrados. Ex.: `make test-filter FILTER=CheckoutTest` |
| `make test-db-drop` | Apaga o banco, o banco do MongoDB e o vhost do RabbitMQ de testes deste checkout (o próximo `make test` os recria). Rode antes de remover um worktree |
| `make pint` | Verifica o estilo do backend com o Pint (`pint --test`, sem corrigir). É o mesmo passo do CI |
| `make test-frontend` | Executa os testes do frontend (Vitest) no container `frontend` (num worktree, num `frontend` avulso com o `frontend/` dele) |

### Testes de carga

| Comando | Descrição |
| --- | --- |
| `make load-test TYPE=<perfil>` | Recria a pilha de carga (`ecommerce-load`, com banco, Redis, RabbitMQ e MongoDB próprios, e o `api-read`), reconstrói o modelo de leitura e espera 202 produtos na vitrine e roda um perfil do k6: `smoke`, `load`, `stress`, `spike`, `breakpoint`, `soak`, `contention` ou `limits`. A pilha de dev não muda |
| `make load-test TYPE=<perfil> TRANSPORT=rabbitmq` | O mesmo perfil com o relay publicando pelo RabbitMQ (`redis` é o padrão); os relatórios se chamam `<perfil>-rabbitmq-<carimbo>` |
| `make load-down` | Remove a pilha de carga e os volumes dela |

### Frontend

| Comando | Descrição |
| --- | --- |
| `make npm-install` | Instala as dependências do frontend |
| `make frontend-shell` | Abre um shell no container `frontend` (num worktree, num `frontend` avulso com o `frontend/` dele) |

### Exemplos

```bash
make setup                          # primeira execução
make fresh                          # recriar o banco com dados de demonstração
make test                           # rodar os testes
make pint                           # conferir o estilo do backend
make queue                          # acompanhar a fila em primeiro plano
make db-shell                       # abrir o psql
make logs                           # acompanhar os logs
make artisan CMD="route:list --path=api"
```

---

## Banco de dados, migrations, seeders e Faker

### Modelo de dados

```
customers ──1:N── orders ──1:N── order_items ──N:1── products ──1:1── stocks
    │                                                │
    1:N                                              N:N (category_product)
    │                                                │
customer_addresses                               categories
```

`orders` não aponta para `customer_addresses`: o endereço entra no pedido como **cópia** (colunas `delivery_*`).

**Ids:** a chave primária das 10 tabelas de negócio (`users`, `customers`, `categories`, `products`, `stocks`, `orders`, `order_items`, `payments`, `customer_addresses` e `deliveries`) é um **UUID v7** gerado pela aplicação (`HasUuids`, sem default no banco), no tipo nativo `uuid` do PostgreSQL, e toda chave estrangeira para elas é `uuid`. Nenhum número sequencial sai do banco: a API, as URLs, os contratos e os eventos só levam UUIDs em texto, na forma canônica (minúsculas, com hífens). As tabelas do framework (`jobs`, `failed_jobs`, `sessions`, `cache`) não mudam. O `sessions.user_id` continua `bigint`, porque a sessão roda no Redis; se o driver passar a `database`, essa coluna precisa virar `uuid`.

| Tabela | Destaques |
| --- | --- |
| `users` | a **equipe** (admin e suporte): `role` obrigatório, **sem default**, indexado, com `CHECK users_role_valid (role IN ('admin', 'support'))`. `email` único com `CHECK users_email_normalized`. Não se liga a nenhuma tabela de negócio |
| `customers` | o **comprador**: `name`, `email` único com `CHECK customers_email_normalized (email = lower(btrim(email)))`, `password`. Lida como `CustomerAccount` (identidade) e como `Customer` (comprador visto pelos pedidos, somente leitura). O mesmo e-mail pode existir em `users` e em `customers`: as tabelas são independentes |
| `categories` | `slug` **único**, gerado automaticamente a partir do nome (`"Eletrônicos"` → `eletronicos`) |
| `products` | `price_cents bigint` (centavos inteiros, nunca `float`) com `CHECK (price_cents >= 0)`, `status` (`active` / `inactive`). **Não** possui quantidade |
| `stocks` | `product_id` **único** (garante o 1:1), `quantity` inteiro com `CHECK (quantity >= 0)` |
| `category_product` | pivot com chave primária composta |
| `customer_addresses` | o caderno do cliente: `customer_id` (FK `cascade`, indexada), `recipient_name`, `postal_code char(8)` com `CHECK customer_addresses_postal_code_digits` (8 dígitos), `street`, `number`, `complement` (opcional), `district`, `city` e `state char(2)` com `CHECK customer_addresses_state_known` (as 27 UFs). Até 10 por cliente (regra da aplicação) |
| `orders` | `total_cents bigint` (centavos, itens + frete) com `CHECK (total_cents >= 0)`, `shipping_cents bigint` (`CHECK >= 0`), `delivery_business_days smallint` (`CHECK > 0`, o prazo prometido), `estimated_delivery_on date` (nula até o pagamento ser aprovado), a **cópia do endereço de entrega** em colunas `delivery_*` `NOT NULL` (exceto o complemento), com `CHECK` de CEP com 8 dígitos e de UF entre as 27, e `CHECK (total_cents >= shipping_cents)`. `status` (`placed`, `awaiting_payment`, `payment_approved`, `delivered`). `code char(8)` **único**, o código que as pessoas leem (ver abaixo), com `CHECK orders_code_format` |
| `payments` | uma linha por **tentativa** de pagamento: `amount_cents` (cópia do total do pedido), `status` (`approved` / `declined`), `decline_reason`, `card_token`, `gateway`, `gateway_transaction_id`. Índice **único parcial** em `order_id` onde `status = 'approved'` (no máximo um aprovado por pedido) e `CHECK` de motivo só na recusa e de valor `>= 0` |
| `deliveries` | o registro do Fulfillment de que um pedido pago foi agendado e, depois, entregue (HEL-16): `order_id` **único** (FK `restrict`), `estimated_delivery_on date` (não nula, escrita só na criação: a primeira data vale) e `delivered_at` (nulo até a entrega, preenchido uma só vez; preenchido é o estado entregue). É a chave natural que diz ao Fulfillment se já anunciou o `DeliveryScheduled` e o `OrderDelivered`. Nenhum outro módulo a lê |
| `order_items` | **snapshot** de `product_name` e `unit_price_cents`; `subtotal_cents bigint` (`unit_price_cents × quantity`), os dois com `CHECK >= 0`. Mudar o produto não altera pedidos antigos |
| `outbox_messages` | o **outbox transacional**: uma linha por evento, gravada na transação da mudança que ele anuncia. `id uuid` (v7 da aplicação, também o id da mensagem), `name varchar(100)` (`ordering.order_paid`), `version smallint`, `payload jsonb` (nulo, escalares ou uma lista plana de escalares), `request_id varchar(128)` (nulo fora de uma requisição), `occurred_at` e `published_at` (nulo enquanto pendente). Índice parcial `outbox_messages_pending_index (occurred_at, id) WHERE published_at IS NULL` para o relay e índice em `published_at` para a retenção. Sem chave estrangeira: o payload leva os ids como dado, e apagar uma mensagem não toca em nenhum outro registro |

**Código do pedido:** toda tela mostra `#K7Q2M9XA` no lugar do id. O código tem 8 símbolos do alfabeto `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (sem `0`, `O`, `1`, `I` e `L`, cerca de 850 bilhões de combinações), é sorteado pelo `OrderCodeGenerator` (`random_int`), é único e nunca muda. Só a colocação do pedido o grava. Ele vale só para leitura: URLs, contratos e eventos continuam com o id, e nenhum contrato recebe o código como parâmetro. O banco recusa um código nulo, de outro tamanho, com símbolo fora do alfabeto ou repetido.

**Integridade:**

- `stocks` e `category_product` usam `cascade` (são dependentes do produto).
- `order_items.product_id` e `orders.customer_id` (→ `customers.id`) usam `restrict`: **não é possível excluir um produto que já está em pedidos** (a API responde `409` e sugere desativar o produto), nem um cliente que tem pedidos.
- `payments.order_id` não tem chave estrangeira desde a isolação dos módulos: o Payment roda sem o Ordering, e o pedido é conferido pela porta `OrdersToPay` antes de qualquer linha ser gravada. A migration de criação (`2026_10_06_000001_create_payments_table`) perdeu só a referência a `orders`, e a `2026_10_21_000001_drop_payments_order_id_foreign` apaga a chave nos bancos já migrados (`make migrate`). Pedidos e pagamentos continuam sem rota de exclusão.
- `deliveries.order_id` também usa `restrict`: um pedido que tem registro de entrega não pode ser apagado (o PostgreSQL responde `23001`).
- Categorias com produtos associados também não podem ser excluídas (`409`).
- `customer_addresses.customer_id` usa `cascade`: o caderno some com a conta, e nenhum pedido sente, porque cada pedido guarda a própria cópia do endereço. Editar ou excluir um endereço nunca muda um pedido feito.

### Migrations e seeders

```bash
make fresh      # php artisan migrate:fresh --seed
make migrate    # apenas migrations
make seed       # apenas seeders
```

Seeders (chamados pelo `DatabaseSeeder` nesta ordem):

| Seeder | O que cria |
| --- | --- |
| `UserSeeder` | a equipe (`admin@example.com` admin e `suporte@example.com` suporte, em `users`) e 10 clientes em `customers` (inclui `cliente@example.com`) |
| `CategorySeeder` | 8 categorias: Eletrônicos, Informática, Celulares, Acessórios, Casa, Escritório, Games, Periféricos |
| `ProductSeeder` | 30 produtos (25 ativos, 5 inativos), cada um com 1 a 3 categorias e imagem `https://picsum.photos/seed/product-{n}/600/600` |
| `StockSeeder` | Estoque para cada produto: 6 sem estoque, 6 com estoque baixo (1–5), 9 médio (6–20), 9 alto (21–150) |
| `CustomerAddressSeeder` | 1 endereço em SP para `cliente@example.com` (o frete mais curto, 2 dias úteis) e 1 ou 2 endereços em UFs variadas para os demais clientes |
| `OrderSeeder` | 24 pedidos com itens, snapshots, cópia de um endereço do cliente, frete e prazo da tabela de frete, e totais (itens + frete); metade nos últimos 30 dias (todos os status) e metade nos últimos 12 meses (entregues). A data prevista só existe nos pedidos pagos |

### Como o Faker é utilizado

- As **factories** (`database/factories` de cada módulo) usam o Faker (`fake()`) com locale `pt_BR` para nomes de clientes, e-mails, nomes/descrições/preços de produtos e datas dos pedidos.
- O `DatabaseSeeder` fixa a semente do Faker (`fake()->seed(2026)`): **toda execução de `make fresh` gera exatamente os mesmos dados**, o que deixa o ambiente previsível.
- Os seeders são idempotentes onde faz sentido (admin via `firstOrNew`, categorias via `firstOrCreate`; produtos/pedidos só são criados se a tabela estiver vazia).
- Os pedidos de demonstração **não decrementam estoque**: representam vendas passadas, anteriores à contagem atual de estoque.
- O Faker reseta o gerador aleatório global do PHP sempre que um dos geradores dele é destruído, e o coletor de lixo pode destruir os de uma execução anterior a qualquer momento (na suíte de testes, em outra posição). Por isso o `DatabaseSeeder` chama `gc_collect_cycles()` antes de fixar a semente: sem isso, o teste de determinismo falhava conforme a ordem e o tamanho da suíte.

Exemplo de uso das factories em testes ou no Tinker (`make artisan CMD=tinker`):

```php
Product::factory()->withStock(10)->create();          // produto ativo com 10 unidades
Product::factory()->inactive()->withStock(0)->create();
User::factory()->admin()->create();               // membro da equipe (também ->support())
CustomerAccount::factory()->create();               // conta de comprador
OrderItem::factory()->forProduct($product, 2)->create();
```

---

## Usuários de teste e dados disponíveis

| Perfil | E-mail | Senha |
| --- | --- | --- |
| Administrador (entra em `/admin/login`) | `admin@example.com` | `password` |
| Suporte (entra em `/admin/login`) | `suporte@example.com` | `password` |
| Cliente (entra em `/login`) | `cliente@example.com` | `password` |
| Demais clientes | e-mails gerados pelo Faker (veja em **Admin → Clientes**) | `password` |

As contas de equipe e de cliente são independentes: as credenciais de uma não entram na outra área, mesmo com o mesmo e-mail.

Após o seed você terá: 2 membros da equipe, 10 clientes (todos com endereço), 8 categorias, 30 produtos (com produtos sem estoque, com estoque baixo e inativos), 24 pedidos distribuídos entre todos os status e datas — o dashboard já mostra gráficos na primeira execução.

---

## Fluxo de checkout

1. O carrinho fica no **Pinia** (persistido no `localStorage`, na chave `cart-v3` — apenas os dados do carrinho).
2. Ao abrir `/checkout`, o frontend chama `POST /api/cart/validate`, que revalida **existência, status, estoque, quantidade e preço atual** de cada item (problemas aparecem por item e bloqueiam a confirmação), e carrega o caderno de endereços (`GET /api/account/addresses`). Sem endereço, o formulário abre no próprio checkout; com endereços, o mais recente já vem escolhido, e "Adicionar outro endereço" abre o formulário.
3. O frontend pede o frete da UF do endereço escolhido (`GET /api/shipping/quote?state=UF`, público) e mostra **Subtotal, Frete, Total** e "Entrega em até N dias úteis após a aprovação do pagamento". Trocar de endereço pede o frete de novo. Esses valores são só exibição; se o frete falhar, a confirmação fica bloqueada com "Tentar novamente".
4. Ao confirmar, o frontend envia **apenas `product_id`, `quantity` e `address_id`** para `POST /api/orders`. Preços, frete, total e UF enviados pelo cliente são ignorados.
5. O `PlaceOrderUseCase` (backend) orquestra o fluxo, usando o `CheckoutService` para as regras, os contratos `ProductCatalog` (Catalog) e `StockReservation` (Inventory) para os produtos e o estoque, e o `OrderRepository` para gravar o pedido:
   1. valida o cliente autenticado (guard `customer`; a equipe não compra — a rota responde `401` a uma sessão de equipe);
   2. valida o formato dos itens e do `address_id` (`StoreOrderRequest`): todo id do corpo precisa ser um UUID canônico, conferido antes de qualquer consulta, e um malformado é `422` no campo;
   3. busca a **cópia do endereço** pelo contrato `DeliveryAddressBook`, só no caderno do próprio cliente (outro cliente ou id inexistente: `422` em `address_id`, sem tocar no estoque), e o **frete** pelo contrato `ShippingQuoter`, a partir da UF da cópia;
   4. **inicia a transação** (`DB::transaction()`);
   5. **bloqueia os registros de estoque** envolvidos (`lockForUpdate()`);
   6. lê os produtos pelo `ProductCatalog` e verifica novamente status e estoque com as quantidades lidas sob o bloqueio (`StockQuantities`);
   7. **recalcula os preços** a partir do catálogo em centavos inteiros (`unit_price_cents × quantity`, soma em `int`), sem `float`;
   8. **reduz o estoque** por `product_id`;
   9. cria o pedido (`placed`) com o **código sorteado**, a cópia do endereço, `shipping_cents`, `delivery_business_days` e `total_cents` = itens + frete, e os itens (snapshot de nome e preço). A gravação do pedido e dos itens roda num savepoint (`DB::transaction()` aninhado) aberto pelo próprio use case: se o código já existe, só o savepoint é desfeito e o código é sorteado de novo, até 3 sorteios, sem soltar o bloqueio nem refazer a baixa de estoque. Na terceira colisão seguida a exceção sobe, a transação inteira é desfeita e a API responde `500` (cerca de 1 em 850 bilhões por pedido existente);
   10. grava o evento `OrderPlaced` no outbox, com o id do pedido, **ainda dentro da transação** (sem pedido não há mensagem, e sem mensagem não há pedido);
   11. **confirma a transação**. O relay publica o `OrderPlaced` em seguida, e o consumidor move o pedido para `awaiting_payment`.
6. O frontend limpa o carrinho e redireciona para `/payment/{orderId}` (o id do pedido, nunca o código), que mostra Subtotal, Frete e Total. O pagamento cobra o `total_cents` do pedido.
7. Depois que o pagamento é aprovado, o Fulfillment calcula a data prevista e o pedido passa a mostrar "Entrega prevista: dd/mm/aaaa" (ver [Eventos, outbox, consumidores e filas](#eventos-outbox-consumidores-e-filas)).

Respostas: `201` (pedido criado, com o `code`), `409` (estoque insuficiente / produto indisponível, com detalhes por item em `errors`), `422` (dados inválidos, ou endereço fora do caderno do cliente), `401`/`403`/`429`.

---

## Transação e concorrência de estoque

Trecho central de `backend/app-modules/ordering/src/Core/UseCases/PlaceOrderUseCase.php`:

```php
DB::transaction(function () use ($customerId, $quantities) {
    // SELECT ... FROM stocks WHERE product_id IN (...) ORDER BY product_id FOR UPDATE
    // (contrato StockReservation, implementado pelo StockRepository — ordem fixa evita deadlocks)
    // $productIds é um ProductIds, derivado do ProductQuantities do carrinho
    // devolve as quantidades lidas sob o bloqueio (StockQuantities), nunca o model
    $stock = $this->stockReservation->lockForProducts($productIds);

    // os produtos vêm do Catalog pelo contrato ProductCatalog (CatalogProducts)
    $products = $this->catalog->findMany($productIds);

    // revalida com os valores bloqueados; se faltar estoque, lança exceção → ROLLBACK
    $this->checkout->assertCanFulfil($quantities, $products, $stock);

    // decrementa o estoque, cria o pedido e os itens
});
```

**Exemplo clássico** — Produto A com estoque 5:

| Momento | Cliente 1 (compra 4) | Cliente 2 (compra 3) |
| --- | --- | --- |
| t1 | `BEGIN` + `SELECT ... FOR UPDATE` → obtém o lock, vê 5 | |
| t2 | | `BEGIN` + `SELECT ... FOR UPDATE` → **espera** o lock |
| t3 | 5 ≥ 4 ✔ → estoque = 1, cria pedido, `COMMIT` | |
| t4 | | obtém o lock, vê **1** → 1 < 3 ✘ → `ROLLBACK`, **409** |

O endereço e o frete são resolvidos **antes** da transação: um endereço recusado nunca chega a bloquear o estoque. O `shipping_cents` e o `total_cents` gravados vêm do servidor, na mesma transação do estoque e dos itens.

Sem o lock, os dois poderiam ler `5` ao mesmo tempo e o estoque ficaria negativo. Camadas de proteção:

1. validação no checkout (antes de confirmar);
2. revalidação **dentro** da transação com lock pessimista;
3. `CHECK (quantity >= 0)` no banco como última linha de defesa;
4. ajustes do admin (`AdjustStockUseCase`) também usam transação + `lockForUpdate()`.

Para ver a concorrência na prática, com o ambiente rodando, deixe um produto com estoque 5 (Admin → Estoque) e dispare dois checkouts simultâneos de clientes diferentes (4 e 3 unidades): um recebe `201` e o outro `409 — Estoque insuficiente. Disponível: 1.`

---

## O modelo de leitura da vitrine

A vitrine (`GET /api/products` e `GET /api/products/{product}`) é o lado de leitura de um CQRS com armazenamento e processo próprios (HEL-17, design em [.design/storefront-read-model.md](.design/storefront-read-model.md)). A escrita fica no PostgreSQL, pelos casos de uso de sempre; a leitura vem de um **modelo de leitura do Catalog no MongoDB**, servido por um processo à parte, o `api-read`, e as duas pontas se falam só pelo outbox.

```text
escrita do admin ou checkout -> api -> Catalog / Inventory / Ordering   (trava a linha, version + 1, grava o evento no outbox)
   -> relay -> Redis ou RabbitMQ -> EventDelivery -> projetor do Catalog -> MongoDB (catalog_products, catalog_categories)
navegador / k6 -> Nginx -- GET e HEAD de /api/products e /api/products/{product} --> api-read -> MongoDB
                        -- todo o resto de /api, /sanctum e /horizon ---------------> api -> PostgreSQL
```

- **O modelo de leitura.** Duas coleções no MongoDB, sem chave nenhuma para as tabelas (só o id e os eventos):
  - `catalog_products`: `_id` (o id do produto), `product` (nulo, ou `name`, `price_cents`, `description`, `image_url`, `status`, `category_ids`, `created_at` e `updated_at` em texto ISO 8601, e `version`), `stock` (nulo, ou `id`, `quantity` e `version`) e `deleted_version` (nulo, ou a lápide). Índices em `product.category_ids`, em (`product.name`, `_id`) e em (`product.price_cents`, `_id`);
  - `catalog_categories`: `_id`, `name`, `slug`, `version` e `deleted_version`, com um índice não único em `slug`.
  Nenhum documento guarda `is_available`, `status_label` nem `available_quantity`: a disponibilidade é decidida na leitura, só pelo `ProductAvailability` e pelo `ProductStatus`, e a consulta da vitrine não filtra por status nem por quantidade (um produto que não pode ser comprado aparece, esmaecido).
- **A versão do registro.** `products`, `categories` e `stocks` ganharam a coluna `version` (`bigint`, não nula, padrão 1, só cresce). Toda escrita que muda a vitrine trava a linha (`SELECT ... FOR UPDATE`), soma 1 à versão na transação dela e grava o evento da linha travada; a exclusão publica a versão guardada mais 1. Duas escritas simultâneas na mesma linha ganham versões crescentes na ordem do commit, e a de maior versão carrega o estado final.
- **Os eventos** (todos na versão 1, em `Modules\Catalog\Events` e `Modules\Inventory\Events`), que levam o **estado inteiro da parte que mudou**, nunca uma diferença:

  | Evento | Payload |
  | --- | --- |
  | `catalog.product_created`, `catalog.product_updated`, `catalog.product_status_changed` | `product_id`, `name`, `price_cents`, `description`, `image_url`, `status`, `category_ids` (lista em ordem crescente de id), `created_at`, `updated_at` (ISO 8601 com o fuso) e `version` |
  | `catalog.product_deleted` | `product_id`, `version` |
  | `catalog.category_created`, `catalog.category_updated` | `category_id`, `name`, `slug`, `version` |
  | `catalog.category_deleted` | `category_id`, `version` |
  | `inventory.stock_changed` | `stock_id`, `product_id`, `quantity` (a quantidade gravada, nunca as unidades movidas), `version` |

  Quem grava: o cadastro e a edição do produto, a troca de status, a exclusão, o cadastro, a renomeação e a exclusão da categoria, o ajuste de estoque, a abertura do estoque de um produto novo e o checkout (um `inventory.stock_changed` por produto do pedido). A exclusão de um produto não publica o estoque que sai pela cascata: a lápide do produto basta.
- **O projetor.** Oito consumidores do Catalog (`catalog.project_product_created`, `catalog.project_product_updated`, `catalog.project_product_status_changed`, `catalog.project_product_deleted`, `catalog.project_category_created`, `catalog.project_category_updated`, `catalog.project_category_deleted` e `catalog.project_stock_changed`), em [`Core/Consumers`](backend/app-modules/catalog/src/Core/Consumers), são os únicos que gravam nas coleções. O Catalog lê o `inventory.stock_changed` com uma classe própria em `Core/InboundEvents`. **A regra de versão** (a chave natural de idempotência, AD-005): cada gravação é um `updateOne` com `upsert` cujo filtro tem a regra, então o banco decide: uma parte (`product`, `stock` ou a categoria) é substituída inteira só quando a versão do evento é maior que a guardada (e, no produto, que a `deleted_version`); a igual ou menor é uma entrega repetida e é descartada. A exclusão grava `deleted_version`; um evento de produto até essa versão é descartado, e um evento de estoque mais novo muda a parte `stock` sem tirar a lápide. Uma chave duplicada do `upsert` é tentada uma vez de novo (o documento pode ter perdido a corrida de inserção) e, se repetir, é um descarte. O projetor converge para o último estado gravado com entregas repetidas, atrasadas, fora de ordem ou simultâneas.
- **A reconstrução.** `make storefront-rebuild` esvazia as coleções (`catalog:read-model-clear`, que recria os índices: é o único caminho que os cria, então todo volume novo do MongoDB pede um `make storefront-rebuild` antes de servir carga) e grava pelo outbox o estado atual de cada registro: `catalog:republish` (um `catalog.category_updated` por categoria e um `catalog.product_updated` por produto, com a versão atual, cada produto lido com as suas categorias numa só consulta) e `inventory:republish` (um `inventory.stock_changed` por estoque). Cada registro é uma transação; uma falha no meio sai com código diferente de zero e diz quantos gravou (`Republicados antes da falha: N.`), e rodar de novo converge. Os seeders e as factories não gravam evento, então depois de um `make fresh` ou `make seed` (que terminam com a reconstrução) e depois de baixar o repositório com um banco de dev existente, rode `make storefront-rebuild`. Durante a reconstrução a vitrine responde `200` com o que já foi projetado.
- **O `api-read` e o gateway.** O `api-read` é o mesmo código e a mesma imagem PHP do `api`, em outro container, sem credencial do PostgreSQL (`DB_HOST`, `DB_USERNAME` e `DB_PASSWORD` vazios e nenhuma dependência do `db`): com o `db` parado, a vitrine continua no ar. O Nginx manda o `GET` e o `HEAD` de `/api/products` e `/api/products/{product}` para ele e o resto para o `api`; um `502` ou `504` do `api-read` (parado ou sem resposta) vira `503` com o corpo padrão de erro (`SERVICE_UNAVAILABLE`, `Este recurso está indisponível no momento.`) e um `X-Request-ID`; o `503` que o próprio `api-read` responde (MongoDB fora do ar) passa como está, com o `request_id` que o log da aplicação registrou. O destino do `api-read` é resolvido a cada requisição (o `resolver` do Docker), então o Nginx sobe mesmo com ele parado. Sem o MongoDB, a vitrine responde o mesmo `503` em até 5 s.
- **O serviço `mongodb`** (`mongo:8.2`, usuário raiz por `MONGO_INITDB_ROOT_USERNAME` e `MONGO_INITDB_ROOT_PASSWORD`, volume `mongodb_data`, porta `MONGODB_FORWARD_PORT` no host) vem com o pacote `mongodb/laravel-mongodb` e a extensão `mongodb` da imagem PHP; a conexão `mongodb` lê as variáveis `MONGODB_*` ([Configuração do `.env`](#configuração-do-env)). O `api`, o `queue-worker`, o `outbox-relay`, o `event-consumer`, o `scheduler` e o `api-read` esperam por ele.
- **Consistência eventual.** Entre uma escrita e a vitrine mostrá-la passam o relay, o transporte e o consumidor: o k6 mede esse tempo na métrica `storefront_projection_lag` ([load-tests/README.md](load-tests/README.md)). O carrinho, o checkout e o painel do admin continuam lendo o PostgreSQL.

---

## Eventos, outbox, consumidores e filas

O ciclo de vida do pedido envolve três partes, que só conversam por eventos:

- **Pedidos** (Ordering): é a única parte que muda o status do pedido.
- **Pagamento** (Payment): cobra pelo gateway, grava cada tentativa e só anuncia que o pagamento foi aprovado.
- **Entrega** (Fulfillment): decide o frete e a data prevista, e anuncia que a data foi calculada e que a transportadora (fake) entregou.

Todo evento passa pelo **outbox transacional** (HEL-9, design em [.design/transactional-outbox.md](.design/transactional-outbox.md)):

1. O caso de uso grava o evento pelo `EventOutbox` **dentro da transação da mudança que ele anuncia**. O commit grava a mudança e a mensagem (uma linha de `outbox_messages`, pendente enquanto `published_at` é nulo) juntas, e um rollback desfaz as duas.
2. O **relay** (`php artisan outbox:relay`, no container `outbox-relay`) reserva as mensagens pendentes das mais antigas para as mais novas, em lotes de 100 (`SELECT ... FOR UPDATE SKIP LOCKED`, então dois relays nunca pegam a mesma), entrega cada uma ao `EventTransport` e só então grava `published_at`.
3. O `EventTransport` que o relay recebe é o `EventTransportSelector`: a cada mensagem ele lê a **feature flag `event-transport`** (`redis` ou `rabbitmq`) e entrega o envelope ao transporte dela (HEL-13, design em [.design/event-transport-flag.md](.design/event-transport-flag.md)):
   - `redis` (o padrão): o `QueueEventTransport` põe na fila padrão (Redis) um job `DeliverEvent` **para cada consumidor inscrito** no nome do evento, com o envelope inteiro, e o `queue-worker`, que roda o Horizon, o executa;
   - `rabbitmq`: o `RabbitMqEventTransport` publica **uma** mensagem por evento na exchange `domain_events`, só retorna depois da confirmação do broker, e o broker copia a mensagem para a fila de cada consumidor. O `event-consumer` (`events:consume`) lê essas filas.
4. Nos dois lados a entrega segue as mesmas regras (`EventDelivery`, extraída do `DeliverEvent`): devolve ao `Context` o `request_id` da requisição que gravou o evento, reconstrói o evento (`fromPayload`) e chama o `handle()` do consumidor, que delega ao caso de uso. Esse caso de uso pode gravar o próximo evento da cadeia, de volta ao passo 1.

```
POST /api/orders ──► OrderPlaced ══► MarkOrderAsAwaitingPayment              placed → awaiting_payment

POST /api/orders/{order}/payment {card_token}
  Pagamento ──► PaymentGateway (fake) ──► payments (approved | declined)
             recusado: 402, pedido inalterado
  Pagamento ──► PaymentApproved ══► MarkOrderAsPaid                          awaiting_payment → payment_approved
                                        │
  Pedidos   ◄───────────────────────────┘──► OrderPaid
                                               │
  Entrega   ══► ScheduleOrderDelivery ──► DeliveryScheduled (data prevista)
                        │                       │
                        │       Pedidos ══► RecordEstimatedDelivery          grava orders.estimated_delivery_on, sem mudar o status
                        ▼
                 DeliverOrder (job, delay 10s) ──► OrderDelivered
                                                    │
  Pedidos   ══► MarkOrderAsDelivered ◄──────────────┘                        payment_approved → delivered

══►  gravado no outbox na transação ─► outbox-relay ─► (flag redis) DeliverEvent na fila ─► consumidor no queue-worker
                                                  └► (flag rabbitmq) mensagem na domain_events ─► fila do consumidor ─► event-consumer
```

| Classe | Parte | Tipo | O que faz |
| --- | --- | --- | --- |
| `Modules\Ordering\Events\OrderPlaced` | Pedidos | Evento `ordering.order_placed` (v1) | Gravado na transação do checkout |
| `Modules\Ordering\Core\Consumers\MarkOrderAsAwaitingPayment` | Pedidos | Consumidor | Move o pedido para `awaiting_payment` (`MarkOrderAsAwaitingPaymentUseCase`) |
| `Modules\Payment\Events\PaymentApproved` | Pagamento | Evento `payment.payment_approved` (v1) | Gravado pelo `PayOrderUseCase` na mesma transação da tentativa aprovada |
| `Modules\Ordering\Core\Consumers\MarkOrderAsPaid` | Pedidos | Consumidor | Move para `payment_approved` e grava `OrderPaid` na mesma transação (`MarkOrderAsPaidUseCase`) |
| `Modules\Ordering\Events\OrderPaid` | Pedidos | Evento `ordering.order_paid` (v1) | Gravado só pela transição que de fato marcou o pedido como pago |
| `Modules\Fulfillment\Core\Consumers\ScheduleOrderDelivery` | Entrega | Consumidor | Calcula a data prevista (`DeliveryCalendar`), registra no log, grava a linha de `deliveries` e o `DeliveryScheduled` na mesma transação (só quem criou a linha anuncia) e, depois do commit, agenda o job de entrega, que um `OrderPaid` repetido de um pedido ainda não entregue agenda de novo (`ScheduleDeliveryUseCase`) |
| `Modules\Fulfillment\Events\DeliveryScheduled` | Entrega | Evento `fulfillment.delivery_scheduled` (v1) | Carrega o id do pedido e a data prevista |
| `Modules\Ordering\Core\Consumers\RecordEstimatedDelivery` | Pedidos | Consumidor | Grava `estimated_delivery_on`, **só se estiver vazia**, sem tocar no status (`RecordEstimatedDeliveryUseCase`) |
| `Modules\Fulfillment\Core\Jobs\DeliverOrder` | Entrega | Job (`ShouldQueue`) | Após `ORDER_DELIVERY_DELAY_SECONDS`, simula a entrega: preenche `deliveries.delivered_at` e grava `OrderDelivered` na mesma transação, e um retry ou job repetido não faz nada (`DeliverOrderUseCase`) |
| `Modules\Fulfillment\Events\OrderDelivered` | Entrega | Evento `fulfillment.order_delivered` (v1) | Anuncia que a transportadora (fake) entregou o pedido |
| `Modules\Ordering\Core\Consumers\MarkOrderAsDelivered` | Pedidos | Consumidor | Move para `delivered` (`MarkOrderAsDeliveredUseCase`) |

A camada de publicar e consumir fica no módulo `Messaging` (`backend/app-modules/messaging`):

| Classe (`Modules\Messaging\...`) | O que faz |
| --- | --- |
| `Contracts\DomainEvent` | O contrato de todo evento de módulo: `eventName()`, `eventVersion()`, `toPayload()` e `fromPayload()` |
| `Contracts\EventOutbox` | Onde o caso de uso grava o evento (`record()`). Implementado pelo `Persistence\Repositories\OutboxMessageRepository`, que recusa a gravação sem transação aberta (`LogicException`) e um valor do payload que não seja nulo, escalar ou uma lista plana de escalares (`InvalidArgumentException`) |
| `Core\EventEnvelope` | A mensagem como ela viaja: `id`, `name`, `version`, `payload`, `occurredAt` e `requestId`, as mesmas chaves das colunas de `outbox_messages` |
| `Core\EventTransport` | A porta por onde o relay publica: retornar é aceitar, lançar exceção é recusar. Duas implementações: o `Core\QueueEventTransport` (a fila do Laravel) e o `Core\RabbitMq\RabbitMqEventTransport`. O que o container entrega à porta é o `Core\EventTransportSelector`, que escolhe uma delas pela flag |
| `Core\EventTransportFlag`, `Core\EventTransportSelector` | A flag `event-transport` (`redis`, o padrão, ou `rabbitmq`), lida e gravada pelo Pennant com o driver `database`, e o seletor que lê a flag a cada publicação e registra uma linha de log quando o transporte muda |
| `Contracts\EventSubscriptions` (a interface que os módulos usam) e `Core\EventSubscriptionRegistry` (a implementação, privada) | As inscrições, por nome de evento, e o **nome estável** de cada consumidor, dado como terceiro argumento do `subscribe()`. Recusa (`LogicException`) um consumidor que não implementa `EventConsumer` ou cujo `handle()` não recebe a classe inscrita, duas classes de evento com o mesmo nome, um nome de consumidor fora de `^[a-z]+\.[a-z_]+$`, um nome de dois consumidores e dois nomes de um consumidor |
| `Contracts\EventConsumer` | A interface dos consumidores, que ficam em `<módulo>/src/Core/Consumers` |
| `Core\EventDelivery`, `Core\DeliverEvent` | As regras de uma entrega (versão, `request_id`, reconstrução do evento, `handle()`), iguais nos dois transportes, e o job de uma entrega (mensagem, consumidor) da fila do Laravel, com `$tries = 3`, que as chama |
| `Core\RabbitMq\RabbitMqConnector`, `RabbitMqTopology`, `RabbitMqEnvelope`, `RabbitMqEventTransport`, `DeadDeliveries` | A conexão com o broker (toda falha diz `RabbitMQ ...`), a topologia (exchanges e filas), a mensagem e o corpo JSON, o transporte e a leitura e a devolução das entregas mortas |
| `Console\EventTransportCommand`, `ConsumeEventsCommand`, `FailedEventsCommand`, `RetryEventsCommand` | `events:transport`, `events:consume`, `events:failed` e `events:retry` |
| `Core\OutboxRelay`, `Console\RelayOutboxCommand` | O ciclo do relay e o processo `outbox:relay [--once]` |
| `Core\RestartSignal` | O sinal do `queue:restart` como um processo longo o enxerga (o relay e o `events:consume`): guarda o valor lido na partida e `changed()` fica verdadeiro quando o comando grava outro; com o cache ilegível (Redis fora) o processo segue e adota o sinal quando o cache voltar |
| `Persistence\Models\OutboxMessage` | Uma linha de `outbox_messages` |

- **O evento é gravado na transação da mudança, e gravar fora de uma é erro.** Antes, cada evento saía depois do commit, numa chamada separada à fila, e uma falha dela travava o pedido para sempre: em `placed` (sem o `OrderPlaced`), em `awaiting_payment` com o cliente já cobrado (sem o `PaymentApproved`) ou, no caso do `OrderPaid`, com a transição já gravada e o retry sem conseguir repeti-la. Agora a mensagem fica pendente até o transporte aceitar, e uma falha da fila só atrasa o pedido. No pagamento, a tentativa aprovada e o `PaymentApproved` entram juntos. Na marcação de pago, a transição e o `OrderPaid` entram juntos, e uma transição que não acontece não grava nada. O agendamento da entrega grava a linha de `deliveries` e a `DeliveryScheduled` juntas, a entrega preenche `delivered_at` e grava a `OrderDelivered` juntas, e o `DeliverOrder` entra na fila depois do commit da `DeliveryScheduled`.
- **Ninguém dispara evento pelo dispatcher do Laravel.** Nenhum evento de módulo tem listener do Laravel (o `bootstrap/app.php` não registra eventos), e o `DomainEventsTest` exige que toda classe de `Events` implemente `DomainEvent` e recusa, em `Modules`, o despacho de eventos do Laravel: o trait `Dispatchable` de eventos, a interface de despacho depois do commit, a facade `Event` e o helper `event()`.
- **O formato publicado não depende da classe PHP.** O nome é `<módulo>.<evento em snake_case>`, a versão é um inteiro a partir de 1 e o payload tem, por chave, nulo, um escalar ou uma lista plana de escalares (`category_ids`), em chaves snake_case, e as datas com hora vão como texto ISO 8601 com o fuso (`toIso8601String()`; objetos, arrays associativos e listas aninhadas são recusados na gravação): `order_id` em todos; `delivery_business_days` (inteiro) no `OrderPaid`; `estimated_delivery_on` (o dia de `America/Sao_Paulo`, em `Y-m-d`) no `DeliveryScheduled`. Mudar um payload é subir a versão. O `DeliverEvent` recusa uma versão que a classe inscrita não declara (`UnexpectedValueException`, com o nome e a versão) sem chamar o consumidor, e a entrega vai para o `failed_jobs`.
- **Cada módulo inscreve os próprios consumidores** no `boot()` do seu service provider, e o produtor nunca lista quem o consome: o `OrderingServiceProvider` inscreve os 4 do Ordering, o `FulfillmentServiceProvider`, o `ScheduleOrderDelivery`, e o `CatalogServiceProvider`, os 8 do projetor da vitrine (ver [O modelo de leitura da vitrine](#o-modelo-de-leitura-da-vitrine)).
- **Uma entrega por consumidor.** Se um consumidor falha, só a entrega dele é repetida (até 3 tentativas) e depois vai para o `failed_jobs`; os outros consumidores da mesma mensagem rodam uma vez. O `make queue-failed` lista a entrega como `<nome do evento> -> <consumidor>`. Como a entrega carrega o envelope inteiro, um `make queue-retry` funciona mesmo depois que a mensagem foi apagada.
- **A entrega acontece ao menos uma vez, e os consumidores são idempotentes** (HEL-16, design em [.design/event-idempotency.md](.design/event-idempotency.md)). Se o relay cair entre o transporte aceitar e a mensagem ser marcada, ela sai de novo, e um `basic_reject` com requeue ou um `queue:retry` também a entrega outra vez. Não há inbox: cada ponto assíncrono declara a sua **chave natural**, e a segunda entrega não muda o banco nem grava mensagem nova no outbox:
  - `MarkOrderAsAwaitingPayment`, `MarkOrderAsPaid` e `MarkOrderAsDelivered`: a transição condicional de `orders.status` (`UPDATE ... WHERE status = :from`); o `OrderPaid` só sai da transição que aconteceu.
  - `RecordEstimatedDelivery`: a primeira gravação de `orders.estimated_delivery_on`.
  - `ScheduleOrderDelivery` e o job `DeliverOrder`: a linha de `deliveries`, única por pedido. Só a escrita que mudou uma linha (a criação, ou o `delivered_at` preenchido pela primeira vez) grava o evento, na mesma transação. O `DeliverOrder` é enfileirado depois do commit e pode se repetir: uma falha ao enfileirar é reparada pela reentrega do `OrderPaid`, e um job duplicado vira um no-op. Um `DeliverOrder` de um pedido sem linha (só um job enfileirado antes da tabela existir, num banco de desenvolvimento) não muda nada: o pedido continua pago, e gravar a linha fica para quando um backfill de produção precisar.
  - **Todo consumidor novo declara a sua chave natural e ganha um teste que entrega o mesmo envelope duas vezes pelo `EventDelivery`** (`ConsumerRedeliveryTest`). O `EventDelivery` não deduplica por id de mensagem; um consumidor sem chave natural pediria um inbox, como decisão nova.
  A ordem de entrega é só a melhor possível, e nenhum consumidor depende dela.
- **O relay**: sem mensagem pendente, espera 500 ms; na primeira falha do transporte, para o ciclo, deixa aquela mensagem e as seguintes pendentes, registra um `warning` no log (com o `message_id`, o `name` e o erro), imprime uma linha de aviso na própria saída (`make logs-relay`) e tenta de novo depois de 5 s. Com `--once`, roda um ciclo e sai com `0`, ou `1` se o transporte recusou uma mensagem. Ele termina com `0`, depois do ciclo em andamento, quando o `php artisan queue:restart` roda (o mesmo sinal no cache que os workers usam), e o container o sobe de novo. Com o Redis fora, o cache também está, e o relay segue tentando publicar até ele voltar.
- **Retenção**: as mensagens publicadas ficam 7 dias e são apagadas pelo relay ao iniciar e no máximo uma vez por hora. As pendentes nunca são apagadas. A tabela não é histórico e não serve para replay.
- **O `request_id` atravessa o relay**: o `EventOutbox` grava o `request_id` do `Context` na mensagem, e o `DeliverEvent` o devolve ao `Context` antes do consumidor (ou o limpa, se a mensagem não tem um). Assim os logs do consumidor e os eventos que ele grava levam o `request_id` da requisição original, por toda a cadeia.
- **A entrega escuta `OrderPaid`, não `PaymentApproved`.** Se escutasse direto o pagamento, o job de entrega correria em paralelo com a marcação de pago. Num retry ou atraso da fila, ele poderia rodar antes e o pedido ficaria preso em `payment_approved`.
- Pagamento e entrega **nunca** alteram o pedido: quem escreve em `orders` são os consumidores da parte de pedidos (o status e a data prevista).
- **Os eventos e o job carregam dados, nunca o model**: `OrderPlaced`, `PaymentApproved`, `OrderDelivered` e `DeliverOrder` levam o `orderId`; o `OrderPaid` leva o `orderId` e os `deliveryBusinessDays` prometidos no checkout; o `DeliveryScheduled` leva o `orderId` e a data prevista. Os casos de uso do Ordering carregam o pedido pelo id (`OrderRepository::findOrFail`), e o Fulfillment agenda a entrega sem ler o pedido (a linha de `deliveries` é dele). Um teste de reflexão (`OrderEventPayloadTest`) falha se um deles voltar a ter um model.
- **A data prevista** conta os dias úteis prometidos no pedido (`delivery_business_days`, que chegam ao Fulfillment pelo `OrderPaid`) a partir do momento em que o Fulfillment trata o `OrderPaid`, em segunda a sexta e no fuso `America/Sao_Paulo` (uma compra às 23h30 de quarta, que já é quinta em UTC, conta a partir de quarta; sábado e domingo contam a partir de segunda). Feriados não são considerados. A primeira gravação vale: o Fulfillment guarda a primeira data em `deliveries.estimated_delivery_on` e não recalcula nem reanuncia, e o Ordering guarda a primeira que recebe; um retry ou um `DeliveryScheduled` repetido não muda a data, e um pedido que já foi entregue ainda recebe a sua data.
- O `DeliverOrder` continua entregando depois do delay (`ORDER_DELIVERY_DELAY_SECONDS`), mesmo antes da data prevista: a entrega é simulada. Ele é um comando interno do Fulfillment, e não um evento, então continua um job do Laravel, fora do outbox.
- Consumidores e job são adaptadores finos: só delegam para o caso de uso correspondente.
- Todas as transições usam `OrderRepository::transitionStatus($order, $from, $to)`, um `UPDATE ... WHERE status = :from`: se uma entrega for executada duas vezes ou fora de ordem, ela simplesmente não faz nada (**idempotência**). Por isso um `PaymentApproved` duplicado gera um único `OrderPaid`. Um retry do `DeliverOrder` nem chega a anunciar a entrega de novo: o `delivered_at` já preenchido o torna um no-op no Fulfillment, e o `OrderDelivered` sai uma só vez.
- O endpoint de pagamento responde **`202 Accepted`** na aprovação, com a tentativa de pagamento, pois a mudança de status acontece de forma assíncrona. O frontend faz *polling* da página do pedido para mostrar a timeline sendo atualizada.
- O administrador **não altera o status manualmente**: ele é controlado exclusivamente pelos eventos e jobs.

### O transporte dos eventos: Redis ou RabbitMQ

A HEL-13 pôs uma segunda implementação atrás da porta `EventTransport`, para comparar a fila do Laravel com o RabbitMQ no mesmo código e ver um consumidor rodar fora dos jobs do Laravel. Os casos de uso, os eventos e os consumidores não mudaram: só o que o relay faz com o envelope.

- **A flag.** O valor global de `event-transport` vive na tabela `features` do Pennant (`laravel/pennant`, driver `database`), então a flag lê e muda mesmo com o Redis fora. Sem nenhuma linha, vale `redis`. `make event-transport` mostra o valor; `make event-transport TO=rabbitmq` (ou `TO=redis`) o troca, sem deploy, e escreve no log uma linha `info` com `previous`, `current` e `changed_by` (o `USER` do host). Outro valor sai com código `1` e lista os aceitos; o valor igual ao atual só avisa que já é aquele, sem gravar nem registrar. O relay limpa o cache em memória do Pennant a cada ciclo, então obedece à troca no ciclo seguinte, sem reiniciar, e registra uma linha de log, uma vez por troca, com o transporte novo.
- **Mensagens em trânsito na troca.** O que já está na fila do Laravel continua sendo executado pelo `queue-worker`, e o que já está nas filas do RabbitMQ continua sendo lido pelo `event-consumer`: os dois processos rodam sempre. Se o relay cair depois de o transporte aceitar e antes de marcar a mensagem, ela sai de novo, inclusive pelo outro transporte, e as guardas dos consumidores a absorvem (a entrega é ao menos uma vez).
- **O nome estável do consumidor.** Cada consumidor é inscrito com um nome (`$subscriptions->subscribe(PaymentApproved::class, MarkOrderAsPaid::class, 'ordering.mark_order_as_paid')`) que dá nome à fila dele, e por isso sobrevive a qualquer troca de namespace ou de classe. São 13: os 5 do ciclo do pedido (`ordering.mark_order_as_awaiting_payment`, `ordering.mark_order_as_paid`, `ordering.record_estimated_delivery`, `ordering.mark_order_as_delivered` e `fulfillment.schedule_order_delivery`) e os 8 do projetor da vitrine (`catalog.project_*`, listados em [O modelo de leitura da vitrine](#o-modelo-de-leitura-da-vitrine)).
- **A topologia no RabbitMQ.** O `RabbitMqEventTransport` a declara na primeira publicação de cada conexão, e o `events:consume`, ao subir (declarar de novo não muda nada):

  | Objeto | Como é |
  | --- | --- |
  | Exchange `domain_events` | `topic`, durável. O relay publica nela, com o nome do evento como routing key |
  | Exchange `domain_events.dead` | `direct`, durável. Recebe a entrega que falhou 3 vezes |
  | Fila `<nome do consumidor>` | `quorum`, com `x-delivery-limit` 2, `x-dead-letter-exchange` `domain_events.dead`, `x-dead-letter-routing-key` o nome do consumidor, `x-dead-letter-strategy` `at-least-once` e `x-overflow` `reject-publish`. Ligada à `domain_events` pelo nome de cada evento que o consumidor assina |
  | Fila `<nome do consumidor>.dead` | `quorum`, sem validade nem TTL, ligada à `domain_events.dead` pelo nome do consumidor. A entrega morta fica ali até o `make events-retry` ou o descarte à mão |

- **A mensagem.** Uma por evento, persistente (`delivery_mode` 2), `content_type` `application/json`, `message_id` igual ao `id` do envelope e `type` igual ao nome do evento. O corpo é um JSON com exatamente as chaves `id`, `name`, `version`, `payload`, `occurred_at` (em UTC, `Y-m-d\TH:i:s.u\Z`) e `request_id`. O relay só marca a mensagem como publicada depois da confirmação do broker (espera de até 5 s); se o broker estiver fora, recusar a mensagem ou não confirmar, a mensagem continua pendente. Quem lê o corpo fora do PHP só precisa desse formato. A `occurred_at` guarda segundos, a precisão da coluna do outbox.
- **O `event-consumer`** (`php artisan events:consume`): um canal, um consumidor por fila, prefetch 1 por consumidor (no máximo uma entrega sem confirmação por fila) e as entregas tratadas uma de cada vez. O broker não segura as outras filas enquanto uma entrega está em andamento: um limite de prefetch do canal inteiro não vale para filas `quorum` (visto no RabbitMQ 4.3.5), então cada fila pode ter a sua próxima entrega já recebida, à espera. Confirma (`basic_ack`) depois de o `handle()` retornar. Se ele lança, devolve a entrega à fila (`basic_reject` com requeue) e registra um `warning` com `message_id`, `name`, `consumer`, `attempt` e `error`; o broker conta as devoluções e, na terceira falha, move a entrega para `<consumidor>.dead`. Uma versão que a classe inscrita não declara, um corpo que não é o JSON acima e uma conexão que cai com a entrega em aberto contam como falhas do mesmo jeito. As tentativas são imediatas, sem espera, como as do `DeliverEvent`. Sem o broker, tenta conectar de novo a cada 5 segundos. Como o relay e os workers, termina com `0`, depois da entrega em andamento, quando o `make queue-restart` muda o sinal `illuminate:queue:restart`, e o container o sobe de novo.
- **As entregas mortas.** `make events-failed` lista cada uma (consumidor, evento, `message_id`, `occurred_at`, `request_id` e o número de entregas) sem tirá-las da fila, ou imprime `Nenhuma entrega morta.`. `make events-retry` devolve todas, cada uma só à fila do consumidor dela (publica, espera a confirmação e só então a remove da fila de mortas, então uma parada no meio nunca perde uma entrega), e `make events-retry ID=<uuid>` devolve só as de uma mensagem, ou sai com `1` se nenhuma tem esse id. Uma entrega devolvida que falha 3 vezes de novo volta à fila de mortas. Os dois comandos saem com `1` e dizem `RabbitMQ ...` se o broker estiver fora.
- **O painel do broker** está em `http://localhost:15672` (usuário e senha do `.env`): filas, mensagens, consumidores e a topologia.
- **Mudar a fila.** Os argumentos de uma fila não mudam depois de declarada (o broker recusa a declaração com `PRECONDITION_FAILED`): mudar o limite de entregas ou a fila de mortas pede apagar o volume do `rabbitmq` (`docker compose down` e `docker volume rm ecommerce_rabbitmq_data`; em dev, o `make fresh` não basta). A topologia só acrescenta: a fila de um consumidor removido do código continua no broker, com as mensagens que tiver, porque apagá-la sozinho apagaria também a de um assinante de fora. Para apagá-la à mão, `docker compose exec rabbitmq rabbitmqctl delete_queue <nome>` e o mesmo com `<nome>.dead`.
- **Horizon.** O container `queue-worker` roda `php artisan horizon` com um supervisor na conexão `redis`, na fila `default`, com os mesmos parâmetros do antigo `queue:work` (1 processo, 3 tentativas, `timeout` de 60 s, `sleep` de 1 s). O painel está em `http://localhost:8080/horizon` e abre só para a equipe com o papel `admin`, autenticada na sessão `staff` (o mesmo alias `admin` das rotas de gestão da equipe): o `support`, um cliente ou um visitante recebem `403`, em qualquer ambiente. O container `scheduler` roda `php artisan schedule:work`, e o agendamento (`routes/console.php`) roda o `horizon:snapshot` a cada 5 minutos, para as métricas do painel. O `make queue-restart` termina o Horizon (`horizon:terminate`) para ele subir com o código novo.
- **Fora do RabbitMQ.** O `DeliverOrder` (o job da entrega com delay) continua na fila do Laravel: o RabbitMQ não atrasa mensagem sem plugin, e ele é um comando interno do Fulfillment. Também não há trace nem métricas do `event-consumer` (ficam para a HEL-8), nem inbox, espera crescente entre tentativas ou um processo por consumidor.

Acompanhando a fila e o outbox:

```bash
make logs-worker     # logs do container queue-worker
make logs-relay      # logs do container outbox-relay
make logs-consumer   # logs do container event-consumer (RabbitMQ)
make queue           # worker extra em primeiro plano
make queue-failed    # entregas e jobs que falharam (fila do Laravel)
make queue-retry     # reprocessar falhas (fila do Laravel)
make event-transport # mostra o transporte; TO=rabbitmq ou TO=redis o troca
make events-failed   # entregas mortas do RabbitMQ
make events-retry    # devolve as mortas aos consumidores
```

> Depois de alterar código de consumidores, jobs ou do relay, rode `make queue-restart` — o Horizon, o relay e o consumidor do RabbitMQ mantêm o código carregado em memória.

**Deploy que muda o formato de um evento ou job** (como a HEL-7, que trocou o model `Order` pelo `orderId`, a HEL-11, que trocou o `orderId` de inteiro para UUID em texto, e a HEL-9, que trocou os listeners pelos consumidores e as entregas `DeliverEvent`): o `queue-restart` só troca o código do Horizon, do relay e do consumidor do RabbitMQ. As mensagens que já estão nas filas do RabbitMQ também (o formato e os argumentos de uma fila não mudam por deploy: ver acima como apagar o volume do `rabbitmq`). Os jobs que já estão na fila, inclusive os `DeliverOrder` com delay, continuam no formato antigo e falham no código novo (na HEL-7, com `Typed property ...::$orderId must not be accessed before initialization`; na HEL-11, com um erro de tipo no `orderId`; na HEL-9, porque a classe do listener mudou de lugar); o retry repete o erro e o pedido fica parado. Antes de subir o código, esvazie a fila e o outbox com o código antigo:

```bash
make artisan CMD="down"
```

```bash
docker compose exec queue-worker php artisan queue:work redis --force --tries=3 --max-time=30
```

```bash
make artisan CMD="queue:monitor redis:default"
```

```bash
make db-shell
```

O `down` faz a API responder `503`, então nenhum pedido novo grava mensagem. O relay continua publicando as pendentes durante a manutenção, e o Horizon do container não processa jobs em manutenção, por isso o segundo comando (um `queue:work` avulso) usa `--force` e para sozinho depois de 30 segundos (mais que o `ORDER_DELIVERY_DELAY_SECONDS` padrão). Repita-o até o `queue:monitor` mostrar `[0]` (no Redis, essa contagem inclui os jobs com delay e os reservados) e, no `psql` do `make db-shell`, `select count(*) from outbox_messages where published_at is null;` responder `0`. Depois suba o código, rode `make queue-restart` e `make artisan CMD="up"`. Mudar o payload de um evento com mensagens em trânsito pede uma versão nova e a leitura das duas versões, que ainda não existe (só a versão atual de cada evento é aceita). Com a flag em `rabbitmq`, esvazie também as filas do RabbitMQ antes do deploy (o `make events-failed` mostra as mortas, e o painel em `http://localhost:15672`, as filas). No ambiente local, `make fresh` e `make queue-restart` bastam.

---

## Regra de disponibilidade do produto

Um produto só pode ser comprado quando **`status = active` E estoque > 0**. A única fonte da regra no backend é o `Modules\Catalog\Core\ValueObjects\ProductAvailability` (HEL-6; antes ela morava no `PurchaseAvailabilityService` do Ordering, o que obrigava o Catalog e o Inventory a depender de um módulo que depende deles):

- `isAvailable()`: combina o status do catálogo com as unidades do estoque.
- `availableQuantity()`: as unidades do estoque, também para um produto inativo (`0` quando o produto não tem linha de estoque).

O value object recebe dados, não models. O `CatalogProduct` (o que o contrato `ProductCatalog` devolve) responde `availability($unitsInStock)`, e cada chamador diz de onde vêm as unidades: o carrinho lê as quantidades pelo `StockLevels`, o checkout pelas linhas bloqueadas (`FOR UPDATE`) do `StockReservation`, o cadastro de produto do admin pelo `ProductStocks` que o `StockLevels` devolve, a vitrine pela parte `stock` do documento no modelo de leitura (o `ProductView` da `StorefrontQuery`, desde a HEL-17) e a lista de estoque do admin pela própria linha. Um produto sem linha de estoque tem 0 unidades e não pode ser comprado. O catálogo público, o detalhe do produto, a validação do carrinho, o checkout e a tela de estoque do admin leem todos a regra dali, e ela não é copiada em nenhum outro lugar. O Ordering fica só com as mensagens: o `PurchaseAvailabilityService` (nome mantido na HEL-6) devolve `Produto indisponível.` ou `Estoque insuficiente. Disponível: N.` sobre um `ProductAvailability`.

- Na listagem pública (`/products`) todos os produtos aparecem; os indisponíveis ficam esmaecidos, não são clicáveis, não podem ser adicionados ao carrinho e mostram "Sem estoque" ou "Indisponível".
- `GET /api/products/{product}` responde `404 — Produto indisponível.` para produtos inativos ou sem estoque, e `404 — Recurso não encontrado.` para um id desconhecido. As duas rotas da vitrine leem o MongoDB pelo `api-read` e respondem `503 SERVICE_UNAVAILABLE` (`Este recurso está indisponível no momento.`) quando ele ou o `api-read` não respondem.
- Quando o estoque chega a zero, o produto deixa de estar disponível automaticamente; quando volta a ser maior que zero, só fica disponível se estiver ativo.
- O frontend apenas reflete o campo `is_available` calculado pela API; checkout e validação do carrinho sempre revalidam no backend.

---

## API REST

As rotas de cada módulo ficam em `backend/app-modules/<módulo>/routes/api.php`, dentro de `Route::prefix('api')->middleware('api')`, com prefixo `/api`. **Todo id de registro** (`id` e qualquer `*_id`, no caminho, no corpo e na resposta) é um UUID v7 em texto, na forma canônica (minúsculas, com hífens); o pedido também traz o `code` de 8 símbolos. Um id malformado no caminho (`/api/products/1`, um UUID em maiúsculas) responde `404`, sem nenhuma query, e no corpo responde `422` no campo. Erros seguem sempre o formato (montado por `Modules\Shared\Http\Responses\ApiErrorResponse`):

```json
{
  "code": "INSUFFICIENT_STOCK",
  "message": "Estoque insuficiente para um ou mais produtos.",
  "errors": { "items.12": ["Estoque insuficiente. Disponível: 2."] },
  "request_id": "9d3c6f0e-6a4f-4c43-9a43-1f0b5e1f2a7b"
}
```

- `code`: identificador estável do erro (enum `Modules\Shared\Enums\ApiErrorCode`). O frontend deve decidir pelo `code`, nunca pelo texto de `message`. Valores: `VALIDATION_FAILED`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `CSRF_TOKEN_MISMATCH`, `TOO_MANY_REQUESTS`, `BUSINESS_RULE_VIOLATION`, `INSUFFICIENT_STOCK`, `PAYMENT_DECLINED` (402, pagamento recusado pelo gateway), `SERVICE_UNAVAILABLE` (503, o módulo que atende a porta de um módulo não está instalado; a mensagem é `Este recurso está indisponível no momento.`), `HTTP_ERROR`, `SERVER_ERROR`.
- `errors`: mensagens por campo (lista de strings); `{}` quando o erro não é de um campo específico.
- `request_id`: o mesmo valor do header `X-Request-ID`, presente em **todas** as respostas. É gerado pelo middleware `AssignRequestId` (ou reaproveitado do header enviado por um proxy, se for seguro) e gravado no `Context` do Laravel, então aparece em todas as linhas de log da requisição e dos jobs enfileirados por ela.
- Respostas de erro são enviadas com `Cache-Control: private, no-store`.

| Método | Rota | Acesso | Descrição |
| --- | --- | --- | --- |
| GET | `/sanctum/csrf-cookie` | público | Inicializa o cookie CSRF (antes do login) |
| POST | `/api/auth/register` | público | Cadastro de cliente (cria em `customers`; `201`, `422`, `429`) |
| POST | `/api/auth/login` | público | Login do cliente (sessão por cookie; guard `customer`) |
| POST | `/api/auth/logout` | cliente | Logout (encerra a sessão do navegador, as duas áreas) |
| GET | `/api/auth/me` | cliente | Cliente autenticado: `id`, `name`, `email`, `created_at` (sem `role`) |
| GET | `/api/products?category=&sort=&page=` | público | Listagem paginada; `sort`: `name`, `price_asc`, `price_desc`; `page`: de 1 a 1.000.000 (acima disso, `422`) |
| GET | `/api/products/{product}` | público | Detalhe (somente disponíveis) |
| GET | `/api/categories` | público | Categorias |
| GET | `/api/shipping/quote?state=UF` | público | Frete e prazo de uma UF: `data: { state, price_cents, delivery_business_days }`. A UF é normalizada (`sp` → `SP`); `422` se ausente ou desconhecida; `throttle:60,1,shipping-quote` |
| POST | `/api/cart/validate` | público | Revalida o carrinho |
| POST | `/api/orders` | cliente | Checkout: `items` e `address_id` (um endereço do próprio cliente). `201` com `code`, `items_total_cents`, `shipping_cents`, `total_cents` e `delivery`; `422` em `address_id` se faltar (`Escolha um endereço de entrega.`) ou não for do cliente (`Endereço de entrega não encontrado.`); `throttle:20,1,checkout` |
| GET | `/api/orders` | cliente | Pedidos do cliente |
| GET | `/api/orders/{order}` | dono | Detalhe do pedido (com o bloco `delivery`): `200`, `404` (inclusive o pedido de outro cliente) |
| POST | `/api/orders/{order}/payment` | dono | Paga com `card_token` (gateway fake): `202` aprovado, com a tentativa em `data: { id, order_id, status, amount_cents }`; `402 PAYMENT_DECLINED` recusado; `404` pedido inexistente ou de outro cliente; `409` se não pode ser pago; `422` do corpo, validado antes de buscar o pedido; `503 SERVICE_UNAVAILABLE` se o Ordering não está instalado; `throttle:20,1,payment` |
| GET | `/api/account` | cliente | Resumo da área do cliente: `customer`, `orders_count`, `last_order`, `recent_orders` (até 5). Cada pedido vem resumido: `id`, `code`, `status`, `status_label`, `total_cents`, `created_at` |
| PUT | `/api/account/profile` | cliente | Atualiza nome, e-mail e senha |
| GET | `/api/account/addresses` | cliente | O caderno do cliente, o mais recente primeiro |
| POST | `/api/account/addresses` | cliente | Cadastra um endereço (`recipient_name`, `postal_code`, `street`, `number`, `complement?`, `district`, `city`, `state`): `201`, `409 BUSINESS_RULE_VIOLATION` no 11º endereço, `422` |
| PUT | `/api/account/addresses/{address}` | dono | Substitui o endereço (mesmos campos do `POST`): `200`, `404` (inclusive o endereço de outro cliente), `422` (validado antes de buscar o endereço) |
| DELETE | `/api/account/addresses/{address}` | dono | Exclui o endereço, sem lê-lo antes: `204`, `404` (inclusive o endereço de outro cliente). Pedidos já feitos não mudam |
| POST | `/api/admin/auth/login` | público | Login da equipe (guard `staff`, contra `users`): `id`, `name`, `email`, `role`, `role_label`, `created_at`; `throttle:10,1,staff-login` |
| POST | `/api/admin/auth/logout` | equipe | Logout (encerra a sessão do navegador, as duas áreas) |
| GET | `/api/admin/auth/me` | equipe | Membro da equipe autenticado |
| GET | `/api/admin/dashboard` | equipe | Métricas agregadas |
| GET/POST | `/api/admin/products` | equipe | Lista / cria (com estoque inicial) |
| GET/PUT | `/api/admin/products/{product}` | equipe | Detalhe / edita (sem estoque) |
| DELETE | `/api/admin/products/{product}` | **admin** | Exclui |
| PATCH | `/api/admin/products/{product}/status` | equipe | Ativa / desativa |
| GET/POST | `/api/admin/categories` | equipe | Lista / cria |
| GET/PUT | `/api/admin/categories/{category}` | equipe | Detalhe / edita |
| DELETE | `/api/admin/categories/{category}` | **admin** | Exclui |
| GET | `/api/admin/stocks` | equipe | Lista estoques |
| PUT | `/api/admin/stocks/{stock}` | equipe | `{ "operation": "increase" \| "decrease", "quantity": 5 }` |
| GET/POST | `/api/admin/customers` | equipe | Lista clientes (15 por página, com `orders_count`) / cria cliente |
| GET/PUT | `/api/admin/customers/{customer}` | equipe | Detalhe (10 pedidos mais recentes em `orders`, resumidos como na conta) / edita cliente; `404` para cliente inexistente. Não há `DELETE` (`405`) |
| GET/POST | `/api/admin/users` | **admin** | Lista / cria membro da equipe (`role`: `admin` ou `support`) |
| GET/PUT/DELETE | `/api/admin/users/{user}` | **admin** | Detalhe / edita / remove membro da equipe. O admin não muda o próprio papel nem remove a própria conta (`409`) |
| GET | `/api/admin/orders` | equipe | Lista pedidos |
| GET | `/api/admin/orders/{order}` | equipe | Detalhe do pedido |

"Equipe" é qualquer sessão do guard `staff` (admin ou suporte); **admin** exige o papel `admin` (middleware `admin`, `EnsureUserIsAdmin`) e responde `403` ao suporte. Uma sessão de cliente nas rotas `/api/admin/*`, ou uma sessão de equipe nas rotas da loja, recebe `401`.

Códigos HTTP usados: `200`, `201`, `202`, `204`, `401`, `402`, `403`, `404`, `405`, `409`, `422`, `429`, `500`. O `403` existe só para o suporte nos `DELETE` e em `/api/admin/users*`.

**Limites de requisição**: cada limite é literal na rota, no `app-modules/<módulo>/routes/api.php` do módulo dono, e cada rota tem o seu contador, nomeado no terceiro parâmetro do `throttle`: `throttle:10,1,registration` no cadastro, `throttle:10,1,customer-login` e `throttle:10,1,staff-login` nos dois logins, `throttle:20,1,checkout` e `throttle:20,1,payment` no checkout e no pagamento, e `throttle:60,1,cart-validation` e `throttle:60,1,shipping-quote` na validação do carrinho e na cotação de frete. Acima do limite, a resposta é `429` com `TOO_MANY_REQUESTS`. A requisição é contada antes de ser conferida: o `throttle` é o `AtomicThrottleRequests`, que soma 1 ao contador e decide pela contagem que recebeu de volta, dada por um só comando do Redis (`AtomicRateLimiter`). Numa rajada de requisições simultâneas passa exatamente o limite. Uma requisição recusada também conta, sem esticar a janela, que termina um minuto depois do primeiro acerto. A chave do contador é o nome da rota mais o cliente: o cliente autenticado (pelo id) ou, sem sessão, o **IP da conexão**, o `REMOTE_ADDR` que o Nginx entrega ao PHP-FPM. Cada rota conta só as próprias requisições: 60 cotações de frete no mesmo minuto deixam a 61ª em `429`, e o login do mesmo IP continua com as 10 tentativas. O Laravel não confia em nenhum proxy, então um `X-Forwarded-For` enviado pelo cliente (e também `X-Forwarded-Host`, `-Proto`, `-Port` e `-Prefix`) é ignorado e não troca a chave do limite (ver [Containers](#containers)). Uma rota nova com `throttle` nomeia um contador que nenhuma outra usa: sem o nome, ela somaria no contador que o Laravel monta só com o cliente. O `RateLimitTest` cobra (ver [Escopo e decisões](#escopo-e-decisões)).

**Registros nas rotas** (HEL-11): nenhuma rota resolve registro. O controller recebe o id como `string` e o entrega ao use case (escrita) ou ao repositório ou à consulta (leitura), então:

- o **corpo é validado antes da existência**: corpo inválido é `422` para qualquer id (do dono, de outro cliente ou desconhecido), e corpo válido com id desconhecido é `404` com `NOT_FOUND` e 'Recurso não encontrado.';
- nas rotas do cliente com registro (pedido, pagamento e endereço), o **dono entra na busca**: o registro de outro cliente responde `404` com o corpo de um id desconhecido, antes de qualquer conferência de status (nunca `409`). Não há mais `403` nessas rotas, porque ele confirmaria a quem não é dono que o registro existe;
- os `DELETE` de produto, categoria, membro da equipe e endereço, e o ajuste de estoque, vão direto à escrita (ou à leitura com lock), sem ler o registro antes: uma query a menos. O `PUT /api/admin/customers/{customer}` também lê o cliente uma vez só.

**Endereços e frete na API**

- O CEP é aceito como `12345678` ou `12345-678` e **sempre volta com 8 dígitos**, sem hífen (a formatação é da tela). A UF é normalizada antes de validar.
- O recurso `Order` (loja e admin, detalhe e listagens) ganhou `items_total_cents`, `shipping_cents` (`total_cents` = os dois somados) e `delivery: { business_days, estimated_on, address }`. `estimated_on` é um dia (`"2026-10-09"`), `null` até o pagamento ser aprovado, e `address` é a cópia que o pedido guardou (`recipient_name`, `postal_code`, `street`, `number`, `complement`, `district`, `city`, `state`).
- O frete vem da tabela de `config/shop.php` (`shipping_rates`), uma linha por UF: SP R$ 15,00 em 2 dias úteis; RJ, MG e ES R$ 22,00 em 4; PR, SC e RS R$ 25,00 em 5; DF, GO, MT e MS R$ 30,00 em 6; BA, SE, AL, PE, PB, RN, CE, PI e MA R$ 38,00 em 8; PA, AP, AM, RR, AC, RO e TO R$ 45,00 em 10. Mudar um valor é uma mudança de configuração com deploy.

> **Nota:** a validação do carrinho usa `POST /api/cart/validate` (em vez de `GET`) porque envia uma lista de itens no corpo da requisição — um `GET` com corpo ou com arrays aninhados na query string seria frágil.

Para listar as rotas: `make artisan CMD="route:list --path=api"`.

---

## Frontend

| Rota | Layout | Acesso |
| --- | --- | --- |
| `/products`, `/products/:id`, `/cart` | PublicLayout | público |
| `/checkout`, `/payment/:orderId` | PublicLayout | cliente |
| `/login`, `/register` | AuthLayout | visitante da loja |
| `/admin/login` | AuthLayout | visitante do admin |
| `/account`, `/account/profile`, `/account/orders`, `/account/orders/:id`, `/account/addresses` | CustomerLayout | cliente |
| `/admin`, `/admin/products`, `/admin/categories`, `/admin/stocks`, `/admin/customers` (e `/new`, `/:id`, `/:id/edit`), `/admin/orders` | AdminLayout | equipe (admin ou suporte) |
| `/admin/users` (e `/new`, `/:id/edit`) | AdminLayout | só o papel `admin` (o suporte vai para `/forbidden`) |

- **Ids nas URLs:** as 8 rotas com id (`product`, `payment`, `account.order`, `admin.products.edit`, `admin.customers.show`, `admin.customers.edit`, `admin.users.edit` e `admin.orders.show`) só casam um UUID canônico, com `sensitive: true` (o roteador casaria um UUID em maiúsculas sem isso). Qualquer outra URL (`/products/7`) cai na página de não encontrado, e a página recebe o id como `string`. Os tipos (`types/index.ts`) declaram todo `id` e `*_id` como `string`.
- **Código do pedido:** toda tela que mostrava `#<id>` mostra `#<code>` (listas e detalhes do cliente e do admin, painel da conta, pagamento, o aviso de pedido criado e o detalhe do cliente no admin). A URL continua com o id.
- **Carrinho:** fica no `localStorage` em `cart-v3`. Um carrinho salvo com ids numéricos em `cart-v2` é descartado ao carregar, e o carrinho começa vazio.

- **`src/services/api.ts`**: instância central do Axios (`withCredentials` + `withXSRFToken`) com tratamento global de erros — `401` (limpa a sessão da área e vai para o login dela: `admin/*` vai para `/admin/login`, o resto para `/login`; `auth/*` e `admin/auth/*` não redirecionam, porque a checagem de sessão de um visitante falha por definição), `403` (aviso + página de acesso negado), `5xx`/rede (aviso). `404`, `409` e `422` são tratados pelas páginas (mensagens e erros por campo).
- **Pinia**: `auth` (cliente autenticado), `staff` (membro da equipe autenticado, com `canDelete` e `canManageStaff` para o papel `admin`), ambos sem tokens, `cart` (carrinho persistido) e `notifications` (toasts).
- **Router**: a decisão está em `router/guards.ts` (`guardRedirect`), por `meta.requiresShopper`, `guestOnly`, `requiresStaff`, `staffGuestOnly` e `requiresAdminRole`; as rotas ficam em `router/routes.ts`. Cada área carrega só a própria sessão (`auth/me` ou `admin/auth/me`). O menu do admin vem de `utils/adminMenu.ts`; o item "Usuários" e os botões "Excluir" só aparecem ao papel `admin`. É apenas experiência de uso — a API aplica as mesmas regras.
- **Endereços e checkout**: o caderno ("Minha conta → Endereços", `AddressBookPage`) usa o composable `useAddressBook` (listar, salvar, excluir com `window.confirm`, erros por campo no `422` e a mensagem do `409` acima do formulário), e o checkout usa o `useCheckout` (endereço escolhido, frete, subtotal/frete/total exibidos, bloqueio da confirmação enquanto o frete é desconhecido, `422` em `address_id` que recarrega o caderno). O formulário (`AddressForm`) tem a UF como lista das 27 siglas. O detalhe do pedido da loja e do admin mostra o endereço (CEP como `01310-100`), Subtotal, Frete e Total e o texto de entrega (`utils/delivery`): o prazo em dias úteis antes do pagamento, "Entrega prevista: dd/mm/aaaa" depois dele e "Pedido entregue" no fim. O dia vem como `"YYYY-MM-DD"` e é formatado sem passar por `new Date()` (`formatDay`), porque o `Date` leria a data como meia-noite UTC e mostraria o dia anterior em São Paulo.
- Filtros, ordenação e página da listagem ficam na URL (`/products?category=games&sort=price_desc&page=2`) e são processados pelo backend.

---

## Testes

```bash
make test                              # todos os testes
make test-filter FILTER=CheckoutTest   # apenas um arquivo/teste
make pint                              # estilo do backend (Pint, sem corrigir)
make test-frontend                     # testes do frontend (Vitest)
```

O Vitest cobre a conversão de dinheiro (`utils/money`), o carrinho, o pagamento com cartão de teste (`composables/usePayment`), o caderno de endereços (`composables/useAddressBook`), o checkout com endereço e frete (`composables/useCheckout`), o texto de entrega, a data só-dia, o CEP, as UFs e o menu da conta (`utils/delivery`, `utils/date`, `utils/postalCode`, `utils/states`, `utils/accountMenu`), os erros tratados globalmente (`services/api`: `401` por área, `403`, `419`, `429` e `5xx`; os demais ficam com cada página), a decisão do guard do router (inclusive a rota do caderno) e o menu do admin (`router/guards`, `utils/adminMenu`) e as permissões por papel (`stores/staff`, `stores/auth`). A lógica é testada sem DOM; o arranjo visual das telas é conferido no navegador. O CI roda só o backend; `make test-frontend` roda localmente.

Os testes usam um banco PostgreSQL separado, no container `db`, porque recursos como `lockForUpdate()` e `to_char()` são específicos do PostgreSQL. A fila roda em modo `sync` nos testes, e nenhum relay roda sozinho (os testes do RabbitMQ, em `app-modules/messaging/tests/Feature/Messaging` e `tests/Feature/Messaging`, usam o broker de verdade, no vhost do checkout, que cada teste esvazia antes de começar; sem o broker, todos falham dizendo `RabbitMQ ...`, e nenhum é pulado): um evento gravado fica pendente até o teste rodar `outbox:relay --once` (`relayOnce()` e `relayUntilIdle()` em `app-modules/messaging/tests/Helpers.php`). Com a fila `sync`, os consumidores rodam dentro do ciclo do relay. O cache é `array` nos testes; só o `RateLimitBurstTest` conta no Redis do container `redis`, com contadores próprios que expiram em um minuto, e dispara as requisições de processos filhos (`pcntl_fork`, presente na imagem PHP do projeto).

### Um banco de testes por checkout

O MongoDB segue a mesma regra: o `make test` passa `MONGODB_DATABASE=<o banco de testes do checkout>` (`ecommerce_testing`, ou `ecommerce_testing_<worktree>`), cada teste de `Feature` que usou o MongoDB deixa as duas coleções vazias (o `RefreshDatabase` só desfaz o PostgreSQL) e o `make test-db-drop` apaga esse banco também. Os testes que leem a vitrine chamam `rebuildStorefront()` no preparo: as factories não gravam evento, e a reconstrução é o caminho para a escrita sem evento.

Todos os checkouts do repositório (a pasta principal e cada `git worktree`) usam o mesmo projeto Compose (`ecommerce`) e, portanto, o mesmo container `db` e o mesmo `rabbitmq`. Como o `RefreshDatabase` roda `migrate:fresh`, duas suítes no mesmo banco quebram uma à outra (`deadlock detected`, `relation "migrations" does not exist`). Por isso o `make test` e o `make test-filter` escolhem o banco e o container pelo checkout, sem passo manual:

| Checkout | Banco | Onde a suíte roda |
| --- | --- | --- |
| Pasta principal (e o CI) | `ecommerce_testing` | No container `api` de dev (`docker compose exec`), que monta o `backend/` dela |
| `git worktree` | `ecommerce_testing_<nome do worktree>`, em minúsculas e com `_` no lugar de `-` e `.` (ex.: `ecommerce_testing_upbeat_lewin_b6b8e7`) | Num container `api` avulso (`docker compose run --rm --no-deps`), que monta o `backend/` do próprio worktree. O `api` de dev testaria o código da pasta principal |

O **vhost do RabbitMQ** segue o mesmo critério: o nome é o do banco de testes do checkout (`ecommerce_testing` ou `ecommerce_testing_<worktree>`), o Makefile o cria com as permissões do usuário do broker (`rabbitmqctl add_vhost` e `set_permissions`) e o passa ao Pest em `RABBITMQ_VHOST`. Assim duas suítes em worktrees diferentes nunca veem as filas uma da outra. Antes da suíte, o Makefile cria o banco se ele ainda não existir e o passa ao Pest em `DB_DATABASE` (a variável real vence o `<env>` do `phpunit.xml`, que não usa `force`). Num worktree, ele também copia o `backend/.env` da pasta principal quando falta, e o entrypoint do container instala o `vendor/` na primeira execução (`composer install`). O worktree usa o `db`, o `redis` e o `rabbitmq` da pilha de dev, que precisa estar no ar (`make up` na pasta principal).

Outro banco: `make test TEST_DB_DATABASE=<nome>`, ou a mesma variável no `.env` da raiz. O nome aceita só minúsculas, dígitos e `_`, sem começar por dígito (até 63 caracteres), e nunca pode ser o banco de desenvolvimento (`DB_DATABASE`), que a suíte apagaria. O `make test-db-drop` apaga o banco e o vhost do checkout. Os bancos e os vhosts de worktrees já removidos ficam no `db` e no `rabbitmq` até alguém apagá-los: liste os bancos com `make db-shell` e `\l ecommerce_testing*`, e apague com `DROP DATABASE <nome> WITH (FORCE);`; os vhosts, com `docker compose exec rabbitmq rabbitmqctl list_vhosts` e `rabbitmqctl delete_vhost <nome>`. O worktree usa o `db`, o `redis` e o `rabbitmq` da pilha de dev, que precisa estar no ar.

Os outros alvos que leem o código do checkout seguem o mesmo critério. Num worktree, o `make pint` (o `pint --test`), o `make shell` e o `make artisan` rodam num `api` avulso com o `backend/` do worktree (copiando o `backend/.env` da pasta principal quando falta), e o `make test-frontend` e o `make frontend-shell` num `frontend` avulso com o `frontend/` dele. Na pasta principal e no CI, os cinco continuam no `docker compose exec` dos containers de dev, como antes. Cuidados no worktree:

- O `make artisan` e o `make shell` usam o `backend/.env` da pasta principal, então falam com o banco e o Redis de dev. Um `make artisan CMD=migrate` ali aplica as migrations do worktree no banco que a pasta principal serve.
- O `make test-frontend` usa o volume `node_modules` de dev, com as dependências do último `npm install` (em geral, as da pasta principal). Um worktree que muda as dependências do frontend testa com as de lá até rodar `make npm-install`, que instala as dele nesse mesmo volume, também lido pelo Vite de dev.
- Os alvos que agem sobre o ambiente de dev (`migrate`, `migrate-fresh`, `seed`, `fresh`, `optimize`, `clear`, `queue*` e `setup`) continuam nos containers de dev em qualquer checkout.

### Em que nível testar

O nível de um teste depende do formato do código, não do nome da camada. Código que **decide** muda um resultado: uma tabela de casos, uma validação, uma guarda, uma transição de status. Código de **instrumentação** só repassa argumentos ou converte um formato em outro, sem condição própria.

| Código | Provas exigidas | Cobertura esperada |
| --- | --- | --- |
| Decide e é alcançado pelo HTTP (services, use cases, adaptadores) | uma na borda (teste de feature) **e** uma na própria camada | na borda: o contrato de cada status; na camada: um caso afirmado por linha da tabela de decisão |
| Decide e é alcançado só por um comando ou pela fila (o relay do outbox, o `DeliverEvent`, o transporte de fila, as inscrições) | uma na borda: o comando pelo `Artisan`, a entrega pela fila `sync` ou `database`, a inscrição pelo service provider no boot | um caso afirmado por linha da tabela de decisão, sem teste separado da classe interna |
| Decide no frontend (composables, stores, utils) | uma na própria camada, com Vitest e os services simulados | um caso por resposta tratada e por estado relevante |
| Garantia do banco (índice único, `CHECK`) | uma direto no banco | um caso por restrição, aceito e recusado |
| Regra de fronteira entre módulos | uma expectativa de arquitetura por namespace, gerada das pastas que existem, vista falhando com uma violação de propósito | toda regra nova |
| Configuração declarativa do ambiente (Compose, Dockerfile, CI, Nginx e os alvos do Makefile que só repassam argumentos) | uma sobre a configuração resolvida: `docker compose config`, `make -n`, `php -m` na imagem, ou uma requisição pela porta publicada (`docker/tests/environment.sh`) | cada serviço, comando, imagem, porta e argumento que um critério nomeia |
| Alvo do Makefile que decide (o vhost por checkout, o `TRANSPORT` do `make load-test`) | uma na borda: o alvo rodado de verdade, num script de prova (`docker/tests/environment.sh`, `load-tests/tests/make.sh`) | cada valor aceito, cada recusa e cada checkout (pasta principal e worktree) |
| Comportamento do broker que a topologia configura (as 3 tentativas, a fila de mortas, a cópia por consumidor, a confirmação) | uma contra o RabbitMQ real, no vhost de testes; a própria camada só onde o broker não produz o caso (o timeout da confirmação, a parada no meio do retry, a reconexão) | cada caminho da mensagem: confirmada, recusada, entregue, devolvida, morta e devolvida pelo retry |
| Instrumentação (controllers, Form Requests, service providers, renderização de exceções) | nenhuma própria | coberta pelas provas de borda |

Um teste prova a camada em que ele **afirma**, não as camadas por onde ele passa. Um teste de feature que atravessa uma tabela de decisão exercita um caminho dela e não falha quando outra linha está errada. Por isso a tabela também é provada na própria camada, como já fazem o `PaymentServiceTest` do Payment e `Feature/UseCases/*`.

### Integração contínua (GitHub Actions)

O workflow [`.github/workflows/ci.yml`](.github/workflows/ci.yml) roda em todo pull request e em todo push na `main`. Ele usa os **mesmos serviços do Docker Compose e os mesmos alvos do `Makefile`** do ambiente local, então o CI testa na mesma imagem PHP e na mesma versão do PostgreSQL que o `make test`:

1. cria os `.env` a partir dos exemplos (`make env-files`);
2. constrói a imagem PHP e sobe `db`, `redis`, `rabbitmq` e `mongodb` (com `--wait`, até o healthcheck de cada um passar) e depois o `api`;
3. instala as dependências do Composer (com cache por `composer.lock`) e gera a `APP_KEY`;
4. confere que nenhum arquivo de `load-tests/` e `.github/` cita o namespace antigo dos módulos (a suíte roda no container `api`, que só monta o `backend/`);
5. verifica o estilo com o Pint (`make pint`, o `pint --test`);
6. roda toda a suíte (`make test`), inclusive os testes de arquitetura que verificam as fronteiras entre módulos. O checkout do CI não é um worktree, então a suíte roda no container `api` e no banco `ecommerce_testing`, como na pasta principal.

Se algum passo falhar, os logs dos containers `db` e `api` aparecem no próprio run. Um push novo na mesma branch cancela o run anterior que ainda estiver em andamento.

Um segundo job, `load-smoke`, roda em paralelo o `make load-test TYPE=smoke` na pilha de carga, com os limites de requisição reais. Ele confere só os códigos e os corpos das respostas, sem limite de latência, e falha quando uma rota, um status ou um payload muda e os scripts do k6 não acompanham. Na falha, imprime os logs da pilha de carga, e no fim sempre roda `make load-down`. Ver [Testes de carga (k6)](#testes-de-carga-k6).

O frontend ainda não é verificado no CI.

### Onde ficam os testes

Os testes de um módulo moram no próprio pacote, em `app-modules/<módulo>/tests/Unit` (sem banco) e `tests/Feature` (com banco), no namespace `Modules\<Módulo>\Tests\` do `autoload` do pacote, e usam só aquele módulo, o `Shared` e o `Messaging`. O que vem de outro módulo é um duplo da porta do próprio módulo (no Payment, `fakeOrdersToPay()` e `unavailableOrdersToPay()`), e quem entra autenticado é a identidade de teste do `Shared` (`signInAsCustomer()`, um `Authenticatable` que só tem o id), nunca um model do Identity. Os helpers de cada módulo ficam no `tests/Helpers.php` dele, e o `tests/Pest.php` da raiz estende o `TestCase` (e o `RefreshDatabase`, em `Feature`) às pastas dos módulos e carrega todos os helpers. O `phpunit.xml` lista as duas origens nas suítes `Unit` e `Feature`, então o `make test` e o `make test-filter` rodam tudo junto, sem comando novo.

Hoje têm testes no módulo o Identity, o Messaging, o Shared, o Catalog e o Payment. Os testes do Ordering, do Fulfillment e do Inventory ficam na raiz até a slice da sua travessia: o Ordering e o Fulfillment assinam os eventos um do outro ao subir, e a migration de `stocks` aponta para `products`, do Catalog. O `backend/tests` é a **suíte de composição**: os fluxos que juntam módulos reais, a arquitetura, a compatibilidade dos eventos, os adaptadores do `Integration` e os seeders. O `ModuleLayersTest` falha se um teste de módulo nomear outro módulo de negócio, o `Integration` ou o apoio da suíte da raiz (só o `Tests\TestCase` é permitido), ou se chamar um helper do `tests/Pest.php` ou do `tests/Helpers.php` de outro módulo de negócio: os helpers são funções globais, que o `tests/Pest.php` carrega para todos os testes, então a chamada a um deles não nomeia classe nenhuma.

Na tabela, o caminho começa em `backend/tests/Feature` (ou em `backend/tests`, quando começa por `Unit/`); com o prefixo `<módulo>:`, começa em `backend/app-modules/<módulo>/tests/Feature` (ou em `.../tests`, quando começa por `Unit/`).

| Arquivo | Cobertura |
| --- | --- |
| `identity: Auth/AuthTest` | cadastro em `customers` (sem `users`, ignorando `role`), e-mail igual ao da equipe, login só do cliente com as 4 chaves, credenciais da equipe recusadas, logout, `429` do cadastro e dos dois logins, também com um `X-Forwarded-For` forjado diferente a cada tentativa |
| `identity: Auth/StaffAuthTest` | login da equipe (`admin` e `support`, sessão lida de novo na próxima requisição), credenciais de cliente e senha errada recusadas, logout, `CHECK` do `role` direto no banco |
| `AuthorizationTest` | `401` a visitante e a sessão de cliente em todas as 27 rotas `/api/admin/*` (percorrendo a tabela de rotas), `401` a sessão de equipe nas 11 rotas da loja (inclui as 4 do caderno), pedido de outro cliente (`404`, como um id desconhecido), nenhuma rota com `auth:sanctum` |
| `RateLimitTest` | as 7 rotas com `throttle` (percorrendo a tabela de rotas, que precisa ser igual à lista do teste), cada uma com um contador nomeado que nenhuma outra repete; esgotar uma rota deixa só ela em `429`, e as outras continuam abertas ao mesmo visitante (pelo IP, nas 5 rotas públicas) e ao mesmo cliente (pelo id, nas 7). Visto falhando antes da correção e com um nome de contador apagado ou repetido de propósito |
| `shared: RateLimitBurstTest` | 30 requisições soltas no mesmo instante, de 30 processos filhos, contra um `throttle:10,1` contado no Redis, numa rota de sonda em `/api` (o `429` é o JSON do `ApiExceptionRenderer`, como nas rotas reais): passam exatamente 10, e 20 recebem `429`; um filho que recebe `5xx` devolve a exceção, que aparece na mensagem da falha; um contador deixado sem validade ganha a da janela no acerto seguinte. Visto falhando antes da correção: com o `throttle` do framework passaram as 30, em 5 de 5 rodadas; sem o `AtomicRateLimiter`, o contador continua sem validade |
| `ProductCatalogTest` | a vitrine lida do modelo de leitura (com o `rebuildStorefront()` no preparo): listagem com inativos, filtro, ordenações, paginação, detalhe indisponível, o mesmo número de comandos do MongoDB com 1 e com 12 produtos e o produto sem linha de estoque (`stock` nulo, indisponível), na vitrine e no cadastro do admin |
| `ProductResponseShapeTest` | as 7 rotas que devolvem produto com as chaves de hoje, na ordem de hoje; o cadastro de admin com 15 por página, do mais novo ao mais antigo; o produto criado com o estoque e as categorias do corpo |
| `Queries/*` | as consultas contra o banco: o filtro, as ordenações e as páginas da vitrine e do admin, o produto completado sem ler o registro de novo, o `ProductCatalog`, o `StockLevels` (a linha de cada produto numa query só, sem `for update`), a lista de estoque (inclusive a linha cujo produto sumiu) e as métricas do dashboard (produtos por status, estoque, só as contas de cliente, pedidos por dia e por mês); as métricas do Catalog e do Identity moram em `catalog: Queries/CatalogMetricsQueryTest` e `identity: Queries/CustomerMetricsQueryTest` |
| `UseCases/Stock/AdjustStockUseCaseTest` | o ajuste devolve o `StockRecord` com o que gravou, não o model |
| `CartValidationTest` | preços recalculados, problemas por item |
| `CheckoutTest` | pedido + decremento + snapshot, total = itens + frete, rollback com `409`, cenário 5/4/3, uso de `FOR UPDATE`, `CHECK` no banco |
| `CheckoutShippingTest` | pedido com a cópia do endereço e o frete da UF, colunas gravadas, valor cobrado com frete, `422` sem endereço / com endereço de outro cliente, frete e total do cliente ignorados, cópia intacta depois de editar ou excluir o endereço, `429`, `404` de pedido desconhecido |
| `UseCases/Order/PlaceOrderUseCaseTest` | o caso de uso com o orçamento e o endereço simulados; endereço desconhecido recusado antes de tocar no estoque |
| `Models/OrderDeliveryTest` | `CHECK`/`NOT NULL` da cópia do endereço, do frete, do prazo e do total direto no banco |
| `OrderStatusFlowTest` | as 5 inscrições de consumidores do ciclo do pedido (os 8 do projetor ficam no `ConsumerNamesTest`), `OrderPlaced` entregue pela fila (`DeliverEvent`), transições, entrega agendada só após `OrderPaid`, job com delay, `DeliveryScheduled` com a data prevista, log sem o endereço, registro da data (primeira vale, status inalterado, pedido já entregue), idempotência, ciclo completo pelo relay com o `request_id` do pagamento em todas as mensagens seguintes, e o ciclo com um `OrderPaid` reentregue no meio (uma mensagem de cada nome e o pedido entregue) |
| `Fulfillment/DeliverySchedulingTest` | o `OrderPaid` entregue pelo `EventDelivery`: a primeira vez (a linha de `deliveries`, o `DeliveryScheduled` e o `DeliverOrder` com o atraso, depois do commit), o repetido sem entrega (nada novo além do job, a primeira data), o repetido já entregue (nada), a falha ao gravar o evento (rollback e reparo na reentrega), a falha ao enfileirar (a linha e o evento ficam, a reentrega repara) e a corrida pela mesma linha |
| `Fulfillment/DeliveryTableTest` | o banco recusa a segunda linha do pedido (`23505`) e a exclusão de um pedido com linha (`23001`); `estimated_delivery_on` não nula, `delivered_at` nula, `id` uuid |
| `Fulfillment/DeliverOrderTest` | o job entrega e anuncia uma vez, o segundo run mantém o `delivered_at` e não grava nada, o rollback do `delivered_at` quando o evento falha, o pedido sem linha (nada muda) e o pedido que o Fulfillment nunca altera |
| `Messaging/ConsumerRedeliveryTest` | o mesmo envelope entregue duas vezes pelo `EventDelivery` a cada consumidor (`MarkOrderAsAwaitingPayment`, `MarkOrderAsPaid`, `RecordEstimatedDelivery`, `MarkOrderAsDelivered`, `ScheduleOrderDelivery`): o banco e a contagem de `outbox_messages` não mudam na segunda, e o `OrderPaid` repetido só enfileira o `DeliverOrder` de novo (dois jobs, o segundo um no-op); e cada um dos 8 eventos da vitrine entregue duas vezes ao seu consumidor do projetor: o documento no MongoDB fica como a primeira entrega o deixou, e nada é gravado no outbox |
| `ReadModel/StorefrontEventsTest` | cada escrita que muda a vitrine grava o seu evento na transação, com a parte inteira e a versão seguinte: o cadastro do produto e o estoque inicial na versão 1, a edição, a troca de status, a exclusão (a versão guardada mais 1, sem evento de estoque), a categoria, o ajuste de estoque e um `inventory.stock_changed` por produto do checkout com a quantidade que sobrou; uma escrita recusada não grava nada nem muda versão; a linha é travada antes de a versão ser calculada |
| `ReadModel/ConcurrentWritesTest` | escritas simultâneas no mesmo produto, e um checkout simultâneo a um ajuste de estoque, ganham versões crescentes, e o evento mais novo leva o estado final |
| `ReadModel/StorefrontRebuildTest` | o `catalog:republish` e o `inventory:republish` gravam o estado atual de cada registro com a versão atual (cada produto lido com as suas categorias numa só leitura); a falha no meio sai com código diferente de zero e diz quantos gravou, e rodar de novo converge; esvaziar o MongoDB e reconstruir devolve a mesma vitrine; um envelope republicado que chega depois de uma escrita não a desfaz; durante a reconstrução a vitrine responde `200` com o que já foi projetado |
| `catalog: ReadModel/ProductProjectorTest` e `catalog: ReadModel/CategoryProjectorTest` | a regra de versão do projetor: a parte é trocada inteira só quando o evento é mais novo, o evento igual ou mais velho é descartado, o estoque que chega antes do produto cria o documento só com essa parte, a lápide (`deleted_version`) segura eventos atrasados, duas entregas simultâneas terminam na versão maior, o `upsert` perdedor conta como descarte, e o MongoDB fora do ar falha a entrega sem mudar o documento |
| `catalog: ReadModel/StorefrontReadTest` | a vitrine lida só do MongoDB, sem nenhuma query no PostgreSQL: 12 por página, o filtro pelo slug de uma categoria viva, as ordenações (nome por byte e preço, com o id no desempate), o total e os links da lista filtrada, o `404` de um id fora da forma canônica sem ler o MongoDB, a disponibilidade decidida na leitura, o mesmo número de leituras com 1 e com 12 produtos (com e sem filtro) e o `503` em até 5 s com o MongoDB fora |
| `catalog: ReadModel/ClearReadModelCommandTest` e `catalog: ReadModel/ReadModelIsolationTest` | o `catalog:read-model-clear` esvazia as duas coleções e deixa os índices; cada teste começa com o modelo de leitura vazio |
| `Database/VersionColumnsTest` | a coluna `version` em `products`, `categories` e `stocks`, com 1 nas linhas que já existiam |
| `Outbox/EventOutboxTest` | a forma de `outbox_messages` no catálogo (colunas, índices, nenhuma chave estrangeira), a gravação dentro da transação (uma mensagem pendente, id v7), o rollback, a recusa fora de transação, os valores aceitos e recusados no payload (um `resource` também é recusado) e o `request_id` do `Context` e da requisição |
| `messaging: Outbox/EventDeliveryTest` | (com o nome estável de cada consumidor na inscrição) uma entrega por consumidor com o envelope inteiro e um nome sem consumidor, pelo relay; o evento reconstruído, o `request_id` restaurado ou limpo, a versão recusada, a falha isolada com 3 tentativas, o `failed_jobs` e o que o `queue:failed` lista (o evento e o consumidor), pelo `queue:work` com a conexão `database`; as recusas da inscrição, pelo `boot()` de um service provider; e a entrega depois da limpeza. Nenhum listener do Laravel nos eventos dos módulos fica na composição, em `Outbox/ModuleEventsListenersTest` |
| `messaging: Outbox/OutboxRelayTest` | o relay pelo comando: ordem, marcação, o envelope montado de cada coluna da linha, parada na primeira falha com o `warning`, saídas `0` e `1`, `FOR UPDATE SKIP LOCKED` com a reserva, a publicação e a marca numa transação só, a mensagem entregue de novo quando o relay para antes do commit, esperas de 500 ms e 5 s, retenção de 7 dias e limpeza por hora, sinal de reinício e o cache fora do ar |
| `messaging: Messaging/BrokerIsolationTest` | a conexão com o vhost do checkout (`RABBITMQ_VHOST`) e o vhost vazio no começo de cada teste, visto falhando com uma exchange e uma fila deixadas para trás de propósito |
| `messaging: Messaging/EventTransportFlagTest` | a flag: o padrão `redis`, `events:transport` mostrando, trocando, recusando outro valor, sem escrever nem registrar quando o valor é o mesmo, registrando quem trocou e funcionando com o Redis fora; o seletor entregando só ao transporte da flag, o relay obedecendo a uma troca no ciclo seguinte e o log de troca uma vez por mudança |
| `Messaging/ConsumerNamesTest` | os 13 consumidores nos 13 nomes estáveis (os 5 do ciclo do pedido e os 8 do projetor da vitrine), o `payment.payment_approved` lido pela classe do Ordering (`Core/InboundEvents/PaymentApproved`), e as recusas da inscrição (formato, um nome para dois consumidores, dois nomes para um) com as inscrições anteriores intactas |
| `messaging: Messaging/RabbitMqEventTransportTest` | contra o broker: a topologia declarada antes da primeira mensagem (exchanges, os 6 argumentos da fila, ligações, fila de mortas), a mensagem (propriedades e as 6 chaves do corpo), uma cópia por fila de consumidor, o evento sem consumidor, a mensagem marcada só depois da confirmação, e pendente com o broker fora, quando ele recusa e quando não confirma em 5 s |
| `Messaging/ConsumeEventsCommandTest` | contra o broker: a entrega confirmada depois do `handle()`, o `request_id` restaurado ou limpo, 3 falhas até a fila de mortas com o log de cada uma, os 4 corpos ilegíveis, a conexão fechada contada como falha, uma entrega por vez, a reconexão a cada 5 s, a parada pelo sinal de reinício, o consumo com a flag em `redis` e o mesmo `OrderPaid` consumido duas vezes com a flag em `rabbitmq` (uma só linha de `deliveries` e um só `DeliveryScheduled`) |
| `messaging: Messaging/DeadDeliveriesCommandsTest` | contra o broker: `events:failed` sem tirar nada da fila, `events:retry` devolvendo cada entrega só ao consumidor dela (todas ou por id), o id desconhecido, a parada no meio sem perder entrega, a nova queda para a fila de mortas e os dois comandos sem o broker |
| `HorizonTest` | o supervisor do Horizon nos dois ambientes, o painel aberto só ao `admin` da equipe (e `403` ao `support`, ao cliente e ao visitante, também em `local`) e o `horizon:snapshot` a cada 5 minutos |
| `Outbox/OrderEventsOutboxTest` | a gravação desfeita junto com a mudança (pagamento `500` sem aprovação nem mensagem, pedido ainda em `awaiting_payment`, nenhum `DeliverOrder`) e as janelas 1 e 2 com o transporte fora e de volta |
| `Unit/Architecture/DomainEventsTest` | toda classe de `Events` implementa `DomainEvent`, e nenhum módulo usa o `Dispatchable` de eventos, a interface de despacho depois do commit, a facade `Event` ou o helper `event()`. Vistos falhando com uma violação de propósito por regra |
| `Unit/Architecture/OrderEventPayloadTest` | nenhum model nos 5 eventos do pedido e no `DeliverOrder`; o nome, a versão e o payload de cada evento (chaves exatas, o inteiro do `OrderPaid`, o dia de São Paulo do `DeliveryScheduled`) e a volta pelo `fromPayload`; cada evento inscrito lido pela classe leitora a partir do payload que a produtora grava (mesmo nome, mesma versão, o mesmo payload de volta), os 8 da vitrine incluídos (o `inventory.stock_changed` lido pela classe do Catalog, `Core/InboundEvents/StockChanged`), e um par divergente acusado |
| `DeliveryEstimateTest` | a data prevista chega à API depois do pagamento aprovado; admin e loja veem o mesmo bloco `delivery` |
| `Unit/Services/DeliveryCalendarTest` | dias úteis em horário de São Paulo: meio de semana, sexta, sábado, 23h30 de Brasília e 10 dias |
| `Account/CustomerAddressTest` | caderno: listagem, cadastro, normalização da UF e do complemento, CEP, campos obrigatórios e tamanhos, limite de 10 (`409`), edição, exclusão, endereço de outro cliente (`404`), `404`, `401` |
| `Models/CustomerAddressTest` | `customer_addresses`: apagada com a conta, FK e `CHECK` de CEP e UF direto no banco |
| `UseCases/Customer/CustomerAddressUseCasesTest` | o 10º endereço é aceito e o 11º recusado, na própria camada |
| `Repositories/CustomerAddressRepositoryTest` | o `DeliveryAddressBook`: só o endereço do próprio cliente vira `DeliveryAddress`, e só nele a edição e a exclusão alteram algo |
| `Account/CustomerRecordRoutesTest` | as 4 rotas do cliente com registro (pedido, pagamento e endereço): o dono recebe `200`/`202`/`204`, o registro de outro cliente responde como um id desconhecido (mesmo corpo, nada alterado, nenhuma cobrança), `404` antes do status, `404` do pagamento desconhecido sem cobrança, `422` antes do dono, `DELETE` do endereço sem leitura prévia e a contagem de queries do dono |
| `Admin/StaffRecordRoutesTest` | as 15 rotas da equipe e da vitrine com registro: o id chega como `string`, id desconhecido é `404` sem mudar nenhuma das 9 tabelas, corpo inválido é `422` para id existente e desconhecido, e a contagem de queries (estoque com 3 queries e um só `FOR UPDATE`, `DELETE` de produto, categoria e membro sem leitura prévia, cliente lido uma vez no `PUT`, as demais leituras e edições com a contagem de antes) |
| `Unit/Services/ShippingRateTableTest` e `ShippingQuoteTest` | as 27 linhas da tabela de frete, a rota pública, `422`, `429` e o contrato `ShippingQuoter` igual à rota |
| `payment: PaymentTest` | com um duplo da porta `OrdersToPay`: `202` + tentativa gravada + mensagem `payment.payment_approved` no outbox (e nenhuma das duas se o outbox falha), `402` para cada cartão recusado, nova tentativa após recusa, `422` do `card_token` antes de consultar a porta (também com a porta indisponível), `409` para pedido que não aguarda pagamento ou com aprovado, corrida perdida, valor sempre do pedido, pedido de outro cliente (`404`), a porta consultada com o cliente autenticado, `503 SERVICE_UNAVAILABLE` com a porta indisponível (nada cobrado nem gravado), `401` sem sessão, garantias do banco, a factory sem pedido, log sem o token, `429` |
| `PaymentFlowTest` | o pagamento com o Ordering real atrás do `Integration`: o pedido continua `awaiting_payment` depois de uma recusa, e `409` para cada status que não aguarda pagamento (`placed`, `payment_approved`, `delivered`) |
| `Integration/OrdersToPayAdapterTest` | a porta `OrdersToPay` resolvida para o adaptador do `Integration`: os 4 status do pedido traduzidos em "aguarda pagamento", e `null` para pedido desconhecido ou de outro cliente |
| `shared: FeatureHarnessTest`, `shared: Unit/UnitHarnessTest` e `shared: TestIdentityTest` | o `TestCase` e o `RefreshDatabase` nas pastas `Feature` dos módulos, e só o `TestCase` nas `Unit`; a identidade de teste entra no guard `customer` só com o id |
| `Database/ModuleMigrationsForeignKeyTest` | as 5 chaves estrangeiras entre módulos que continuam e nenhuma em `payments.order_id` |
| `payment: Unit/Gateways/FakePaymentGatewayTest` | resultado de cada cartão de teste, valor ignorado, id de transação novo |
| `Admin/*` | categorias (slug), produtos, estoque (as chaves de cada linha, a mesma contagem de queries com 1 e com 20 linhas e a única leitura de `products`), clientes (`CustomerTest`), equipe (`StaffTest`), papéis (`StaffRoleTest`: todo `DELETE` do admin só para `admin`, percorrendo a tabela de rotas; `404` dos ids desconhecidos), pedidos e dashboard (inclusive a loja vazia) |
| `Account/AccountTest` | resumo e edição de perfil/senha |
| `Models/CustomerTest` | comprador lido a partir da conta, projeção somente leitura |
| `Models/CustomerAccountTest` | `customers`: FK do pedido (`restrict`, id existente), `CHECK` do e-mail e unicidade direto no banco |
| `identity: Middleware/EnsureUserIsAdminTest` | o middleware `admin` deixa passar só o papel `admin` |
| `identity: UseCases/User/StaffUseCasesTest` | regras do admin sobre si mesmo: não muda o próprio papel, não remove a própria conta; exclusão e edição por id, com `ModelNotFoundException` para id desconhecido |
| `Unit/Architecture/ModulePackagesTest` | os 11 pacotes em `modules:list` (o `Integration` classificado como composição), nenhuma referência ao namespace antigo, `bootstrap/providers.php` sem módulos, só as pastas permitidas em cada `src`, o namespace `Tests` no manifesto de cada pacote com testes e nenhum na raiz |
| `Unit/Architecture/ModuleLayersTest` | `Core` e `Persistence` sem o `Http` do módulo, infraestrutura (`Shared`, `Messaging`) sem módulo de negócio, `Shared` sem `Messaging`, os testes de cada módulo sem outro módulo de negócio nem o apoio da raiz (nomes de classe e chamadas aos helpers deles), o Payment isolado (nenhum outro pacote além do `Integration` o nomeia, e ele não nomeia outro módulo de negócio) e as classes leitoras de evento usadas só pelos consumidores e pelo provider, cada regra com uma violação plantada em `Fixtures/Layout/` ou `Fixtures/Isolation/` |
| `messaging: Messaging/MessagingModuleTest` | a fachada do `Messaging` (4 interfaces) e os 5 comandos de evento registrados pelo provider do módulo |
| `Unit/Architecture/ModuleBoundariesTest` | fronteiras entre módulos: pastas privadas (o `Http` incluído) e vocabulário público calculado das assinaturas de `Contracts` e `Events` (com classes de teste em `Fixtures/` para o percurso), estoque só pelos contratos, Identity sem pedidos, Ordering, Payment e Fulfillment sem o `User` da equipe, Payment e Fulfillment sem `OrderRepository` e sem se conhecerem, Payment cobrando só pela porta `PaymentGateway`, Ordering sem o Customers e só com os eventos do Fulfillment (uma expectativa por namespace), Fulfillment sem o Customers, contratos do Ordering só interfaces, Shared sem módulos de negócio, repositories sem os services do próprio módulo, e as consultas (sem model nem coleção do Eloquent nas assinaturas, sem repositories nem casos de uso do próprio módulo, os 6 contratos de leitura resolvidos para as consultas dos donos, sem lista de exceções). As 9 travessias que eram exceções foram vistas falhando uma a uma com uma violação de propósito |
| `Unit/Architecture/ReadModelTest` | o modelo de leitura é do Catalog: nenhum outro módulo nomeia a conexão, as coleções, os repositórios dos documentos ou o `ReadModelStore`, e dentro do Catalog só o projetor, os comandos, a persistência e o provider os alcançam (o `Http` e os casos de uso não). Visto falhando com duas violações de propósito |
| `Unit/Architecture/ModuleCachePathTest` | o caminho do cache de módulos vem de `APP_MODULES_CACHE` e, sem a variável, é o `bootstrap/cache/app-modules.php` |
| `Auth/EmailAndPasswordTest` | e-mail como conta única (caixa, `CHECK` do banco em `users` e `customers`, login, perfil, admin), política de senha só ao escolher uma |
| `Unit/ValueObjects/*` | `ProductAvailability` (os 4 casos de status e unidades, em `catalog: Unit/ValueObjects`), `Email` (normalização e recusas) e `Password` (política, sem vazar o texto em dumps, serialização e traces), os dois em `identity: Unit/ValueObjects`, e as listas tipadas (`TypedListsTest`: agrupamento, totais derivados, recusa de tipo e de id inválido ou repetido) |
| `Database/UuidKeysTest` | as 10 chaves primárias e as 9 colunas de referência são `uuid` (a `payments.order_id` sem chave estrangeira desde a isolação dos módulos), o id v7 vem da aplicação (a coluna não tem default), as regras de exclusão e as constraints continuam |
| `UuidIdsTest` | todo `id` e `*_id` das respostas é um UUID em texto, id malformado no corpo (`422`, sem query em `products`, `stocks` e `categories`) e no caminho (`404` sem query, percorrendo a tabela de rotas) |
| `Models/OrderCodeTest` e `Unit/Services/OrderCodeGeneratorTest` | o código do pedido direto no banco (nulo, tamanho, alfabeto, único) e o sorteio de 8 símbolos |
| `Unit/Architecture/IdTypesTest` | os 10 métodos de contrato, os 5 eventos e o job `DeliverOrder` falam em `string`, o `PayableOrders::findForPayment` recebe o dono, a porta `OrdersToPay` do Payment tem a forma do design, e nenhum `int` chamado `id` ou terminado em `Id` sobra em `Modules` |
| `Unit/Architecture/OrderCodeBoundariesTest` | o código do pedido fora dos contratos, dos eventos e das rotas |
| `Unit/Architecture/RouteBindingTest` | nenhuma rota de `/api` resolve registro: nem binding implícito (parâmetro de ação `UrlRoutable`), nem explícito, e todo parâmetro tem o padrão do id canônico. Vistos falhando com uma violação de propósito por regra |
| `Unit/Architecture/TypedListSignaturesTest` | nenhum `array` nas assinaturas de domínio que carregam as listas tipadas, os contratos de métricas incluídos |
| `Requests/AccountRequestRulesTest` | os Form Requests de conta usam o `EmailRule`, o `PasswordRule` e o `NormalizesEmailInput` do próprio módulo (Identity ou Customers) e não repetem as regras |
| `Unit/Architecture/HttpAdaptersTest` | os adaptadores HTTP do Customers e do Fulfillment não escrevem limite, expressão regular nem lista de UFs e delegam a `Email`, `Password` e `BrazilianState`; cada request de UF usa o `NormalizesStateInput` do próprio módulo |
| `Unit/Resources/CustomerProfileResourceTest` | o `CustomerProfileResource` do Identity e o do Customers mostram a conta no mesmo formato |
| `SeederTest` | quantidades mínimas exigidas, as 2 contas da equipe e os 10 clientes (em `customers`), endereço para todo cliente, pedidos com cópia e frete da tabela, data prevista só nos pedidos pagos e determinismo dos seeders |
| `shared: RateLimiterStoreTest` | sem `CACHE_LIMITER`, o limitador de requisições usa o armazenamento padrão (os limites valem como sempre) |

---

## Testes de carga (k6)

A pasta [`load-tests/`](load-tests/README.md) guarda os testes de desempenho e de carga com o [k6](https://grafana.com/docs/k6/latest/) (HEL-14; design em [.design/load-tests-k6.md](.design/load-tests-k6.md)). Os números são uma **linha de base**: comparam uma rodada com a próxima, na mesma máquina, com a mesma configuração e o mesmo conjunto de dados. Não dizem quanto um servidor de produção aguentaria.

```bash
make load-test TYPE=smoke
```

- **Pilha de carga.** Cada rodada recria o projeto Compose `ecommerce-load` (`load-tests/docker-compose.yml`): Postgres (`ecommerce_load`), Redis, RabbitMQ e MongoDB próprios, a API com configuração de produção (sem debug, opcache sem revalidação, caches em `/tmp/laravel` dentro do container, PHP-FPM estático com 16 workers), o `api-read` da vitrine (num container próprio, com a mesma configuração do PHP-FPM), o worker (Horizon), o relay, o consumidor do RabbitMQ, o Nginx e o k6. Ela compartilha com a de dev só a imagem do PHP e a árvore `backend/`, e nenhuma porta é publicada no host.
- **Dados.** O `LoadTestSeeder` (fora do `DatabaseSeeder`) cria sempre os mesmos 1.000 clientes (`load-customer-<n>@example.com`), a equipe, 201 produtos (170 disponíveis, 10 sem unidades, 20 inativos e o `Produto Disputado`, com 10) e 10.000 pedidos de histórico, em cerca de 5 segundos.
- **Mistura.** Os perfis de volume dividem os VUs em cerca de 75% vitrine, 20% compra e 5% equipe. Cada requisição declara os status que espera: um `404` ou um `409` esperados não são erro.
- **Limites de requisição.** Nos perfis de volume e no `contention`, a pilha roda com `CACHE_LIMITER=array`, e nenhum limite é atingido. O `smoke` e o `limits` rodam com os limites reais.
- **Espera até pagar.** A compra consulta o pedido a cada 250 ms (o frontend consulta a cada 2 s) até `awaiting_payment`: com 2 s, a leitura valeria o mesmo para os dois transportes. O tempo desde o `201` é a métrica `order_payable_wait`, que mede o relay do outbox e o worker (ou o consumidor do RabbitMQ) sob carga.
- **Transporte.** `make load-test TYPE=<perfil> TRANSPORT=rabbitmq` grava `rabbitmq` na flag `event-transport` da pilha de carga antes de o k6 começar (`redis` é o padrão) e põe o transporte no nome dos relatórios (`<perfil>-rabbitmq-<carimbo>`). Um transporte desconhecido é recusado antes de subir qualquer container. A tabela por transporte fica no [README da pasta](load-tests/README.md).

| Perfil | O que faz |
| --- | --- |
| `smoke` | 1 VU por fluxo, uma iteração, passando por todos os casos de erro; também roda no CI |
| `load` | 50 VUs por 5 minutos: a carga da linha de base |
| `stress` | 150 VUs por 5 minutos |
| `spike` | de 50 para 300 VUs em 10 segundos e de volta |
| `breakpoint` | taxa de chegada de 5 a 500 iterações/s em 15 minutos, até quebrar |
| `soak` | 50 VUs por 1 hora |
| `contention` | 50 clientes disputam as 10 unidades do `Produto Disputado`, e 50 pares de pedidos cruzam a ordem dos itens |
| `limits` | cada rota limitada até o limite e mais uma, e uma rajada de 30 logins simultâneos |

Os relatórios (HTML do painel do k6, JSON e a lista de checks) vão para `load-tests/results/`, fora do git. Os limites de aprovação de cada perfil, a regra para aceitar uma linha na tabela da linha de base e a tabela ficam no [README da pasta](load-tests/README.md).

---

## Containers

| Container | Imagem | Função |
| --- | --- | --- |
| `nginx` | `nginx:alpine` | Ponto único de entrada (`:8080`). Roteia o `GET` e o `HEAD` da vitrine para o `api-read` e `/api`, `/sanctum` e `/horizon` para o PHP-FPM do `api`, com o IP da conexão em `REMOTE_ADDR`, e o resto para o Vite (inclui WebSocket do HMR) |
| `api` | `docker/php/Dockerfile` (PHP 8.5-FPM) | Laravel. Instala as dependências do Composer na primeira execução |
| `api-read` | mesma imagem da `api` | Serve a vitrine (`GET` e `HEAD` de `/api/products` e `/api/products/{product}`) a partir do MongoDB, sem credencial do PostgreSQL e sem esperar o `db` (ver [O modelo de leitura da vitrine](#o-modelo-de-leitura-da-vitrine)) |
| `queue-worker` | mesma imagem da `api` | `php artisan horizon` — o Horizon, com um supervisor na fila `default` do Redis: processa as entregas aos consumidores (`DeliverEvent`, com a flag em `redis`) e os jobs |
| `outbox-relay` | mesma imagem da `api` | `php artisan outbox:relay` — publica as mensagens pendentes de `outbox_messages` pelo transporte da flag `event-transport`, em até 1 s, e apaga as publicadas há mais de 7 dias |
| `event-consumer` | mesma imagem da `api` | `php artisan events:consume` — lê as filas dos consumidores no RabbitMQ (com a flag em `redis` ele fica ocioso, mas continua lendo o que já estava nas filas) |
| `scheduler` | mesma imagem da `api` | `php artisan schedule:work` — o `horizon:snapshot` a cada 5 minutos |
| `frontend` | `docker/node/Dockerfile` (Node 24) | Servidor de desenvolvimento do Vite |
| `db` | `postgres:18-alpine` | Banco principal (`ecommerce`) e de testes (`ecommerce_testing`, mais um `ecommerce_testing_<worktree>` por `git worktree` que rodou `make test`) |
| `mongodb` | `mongo:8.2` | O modelo de leitura da vitrine (`catalog_products` e `catalog_categories`), com o volume `mongodb_data` e a porta `MONGODB_FORWARD_PORT` (27018) no host. O `make test` usa um banco por checkout |
| `redis` | `redis:8-alpine` | Fila, sessões, cache e os dados do Horizon |
| `rabbitmq` | `rabbitmq:4.3.5-management-alpine` | O broker do transporte `rabbitmq`, com o volume `rabbitmq_data` e o painel de gestão em `:15672` (`RABBITMQ_MANAGEMENT_PORT`). Também tem um vhost de testes por checkout |

O código do backend e do frontend é montado como volume: alterações aparecem imediatamente (HMR no frontend; o PHP-FPM lê os arquivos a cada requisição). O Horizon, o relay e o consumidor do RabbitMQ precisam de `make queue-restart` após mudanças em jobs, consumidores ou no relay.

Os testes de carga sobem um segundo projeto Compose, o `ecommerce-load` (`load-tests/docker-compose.yml`), com os seus próprios `db`, `redis`, `mongodb`, `rabbitmq`, `api`, `api-read`, `queue-worker`, `outbox-relay`, `event-consumer` e `nginx`, mais o `k6` (`grafana/k6`). Ele sobe a cada rodada, fica no ar depois dela para inspeção até o `make load-down` e não publica portas; ver [Testes de carga (k6)](#testes-de-carga-k6).

**IP do cliente:** o Nginx fala FastCGI com o PHP-FPM, e não HTTP, então para o Laravel ele não é um proxy. O `fastcgi_params` já entrega o endereço da conexão (`REMOTE_ADDR`, o `$remote_addr` do Nginx) e o esquema (`HTTPS` e `REQUEST_SCHEME`). Por isso o `bootstrap/app.php` não chama `trustProxies()`, e o `docker/nginx/default.conf` não repassa nenhum `X-Forwarded-*` próprio ao PHP. No Docker local, quem chega pela porta publicada aparece com o gateway da rede do Compose (por exemplo, `172.20.0.1`), então todo o tráfego do host divide, em cada rota, o mesmo contador por IP. Se um balanceador ou uma CDN entrar na frente do Nginx, o IP real deve ser restaurado no próprio Nginx (`set_real_ip_from` com o endereço do balanceador e `real_ip_header X-Forwarded-For`), nunca com `trustProxies(at: '*')`.

---

## Fluxo completo para demonstração

1. Abra `/admin/login` e entre como **admin** (`admin@example.com` / `password`) → abre o `/admin` com o dashboard. Em **Usuários** o admin cadastra a equipe; o suporte (`suporte@example.com`) entra pela mesma tela, cria e edita, mas não vê "Usuários" nem os botões "Excluir".
2. **Categorias** → crie uma categoria (o slug é gerado automaticamente).
3. **Produtos → Novo produto** → preencha os dados, selecione a categoria e defina o estoque inicial.
4. Abra a loja (`/products`): o produto aparece; filtre pela categoria e ordene por preço.
5. Saia e **crie uma conta** de cliente em `/register` (ou use `cliente@example.com`).
6. Adicione produtos ao carrinho e vá para o **checkout**: o backend valida o estoque e o endereço de entrega já vem escolhido (`cliente@example.com` tem um endereço em SP; uma conta nova abre o formulário no próprio checkout). O frete da UF aparece junto com o total e o prazo.
7. **Confirme a compra**: a transação bloqueia e reduz o estoque e cria o pedido, com a cópia do endereço e o frete. Em **Minha conta → Endereços** dá para cadastrar outros endereços, editar e excluir; os pedidos já feitos não mudam.
8. Na página de pagamento, o status passa de *Pedido efetuado* para *Aguardando pagamento* (o relay publica o `OrderPlaced` e o consumidor roda na fila).
9. Escolha **Recusado: saldo insuficiente** e clique em **Pagar**: a recusa aparece na página e o pedido continua aguardando pagamento. Escolha **Cartão aprovado** e clique em **Pagar**: o pedido vai para *Pagamento aprovado* e, após ~10 s, para *Pedido entregue* (job com delay). A timeline atualiza sozinha, e o pedido mostra "Entrega prevista: dd/mm/aaaa" (ou "Pedido entregue" no fim).
10. Veja o histórico em **Minha conta → Meus pedidos**.
11. Entre novamente em `/admin/login` como admin: o pedido aparece em **Pedidos** e as métricas do dashboard foram atualizadas.

Dica: deixe `make logs-worker` aberto em outro terminal para ver as entregas e os jobs sendo processados (`ordering.order_placed -> ...MarkOrderAsAwaitingPayment`). Para ver o outbox segurando um evento, pare o Redis (`docker compose stop redis`) antes de confirmar a compra: o pedido fica em *Pedido efetuado*, a mensagem continua pendente e o `make logs-relay` avisa a cada 5 s; depois de `docker compose start redis`, o pedido segue para *Aguardando pagamento*. Para ver o outro transporte, rode `make event-transport TO=rabbitmq` antes de confirmar a compra: o `make logs-relay` registra a troca, o `make logs-consumer` e o painel em `http://localhost:15672` mostram a entrega, e `make event-transport TO=redis` volta ao padrão. O painel da fila do Laravel está em `http://localhost:8080/horizon` (entre antes no `/admin/login` como admin).

---

## Escopo e decisões

Fora do escopo por definição: cupons, descontos, gateway de pagamento real, transportadora real ou serviço de frete pronto (o frete é uma tabela fixa por UF, sem peso, dimensões, CEP, modalidades, frete grátis ou feriados, e muda só com deploy), rastreio e etapa "Enviado", nota fiscal, wishlist, avaliações, chat, marketplace, multi-tenant, Elasticsearch, microsserviços e Kubernetes. Também não há upload de imagens: os produtos guardam apenas uma URL fake do `picsum.photos`.

Decisões relevantes:

- **Dinheiro**: centavos inteiros do banco à tela. As colunas são `bigint` (`price_cents`, `unit_price_cents`, `subtotal_cents`, `total_cents`) com `CHECK >= 0`, a API trafega inteiros com o sufixo `_cents` (sem alias para os nomes antigos) e o estado do frontend também guarda centavos. `shipping_cents` segue a mesma regra (`bigint`, `CHECK >= 0`), e `total_cents` passou a ser a soma dos itens mais o frete. A conversão para reais existe só no frontend (`utils/money`): para exibir (`formatCents`) e para ler o preço que o admin digita (`parseReaisInput`, aceita `199,90`, `199.90`, `199,9` e `199`). O carrinho persiste em `localStorage` na chave `cart-v3`; as chaves antigas (`cart` e `cart-v2`) são descartadas. As migrations originais foram editadas no lugar: quem tem banco local roda `make fresh`, e dumps antigos de `storage-dumps/` deixam de ser compatíveis.
- **E-mail e senha como value objects**: e-mails que só diferem na caixa são a mesma conta. O `Email` (`Identity/ValueObjects`) remove os espaços das pontas, coloca em minúsculas, valida o formato (RFC) e limita a 255 caracteres. Os Form Requests normalizam o `email` antes de validar (`NormalizesEmailInput`, um no Identity e outro no Customers, os dois chamando `Email::normalize`), então o `unique`, o login e a resposta (`data.email`) usam a forma canônica. As colunas `users.email` e `customers.email` têm o `CHECK ..._email_normalized (email = lower(btrim(email)))`, na migration original: quem tem banco local roda `make fresh`. O `Password` aplica a política (mínimo de 8 caracteres) só quando alguém escolhe uma senha (cadastro, admin e perfil); o login aceita senhas antigas. O texto da senha não aparece em `json_encode`, `serialize`, `var_export`, `print_r`, `var_dump` nem em stack traces enquanto estiver no value object. Ele só sai por `reveal()`, usado ao montar os atributos que vão para o `UserRepository` e o `CustomerAccountRepository`, e dali em diante é uma string comum.
- **Listas de domínio tipadas**: o carrinho, as linhas do pedido, o resultado da validação, os ids de produto, categoria e cliente e a contagem de pedidos por cliente são classes, não `array`. O total do pedido (`OrderLines::totalCents()`) e o do carrinho (`ValidatedCart::totalCents()`) são calculados a partir das linhas, então não divergem delas; o `OrderRepository::createWithItems()` grava o total que as linhas calculam mais o frete. A resposta da API não mudou. O contrato `StockReservation::lockForProducts()` recebe `ProductIds` e devolve `StockQuantities`.
- **Endereços e frete** (feature `addresses-and-shipping`, design em [.design/addresses-and-shipping.md](.design/addresses-and-shipping.md)):
  - **O pedido guarda uma cópia do endereço**, do frete e do prazo prometido, em colunas `NOT NULL` de `orders` e sem referência ao caderno. Editar ou excluir um endereço, ou mudar a tabela de frete, nunca altera um pedido feito, e o banco garante que nenhum pedido existe sem destino.
  - **O servidor calcula o frete e o total.** O cliente envia só os itens e o `address_id`; `shipping_cents`, `total_cents` e a UF enviados são ignorados. O frete do checkout é só exibição: o pedido usa a tabela vigente na criação, e o Payment cobra o `total_cents` do pedido.
  - **Contratos definidos pelo Ordering**, como o `ProductOrderHistory`: `DeliveryAddressBook::find(customerId, addressId): ?DeliveryAddress` (implementado pelo `CustomerAddressRepository`, ligado no `CustomersServiceProvider`) e `ShippingQuoter::quote(BrazilianState): ShippingQuote` (implementado pelo `ShippingRateTable`, ligado no `FulfillmentServiceProvider`). O vocabulário que eles trocam (`DeliveryAddress`, `ShippingQuote`, `BrazilianState`) fica no Ordering, porque o Customers e o Fulfillment já dependem dele. O `ModuleBoundariesTest` proíbe o Ordering de usar o Customers e qualquer namespace do Fulfillment além dos eventos. O `BrazilianState` é vocabulário do Ordering (o `ShippingQuoter` o expõe), mas o `Http` do Ordering é privado: os Form Requests do Customers e do Fulfillment normalizam o `state` cada um com o seu trait `NormalizesStateInput` (no `Http/Requests/Concerns` do próprio módulo), que chama `BrazilianState::normalize`, e a regra continua num lugar só.
  - **A tabela de frete é do Fulfillment** (`config/shop.php`, `shipping_rates`) e nenhum outro lugar calcula preço, prazo ou data: o frontend mostra o que a API devolve.
  - **A data prevista** é calculada pelo Fulfillment ao tratar o `OrderPaid` e chega ao pedido pelo evento `DeliveryScheduled`; só o Ordering escreve em `orders`. A tabela `deliveries` guarda só a primeira data e o instante da entrega (a chave de idempotência do módulo): não há rastreio nem etapa "Enviado".
  - **Limite de 10 endereços** por cliente, sem trava de concorrência: dois cadastros simultâneos podem deixar o cliente com 11, e isso é aceito.
  - **Sem migração de dados**: a tabela nova e as colunas novas de `orders` entraram nas migrations originais, então quem tem banco local roda `make fresh`, e dumps antigos de `storage-dumps/` deixam de ser compatíveis.
- **Estoque separado do produto**: a edição de produto (`PUT /api/admin/products/{product}`) não altera estoque; ajustes passam por `/api/admin/stocks/{stock}`.
- **Equipe e clientes são contas separadas** (feature `staff-and-customer-accounts`, design em [.design/staff-and-customer-accounts.md](.design/staff-and-customer-accounts.md)): a equipe (admin e suporte) vive em `users`, o comprador em `customers`, e o mesmo e-mail pode existir nas duas. Um e-mail continua único dentro de cada tabela. Não há migração de dados: o banco é recriado por `make fresh` (as migrations originais foram editadas no lugar).
- **Um guard de sessão por área**: `config/auth.php` define `customer` (provider `customers` → `CustomerAccount`, o padrão) e `staff` (provider `users` → `User`). As rotas da loja usam `auth:customer` e as do admin `auth:staff`; **nenhuma rota usa `auth:sanctum`** (o Sanctum fica só no `statefulApi()`, para sessão e CSRF), e um teste confere isso. Com `auth:sanctum` e uma lista de guards, a primeira sessão que autenticasse valeria, e uma sessão de equipe passaria nas rotas da loja. Código novo deve seguir o mesmo precedente. O browser tem **um cookie de sessão**, então `POST /api/auth/logout` e `POST /api/admin/auth/logout` encerram as duas áreas.
- **Só o admin remove** (precedente): todo `DELETE` de `/api/admin/*` e o recurso `/api/admin/users` ficam no grupo com o middleware `admin` (`EnsureUserIsAdmin`), e o suporte recebe `403`. Uma rota `DELETE` nova entra no grupo restrito por padrão, e o teste `StaffRoleTest` percorre `Route::getRoutes()` e falha se algum `DELETE` de `api/admin/*` responder diferente de `403` ao suporte. A alternativa, um `delete` em cada policy, deixaria uma rota nova sem o método liberar o suporte em silêncio.
- **Equipe**: o admin cadastra, edita, troca o papel e remove membros em **Usuários**. Ele não muda o próprio papel nem remove a própria conta (`409`, `BusinessRuleException`). Dois admins se removendo ou se rebaixando ao mesmo tempo é aceito sem trava: pode sobrar zero admins, e a recuperação é pelo seeder. Excluir clientes continua fora do escopo (`orders.customer_id` impede, e não há decisão de LGPD).
- **Módulos** (`app-modules/<módulo>`): cada módulo é um pacote Composer (`modules/<módulo>`, namespace `Modules\<Módulo>`) que o `internachi/modular` descobre; o provider vem do `composer.json` do pacote. A fachada pública (`Contracts`, `Events`) fica ao lado de três camadas privadas, `Core`, `Persistence` e `Http`, e cada módulo usa só as pastas de que precisa:

  ```
  app-modules/ordering/
    composer.json
    src/
      Contracts/  Events/                           fachada pública
      Core/         Consumers/ DTOs/ Enums/ Exceptions/ Jobs/ Services/ UseCases/ ValueObjects/
      Persistence/  Models/ Queries/ Repositories/
      Http/         Controllers/(Admin/) Requests/(Admin/) Resources/ Rules/ Middleware/
      Providers/    OrderingServiceProvider.php
    database/factories/
  ```

  - **Direção das camadas:** `Http` → `Core` → `Persistence`. `Core` e `Persistence` nunca usam o `Http` do próprio módulo, e o `ModuleLayersTest` cobra. O `Payment` ainda tem a pasta `Gateways` (adaptadores do gateway de pagamento), e o `Messaging`, o `Catalog` e o `Inventory`, a `Console` (os comandos de evento, e, desde a HEL-17, os da reconstrução da vitrine: `catalog:read-model-clear`, `catalog:republish` e `inventory:republish`). No Catalog, a `Persistence` também tem a pasta `ReadModel` (a conexão com o MongoDB e a escrita condicional do projetor). O `Shared` é um kernel e não tem camadas: `Http`, `Cache`, `Enums`, `Exceptions`, `Repositories` e `Support`.
  - **Criar um módulo:** `php artisan make:module <nome>` cria o pacote; fora dele só mudam o `composer.json` e o `composer.lock` da raiz. Depois de baixar o repositório, ou de trocar de branch com módulos novos, rode `composer install` para criar o symlink de `vendor/modules/<módulo>` (o container `api` só instala quando falta o `vendor/autoload.php`).
  - **Deploy:** esvazie a fila do Redis antes de subir a troca de namespace (os jobs `DeliverEvent` já enfileirados guardam a classe do consumidor com o namespace antigo); o RabbitMQ não é afetado, porque as filas usam o nome estável do consumidor.
  - **Padrões de rota:** os padrões de UUID dos parâmetros são registrados no `register()` do service provider do módulo dono do parâmetro (`product` e `category` no Catalog, `stock` no Inventory, `user` no Identity, `customer` e `address` no Customers, `order` no Ordering), e não no `boot()`: uma rota copia os padrões globais quando é criada, e as rotas de um módulo são criadas quando a biblioteca de módulos inicia. O `ModuleRoutesTest` cobra o dono de cada um.
  - **`Admin/`** separa o que pertence à área administrativa, como `Catalog\Http\Controllers\ProductController` (vitrine) e `Catalog\Http\Controllers\Admin\ProductController` (cadastro).
  - **Ligações explícitas:** models e factories se declaram por `#[UseFactory]` / `protected $model`. Assim não dependem mais da convenção de namespace `App\Models`.
  - **Rotas:** cada módulo declara as suas em `app-modules/<módulo>/routes/api.php`, dentro de `Route::prefix('api')->middleware('api')->group(...)`, porque a biblioteca de módulos só faz `require` do arquivo e não aplica prefixo nem grupo; os grupos de middleware (`auth:customer`, `auth:staff`, `admin`) são repetidos em cada módulo. O arquivo central de rotas deixou de existir, e um módulo novo não edita a raiz. O `ModuleRoutesTest` recusa um arquivo de rotas que nomeie controller de outro módulo e um par método e URI repetido.
  - **Cache de módulos:** a biblioteca de módulos descobre os pacotes no sistema de arquivos a cada boot, e sem cache isso custou cerca de 38 ms por requisição na pilha de carga. O cache dela (`modules:cache`) tem caminho fixo, então o `AppServiceProvider` o religa à variável `APP_MODULES_CACHE` (sem ela, `bootstrap/cache/app-modules.php`); só a pilha de carga a define, em `/tmp/laravel`, e roda o `modules:cache` (ver os [testes de carga](load-tests/README.md)). Em produção, rode `php artisan modules:cache` no deploy, junto com os outros caches.
  - **Migrations:** cada módulo guarda as migrations das suas tabelas em `app-modules/<módulo>/database/migrations`, com o nome de sempre, porque a tabela `migrations` guarda o nome do arquivo e renomear reexecutaria o SQL numa base já migrada. O migrator ordena os caminhos juntos pelo nome, o que mantém as chaves estrangeiras entre módulos (`orders.customer_id`, `order_items.product_id`, `stocks.product_id`, `deliveries.order_id`, `customer_addresses.customer_id`; a `payments.order_id` saiu na isolação dos módulos). Ficam na raiz só `cache`, `jobs` e `features`, que são do framework. O arquivo da `users` leva `password_reset_tokens` e `sessions` para o Identity, porque dividi-lo mudaria uma migration já executada. O `ModuleMigrationsTest` congela os 18 nomes: as 3 do framework e as 15 dos módulos (eram 16 nomes depois da isolação dos módulos, com a `2026_10_21_000001_drop_payments_order_id_foreign`, do Payment, por último; as duas da HEL-17 dão a coluna `version` a `products` e `categories`, no Catalog, e a `stocks`, no Inventory).
  - **Testes:** ficaram em `backend/tests`, organizados por tipo (Unit/Feature), até a isolação dos módulos, que os levou para dentro de cada módulo (ver abaixo e [Onde ficam os testes](#onde-ficam-os-testes)).
- **Contratos do estoque**: o catálogo, o carrinho, o checkout e o dashboard não usam o `StockRepository` diretamente, e sim quatro contratos em `Modules\Inventory\Contracts`, que falam em ids e quantidades:
  - `StockInitializer::createForProduct(productId, quantity)`, usado pelo `CreateProductUseCase` para abrir o estoque de um produto novo, na mesma transação.
  - `StockLevels::quantitiesFor(ProductIds): StockQuantities`, usado pelo `ValidateCartUseCase`, sem bloqueio, e `StockLevels::stocksFor(ProductIds): ProductStocks`, que devolve o id da linha e a quantidade de cada produto e é usado pela consulta do Catalog (`ProductQuery`) para o cadastro de produto do admin, numa leitura só para a página inteira (a vitrine também o usava até a HEL-17, quando passou a ler a parte `stock` do modelo de leitura).
  - `StockMetrics::totals(): StockTotals`, usado pela consulta do Backoffice (`DashboardQuery`): os produtos com unidades e o total de unidades.
  - `StockReservation::lockForProducts(ProductIds): StockQuantities` / `decrement(productId, quantity)`, usados pelo `PlaceOrderUseCase` para bloquear e debitar o estoque.
  - Os que só leem (`StockLevels` e `StockMetrics`) são implementados por consultas do Inventory (`StockLevelsQuery` e `StockMetricsQuery`); os que gravam ou travam (`StockInitializer` e `StockReservation`) pelo `StockRepository`. Todos são ligados no `InventoryServiceProvider` (`$bindings`). A transação, a ordem dos bloqueios e o `FOR UPDATE` continuam idênticos.
  - O teste de arquitetura `ModuleBoundariesTest` falha se qualquer outro módulo, o Backoffice incluído, usar o `StockRepository`.
- **Conta x comprador**: a tabela `customers` tem dois modelos com papéis distintos.
  - `CustomerAccount` (Identity) é a **identidade** do comprador (login, senha e perfil, guard `customer`) e não conhece pedidos. `User` (Identity) é só a equipe (guard `staff`), não se liga a nenhuma tabela de negócio e é proibido no Ordering, no Payment e no Fulfillment (`ModuleBoundariesTest`, uma expectativa por namespace).
  - `Customer` (Ordering) é o **comprador visto pelo lado de pedidos**: projeção somente leitura de `customers` com `id`, `name` e `email`, que lança `LogicException` se alguém tentar gravar por ela. O pedido aponta para ele em `Order::customer()`. O cliente autenticado entra no Ordering só como id: o dono do pedido é conferido na própria busca (`OrderRepository::findForCustomer` e `findForPayment`), sem conhecer o `CustomerAccount`.
  - O `OrderRepository` filtra pedidos pelo id do cliente (`paginateForCustomer`, `recentForCustomer`, `countForCustomer`, `countPerCustomer`), sem receber a conta. Os três últimos formam o contrato `CustomerOrderHistory`, que o Customers usa: o `countPerCustomer` recebe `CustomerIds` e devolve `OrderCountsByCustomer`, e o `recentForCustomer` devolve `OrderSummaries`.
  - A tela **Clientes** do admin (`/api/admin/customers`) junta as duas partes no `CustomerSummaryDTO` / `CustomerSummaryResource`, montados pelos casos de uso `ListCustomersUseCase` e `ShowCustomerUseCase`. A listagem faz sempre 2 consultas por página (contas e, depois, a contagem de pedidos agrupada). O Customers cria, edita, busca e lista as contas pelo contrato `CustomerAccounts` do Identity (implementado pelo `CustomerAccountRepository`), que devolve `CustomerProfile`, e o `ShowCustomerUseCase` e o `UpdateCustomerUseCase` buscam o cliente do admin por ele, pelo id da rota (`404` para conta inexistente).
  - A coluna é `orders.customer_id` (→ `customers.id`, `restrict`). A `CustomerAccount` ficou no Identity, e não no Customers, porque o Ordering dependeria do Customers, que já depende do Ordering, e isso criaria um ciclo.
  - Contrato das contas: o recurso do cliente é `{ id, name, email, created_at }` (sem `role`) e o da equipe `{ id, name, email, role, role_label, created_at }`, com `role_label` `Administrador` ou `Suporte`. `GET /api/account` devolve o cliente em `data.customer`.
- **Ids UUID v7 e nenhum binding nas rotas** (HEL-11, design em [.design/uuid-record-ids.md](.design/uuid-record-ids.md)):
  - **O id é um UUID v7 gerado pela aplicação**, em texto, em toda fronteira: tabelas (`uuid` nativo), contratos, eventos, listas tipadas, value objects, DTOs, use cases, respostas e URLs. O v7 é ordenado no tempo, então as inserções têm localidade no índice (RFC 9562) e as ordenações `latest()->latest('id')` continuam desempatando por ele. Ofuscar o inteiro (Sqids) foi descartado: o número continuaria sequencial por baixo. O custo aceito: os 48 bits mais significativos do v7 são o instante de criação, e expor o id revela quando o registro nasceu. Um UUID também não é credencial: o dono continua sendo conferido.
  - **A forma canônica mora em `Shared\Support\RecordId`** (`[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}`, só minúsculas). Dela leem os `Route::pattern` dos service providers dos módulos donos (um por parâmetro: `product`, `category`, `stock`, `customer`, `user`, `order` e `address`), a regra `CanonicalRecordId` dos ids do corpo e as listas tipadas. `whereUuid` e a regra `uuid` do Laravel foram descartadas porque aceitam maiúsculas, e um id da própria conta em maiúsculas passaria como outro na comparação de texto do `DELETE /api/admin/users/{user}`. Um id malformado nunca chega ao banco, porque o PostgreSQL responde com erro (e não com "nenhuma linha") a um texto que não é UUID: no caminho é `404` sem query, no corpo é `422` (com `bail` antes de qualquer `exists`).
  - **Nenhuma rota resolve registro** (nem binding implícito, nem explícito). O controller carrega o id como `string`, e o use case ou o repositório o resolve. Isso desfez o desvio que a análise de domínio registrava para o pagamento. O custo aceito: o corpo é validado antes da existência. Um binding explícito por parâmetro foi descartado: não economizaria nenhuma query e deixaria dois mecanismos.
  - **O dono entra na busca, e as policies que só conferiam o dono saíram.** O registro de outro cliente é indistinguível de um id desconhecido (`404`). Manter a policy como segunda checagem, respondendo `403`, confirmaria que o registro existe. Conferir o dono depois da busca, no use case, deixaria o `409` de status sair antes do `404`.
  - **O código do pedido** (`orders.code`): sorteado, independente do id, único e imutável. Uma colisão nunca derruba o pedido: o `PlaceOrderUseCase` isola a gravação num savepoint e sorteia de novo, até 3 vezes (o savepoint é necessário porque, no PostgreSQL, uma violação de unicidade aborta a transação inteira). O laço fica no use case, e não no repositório, porque um repositório não usa os services do módulo. Um código derivado do UUID foi descartado (não é único garantido), e uma sequência também (revelaria a contagem de pedidos).
  - **Sem migração de dados**: as migrations de criação das 9 tabelas foram editadas no lugar. Quem tem banco local roda `make fresh` e `make queue-restart`, e dumps antigos de `storage-dumps/` deixam de ser compatíveis.
- **Facades dos módulos** (HEL-7, design em [.design/module-facades.md](.design/module-facades.md)):
  - **A facade de um módulo é o seu `Contracts` mais os seus `Events`.** Contratos por papel, só para travessias que existem, nomeados pelo papel de que o chamador precisa: `ProductCatalog` (Catalog), `StockLevels` (Inventory), `PayableOrders` e `CustomerOrderHistory` (Ordering) e `CustomerAccounts` (Identity), ao lado dos que já existiam. Quando o fornecedor já depende do consumidor, o consumidor define o contrato (`ProductOrderHistory`, `DeliveryAddressBook`, `ShippingQuoter`). Uma interface única por módulo foi descartada: todo consumidor dependeria de tudo.
  - **O pagamento lia o pedido pelo `PayableOrders`** (hoje pela própria porta `OrdersToPay`, ver a isolação abaixo), dentro do `PayOrderUseCase`, com o id do cliente e o id do pedido (`findForPayment($customerId, $orderId)`). Um pedido desconhecido ou de outro cliente é `404`, antes da conferência do status. A resposta `202` passou a ser a tentativa de pagamento (o frontend só lê o `message`).
  - **A conta e o admin de clientes mostram pedidos resumidos** (`id`, `status`, `status_label`, `total_cents`, `created_at`), que são os campos que as telas usam. As relações Eloquent `Payment::order()` e `OrderItem::product()` saíram; as chaves estrangeiras continuam.
  - **Exceções declaradas na HEL-7, removidas na HEL-6**: o Backoffice lia os repositories de quatro módulos e o enum `ProductStatus` do Catalog, e a vitrine e a lista de estoque do admin usavam `Product::stock`, `Stock::product` e a regra de disponibilidade do Ordering. As 9 saíram do `ModuleBoundariesTest` (ver "Lado de leitura" abaixo).
- **Vocabulário público dos módulos** (HEL-10, design em [.design/module-vocabulary.md](.design/module-vocabulary.md)):
  - **Público é o que uma assinatura expõe**, não a pasta. O `ModuleBoundariesTest` lê por reflexão os métodos públicos das interfaces de `Contracts` e das classes de `Events` de cada módulo e segue os tipos nativos (parâmetros, retornos, propriedades públicas) em cada classe alcançada. Hoje são 30 tipos públicos (por exemplo `CatalogProducts`, `ProductStatus` e `ProductAvailability` pelo `CatalogProduct`, `StockQuantities` e `ProductStocks`, `Email` e `Password` pelo `CreateUserDTO`, `OrderStatus` pelo `OrderForPayment`, `OrderToPay` pela porta `OrdersToPay`, `DeclineReason` e `PaymentStatus` pelo `ChargeResult`) e 30 privados (por exemplo `ValidatedCart`, `OrderLines`, `ProductQuantities`, `ProductRecord`, `StockRecord`, `LoginCredentialsDTO`, `UserRole` e, desde a HEL-17, o `CategoryRecord` e o `ProductSnapshot` da reconstrução da vitrine). Os eventos da vitrine só levam escalares e listas de escalares, então não somaram nenhum tipo público. Um tipo novo numa assinatura fica público e uma classe nova que nenhuma assinatura alcança fica privada, sem editar o teste.
  - **O `Http` de cada módulo é privado, sem exceção.** O Customers tem o seu `EmailRule`, `PasswordRule`, `NormalizesEmailInput`, `NormalizesStateInput` e `CustomerProfileResource`, e o Fulfillment o seu `NormalizesStateInput`. Eles só traduzem para o Laravel o que `Email`, `Password` e `BrazilianState` decidem (o `HttpAdaptersTest` cobra), e o `CustomerProfileResourceTest` mantém a conta no mesmo formato no Identity e no Customers. Expor as peças do Identity e do Ordering como exceção foi descartado: poria código de framework na superfície pública.
- **Lado de leitura** (HEL-6, design em [.design/cqrs-read-side.md](.design/cqrs-read-side.md)):
  - **Toda leitura que cruzava módulo virou uma consulta** do módulo que serve a rota (`Queries/`), que devolve dados e pede ao dono, pelo contrato dele, o que não é seu: a vitrine (até a HEL-17) e o cadastro de produto do admin pedem o estoque ao `StockLevels::stocksFor`, a lista de estoque pede os produtos ao `ProductCatalog`, e o dashboard lê o `CatalogMetrics`, o `StockMetrics`, o `CustomerMetrics` e o `OrderMetrics`. Um contrato de leitura é implementado por uma consulta do dono, e um contrato que grava ou trava continua no repositório. `Product::stock` e `Stock::product` (HEL-6) e o caso de uso do dashboard deixaram de existir; a chave estrangeira `stocks.product_id` e a exclusão em cascata continuam.
  - **A leitura é sempre exata.** Um modelo de leitura projetado pelos eventos do outbox foi descartado: nenhuma leitura lenta foi medida, a projeção pediria eventos de produto, estoque e cliente que não existem, e a vitrine ficaria alguns segundos atrasada. *(Revisto na HEL-17 para a vitrine, como estudo do padrão e não por uma leitura lenta: ver "Modelo de leitura da vitrine" abaixo. As outras leituras desta rodada continuam exatas, no PostgreSQL.)* Uma consulta que junta as tabelas de outro módulo num SQL só também: faria uma query a menos por página, mas o Catalog dependeria do esquema de `stocks` sem contrato.
  - **Uma leitura por módulo para a página inteira**: a vitrine faz o mesmo número de queries com 1 e com 12 produtos, e a lista de estoque com 1 e com 20 linhas (o teste mede). As contagens da HEL-11 não mudaram (abrir produto 3, editar 10, mudar o status 4, ajustar o estoque 3 com um só `FOR UPDATE`).
  - **O caso de uso de escrita devolve os dados do que gravou**, num value object, e a consulta completa a resposta sem reler o registro. Devolver só o id e reler depois do commit somaria uma query a cada escrita e quebraria as contagens da HEL-11.
  - **A regra de disponibilidade passou do Ordering para o Catalog** (`ProductAvailability`, ver [Regra de disponibilidade do produto](#regra-de-disponibilidade-do-produto)). A parte "sem linha de estoque é zero" fica onde a quantidade é lida: no `StockQuantities` do Inventory (carrinho e checkout) e no `ProductView` do Catalog (vitrine e cadastro), que usa 0 unidades para o produto sem linha.
  - **A única diferença de corpo**: um produto sem linha de estoque (estado que as rotas não produzem, porque a linha nasce com o produto) agora responde `is_available: false` e `available_quantity: 0`, como o carrinho já respondia, no lugar de `null`.
  - **O dashboard devolve um `array`** (o corpo da resposta, montado pelo `DashboardService`) e não um value object: o serviço já era assim e converter mudaria as asserções sem ganho.
- **Outbox transacional** (HEL-9, design em [.design/transactional-outbox.md](.design/transactional-outbox.md)):
  - **O evento é gravado na transação da mudança que ele anuncia**, e gravar sem transação aberta é erro de programação. A recusa confere as transações que contam para os callbacks de commit (`app('db.transactions')->callbackApplicableTransactions()`), e não o `DB::transactionLevel()`: dentro do `RefreshDatabase` o nível nunca é zero, e a recusa não poderia ser provada. Despachar em `DB::afterCommit` foi descartado: é a mesma janela de antes, porque o envio falha depois do commit. O driver de fila `database` também funcionaria como outbox, mas prende os eventos aos jobs do Laravel, que é o que a HEL-13 quer deixar.
  - **Um relay por polling, num container próprio** (`outbox-relay`), em vez de uma tarefa do scheduler por minuto (até 1 min de espera), de um job de relay despachado depois do commit (que esperaria o próximo evento se o envio falhasse) ou de `LISTEN/NOTIFY` do PostgreSQL (mais uma conexão para gerenciar).
  - **O envelope tem formato próprio**: o nome `<módulo>.<evento>` e a versão, não a classe PHP (a HEL-12 vai mudar os namespaces), e o payload escrito por cada evento (`toPayload`), não derivado das propriedades por reflexão, para que renomear uma propriedade não mude o formato publicado sem ninguém perceber.
  - **O transporte é uma porta** (`EventTransport`) e cada consumidor se inscreve no módulo que consome. A forma mais leve, republicar as mensagens no dispatcher do Laravel com os listeners `ShouldQueue` de antes, perdeu porque a HEL-13 pede consumidores fora dos jobs do Laravel. A inscrição explícita venceu a descoberta pelo tipo do `handle()`: com 5 consumidores, a lista no service provider é mais fácil de ler.
  - **Uma tabela só**, sem chave estrangeira, com a migration junto das demais até a HEL-12. Sem inbox por consumidor, sem histórico nem replay, e sem garantia de ordem entre mensagens.
  - **Sem migração de dados**: a tabela é nova. Quem tem banco local roda `make fresh` (ou `make migrate`) e `make queue-restart`, e o `make up` sobe o container novo.
- **Pagamento recusado** não cria status no pedido: a recusa (`402`) é gravada em `payments` e o pedido continua em `awaiting_payment`, mantendo os quatro status de negócio da especificação.
- **Gateway de pagamento atrás de uma porta:** o `PayOrderUseCase` só conhece a interface `PaymentGateway`, ligada ao `FakePaymentGateway` no `PaymentServiceProvider`. Trocar de gateway é uma classe nova e uma ligação. O fake decide pelo cartão de teste (`fake_card_approved`, `fake_card_insufficient_funds`, `fake_card_declined`; qualquer outro é cartão inválido). A cobrança é síncrona: um gateway que confirme depois (Pix, webhook) exigiria um estado pendente. Ver [.design/fake-payment-gateway.md](.design/fake-payment-gateway.md).
- **Nenhum proxy confiável** (o IP forjado, achado na discovery da HEL-14): o `trustProxies(at: '*')` saiu do `bootstrap/app.php`. Com ele, o Laravel aceitava o `X-Forwarded-For` de qualquer conexão, e um cliente trocava o próprio IP, e com ele a chave do limite, a cada requisição, anulando o `throttle:10,1` do login e do cadastro contra força bruta. Agora o IP é o `REMOTE_ADDR` que o Nginx entrega por FastCGI (ver [Containers](#containers)). Duas alternativas foram descartadas:
  - **Confiar só na rede do Compose** não fecha a falha: o `REMOTE_ADDR` não é o Nginx, e sim quem se conectou a ele, que para o tráfego do host é o gateway da própria rede. Confiar na rede seria confiar em todo o tráfego do host.
  - **Manter o `*` e fazer o Nginx sobrescrever o `X-Forwarded-For`** exigiria sobrescrever também o `X-Forwarded-Host`, `-Proto`, `-Port` e `-Prefix`, que o `*` também aceitava do cliente, e a correção só existiria no Nginx, onde um teste Pest não a alcança.

  O `fastcgi_param HTTP_X_FORWARDED_PROTO` do Nginx saiu junto, porque o Laravel não o lia mais: o esquema vem do `HTTPS` do `fastcgi_params`. O `AuthTest` prova a correção: a 11ª tentativa de cadastro ou de login, com um `X-Forwarded-For` forjado diferente em cada uma, ainda recebe `429`.
- **Um contador por rota** (achado no smoke test da correção do IP forjado): o `throttle:N,M` do Laravel monta a chave só com o cliente (o id do usuário, ou o domínio e o IP, em `ThrottleRequests::resolveRequestSignature`), sem a rota. As 7 rotas com limite dividiam um só contador por cliente, e cada uma comparava o total com o próprio limite: 10 cotações de frete no mesmo minuto deixavam o login do mesmo IP em `429`. Agora o terceiro parâmetro do `throttle` nomeia o contador de cada rota (`throttle:10,1,customer-login`) e entra na frente da chave. Os limites não mudaram.
  - **O nome no `throttle` venceu os limitadores nomeados** (`RateLimiter::for` num service provider). Os dois separam os contadores, mas o nome é uma palavra a mais em cada rota e deixa o limite escrito nela. Os limitadores nomeados passam a valer a pena quando uma rota precisar de mais de um limite, de um limite diferente para visitante e cliente, ou de uma chave além do cliente (por exemplo, o e-mail tentado no login).
  - **O cadastro e os dois logins não dividem um contador.** Um contador comum não reduz a força bruta: contra cada formulário, o atacante continua com 10 tentativas por minuto por IP. E um formulário bloquearia os outros para todos atrás do mesmo IP (uma rede de escritório, ou o gateway do Compose no Docker local): as senhas erradas de clientes travariam o login da equipe, e um cadastro recusado (o `422` também conta) gastaria as tentativas de login.
  - O `RateLimitTest` percorre a tabela de rotas e falha se uma rota com `throttle` não nomear o contador ou repetir o nome de outra, e esgota cada rota para provar que as outras continuam abertas.
- **Contar antes de conferir** (achado no perfil `limits` do k6, HEL-14: numa rajada de 30 logins simultâneos com senha errada, passaram 11 contra o limite de 10): o `ThrottleRequests` do Laravel lê a contagem, compara com o limite e só depois soma a requisição. As requisições que chegam juntas leem a contagem antes de qualquer uma somar, e todas passam: no `RateLimitBurstTest`, com 30 processos soltos no mesmo instante, passaram as 30. Agora o alias `throttle` é o [`AtomicThrottleRequests`](backend/app-modules/shared/src/Http/Middleware/AtomicThrottleRequests.php), que soma primeiro e deixa passar a requisição cuja contagem não passa do limite. Os limites, os nomes dos contadores e o `429` (a exceção e os headers do framework) não mudaram.
  - **O incremento também precisava ser um comando só.** O `RateLimiter::increment` do framework cria o contador em zero, incrementa e, quando o incremento diz 1 mas a criação falhou, grava 1 por cima. As requisições que abrem a janela se intercalam ali, e a gravação apaga os acertos das outras: só com a ordem trocada no middleware, passaram até 19 de 30, em 12 de 40 rajadas. O [`AtomicRateLimiter`](backend/app-modules/shared/src/Cache/AtomicRateLimiter.php) incrementa e dá a validade da janela num script Lua, e com as duas mudanças passaram exatamente 10 nas 40 rajadas. Ele entra por um `extend` no `AppServiceProvider` (o framework registra o `RateLimiter` num provider adiado, que substituiria uma ligação comum ao carregar), na mesma store (`cache.limiter`) e com as mesmas chaves (o contador e o `:timer` do `Retry-After`).
  - **O `throttleWithRedis()` do Laravel não resolve**: o `ThrottleRequestsWithRedis` confere num script, soma em outro e ignora a resposta do próprio `acquire()`; passaram 21, 13 e 15 de 30 em três rajadas. O `RateLimiter::attempt` também confere antes de somar. Uma trava (`Cache::lock`) em volta da conferência e da soma faria as requisições da mesma chave esperarem umas pelas outras.
  - **Uma requisição recusada também conta**, porque soma antes de saber. A janela não estica: termina um minuto depois do primeiro acerto, e o `Retry-After` continua vindo dela. Com mais de um limite na rota (hoje nenhuma tem), a requisição recusada por um já contou nos anteriores. Um limite com `after()` só sabe se conta depois da resposta e continua na ordem do framework.
  - Fora do Redis, o incremento é o do framework: a store `array`, dos testes e dos perfis de volume do k6, vive num processo só.
- **Um banco de testes por checkout** (visto em 17/10/2026, com duas suítes rodando ao mesmo tempo em worktrees diferentes: `deadlock detected` e `relation "migrations" does not exist`): todos os checkouts dividem o projeto Compose `ecommerce`, e todos testavam no mesmo `ecommerce_testing`. Agora o Makefile dá a cada `git worktree` o banco `ecommerce_testing_<nome>` e um container `api` avulso com o próprio `backend/`; a pasta principal e o CI continuam no `ecommerce_testing` e no `api` de dev (ver [Um banco de testes por checkout](#um-banco-de-testes-por-checkout)).
  - **O worktree é reconhecido pelo git**, sem variável a definir: o `git rev-parse --git-dir` de um worktree termina em `.git/worktrees/<nome>`, e o git não repete um nome no mesmo repositório. Um segundo clone (que não é um worktree) se comporta como a pasta principal e roda no `api` de dev, que monta o `backend/` de quem subiu a pilha.
  - **Um projeto Compose por worktree** (o `name` vindo de uma variável) foi descartado: duplicaria `db`, `redis`, `nginx` e o frontend, as portas publicadas (`8080` e `5433`) colidiriam, e cada worktree precisaria do próprio `make setup`.
  - **Os outros alvos que leem o código seguem o mesmo critério** (`make pint`, `make shell`, `make artisan`, `make test-frontend` e `make frontend-shell`): num worktree, o `exec` no container de dev abriria a pasta principal. O Pint ganhou o alvo `make pint`, que o CI e o AGENTS.md usam, no lugar do `docker compose exec -T api ./vendor/bin/pint --test` escrito à mão. Os alvos que agem sobre o ambiente de dev (`migrate`, `seed`, `fresh`, `optimize`, `clear`, `queue*`) ficaram no `api` de dev: as migrations de um worktree no banco de dev deixariam o esquema fora do código que a pasta principal serve. Quem quiser isso de propósito usa o `make artisan CMD=migrate` do worktree.
  - **O `--parallel` do Laravel não resolve**: ele separa os processos de uma suíte, e os bancos dele derivam do `DB_DATABASE` (`<banco>_test_<n>`). Duas suítes sobre o mesmo `ecommerce_testing` disputariam os mesmos bancos.
  - **A sonda do `RateLimitBurstTest` fica em `/api`** (achado numa rodada da suíte inteira num worktree novo, que deu `[204 => 10, 429 => 19, 500 => 1]`). Fora de `/api`, o `429` era a página HTML do framework (`errors::429`), e sem o view já compilado (um checkout novo, ou depois de um `view:clear`) os 20 filhos recusados compilavam o Blade ao mesmo tempo. O compilador grava num arquivo temporário e o renomeia por cima do compilado, o que é atômico num sistema de arquivos Linux, mas não no bind mount (`virtiofs`) do Docker Desktop no macOS: ali, o arquivo que outro filho está renomeando some por um instante, e o `require` (ou o `hash_file` que compara o conteúdo) falha com `No such file or directory`, o que vira um `500`. Com os views apagados antes de cada rodada, o teste passou em 1 de 10; com os views compilados no sistema de arquivos do próprio container, em 15 de 15; com a sonda em `/api`, cujo `429` é o JSON do `ApiExceptionRenderer`, em 30 de 30. A API não muda: as rotas com `throttle` já ficam em `/api` e respondem em JSON, e o CI, em Linux, não tem a corrida. O filho agora devolve, junto com o status, a exceção de um `5xx` (ou de uma requisição que lançou), e a falha do teste mostra o motivo.
- **O transporte dos eventos por feature flag** (HEL-13, design em [.design/event-transport-flag.md](.design/event-transport-flag.md) e plano em [.specs/features/event-transport-flag](.specs/features/event-transport-flag/)): a porta `EventTransport` da HEL-9 ganhou o RabbitMQ como segunda implementação, e o relay escolhe uma das duas pela flag `event-transport`.
  - **A flag fica no banco, não no Redis**: com o Redis fora, ninguém leria nem trocaria uma flag guardada nele. Uma variável de ambiente exigiria reiniciar o relay, e a decisão foi trocar sem deploy. O Pennant (driver `database`) foi escolhido porque a HEL-13 é sobre usar feature flags, e os escopos dele servem às próximas.
  - **`php-amqplib`, e não a extensão `amqp` do PECL nem o `laravel-queue-rabbitmq`**: a extensão compila uma biblioteca C a mais na imagem Alpine, e o `php-amqplib` atende a carga; o driver de fila manteria os consumidores como jobs do Laravel, o contrário do objetivo (ver um consumidor fora dos jobs). A imagem ganhou a extensão `sockets`.
  - **Uma mensagem por evento, e o broker copia para cada consumidor.** O produtor não lista os consumidores (como já era no Redis, onde o `QueueEventTransport` lê as inscrições) e um consumidor fora do PHP só precisa de uma fila ligada à exchange.
  - **Fila `quorum` com `x-delivery-limit` 2 e `basic_reject` com requeue**, a partir do RabbitMQ 4.3. O `basic_nack` com requeue não conta para o limite de entregas desde essa versão, e a mensagem ficaria em loop; o `basic_reject` conta. Por isso a imagem é a `4.3.5` (a `4.3.6`, de 16/10/2026, era nova demais para fixar).
  - **O nome da fila é o nome estável do consumidor, dado na inscrição**, e não derivado da classe: a HEL-12 vai mudar os namespaces, e a fila ficaria órfã com mensagens dentro. Uma constante em cada consumidor mudaria os cinco, e o Review da HEL-9 pede que nenhum mude.
  - **Argumentos de fila, e não políticas**: as políticas ficam fora da aplicação e precisariam ser criadas em cada vhost (dev, carga e cada vhost de testes).
  - **Um vhost de testes por checkout**, como o banco: duas suítes em worktrees diferentes veriam as filas uma da outra. Um broker simulado não provaria as 3 tentativas, a fila de mortas nem a confirmação.
  - **O Horizon fecha pelo alias `admin`**, e o portão dele é aberto (`Horizon::auth(fn () => true)`): o portão padrão abre o painel a qualquer um em `local` e, fora dele, usa o guard padrão, que não é o `staff`.
  - **A medição no k6 ganhou uma segunda tabela**, e não uma coluna na da HEL-14: a coluna quebraria as provas `docs.sh baseline-rows` e `profiles.test.js`, que fixam aquela tabela e os limites de aprovação que saem dela (ver o [README da pasta](load-tests/README.md)).
- **Módulos isolados, piloto Payment ↔ Ordering** (design em [.design/module-isolation.md](.design/module-isolation.md) e plano em [.specs/features/module-isolation-pilot](.specs/features/module-isolation-pilot/)): cada módulo de negócio deve ser uma unidade completa, que sobe e passa os próprios testes sem os outros. O piloto fez isso com o Payment e deixou a forma que as próximas travessias copiam.
  - **A porta é do consumidor, no vocabulário dele**: o Payment declara `OrdersToPay` e `OrderToPay` no próprio `Contracts` e nos próprios `ValueObjects`, e o status completo do pedido não atravessa, só se ele aguarda pagamento. O Ordering continua oferecendo o `PayableOrders`, que agora só o adaptador do `Integration` usa.
  - **O pacote `Integration` é a única exceção de fronteira**: é infraestrutura de composição, não removível, e o único que usa mais de um módulo de negócio, sempre pelas fachadas deles (o `ModuleBoundariesTest` o trata como mais uma origem das regras). A raiz (`app/`) continua só com o framework.
  - **Sem o dono, `503`, e nada gravado**: com o Ordering ausente, a porta lança `ServiceUnavailableException` (Shared), e a rota responde `503 SERVICE_UNAVAILABLE` antes de cobrar. Nenhuma resposta parcial nem dado inventado. Um evento sem consumidor instalado é publicado e ninguém reage.
  - **O evento é lido por uma classe do consumidor**: o formato publicado é o nome, a versão e as chaves do payload, então nenhum compilador liga a leitora à produtora; o `OrderEventPayloadTest` faz isso, e só os consumidores e o provider usam a classe leitora (só o produtor grava o evento).
  - **Sem chave estrangeira entre módulos**: `payments.order_id` perdeu a chave. É a única exceção à regra de nunca editar uma migration executada: a de criação perdeu só a referência a `orders`, e uma migration nova apaga a chave onde ela existir.
  - **Testes no módulo**: os testes que usam só um módulo que sobe sozinho foram para o `tests/` dele (27 arquivos, em 5 módulos), e o `backend/tests` virou a suíte de composição. A prova de remoção roda a pasta de cada um numa cópia sem os outros módulos de negócio. O Inventory ficou para depois porque a migration de `stocks` aponta para `products`.
  - **Para quem já tem o ambiente**: `composer install` (pacote novo), `make migrate` (apaga a chave nos bancos já migrados) e `make queue-restart` (o consumidor `MarkOrderAsPaid` lê outra classe).
- **Modelo de leitura da vitrine** (HEL-17, design em [.design/storefront-read-model.md](.design/storefront-read-model.md) e plano em [.specs/features/storefront-read-model](.specs/features/storefront-read-model/)): a vitrine passou a ser o lado de leitura de um CQRS com armazenamento e processo próprios (ver [O modelo de leitura da vitrine](#o-modelo-de-leitura-da-vitrine)). Foi um estudo do padrão, e não a resposta a uma dor medida: no `load` de 20/10/2026 a vitrine tinha p95 de 24 ms, e a ruptura vinha da fila do PHP-FPM, não do banco.
  - **Sem leitura de reserva**: com o MongoDB ou o `api-read` fora do ar, a vitrine responde `503 SERVICE_UNAVAILABLE`, e nunca lê o PostgreSQL. A vitrine pode ficar alguns segundos atrás da escrita, e isso foi aceito porque quem decide a venda é o checkout, no PostgreSQL e com lock.
  - **O evento leva o estado inteiro da parte que mudou e a versão do registro**, e nunca uma diferença (um evento de estoque leva a quantidade gravada, não as unidades vendidas: uma entrega repetida baixaria o estoque duas vezes). Eventos só com o id, que o projetor completaria lendo a escrita, foram recusados: criariam uma leitura do PostgreSQL no lado de leitura.
  - **A versão vem da escrita, por registro, e só cresce**: a linha é travada (`SELECT ... FOR UPDATE`) e a versão soma 1 na mesma transação. A versão pelo relógio (`updated_at`) ou pelo id da mensagem foi descartada (duas escritas no mesmo segundo empatam, e o id não diz a ordem das transações), e uma sequência global também (acoplaria escritas que não disputam a mesma linha).
  - **O banco decide a versão**: cada gravação do projetor é um `updateOne` com `upsert` e a regra no filtro, então duas entregas simultâneas terminam na versão maior sem trava na aplicação. A exclusão deixa uma lápide (`deleted_version`) para um evento atrasado nunca ressuscitar o registro.
  - **A disponibilidade é decidida na leitura**, pelo `ProductAvailability`: com o produto e o estoque chegando por eventos diferentes, um `is_available` gravado ficaria preso à parte que chegou por último e escreveria a regra uma segunda vez.
  - **O Nginx é o gateway**, e o `api-read` é o mesmo código e a mesma imagem do `api`, sem credencial do PostgreSQL. Um gateway dedicado (Traefik, Kong, KrakenD) só ganha quando a borda precisar de autenticação, limite ou descoberta, e uma aplicação de leitura separada, quando o lado de leitura precisar de deploy, versão e escala independentes.
  - **A sincronização é pelo outbox, e não por CDC**: o CDC das tabelas de domínio prenderia a projeção ao esquema de dois módulos, e o CDC da `outbox_messages` (Debezium) trocaria o relay de todos os eventos. O PostgreSQL com `jsonb` como banco de leitura também foi descartado: seria o mesmo banco da escrita, e o estudo é um banco de leitura de outro tipo.
  - **A reconstrução republica pelo outbox** o estado atual de cada registro (`make storefront-rebuild`). Reconstruir do histórico do outbox não serve, porque as mensagens publicadas ficam só 7 dias e os seeders gravam sem evento.
  - **Para quem já tem o ambiente**: `make build` (a extensão `mongodb` na imagem PHP), `composer install` (o pacote `mongodb/laravel-mongodb`), `make up` (os containers `mongodb` e `api-read`), `make migrate` (a coluna `version`), `make storefront-rebuild` (o MongoDB nasce vazio) e `make queue-restart` (os 8 consumidores novos).
