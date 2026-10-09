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
- **Camadas dentro de cada módulo** (ver o README): controllers finos, casos de uso em `UseCases/`, regras puras em `Services/` (sem banco, transação ou eventos) e acesso a dados só em `Repositories/`, que não usam os services do módulo (o `ModuleBoundariesTest` cobra).
- **Ligações explícitas:** um model novo declara a sua factory com `#[UseFactory]` (e a factory declara `protected $model`), e a sua policy com `#[UsePolicy]`. As convenções de namespace do Laravel (`App\Models`) não valem para os módulos.
- **Fronteiras entre contextos** (ver a [análise de domínio](docs/domain-analysis.md)):
  - **A facade de um módulo é o seu `Contracts/` mais os seus `Events/`.** Um módulo só usa outro por eles; as demais pastas (`Models`, `Repositories`, `Services`, `UseCases`, `Policies`, `Jobs`, `Listeners`, `Http`, uma pasta nova...) e o service provider são privados.
  - **De `DTOs`, `ValueObjects` e `Enums`, só o vocabulário é público:** as classes que os tipos nativos das assinaturas de `Contracts` e `Events` do próprio módulo alcançam (parâmetros, retornos e propriedades públicas, o construtor incluído), de forma transitiva. O `ModuleBoundariesTest` calcula isso a cada execução: para tornar um tipo público, ele precisa aparecer numa assinatura real de contrato ou evento do módulo dono (nunca crie um método só para isso), e um tipo citado só em docblock continua privado.
  - **O `Http` de cada módulo é privado.** Um módulo que precisa de uma regra de validação, de um trait de normalização ou de um resource de outro tem o seu adaptador no próprio `Http`, que só traduz o que o value object ou o enum decide, sem escrever limite, expressão regular ou lista de valores (o `HttpAdaptersTest` cobra os do Customers e do Fulfillment).
  - Contratos e eventos falam em **dados** (ids, value objects, enums), nunca em models ou coleções do Eloquent. Um contrato só existe para uma travessia real e leva o nome do papel de que o chamador precisa. É implementado por um repositório do módulo dono e ligado no service provider dele. Quando o fornecedor já depende do consumidor, o consumidor define o contrato.
  - Fora do Identity, o cliente autenticado é um id: as policies recebem o `Authenticatable` do framework. Um parâmetro de rota que aponta para o registro de outro módulo é resolvido pelo contrato do dono (`Route::bind`), nunca por route model binding.
  - As exceções que existem (o dashboard, inclusive o enum `ProductStatus` do Catalog, a vitrine e a lista de estoque do admin, até a HEL-6) ficam declaradas no `ModuleBoundariesTest`. Não crie exceção nova para fazer o teste passar.
  - Fora do próprio estoque, use os contratos de `App\Modules\Inventory\Contracts` (`StockInitializer`, `StockLevels`, `StockReservation`), nunca o `StockRepository`.
  - Só a parte de pedidos altera o status do pedido. Pagamento e entrega apenas publicam eventos (`PaymentApproved`, `OrderDelivered`).
  - O `User` é identidade e não conhece pedidos. O lado de pedidos usa o `Customer`, que é somente leitura.
  - O Catalog não usa models nem repositories do Ordering. Quando precisar de algo dos pedidos, use o contrato que ele mesmo define (`ProductOrderHistory`), implementado pelo Ordering.
  - O módulo `Shared` é só infraestrutura e não depende de nenhum módulo de negócio.
  - O teste de arquitetura `ModuleBoundariesTest` cobra essas regras: a privacidade das pastas é gerada a partir das pastas que existem, a do vocabulário a partir das classes que existem e das assinaturas, e as regras de direção são escritas uma a uma. Ao criar uma regra nova, use **um namespace por expectativa**: com uma lista de namespaces, o `not->toUse` do Pest nunca falha. Confirme também que a regra falha com uma violação de propósito.
- **Commits** em inglês, no formato `feat:`, `fix:`, `refactor:`, `test:`, `docs:`.

---

## Verificação antes de concluir

Tudo roda via Docker:

```bash
make test
```

```bash
docker compose exec -T api ./vendor/bin/pint --test
```

Depois de alterar listeners ou jobs, reinicie o worker:

```bash
make queue-restart
```

O CI (`.github/workflows/ci.yml`) roda o Pint e o `make test` em todo pull request, com o mesmo Docker Compose do ambiente local. Rode os dois localmente antes de abrir o PR: o CI só confirma.

### Checklist final

- [ ] Os testes passam (`make test`).
- [ ] O estilo está correto (Pint).
- [ ] Se mudou o ambiente (Dockerfile, `docker-compose.yml`, `Makefile`, `.env.example`), o workflow de CI continua coerente com ele.
- [ ] O README descreve o comportamento atual.
- [ ] A análise de domínio reflete o estado atual dos contextos.
- [ ] Nenhuma menção a nomes antigos ficou para trás.
