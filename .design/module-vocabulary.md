# Vocabulário público dos módulos: o que os contratos expõem e mais nada

> Faça o plano a partir deste documento. Cada slice abaixo já traz a sua forma: copie, não derive de novo.
> Status: confirmed by diashelter, 2026-10-09

## Situation

- Project: em construção. É um projeto de estudo, sem produção e sem usuários, e o banco é recriado por `make fresh`.
- Decision: decidida por diashelter em 2026-10-08, na Key decision 1 da [HEL-7](module-facades.md), que deixou esta rodada para a [HEL-10](https://linear.app/helter/issue/HEL-10/fechar-as-travessias-de-http-value-objects-enums-e-dtos-entre-modulos). A issue está em andamento. Esta discovery decidiu onde fica o vocabulário, o destino do `Http` e o caso do dashboard.
- In flight: segue o `ModuleBoundariesTest` da HEL-7, que gera a privacidade a partir do que existe e nomeia cada exceção com a issue que a remove. Segue também as discoveries anteriores, em que o vocabulário de um contrato fica no módulo que define o contrato. A HEL-12 (layout de pastas, rotas e migrations por módulo, `internachi/modular`), a HEL-11 (UUID v7 no lugar dos ids inteiros) e a HEL-6 (leituras) ficam de fora, mas vão criar e mover contratos em cima da regra definida aqui.
- At stake: baixo. São cerca de 15 arquivos, sem dados, e errar custa só retrabalho. O que pesa é a definição do que é público, porque a HEL-6, a HEL-11 e a HEL-12 vão ser construídas sobre ela.

## Problem

Construção. A HEL-7 fechou as pastas privadas, mas deixou `Http`, `ValueObjects`, `Enums` e `DTOs` alcançáveis por qualquer módulo. Hoje o teste não distingue um value object que um contrato devolve de um DTO interno do Catalog ou de uma regra de validação do Identity, e uma travessia nova para qualquer um deles passa sem aviso. Sem fechar isso, a HEL-6 não sabe o que torna público o tipo que um contrato de leitura devolve, e a HEL-12 reorganizaria as pastas sem uma regra do que é público. Fazer isso depois delas significaria decidir a superfície pública de novo para cada contrato novo.

Recontagem de 2026-10-09, depois do merge da HEL-7, a partir das declarações `use` em `backend/app/Modules`: há 57 referências de um módulo para essas quatro pastas de outro. A contagem da issue, de antes da HEL-7, era 42: os contratos novos trouxeram vocabulário. Dessas 57:

- 42 apontam para tipos que alguma assinatura de contrato expõe;
- 14 apontam para o `Http` de outro módulo: Customers → Identity (`EmailRule`, `PasswordRule`, `NormalizesEmailInput` e `CustomerProfileResource`) e Customers e Fulfillment → Ordering (`NormalizesStateInput`, que o próprio Ordering não usa);
- 1 aponta para um enum que nenhum contrato expõe: o `ProductStatus`, no dashboard do Backoffice.

## Success

- Worked if:
  - as 15 referências a classes que não são vocabulário caem para 0: as 14 de `Http` somem, e o `ProductStatus` do dashboard fica declarado como exceção da HEL-6;
  - as 42 restantes apontam só para tipos que uma assinatura de contrato expõe;
  - o `ModuleBoundariesTest` permite só `Contracts`, `Events` e o vocabulário calculado, e falha com uma violação de propósito em cada categoria: value object, enum, DTO e `Http`;
  - as rotas tocadas devolvem os mesmos códigos, corpos e mensagens de validação;
  - `make test` e o Pint passam.
- Going wrong:
  - um adaptador copiado repete um limite, uma expressão regular ou uma lista de valores em vez de chamar o vocabulário, e a regra passa a ser escrita duas vezes;
  - um consumidor da HEL-6 ganha uma exceção nova em vez de uma lista tipada;
  - um contrato ganha um parâmetro ou um método só para tornar público um tipo interno.
- Review: quando a HEL-6 ou a HEL-12 começar. diashelter confere se o vocabulário calculado ainda é o que os contratos precisam e se a lista de exceções continua só com a HEL-6.

## Boundary

In:
- A regra do vocabulário público no `ModuleBoundariesTest` e a prova por violação de propósito.
- As 14 travessias de `Http`: Customers → Identity e Customers e Fulfillment → Ordering.
- O `ProductStatus` do dashboard como exceção da HEL-6.
- A atualização do README, da análise de domínio e do `AGENTS.md`, que hoje dizem "até a HEL-10".

Out:
- Layout de pastas, rotas e migrations por módulo e `internachi/modular`: ficam para a HEL-12. A regra desta rodada não depende de pasta.
- UUID v7 no lugar dos ids inteiros: fica para a HEL-11. Quando os tipos dos ids mudarem, as assinaturas mudam e o vocabulário acompanha sem mexer no teste.
- O dashboard, a vitrine e a lista de estoque do admin: ficam para a HEL-6.

Unchanged:
- Nenhuma classe de vocabulário muda de pasta nem de namespace.
- As assinaturas dos contratos e os payloads dos eventos.
- `Email`, `Password` e `BrazilianState` continuam sendo o único lugar das regras de e-mail, senha e UF.
- As rotas, os códigos HTTP, os corpos de resposta e as mensagens de validação.
- As regras de direção do `ModuleBoundariesTest`, a regra de que `Contracts` só tem interfaces e a regra de que os contratos falam em dados.
- As oito exceções da HEL-6 que já existem.
- O vocabulário do `PaymentGateway` (`ChargeRequest`, `ChargeResult`, `DeclineReason`): fica público pela regra, mas ninguém fora do Payment o usa.

## Shape

O vocabulário público de cada módulo deixa de ser uma lista de pastas e passa a ser o conjunto de tipos que os seus contratos e eventos expõem, calculado pelo `ModuleBoundariesTest` a partir das assinaturas. Todo o resto de `ValueObjects`, `Enums`, `DTOs` e `Http` fica privado. As 14 peças de `Http` que outros módulos usam dão lugar a adaptadores próprios no Customers e no Fulfillment, e o `ProductStatus` do dashboard entra na exceção da HEL-6. A porta é a definição de público: a HEL-6, a HEL-11 e a HEL-12 vão criar e mover contratos em cima dela, e trocar a regra depois significa revisar cada contrato novo.

A alternativa mais pesada é mover o vocabulário para uma subpasta de `Contracts`. São cerca de 20 classes e 45 `use`, e o `Contracts` deixa de ter só interfaces. Ela só ganha se ler o que é público na árvore de pastas, sem rodar o teste, importar mais que o custo da mudança, e a HEL-12 vai reorganizar essas pastas de qualquer forma.

Fora da comparação, só para referência:
- Declarar `ValueObjects` e `Enums` públicos por inteiro foi descartado: tipos internos como `ValidatedCart`, `OrderLines` e `ProductQuantities` ficariam alcançáveis.
- Um atributo nas classes públicas, conferido pelo teste contra as assinaturas, deixaria o público visível na própria classe, mas seria uma segunda fonte para a mesma informação.
- O dono continuar expondo as peças de `Http` foi descartado: põe código de framework na superfície pública e torna a exceção permanente.

## Key decisions

1. **O vocabulário público de um módulo é exatamente o conjunto de tipos que as assinaturas dos seus `Contracts` e `Events` expõem, seguido de forma transitiva. O resto de `ValueObjects`, `Enums` e `DTOs` é privado.** Transitivo quer dizer que os tipos dos parâmetros, dos retornos e das propriedades públicas de um tipo público também são públicos: o `OrderForPayment` expõe o `OrderStatus`, e o `CreateUserDTO` expõe o `Email` e o `Password`. Só entram tipos de módulos de negócio, nunca do `Shared` nem do framework. O vocabulário fica na pasta em que está e no módulo que define o contrato. O que o torna público é aparecer numa assinatura, não a pasta, então a regra continua valendo se a HEL-12 reorganizar os módulos.
2. **Só conta a declaração de tipo nativa do PHP. Um tipo citado apenas em docblock (`Collection<Foo>`, `list<Foo>`) continua privado.** Um contrato que entrega vários valores entrega uma lista tipada, como já fazem `OrderSummaries` e `CatalogProducts`. Quando um contrato de leitura da HEL-6 fizer o teste falhar, a solução é a lista tipada, nunca uma exceção.
3. **A superfície pública só cresce quando uma assinatura de contrato ou de evento cresce, e o teste não guarda a lista do vocabulário.** Um tipo novo numa assinatura fica público sem que ninguém mexa no teste, e quem revisa é a mudança no contrato. Um tipo interno nunca é liberado por uma entrada no teste.
4. **O `Http` de um módulo é privado por inteiro, sem exceção.** Cada módulo escreve os seus adaptadores HTTP (regras de validação, normalização da entrada e resources) em cima do vocabulário público. A regra continua escrita uma vez só, em `Email`, `Password` e `BrazilianState`. O adaptador só a traduz para o formato do Laravel e nunca repete um limite, uma expressão regular ou uma lista de valores.
5. **A lista de exceções não ganha nenhuma travessia nova.** A única entrada nova é o `ProductStatus` do Backoffice, porque é com ele que o dashboard lê a contagem de produtos por status vinda do repository do Catalog, uma leitura que já é exceção da HEL-6. As duas saem juntas na HEL-6.

## Work

| Slice | Delivers | Status |
|---|---|---|
| [ModuleBoundariesTest](#moduleboundariestest) | O vocabulário calculado, o `Http` privado, a exceção do `ProductStatus` e a prova por violação de propósito | clear |
| [EmailRule e PasswordRule](#emailrule-e-passwordrule) | Os Form Requests de conta do Customers validando e normalizando e-mail e senha com adaptadores próprios | clear |
| [CustomerProfileResource](#customerprofileresource) | O Customers mostrando a conta com o próprio resource, no mesmo formato do Identity | clear |
| [NormalizesStateInput](#normalizesstateinput) | O Customers e o Fulfillment normalizando a UF cada um no seu `Http` | clear |

Order: ModuleBoundariesTest → EmailRule e PasswordRule → CustomerProfileResource → NormalizesStateInput. O teste é escrito primeiro e falha listando as 14 travessias de `Http`; cada slice zera a sua parte.

Derivable from the repository, left to the plan:
- Uma expectativa por namespace ou por classe, a prova por violação de propósito e a conferência de que cada módulo citado existe: tudo como o `ModuleBoundariesTest` já faz.
- A leitura das assinaturas por reflexão: como o teste de contratos que falam em dados já faz.
- O nome dos adaptadores copiados: o mesmo do original no Identity e no Ordering, dentro do `Http` de cada módulo.
- A atualização do README, da análise de domínio (o problema da HEL-10 marcado com ✅ e a matriz de coesão Customers → Identity) e do `AGENTS.md`: como o próprio `AGENTS.md` pede.

### ModuleBoundariesTest

**Delivers** a fronteira como `Contracts`, `Events` e o vocabulário que eles expõem, e mais nada. **Status: clear.** É a porta (Key decisions 1 a 3).

| State | What should happen |
|---|---|
| Um módulo usa um tipo que uma assinatura de `Contracts` ou de `Events` de outro expõe, direta ou transitivamente | Permitido se as regras de direção também permitirem, mesmo sem chamar o contrato, como o Customers gravando a UF com o `BrazilianState` |
| Um módulo usa qualquer outra classe de `ValueObjects`, `Enums` ou `DTOs` de outro | O teste falha e nomeia o módulo de origem e a classe atravessada |
| Um módulo usa qualquer classe do `Http` de outro | O teste falha, sem exceção (Key decision 4) |
| Um contrato ou um evento passa a expor um tipo novo | O tipo fica público sem mudar o teste (Key decision 3) |
| Um tipo aparece só no docblock de uma assinatura | Continua privado (Key decision 2) |
| O Backoffice usa o `ProductStatus` | Permitido como exceção da HEL-6, escrita ao lado da leitura do repository do Catalog (Key decision 5) |
| Tipos internos conhecidos (`ValidatedCart`, `LoginCredentialsDTO`, `UserRole`) | O teste confirma que estão fora do vocabulário: é a prova de que a lista ficou mais estreita |
| Uma violação de propósito em cada categoria (value object, enum, DTO e `Http`) | O teste falha; isso é conferido antes da entrega |

### EmailRule e PasswordRule

**Delivers** a criação e a edição de cliente no admin (`POST /api/admin/customers`, `PUT /api/admin/customers/{customer}`) e a edição do perfil (`PUT /api/account/profile`) com a validação e a normalização de e-mail e senha feitas por adaptadores do próprio Customers. **Status: clear.**

| State | What should happen | Caller sees |
|---|---|---|
| E-mail com espaços ou maiúsculas | Normalizado pela regra do `Email` antes de validar, e o `unique` compara a forma canônica | inalterado |
| E-mail mal formado ou com mais de 255 caracteres | Recusado pela regra do `Email` | `422` em `email`, com a mesma mensagem de hoje |
| E-mail que já é de outro cliente | Recusado | `422` em `email` (inalterado) |
| Senha com menos de 8 caracteres | Recusada pela regra do `Password` | `422` em `password`, com a mesma mensagem de hoje |
| Perfil editado com a senha em branco | Nome e e-mail mudam, a senha fica | `200` (inalterado) |

O teste que impede os Form Requests de conta de repetir as regras continua cobrindo os três requests do Customers, agora com os adaptadores do Customers.

Alternatives considered: um gancho genérico de normalização no `ApiFormRequest` do Shared, que recebe funções e não conhece nenhum módulo de negócio. Ganha se um terceiro módulo passar a normalizar e-mail ou UF.

### CustomerProfileResource

**Delivers** a conta de cliente mostrada pelo resource do próprio Customers em `GET /api/account`, `PUT /api/account/profile` e `/api/admin/customers`, com o mesmo formato que o Identity devolve em `/api/auth/register`, `/api/auth/login` e `/api/auth/me`. **Status: clear.**

| State | What should happen | Caller sees |
|---|---|---|
| O cliente abre a conta ou edita o perfil | A conta sai pelo resource do Customers | `200` com `customer` ou `data` em `{id, name, email, created_at}` (inalterado) |
| O admin lista, abre, cria ou edita um cliente | Os campos da conta saem pelo mesmo resource, mais `orders_count` e `orders` | inalterado |
| A mesma conta é mostrada pelo Identity e pelo Customers | Mesmas chaves e mesmos valores; um teste compara as duas saídas | — |
| O formato muda só de um lado | O teste de formato falha até o outro lado acompanhar | — |

A conta continua com um único formato na API. Quem garante isso deixa de ser uma classe compartilhada e passa a ser o teste da terceira linha. Isso desfaz de propósito a classe única criada na HEL-7, que só poderia continuar como exceção permanente no `Http`.

Alternatives considered: o Identity expor o resource como exceção. Ganharia se o formato da conta mudasse com frequência; hoje são quatro campos.

### NormalizesStateInput

**Delivers** o caderno de endereços (`POST /api/account/addresses`, `PUT /api/account/addresses/{address}`) e a cotação de frete (`GET /api/shipping/quote`) normalizando a UF cada um no seu próprio `Http`. O trait sai do Ordering, que nunca o usou. **Status: clear.**

| State | What should happen | Caller sees |
|---|---|---|
| UF com espaços ou minúsculas (`" sp "`) | Normalizada pela regra do `BrazilianState` antes de validar | aceita como `SP` (inalterado) |
| UF fora das 27 siglas | Recusada | `422` em `state` (inalterado) |

## Sources

- [HEL-10](https://linear.app/helter/issue/HEL-10/fechar-as-travessias-de-http-value-objects-enums-e-dtos-entre-modulos): as perguntas desta rodada e o critério de pronto.
- [Facades dos módulos](module-facades.md): a Key decision 1, que deixou esta rodada para depois, o `ModuleBoundariesTest` gerado a partir das pastas e as exceções da HEL-6.
- [Endereços e frete](addresses-and-shipping.md): a Key decision 6, com o vocabulário no módulo que define o contrato e o `BrazilianState` no Ordering.
- [Value objects e listas tipadas](value-objects-and-typed-lists.md): o `ProductIds` no Catalog e as listas tipadas.
- [Contas de equipe e de cliente](staff-and-customer-accounts.md): `Email`, `Password`, `EmailRule` e `PasswordRule` valendo para as duas contas.
- [Análise de domínio](../docs/domain-analysis.md): o problema registrado para a HEL-10 e a matriz de coesão.
- HEL-12, HEL-11 e HEL-6 no Linear: o que vem depois e o que fica de fora.
