# Storefront Read Model checks

Mutations: 3
Build: `make pint`
Plan: `.specs/features/storefront-read-model/plan.md`

77 checks em 7 slices · 9 one-way doors · 0 open

**Notação das provas**

- **`make test-filter FILTER="<regex>"`:** roda no checkout da construção, sobre o banco de testes do checkout (`ecommerce_testing`, ou `ecommerce_testing_<worktree>`) e o banco do MongoDB de mesmo nome. O arquivo citado depois do comando é onde o caso mora; um caso novo é escrito antes do código que o satisfaz, a partir deste texto, nunca lendo a implementação.
- **Suíte de composição** (`backend/tests`): os casos que precisam do Catalog com o Inventory, o Ordering ou o Identity. **Testes do Catalog** (`backend/app-modules/catalog/tests`): o projetor, a leitura da vitrine e o comando de esvaziar, só com o Catalog, o `Shared` e o `Messaging`, montando os documentos por entregas pelo `EventDelivery` (a leitora `Modules\Catalog\Core\InboundEvents\StockChanged` faz o papel do evento de estoque).
- **`sh docker/tests/read-model.sh --only <caso>`:** arquivo novo, no formato do `docker/tests/environment.sh`. Os casos que sobem ou param containers precisam da pilha de dev no ar (`make up`) e a deixam como encontraram.
- **`sh load-tests/tests/<arquivo> ...`:** os scripts e casos de prova da pilha de carga, no formato de hoje (`k6.sh <arquivo>.test.js --only <caso>`, `make.sh`, `stack.sh`, `dataset.sh`, `docs.sh`, `run.sh`, `assert-check.sh`, `assert-metric.sh`).
- **Provas de script da feature:** em `.specs/features/storefront-read-model/proofs/`; cada uma sai com 0 só quando a afirmação vale.
- **Execuções simultâneas:** processos do `pcntl_fork`, liberados no mesmo instante, como o `simultaneousOutcomes()` do `RateLimitBurstTest`, com dados gravados de verdade (fora da transação do `RefreshDatabase`) e apagados no fim do caso.
- **Base da feature:** `82337f0`.
- **Contagem de mutações:** 3, o padrão (o `AGENTS.md` não declara outra). Para 77 checks e uma porta de concorrência, é pouco; diashelter pode subir até 5.

## Checks

### S1 - Eventos `catalog.*` e `inventory.stock_changed`

**C1** - Depois de um `POST /api/admin/products` com `201`, a linha de `products` tem `version` 1, a de `stocks` tem `version` 1, e `outbox_messages` tem exatamente um `catalog.product_created` versão 1 com as chaves `product_id`, `name`, `price_cents`, `description`, `image_url`, `status`, `category_ids`, `created_at`, `updated_at` e `version` (nem mais, nem menos), os valores gravados e `version` 1, e exatamente um `inventory.stock_changed` versão 1 com `stock_id` (o id da linha de `stocks`), `product_id`, `quantity` (a quantidade de abertura) e `version` 1. Exemplos, um caso cada: `image_url` nulo no corpo → o payload leva a URL padrão que o produto gravou, e a versão continua 1; `category_ids` vazio → `[]`; duas categorias → os dois ids em ordem crescente (AC 1, porta 1, porta 3)
Proof: `make test-filter FILTER="records the product creation and the opening stock at version 1"`

**C2** - Um `PUT /api/admin/products/{product}` com `200` soma 1 a `products.version` e grava um `catalog.product_updated` com o produto inteiro como ficou na linha e a versão nova. Exemplos: a edição de todos os campos; a edição só das categorias → o `category_ids` novo e a versão mais 1; duas edições seguidas → versões 2 e 3, nessa ordem (AC 2)
Proof: `make test-filter FILTER="records the whole product at the next version on an edit"`

**C3** - Um `PATCH /api/admin/products/{product}/status` com `200` soma 1 a `products.version` e grava um `catalog.product_status_changed` com as dez chaves da porta 1, com `name`, `price_cents`, `description`, `image_url`, `category_ids` e `created_at` iguais aos da linha, e não só o `status` (AC 3)
Proof: `make test-filter FILTER="records the whole product at the next version on a status change"`

**C4** - Um `DELETE /api/admin/products/{product}` com `204` grava um `catalog.product_deleted` com exatamente `product_id` e `version` (a versão guardada mais 1) e nenhum `inventory.stock_changed`, embora a linha de `stocks` saia pela cascata (AC 4)
Proof: `make test-filter FILTER="records the product deletion at the stored version plus one and no stock change"`

**C5** - Um `POST /api/admin/categories` com `201` grava `catalog.category_created` com `category_id`, `name`, o `slug` gravado e `version` 1, e `categories.version` é 1; um `PUT /api/admin/categories/{category}` com `200` que renomeia grava `catalog.category_updated` com o `slug` regenerado e a versão mais 1, igual à da linha (AC 5)
Proof: `make test-filter FILTER="records the category creation at version 1 and the rename at the next version"`

**C6** - Um `DELETE /api/admin/categories/{category}` de uma categoria sem produtos, com `204`, grava um `catalog.category_deleted` com exatamente `category_id` e `version` (a versão guardada mais 1) (AC 6)
Proof: `make test-filter FILTER="records the category deletion at the stored version plus one"`

**C7** - Um `PUT /api/admin/stocks/{stock}` com `200` soma 1 a `stocks.version` e grava um `inventory.stock_changed` com a quantidade gravada, nunca as unidades ajustadas: `increase` 3 sobre 7 → `quantity` 10; `decrease` 7 sobre 7 → `quantity` 0 (AC 7)
Proof: `make test-filter FILTER="records the stored quantity at the next version on a stock adjustment"`

**C8** - Um `POST /api/orders` com `201` que compra 3 unidades de um produto com 10 e 1 de um com 5 grava, na transação do checkout, exatamente dois `inventory.stock_changed`, com `quantity` 7 e 4 e a versão de cada estoque mais 1. O caso `keeps the query count of the checkout` muda com esta afirmação: as únicas consultas que ele ganha são as das mensagens de estoque, antes do `ordering.order_placed` (AC 8)
Proof: `make test-filter FILTER="records one stock change per product of the checkout with the quantity left"`
Proof: `make test-filter FILTER="keeps the query count of the checkout"`

**C9** - Uma escrita recusada responde como hoje, não grava mensagem nenhuma em `outbox_messages` e não muda nenhuma `version`. Table-driven sobre 5: a exclusão de uma categoria com produtos; o ajuste que deixaria o estoque negativo (`422` `O estoque não pode ficar negativo.`); o checkout de um produto inativo; o de um produto sem estoque; o checkout com estoque insuficiente (`409`) (AC 9)
Proof: `make test-filter FILTER="records nothing and keeps every version when a write is refused"`

**C10** - O checkout que falha na terceira colisão do código do pedido, depois de baixar o estoque, não deixa nenhum `inventory.stock_changed` em `outbox_messages` e deixa a `version` e a `quantity` dos estoques como estavam (AC 10)
Proof: `make test-filter FILTER="keeps no stock change and no version of a checkout that fails on the third code collision"`

**C11** - Cada escrita que muda a vitrine trava a linha antes de calcular a versão: no log de consultas, a primeira consulta que toca a linha é um `select ... for update` e vem antes do `update` que soma 1 a `version`. Table-driven sobre 7: edição do produto, mudança de status, exclusão do produto, renomeação da categoria, exclusão da categoria, ajuste de estoque e checkout (AC 11, porta 3)
Proof: `make test-filter FILTER="locks the row before computing the version"`

**C12** - Uma mudança de status e uma edição do mesmo produto, em execuções simultâneas repetidas 20 vezes, sempre deixam versões estritamente crescentes em `outbox_messages` para aquele produto, sem versão repetida, e o evento de maior versão carrega exatamente o estado que a linha de `products` guarda no fim (AC 11)
Proof: `make test-filter FILTER="gives concurrent product writes increasing versions and the final row to the newest event"`

**C13** - Um checkout e um ajuste do mesmo estoque, em execuções simultâneas repetidas 20 vezes, sempre deixam versões estritamente crescentes para aquele estoque, sem versão repetida, e o `inventory.stock_changed` de maior versão carrega a `quantity` que a linha de `stocks` guarda no fim (AC 11)
Proof: `make test-filter FILTER="gives a concurrent checkout and stock adjustment increasing versions and the final quantity to the newest event"`

**C14** - O `EventOutbox` grava um payload com `['a', 'b']`, com `[]` e com uma data em texto ISO 8601 com o fuso (`2026-10-27T13:45:00+00:00`), e recusa com `InvalidArgumentException`, sem gravar nada, `[['a']]`, `['k' => 'v']` e `new stdClass`. O caso `refuses a payload value that is not a scalar or null` sai com esta afirmação, e o `refuses a resource in the payload` fica (AC 12, porta 2)
Proof: `make test-filter FILTER="accepts a flat list and an ISO 8601 date in the payload"`
Proof: `make test-filter FILTER="refuses an associative array, a nested array and an object in the payload"`
Proof: `make test-filter FILTER="refuses a resource in the payload"`

**C15** - Os casos nomeados em `proofs/c15-unchanged-cases.sh` estão byte a byte iguais aos da base `82337f0` e passam: os nomes, versões e payloads dos 5 eventos de hoje (`declares the name and version of every order event`, `maps every order event payload and rebuilds it`), os consumidores de hoje no `ConsumerRedeliveryTest`, o carrinho (todo o `CartValidationTest`), as recusas e o lock do checkout (`returns 409 and changes nothing when stock is insufficient`, `rejects inactive and out of stock products`, `never lets stock go negative (spec scenario: stock 5, buy 4 then 3)`, `locks the stock rows with SELECT ... FOR UPDATE`), a lista do admin (`lists the admin products newest first fifteen per page`) e a flag de transporte (`EventTransportFlagTest`) (AC 13)
Proof: `sh .specs/features/storefront-read-model/proofs/c15-unchanged-cases.sh`

**C16** - A leitora `Modules\Catalog\Core\InboundEvents\StockChanged` lê o payload que `Modules\Inventory\Events\StockChanged::toPayload()` grava nos mesmos `stock_id`, `product_id`, `quantity` e `version`, com o mesmo nome e a mesma versão, e a suíte de composição falha com uma leitora que discorda do produtor (AC 14)
Proof: `make test-filter FILTER="reads the stock change with the catalog reader into the same values"`
Proof: `make test-filter FILTER="detects a reader that disagrees with its producer"`

**C17** - As migrations `2026_10_27_000001_add_version_to_products_and_categories_tables` (em `app-modules/catalog/database/migrations`) e `2026_10_27_000002_add_version_to_stocks_table` (em `app-modules/inventory/database/migrations`) existem, o `ModuleMigrationsTest` congela 18 nomes, e `products.version`, `categories.version` e `stocks.version` são `bigint`, `NOT NULL`, com default 1; uma linha que já existia antes do `up()` fica com `version` 1 (porta 3)
Proof: `make test-filter FILTER="keeps the eighteen frozen migration names"`
Proof: `make test-filter FILTER="places each module migration in its owner package"`
Proof: `make test-filter FILTER="adds the version column to products, categories and stocks"`
Proof: `make test-filter FILTER="gives version 1 to the rows that existed before"`

### S2 - ProductDocument e o projetor

**C18** - Um evento de produto com versão maior que a da parte guardada, ou sem parte guardada, substitui a parte `product` inteira pelos valores do payload e pela versão dele, e cria o documento quando ele não existe. Table-driven sobre os 3 eventos de produto; caso forte: a parte guardada com `description` `"x"` e o evento novo com `description` nulo → `null`, e um campo da parte que o evento não traz não sobra (AC 15)
Proof: `make test-filter FILTER="replaces the product part with a newer product event"`

**C19** - Um evento de produto com versão menor ou igual à guardada, ou menor ou igual a `deleted_version`, é descartado sem mudar nada no documento. Table-driven: versão 2 guardada e versão 2 de novo → nada muda; versão 1 depois da 3 → nada muda; versão 3 depois da 2 → aplicado; lápide 4 e evento de produto versão 4 → nada muda (AC 16)
Proof: `make test-filter FILTER="discards a product event that is not newer"`

**C20** - Um `inventory.stock_changed` de um produto sem documento cria o documento com `product` nulo, `deleted_version` nulo e `stock` com exatamente `id`, `quantity` e `version` (AC 17)
Proof: `make test-filter FILTER="creates the document with only the stock part when the stock arrives first"`

**C21** - Um evento de estoque com versão maior que a guardada substitui a parte `stock` inteira; com versão igual ou menor, é descartado. Table-driven: maior → substituída; igual → nada muda; menor → nada muda (AC 18)
Proof: `make test-filter FILTER="replaces or discards the stock part by its version"`

**C22** - Um `catalog.product_deleted` grava `deleted_version` com a versão dele, criando o documento quando não existe; depois dele, um evento de produto com versão até `deleted_version` é descartado e um evento de estoque mais novo substitui a parte `stock` sem mudar `deleted_version`. Caso forte: `catalog.product_deleted` versão 4 antes do `catalog.product_created` versão 1 → o documento continua lápide, com `product` nulo (AC 19)
Proof: `make test-filter FILTER="keeps a deleted product as a tombstone"`

**C23** - Os eventos de categoria criam, substituem, descartam ou viram lápide o documento de `catalog_categories` pela mesma regra de versão (criada; renomeada com versão maior; renomeação mais velha descartada; excluída vira lápide; renomeação atrasada depois da exclusão descartada), e nenhum documento de `catalog_products` muda em nenhum dos casos (AC 20)
Proof: `make test-filter FILTER="projects categories by the same version rule and touches no product"`

**C24** - O mesmo envelope entregue duas vezes pelo `EventDelivery` ao consumidor do projetor muda o documento uma vez só (a segunda entrega deixa o documento igual ao da primeira) e não grava mensagem em `outbox_messages`. Table-driven sobre os 8 eventos e os seus consumidores (AC 21, AD-001)
Proof: `make test-filter FILTER="redelivers every storefront event to its projector"`

**C25** - Duas entregas da mesma parte com versões diferentes, em execuções simultâneas repetidas 20 vezes (com e sem o documento existente), sempre deixam o documento na versão maior, e nenhuma das duas entregas falha (AC 22)
Proof: `make test-filter FILTER="ends with the higher version when two deliveries race"`

**C26** - A gravação condicional que perde para uma versão mais nova conta como descarte e completa a entrega: uma entrega de versão 1 a um documento que outra escrita cria com versão 2 entre o filtro e o upsert (a chave duplicada do upsert) não lança exceção pelo `EventDelivery` e deixa a versão 2 (AC 22)
Proof: `make test-filter FILTER="counts a losing upsert as a discard"`

**C27** - Com o MongoDB sem responder (a conexão apontada para um endereço que não atende), a entrega pelo `EventDelivery` lança exceção, como a de qualquer consumidor, e o documento guardado continua igual depois que a conexão volta (AC 23)
Proof: `make test-filter FILTER="fails the delivery and keeps the document when MongoDB does not answer"`

**C28** - O `EventSubscriptions` tem exatamente 13 consumidores: os 5 de hoje e os 8 da porta 8 (`catalog.project_product_created`, `catalog.project_product_updated`, `catalog.project_product_status_changed`, `catalog.project_product_deleted`, `catalog.project_category_created`, `catalog.project_category_updated`, `catalog.project_category_deleted` e `catalog.project_stock_changed`), cada um com a sua classe em `Modules\Catalog\Core\Consumers` e o seu evento, e nenhum outro nome (AC 24, porta 8)
Proof: `make test-filter FILTER="subscribes the thirteen consumers under their stable names"`
Proof: `make test-filter FILTER="subscribes no consumer under any other name"`

**C29** - Só o `Modules\Catalog` nomeia a conexão `mongodb`, `catalog_products` e `catalog_categories`; dentro dele, só `Core\Consumers`, `Console` (o `catalog:read-model-clear`), `Persistence` e `Providers` alcançam a persistência do modelo de leitura, e a gravação só vem dos consumidores e do comando de esvaziar. A suíte de arquitetura passa com o código e falha com cada fixture de violação proposital: uma classe do Inventory que nomeia `catalog_products`, e um controller do `Http` do Catalog que grava no modelo de leitura (AC 25)
Proof: `make test-filter FILTER="keeps the read model inside the catalog"`
Proof: `make test-filter FILTER="fails on a deliberate read model violation"`

**C30** - Depois de um evento de cada tipo, o documento de `catalog_products` tem exatamente as chaves `_id`, `product`, `stock` e `deleted_version`; `product` tem exatamente `name`, `price_cents`, `description`, `image_url`, `status`, `category_ids`, `created_at`, `updated_at` e `version`, com as datas em texto; `stock` tem exatamente `id`, `quantity` e `version`; e o de `catalog_categories` tem exatamente `_id`, `name`, `slug`, `version` e `deleted_version`. Nenhum guarda `is_available`, `status_label` nem `available_quantity` (AC 25, porta 4)
Proof: `make test-filter FILTER="stores exactly the fields of door 4"`

### S3 - Reconstrução

**C31** - O alvo `storefront-rebuild` do `Makefile` roda `catalog:read-model-clear`, `catalog:republish` e `inventory:republish`, nessa ordem e só eles; nenhum arquivo de `app-modules/catalog` chama um comando `inventory:` e nenhum de `app-modules/inventory` chama um comando `catalog:` (AC 26, porta 7)
Proof: `sh docker/tests/read-model.sh --only make-rebuild`

**C32** - O `catalog:read-model-clear` sai com 0, imprime `Modelo de leitura esvaziado: catalog_products e catalog_categories.`, deixa as duas coleções com 0 documentos e com exatamente os índices da porta 4: em `catalog_products`, `product.category_ids`, (`product.name`, `_id`) e (`product.price_cents`, `_id`); em `catalog_categories`, `slug` não único (além do `_id`) (AC 27, porta 4)
Proof: `make test-filter FILTER="empties both collections and leaves the indexes of door 4"`

**C33** - O `catalog:republish` sai com 0, imprime `Republicados: <n> categorias e <m> produtos.` e grava exatamente um `catalog.category_updated` por categoria e um `catalog.product_updated` por produto, cada um com o estado atual e a `version` atual da linha (sem somar 1); o `inventory:republish` sai com 0, imprime `Republicados: <n> estoques.` e grava exatamente um `inventory.stock_changed` por linha de `stocks`, com a `quantity` e a `version` atuais (AC 28, porta 7)
Proof: `make test-filter FILTER="republishes every category and product with the current state and version"`
Proof: `make test-filter FILTER="republishes every stock row with the current quantity and version"`

**C34** - O `catalog:republish` lê cada produto e as suas categorias num só instantâneo: no log de consultas, a linha do produto e os ids das categorias vêm de uma só consulta, ou de consultas dentro de uma transação `REPEATABLE READ` aberta antes delas (AC 28)
Proof: `make test-filter FILTER="reads each product and its categories in one snapshot"`

**C35** - Um comando de republicação que falha no meio (o outbox recusa a gravação do terceiro registro) sai com código diferente de 0 e imprime `Republicados antes da falha: 2.`; rodado de novo, termina com 0, e depois das entregas a vitrine é igual à de uma reconstrução sem falha. Table-driven sobre `catalog:republish` e `inventory:republish` (AC 29)
Proof: `make test-filter FILTER="exits non-zero with the count recorded when the republish fails halfway and converges when run again"`

**C36** - As receitas `fresh` e `seed` do `Makefile` terminam com `$(MAKE) storefront-rebuild`, depois do comando de migração ou de seed (AC 30)
Proof: `sh docker/tests/read-model.sh --only make-fresh-seed`

**C37** - Com produtos, categorias e estoques gravados pelas rotas do admin e pelo checkout (pelo menos 14 produtos, para duas páginas), esvaziar o MongoDB, reconstruir e entregar as mensagens devolve, em `GET /api/products`, o mesmo corpo em todas as páginas de cada uma das 3 ordenações e com o filtro de uma categoria, e em `GET /api/products/{product}` o mesmo corpo para cada produto disponível e o mesmo status para os demais (AC 31)
Proof: `make test-filter FILTER="returns the same storefront after emptying MongoDB and rebuilding"`

**C38** - Documentos de `catalog_products` e `catalog_categories` cujos ids não existem no PostgreSQL (um banco anterior) não sobram depois da reconstrução (AC 32)
Proof: `make test-filter FILTER="leaves no document of a record missing from PostgreSQL after the rebuild"`

**C39** - Um envelope republicado entregue depois do envelope de uma escrita que gravou depois dele é descartado, e a vitrine mostra o estado da escrita; com as coleções esvaziadas e só as categorias entregues, `GET /api/products` responde `200` com `meta.total` 0, e com metade dos produtos entregue, `200` com essa metade (AC 33)
Proof: `make test-filter FILTER="keeps the written state when a republished envelope arrives after the write"`
Proof: `make test-filter FILTER="answers 200 with what was projected while the rebuild runs"`

### S4 - api-read

**C40** - Os casos da vitrine de `ProductCatalogTest`, `ProductResponseShapeTest`, `ApiErrorResponseTest`, `AuthorizationTest`, `MoneyInCentsTest` e `RequestIdTest` passam contra o modelo de leitura, e a diferença desses arquivos para a base `82337f0` é só: as linhas que chamam o helper de reconstrução no preparo; a saída do caso `reads the stock of the catalog page through the inventory` (C49 o substitui); e o caso `lists the catalog with the same query count for 1 and 12 products`, que passa a contar as leituras do MongoDB (C48). Nenhuma expectativa de status ou de corpo muda (AC 34)
Proof: `sh .specs/features/storefront-read-model/proofs/c40-storefront-tests.sh`

**C41** - Com 13 documentos com a parte do produto, 1 só com a parte do estoque e 1 lápide, `GET /api/products` responde `meta.total` 13, `meta.last_page` 2, `meta.per_page` 12, 12 itens na página 1 e 1 na página 2, nenhum item da lápide nem do documento só com estoque, e `GET /api/products?sort=price_asc&page=1` tem `links.next` com `sort=price_asc&page=2` (AC 35)
Proof: `make test-filter FILTER="lists only the documents with a product part and no tombstone twelve per page"`

**C42** - `category=<slug>` lista só os produtos cujo `category_ids` tem uma categoria sem lápide com aquele slug. Table-driven: o slug de uma categoria viva → os produtos dela; um slug que só uma lápide guarda → página vazia; um slug desconhecido → `200`, `data` vazio e `meta.total` 0; uma categoria nova que reusa o slug de uma lápide → só os produtos da nova (AC 36)
Proof: `make test-filter FILTER="filters by the slug of a category that is not a tombstone"`
Proof: `make test-filter FILTER="counts the filtered list in MongoDB and reads the same number of times for one and twelve products"`

**C43** - `sort=name` ordena por byte, com o id desempatando: `Abacaxi`, `Banana`, `Zebra`, `banana`, `caju`, `Água`, `éclair`, nessa ordem; `price_asc` e `price_desc` ordenam por `price_cents`, e dois produtos com o mesmo preço (e dois com o mesmo nome) saem com o menor id primeiro nas três ordenações; sem `sort`, a ordem é a de `name` (AC 37)
Proof: `make test-filter FILTER="sorts the storefront by name in byte order and by price with the id breaking ties"`

**C44** - `sort=random`, `page=0` e `page=abc` respondem `422` com o erro de validação no campo, como hoje (AC 38)
Proof: `make test-filter FILTER="refuses an invalid sort or page"`

**C45** - `GET /api/products/{product}`, table-driven: documento ativo com 3 unidades → `200` com `data.available_quantity` 3; inativo com unidades → `404` `Produto indisponível.`; ativo com 0 unidades → `404` `Produto indisponível.`; com a parte do produto e sem a do estoque → `404` `Produto indisponível.`; id desconhecido → `404` `Recurso não encontrado.`; lápide → `404` `Recurso não encontrado.`; só com a parte do estoque → `404` `Recurso não encontrado.` (AC 39)
Proof: `make test-filter FILTER="answers the product page from the document"`

**C46** - `GET /api/products/abc` responde `404` sem nenhuma leitura no MongoDB (AC 39)
Proof: `make test-filter FILTER="answers 404 to a non canonical id without reading MongoDB"`

**C47** - Um documento listado sem a parte do estoque sai com `stock` nulo, `is_available` falso e `available_quantity` 0; um produto que cita uma categoria que ainda não chegou e uma que virou lápide sai com só as categorias vivas em `categories`, na ordem de `category_ids` (AC 40)
Proof: `make test-filter FILTER="renders a document without the stock part as unavailable"`
Proof: `make test-filter FILTER="leaves out a category that has not arrived or is a tombstone"`

**C48** - `GET /api/products` faz o mesmo número de leituras no MongoDB (contadas pelo log de consultas da conexão `mongodb`) com 1 produto e com 12, cada um com 2 categorias, e esse número é maior que 0 (AC 41)
Proof: `make test-filter FILTER="reads MongoDB the same number of times for one and twelve products"`
Proof: `make test-filter FILTER="counts the filtered list in MongoDB and reads the same number of times for one and twelve products"`
Proof: `make test-filter FILTER="lists the catalog with the same query count for 1 and 12 products"`

**C49** - `GET /api/products`, com e sem filtro, e `GET /api/products/{product}` não fazem nenhuma consulta na conexão `pgsql` (AC 42)
Proof: `make test-filter FILTER="runs no PostgreSQL query on the storefront"`

**C50** - A disponibilidade é decidida na leitura: um documento `active` com 0 unidades sai listado com `is_available` falso e `status_label` `Ativo`; um `inactive` com 5 unidades sai listado com `is_available` falso e `available_quantity` 5; e a consulta da vitrine não filtra por `product.status` nem por `stock.quantity` (AC 43)
Proof: `make test-filter FILTER="decides availability when reading"`
Proof: `sh .specs/features/storefront-read-model/proofs/c50-no-rule-in-query.sh`

**C51** - Com a conexão `mongodb` apontada para um endereço que não atende, `GET /api/products` e `GET /api/products/{product}` respondem `503` com `{"code":"SERVICE_UNAVAILABLE","message":"Este recurso está indisponível no momento.","errors":{},"request_id":"<o X-Request-ID da resposta>"}` em menos de 5 segundos cada (AC 44)
Proof: `make test-filter FILTER="answers 503 within five seconds when MongoDB does not answer"`

### S5 - Gateway

**C52** - Na pilha de dev, com o `api` parado, `GET /api/products`, `GET /api/products?sort=price_desc&page=1`, `GET /api/products/{product}` de um produto disponível e `HEAD /api/products` respondem `200` pelo Nginx (AC 45)
Proof: `sh docker/tests/read-model.sh --only gateway-storefront`

**C53** - Na pilha de carga, depois do smoke, o `api-read` responde a vitrine: com o `api` parado, `GET /api/products` pelo Nginx responde `200` com `meta.total` 202 (AC 45)
Proof: `sh load-tests/tests/stack.sh --only gateway`

**C54** - Na pilha de dev, com o `api-read` parado, as outras requisições respondem como hoje pelo `api`: `GET /api/categories` → `200`; `GET /api/admin/products` sem sessão → `401`; `POST /api/cart/validate` com corpo vazio → `422`; `DELETE /api/products` → `405`; `GET /sanctum/csrf-cookie` → `204`; `GET /horizon` → um status diferente de `503` e `502` (AC 46)
Proof: `sh docker/tests/read-model.sh --only gateway-rest`

**C55** - Com o `api-read` parado e o Nginx já no ar, `GET /api/products` responde `503` com `Content-Type: application/json`, o corpo `{"code":"SERVICE_UNAVAILABLE","message":"Este recurso está indisponível no momento.","errors":{},"request_id":"<id>"}` e um `X-Request-ID` igual ao `request_id`; o bloco da vitrine no `docker/nginx/default.conf` e no `load-tests/docker/nginx.conf` tem um só `fastcgi_pass`, para `api-read`, e a página de erro dele não tem `fastcgi_pass` (AC 47)
Proof: `sh docker/tests/read-model.sh --only gateway-down`

**C56** - Com o `api-read` parado antes de o Nginx (re)iniciar, o Nginx sobe e `GET /api/products` responde `503` com o mesmo corpo (AC 47)
Proof: `sh docker/tests/read-model.sh --only gateway-cold`

**C57** - No `docker compose config` de dev e de carga, o `api-read` tem `DB_HOST`, `DB_USERNAME` e `DB_PASSWORD` vazios e não depende do `db`; com o `db` parado, `GET /api/products` pelo Nginx de dev responde `200` (AC 48, porta 6)
Proof: `sh docker/tests/read-model.sh --only api-read-no-db`

**C58** - Os dois blocos FastCGI do Nginx (de dev e de carga) incluem `fastcgi_params` e não sobrescrevem `REMOTE_ADDR`, e o Laravel não confia em proxy nenhum: um `X-Forwarded-For` mandado pelo cliente não muda o endereço (AC 49)
Proof: `sh docker/tests/read-model.sh --only client-address`

**C59** - O `TEST_API` do `Makefile` passa `-e MONGODB_DATABASE=$(TEST_DB_DATABASE)` (no checkout principal, `ecommerce_testing`; num worktree, `ecommerce_testing_<nome>`), e o `test-db-drop` apaga esse banco no MongoDB; na suíte, um teste de `Feature` começa com as duas coleções vazias mesmo depois de outro ter gravado nelas (AC 50)
Proof: `sh docker/tests/read-model.sh --only test-mongo-db`
Proof: `make test-filter FILTER="starts each test with an empty read model"`

**C60** - No `.github/workflows/ci.yml`, o job `backend` sobe `mongodb` no mesmo `docker compose up -d --wait` do `db`, do `redis` e do `rabbitmq`, e o job `load-smoke` roda `make load-test TYPE=smoke`, cujo `load-tests/docker-compose.yml` tem `mongodb` e `api-read` (AC 51)
Proof: `sh docker/tests/read-model.sh --only ci`

**C61** - O MongoDB como dependência: o `backend/composer.json` exige `mongodb/laravel-mongodb` `^5.7`; a imagem PHP tem a extensão `mongodb`; o `docker-compose.yml` de dev tem o serviço `mongodb` (`mongo:8.2`, com healthcheck e usuário raiz por `MONGO_INITDB_ROOT_USERNAME` e `MONGO_INITDB_ROOT_PASSWORD`), e o `api`, o `queue-worker`, o `outbox-relay`, o `event-consumer`, o `scheduler` e o `api-read` dependem dele saudável (porta 5)
Proof: `sh docker/tests/read-model.sh --only dev-services`
Proof: `sh docker/tests/read-model.sh --only php-extension`

**C62** - Na pilha de carga, os serviços são `api api-read db event-consumer k6 mongodb nginx outbox-relay queue-worker rabbitmq redis`, o `api`, o `queue-worker`, o `event-consumer` e o `api-read` têm `MONGODB_HOST` `mongodb`, e o `api-read` usa o mesmo `fpm-pool.conf` do `api` (porta 6)
Proof: `sh load-tests/tests/stack.sh --only connection`
Proof: `sh load-tests/tests/stack.sh --only fpm-pool`

### S6 - storefront_projection_lag

**C63** - Só o VU de posição 1 do cenário `backoffice` grava o preço do `Produto da Equipe`: a decisão devolve verdadeiro para ele em toda iteração e falso para os VUs 2 a 50 (AC 52)
Proof: `sh load-tests/tests/k6.sh projection.test.js --only first-vu-writes`

**C64** - O preço gravado é 1 centavo acima do último que o VU gravou, a partir do preço lido no `setup()`: 10000 → 10001 → 10002 (AC 52)
Proof: `sh load-tests/tests/k6.sh projection.test.js --only next-price`

**C65** - A espera dorme 250 ms e lê, a cada vez, até a vitrine mostrar exatamente o preço gravado: um preço diferente (mais velho ou maior) não encerra a espera, e a leitura que mostra o preço encerra com o tempo esperado (AC 52)
Proof: `sh load-tests/tests/k6.sh projection.test.js --only polls-until-exact-price`

**C66** - A amostra é o tempo, em ms, entre o `200` do `PUT` e a leitura que mostrou o preço, somada ao próprio `storefront_projection_lag` (o fluxo passa a métrica, e a prova confere a chamada exata ao `add`); o `PUT` vai a `/api/admin/products/{product}` e a leitura a `/api/products/{product}` (AC 52)
Proof: `sh load-tests/tests/k6.sh projection.test.js --only measures-from-the-put`
Proof: `sh load-tests/tests/k6.sh projection.test.js --only flow-wires-the-metric`

**C67** - Sem o preço em 60 s, a espera desiste e conta exatamente 1 erro em `unexpected_errors`; uma espera que vê o preço não conta nenhum (AC 53)
Proof: `sh load-tests/tests/k6.sh projection.test.js --only gives-up-after-60s`

**C68** - O resumo e o `results/<perfil>-checks.txt` mostram o p95 de `storefront_projection_lag` quando ele tem amostras, e as opções de `load`, `stress` e `soak` não têm limite em `storefront_projection_lag` (AC 54)
Proof: `sh load-tests/tests/k6.sh summary.test.js --only projection-lag-p95`
Proof: `sh load-tests/tests/k6.sh profiles.test.js --only no-projection-lag-threshold`

**C69** - O `make load-test` faz, nessa ordem: o seed, a subida do `queue-worker`, do `outbox-relay`, do `event-consumer`, do `api-read` e do `nginx`, o `storefront-rebuild` da pilha de carga, a espera até `GET /api/products` pelo Nginx informar `meta.total` 202 (desistindo com erro em 120 s), e só então o `k6 run` (AC 55)
Proof: `sh load-tests/tests/make.sh --only rebuild-before-k6`

**C70** - No último smoke, o check `setup: dataset has 202 products` passou (AC 55)
Proof: `sh load-tests/tests/assert-check.sh smoke --name "setup: dataset has 202 products"`

**C71** - O `LoadTestSeeder` cria exatamente um `Produto da Equipe`, ativo e com unidades, que nenhum fluxo de compra escolhe (não está em `data.available` nem é o `Produto Disputado`), e o conjunto tem 202 produtos (AC 56, porta 9)
Proof: `sh load-tests/tests/dataset.sh --only team-product`
Proof: `sh load-tests/tests/dataset.sh --only catalog`

**C72** - O `make load-test TYPE=smoke` sai com 0, passando pelo Nginx com o `api-read`, e o smoke tem pelo menos 1 amostra de `storefront_projection_lag` (AC 57)
Proof: `sh load-tests/tests/run.sh --only smoke`
Proof: `sh load-tests/tests/assert-metric.sh smoke --name storefront_projection_lag --at-least 1`

**C73** - O `load-tests/README.md` abre uma série nova nas duas tabelas da linha de base, com a frase que diz que a vitrine passou a ser servida pelo `api-read`, e descreve o `storefront_projection_lag` (o que mede, de onde a onde, a desistência em 60 s), o `Produto da Equipe` e os 202 produtos, sem nenhuma menção a 201 produtos (AC 58, porta 9)
Proof: `sh load-tests/tests/docs.sh --only read-model`

### S7 - Documentação

**C74** - O `README.md` descreve o modelo de leitura (as duas coleções e os campos), o projetor (os 8 consumidores e a regra de versão), a reconstrução (`make storefront-rebuild` e os três comandos), o `api-read`, o roteamento do gateway, o serviço `mongodb` e as variáveis `MONGODB_*`, os 8 eventos com os payloads, as colunas `version` e a regra nova do payload, e nenhuma frase do README diz que o payload tem só escalares (AC 59)
Proof: `sh .specs/features/storefront-read-model/proofs/docs.sh --only readme`

**C75** - O `.env.example` e o `backend/.env.example` listam as variáveis `MONGODB_*` da porta 5 (`MONGODB_HOST`, `MONGODB_PORT`, `MONGODB_DATABASE`, `MONGODB_USERNAME`, `MONGODB_PASSWORD` no do backend; as credenciais e o `MONGODB_FORWARD_PORT` no da raiz) (AC 59, porta 5)
Proof: `sh .specs/features/storefront-read-model/proofs/docs.sh --only env-examples`

**C76** - O `docs/domain-analysis.md` tem "modelo de leitura", "projetor" e "reconstrução" na linguagem ubíqua do Catalog, a matriz entre contextos sem o uso do `StockLevels` pela vitrine, a frase do Messaging com a regra nova do payload e uma linha "Última atualização" de 27/10/2026 ou depois (AC 60)
Proof: `sh .specs/features/storefront-read-model/proofs/docs.sh --only domain-analysis`

**C77** - O `AGENTS.md` traz a regra nova do payload (lista plana de escalares e datas ISO 8601), o `Console/` do Catalog e do Inventory, os eventos que carregam o estado com a versão e a regra de versão do projetor, que as consultas também leem as coleções do MongoDB do próprio módulo e que o `make test` e o CI precisam do `mongodb`; e nenhuma frase diz que só o `Messaging` tem `Console/` nem que o payload tem só escalares (AC 61)
Proof: `sh .specs/features/storefront-read-model/proofs/docs.sh --only agents`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| `GET /api/products` statuses (3) | 200 C41 · 422 C44 · 503 C51, C55 | - |
| `GET /api/products/{product}` statuses (3) | 200 C45 · 404 C45, C46 · 503 C51, C55 | - |
| Landing doors (9) | door 1 C1, C16 · door 2 C14 · door 3 C11, C17 · door 4 C30, C32 · door 5 C61, C75 · door 6 C52, C57, C62 · door 7 C31, C33 · door 8 C28 · door 9 C63, C71, C73 | - |
| eventos novos (8) | `catalog.product_created` C1 · `catalog.product_updated` C2 · `catalog.product_status_changed` C3 · `catalog.product_deleted` C4 · `catalog.category_created` C5 · `catalog.category_updated` C5 · `catalog.category_deleted` C6 · `inventory.stock_changed` C7, C8 | - |
| escritas que mudam a vitrine (8) | criar produto C1 · editar C2 · mudar status C3 · excluir produto C4 · criar ou renomear categoria C5 · excluir categoria C6 · ajustar estoque C7 · checkout C8 | - |
| escritas recusadas, AC 9 (5) | C9, table-driven over all 5 | - |
| escritas que travam a linha, AC 11 (7) | C11, table-driven over all 7 | - |
| tipos de valor do payload, AC 12 (6) | lista `['a', 'b']` C14 · lista vazia C14 · data ISO 8601 C14 · associativo C14 · aninhado C14 · objeto C14 | - |
| regra de versão do produto, AC 16 (4 examples) | igual C19 · mais velho C19 · mais novo C19 · até a lápide C19 | - |
| regra de versão do estoque, AC 18 (3 examples) | maior C21 · igual C21 · menor C21 | - |
| transições do documento (9) | nada → só estoque C20 · nada → na vitrine C18 · nada → lápide C22 · só estoque → na vitrine C18 · na vitrine → na vitrine C18, C21 · na vitrine → lápide C22 · só estoque → lápide C22 · lápide → lápide C22 · qualquer → nada C32 | - |
| consumidores do projetor (8) | C24, table-driven over all 8 · C28, table-driven over all 8 | - |
| ordenações da vitrine (3) | `name` C43 · `price_asc` C43 · `price_desc` C43 | - |
| página do produto, AC 39 (8 examples) | disponível C45 · inativo C45 · 0 unidades C45 · sem estoque C45 · desconhecido C45 · lápide C45 · só estoque C45 · fora da forma canônica C46 | - |
| filtro por slug, AC 36 (4 examples) | categoria viva C42 · só lápide C42 · desconhecido C42 · slug reusado C42 | - |
| comandos da reconstrução (3) | `catalog:read-model-clear` C32 · `catalog:republish` C33, C35 · `inventory:republish` C33, C35 | - |
| alvos do `Makefile` que reconstroem (3) | `storefront-rebuild` C31 · `fresh` C36 · `seed` C36 | - |
| startup config: a conexão `mongodb` (6 assemblies) | `api-read` de dev C52 · `api-read` de carga C53 · `queue-worker` C72 · `event-consumer` C62 · `api` (comandos) C69 · harness de testes C59 | - |
| rotas que ficam no `api`, AC 46 (6) | `/api/categories` C54 · `/api/admin/products` C54 · `POST /api/cart/validate` C54 · `DELETE /api/products` C54 · `/sanctum` C54 · `/horizon` C54 | - |
| pilhas com o gateway (2) | dev C52, C54, C55, C56 · carga C53, C55, C62 | - |
| testes da vitrine sem expectativa nova, AC 34 (6 files) | C40, table-driven over all 6 | - |
| documentos atualizados (6) | `README.md` C74 · `.env.example` C75 · `backend/.env.example` C75 · `docs/domain-analysis.md` C76 · `AGENTS.md` C77 · `load-tests/README.md` C73 | - |

- Afirmações sobre status, rota ou corpo de resposta: C1 a C9, C41 a C51, C52 a C56 - cada uma com uma prova que chama a rota (o Pest pela aplicação, o shell pelo Nginx)
- As afirmações de concorrência (C12, C13, C25) rodam em processos simultâneos de verdade, e as de lock (C11) observam a ordem das consultas na transação

## Swept

- validation: C14, C44, C46
- failure modes: C10, C27, C35, C51
- idempotency: C19, C21, C24
- authorization: existing - a vitrine é pública e as rotas de escrita continuam com `auth:staff`, `admin` e `auth:customer` nos arquivos de rotas, que não mudam; C54 confere que elas seguem no `api`
- concurrency: C11, C12, C13, C25, C26, C39
- data lifecycle: C17, C22, C32, C38
- dependency failure: C27, C51, C55, C56, C57
- state transitions: C18, C20, C22, C23
- observability: C66, C67, C68, C72

## Handoff

- **Settled mid-build:** none
- **Dispatched:** Verifier (fresh sub-agent) at HEAD `9cf02e5`: returned (5 findings, closed in `b8d1ba2`; `proofs.md` at `bb966ca`: 101 pass, 0 fail, mutations 5 killed)
- **Boundary:** C1 a C77 fechados em `bb966ca`; construídos, em quatro commits de código (`feat(catalog): record versioned storefront events...` a `feat(load-tests): measure the storefront projection lag`) e o de documentação; a suíte (`make test`, 2435 casos) e o `make pint` passam; o smoke do k6 passou pelo gateway com o `api-read` (amostra de `storefront_projection_lag` de 1,6 s)
- **Abandoned:** o `REPEATABLE READ` da reconstrução (C34): `SET TRANSACTION ISOLATION LEVEL` dentro do savepoint do `RefreshDatabase` falha, então o produto e os ids das categorias vêm de uma só consulta (a outra opção do C34); os use cases do projetor (`Project*UseCase`): o C29 pede que só `Core\Consumers`, `Console`, `Persistence` e `Providers` alcancem o modelo de leitura, então os consumidores chamam os repositórios de documento direto; a gravação por `upsert` sem segunda tentativa (AD-007)
- **Desvios a conferir:** (1) o C63 diz "VU de posição 1 do cenário": o k6 não dá o número do VU dentro do cenário, então o escritor é o VU que roda a iteração 0 do cenário `backoffice` (`claimsTeamWrites`); (2) além dos casos que o plano lista, mudaram de expectativa por causa do `SELECT ... FOR UPDATE` e das mensagens do outbox os casos de contagem de consultas e de leitura-antes-de-excluir do `StaffRecordRoutesTest` (`adjusts the stock...`, `deletes a product...`, `deletes a category...`, `keeps the query count...`), o `StockTest` (`makes a product unavailable...`, que agora reconstrói a vitrine e entrega as mensagens), o `OrderStatusFlowTest` (`subscribes exactly the five order consumers`, que ignora os oito do Catalog), o `DomainEventsTest`, o `ModuleEventsListenersTest`, o `StockRepositoryTest` (chama o repositório numa transação) e o `OrderEventPayloadTest` (os eventos novos); (3) o `phpunit.xml` passou o `memory_limit` de 256M para 512M: a suíte tem 2435 casos e cada um sobe uma aplicação (pico de 309 MB); (4) as linhas novas das duas tabelas da linha de base do `load-tests/README.md` dizem "sem rodada aceita": a regra dos 20% pede duas rodadas do `load` no mesmo commit (cerca de 8 minutos cada, 4 rodadas para os dois transportes), que não foram feitas
- **Walked:** nenhuma tela mudou (o corpo da vitrine é o de hoje); a vitrine foi lida pelo Nginx de dev (`GET /api/products`, `?sort=price_desc&category=...`, `HEAD`, o produto, o `DELETE` que cai no `api`) e pelo smoke de carga
