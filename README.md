# Loja Demo — e-commerce de estudo (Laravel 13 + Vue 3)

Aplicação de e-commerce **pequena, mas tecnicamente completa**, criada para estudo e testes técnicos. O foco não é ter muitas funcionalidades, e sim implementar corretamente conceitos importantes de engenharia de software:

- API REST com Laravel (Form Requests, API Resources, Middleware)
- Autenticação SPA com **Laravel Sanctum** (cookie HTTP-only, sem token no `localStorage`)
- Autorização por papel (`admin` / `customer`)
- Relacionamentos **1:1** (`Product` ↔ `Stock`) e **N:N** (`Product` ↔ `Category`)
- Carrinho com **Pinia** e checkout validado no backend
- **Transação atômica** + `lockForUpdate()` para controle de **concorrência** de estoque
- **Eventos pelo outbox transacional**, consumidores e jobs assíncronos em fila **Redis**
- Endereços de entrega, **frete por UF** calculado pelo servidor e **data prevista** de entrega
- Dashboard administrativo com dados agregados no backend
- Testes automatizados com **Pest**
- Ambiente 100% **Docker** e automação com **Makefile**
- Dados de demonstração com **Factories, Seeders e Faker**

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
12. [Eventos, outbox, consumidores e filas](#eventos-outbox-consumidores-e-filas)
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
| Banco / cache / fila | PostgreSQL 18, Redis 8, Laravel Queue (driver `redis`) |
| Frontend | Vue.js 3, Pinia 4, Vue Router 5, Axios, Vite 8, TypeScript, Tailwind CSS 4, Chart.js |
| Infraestrutura | Docker, Docker Compose, Nginx, Node.js 24 LTS, Makefile |

---

## Arquitetura

```
                      Navegador  →  http://localhost:8080
                            │
                     ┌──────▼──────┐
                     │    nginx    │  ponto único de entrada
                     └──┬───────┬──┘
        /api/*, /sanctum/*  │       │  /* (todo o resto)
                 ┌──────────▼─┐   ┌─▼──────────┐
                 │ api        │   │ frontend   │
                 │ PHP-FPM    │   │ Vite (Vue) │
                 └──┬──────┬──┘   └────────────┘
                    │      │
            ┌───────▼┐   ┌─▼──────┐      ┌──────────────┐
            │   db   │   │ redis  │◄─────┤ queue-worker │  php artisan queue:work redis
            │ PG 18  │   │ fila / │      │ (mesma imagem│
            └▲───▲───┘   │ sessão │      │   da api)    │
             │   │       └───▲────┘      └──────┬───────┘
             │   └───────────┼──────────────────┘
             │           ┌───┴──────────┐
             └───────────┤ outbox-relay │  php artisan outbox:relay
                         │ (mesma imagem│  outbox_messages → fila
                         │   da api)    │
                         └──────────────┘
```

- **Frontend e backend no mesmo host** (`localhost:8080`): o Nginx encaminha `/api/*` e `/sanctum/*` para o Laravel e o restante para o Vite. Isso permite autenticação por cookie de sessão sem CORS.
- **Backend em Laravel organizado em módulos** (`app/Modules/<Módulo>`), um por contexto de negócio. Continua sendo **um monólito**, com um único banco e um único deploy, sem microsserviços. A separação entre leitura e escrita vale só para as leituras que cruzam módulos, que são consultas (camada `Queries/`, abaixo), e nada é projetado: a leitura é sempre exata. Os contextos e as fronteiras entre eles estão em [docs/domain-analysis.md](docs/domain-analysis.md).
- **A facade de cada módulo é o seu `Contracts` mais os seus `Events`** (HEL-7, design em [.design/module-facades.md](.design/module-facades.md)). Um módulo usa outro só por interfaces de `Contracts/`, injetadas no construtor e ligadas, no service provider do módulo dono, a uma consulta dele (um contrato que só lê) ou a um repositório dele (um contrato que grava ou trava), e pelos eventos dele. Contratos e eventos falam em dados (ids, value objects, enums), nunca em models do Eloquent. Todas as outras pastas são privadas, inclusive uma pasta nova e o `Http`. De `DTOs`, `ValueObjects` e `Enums`, só é público o **vocabulário** do módulo (HEL-10, design em [.design/module-vocabulary.md](.design/module-vocabulary.md)): as classes dele que os tipos nativos das assinaturas dos seus `Contracts` e `Events` alcançam (parâmetros, retornos e propriedades públicas, o construtor incluído), de forma transitiva. O vocabulário é calculado a cada execução do teste, sem lista guardada: um tipo citado só em docblock não conta, e o contrato de um módulo não torna público um tipo interno de outro. Desde a HEL-6 o teste não declara nenhuma exceção.
- **Dentro de cada módulo, as mesmas camadas**: validação em Form Requests e respostas com API Resources.
  - **Controllers** (`Http/Controllers`): só HTTP (request, autorização, resposta). Leituras simples, que não cruzam módulo, chamam um repository direto.
  - **Casos de uso** (`UseCases/`): cada um representa uma intenção do usuário ou do sistema (ex.: `PlaceOrderUseCase`) e orquestra o fluxo: transação, repositories, services, eventos e jobs.
  - **Services** (`Services/`): regras de negócio **puras**, sem acesso a banco, transação ou eventos.
  - **Repositories** (`Repositories/`): a camada que grava no banco e faz as leituras que não cruzam módulo. Não usa os services do próprio módulo: as regras são aplicadas antes de chamá-lo (o `ModuleBoundariesTest` cobra).
  - **Consultas** (`Queries/`, HEL-6, design em [.design/cqrs-read-side.md](.design/cqrs-read-side.md)): a classe de leitura do módulo que serve uma rota ou responde um contrato que só lê. Devolve value objects, listas tipadas ou um `LengthAwarePaginator` deles, nunca um model nem uma coleção do Eloquent, e nunca grava. Lê só as tabelas do próprio módulo; o que é de outro módulo ela pede ao dono, pelo contrato dele, por ids e uma vez para a página inteira, então o tamanho da página não muda o número de queries. Não usa os `Repositories` nem os `UseCases` do próprio módulo (o `ModuleBoundariesTest` cobra). O caso de uso de escrita devolve os dados do que gravou (`ProductRecord` e `StockRecord`, privados), e a consulta completa a resposta sem reler o registro. Existem no Catalog (`ProductQuery`, `ProductCatalogQuery`, `CatalogMetricsQuery`), no Inventory (`StockLevelsQuery`, `StockListQuery`, `StockMetricsQuery`), no Identity (`CustomerMetricsQuery`), no Ordering (`OrderMetricsQuery`) e no Backoffice (`DashboardQuery`); as outras leituras (pedidos, clientes, endereços, categorias, pagamento) ficam nos repositories até serem tocadas.
  - **Value objects** (`ValueObjects/`): conceitos com invariante própria, `final readonly`, que lançam `InvalidArgumentException` se construídos com um valor inválido. Hoje existem no Identity: `Email` e `Password`. Os Form Requests os usam pelas regras `EmailRule` e `PasswordRule`, que cada módulo com formulário de conta tem no seu `Http/Rules` (Identity e Customers, porque o `Http` de um módulo é privado) e que só traduzem o que o value object decide, e eles viram `string` só na gravação, no `toArray()` do `CreateUserDTO` e do `UpdateUserProfileDTO` (este deixa a senha de fora quando nenhuma nova é enviada).
  - **Listas tipadas** (também em `ValueObjects/`): as listas de domínio que atravessam camadas não são `array`. Cada uma é `final readonly`, implementa `IteratorAggregate` e `Countable`, é construída com parâmetro variádico tipado (um elemento de outro tipo lança `TypeError`) e calcula os próprios totais. No Catalog: `ProductIds` e `CategoryIds` (ids no formato canônico do UUID e sem repetição, senão `InvalidArgumentException`) e `CatalogProducts`/`CatalogProduct` (o que o contrato `ProductCatalog` devolve: id, nome, imagem, preço e o `ProductStatus`, com a `availability()` do produto) e `ProductCountsByStatus` (o que o `CatalogMetrics` devolve, com zero para o status sem produtos). O `ProductAvailability` é a regra "pode ser comprado", e `ProductView`, `ProductCategories`/`ProductCategory` e `ProductRecord` são o que a consulta do Catalog monta para as telas de produto. No Inventory: `StockQuantities` (quantidade por produto, 0 para produto sem linha de estoque), `ProductStocks`/`ProductStock` (o id da linha de estoque e a quantidade de cada produto, `null` para o produto sem linha) e `StockTotals`; `StockView` e `StockRecord` são o que a lista de estoque monta. No Ordering: `ProductQuantities` (quantidade por produto, em ordem de `product_id`), `OrderLines`/`OrderLine` (linhas do pedido novo e o total derivado), `ValidatedCart`/`ValidatedCartLine` (resultado da validação do carrinho, com `total_cents` e `is_valid` derivados), `CustomerIds`, `OrderCountsByCustomer` (responde zero para cliente sem pedidos) e `OrderCountsByPeriod` (a contagem por dia ou mês do `OrderMetrics`, zero para o período sem pedidos). Também no Ordering, `DeliveryAddress` (a cópia de um endereço que o pedido guarda) e `ShippingQuote` (preço e prazo de uma UF) são o vocabulário dos contratos `DeliveryAddressBook` e `ShippingQuoter`, `OrderForPayment` é o que o `PayableOrders` devolve e `OrderSummaries`/`OrderSummary` (id, status, total e data) são o histórico que o `CustomerOrderHistory` devolve. No Identity, `CustomerProfile` (a conta sem credenciais) é o que o `CustomerAccounts` devolve. O `CartDTO` é a lista tipada de `CartItemDTO`. O `array` só aparece onde o Laravel o exige: `whereIn`, `sync`, `createMany` e o `ValidatedCartResource`.
- **Fronteiras verificadas por teste**: o `ModuleBoundariesTest` (teste de arquitetura do Pest) é uma lista do que é permitido. Ele gera uma expectativa para cada pasta privada de cada módulo (o `Http` incluído), a partir das pastas que existem, e uma para cada classe de `DTOs`, `ValueObjects` e `Enums` fora do vocabulário do módulo dono, a partir dos arquivos que existem, e falha se outro módulo usar uma delas ou a classe da raiz de um módulo (o service provider). Também confere que `Contracts` só tem interfaces, que nenhuma assinatura de contrato usa model ou coleção do Eloquent e que nenhum repository usa os services do próprio módulo, além das regras de direção entre módulos. Para as consultas, confere que nenhuma assinatura pública declara model nem coleção do Eloquent e que nenhuma usa os repositories nem os casos de uso do próprio módulo. Não há lista de exceções.
- **Processamento assíncrono**: mudanças de status do pedido são feitas por consumidores e jobs executados pelo container `queue-worker`. Os eventos chegam a eles pelo **outbox transacional**: o caso de uso grava o evento na mesma transação da mudança, e o container `outbox-relay` o publica na fila (ver [Eventos, outbox, consumidores e filas](#eventos-outbox-consumidores-e-filas)).

---

## Estrutura de pastas

```
/
├── backend/                         Laravel 13 (API only)
│   ├── app/
│   │   ├── Modules/
│   │   │   ├── Catalog/             produtos e categorias (vitrine e cadastro); Queries/ (leituras que servem as rotas e os contratos); publica Contracts/ (ProductCatalog, CatalogMetrics) e define ProductOrderHistory; dono do ProductAvailability; CatalogServiceProvider
│   │   │   ├── Inventory/           estoque; Queries/ (StockLevels, StockMetrics e a lista de estoque); publica Contracts/ (StockInitializer, StockLevels, StockMetrics, StockReservation) e InventoryServiceProvider
│   │   │   ├── Ordering/            carrinho, checkout, pedidos, Customer (comprador, somente leitura) — núcleo do negócio; define Contracts/ (DeliveryAddressBook, ShippingQuoter), publica PayableOrders, CustomerOrderHistory e OrderMetrics (este por uma consulta), e guarda o vocabulário que eles trocam
│   │   │   ├── Payment/             lê o pedido do cliente pelo PayableOrders, cobra pela porta PaymentGateway (gateway fake), grava as tentativas e publica PaymentApproved
│   │   │   ├── Fulfillment/         tabela de frete por UF (ShippingRateTable), calendário de dias úteis, entrega fake: agenda o DeliverOrder e publica DeliveryScheduled e OrderDelivered; FulfillmentServiceProvider
│   │   │   ├── Identity/            as duas contas (User = equipe, CustomerAccount = comprador), papéis, logins (Sanctum, um guard por área), Email e Password (ValueObjects), EnsureUserIsAdmin; publica Contracts/ (CustomerAccounts, CustomerMetrics) e IdentityServiceProvider
│   │   │   ├── Customers/           "Minha conta", o caderno de endereços (`customer_addresses`) e a tela Clientes do admin (CustomerSummaryDTO), pelas contas do CustomerAccounts e pelo histórico do CustomerOrderHistory, com os próprios adaptadores HTTP de e-mail, senha, UF e conta; CustomersServiceProvider
│   │   │   ├── Backoffice/          dashboard do admin (DashboardQuery, que lê os quatro contratos de métricas: CatalogMetrics, StockMetrics, CustomerMetrics e OrderMetrics)
│   │   │   └── Shared/              infraestrutura comum: erros da API, ApiFormRequest, BaseRepository, Controller, X-Request-ID, o `throttle` que conta antes de decidir (Http/Middleware/AtomicThrottleRequests, Cache/AtomicRateLimiter) e o outbox transacional (Messaging/: DomainEvent, EventOutbox, EventTransport, EventSubscriptions, DeliverEvent, OutboxRelay; Console/: outbox:relay)
│   │   └── Providers/               AppServiceProvider (configurações globais, a ligação do EventOutbox, do EventTransport e das inscrições, e o AtomicRateLimiter no lugar do RateLimiter do framework)
│   ├── config/shop.php              configurações da loja (delay da entrega, paginação, tabela de frete por UF)
│   ├── database/
│   │   ├── factories/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── lang/pt_BR/validation.php    mensagens de validação em português
│   ├── routes/api.php
│   └── tests/                       testes Pest: Unit/ (services puros e arquitetura dos módulos) e Feature/ (HTTP, casos de uso, repositories)
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
│   └── postgres/init/               cria o banco de testes
├── load-tests/                      testes de carga com k6 (ver Testes de carga): o Compose do projeto `ecommerce-load`, os fluxos, os perfis, as provas dos scripts e a linha de base
├── storage-dumps/                   destino do `make db-dump`
├── docker-compose.yml
├── Makefile
├── .env.example                     variáveis do Docker Compose
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
3. sobe os containers e aguarda PostgreSQL e Redis ficarem saudáveis;
4. instala as dependências PHP (`composer install`) e gera a `APP_KEY`;
5. executa `migrate:fresh --seed` (**recria o banco de desenvolvimento**);
6. reinicia o worker da fila.

Depois acesse **http://localhost:8080**.

> ⚠️ Como o `make setup` executa `migrate:fresh`, rodá-lo novamente apaga os dados do banco de desenvolvimento e recria os dados de demonstração.

---

## Configuração do `.env`

Existem dois arquivos:

| Arquivo | Para que serve |
| --- | --- |
| `.env` (raiz) | Variáveis do Docker Compose: `APP_PORT`, credenciais do PostgreSQL, `DB_FORWARD_PORT`. |
| `backend/.env` | Configuração do Laravel: banco (`DB_*`), Redis, fila, sessão, Sanctum, locale, delay da entrega. |

Variáveis importantes do `backend/.env`:

| Variável | Valor padrão | Observação |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8080` | URL servida pelo Nginx |
| `DB_HOST` / `DB_DATABASE` | `db` / `ecommerce` | Devem bater com o `.env` da raiz |
| `QUEUE_CONNECTION` | `redis` | Fila consumida pelo `queue-worker` |
| `SESSION_DRIVER` | `redis` | Sessão (cookie HTTP-only) |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:8080,...` | Domínios que autenticam por cookie. **Se mudar `APP_PORT`, atualize aqui.** |
| `APP_FAKER_LOCALE` | `pt_BR` | Locale do Faker |
| `ORDER_DELIVERY_DELAY_SECONDS` | `10` | Delay do job de entrega simulada |
| `CACHE_LIMITER` | vazio | Armazenamento das contagens do limitador de requisições. Vazio usa o armazenamento padrão, e os limites das rotas valem. Só a pilha de carga define (`array`); ver [Testes de carga (k6)](#testes-de-carga-k6) |

---

## Comandos do Makefile

Execute `make` (ou `make help`) para listar todos os comandos.

### Ambiente

| Comando | Descrição |
| --- | --- |
| `make setup` | Sobe e prepara todo o projeto (recria o banco) |
| `make up` | Inicia todos os containers (`docker compose up -d`) |
| `make down` | Para os containers (`docker compose down`) |
| `make build` | Reconstrói as imagens (`docker compose build`) |
| `make rebuild` | Reconstrói as imagens e inicia os containers |
| `make ps` | Mostra o status dos containers |
| `make logs` | Logs principais (api, queue-worker, outbox-relay, nginx, frontend) |
| `make logs-api` | Logs da API |
| `make logs-worker` | Logs do worker de filas |
| `make logs-relay` | Logs do relay do outbox (`outbox-relay`) |

### Laravel

| Comando | Descrição |
| --- | --- |
| `make shell` | Abre um shell no container `api` |
| `make artisan CMD="..."` | Executa um comando Artisan. Ex.: `make artisan CMD="route:list"` |
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
| `make queue` | Executa `php artisan queue:work redis` em primeiro plano (útil para ver o processamento ao vivo) |
| `make queue-restart` | Reinicia os workers e o relay do outbox (**necessário após alterar código de jobs, consumidores ou do relay**) |
| `make queue-failed` | Lista os jobs que falharam |
| `make queue-retry` | Reprocessa todos os jobs falhos (ou um: `make queue-retry ID=<uuid>`) |

### Testes

| Comando | Descrição |
| --- | --- |
| `make test` | Executa todos os testes (Pest) |
| `make test-filter FILTER="..."` | Executa testes filtrados. Ex.: `make test-filter FILTER=CheckoutTest` |
| `make test-frontend` | Executa os testes do frontend (Vitest) no container `frontend` |

### Testes de carga

| Comando | Descrição |
| --- | --- |
| `make load-test TYPE=<perfil>` | Recria a pilha de carga (`ecommerce-load`, com banco e Redis próprios) e roda um perfil do k6: `smoke`, `load`, `stress`, `spike`, `breakpoint`, `soak`, `contention` ou `limits`. A pilha de dev não muda |
| `make load-down` | Remove a pilha de carga e os volumes dela |

### Frontend

| Comando | Descrição |
| --- | --- |
| `make npm-install` | Instala as dependências do frontend |
| `make frontend-shell` | Abre um shell no container `frontend` |

### Exemplos

```bash
make setup                          # primeira execução
make fresh                          # recriar o banco com dados de demonstração
make test                           # rodar os testes
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

**Ids:** a chave primária das 9 tabelas de negócio (`users`, `customers`, `categories`, `products`, `stocks`, `orders`, `order_items`, `payments` e `customer_addresses`) é um **UUID v7** gerado pela aplicação (`HasUuids`, sem default no banco), no tipo nativo `uuid` do PostgreSQL, e toda chave estrangeira para elas é `uuid`. Nenhum número sequencial sai do banco: a API, as URLs, os contratos e os eventos só levam UUIDs em texto, na forma canônica (minúsculas, com hífens). As tabelas do framework (`jobs`, `failed_jobs`, `sessions`, `cache`) não mudam. O `sessions.user_id` continua `bigint`, porque a sessão roda no Redis; se o driver passar a `database`, essa coluna precisa virar `uuid`.

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
| `order_items` | **snapshot** de `product_name` e `unit_price_cents`; `subtotal_cents bigint` (`unit_price_cents × quantity`), os dois com `CHECK >= 0`. Mudar o produto não altera pedidos antigos |
| `outbox_messages` | o **outbox transacional**: uma linha por evento, gravada na transação da mudança que ele anuncia. `id uuid` (v7 da aplicação, também o id da mensagem), `name varchar(100)` (`ordering.order_paid`), `version smallint`, `payload jsonb` (só escalares), `request_id varchar(128)` (nulo fora de uma requisição), `occurred_at` e `published_at` (nulo enquanto pendente). Índice parcial `outbox_messages_pending_index (occurred_at, id) WHERE published_at IS NULL` para o relay e índice em `published_at` para a retenção. Sem chave estrangeira: o payload leva os ids como dado, e apagar uma mensagem não toca em nenhum outro registro |

**Código do pedido:** toda tela mostra `#K7Q2M9XA` no lugar do id. O código tem 8 símbolos do alfabeto `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (sem `0`, `O`, `1`, `I` e `L`, cerca de 850 bilhões de combinações), é sorteado pelo `OrderCodeGenerator` (`random_int`), é único e nunca muda. Só a colocação do pedido o grava. Ele vale só para leitura: URLs, contratos e eventos continuam com o id, e nenhum contrato recebe o código como parâmetro. O banco recusa um código nulo, de outro tamanho, com símbolo fora do alfabeto ou repetido.

**Integridade:**

- `stocks` e `category_product` usam `cascade` (são dependentes do produto).
- `order_items.product_id` e `orders.customer_id` (→ `customers.id`) usam `restrict`: **não é possível excluir um produto que já está em pedidos** (a API responde `409` e sugere desativar o produto), nem um cliente que tem pedidos.
- `payments.order_id` também usa `restrict`: um pedido com tentativas de pagamento não pode ser apagado.
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

- As **factories** (`database/factories`) usam o Faker (`fake()`) com locale `pt_BR` para nomes de clientes, e-mails, nomes/descrições/preços de produtos e datas dos pedidos.
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

Trecho central de `backend/app/Modules/Ordering/UseCases/PlaceOrderUseCase.php`:

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

## Eventos, outbox, consumidores e filas

O ciclo de vida do pedido envolve três partes, que só conversam por eventos:

- **Pedidos** (Ordering): é a única parte que muda o status do pedido.
- **Pagamento** (Payment): cobra pelo gateway, grava cada tentativa e só anuncia que o pagamento foi aprovado.
- **Entrega** (Fulfillment): decide o frete e a data prevista, e anuncia que a data foi calculada e que a transportadora (fake) entregou.

Todo evento passa pelo **outbox transacional** (HEL-9, design em [.design/transactional-outbox.md](.design/transactional-outbox.md)):

1. O caso de uso grava o evento pelo `EventOutbox` **dentro da transação da mudança que ele anuncia**. O commit grava a mudança e a mensagem (uma linha de `outbox_messages`, pendente enquanto `published_at` é nulo) juntas, e um rollback desfaz as duas.
2. O **relay** (`php artisan outbox:relay`, no container `outbox-relay`) reserva as mensagens pendentes das mais antigas para as mais novas, em lotes de 100 (`SELECT ... FOR UPDATE SKIP LOCKED`, então dois relays nunca pegam a mesma), entrega cada uma ao `EventTransport` e só então grava `published_at`.
3. O transporte de hoje, o `QueueEventTransport`, põe na fila padrão (Redis) um job `DeliverEvent` **para cada consumidor inscrito** no nome do evento, com o envelope inteiro.
4. O `queue-worker` roda o `DeliverEvent`: devolve ao `Context` o `request_id` da requisição que gravou o evento, reconstrói o evento (`fromPayload`) e chama o `handle()` do consumidor, que delega ao caso de uso. Esse caso de uso pode gravar o próximo evento da cadeia, de volta ao passo 1.

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

══►  gravado no outbox na transação ─► outbox-relay ─► DeliverEvent na fila ─► consumidor no queue-worker
```

| Classe | Parte | Tipo | O que faz |
| --- | --- | --- | --- |
| `App\Modules\Ordering\Events\OrderPlaced` | Pedidos | Evento `ordering.order_placed` (v1) | Gravado na transação do checkout |
| `App\Modules\Ordering\Consumers\MarkOrderAsAwaitingPayment` | Pedidos | Consumidor | Move o pedido para `awaiting_payment` (`MarkOrderAsAwaitingPaymentUseCase`) |
| `App\Modules\Payment\Events\PaymentApproved` | Pagamento | Evento `payment.payment_approved` (v1) | Gravado pelo `PayOrderUseCase` na mesma transação da tentativa aprovada |
| `App\Modules\Ordering\Consumers\MarkOrderAsPaid` | Pedidos | Consumidor | Move para `payment_approved` e grava `OrderPaid` na mesma transação (`MarkOrderAsPaidUseCase`) |
| `App\Modules\Ordering\Events\OrderPaid` | Pedidos | Evento `ordering.order_paid` (v1) | Gravado só pela transição que de fato marcou o pedido como pago |
| `App\Modules\Fulfillment\Consumers\ScheduleOrderDelivery` | Entrega | Consumidor | Calcula a data prevista (`DeliveryCalendar`), registra no log, grava `DeliveryScheduled` numa transação só dele e, depois do commit, agenda o job de entrega (`ScheduleDeliveryUseCase`) |
| `App\Modules\Fulfillment\Events\DeliveryScheduled` | Entrega | Evento `fulfillment.delivery_scheduled` (v1) | Carrega o id do pedido e a data prevista |
| `App\Modules\Ordering\Consumers\RecordEstimatedDelivery` | Pedidos | Consumidor | Grava `estimated_delivery_on`, **só se estiver vazia**, sem tocar no status (`RecordEstimatedDeliveryUseCase`) |
| `App\Modules\Fulfillment\Jobs\DeliverOrder` | Entrega | Job (`ShouldQueue`) | Após `ORDER_DELIVERY_DELAY_SECONDS`, simula a entrega e grava `OrderDelivered` numa transação só dele (`DeliverOrderUseCase`) |
| `App\Modules\Fulfillment\Events\OrderDelivered` | Entrega | Evento `fulfillment.order_delivered` (v1) | Anuncia que a transportadora (fake) entregou o pedido |
| `App\Modules\Ordering\Consumers\MarkOrderAsDelivered` | Pedidos | Consumidor | Move para `delivered` (`MarkOrderAsDeliveredUseCase`) |

A camada de publicar e consumir fica no `Shared`:

| Classe (`App\Modules\Shared\...`) | O que faz |
| --- | --- |
| `Messaging\DomainEvent` | O contrato de todo evento de módulo: `eventName()`, `eventVersion()`, `toPayload()` e `fromPayload()` |
| `Messaging\EventOutbox` | Onde o caso de uso grava o evento (`record()`). Implementado pelo `Repositories\OutboxMessageRepository`, que recusa a gravação sem transação aberta (`LogicException`) e um valor do payload que não seja escalar nem nulo (`InvalidArgumentException`) |
| `Messaging\EventEnvelope` | A mensagem como ela viaja: `id`, `name`, `version`, `payload`, `occurredAt` e `requestId`, as mesmas chaves das colunas de `outbox_messages` |
| `Messaging\EventTransport` | A porta por onde o relay publica: retornar é aceitar, lançar exceção é recusar. Hoje a única implementação é o `Messaging\QueueEventTransport`; a HEL-13 acrescenta outra atrás de uma feature flag |
| `Messaging\EventSubscriptions` | As inscrições, por nome de evento. Recusa (`LogicException`) um consumidor que não implementa `EventConsumer` ou cujo `handle()` não recebe a classe inscrita, e duas classes de evento com o mesmo nome |
| `Messaging\EventConsumer` | A interface dos consumidores, que ficam em `<Módulo>/Consumers` |
| `Messaging\DeliverEvent` | O job de uma entrega (mensagem, consumidor), com `$tries = 3` |
| `Messaging\OutboxRelay`, `Console\RelayOutboxCommand` | O ciclo do relay e o processo `outbox:relay [--once]` |
| `Models\OutboxMessage` | Uma linha de `outbox_messages` |

- **O evento é gravado na transação da mudança, e gravar fora de uma é erro.** Antes, cada evento saía depois do commit, numa chamada separada à fila, e uma falha dela travava o pedido para sempre: em `placed` (sem o `OrderPlaced`), em `awaiting_payment` com o cliente já cobrado (sem o `PaymentApproved`) ou, no caso do `OrderPaid`, com a transição já gravada e o retry sem conseguir repeti-la. Agora a mensagem fica pendente até o transporte aceitar, e uma falha da fila só atrasa o pedido. No pagamento, a tentativa aprovada e o `PaymentApproved` entram juntos. Na marcação de pago, a transição e o `OrderPaid` entram juntos, e uma transição que não acontece não grava nada. O agendamento da entrega e a entrega, que não têm estado próprio, abrem uma transação só para a mensagem, e o `DeliverOrder` entra na fila depois do commit da `DeliveryScheduled`.
- **Ninguém dispara evento pelo dispatcher do Laravel.** Nenhum evento de módulo tem listener do Laravel (o `bootstrap/app.php` não registra eventos), e o `DomainEventsTest` exige que toda classe de `Events` implemente `DomainEvent` e recusa, em `App\Modules`, o despacho de eventos do Laravel: o trait `Dispatchable` de eventos, a interface de despacho depois do commit, a facade `Event` e o helper `event()`.
- **O formato publicado não depende da classe PHP.** O nome é `<módulo>.<evento em snake_case>`, a versão é um inteiro a partir de 1 e o payload tem só escalares, em chaves snake_case: `order_id` em todos; `delivery_business_days` (inteiro) no `OrderPaid`; `estimated_delivery_on` (o dia de `America/Sao_Paulo`, em `Y-m-d`) no `DeliveryScheduled`. Mudar um payload é subir a versão. O `DeliverEvent` recusa uma versão que a classe inscrita não declara (`UnexpectedValueException`, com o nome e a versão) sem chamar o consumidor, e a entrega vai para o `failed_jobs`.
- **Cada módulo inscreve os próprios consumidores** no `boot()` do seu service provider, e o produtor nunca lista quem o consome: o `OrderingServiceProvider` inscreve os 4 do Ordering e o `FulfillmentServiceProvider`, o `ScheduleOrderDelivery`.
- **Uma entrega por consumidor.** Se um consumidor falha, só a entrega dele é repetida (até 3 tentativas) e depois vai para o `failed_jobs`; os outros consumidores da mesma mensagem rodam uma vez. O `make queue-failed` lista a entrega como `<nome do evento> -> <consumidor>`. Como a entrega carrega o envelope inteiro, um `make queue-retry` funciona mesmo depois que a mensagem foi apagada.
- **A entrega acontece ao menos uma vez, e os consumidores são idempotentes.** Se o relay cair entre o transporte aceitar e a mensagem ser marcada, ela sai de novo. Não há inbox: as guardas dos consumidores bastam (a transição condicional de `orders.status` e a primeira gravação de `orders.estimated_delivery_on`). A ordem de entrega é só a melhor possível, e nenhum consumidor depende dela.
- **O relay**: sem mensagem pendente, espera 500 ms; na primeira falha do transporte, para o ciclo, deixa aquela mensagem e as seguintes pendentes, registra um `warning` no log (com o `message_id`, o `name` e o erro), imprime uma linha de aviso na própria saída (`make logs-relay`) e tenta de novo depois de 5 s. Com `--once`, roda um ciclo e sai com `0`, ou `1` se o transporte recusou uma mensagem. Ele termina com `0`, depois do ciclo em andamento, quando o `php artisan queue:restart` roda (o mesmo sinal no cache que os workers usam), e o container o sobe de novo. Com o Redis fora, o cache também está, e o relay segue tentando publicar até ele voltar.
- **Retenção**: as mensagens publicadas ficam 7 dias e são apagadas pelo relay ao iniciar e no máximo uma vez por hora. As pendentes nunca são apagadas. A tabela não é histórico e não serve para replay.
- **O `request_id` atravessa o relay**: o `EventOutbox` grava o `request_id` do `Context` na mensagem, e o `DeliverEvent` o devolve ao `Context` antes do consumidor (ou o limpa, se a mensagem não tem um). Assim os logs do consumidor e os eventos que ele grava levam o `request_id` da requisição original, por toda a cadeia.
- **A entrega escuta `OrderPaid`, não `PaymentApproved`.** Se escutasse direto o pagamento, o job de entrega correria em paralelo com a marcação de pago. Num retry ou atraso da fila, ele poderia rodar antes e o pedido ficaria preso em `payment_approved`.
- Pagamento e entrega **nunca** alteram o pedido: quem escreve em `orders` são os consumidores da parte de pedidos (o status e a data prevista).
- **Os eventos e o job carregam dados, nunca o model**: `OrderPlaced`, `PaymentApproved`, `OrderDelivered` e `DeliverOrder` levam o `orderId`; o `OrderPaid` leva o `orderId` e os `deliveryBusinessDays` prometidos no checkout; o `DeliveryScheduled` leva o `orderId` e a data prevista. Os casos de uso do Ordering carregam o pedido pelo id (`OrderRepository::findOrFail`), e o Fulfillment agenda a entrega sem ler o pedido. Um teste de reflexão (`OrderEventPayloadTest`) falha se um deles voltar a ter um model.
- **A data prevista** conta os dias úteis prometidos no pedido (`delivery_business_days`, que chegam ao Fulfillment pelo `OrderPaid`) a partir do momento em que o Fulfillment trata o `OrderPaid`, em segunda a sexta e no fuso `America/Sao_Paulo` (uma compra às 23h30 de quarta, que já é quinta em UTC, conta a partir de quarta; sábado e domingo contam a partir de segunda). Feriados não são considerados. A primeira gravação vale: um retry ou um `DeliveryScheduled` repetido não muda a data, e um pedido que já foi entregue ainda recebe a sua data.
- O `DeliverOrder` continua entregando depois do delay (`ORDER_DELIVERY_DELAY_SECONDS`), mesmo antes da data prevista: a entrega é simulada. Ele é um comando interno do Fulfillment, e não um evento, então continua um job do Laravel, fora do outbox.
- Consumidores e job são adaptadores finos: só delegam para o caso de uso correspondente.
- Todas as transições usam `OrderRepository::transitionStatus($order, $from, $to)`, um `UPDATE ... WHERE status = :from`: se uma entrega for executada duas vezes ou fora de ordem, ela simplesmente não faz nada (**idempotência**). Por isso um `PaymentApproved` duplicado gera um único `OrderPaid`, e um retry do `DeliverOrder` (que anuncia a entrega de novo) é ignorado.
- O endpoint de pagamento responde **`202 Accepted`** na aprovação, com a tentativa de pagamento, pois a mudança de status acontece de forma assíncrona. O frontend faz *polling* da página do pedido para mostrar a timeline sendo atualizada.
- O administrador **não altera o status manualmente**: ele é controlado exclusivamente pelos eventos e jobs.

Acompanhando a fila e o outbox:

```bash
make logs-worker     # logs do container queue-worker
make logs-relay      # logs do container outbox-relay
make queue           # worker extra em primeiro plano
make queue-failed    # entregas e jobs que falharam
make queue-retry     # reprocessar falhas
```

> Depois de alterar código de consumidores, jobs ou do relay, rode `make queue-restart` — o worker e o relay mantêm o código carregado em memória.

**Deploy que muda o formato de um evento ou job** (como a HEL-7, que trocou o model `Order` pelo `orderId`, a HEL-11, que trocou o `orderId` de inteiro para UUID em texto, e a HEL-9, que trocou os listeners pelos consumidores e as entregas `DeliverEvent`): o `queue-restart` só troca o código do worker e do relay. Os jobs que já estão na fila, inclusive os `DeliverOrder` com delay, continuam no formato antigo e falham no código novo (na HEL-7, com `Typed property ...::$orderId must not be accessed before initialization`; na HEL-11, com um erro de tipo no `orderId`; na HEL-9, porque a classe do listener mudou de lugar); o retry repete o erro e o pedido fica parado. Antes de subir o código, esvazie a fila e o outbox com o código antigo:

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

O `down` faz a API responder `503`, então nenhum pedido novo grava mensagem. O relay continua publicando as pendentes durante a manutenção, e o worker do container não processa jobs em manutenção, por isso o segundo comando usa `--force` e para sozinho depois de 30 segundos (mais que o `ORDER_DELIVERY_DELAY_SECONDS` padrão). Repita-o até o `queue:monitor` mostrar `[0]` (no Redis, essa contagem inclui os jobs com delay e os reservados) e, no `psql` do `make db-shell`, `select count(*) from outbox_messages where published_at is null;` responder `0`. Depois suba o código, rode `make queue-restart` e `make artisan CMD="up"`. Mudar o payload de um evento com mensagens em trânsito pede uma versão nova e a leitura das duas versões, que ainda não existe (só a versão atual de cada evento é aceita). No ambiente local, `make fresh` e `make queue-restart` bastam.

---

## Regra de disponibilidade do produto

Um produto só pode ser comprado quando **`status = active` E estoque > 0**. A única fonte da regra no backend é o `App\Modules\Catalog\ValueObjects\ProductAvailability` (HEL-6; antes ela morava no `PurchaseAvailabilityService` do Ordering, o que obrigava o Catalog e o Inventory a depender de um módulo que depende deles):

- `isAvailable()`: combina o status do catálogo com as unidades do estoque.
- `availableQuantity()`: as unidades do estoque, também para um produto inativo (`0` quando o produto não tem linha de estoque).

O value object recebe dados, não models. O `CatalogProduct` (o que o contrato `ProductCatalog` devolve) responde `availability($unitsInStock)`, e cada chamador diz de onde vêm as unidades: o carrinho lê as quantidades pelo `StockLevels`, o checkout pelas linhas bloqueadas (`FOR UPDATE`) do `StockReservation`, a vitrine e o cadastro de produto do admin pelo `ProductStocks` que o `StockLevels` devolve, e a lista de estoque do admin pela própria linha. Um produto sem linha de estoque tem 0 unidades e não pode ser comprado. O catálogo público, o detalhe do produto, a validação do carrinho, o checkout e a tela de estoque do admin leem todos a regra dali, e ela não é copiada em nenhum outro lugar. O Ordering fica só com as mensagens: o `PurchaseAvailabilityService` (nome mantido na HEL-6) devolve `Produto indisponível.` ou `Estoque insuficiente. Disponível: N.` sobre um `ProductAvailability`.

- Na listagem pública (`/products`) todos os produtos aparecem; os indisponíveis ficam esmaecidos, não são clicáveis, não podem ser adicionados ao carrinho e mostram "Sem estoque" ou "Indisponível".
- `GET /api/products/{product}` responde `404 — Produto indisponível.` para produtos inativos ou sem estoque.
- Quando o estoque chega a zero, o produto deixa de estar disponível automaticamente; quando volta a ser maior que zero, só fica disponível se estiver ativo.
- O frontend apenas reflete o campo `is_available` calculado pela API; checkout e validação do carrinho sempre revalidam no backend.

---

## API REST

Todas as rotas ficam em `backend/routes/api.php` com prefixo `/api`. **Todo id de registro** (`id` e qualquer `*_id`, no caminho, no corpo e na resposta) é um UUID v7 em texto, na forma canônica (minúsculas, com hífens); o pedido também traz o `code` de 8 símbolos. Um id malformado no caminho (`/api/products/1`, um UUID em maiúsculas) responde `404`, sem nenhuma query, e no corpo responde `422` no campo. Erros seguem sempre o formato (montado por `App\Modules\Shared\Http\Responses\ApiErrorResponse`):

```json
{
  "code": "INSUFFICIENT_STOCK",
  "message": "Estoque insuficiente para um ou mais produtos.",
  "errors": { "items.12": ["Estoque insuficiente. Disponível: 2."] },
  "request_id": "9d3c6f0e-6a4f-4c43-9a43-1f0b5e1f2a7b"
}
```

- `code`: identificador estável do erro (enum `App\Modules\Shared\Enums\ApiErrorCode`). O frontend deve decidir pelo `code`, nunca pelo texto de `message`. Valores: `VALIDATION_FAILED`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `CSRF_TOKEN_MISMATCH`, `TOO_MANY_REQUESTS`, `BUSINESS_RULE_VIOLATION`, `INSUFFICIENT_STOCK`, `PAYMENT_DECLINED` (402, pagamento recusado pelo gateway), `HTTP_ERROR`, `SERVER_ERROR`.
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
| GET | `/api/products?category=&sort=&page=` | público | Listagem paginada; `sort`: `name`, `price_asc`, `price_desc` |
| GET | `/api/products/{product}` | público | Detalhe (somente disponíveis) |
| GET | `/api/categories` | público | Categorias |
| GET | `/api/shipping/quote?state=UF` | público | Frete e prazo de uma UF: `data: { state, price_cents, delivery_business_days }`. A UF é normalizada (`sp` → `SP`); `422` se ausente ou desconhecida; `throttle:60,1,shipping-quote` |
| POST | `/api/cart/validate` | público | Revalida o carrinho |
| POST | `/api/orders` | cliente | Checkout: `items` e `address_id` (um endereço do próprio cliente). `201` com `code`, `items_total_cents`, `shipping_cents`, `total_cents` e `delivery`; `422` em `address_id` se faltar (`Escolha um endereço de entrega.`) ou não for do cliente (`Endereço de entrega não encontrado.`); `throttle:20,1,checkout` |
| GET | `/api/orders` | cliente | Pedidos do cliente |
| GET | `/api/orders/{order}` | dono | Detalhe do pedido (com o bloco `delivery`): `200`, `404` (inclusive o pedido de outro cliente) |
| POST | `/api/orders/{order}/payment` | dono | Paga com `card_token` (gateway fake): `202` aprovado, com a tentativa em `data: { id, order_id, status, amount_cents }`; `402 PAYMENT_DECLINED` recusado; `404` pedido inexistente ou de outro cliente; `409` se não pode ser pago; `422` do corpo, validado antes de buscar o pedido; `throttle:20,1,payment` |
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

**Limites de requisição**: cada limite é literal na rota, em `routes/api.php`, e cada rota tem o seu contador, nomeado no terceiro parâmetro do `throttle`: `throttle:10,1,registration` no cadastro, `throttle:10,1,customer-login` e `throttle:10,1,staff-login` nos dois logins, `throttle:20,1,checkout` e `throttle:20,1,payment` no checkout e no pagamento, e `throttle:60,1,cart-validation` e `throttle:60,1,shipping-quote` na validação do carrinho e na cotação de frete. Acima do limite, a resposta é `429` com `TOO_MANY_REQUESTS`. A requisição é contada antes de ser conferida: o `throttle` é o `AtomicThrottleRequests`, que soma 1 ao contador e decide pela contagem que recebeu de volta, dada por um só comando do Redis (`AtomicRateLimiter`). Numa rajada de requisições simultâneas passa exatamente o limite. Uma requisição recusada também conta, sem esticar a janela, que termina um minuto depois do primeiro acerto. A chave do contador é o nome da rota mais o cliente: o cliente autenticado (pelo id) ou, sem sessão, o **IP da conexão**, o `REMOTE_ADDR` que o Nginx entrega ao PHP-FPM. Cada rota conta só as próprias requisições: 60 cotações de frete no mesmo minuto deixam a 61ª em `429`, e o login do mesmo IP continua com as 10 tentativas. O Laravel não confia em nenhum proxy, então um `X-Forwarded-For` enviado pelo cliente (e também `X-Forwarded-Host`, `-Proto`, `-Port` e `-Prefix`) é ignorado e não troca a chave do limite (ver [Containers](#containers)). Uma rota nova com `throttle` nomeia um contador que nenhuma outra usa: sem o nome, ela somaria no contador que o Laravel monta só com o cliente. O `RateLimitTest` cobra (ver [Escopo e decisões](#escopo-e-decisões)).

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
make test-frontend                     # testes do frontend (Vitest)
```

O Vitest cobre a conversão de dinheiro (`utils/money`), o carrinho, o pagamento com cartão de teste (`composables/usePayment`), o caderno de endereços (`composables/useAddressBook`), o checkout com endereço e frete (`composables/useCheckout`), o texto de entrega, a data só-dia, o CEP, as UFs e o menu da conta (`utils/delivery`, `utils/date`, `utils/postalCode`, `utils/states`, `utils/accountMenu`), os erros tratados globalmente (`services/api`: `401` por área, `403`, `419`, `429` e `5xx`; os demais ficam com cada página), a decisão do guard do router (inclusive a rota do caderno) e o menu do admin (`router/guards`, `utils/adminMenu`) e as permissões por papel (`stores/staff`, `stores/auth`). A lógica é testada sem DOM; o arranjo visual das telas é conferido no navegador. O CI roda só o backend; `make test-frontend` roda localmente.

Os testes usam um banco PostgreSQL separado (`ecommerce_testing`, criado automaticamente pelo container `db`), porque recursos como `lockForUpdate()` e `to_char()` são específicos do PostgreSQL. A fila roda em modo `sync` nos testes, e nenhum relay roda sozinho: um evento gravado fica pendente até o teste rodar `outbox:relay --once` (`relayOnce()` e `relayUntilIdle()` em `tests/Pest.php`). Com a fila `sync`, os consumidores rodam dentro do ciclo do relay. O cache é `array` nos testes; só o `RateLimitBurstTest` conta no Redis do container `redis`, com contadores próprios que expiram em um minuto, e dispara as requisições de processos filhos (`pcntl_fork`, presente na imagem PHP do projeto).

### Em que nível testar

O nível de um teste depende do formato do código, não do nome da camada. Código que **decide** muda um resultado: uma tabela de casos, uma validação, uma guarda, uma transição de status. Código de **instrumentação** só repassa argumentos ou converte um formato em outro, sem condição própria.

| Código | Provas exigidas | Cobertura esperada |
| --- | --- | --- |
| Decide e é alcançado pelo HTTP (services, use cases, adaptadores) | uma na borda (teste de feature) **e** uma na própria camada | na borda: o contrato de cada status; na camada: um caso afirmado por linha da tabela de decisão |
| Decide e é alcançado só por um comando ou pela fila (o relay do outbox, o `DeliverEvent`, o transporte de fila, as inscrições) | uma na borda: o comando pelo `Artisan`, a entrega pela fila `sync` ou `database`, a inscrição pelo service provider no boot | um caso afirmado por linha da tabela de decisão, sem teste separado da classe interna |
| Decide no frontend (composables, stores, utils) | uma na própria camada, com Vitest e os services simulados | um caso por resposta tratada e por estado relevante |
| Garantia do banco (índice único, `CHECK`) | uma direto no banco | um caso por restrição, aceito e recusado |
| Regra de fronteira entre módulos | uma expectativa de arquitetura por namespace, gerada das pastas que existem, vista falhando com uma violação de propósito | toda regra nova |
| Instrumentação (controllers, Form Requests, service providers, renderização de exceções) | nenhuma própria | coberta pelas provas de borda |

Um teste prova a camada em que ele **afirma**, não as camadas por onde ele passa. Um teste de feature que atravessa uma tabela de decisão exercita um caminho dela e não falha quando outra linha está errada. Por isso a tabela também é provada na própria camada, como já fazem `Unit/Services/PaymentServiceTest` e `Feature/UseCases/*`.

### Integração contínua (GitHub Actions)

O workflow [`.github/workflows/ci.yml`](.github/workflows/ci.yml) roda em todo pull request e em todo push na `main`. Ele usa os **mesmos serviços do Docker Compose e os mesmos alvos do `Makefile`** do ambiente local, então o CI testa na mesma imagem PHP e na mesma versão do PostgreSQL que o `make test`:

1. cria os `.env` a partir dos exemplos (`make env-files`);
2. constrói a imagem PHP e sobe `db`, `redis` e `api`;
3. instala as dependências do Composer (com cache por `composer.lock`) e gera a `APP_KEY`;
4. verifica o estilo com o Pint (`pint --test`);
5. roda toda a suíte (`make test`), inclusive os testes de arquitetura que verificam as fronteiras entre módulos.

Se algum passo falhar, os logs dos containers `db` e `api` aparecem no próprio run. Um push novo na mesma branch cancela o run anterior que ainda estiver em andamento.

Um segundo job, `load-smoke`, roda em paralelo o `make load-test TYPE=smoke` na pilha de carga, com os limites de requisição reais. Ele confere só os códigos e os corpos das respostas, sem limite de latência, e falha quando uma rota, um status ou um payload muda e os scripts do k6 não acompanham. Na falha, imprime os logs da pilha de carga, e no fim sempre roda `make load-down`. Ver [Testes de carga (k6)](#testes-de-carga-k6).

O frontend ainda não é verificado no CI.

| Arquivo | Cobertura |
| --- | --- |
| `Auth/AuthTest` | cadastro em `customers` (sem `users`, ignorando `role`), e-mail igual ao da equipe, login só do cliente com as 4 chaves, credenciais da equipe recusadas, logout, `429` do cadastro e dos dois logins, também com um `X-Forwarded-For` forjado diferente a cada tentativa |
| `Auth/StaffAuthTest` | login da equipe (`admin` e `support`, sessão lida de novo na próxima requisição), credenciais de cliente e senha errada recusadas, logout, `CHECK` do `role` direto no banco |
| `AuthorizationTest` | `401` a visitante e a sessão de cliente em todas as 27 rotas `/api/admin/*` (percorrendo a tabela de rotas), `401` a sessão de equipe nas 11 rotas da loja (inclui as 4 do caderno), pedido de outro cliente (`404`, como um id desconhecido), nenhuma rota com `auth:sanctum` |
| `RateLimitTest` | as 7 rotas com `throttle` (percorrendo a tabela de rotas, que precisa ser igual à lista do teste), cada uma com um contador nomeado que nenhuma outra repete; esgotar uma rota deixa só ela em `429`, e as outras continuam abertas ao mesmo visitante (pelo IP, nas 5 rotas públicas) e ao mesmo cliente (pelo id, nas 7). Visto falhando antes da correção e com um nome de contador apagado ou repetido de propósito |
| `RateLimitBurstTest` | 30 requisições soltas no mesmo instante, de 30 processos filhos, contra um `throttle:10,1` contado no Redis: passam exatamente 10, e 20 recebem `429`; um contador deixado sem validade ganha a da janela no acerto seguinte. Visto falhando antes da correção: com o `throttle` do framework passaram as 30, em 5 de 5 rodadas; sem o `AtomicRateLimiter`, o contador continua sem validade |
| `ProductCatalogTest` | listagem com inativos, filtro, ordenações, paginação, detalhe indisponível, a mesma contagem de queries com 1 e com 12 produtos, a única leitura de `stocks` (um `where in` pelos ids da página, sem `join`) e o produto sem linha de estoque (`stock` nulo, indisponível) |
| `ProductResponseShapeTest` | as 7 rotas que devolvem produto com as chaves de hoje, na ordem de hoje; o cadastro de admin com 15 por página, do mais novo ao mais antigo; o produto criado com o estoque e as categorias do corpo |
| `Queries/*` | as consultas contra o banco: o filtro, as ordenações e as páginas da vitrine e do admin, o produto completado sem ler o registro de novo, o `ProductCatalog`, o `StockLevels` (a linha de cada produto numa query só, sem `for update`), a lista de estoque (inclusive a linha cujo produto sumiu) e as métricas do dashboard (produtos por status, estoque, só as contas de cliente, pedidos por dia e por mês) |
| `UseCases/Stock/AdjustStockUseCaseTest` | o ajuste devolve o `StockRecord` com o que gravou, não o model |
| `CartValidationTest` | preços recalculados, problemas por item |
| `CheckoutTest` | pedido + decremento + snapshot, total = itens + frete, rollback com `409`, cenário 5/4/3, uso de `FOR UPDATE`, `CHECK` no banco |
| `CheckoutShippingTest` | pedido com a cópia do endereço e o frete da UF, colunas gravadas, valor cobrado com frete, `422` sem endereço / com endereço de outro cliente, frete e total do cliente ignorados, cópia intacta depois de editar ou excluir o endereço, `429`, `404` de pedido desconhecido |
| `UseCases/Order/PlaceOrderUseCaseTest` | o caso de uso com o orçamento e o endereço simulados; endereço desconhecido recusado antes de tocar no estoque |
| `Models/OrderDeliveryTest` | `CHECK`/`NOT NULL` da cópia do endereço, do frete, do prazo e do total direto no banco |
| `OrderStatusFlowTest` | as 5 inscrições de consumidores, `OrderPlaced` entregue pela fila (`DeliverEvent`), transições, entrega agendada só após `OrderPaid`, job com delay, `DeliveryScheduled` com a data prevista, log sem o endereço, registro da data (primeira vale, status inalterado, pedido já entregue), idempotência, ciclo completo pelo relay com o `request_id` do pagamento em todas as mensagens seguintes |
| `Outbox/EventOutboxTest` | a forma de `outbox_messages` no catálogo (colunas, índices, nenhuma chave estrangeira), a gravação dentro da transação (uma mensagem pendente, id v7), o rollback, a recusa fora de transação, os valores aceitos e recusados no payload (um `resource` também é recusado) e o `request_id` do `Context` e da requisição |
| `Outbox/EventDeliveryTest` | uma entrega por consumidor com o envelope inteiro e um nome sem consumidor, pelo relay; o evento reconstruído, o `request_id` restaurado ou limpo, a versão recusada, a falha isolada com 3 tentativas, o `failed_jobs` e o que o `queue:failed` lista (o evento e o consumidor), pelo `queue:work` com a conexão `database`; as recusas da inscrição, pelo `boot()` de um service provider; a entrega depois da limpeza e nenhum listener do Laravel nos eventos |
| `Outbox/OutboxRelayTest` | o relay pelo comando: ordem, marcação, o envelope montado de cada coluna da linha, parada na primeira falha com o `warning`, saídas `0` e `1`, `FOR UPDATE SKIP LOCKED` com a reserva, a publicação e a marca numa transação só, a mensagem entregue de novo quando o relay para antes do commit, esperas de 500 ms e 5 s, retenção de 7 dias e limpeza por hora, sinal de reinício e o cache fora do ar |
| `Outbox/OrderEventsOutboxTest` | a gravação desfeita junto com a mudança (pagamento `500` sem aprovação nem mensagem, pedido ainda em `awaiting_payment`, nenhum `DeliverOrder`) e as janelas 1 e 2 com o transporte fora e de volta |
| `Unit/Architecture/DomainEventsTest` | toda classe de `Events` implementa `DomainEvent`, e nenhum módulo usa o `Dispatchable` de eventos, a interface de despacho depois do commit, a facade `Event` ou o helper `event()`. Vistos falhando com uma violação de propósito por regra |
| `Unit/Architecture/OrderEventPayloadTest` | nenhum model nos 5 eventos e no `DeliverOrder`; o nome, a versão e o payload de cada evento (chaves exatas, o inteiro do `OrderPaid`, o dia de São Paulo do `DeliveryScheduled`) e a volta pelo `fromPayload` |
| `DeliveryEstimateTest` | a data prevista chega à API depois do pagamento aprovado; admin e loja veem o mesmo bloco `delivery` |
| `Unit/Services/DeliveryCalendarTest` | dias úteis em horário de São Paulo: meio de semana, sexta, sábado, 23h30 de Brasília e 10 dias |
| `Account/CustomerAddressTest` | caderno: listagem, cadastro, normalização da UF e do complemento, CEP, campos obrigatórios e tamanhos, limite de 10 (`409`), edição, exclusão, endereço de outro cliente (`404`), `404`, `401` |
| `Models/CustomerAddressTest` | `customer_addresses`: apagada com a conta, FK e `CHECK` de CEP e UF direto no banco |
| `UseCases/Customer/CustomerAddressUseCasesTest` | o 10º endereço é aceito e o 11º recusado, na própria camada |
| `Repositories/CustomerAddressRepositoryTest` | o `DeliveryAddressBook`: só o endereço do próprio cliente vira `DeliveryAddress`, e só nele a edição e a exclusão alteram algo |
| `Account/CustomerRecordRoutesTest` | as 4 rotas do cliente com registro (pedido, pagamento e endereço): o dono recebe `200`/`202`/`204`, o registro de outro cliente responde como um id desconhecido (mesmo corpo, nada alterado, nenhuma cobrança), `404` antes do status, `404` do pagamento desconhecido sem cobrança, `422` antes do dono, `DELETE` do endereço sem leitura prévia e a contagem de queries do dono |
| `Admin/StaffRecordRoutesTest` | as 15 rotas da equipe e da vitrine com registro: o id chega como `string`, id desconhecido é `404` sem mudar nenhuma das 9 tabelas, corpo inválido é `422` para id existente e desconhecido, e a contagem de queries (estoque com 3 queries e um só `FOR UPDATE`, `DELETE` de produto, categoria e membro sem leitura prévia, cliente lido uma vez no `PUT`, as demais leituras e edições com a contagem de antes) |
| `Unit/Services/ShippingRateTableTest` e `ShippingQuoteTest` | as 27 linhas da tabela de frete, a rota pública, `422`, `429` e o contrato `ShippingQuoter` igual à rota |
| `PaymentTest` | `202` + tentativa gravada + mensagem `payment.payment_approved` no outbox, `402` para cada cartão recusado, nova tentativa após recusa, `422` do `card_token`, `409` fora de `awaiting_payment` ou com aprovado, corrida perdida, valor sempre do pedido, pedido de outro cliente (`404`, antes do status), garantias do banco, log sem o token, `429` |
| `Unit/Gateways/FakePaymentGatewayTest` | resultado de cada cartão de teste, valor ignorado, id de transação novo |
| `Admin/*` | categorias (slug), produtos, estoque (as chaves de cada linha, a mesma contagem de queries com 1 e com 20 linhas e a única leitura de `products`), clientes (`CustomerTest`), equipe (`StaffTest`), papéis (`StaffRoleTest`: todo `DELETE` do admin só para `admin`, percorrendo a tabela de rotas; `404` dos ids desconhecidos), pedidos e dashboard (inclusive a loja vazia) |
| `Account/AccountTest` | resumo e edição de perfil/senha |
| `Models/CustomerTest` | comprador lido a partir da conta, projeção somente leitura |
| `Models/CustomerAccountTest` | `customers`: FK do pedido (`restrict`, id existente), `CHECK` do e-mail e unicidade direto no banco |
| `Middleware/EnsureUserIsAdminTest` | o middleware `admin` deixa passar só o papel `admin` |
| `UseCases/User/StaffUseCasesTest` | regras do admin sobre si mesmo: não muda o próprio papel, não remove a própria conta; exclusão e edição por id, com `ModelNotFoundException` para id desconhecido |
| `Unit/Architecture/ModuleBoundariesTest` | fronteiras entre módulos: pastas privadas (o `Http` incluído) e vocabulário público calculado das assinaturas de `Contracts` e `Events` (com classes de teste em `Fixtures/` para o percurso), estoque só pelos contratos, Identity sem pedidos, Ordering, Payment e Fulfillment sem o `User` da equipe, Payment e Fulfillment sem `OrderRepository` e sem se conhecerem, Payment cobrando só pela porta `PaymentGateway`, Ordering sem o Customers e só com os eventos do Fulfillment (uma expectativa por namespace), Fulfillment sem o Customers, contratos do Ordering só interfaces, Shared sem módulos de negócio, repositories sem os services do próprio módulo, e as consultas (sem model nem coleção do Eloquent nas assinaturas, sem repositories nem casos de uso do próprio módulo, os 6 contratos de leitura resolvidos para as consultas dos donos, sem lista de exceções). As 9 travessias que eram exceções foram vistas falhando uma a uma com uma violação de propósito |
| `Auth/EmailAndPasswordTest` | e-mail como conta única (caixa, `CHECK` do banco em `users` e `customers`, login, perfil, admin), política de senha só ao escolher uma |
| `Unit/ValueObjects/*` | `ProductAvailability` (os 4 casos de status e unidades), `Email` (normalização e recusas), `Password` (política, sem vazar o texto em dumps, serialização e traces) e as listas tipadas (`TypedListsTest`: agrupamento, totais derivados, recusa de tipo e de id inválido ou repetido) |
| `Database/UuidKeysTest` | as 9 chaves primárias e as 8 estrangeiras são `uuid`, o id v7 vem da aplicação (a coluna não tem default), as regras de exclusão e as constraints continuam |
| `UuidIdsTest` | todo `id` e `*_id` das respostas é um UUID em texto, id malformado no corpo (`422`, sem query em `products`, `stocks` e `categories`) e no caminho (`404` sem query, percorrendo a tabela de rotas) |
| `Models/OrderCodeTest` e `Unit/Services/OrderCodeGeneratorTest` | o código do pedido direto no banco (nulo, tamanho, alfabeto, único) e o sorteio de 8 símbolos |
| `Unit/Architecture/IdTypesTest` | os 9 métodos de contrato, os 5 eventos e o job `DeliverOrder` falam em `string`, o `PayableOrders::findForPayment` recebe o dono, e nenhum `int` chamado `id` ou terminado em `Id` sobra em `Modules` |
| `Unit/Architecture/OrderCodeBoundariesTest` | o código do pedido fora dos contratos, dos eventos e das rotas |
| `Unit/Architecture/RouteBindingTest` | nenhuma rota de `/api` resolve registro: nem binding implícito (parâmetro de ação `UrlRoutable`), nem explícito, e todo parâmetro tem o padrão do id canônico. Vistos falhando com uma violação de propósito por regra |
| `Unit/Architecture/TypedListSignaturesTest` | nenhum `array` nas assinaturas de domínio que carregam as listas tipadas, os contratos de métricas incluídos |
| `Requests/AccountRequestRulesTest` | os Form Requests de conta usam o `EmailRule`, o `PasswordRule` e o `NormalizesEmailInput` do próprio módulo (Identity ou Customers) e não repetem as regras |
| `Unit/Architecture/HttpAdaptersTest` | os adaptadores HTTP do Customers e do Fulfillment não escrevem limite, expressão regular nem lista de UFs e delegam a `Email`, `Password` e `BrazilianState`; cada request de UF usa o `NormalizesStateInput` do próprio módulo |
| `Unit/Resources/CustomerProfileResourceTest` | o `CustomerProfileResource` do Identity e o do Customers mostram a conta no mesmo formato |
| `SeederTest` | quantidades mínimas exigidas, as 2 contas da equipe e os 10 clientes (em `customers`), endereço para todo cliente, pedidos com cópia e frete da tabela, data prevista só nos pedidos pagos e determinismo dos seeders |
| `RateLimiterStoreTest` | sem `CACHE_LIMITER`, o limitador de requisições usa o armazenamento padrão (os limites valem como sempre) |

---

## Testes de carga (k6)

A pasta [`load-tests/`](load-tests/README.md) guarda os testes de desempenho e de carga com o [k6](https://grafana.com/docs/k6/latest/) (HEL-14; design em [.design/load-tests-k6.md](.design/load-tests-k6.md)). Os números são uma **linha de base**: comparam uma rodada com a próxima, na mesma máquina, com a mesma configuração e o mesmo conjunto de dados. Não dizem quanto um servidor de produção aguentaria.

```bash
make load-test TYPE=smoke
```

- **Pilha de carga.** Cada rodada recria o projeto Compose `ecommerce-load` (`load-tests/docker-compose.yml`): Postgres (`ecommerce_load`) e Redis próprios, a API com configuração de produção (sem debug, opcache sem revalidação, caches em `/tmp/laravel` dentro do container, PHP-FPM estático com 16 workers), o worker, o relay, o Nginx e o k6. Ela compartilha com a de dev só a imagem do PHP e a árvore `backend/`, e nenhuma porta é publicada no host.
- **Dados.** O `LoadTestSeeder` (fora do `DatabaseSeeder`) cria sempre os mesmos 1.000 clientes (`load-customer-<n>@example.com`), a equipe, 201 produtos (170 disponíveis, 10 sem unidades, 20 inativos e o `Produto Disputado`, com 10) e 10.000 pedidos de histórico, em cerca de 5 segundos.
- **Mistura.** Os perfis de volume dividem os VUs em cerca de 75% vitrine, 20% compra e 5% equipe. Cada requisição declara os status que espera: um `404` ou um `409` esperados não são erro.
- **Limites de requisição.** Nos perfis de volume e no `contention`, a pilha roda com `CACHE_LIMITER=array`, e nenhum limite é atingido. O `smoke` e o `limits` rodam com os limites reais.
- **Espera até pagar.** A compra consulta o pedido a cada 2 segundos, como o frontend, até `awaiting_payment`. O tempo desde o `201` é a métrica `order_payable_wait`, que mede o relay do outbox e o worker sob carga.

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
| `nginx` | `nginx:alpine` | Ponto único de entrada (`:8080`). Roteia `/api` e `/sanctum` para o PHP-FPM, com o IP da conexão em `REMOTE_ADDR`, e o resto para o Vite (inclui WebSocket do HMR) |
| `api` | `docker/php/Dockerfile` (PHP 8.5-FPM) | Laravel. Instala as dependências do Composer na primeira execução |
| `queue-worker` | mesma imagem da `api` | `php artisan queue:work redis` — processa as entregas aos consumidores (`DeliverEvent`) e os jobs |
| `outbox-relay` | mesma imagem da `api` | `php artisan outbox:relay` — publica as mensagens pendentes de `outbox_messages` na fila, em até 1 s, e apaga as publicadas há mais de 7 dias |
| `frontend` | `docker/node/Dockerfile` (Node 24) | Servidor de desenvolvimento do Vite |
| `db` | `postgres:18-alpine` | Banco principal (`ecommerce`) e de testes (`ecommerce_testing`) |
| `redis` | `redis:8-alpine` | Fila, sessões e cache |

O código do backend e do frontend é montado como volume: alterações aparecem imediatamente (HMR no frontend; o PHP-FPM lê os arquivos a cada requisição). O worker e o relay precisam de `make queue-restart` após mudanças em jobs, consumidores ou no relay.

Os testes de carga sobem um segundo projeto Compose, o `ecommerce-load` (`load-tests/docker-compose.yml`), com os seus próprios `db`, `redis`, `api`, `queue-worker`, `outbox-relay` e `nginx`, mais o `k6` (`grafana/k6`). Ele existe só durante as rodadas e não publica portas; ver [Testes de carga (k6)](#testes-de-carga-k6).

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

Dica: deixe `make logs-worker` aberto em outro terminal para ver as entregas e os jobs sendo processados (`ordering.order_placed -> ...MarkOrderAsAwaitingPayment`). Para ver o outbox segurando um evento, pare o Redis (`docker compose stop redis`) antes de confirmar a compra: o pedido fica em *Pedido efetuado*, a mensagem continua pendente e o `make logs-relay` avisa a cada 5 s; depois de `docker compose start redis`, o pedido segue para *Aguardando pagamento*.

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
  - **A data prevista** é calculada pelo Fulfillment ao tratar o `OrderPaid` e chega ao pedido pelo evento `DeliveryScheduled`; só o Ordering escreve em `orders`. Sem tabela de remessas: não há rastreio nem etapa "Enviado".
  - **Limite de 10 endereços** por cliente, sem trava de concorrência: dois cadastros simultâneos podem deixar o cliente com 11, e isso é aceito.
  - **Sem migração de dados**: a tabela nova e as colunas novas de `orders` entraram nas migrations originais, então quem tem banco local roda `make fresh`, e dumps antigos de `storage-dumps/` deixam de ser compatíveis.
- **Estoque separado do produto**: a edição de produto (`PUT /api/admin/products/{product}`) não altera estoque; ajustes passam por `/api/admin/stocks/{stock}`.
- **Equipe e clientes são contas separadas** (feature `staff-and-customer-accounts`, design em [.design/staff-and-customer-accounts.md](.design/staff-and-customer-accounts.md)): a equipe (admin e suporte) vive em `users`, o comprador em `customers`, e o mesmo e-mail pode existir nas duas. Um e-mail continua único dentro de cada tabela. Não há migração de dados: o banco é recriado por `make fresh` (as migrations originais foram editadas no lugar).
- **Um guard de sessão por área**: `config/auth.php` define `customer` (provider `customers` → `CustomerAccount`, o padrão) e `staff` (provider `users` → `User`). As rotas da loja usam `auth:customer` e as do admin `auth:staff`; **nenhuma rota usa `auth:sanctum`** (o Sanctum fica só no `statefulApi()`, para sessão e CSRF), e um teste confere isso. Com `auth:sanctum` e uma lista de guards, a primeira sessão que autenticasse valeria, e uma sessão de equipe passaria nas rotas da loja. Código novo deve seguir o mesmo precedente. O browser tem **um cookie de sessão**, então `POST /api/auth/logout` e `POST /api/admin/auth/logout` encerram as duas áreas.
- **Só o admin remove** (precedente): todo `DELETE` de `/api/admin/*` e o recurso `/api/admin/users` ficam no grupo com o middleware `admin` (`EnsureUserIsAdmin`), e o suporte recebe `403`. Uma rota `DELETE` nova entra no grupo restrito por padrão, e o teste `StaffRoleTest` percorre `Route::getRoutes()` e falha se algum `DELETE` de `api/admin/*` responder diferente de `403` ao suporte. A alternativa, um `delete` em cada policy, deixaria uma rota nova sem o método liberar o suporte em silêncio.
- **Equipe**: o admin cadastra, edita, troca o papel e remove membros em **Usuários**. Ele não muda o próprio papel nem remove a própria conta (`409`, `BusinessRuleException`). Dois admins se removendo ou se rebaixando ao mesmo tempo é aceito sem trava: pode sobrar zero admins, e a recuperação é pelo seeder. Excluir clientes continua fora do escopo (`orders.customer_id` impede, e não há decisão de LGPD).
- **Módulos** (`app/Modules/<Módulo>`): cada módulo repete a mesma estrutura interna, só com as pastas de que precisa:

  ```
  Modules/Ordering/
    Consumers/  DTOs/  Enums/  Events/  Exceptions/  Models/
    Queries/  Repositories/  Services/  UseCases/  ValueObjects/
    Http/Controllers/(Admin/)  Http/Requests/(Admin/)  Http/Resources/
  ```

  - **`Admin/`** separa o que pertence à área administrativa, como `Catalog\Http\Controllers\ProductController` (vitrine) e `Catalog\Http\Controllers\Admin\ProductController` (cadastro).
  - **Ligações explícitas:** models e factories se declaram por `#[UseFactory]` / `protected $model`. Assim não dependem mais da convenção de namespace `App\Models`.
  - **Rotas:** ficam centralizadas em `routes/api.php`, que importa cada controller pelo nome completo, para manter num só lugar o mapa da API e os grupos de middleware (`auth:customer`, `auth:staff`, `admin`).
  - **Testes:** continuam em `backend/tests`, organizados por tipo (Unit/Feature). Só os namespaces importados mudaram.
- **Contratos do estoque**: o catálogo, o carrinho, o checkout e o dashboard não usam o `StockRepository` diretamente, e sim quatro contratos em `App\Modules\Inventory\Contracts`, que falam em ids e quantidades:
  - `StockInitializer::createForProduct(productId, quantity)`, usado pelo `CreateProductUseCase` para abrir o estoque de um produto novo, na mesma transação.
  - `StockLevels::quantitiesFor(ProductIds): StockQuantities`, usado pelo `ValidateCartUseCase`, sem bloqueio, e `StockLevels::stocksFor(ProductIds): ProductStocks`, que devolve o id da linha e a quantidade de cada produto e é usado pela consulta do Catalog (`ProductQuery`) para a vitrine e o cadastro de produto, numa leitura só para a página inteira.
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
  - **A forma canônica mora em `Shared\Support\RecordId`** (`[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}`, só minúsculas). Dela leem os `Route::pattern` do `AppServiceProvider` (um por parâmetro: `product`, `category`, `stock`, `customer`, `user`, `order` e `address`), a regra `CanonicalRecordId` dos ids do corpo e as listas tipadas. `whereUuid` e a regra `uuid` do Laravel foram descartadas porque aceitam maiúsculas, e um id da própria conta em maiúsculas passaria como outro na comparação de texto do `DELETE /api/admin/users/{user}`. Um id malformado nunca chega ao banco, porque o PostgreSQL responde com erro (e não com "nenhuma linha") a um texto que não é UUID: no caminho é `404` sem query, no corpo é `422` (com `bail` antes de qualquer `exists`).
  - **Nenhuma rota resolve registro** (nem binding implícito, nem explícito). O controller carrega o id como `string`, e o use case ou o repositório o resolve. Isso desfez o desvio que a análise de domínio registrava para o pagamento. O custo aceito: o corpo é validado antes da existência. Um binding explícito por parâmetro foi descartado: não economizaria nenhuma query e deixaria dois mecanismos.
  - **O dono entra na busca, e as policies que só conferiam o dono saíram.** O registro de outro cliente é indistinguível de um id desconhecido (`404`). Manter a policy como segunda checagem, respondendo `403`, confirmaria que o registro existe. Conferir o dono depois da busca, no use case, deixaria o `409` de status sair antes do `404`.
  - **O código do pedido** (`orders.code`): sorteado, independente do id, único e imutável. Uma colisão nunca derruba o pedido: o `PlaceOrderUseCase` isola a gravação num savepoint e sorteia de novo, até 3 vezes (o savepoint é necessário porque, no PostgreSQL, uma violação de unicidade aborta a transação inteira). O laço fica no use case, e não no repositório, porque um repositório não usa os services do módulo. Um código derivado do UUID foi descartado (não é único garantido), e uma sequência também (revelaria a contagem de pedidos).
  - **Sem migração de dados**: as migrations de criação das 9 tabelas foram editadas no lugar. Quem tem banco local roda `make fresh` e `make queue-restart`, e dumps antigos de `storage-dumps/` deixam de ser compatíveis.
- **Facades dos módulos** (HEL-7, design em [.design/module-facades.md](.design/module-facades.md)):
  - **A facade de um módulo é o seu `Contracts` mais os seus `Events`.** Contratos por papel, só para travessias que existem, nomeados pelo papel de que o chamador precisa: `ProductCatalog` (Catalog), `StockLevels` (Inventory), `PayableOrders` e `CustomerOrderHistory` (Ordering) e `CustomerAccounts` (Identity), ao lado dos que já existiam. Quando o fornecedor já depende do consumidor, o consumidor define o contrato (`ProductOrderHistory`, `DeliveryAddressBook`, `ShippingQuoter`). Uma interface única por módulo foi descartada: todo consumidor dependeria de tudo.
  - **O pagamento lê o pedido pelo `PayableOrders`**, dentro do `PayOrderUseCase`, com o id do cliente e o id do pedido (`findForPayment($customerId, $orderId)`). Um pedido desconhecido ou de outro cliente é `404`, antes da conferência do status. A resposta `202` passou a ser a tentativa de pagamento (o frontend só lê o `message`).
  - **A conta e o admin de clientes mostram pedidos resumidos** (`id`, `status`, `status_label`, `total_cents`, `created_at`), que são os campos que as telas usam. As relações Eloquent `Payment::order()` e `OrderItem::product()` saíram; as chaves estrangeiras continuam.
  - **Exceções declaradas na HEL-7, removidas na HEL-6**: o Backoffice lia os repositories de quatro módulos e o enum `ProductStatus` do Catalog, e a vitrine e a lista de estoque do admin usavam `Product::stock`, `Stock::product` e a regra de disponibilidade do Ordering. As 9 saíram do `ModuleBoundariesTest` (ver "Lado de leitura" abaixo).
- **Vocabulário público dos módulos** (HEL-10, design em [.design/module-vocabulary.md](.design/module-vocabulary.md)):
  - **Público é o que uma assinatura expõe**, não a pasta. O `ModuleBoundariesTest` lê por reflexão os métodos públicos das interfaces de `Contracts` e das classes de `Events` de cada módulo e segue os tipos nativos (parâmetros, retornos, propriedades públicas) em cada classe alcançada. Hoje são 29 tipos públicos (por exemplo `CatalogProducts`, `ProductStatus` e `ProductAvailability` pelo `CatalogProduct`, `StockQuantities` e `ProductStocks`, `Email` e `Password` pelo `CreateUserDTO`, `OrderStatus` pelo `OrderForPayment`, `DeclineReason` e `PaymentStatus` pelo `ChargeResult`) e 28 privados (por exemplo `ValidatedCart`, `OrderLines`, `ProductQuantities`, `ProductRecord`, `StockRecord`, `LoginCredentialsDTO`, `UserRole`). Um tipo novo numa assinatura fica público e uma classe nova que nenhuma assinatura alcança fica privada, sem editar o teste.
  - **O `Http` de cada módulo é privado, sem exceção.** O Customers tem o seu `EmailRule`, `PasswordRule`, `NormalizesEmailInput`, `NormalizesStateInput` e `CustomerProfileResource`, e o Fulfillment o seu `NormalizesStateInput`. Eles só traduzem para o Laravel o que `Email`, `Password` e `BrazilianState` decidem (o `HttpAdaptersTest` cobra), e o `CustomerProfileResourceTest` mantém a conta no mesmo formato no Identity e no Customers. Expor as peças do Identity e do Ordering como exceção foi descartado: poria código de framework na superfície pública.
- **Lado de leitura** (HEL-6, design em [.design/cqrs-read-side.md](.design/cqrs-read-side.md)):
  - **Toda leitura que cruzava módulo virou uma consulta** do módulo que serve a rota (`Queries/`), que devolve dados e pede ao dono, pelo contrato dele, o que não é seu: a vitrine e o cadastro de produto do admin pedem o estoque ao `StockLevels::stocksFor`, a lista de estoque pede os produtos ao `ProductCatalog`, e o dashboard lê o `CatalogMetrics`, o `StockMetrics`, o `CustomerMetrics` e o `OrderMetrics`. Um contrato de leitura é implementado por uma consulta do dono, e um contrato que grava ou trava continua no repositório. `Product::stock` e `Stock::product` (HEL-6) e o caso de uso do dashboard deixaram de existir; a chave estrangeira `stocks.product_id` e a exclusão em cascata continuam.
  - **A leitura é sempre exata.** Um modelo de leitura projetado pelos eventos do outbox foi descartado: nenhuma leitura lenta foi medida, a projeção pediria eventos de produto, estoque e cliente que não existem, e a vitrine ficaria alguns segundos atrasada. Uma consulta que junta as tabelas de outro módulo num SQL só também: faria uma query a menos por página, mas o Catalog dependeria do esquema de `stocks` sem contrato.
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
- **Contar antes de conferir** (achado no perfil `limits` do k6, HEL-14: numa rajada de 30 logins simultâneos com senha errada, passaram 11 contra o limite de 10): o `ThrottleRequests` do Laravel lê a contagem, compara com o limite e só depois soma a requisição. As requisições que chegam juntas leem a contagem antes de qualquer uma somar, e todas passam: no `RateLimitBurstTest`, com 30 processos soltos no mesmo instante, passaram as 30. Agora o alias `throttle` é o [`AtomicThrottleRequests`](backend/app/Modules/Shared/Http/Middleware/AtomicThrottleRequests.php), que soma primeiro e deixa passar a requisição cuja contagem não passa do limite. Os limites, os nomes dos contadores e o `429` (a exceção e os headers do framework) não mudaram.
  - **O incremento também precisava ser um comando só.** O `RateLimiter::increment` do framework cria o contador em zero, incrementa e, quando o incremento diz 1 mas a criação falhou, grava 1 por cima. As requisições que abrem a janela se intercalam ali, e a gravação apaga os acertos das outras: só com a ordem trocada no middleware, passaram até 19 de 30, em 12 de 40 rajadas. O [`AtomicRateLimiter`](backend/app/Modules/Shared/Cache/AtomicRateLimiter.php) incrementa e dá a validade da janela num script Lua, e com as duas mudanças passaram exatamente 10 nas 40 rajadas. Ele entra por um `extend` no `AppServiceProvider` (o framework registra o `RateLimiter` num provider adiado, que substituiria uma ligação comum ao carregar), na mesma store (`cache.limiter`) e com as mesmas chaves (o contador e o `:timer` do `Retry-After`).
  - **O `throttleWithRedis()` do Laravel não resolve**: o `ThrottleRequestsWithRedis` confere num script, soma em outro e ignora a resposta do próprio `acquire()`; passaram 21, 13 e 15 de 30 em três rajadas. O `RateLimiter::attempt` também confere antes de somar. Uma trava (`Cache::lock`) em volta da conferência e da soma faria as requisições da mesma chave esperarem umas pelas outras.
  - **Uma requisição recusada também conta**, porque soma antes de saber. A janela não estica: termina um minuto depois do primeiro acerto, e o `Retry-After` continua vindo dela. Com mais de um limite na rota (hoje nenhuma tem), a requisição recusada por um já contou nos anteriores. Um limite com `after()` só sabe se conta depois da resposta e continua na ordem do framework.
  - Fora do Redis, o incremento é o do framework: a store `array`, dos testes e dos perfis de volume do k6, vive num processo só.
