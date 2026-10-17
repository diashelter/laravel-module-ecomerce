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
| `.env.example` e `backend/.env.example` | Variáveis de ambiente novas ou alteradas |
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
- **Módulos do backend:** todo código de negócio fica em `backend/app/Modules/<Módulo>` (Catalog, Inventory, Ordering, Payment, Fulfillment, Identity, Customers, Backoffice, Shared). Antes de criar uma classe, decida a qual contexto ela pertence ([análise de domínio](docs/domain-analysis.md)). Não recrie pastas por camada na raiz de `app/`.
- **Camadas dentro de cada módulo** (ver o README): controllers finos, casos de uso em `UseCases/`, regras puras em `Services/` (sem banco, transação ou eventos), escrita e acesso a dados em `Repositories/`, que não usam os services do módulo, e leituras que servem uma rota ou um contrato em `Queries/`, que não usam os `Repositories` nem os `UseCases` do módulo (o `ModuleBoundariesTest` cobra).
- **Ligações explícitas:** um model novo declara a sua factory com `#[UseFactory]` (e a factory declara `protected $model`). As convenções de namespace do Laravel (`App\Models`) não valem para os módulos.
- **Fronteiras entre contextos** (ver a [análise de domínio](docs/domain-analysis.md)):
  - **A facade de um módulo é o seu `Contracts/` mais os seus `Events/`.** Um módulo só usa outro por eles; as demais pastas (`Models`, `Repositories`, `Services`, `UseCases`, `Jobs`, `Consumers`, `Http`, uma pasta nova...) e o service provider são privados.
  - **De `DTOs`, `ValueObjects` e `Enums`, só o vocabulário é público:** as classes que os tipos nativos das assinaturas de `Contracts` e `Events` do próprio módulo alcançam (parâmetros, retornos e propriedades públicas, o construtor incluído), de forma transitiva. O `ModuleBoundariesTest` calcula isso a cada execução: para tornar um tipo público, ele precisa aparecer numa assinatura real de contrato ou evento do módulo dono (nunca crie um método só para isso), e um tipo citado só em docblock continua privado.
  - **O `Http` de cada módulo é privado.** Um módulo que precisa de uma regra de validação, de um trait de normalização ou de um resource de outro tem o seu adaptador no próprio `Http`, que só traduz o que o value object ou o enum decide, sem escrever limite, expressão regular ou lista de valores (o `HttpAdaptersTest` cobra os do Customers e do Fulfillment).
  - Contratos e eventos falam em **dados** (ids, value objects, enums), nunca em models ou coleções do Eloquent. Um contrato só existe para uma travessia real e leva o nome do papel de que o chamador precisa. Um contrato de leitura é implementado por uma consulta do módulo dono (`Queries/`), e um contrato que grava ou trava (`StockInitializer`, `StockReservation`) pelo repositório dele; os dois são ligados no service provider do dono. Quando o fornecedor já depende do consumidor, o consumidor define o contrato.
  - Fora do Identity, o cliente autenticado é um id (o `getAuthIdentifier()` do `Authenticatable` do framework).
  - **Nenhuma rota resolve registro.** Nem route model binding implícito, nem binding explícito de parâmetro. O controller recebe o parâmetro como `string $product`, `string $order` e assim por diante, e o id segue até quem o resolve: o use case (escrita) ou o repositório ou a consulta (leitura), e o contrato do dono quando o registro é de outro módulo. Use cases e repositórios recebem ids, nunca models vindos da rota. O corpo é validado antes da existência: corpo inválido é `422` para qualquer id, e corpo válido com id desconhecido é `404`. Uma exclusão ou um ajuste vai direto à escrita (ou à leitura com lock), sem ler o registro antes.
  - **O id é um UUID v7 em texto, na forma canônica** (minúsculas, com hífens), declarada uma vez em `Shared\Support\RecordId`. Todo parâmetro de rota de `/api` tem o `Route::pattern` dessa expressão no `AppServiceProvider` (um id fora dela é `404` sem query), e todo id do corpo passa pela regra `CanonicalRecordId` com `bail` antes de qualquer `exists`: o PostgreSQL responde com erro, e não com "nenhuma linha", a um texto que não é UUID. Nenhum `int` chamado `id` ou terminado em `Id` existe em `Modules`.
  - **O dono entra na busca.** Nas rotas do cliente com registro (pedido, pagamento e endereço), o repositório busca com `customer_id` (`PayableOrders::findForPayment($customerId, $orderId)`). O registro de outro cliente responde `404`, com o corpo de um id desconhecido, antes de qualquer conferência de status. Não crie policy que só confira o dono nem responda `403` ao lado da busca: ela confirmaria a quem não é dono que o registro existe.
  - **O código do pedido (`code`) é só para pessoas lerem.** Nenhum contrato o recebe, nenhum evento o carrega e nenhuma URL o usa: URLs, contratos e eventos continuam com o id.
  - O `ModuleBoundariesTest` e o `RouteBindingTest` (`tests/Unit/Architecture`) cobram essas regras. O `RouteBindingTest` percorre `Route::getRoutes()` e falha se uma rota de `/api` tiver parâmetro de ação que implemente `UrlRoutable`, binding explícito ou um parâmetro sem o padrão canônico.
  - **Não há exceção de fronteira.** O `ModuleBoundariesTest` não tem lista de travessias permitidas, e não se cria uma para fazer o teste passar. Uma leitura nova que junta módulos é uma **consulta** do módulo que serve a rota, que pede ao dono, pelo contrato dele, o que não é seu.
  - **Consultas** (HEL-6, ver o [design](.design/cqrs-read-side.md)): a pasta `Queries/` do módulo é privada. Uma consulta lê só as tabelas do próprio módulo; o que é de outro módulo ela pede ao dono pelo contrato dele, por ids e uma vez para a página inteira, para o tamanho da página nunca mudar o número de queries. Nenhuma assinatura pública de consulta declara model nem coleção do Eloquent. Nenhuma relação do Eloquent atravessa módulo. Um caso de uso de escrita devolve um value object com os dados do que gravou (`ProductRecord`, `StockRecord`), e a consulta completa a resposta sem reler o registro.
  - **A regra "pode ser comprado"** (produto ativo e com unidades) mora só no `ProductAvailability` (Catalog). O Ordering só monta as mensagens do carrinho e do checkout sobre ela; nenhuma outra classe combina o status com as unidades.
  - Fora do próprio estoque, use os contratos de `App\Modules\Inventory\Contracts` (`StockInitializer`, `StockLevels`, `StockMetrics`, `StockReservation`), nunca o `StockRepository`.
  - Só a parte de pedidos altera o status do pedido. Pagamento e entrega apenas publicam eventos (`PaymentApproved`, `OrderDelivered`).
- **Eventos pelo outbox transacional** (HEL-9, ver o README e o [design](.design/transactional-outbox.md)):
  - **Todo evento é gravado pelo `EventOutbox` (`Shared\Messaging`), dentro da transação da mudança que ele anuncia.** Nunca dispare um evento pelo dispatcher do Laravel (o trait `Dispatchable` de eventos, a interface de despacho depois do commit, a facade `Event`, o helper `event()`) nem "atualize e depois dispare": gravar sem transação aberta lança `LogicException`, e o `DomainEventsTest` recusa o despacho direto em `App\Modules`. Um caso de uso sem estado próprio abre uma transação só para a mensagem, e um job que depende do evento entra na fila depois do commit dele.
  - **A tabela é `outbox_messages`**: `id` (UUID v7 da aplicação, também o id da mensagem), `name`, `version`, `payload` (`jsonb`), `request_id`, `occurred_at` e `published_at` (nulo enquanto pendente), sem chave estrangeira. As publicadas ficam 7 dias; as pendentes nunca são apagadas.
  - **Todo evento de módulo implementa `DomainEvent`**: o nome estável `<módulo>.<evento em snake_case>` (nunca a classe PHP), a versão (um inteiro a partir de 1), `toPayload()` com só escalares ou nulo em chaves snake_case (ids em texto, datas em `Y-m-d`) e `fromPayload()`. Mudar um payload é subir a versão; o `DeliverEvent` recusa uma versão que a classe não declara.
  - **O relay publica pela porta `EventTransport`** (`publish(EventEnvelope)`: retornar é aceitar, lançar exceção é recusar). Casos de uso, eventos e consumidores não sabem qual é o transporte; um transporte novo é uma implementação nova, ligada no `AppServiceProvider`.
  - **O consumidor fica em `<Módulo>/Consumers`**, implementa `EventConsumer` e tem um único `handle()` que recebe a classe do evento. O módulo que consome o inscreve no `boot()` do seu service provider (`$subscriptions->subscribe(OrderPaid::class, ScheduleOrderDelivery::class)`), e o produtor nunca lista os consumidores. Cada consumidor recebe a sua entrega (`DeliverEvent`, 3 tentativas), ao menos uma vez: ele precisa ser idempotente.
  - O `request_id` da requisição vai na mensagem e volta ao `Context` do consumidor; não o passe à mão.
  - O `User` é identidade e não conhece pedidos. O lado de pedidos usa o `Customer`, que é somente leitura.
  - O Catalog não usa models nem repositories do Ordering. Quando precisar de algo dos pedidos, use o contrato que ele mesmo define (`ProductOrderHistory`), implementado pelo Ordering.
  - O módulo `Shared` é só infraestrutura e não depende de nenhum módulo de negócio.
  - O teste de arquitetura `ModuleBoundariesTest` cobra essas regras: a privacidade das pastas é gerada a partir das pastas que existem, a do vocabulário a partir das classes que existem e das assinaturas, e as regras de direção são escritas uma a uma. Ao criar uma regra nova, use **um namespace por expectativa**: com uma lista de namespaces, o `not->toUse` do Pest nunca falha. Confirme também que a regra falha com uma violação de propósito.
- **Testes de carga** (HEL-14, ver o [README da pasta](load-tests/README.md)): a pilha de carga é o projeto Compose `ecommerce-load` e compartilha com a de dev a árvore `backend/`. Nada rodado nos containers de carga pode gravar ali um arquivo que a API de dev lê: os caches ficam em `/tmp/laravel` (`APP_CONFIG_CACHE`, `APP_ROUTES_CACHE`, `APP_EVENTS_CACHE`) e os logs vão para `stderr`. Nunca rode `php artisan optimize` nesses containers: o cache iria para `bootstrap/cache`, e a API de dev passaria a ler o banco de carga. Uma variável nova que separa as duas pilhas entra no `environment` do Compose de carga, porque a variável de ambiente prevalece sobre o `backend/.env`. O k6 só fala HTTP, como o SPA: nada de rota só de teste, acesso ao banco ou atalho de autenticação.
- **Limites de requisição:** todo `throttle` de rota nomeia o seu contador no terceiro parâmetro (`throttle:10,1,customer-login`), com um nome que nenhuma outra rota usa. Sem o nome, a chave é só o cliente, e as rotas com limite dividiriam um contador (o `RateLimitTest` cobra). O alias `throttle` é o `AtomicThrottleRequests` (Shared), que conta a requisição antes de decidir, sobre o `AtomicRateLimiter`, que incrementa num só comando do Redis. Não volte ao `ThrottleRequests` do framework nem use o `throttleWithRedis()`: os dois conferem antes de contar, e uma rajada simultânea passa do limite (o `RateLimitBurstTest` cobra).
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

**Num `git worktree`**, o `make test` já roda o `backend/` do próprio worktree, num container `api` avulso e no banco `ecommerce_testing_<nome do worktree>`, criado na primeira execução; duas suítes em worktrees diferentes podem rodar ao mesmo tempo (ver [Um banco de testes por checkout](README.md#um-banco-de-testes-por-checkout)). O `make test-filter`, o `make pint`, o `make shell`, o `make artisan`, o `make test-frontend` e o `make frontend-shell` também abrem o código do worktree. Não rode esses comandos pelo `docker compose exec`: os containers de dev montam o `backend/` e o `frontend/` da pasta principal. O `make artisan` do worktree fala com o banco e o Redis de dev: um `migrate` ali altera o banco que a pasta principal serve.

Depois de alterar consumidores, jobs ou o relay do outbox, reinicie o worker e o relay:

```bash
make queue-restart
```

Depois de alterar uma rota, um status, um payload ou os scripts de `load-tests/`, rode o smoke de carga:

```bash
make load-test TYPE=smoke
```

O CI (`.github/workflows/ci.yml`) roda o `make pint`, o `make test` e o smoke de carga em todo pull request, com o mesmo Docker Compose do ambiente local. Rode os três localmente antes de abrir o PR: o CI só confirma.

### Checklist final

- [ ] Os testes passam (`make test`).
- [ ] O estilo está correto (`make pint`).
- [ ] O smoke de carga passa (`make load-test TYPE=smoke`), se a mudança tocou uma rota, um payload ou `load-tests/`.
- [ ] Se mudou o ambiente (Dockerfile, `docker-compose.yml`, `Makefile`, `.env.example`), o workflow de CI continua coerente com ele.
- [ ] O README descreve o comportamento atual.
- [ ] A análise de domínio reflete o estado atual dos contextos.
- [ ] Nenhuma menção a nomes antigos ficou para trás.
