# Vocabulário público dos módulos

## Problem

Hoje o `ModuleBoundariesTest` trata `DTOs`, `ValueObjects`, `Enums` e `Http` de qualquer módulo como públicos por inteiro. O teste não distingue um value object que um contrato devolve de um DTO interno do Catalog ou de uma regra de validação do Identity, então uma travessia nova para qualquer um deles passa sem aviso. Das 45 classes nessas três primeiras pastas, 23 não aparecem em nenhuma assinatura de contrato ou de evento (`ValidatedCart`, `OrderLines`, `ProductQuantities`, `LoginCredentialsDTO`, `UserRole`...) e mesmo assim são alcançáveis de fora.

Recontagem de 2026-10-09 nas declarações `use` de `backend/app/Modules`, sem contar o `Shared`: 57 referências de um módulo para essas quatro pastas de outro. Dessas, 42 apontam para tipos que alguma assinatura de contrato expõe. As outras 15 não deveriam existir:

- 14 apontam para o `Http` de outro módulo: Customers → Identity (`EmailRule`, `PasswordRule`, `NormalizesEmailInput`, `CustomerProfileResource`), e Customers e Fulfillment → Ordering (`NormalizesStateInput`, que o próprio Ordering não usa);
- 1 aponta para um enum que nenhum contrato expõe: o `ProductStatus`, no `DashboardService` do Backoffice.

Quem paga são as próximas rodadas. A HEL-6 não sabe o que torna público o tipo que um contrato de leitura devolve, e a HEL-12 reorganizaria as pastas sem uma regra do que é público. Decidir isso depois delas é decidir a superfície pública de novo para cada contrato novo.

Quando isto estiver entregue, o teste permite de fora só `Contracts`, `Events` e o vocabulário que as assinaturas deles expõem, calculado a cada execução. O `Http` de cada módulo é privado, e o Customers e o Fulfillment têm os seus próprios adaptadores. Para quem usa a loja nada muda: as rotas tocadas devolvem os mesmos códigos, corpos e mensagens.

## Flow

Reaproveita o gerador de regras do `ModuleBoundariesTest` (pastas privadas geradas do que existe, `BOUNDARY_EXCEPTIONS`), a leitura das assinaturas por reflexão do teste "speaks only data in every contract signature", e os value objects `Email` e `Password` e o enum `BrazilianState` como o único lugar das regras de e-mail, senha e UF. Os adaptadores novos só traduzem essas regras para o Laravel.

1. `make test` -> `ModuleBoundariesTest` (exists) lê por reflexão as assinaturas de `Contracts` e `Events` de cada módulo e calcula o vocabulário dele (door 1). Gera uma expectativa por classe de `ValueObjects`, `Enums` e `DTOs` fora do vocabulário (door 1) e uma por namespace `Http` de outro módulo (door 2), menos a exceção do `ProductStatus` (door 3)
2. `POST /api/admin/customers`, `PUT /api/admin/customers/{customer}`, `PUT /api/account/profile` -> `Customers` (exists): os Form Requests normalizam o e-mail com o `NormalizesEmailInput` do Customers e validam com o `EmailRule` e o `PasswordRule` do Customers (door 4), que chamam `Email` e `Password` (vocabulário do Identity, exists). Daí seguem para os use cases e o `CustomerAccounts` (exists), que devolve um `CustomerProfile`
3. `GET /api/account`, `PUT /api/account/profile`, `/api/admin/customers*` -> `Customers` (exists) mostra o `CustomerProfile` com o seu `CustomerProfileResource` (door 4). O `AuthController` do Identity (exists) continua com o resource do Identity em `/api/auth/register`, `/api/auth/login` e `/api/auth/me`
4. `POST /api/account/addresses`, `PUT /api/account/addresses/{address}` -> `Customers` (exists) normaliza a UF com o seu `NormalizesStateInput` (door 4), que chama `BrazilianState::normalize` (vocabulário do Ordering, exists), e grava `customer_addresses`
5. `GET /api/shipping/quote` -> `Fulfillment` (exists) normaliza a UF com o seu `NormalizesStateInput` (door 4) e cota pelo `ShippingRateTable` (exists)
6. out: as mesmas respostas de hoje. O `NormalizesStateInput` do Ordering deixa de existir

## Impact

| Front | What changes |
| --- | --- |
| domain | termo existente "público": era a pasta (`Contracts`, `Events`, `DTOs`, `ValueObjects`, `Enums`, `Http`) e passa a ser aparecer numa assinatura de `Contracts` ou `Events` do próprio módulo, de forma transitiva. Quem decide com base nele hoje: `PUBLIC_DIRECTORIES` no `ModuleBoundariesTest`, o `AGENTS.md`, o README e a análise de domínio |
| domain | ficam públicos 22 tipos, os mesmos que a reflexão alcança hoje: Catalog `CatalogProduct`, `CatalogProducts`, `ProductIds`; Identity `CreateUserDTO`, `UpdateUserProfileDTO`, `CustomerProfile`, `Email`, `Password`; Inventory `StockQuantities`; Ordering `BrazilianState`, `OrderStatus`, `CustomerIds`, `DeliveryAddress`, `OrderCountsByCustomer`, `OrderForPayment`, `OrderSummaries`, `OrderSummary`, `ShippingQuote`; Payment `ChargeRequest`, `ChargeResult`, `DeclineReason`, `PaymentStatus`. O `PaymentStatus` não estava na lista da discovery: o `ChargeResult` o expõe no construtor |
| domain | ficam privados 23 tipos. O único usado fora do seu módulo hoje é o `ProductStatus` (Backoffice, exceção HEL-6). Os outros 22 não têm usuário externo: Catalog `CategoryDTO`, `CreateProductDTO`, `ProductCatalogFilterDTO`, `ProductDTO`, `UpdateProductStatusDTO`, `CategoryIds`; Customers `CustomerAddressDTO`, `CustomerSummaryDTO`; Identity `CreateStaffMemberDTO`, `LoginCredentialsDTO`, `UpdateStaffMemberDTO`, `UserRole`; Inventory `AdjustStockDTO`, `StockOperation`; Ordering `CartDTO`, `CartItemDTO`, `OrderLine`, `OrderLines`, `ProductQuantities`, `ValidatedCart`, `ValidatedCartLine`; Payment `PayOrderDTO` |
| domain | `EmailRule`, `PasswordRule`, `NormalizesEmailInput` e `CustomerProfileResource` ficam no Identity e passam a ser usados só por ele. Quem usa hoje de fora: `StoreCustomerRequest`, `UpdateCustomerRequest`, `UpdateProfileRequest`, `AccountController`, `ProfileController` e `CustomerSummaryResource` (Customers) |
| domain | `Ordering\Http\Requests\Concerns\NormalizesStateInput` sai. Quem usa hoje: `CustomerAddressRequest` (Customers) e `ShippingQuoteRequest` (Fulfillment). O Ordering não usa |
| tests | `ModuleBoundariesTest`: o vocabulário calculado, o `Http` privado e a nona exceção; todas as regras de hoje continuam. `AccountRequestRulesTest`: os três requests do Customers passam a ser conferidos contra os adaptadores do Customers; os quatro do Identity, contra os do Identity. Teste novo: o formato da conta igual nos dois resources |
| stored data | nothing to migrate - nenhuma tabela, coluna ou dado muda |
| config | nada - nenhum provider, variável de ambiente ou rota muda |
| docs | README (linhas 79, 85, 700, 745 e 750: "até a HEL-10", as regras de e-mail e senha, o `AccountRequestRulesTest` e o trait de UF), análise de domínio (linhas 265, 270, 290, 329, 460, 464, 514 e 579, a matriz Customers → Identity, o problema da HEL-10 com ✅ e a "Última atualização") e `AGENTS.md` (linha 63: a regra do vocabulário no lugar de "até a HEL-10") |

## Relations

None - no stored-data shape change. Nenhuma tabela, coluna, índice ou chave estrangeira muda.

## Surface

None - nothing consumed outside changes. As rotas tocadas (`/api/admin/customers*`, `/api/account`, `/api/account/profile`, `/api/account/addresses*` e `/api/shipping/quote`) mantêm entrada, saída e códigos; os critérios de S2, S3 e S4 conferem isso.

## Landing

| One-way door | Literal shape | Alternative rejected |
| --- | --- | --- |
| 1. o vocabulário público calculado (`ModuleBoundariesTest`) | Para cada módulo B diferente de `Shared`, o vocabulário de B é o conjunto das classes declaradas em `App\Modules\B` alcançadas pelos tipos nativos (parâmetros, retorno e propriedades públicas) dos métodos públicos das interfaces de `B\Contracts` e das classes de `B\Events`. O cálculo segue esses mesmos tipos em cada classe alcançada de `App\Modules`, menos o `Shared`. Tipos embutidos, `self`, `static`, `Shared`, o framework e os docblocks não contam. Para cada par de módulos distintos A e B, com B diferente de `Shared`, e cada classe C de `B\ValueObjects`, `B\Enums` e `B\DTOs` fora do vocabulário de B: `arch("{A} uses only the public vocabulary of {B}: {C}")->expect('App\Modules\A')->not->toUse(C)`. O teste não guarda a lista do vocabulário | subpasta de vocabulário em `Contracts`: move cerca de 20 classes e 45 `use`, e o `Contracts` deixa de ter só interfaces, sendo que a HEL-12 vai reorganizar as pastas de qualquer forma. Pastas públicas por inteiro: `ValidatedCart`, `OrderLines` e `ProductQuantities` ficam alcançáveis. Um atributo nas classes públicas: seria uma segunda fonte para a mesma informação. Vocabulário como a união das assinaturas de todos os módulos: um contrato de A tornaria público um tipo interno de B sem que nenhum contrato de B mude |
| 2. o `Http` de um módulo é privado, sem exceção | `Http` sai das pastas públicas e entra no gerador das privadas: `expect('App\Modules\A')->not->toUse('App\Modules\B\Http')` para todo A diferente de B, com B diferente de `Shared`, e nenhuma entrada de `BOUNDARY_EXCEPTIONS` aponta para um `Http`. Pastas públicas: só `Contracts` e `Events` | o Identity e o Ordering exporem as peças como exceção: põe código de framework na superfície pública e torna a exceção permanente. Um gancho genérico de normalização no `ApiFormRequest` do Shared: só ganha quando um terceiro módulo normalizar e-mail ou UF |
| 3. a nona exceção | `['App\\Modules\\Backoffice', 'App\\Modules\\Catalog\\Enums\\ProductStatus', 'HEL-6']`, logo depois de `['App\\Modules\\Backoffice', 'App\\Modules\\Catalog\\Repositories', 'HEL-6']`. O teste "declares exactly the HEL-6 exceptions" passa a listar nove entradas | um contrato do Catalog ganhar um método só para tornar o enum público: é o "contrato que cresce para expor um tipo interno" que a discovery proíbe. Trocar o enum por strings no dashboard: mexe na leitura que a HEL-6 vai refazer |
| 4. o adaptador próprio por módulo (precedente para a HEL-6 e a HEL-12) | Com o mesmo nome do original, dentro do `Http` de cada módulo: `App\Modules\Customers\Http\Rules\EmailRule` e `PasswordRule`, `App\Modules\Customers\Http\Requests\Concerns\NormalizesEmailInput` e `NormalizesStateInput`, `App\Modules\Fulfillment\Http\Requests\Concerns\NormalizesStateInput` e `App\Modules\Customers\Http\Resources\CustomerProfileResource`. Cada um delega a `Email`, `Password` e `BrazilianState` e não escreve limite, expressão regular nem lista de UFs. O formato único da conta é garantido por um teste que mostra o mesmo `CustomerProfile` pelos dois resources e compara as saídas | uma classe compartilhada (a de hoje): só continuaria como exceção permanente no `Http`. Um trait no `Shared`: o `Shared` não pode depender de um módulo de negócio |

- Nothing else in this change is hard to reverse: nomes de funções e constantes do teste, o arquivo do teste de formato e a forma interna de cada adaptador ficam para o diff

## Criteria

### S1: ModuleBoundariesTest (P1)

A fronteira passa a ser `Contracts`, `Events` e o vocabulário que eles expõem, e mais nada.

**Acceptance Criteria**

1. The `ModuleBoundariesTest` SHALL compute the public vocabulary of each module B other than `Shared` as the classes declared under `App\Modules\B` reached from the native parameter, return and public property types of the public methods of the interfaces in `B\Contracts` and of the classes in `B\Events`, following those same types transitively through every reached class under `App\Modules` other than `Shared`
2. IF a class under `App\Modules\<A>` uses a class of `App\Modules\<B>\ValueObjects`, `App\Modules\<B>\Enums` or `App\Modules\<B>\DTOs` outside the public vocabulary of B, for distinct modules A and B with B other than `Shared`, THEN the `ModuleBoundariesTest` SHALL fail with a test description naming A and the crossed class
3. IF a class under `App\Modules\<A>` uses any class under `App\Modules\<B>\Http`, for distinct modules A and B with B other than `Shared`, THEN the `ModuleBoundariesTest` SHALL fail, with no exception for any pair of modules
4. WHEN a class under `App\Modules\<A>` uses a class in the public vocabulary of B and no direction rule forbids A from using B THEN the `ModuleBoundariesTest` SHALL pass for that crossing, including `Customers\Models\CustomerAddress` using `Ordering\Enums\BrazilianState` without calling any contract
5. WHEN an interface in `B\Contracts` or a class in `B\Events` gains a native type T declared under `App\Modules\B` THEN the `ModuleBoundariesTest` SHALL treat T as public with no edit to the test file
6. WHEN a new class is created under `B\ValueObjects`, `B\Enums` or `B\DTOs` that no signature of B's contracts or events reaches THEN the `ModuleBoundariesTest` SHALL treat it as private with no edit to the test file
7. IF a type appears in a contract signature only through a docblock (`list<T>`, `Collection<int, T>`) THEN the computed vocabulary SHALL not contain T
8. IF an interface in `App\Modules\<A>\Contracts` uses a class of B outside the public vocabulary of B THEN the `ModuleBoundariesTest` SHALL fail
9. The computed vocabulary SHALL contain `Identity\ValueObjects\Email` and `Identity\ValueObjects\Password` (through `CreateUserDTO`), `Ordering\Enums\OrderStatus` (through `OrderForPayment`) and `Payment\Enums\DeclineReason` (through `ChargeResult`), and SHALL not contain `Ordering\ValueObjects\ValidatedCart`, `Ordering\ValueObjects\OrderLines`, `Ordering\ValueObjects\ProductQuantities`, `Identity\DTOs\LoginCredentialsDTO` or `Identity\Enums\UserRole`
10. The `ModuleBoundariesTest` SHALL declare exactly nine exceptions: the eight of today plus `['App\Modules\Backoffice', 'App\Modules\Catalog\Enums\ProductStatus', 'HEL-6']`, written immediately after the Backoffice → `Catalog\Repositories` entry
11. IF a module other than the Backoffice uses `Catalog\Enums\ProductStatus` THEN the `ModuleBoundariesTest` SHALL fail
12. WHEN a deliberate violation of each category (a value object, an enum and a DTO outside the vocabulary, and an `Http` class of another module) is introduced THEN the `ModuleBoundariesTest` SHALL fail for each, and SHALL pass again once it is removed
13. The `ModuleBoundariesTest` SHALL keep every rule it has today: private directories and module root classes, contracts with interfaces only, contracts speaking data, the contract bindings, the direction rules, repositories without their module services, the password policy in the account form requests and the final readonly Identity value objects
14. WHEN `make test` runs on the finished branch THEN the `ModuleBoundariesTest` SHALL pass

**Independent test:** rodar o `ModuleBoundariesTest` com uma violação de cada categoria e vê-lo falhar; depois sem ela e vê-lo passar.

### S2: EmailRule e PasswordRule (P1)

A criação e a edição de cliente no admin e a edição do perfil validam e normalizam e-mail e senha com adaptadores do próprio Customers.

**Acceptance Criteria**

15. The Form Requests `StoreCustomerRequest`, `UpdateCustomerRequest` and `UpdateProfileRequest` SHALL validate `email` with `App\Modules\Customers\Http\Rules\EmailRule`, normalize it with `App\Modules\Customers\Http\Requests\Concerns\NormalizesEmailInput`, and validate `password` with `App\Modules\Customers\Http\Rules\PasswordRule`
16. WHEN `POST /api/admin/customers`, `PUT /api/admin/customers/{customer}` or `PUT /api/account/profile` receives `email` `"  Ana@Example.COM "` THEN the system SHALL return `201` for the creation and `200` for the two updates, with the e-mail stored and returned as `"ana@example.com"`
17. IF `email` normalizes to an e-mail that already belongs to another customer THEN those three routes SHALL return `422` with `errors.email` `["O valor informado para e-mail já está em uso."]`
18. IF `email` is `"not-an-email"` THEN those three routes SHALL return `422` with `errors.email` `["O campo e-mail deve ser um e-mail válido."]`
19. IF `email` has 256 characters after normalization THEN those three routes SHALL return `422` with `errors.email` `["O campo e-mail não pode ter mais de 255 caracteres."]`
20. IF `password` has 7 characters and a matching `password_confirmation` THEN those three routes SHALL return `422` with `errors.password` `["O campo senha deve ter pelo menos 8 caracteres."]`
21. WHEN `PUT /api/account/profile` receives a new name and e-mail with `password` blank THEN the system SHALL return `200`, store the new name and e-mail, and keep the stored password hash unchanged
22. The adapters this feature creates under `App\Modules\Customers\Http` and `App\Modules\Fulfillment\Http` SHALL contain no integer literal, no regular expression and no list of state codes, and SHALL read the e-mail and password limits and the state codes only from `Email`, `Password` and `BrazilianState`

**Independent test:** criar e editar um cliente no admin e editar o perfil com e-mail em maiúsculas, mal formado, longo demais e já usado, e com senha curta.

### S3: CustomerProfileResource (P2)

O Customers mostra a conta com o próprio resource, no mesmo formato que o Identity devolve.

**Acceptance Criteria**

23. WHEN a customer sends `GET /api/account` THEN the system SHALL return `200` with `data.customer` holding exactly the keys `id`, `name`, `email` and `created_at`, with `created_at` in ISO 8601
24. WHEN `PUT /api/account/profile` succeeds THEN `data` SHALL hold exactly the keys `id`, `name`, `email` and `created_at`, and `message` SHALL be `"Dados atualizados com sucesso."`
25. WHEN an admin lists, opens, creates or updates a customer THEN each customer object SHALL hold `id`, `name`, `email`, `created_at` and `orders_count`, plus `orders` when the customer is opened, with the same values as today
26. The account shape test SHALL render the same `CustomerProfile` through `App\Modules\Identity\Http\Resources\CustomerProfileResource` and `App\Modules\Customers\Http\Resources\CustomerProfileResource` and find the two arrays identical, with the same keys in the same order and the same values
27. IF one of the two resources adds, removes or renames a key, or changes the format of a value, THEN the account shape test SHALL fail

**Independent test:** rodar o teste de formato e abrir "Minha conta" e o detalhe de um cliente no admin.

### S4: NormalizesStateInput (P2)

O caderno de endereços e a cotação de frete normalizam a UF cada um no seu próprio `Http`.

**Acceptance Criteria**

28. WHEN `POST /api/account/addresses` or `PUT /api/account/addresses/{address}` receives `state` `" sp "` THEN the system SHALL return `201` for the creation and `200` for the update, with `data.state` `"SP"` and the stored state `SP`
29. WHEN `GET /api/shipping/quote` receives `state` `" sp "` THEN the system SHALL return `200` with `data.state` `"SP"`
30. IF `state` is `"XX"` THEN those three routes SHALL return `422` with an error under `errors.state`
31. The Form Requests `CustomerAddressRequest` and `ShippingQuoteRequest` SHALL normalize `state` with the `NormalizesStateInput` of their own module, `App\Modules\Customers\Http\Requests\Concerns\NormalizesStateInput` and `App\Modules\Fulfillment\Http\Requests\Concerns\NormalizesStateInput`
32. The system SHALL not declare `App\Modules\Ordering\Http\Requests\Concerns\NormalizesStateInput`

**Independent test:** cadastrar um endereço e cotar o frete com `" sp "` e com `"XX"`.

## Out of scope

| Excluded | Why |
| --- | --- |
| layout de pastas, rotas e migrations por módulo, e `internachi/modular` | HEL-12. A regra desta rodada não depende de pasta |
| UUID v7 no lugar dos ids inteiros | HEL-11. Quando as assinaturas mudarem, o vocabulário acompanha sem mexer no teste |
| dashboard, vitrine e lista de estoque do admin sem as exceções | HEL-6 |
| mover alguma classe de vocabulário de pasta ou de namespace | discovery, Boundary "Unchanged" |
| mudar assinaturas de contratos ou payloads de eventos | discovery, Boundary "Unchanged" |

## Assumptions

| Assumption | Chosen default | Rationale | Confirmed? |
| --- | --- | --- | --- |
| parâmetros de construtor contam como assinatura | sim: o construtor é um método público, e quem recebe um tipo público precisa conseguir montá-lo | é assim que `CreateUserDTO` expõe `Email` e `Password`, e `ChargeResult` expõe `DeclineReason` e `PaymentStatus`, como a Key decision 1 descreve | n |
| métodos herdados do framework (`Dispatchable` nos eventos, `UnitEnum` nos enums) | entram na leitura, mas os tipos deles são do framework ou embutidos e nunca viram vocabulário | a Key decision 1 só conta tipos de módulos de negócio | n |
| comparação no teste de formato | arrays idênticos com `toBe`, para a mesma ordem de chaves e os mesmos valores | a discovery pede mesmas chaves e mesmos valores; com a ordem fixa, o JSON também fica idêntico | n |
| o `AccountRequestRulesTest` dos requests do Identity | continua conferindo os quatro requests do Identity contra o `EmailRule` e o `PasswordRule` do Identity | o teste mora em `tests/`, fora dos módulos, e pode citar os dois lados | n |

**Open questions:** none - all resolved or logged above.

## Observable

| Surface | Decision | Landing |
| --- | --- | --- |
| API `POST /api/admin/customers`, `PUT /api/admin/customers/{customer}`, `PUT /api/account/profile` | response shape | AC 16, 21, 24, 25 |
| API `POST /api/admin/customers`, `PUT /api/admin/customers/{customer}`, `PUT /api/account/profile` | error shape and codes | AC 17, 18, 19, 20 |
| API `GET /api/account`, `GET /api/admin/customers*` | response shape | AC 23, 25 |
| API `POST /api/account/addresses`, `PUT /api/account/addresses/{address}`, `GET /api/shipping/quote` | response shape | AC 28, 29 |
| API `POST /api/account/addresses`, `PUT /api/account/addresses/{address}`, `GET /api/shipping/quote` | error shape and codes | AC 30 |
| all touched routes | who may call it | existing - `auth:customer` na conta, guard `staff` no admin, cotação sem sessão; nada muda |
| all touched routes | versioning, rate limits | n/a - o projeto não versiona a API e nenhuma rota, middleware ou limite muda |
| collection `BOUNDARY_EXCEPTIONS` | grouping, ordering, the exception that does not fit | AC 10, 11 |
| collection vocabulário público | grouping criterion, duplicates, naming | AC 1, 9; um tipo exposto por vários contratos entra uma vez, e nenhum nome muda |
| document README, análise de domínio, `AGENTS.md` | structure, depth, what the reader does next | existing - o `AGENTS.md` define onde e como atualizar; os pontos estão na linha docs do Impact |

## Sources

- [HEL-10](https://linear.app/helter/issue/HEL-10/fechar-as-travessias-de-http-value-objects-enums-e-dtos-entre-modulos) - a pergunta da rodada e o critério de pronto
- [.design/module-vocabulary.md](../../../.design/module-vocabulary.md) - o design confirmado em 2026-10-09: Key decisions 1 a 5, a forma e os slices
