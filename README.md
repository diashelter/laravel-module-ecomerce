# Loja Demo — e-commerce de estudo (Laravel 13 + Vue 3)

Aplicação de e-commerce **pequena, mas tecnicamente completa**, criada para estudo e testes técnicos. O foco não é ter muitas funcionalidades, e sim implementar corretamente conceitos importantes de engenharia de software:

- API REST com Laravel (Form Requests, API Resources, Policies, Middleware)
- Autenticação SPA com **Laravel Sanctum** (cookie HTTP-only, sem token no `localStorage`)
- Autorização por papel (`admin` / `customer`)
- Relacionamentos **1:1** (`Product` ↔ `Stock`) e **N:N** (`Product` ↔ `Category`)
- Carrinho com **Pinia** e checkout validado no backend
- **Transação atômica** + `lockForUpdate()` para controle de **concorrência** de estoque
- **Eventos, listeners e jobs assíncronos** em fila **Redis**
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
12. [Eventos, listeners, jobs e filas](#eventos-listeners-jobs-e-filas)
13. [Regra de disponibilidade do produto](#regra-de-disponibilidade-do-produto)
14. [API REST](#api-rest)
15. [Frontend](#frontend)
16. [Testes](#testes)
17. [Containers](#containers)
18. [Fluxo completo para demonstração](#fluxo-completo-para-demonstração)
19. [Escopo e decisões](#escopo-e-decisões)

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
            └───▲────┘   │ sessão │      │   da api)    │
                │        └────────┘      └──────┬───────┘
                └───────────────────────────────┘
```

- **Frontend e backend no mesmo host** (`localhost:8080`): o Nginx encaminha `/api/*` e `/sanctum/*` para o Laravel e o restante para o Vite. Isso permite autenticação por cookie de sessão sem CORS.
- **Backend em Laravel organizado em módulos** (`app/Modules/<Módulo>`), um por contexto de negócio. Continua sendo **um monólito**, com um único banco e um único deploy, sem CQRS nem microsserviços. Os contextos e as fronteiras entre eles estão em [docs/domain-analysis.md](docs/domain-analysis.md).
- **Dentro de cada módulo, as mesmas camadas**: validação em Form Requests e respostas com API Resources.
  - **Controllers** (`Http/Controllers`): só HTTP (request, autorização, resposta). Consultas simples chamam um repository direto.
  - **Casos de uso** (`UseCases/`): cada um representa uma intenção do usuário ou do sistema (ex.: `PlaceOrderUseCase`) e orquestra o fluxo: transação, repositories, services, eventos e jobs.
  - **Services** (`Services/`): regras de negócio **puras**, sem acesso a banco, transação ou eventos.
  - **Repositories** (`Repositories/`): única camada que lê e grava no banco.
- **Fronteiras verificadas por teste**: o `ModuleBoundariesTest` (teste de arquitetura do Pest) falha se um módulo usar o que não devia de outro.
- **Processamento assíncrono**: mudanças de status do pedido são feitas por listeners/jobs executados pelo container `queue-worker`.

---

## Estrutura de pastas

```
/
├── backend/                         Laravel 13 (API only)
│   ├── app/
│   │   ├── Modules/
│   │   │   ├── Catalog/             produtos e categorias (vitrine e cadastro)
│   │   │   ├── Inventory/           estoque; publica Contracts/ (StockInitializer, StockReservation) e InventoryServiceProvider
│   │   │   ├── Ordering/            carrinho, checkout, pedidos, Customer (comprador, somente leitura) — núcleo do negócio
│   │   │   ├── Payment/             pagamento fake: só publica PaymentApproved
│   │   │   ├── Fulfillment/         entrega fake: agenda o DeliverOrder e publica OrderDelivered
│   │   │   ├── Identity/            User, papéis, login (Sanctum), EnsureUserIsAdmin, UserPolicy
│   │   │   ├── Customers/           "Minha conta" e a tela Clientes do admin (CustomerSummaryDTO)
│   │   │   ├── Backoffice/          dashboard do admin (read model que lê todos os módulos)
│   │   │   └── Shared/              infraestrutura comum: erros da API, ApiFormRequest, BaseRepository, Controller, X-Request-ID
│   │   └── Providers/               AppServiceProvider (configurações globais)
│   ├── config/shop.php              configurações da loja (delay da entrega, paginação)
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
│       ├── composables/             usePolling, useFormErrors
│       ├── layouts/                 PublicLayout, AuthLayout, CustomerLayout, AdminLayout
│       ├── pages/                   public/, auth/, account/, admin/
│       ├── router/                  rotas + guards de autenticação/autorização
│       ├── services/                api.ts (Axios central) + serviços por recurso
│       ├── stores/                  Pinia: auth, cart, notifications
│       ├── types/                   tipos TypeScript da API
│       └── utils/                   dinheiro, datas, slug
│
├── docker/
│   ├── nginx/default.conf
│   ├── node/Dockerfile
│   ├── php/Dockerfile, php.ini, entrypoint.sh
│   └── postgres/init/               cria o banco de testes
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
| `make logs` | Logs principais (api, queue-worker, nginx, frontend) |
| `make logs-api` | Logs da API |
| `make logs-worker` | Logs do worker de filas |

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
| `make queue-restart` | Reinicia os workers (**necessário após alterar código de jobs/listeners**) |
| `make queue-failed` | Lista os jobs que falharam |
| `make queue-retry` | Reprocessa todos os jobs falhos (ou um: `make queue-retry ID=<uuid>`) |

### Testes

| Comando | Descrição |
| --- | --- |
| `make test` | Executa todos os testes (Pest) |
| `make test-filter FILTER="..."` | Executa testes filtrados. Ex.: `make test-filter FILTER=CheckoutTest` |

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
users ──1:N── orders ──1:N── order_items ──N:1── products ──1:1── stocks
                                                     │
                                                     N:N (category_product)
                                                     │
                                                 categories
```

| Tabela | Destaques |
| --- | --- |
| `users` | `role` (`admin` / `customer`), indexado. Lida como `User` (identidade) e como `Customer` (comprador, somente leitura) |
| `categories` | `slug` **único**, gerado automaticamente a partir do nome (`"Eletrônicos"` → `eletronicos`) |
| `products` | `price decimal(10,2)` (nunca `float`), `status` (`active` / `inactive`). **Não** possui quantidade |
| `stocks` | `product_id` **único** (garante o 1:1), `quantity` inteiro com `CHECK (quantity >= 0)` |
| `category_product` | pivot com chave primária composta |
| `orders` | `total decimal(12,2)`, `status` (`placed`, `awaiting_payment`, `payment_approved`, `delivered`) |
| `order_items` | **snapshot** de `product_name` e `unit_price`: mudar o produto não altera pedidos antigos |

**Integridade:**

- `stocks` e `category_product` usam `cascade` (são dependentes do produto).
- `order_items.product_id` e `orders.user_id` usam `restrict`: **não é possível excluir um produto que já está em pedidos** (a API responde `409` e sugere desativar o produto).
- Categorias com produtos associados também não podem ser excluídas (`409`).

### Migrations e seeders

```bash
make fresh      # php artisan migrate:fresh --seed
make migrate    # apenas migrations
make seed       # apenas seeders
```

Seeders (chamados pelo `DatabaseSeeder` nesta ordem):

| Seeder | O que cria |
| --- | --- |
| `UserSeeder` | 1 admin + 10 clientes (inclui `cliente@example.com`) |
| `CategorySeeder` | 8 categorias: Eletrônicos, Informática, Celulares, Acessórios, Casa, Escritório, Games, Periféricos |
| `ProductSeeder` | 30 produtos (25 ativos, 5 inativos), cada um com 1 a 3 categorias e imagem `https://picsum.photos/seed/product-{n}/600/600` |
| `StockSeeder` | Estoque para cada produto: 6 sem estoque, 6 com estoque baixo (1–5), 9 médio (6–20), 9 alto (21–150) |
| `OrderSeeder` | 24 pedidos com itens, snapshots e totais; metade nos últimos 30 dias (todos os status) e metade nos últimos 12 meses (entregues) |

### Como o Faker é utilizado

- As **factories** (`database/factories`) usam o Faker (`fake()`) com locale `pt_BR` para nomes de clientes, e-mails, nomes/descrições/preços de produtos e datas dos pedidos.
- O `DatabaseSeeder` fixa a semente do Faker (`fake()->seed(2026)`): **toda execução de `make fresh` gera exatamente os mesmos dados**, o que deixa o ambiente previsível.
- Os seeders são idempotentes onde faz sentido (admin via `firstOrNew`, categorias via `firstOrCreate`; produtos/pedidos só são criados se a tabela estiver vazia).
- Os pedidos de demonstração **não decrementam estoque**: representam vendas passadas, anteriores à contagem atual de estoque.

Exemplo de uso das factories em testes ou no Tinker (`make artisan CMD=tinker`):

```php
Product::factory()->withStock(10)->create();          // produto ativo com 10 unidades
Product::factory()->inactive()->withStock(0)->create();
User::factory()->admin()->create();
OrderItem::factory()->forProduct($product, 2)->create();
```

---

## Usuários de teste e dados disponíveis

| Perfil | E-mail | Senha |
| --- | --- | --- |
| Administrador | `admin@example.com` | `password` |
| Cliente | `cliente@example.com` | `password` |
| Demais clientes | e-mails gerados pelo Faker (veja em **Admin → Clientes**) | `password` |

Após o seed você terá: 11 usuários, 8 categorias, 30 produtos (com produtos sem estoque, com estoque baixo e inativos), 24 pedidos distribuídos entre todos os status e datas — o dashboard já mostra gráficos na primeira execução.

---

## Fluxo de checkout

1. O carrinho fica no **Pinia** (persistido no `localStorage` — apenas os dados do carrinho).
2. Ao abrir `/checkout`, o frontend chama `POST /api/cart/validate`, que revalida **existência, status, estoque, quantidade e preço atual** de cada item. Problemas aparecem por item e bloqueiam a confirmação.
3. Ao confirmar, o frontend envia **apenas `product_id` e `quantity`** para `POST /api/orders`. Preços e totais enviados pelo cliente são ignorados.
4. O `PlaceOrderUseCase` (backend) orquestra o fluxo, usando o `CheckoutService` para as regras e os repositories para o banco:
   1. valida usuário autenticado (e que é cliente — `OrderPolicy@create`);
   2. valida o formato dos itens (`StoreOrderRequest`);
   3. **inicia a transação** (`DB::transaction()`);
   4. **bloqueia os registros de estoque** envolvidos (`lockForUpdate()`);
   5. verifica novamente status e estoque com os valores bloqueados;
   6. **recalcula os preços** a partir do banco (com `BcMath\Number`, sem `float`);
   7. **reduz o estoque**;
   8. cria o pedido (`placed`) e os itens (snapshot de nome e preço);
   9. **confirma a transação**;
   10. dispara o evento `OrderPlaced` (somente após o commit).
5. O frontend limpa o carrinho e redireciona para `/payment/{orderId}`.

Respostas: `201` (pedido criado), `409` (estoque insuficiente / produto indisponível, com detalhes por item em `errors`), `422` (dados inválidos), `401`/`403`.

---

## Transação e concorrência de estoque

Trecho central de `backend/app/Modules/Ordering/UseCases/PlaceOrderUseCase.php`:

```php
DB::transaction(function () use ($customerId, $quantities) {
    // SELECT ... FROM stocks WHERE product_id IN (...) ORDER BY product_id FOR UPDATE
    // (contrato StockReservation, implementado pelo StockRepository — ordem fixa evita deadlocks)
    $stocks = $this->stockReservation->lockForProducts($productIds);

    // revalida com os valores bloqueados; se faltar estoque, lança exceção → ROLLBACK
    $this->checkout->assertCanFulfil($quantities, $products);

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

Sem o lock, os dois poderiam ler `5` ao mesmo tempo e o estoque ficaria negativo. Camadas de proteção:

1. validação no checkout (antes de confirmar);
2. revalidação **dentro** da transação com lock pessimista;
3. `CHECK (quantity >= 0)` no banco como última linha de defesa;
4. ajustes do admin (`AdjustStockUseCase`) também usam transação + `lockForUpdate()`.

Para ver a concorrência na prática, com o ambiente rodando, deixe um produto com estoque 5 (Admin → Estoque) e dispare dois checkouts simultâneos de clientes diferentes (4 e 3 unidades): um recebe `201` e o outro `409 — Estoque insuficiente. Disponível: 1.`

---

## Eventos, listeners, jobs e filas

O ciclo de vida do pedido envolve três partes, que só conversam por eventos:

- **Pedidos** (Ordering): é a única parte que muda o status do pedido.
- **Pagamento** (Payment): só anuncia que o pagamento foi aprovado.
- **Entrega** (Fulfillment): só anuncia que a transportadora (fake) entregou.

```
POST /api/orders ──► OrderPlaced ──(fila)──► MarkOrderAsAwaitingPayment        placed → awaiting_payment

POST /api/orders/{id}/payment
  Pagamento ──► PaymentApproved ──(fila)──► MarkOrderAsPaid                    awaiting_payment → payment_approved
                                                │
  Pedidos   ◄───────────────────────────────────┘──► OrderPaid
                                                       │
  Entrega   ──(fila)──► ScheduleOrderDelivery ──► DeliverOrder (job, delay 10s) ──► OrderDelivered
                                                                                    │
  Pedidos   ──(fila)──► MarkOrderAsDelivered ◄──────────────────────────────────────┘   payment_approved → delivered
```

| Classe | Parte | Tipo | O que faz |
| --- | --- | --- | --- |
| `App\Modules\Ordering\Events\OrderPlaced` | Pedidos | Evento (`ShouldDispatchAfterCommit`) | Disparado após o commit do checkout |
| `App\Modules\Ordering\Listeners\MarkOrderAsAwaitingPayment` | Pedidos | Listener (`ShouldQueue`) | Move o pedido para `awaiting_payment` (`MarkOrderAsAwaitingPaymentUseCase`) |
| `App\Modules\Payment\Events\PaymentApproved` | Pagamento | Evento | Disparado pelo pagamento fake (`PayOrderUseCase`) |
| `App\Modules\Ordering\Listeners\MarkOrderAsPaid` | Pedidos | Listener (`ShouldQueue`) | Move para `payment_approved` e dispara `OrderPaid` (`MarkOrderAsPaidUseCase`) |
| `App\Modules\Ordering\Events\OrderPaid` | Pedidos | Evento | Disparado só pela transição que de fato marcou o pedido como pago |
| `App\Modules\Fulfillment\Listeners\ScheduleOrderDelivery` | Entrega | Listener (`ShouldQueue`) | Agenda o job de entrega (`ScheduleDeliveryUseCase`) |
| `App\Modules\Fulfillment\Jobs\DeliverOrder` | Entrega | Job (`ShouldQueue`) | Após `ORDER_DELIVERY_DELAY_SECONDS`, simula a entrega e dispara `OrderDelivered` (`DeliverOrderUseCase`) |
| `App\Modules\Fulfillment\Events\OrderDelivered` | Entrega | Evento | Anuncia que a transportadora (fake) entregou o pedido |
| `App\Modules\Ordering\Listeners\MarkOrderAsDelivered` | Pedidos | Listener (`ShouldQueue`) | Move para `delivered` (`MarkOrderAsDeliveredUseCase`) |

- **A entrega escuta `OrderPaid`, não `PaymentApproved`.** Se escutasse direto o pagamento, o job de entrega correria em paralelo com a marcação de pago. Num retry ou atraso da fila, ele poderia rodar antes e o pedido ficaria preso em `payment_approved`.
- Pagamento e entrega **nunca** alteram o status: quem faz isso são os listeners da parte de pedidos.
- Os listeners são registrados automaticamente (event discovery do Laravel), procurando em `app/Modules/*/Listeners` (configurado em `bootstrap/app.php`).
- Listeners e job são adaptadores finos: só delegam para o caso de uso correspondente.
- Todas as transições usam `OrderRepository::transitionStatus($order, $from, $to)`, um `UPDATE ... WHERE status = :from`: se um job for executado duas vezes ou fora de ordem, ele simplesmente não faz nada (**idempotência**). Por isso um `PaymentApproved` duplicado gera um único `OrderPaid`, e um retry do `DeliverOrder` (que anuncia a entrega de novo) é ignorado.
- O endpoint de pagamento responde **`202 Accepted`**, pois a mudança de status acontece de forma assíncrona. O frontend faz *polling* da página do pedido para mostrar a timeline sendo atualizada.
- O administrador **não altera o status manualmente**: ele é controlado exclusivamente pelos eventos e jobs.

Acompanhando a fila:

```bash
make logs-worker     # logs do container queue-worker
make queue           # worker extra em primeiro plano
make queue-failed    # jobs que falharam
make queue-retry     # reprocessar falhas
```

> Depois de alterar código de listeners/jobs, rode `make queue-restart` — o worker mantém o código carregado em memória.

---

## Regra de disponibilidade do produto

Um produto só pode ser comprado quando **`status = active` E estoque > 0**. A única fonte da regra no backend é o `App\Modules\Ordering\Services\PurchaseAvailabilityService`:

- `isAvailable($product, $stock)`: combina o status do catálogo (`Product::isActive()`) com as unidades do estoque (`Stock::hasUnits()`).
- `availableQuantity($stock)`: quantidade disponível (`0` quando o produto não tem linha de estoque).
- `purchaseProblem($product, $stock, $quantity)`: motivo pelo qual a quantidade não pode ser comprada (`Produto indisponível.` ou `Estoque insuficiente. Disponível: N.`), ou `null`.

O estoque é passado explicitamente porque cada chamador sabe de onde ele vem: relação carregada no catálogo e no carrinho, linha bloqueada (`FOR UPDATE`) no checkout. O catálogo público, o detalhe do produto, a validação do carrinho, o checkout e a tela de estoque do admin usam todos esse mesmo service.

- Na listagem pública (`/products`) todos os produtos aparecem; os indisponíveis ficam esmaecidos, não são clicáveis, não podem ser adicionados ao carrinho e mostram "Sem estoque" ou "Indisponível".
- `GET /api/products/{id}` responde `404 — Produto indisponível.` para produtos inativos ou sem estoque.
- Quando o estoque chega a zero, o produto deixa de estar disponível automaticamente; quando volta a ser maior que zero, só fica disponível se estiver ativo.
- O frontend apenas reflete o campo `is_available` calculado pela API; checkout e validação do carrinho sempre revalidam no backend.

---

## API REST

Todas as rotas ficam em `backend/routes/api.php` com prefixo `/api`. Erros seguem sempre o formato (montado por `App\Modules\Shared\Http\Responses\ApiErrorResponse`):

```json
{
  "code": "INSUFFICIENT_STOCK",
  "message": "Estoque insuficiente para um ou mais produtos.",
  "errors": { "items.12": ["Estoque insuficiente. Disponível: 2."] },
  "request_id": "9d3c6f0e-6a4f-4c43-9a43-1f0b5e1f2a7b"
}
```

- `code`: identificador estável do erro (enum `App\Modules\Shared\Enums\ApiErrorCode`). O frontend deve decidir pelo `code`, nunca pelo texto de `message`. Valores: `VALIDATION_FAILED`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `CSRF_TOKEN_MISMATCH`, `TOO_MANY_REQUESTS`, `BUSINESS_RULE_VIOLATION`, `INSUFFICIENT_STOCK`, `HTTP_ERROR`, `SERVER_ERROR`.
- `errors`: mensagens por campo (lista de strings); `{}` quando o erro não é de um campo específico.
- `request_id`: o mesmo valor do header `X-Request-ID`, presente em **todas** as respostas. É gerado pelo middleware `AssignRequestId` (ou reaproveitado do header enviado por um proxy, se for seguro) e gravado no `Context` do Laravel, então aparece em todas as linhas de log da requisição e dos jobs enfileirados por ela.
- Respostas de erro são enviadas com `Cache-Control: private, no-store`.

| Método | Rota | Acesso | Descrição |
| --- | --- | --- | --- |
| GET | `/sanctum/csrf-cookie` | público | Inicializa o cookie CSRF (antes do login) |
| POST | `/api/auth/register` | público | Cadastro (sempre `customer`) |
| POST | `/api/auth/login` | público | Login (sessão por cookie) |
| POST | `/api/auth/logout` | autenticado | Logout |
| GET | `/api/auth/me` | autenticado | Usuário autenticado |
| GET | `/api/products?category=&sort=&page=` | público | Listagem paginada; `sort`: `name`, `price_asc`, `price_desc` |
| GET | `/api/products/{id}` | público | Detalhe (somente disponíveis) |
| GET | `/api/categories` | público | Categorias |
| POST | `/api/cart/validate` | público | Revalida o carrinho |
| POST | `/api/orders` | cliente | Checkout |
| GET | `/api/orders` | autenticado | Pedidos do usuário |
| GET | `/api/orders/{id}` | dono | Detalhe do pedido |
| POST | `/api/orders/{id}/payment` | dono | Pagamento fake (`202`) |
| GET | `/api/account` | autenticado | Resumo da área do cliente |
| PUT | `/api/account/profile` | autenticado | Atualiza nome, e-mail e senha |
| GET | `/api/admin/dashboard` | admin | Métricas agregadas |
| GET/POST | `/api/admin/products` | admin | Lista / cria (com estoque inicial) |
| GET/PUT/DELETE | `/api/admin/products/{id}` | admin | Detalhe / edita (sem estoque) / exclui |
| PATCH | `/api/admin/products/{id}/status` | admin | Ativa / desativa |
| GET/POST | `/api/admin/categories` | admin | Lista / cria |
| GET/PUT/DELETE | `/api/admin/categories/{id}` | admin | Detalhe / edita / exclui |
| GET | `/api/admin/stocks` | admin | Lista estoques |
| PUT | `/api/admin/stocks/{id}` | admin | `{ "operation": "increase" \| "decrease", "quantity": 5 }` |
| GET/POST | `/api/admin/users` | admin | Lista usuários / cria cliente |
| GET/PUT | `/api/admin/users/{id}` | admin | Detalhe / edita cliente |
| GET | `/api/admin/orders` | admin | Lista pedidos |
| GET | `/api/admin/orders/{id}` | admin | Detalhe do pedido |

Códigos HTTP usados: `200`, `201`, `202`, `204`, `401`, `403`, `404`, `409`, `422`, `429`, `500`.

> **Nota:** a validação do carrinho usa `POST /api/cart/validate` (em vez de `GET`) porque envia uma lista de itens no corpo da requisição — um `GET` com corpo ou com arrays aninhados na query string seria frágil.

Para listar as rotas: `make artisan CMD="route:list --path=api"`.

---

## Frontend

| Rota | Layout | Acesso |
| --- | --- | --- |
| `/products`, `/products/:id`, `/cart` | PublicLayout | público |
| `/checkout`, `/payment/:orderId` | PublicLayout | cliente |
| `/login`, `/register` | AuthLayout | visitante |
| `/account`, `/account/profile`, `/account/orders`, `/account/orders/:id` | CustomerLayout | cliente |
| `/admin`, `/admin/products`, `/admin/categories`, `/admin/stocks`, `/admin/users`, `/admin/orders` | AdminLayout | admin |

- **`src/services/api.ts`**: instância central do Axios (`withCredentials` + `withXSRFToken`) com tratamento global de erros — `401` (limpa a sessão e vai para o login), `403` (aviso + página de acesso negado), `5xx`/rede (aviso). `404`, `409` e `422` são tratados pelas páginas (mensagens e erros por campo).
- **Pinia**: `auth` (usuário autenticado, sem tokens), `cart` (carrinho persistido) e `notifications` (toasts).
- **Router**: guards por `meta.requiresAuth`, `requiresAdmin`, `requiresCustomer` e `guestOnly`. É apenas experiência de uso — a API aplica as mesmas regras.
- Filtros, ordenação e página da listagem ficam na URL (`/products?category=games&sort=price_desc&page=2`) e são processados pelo backend.

---

## Testes

```bash
make test                              # todos os testes
make test-filter FILTER=CheckoutTest   # apenas um arquivo/teste
```

Os testes usam um banco PostgreSQL separado (`ecommerce_testing`, criado automaticamente pelo container `db`), porque recursos como `lockForUpdate()` e `to_char()` são específicos do PostgreSQL. A fila roda em modo `sync` nos testes.

### Integração contínua (GitHub Actions)

O workflow [`.github/workflows/ci.yml`](.github/workflows/ci.yml) roda em todo pull request e em todo push na `main`. Ele usa os **mesmos serviços do Docker Compose e os mesmos alvos do `Makefile`** do ambiente local, então o CI testa na mesma imagem PHP e na mesma versão do PostgreSQL que o `make test`:

1. cria os `.env` a partir dos exemplos (`make env-files`);
2. constrói a imagem PHP e sobe `db`, `redis` e `api`;
3. instala as dependências do Composer (com cache por `composer.lock`) e gera a `APP_KEY`;
4. verifica o estilo com o Pint (`pint --test`);
5. roda toda a suíte (`make test`), inclusive os testes de arquitetura que verificam as fronteiras entre módulos.

Se algum passo falhar, os logs dos containers `db` e `api` aparecem no próprio run. Um push novo na mesma branch cancela o run anterior que ainda estiver em andamento.

O frontend ainda não é verificado no CI.

| Arquivo | Cobertura |
| --- | --- |
| `Auth/AuthTest` | cadastro sempre `customer`, login, logout, `me`, credenciais inválidas |
| `AuthorizationTest` | `401`/`403` nas rotas admin, pedido de outro cliente, admin não compra |
| `ProductCatalogTest` | listagem com inativos, filtro, ordenações, paginação, detalhe indisponível |
| `CartValidationTest` | preços recalculados, problemas por item |
| `CheckoutTest` | pedido + decremento + snapshot, rollback com `409`, cenário 5/4/3, uso de `FOR UPDATE`, `CHECK` no banco |
| `OrderStatusFlowTest` | listeners de cada evento, transições, entrega agendada só após `OrderPaid`, job com delay, idempotência, ciclo completo |
| `PaymentTest` | `202` + evento, `409` fora de `awaiting_payment`, `403` de outro cliente |
| `Admin/*` | categorias (slug), produtos, estoque, clientes, pedidos e dashboard |
| `Account/AccountTest` | resumo e edição de perfil/senha |
| `Models/CustomerTest` | comprador lido a partir da conta, projeção somente leitura |
| `Unit/Architecture/ModuleBoundariesTest` | fronteiras entre módulos: estoque só pelos contratos, Identity sem pedidos, Payment e Fulfillment sem `OrderRepository` e sem se conhecerem, Shared sem módulos de negócio |
| `SeederTest` | quantidades mínimas exigidas e determinismo dos seeders |

---

## Containers

| Container | Imagem | Função |
| --- | --- | --- |
| `nginx` | `nginx:alpine` | Ponto único de entrada (`:8080`). Roteia `/api` e `/sanctum` para o PHP-FPM e o resto para o Vite (inclui WebSocket do HMR) |
| `api` | `docker/php/Dockerfile` (PHP 8.5-FPM) | Laravel. Instala as dependências do Composer na primeira execução |
| `queue-worker` | mesma imagem da `api` | `php artisan queue:work redis` — processa listeners e jobs |
| `frontend` | `docker/node/Dockerfile` (Node 24) | Servidor de desenvolvimento do Vite |
| `db` | `postgres:18-alpine` | Banco principal (`ecommerce`) e de testes (`ecommerce_testing`) |
| `redis` | `redis:8-alpine` | Fila, sessões e cache |

O código do backend e do frontend é montado como volume: alterações aparecem imediatamente (HMR no frontend; o PHP-FPM lê os arquivos a cada requisição). O worker precisa de `make queue-restart` após mudanças em jobs/listeners.

---

## Fluxo completo para demonstração

1. Entre como **admin** (`admin@example.com` / `password`) → abre o `/admin` com o dashboard.
2. **Categorias** → crie uma categoria (o slug é gerado automaticamente).
3. **Produtos → Novo produto** → preencha os dados, selecione a categoria e defina o estoque inicial.
4. Abra a loja (`/products`): o produto aparece; filtre pela categoria e ordene por preço.
5. Saia e **crie uma conta** de cliente em `/register` (ou use `cliente@example.com`).
6. Adicione produtos ao carrinho e vá para o **checkout**: o backend valida o estoque.
7. **Confirme a compra**: a transação bloqueia e reduz o estoque e cria o pedido.
8. Na página de pagamento, o status passa de *Pedido efetuado* para *Aguardando pagamento* (listener na fila).
9. Clique em **Aprovar pagamento**: o pedido vai para *Pagamento aprovado* e, após ~10 s, para *Pedido entregue* (job com delay). A timeline atualiza sozinha.
10. Veja o histórico em **Minha conta → Meus pedidos**.
11. Entre novamente como admin: o pedido aparece em **Pedidos** e as métricas do dashboard foram atualizadas.

Dica: deixe `make logs-worker` aberto em outro terminal para ver os jobs sendo processados.

---

## Escopo e decisões

Fora do escopo por definição: frete, cupons, descontos, endereços, gateway de pagamento real, nota fiscal, wishlist, avaliações, chat, marketplace, multi-tenant, Elasticsearch, microsserviços e Kubernetes. Também não há upload de imagens: os produtos guardam apenas uma URL fake do `picsum.photos`.

Decisões relevantes:

- **Dinheiro**: `decimal` no banco, strings na API e `BcMath\Number` nos cálculos do backend; no frontend os valores são convertidos para centavos inteiros apenas para exibição.
- **Estoque separado do produto**: a edição de produto (`PUT /api/admin/products/{id}`) não altera estoque; ajustes passam por `/api/admin/stocks/{id}`.
- **Administradores** são criados apenas pelo seeder; a tela de clientes só cria/edita clientes.
- **Módulos** (`app/Modules/<Módulo>`): cada módulo repete a mesma estrutura interna, só com as pastas de que precisa:

  ```
  Modules/Ordering/
    DTOs/  Enums/  Events/  Exceptions/  Listeners/  Models/  Policies/
    Repositories/  Services/  UseCases/
    Http/Controllers/(Admin/)  Http/Requests/(Admin/)  Http/Resources/
  ```

  - **`Admin/`** separa o que pertence à área administrativa, como `Catalog\Http\Controllers\ProductController` (vitrine) e `Catalog\Http\Controllers\Admin\ProductController` (cadastro).
  - **Ligações explícitas:** models e factories se declaram por `#[UseFactory]` / `protected $model`, e as policies por `#[UsePolicy]`. Assim não dependem mais da convenção de namespace `App\Models`.
  - **Rotas:** ficam centralizadas em `routes/api.php`, que importa cada controller pelo nome completo, para manter num só lugar o mapa da API e os grupos de middleware (`auth:sanctum`, `admin`).
  - **Testes:** continuam em `backend/tests`, organizados por tipo (Unit/Feature). Só os namespaces importados mudaram.
- **Contratos do estoque**: o catálogo e o checkout não usam o `StockRepository` diretamente, e sim dois contratos em `App\Modules\Inventory\Contracts`:
  - `StockInitializer::createForProduct()`, usado pelo `CreateProductUseCase` para abrir o estoque de um produto novo.
  - `StockReservation::lockForProducts()` / `decrement()`, usados pelo `PlaceOrderUseCase` para bloquear e debitar o estoque.
  - Os dois são implementados pelo próprio `StockRepository` e ligados no `InventoryServiceProvider` (`$bindings`). Por isso a transação e o `FOR UPDATE` continuam idênticos.
  - O teste de arquitetura `ModuleBoundariesTest` falha se qualquer outro módulo (exceto o read model do Backoffice) usar o `StockRepository`.
- **Usuário x cliente**: a mesma tabela `users` tem dois modelos com papéis distintos.
  - `User` é a **identidade** (login, senha, papel e perfil) e não conhece pedidos.
  - `Customer` é o **comprador visto pelo lado de pedidos**: projeção somente leitura com `id`, `name` e `email`, que lança `LogicException` se alguém tentar gravar por ela. O pedido aponta para ele em `Order::customer()`.
  - O `OrderRepository` filtra pedidos pelo id do cliente (`paginateForCustomer`, `recentForCustomer`, `countForCustomer`, `countPerCustomer`), sem receber o `User`.
  - A tela **Clientes** do admin junta as duas partes no `CustomerSummaryDTO` / `CustomerSummaryResource`, montados pelos casos de uso `ListCustomersUseCase` e `ShowCustomerUseCase`. A listagem faz sempre 2 consultas por página (contas e, depois, a contagem de pedidos agrupada).
  - A coluna continua `orders.user_id`, porque o cliente é identificado pelo id da conta.
- **Pagamento recusado** não foi implementado (era opcional), mantendo os quatro status de negócio da especificação.
