# Vocabulário público dos módulos - checks

Profile: standard
Plan: `.specs/features/module-vocabulary/plan.md`

37 checks in 4 slices e a documentação · 4 one-way doors · 0 open

Comandos: o backend roda via `docker compose exec -T api ./vendor/bin/pest --filter="<nome do teste>"`, no mesmo container do `make test` que o CI executa. O Pint roda via `docker compose exec -T api ./vendor/bin/pint --test`, como no CI. Os nomes de teste novos não usam parênteses, colchetes nem barras, porque o `--filter` do Pest é uma expressão regular. As descrições geradas pelo `ModuleBoundariesTest` seguem o formato de hoje, com o módulo de origem e o alvo no texto.

"Rota de cliente" quer dizer as três rotas de conta do Customers: `POST /api/admin/customers` (sessão de equipe), `PUT /api/admin/customers/{customer}` (sessão de equipe) e `PUT /api/account/profile` (sessão de cliente, com `current_password` `password` quando escolhe uma senha), como o `accountRoutes()` do `EmailAndPasswordTest` já monta. As mensagens são as de `lang/pt_BR/validation.php`, com `APP_LOCALE=pt_BR`.

Os checks marcados com "exercício temporário" provam uma propriedade do teste de fronteira com uma mudança de propósito no código, revertida logo depois e nunca commitada, como o C7 da feature `module-facades`. Durante o exercício, `git diff --quiet -- backend/tests` precisa sair com 0, ou seja, o teste não é editado.

Testes existentes que mudam, sem afrouxar nenhuma asserção:
- `AccountRequestRulesTest` "keeps the email and password rules out of the account form requests": o dataset passa a dizer de qual módulo são as regras esperadas. Os quatro requests do Identity continuam com o `EmailRule` e o `PasswordRule` do Identity, e os três do Customers passam para os do Customers (C16);
- `EmailAndPasswordTest` "rejects a password shorter than 8 characters on every route that chooses one" passa a comparar `errors.password` inteiro, e não só o primeiro item (C21);
- `AccountTest` "returns the account summary under the customer key without a role" ganha a asserção do `created_at` em ISO 8601 (C25);
- `ModuleBoundariesTest` "generates the private directory rules from the folders that exist" passa a exigir `Http` entre as privadas e nenhuma das pastas de vocabulário (C6). "declares exactly the HEL-6 exceptions" passa a listar nove entradas (C11).

## Checks

### S1 - ModuleBoundariesTest · 1 arquivo · 17 KB · ~4k

**C1** - O vocabulário de um módulo é lido das interfaces de `Contracts` e das classes de `Events` dele, e guarda só as classes declaradas nele (AC 1, door 1):
- as classes lidas para o Ordering incluem `CustomerOrderHistory` (contrato) e `OrderPlaced` (evento);
- as lidas para o Payment incluem `PaymentGateway` e `PaymentApproved`;
- o vocabulário do Inventory contém `Inventory\ValueObjects\StockQuantities` e não contém `Catalog\ValueObjects\ProductIds`, que o `StockLevels` expõe mas é declarado no Catalog;
- o vocabulário do Catalog contém `ProductIds`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="computes the vocabulary of a module from its own contracts and events"`

**C2** - Sobre um contrato e uma classe de teste (fixtures), o percurso das assinaturas alcança e deixa de fora exatamente o que a Key decision 1 diz (AC 1, door 1).

O contrato de teste tem quatro métodos: `pay(OrderForPayment $order): ?CustomerProfile`, `register(CreateUserDTO $data): void`, `quote(ShippingQuote|DeliveryAddress $target): int` e `at(CarbonImmutable $when, ApiErrorCode $code): static`. A classe de teste tem uma propriedade pública `OrderStatus $status`, uma propriedade privada `ValidatedCartLine $line` e um método privado `lines(OrderLines $lines): ProductQuantities`.

O conjunto alcançado:
- contém `OrderForPayment` (parâmetro), `CustomerProfile` (retorno anulável), `ShippingQuote` e `DeliveryAddress` (união), `CreateUserDTO` (parâmetro), `Email` e `Password` (parâmetros do construtor de `CreateUserDTO`, transitivo) e `OrderStatus` (propriedade pública da classe de teste e de `OrderForPayment`);
- não contém `CarbonImmutable` (framework), `App\Modules\Shared\Enums\ApiErrorCode` (`Shared`), nenhum nome embutido nem `static`, `ValidatedCartLine` (propriedade privada), `OrderLines` e `ProductQuantities` (método privado).

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="follows the native parameter, return and public property types transitively"`

**C3** - Um contrato de teste com `/** @return list<ValidatedCart> */ public function carts(): array;` e `/** @param Collection<int, OrderLines> $lines */ public function keep(Collection $lines): void;` não alcança nem `ValidatedCart` nem `OrderLines` (AC 7, Key decision 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps a type out of the vocabulary when only a docblock names it"`

**C4** - Sobre o código de hoje, o vocabulário calculado (AC 9):
- contém `Identity\ValueObjects\Email`, `Identity\ValueObjects\Password`, `Ordering\Enums\OrderStatus` e `Payment\Enums\DeclineReason`;
- não contém `Ordering\ValueObjects\ValidatedCart`, `Ordering\ValueObjects\OrderLines`, `Ordering\ValueObjects\ProductQuantities`, `Identity\DTOs\LoginCredentialsDTO` e `Identity\Enums\UserRole`.

O teste afirma só esses nove membros e não guarda a lista inteira (Key decision 3).
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="computes the vocabulary the contracts expose today"`

**C5** - Para cada par de módulos distintos A e B, com B diferente de `Shared`, e cada classe C de `B\ValueObjects`, `B\Enums` e `B\DTOs` fora do vocabulário de B, existe uma expectativa de que `App\Modules\A` não usa C. A descrição é `"{A} uses only the public vocabulary of {B}: {C}"`. As expectativas são geradas a partir dos arquivos que existem, e o teste afirma três coisas sobre a lista de classes fora do vocabulário (AC 2, door 1):
- tem pelo menos 23 entradas;
- contém `ValidatedCart`, `LoginCredentialsDTO`, `UserRole` e `Catalog\ValueObjects\CategoryIds`;
- não contém `Email`, `OrderStatus` nem `CatalogProducts`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="uses only the public vocabulary of"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="generates the vocabulary rules from the classes that exist"`

**C6** - As pastas públicas são só `Contracts` e `Events`. A lista de pastas privadas, gerada das pastas que existem, contém `Http` e as 9 de hoje (`Exceptions`, `Gateways`, `Jobs`, `Listeners`, `Models`, `Policies`, `Repositories`, `Services`, `UseCases`), e não contém `Contracts`, `Events`, `DTOs`, `Enums` nem `ValueObjects`. Para cada par de módulos distintos A e B, com B diferente de `Shared`, existe uma expectativa de que `App\Modules\A` não usa `App\Modules\B\Http`, e nenhuma entrada de `BOUNDARY_EXCEPTIONS` aponta para um `Http` (AC 3, door 2)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="generates the private directory rules from the folders that exist"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reaches another module only through its public directories"`

**C7** - Um uso do vocabulário passa quando nenhuma regra de direção o proíbe: o `Customers\Models\CustomerAddress` importa `Ordering\Enums\BrazilianState` sem chamar nenhum contrato, e o `ModuleBoundariesTest` passa (AC 4)
Proof: `grep -q 'use App\\Modules\\Ordering\\Enums\\BrazilianState;' backend/app/Modules/Customers/Models/CustomerAddress.php`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="uses only the public vocabulary of Ordering"`

**C8** - Exercício temporário (AC 5, Key decision 3):
- com `use App\Modules\Ordering\ValueObjects\ValidatedCart;` em `backend/app/Modules/Payment/Services/PaymentService.php` e um método `public function probe(): ?ValidatedCart { return null; }` em `Ordering\Events\OrderPlaced`, a expectativa do Payment sobre o Ordering passa;
- sem o método, só com o `use`, ela falha;
- nos dois casos o arquivo de teste não é editado.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="Payment uses only the public vocabulary of Ordering"` com o método e o `use` aplicados: sai com 0; só com o `use`: sai com código diferente de zero

**C9** - Exercício temporário: uma classe nova `backend/app/Modules/Ordering/ValueObjects/VocabularyProbe.php` (`final readonly`, sem uso em contrato nem evento), usada em `Payment\Services\PaymentService`, faz a expectativa falhar com uma descrição que nomeia `App\Modules\Ordering\ValueObjects\VocabularyProbe`, sem edição no arquivo de teste (AC 6)
Proof: `docker compose exec -T -e COLUMNS=250 api ./vendor/bin/pest --filter="Payment uses only the public vocabulary of Ordering"` com a classe e o `use` aplicados: sai com código diferente de zero e a saída contém `VocabularyProbe` (sem o `COLUMNS`, o Pest corta a descrição na largura do terminal)

**C10** - Exercício temporário: uma interface nova `backend/app/Modules/Inventory/Contracts/CartProbe.php` com `public function probe(ValidatedCart $cart): void;` (o `ValidatedCart` do Ordering num parâmetro de um contrato do Inventory, e não só num `use`) faz falhar a expectativa `Inventory uses only the public vocabulary of Ordering: App\Modules\Ordering\ValueObjects\ValidatedCart`. Um contrato de A não torna público um tipo interno de B (AC 8)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="Inventory uses only the public vocabulary of Ordering"` com a interface aplicada: sai com código diferente de zero

**C11** - A lista de exceções do teste é exatamente esta, nesta ordem, cada uma com a issue `HEL-6` (AC 10, door 3):
- `Backoffice` → `Catalog\Repositories`;
- `Backoffice` → `Catalog\Enums\ProductStatus`;
- `Backoffice` → `Identity\Repositories`;
- `Backoffice` → `Inventory\Repositories`;
- `Backoffice` → `Ordering\Repositories`;
- `Catalog\Models` → `Inventory\Models\Stock`;
- `Inventory\Models` → `Catalog\Models\Product`;
- `Catalog\Http` → `Ordering\Services\PurchaseAvailabilityService`;
- `Inventory\Http` → `Ordering\Services\PurchaseAvailabilityService`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="declares exactly the HEL-6 exceptions"`

**C12** - A exceção do `ProductStatus` vale só para ele e só para o Backoffice (AC 11, door 3):
- as expectativas de que Customers, Fulfillment, Identity, Inventory, Ordering e Payment não usam `Catalog\Enums\ProductStatus` existem e passam;
- o Backoffice continua com expectativas sobre as outras classes do Catalog fora do vocabulário, como `CategoryIds` e `ProductDTO`;
- exercício temporário: um `use App\Modules\Catalog\Enums\ProductStatus;` em `backend/app/Modules/Ordering/Services/CheckoutService.php` faz o teste falhar;
- exercício temporário: um `use App\Modules\Catalog\ValueObjects\CategoryIds;` em `backend/app/Modules/Backoffice/Services/DashboardService.php` faz o teste falhar.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="uses only the public vocabulary of Catalog"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="uses only the public vocabulary of Catalog"` com cada um dos dois `use` aplicado, um de cada vez: sai com código diferente de zero

**C13** - Quatro violações de propósito, uma de cada vez, fazem o teste sair com código diferente de zero. Revertidas, ele passa (AC 12):
- (a) value object: `use App\Modules\Ordering\ValueObjects\ValidatedCart;` em `backend/app/Modules/Payment/Services/PaymentService.php`;
- (b) enum: `use App\Modules\Identity\Enums\UserRole;` em `backend/app/Modules/Customers/UseCases/ListCustomersUseCase.php`;
- (c) DTO: `use App\Modules\Catalog\DTOs\ProductDTO;` em `backend/app/Modules/Ordering/Services/CheckoutService.php`;
- (d) `Http`: `use App\Modules\Identity\Http\Rules\EmailRule;` em `backend/app/Modules/Customers/Http/Requests/UpdateProfileRequest.php`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="uses only the public vocabulary of"` com (a), (b) e (c), uma de cada vez: sai com código diferente de zero
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reaches another module only through its public directories"` com (d): sai com código diferente de zero

**C14** - Todas as regras que o `ModuleBoundariesTest` tem hoje continuam presentes e passando (AC 13)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not use another module root class"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps only interfaces in every module contracts"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="speaks only data in every contract signature"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="resolves the module contracts to their implementations"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reaches the inventory only through its contracts"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="the catalog does not use the ordering"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="identity does not depend on ordering"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not use the staff user"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="never changes the order status itself"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="payment does not know fulfillment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="fulfillment does not know payment"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ordering does not know customers"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="ordering reaches fulfillment only through its events"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="fulfillment does not know customers"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="reaches the payment gateway only through its contract"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="the shared kernel does not depend on"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="repositories do not use the module services"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps every module named by these rules"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="identity value objects are final and readonly"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="does not write the password policy itself"`

**C15** - Na branch terminada, o `ModuleBoundariesTest` inteiro passa, `make test` passa e o Pint não acusa nada (AC 14)
Proof: `docker compose exec -T api ./vendor/bin/pest tests/Unit/Architecture/ModuleBoundariesTest.php`
Proof: `make test`
Proof: `docker compose exec -T api ./vendor/bin/pint --test`

### S2 - EmailRule e PasswordRule · 9 arquivos · 16 KB · ~4k

**C16** - Os três requests de conta do Customers usam os adaptadores do próprio módulo, e os quatro do Identity continuam com os do Identity (AC 15, door 4):
- `StoreCustomerRequest`, `UpdateCustomerRequest` e `UpdateProfileRequest` têm em `email` uma instância de `App\Modules\Customers\Http\Rules\EmailRule`, e não do Identity;
- os três têm em `password` uma instância de `App\Modules\Customers\Http\Rules\PasswordRule`;
- os três usam o trait `App\Modules\Customers\Http\Requests\Concerns\NormalizesEmailInput`;
- `RegisterRequest`, `LoginRequest`, `StoreStaffMemberRequest` e `UpdateStaffMemberRequest` continuam com o `EmailRule`, o `PasswordRule` (o `LoginRequest` sem ele) e o `NormalizesEmailInput` do Identity;
- nenhum dos sete tem `email`, `max:255` ou o `Password` do Laravel.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the email and password rules out of the account form requests"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes the email with the trait of its own module"`

**C17** - Em cada rota de cliente, o e-mail `"  Ana@Example.COM "` responde `201` na criação e `200` nas duas edições, com `data.email` `"ana@example.com"`, e a linha de `customers` fica com `"ana@example.com"` (AC 16)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes an email with spaces and capitals on every customer route"`

**C18** - Com outro cliente já cadastrado como `ana@example.com`, cada rota de cliente que recebe `" ANA@Example.com "` responde `422` com `errors.email` igual a `["O valor informado para e-mail já está em uso."]`. Nenhuma conta é criada nem alterada (AC 17)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects the email of another customer in another case on every customer route"`

**C19** - Em cada rota de cliente, `email` `"not-an-email"` responde `422` com `errors.email` igual a `["O campo e-mail deve ser um e-mail válido."]` (AC 18)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a malformed email on every customer route"`

**C20** - Em cada rota de cliente, um e-mail de 256 caracteres responde `422` com `errors.email` igual a `["O campo e-mail não pode ter mais de 255 caracteres."]`. Um de 255 caracteres é aceito: `201` na criação e `200` nas edições (AC 19)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="bounds the email at 255 characters on every customer route"`

**C21** - Em cada rota que escolhe senha, inclusive as três de cliente, uma senha de 7 caracteres com `password_confirmation` igual responde `422` com `errors.password` igual a `["O campo senha deve ter pelo menos 8 caracteres."]`. Nas três rotas de cliente, uma senha de 8 caracteres (`abcd1234`) é aceita, e o hash gravado confere com ela (AC 20)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a password shorter than 8 characters on every route that chooses one"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="accepts a password of 8 characters on every customer route"`

**C22** - Em cada rota de cliente, um `email` que não é string (`["ana@example.com"]`) responde `422` com erro em `errors.email`, e uma `password` que não é string (`["abcd1234"]`) responde `422` com erro em `errors.password`. Nunca responde `500` (Test policy: a linha "não é string" de cada adaptador)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects an email or a password that is not a string on every customer route"`

**C23** - `PUT /api/account/profile` com `name` `"Ana Nova"`, `email` `"nova@example.com"`, `password` `""` e `password_confirmation` `""` responde `200`. O nome e o e-mail gravados passam a ser esses, e o hash da senha continua o mesmo de antes (AC 21)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="keeps the password when the profile is saved with a blank password"`

**C24** - Os seis adaptadores novos não escrevem nenhuma regra. São eles: `Customers\Http\Rules\EmailRule`, `Customers\Http\Rules\PasswordRule`, `Customers\Http\Requests\Concerns\NormalizesEmailInput`, `Customers\Http\Requests\Concerns\NormalizesStateInput`, `Customers\Http\Resources\CustomerProfileResource` e `Fulfillment\Http\Requests\Concerns\NormalizesStateInput` (AC 22, door 4).

Pelos tokens do PHP, fora do `declare(strict_types=1)` do cabeçalho, nenhum dos seis tem:
- literal numérico (`T_LNUMBER` ou `T_DNUMBER`);
- chamada a função `preg_*`;
- string literal com exatamente duas letras maiúsculas;
- string literal começando por `/`, `#` ou `~`.

Pelos arch tests, as regras vêm do vocabulário: o `EmailRule` e o `NormalizesEmailInput` do Customers usam `Identity\ValueObjects\Email`, o `PasswordRule` usa `Identity\ValueObjects\Password`, e os dois `NormalizesStateInput` usam `Ordering\Enums\BrazilianState`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="writes no limit, pattern or state list in the http adapters"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="delegates the http adapters to the vocabulary"`

### S3 - CustomerProfileResource · 6 arquivos · 15 KB · ~4k

**C25** - `GET /api/account` responde `200` com `data.customer` tendo exatamente as chaves `id`, `name`, `email` e `created_at`, e `data.customer.created_at` igual ao `created_at` da conta em `toIso8601String()` (AC 23, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="returns the account summary under the customer key without a role"`

**C26** - `PUT /api/account/profile` com nome e e-mail novos responde `200`, com `data` tendo exatamente as chaves `id`, `name`, `email` e `created_at` e `message` `"Dados atualizados com sucesso."` (AC 24)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="updates name and email"`

**C27** - Na área de clientes do admin, com sessão de equipe, cada cliente sai com as mesmas chaves de hoje, e `created_at` igual ao da conta em `toIso8601String()` (AC 25):
- a listagem (`GET /api/admin/customers`, cada item de `data`), a criação (`POST`, `201`) e a edição (`PUT`, `200`): exatamente `id`, `name`, `email`, `created_at` e `orders_count`;
- o detalhe (`GET /api/admin/customers/{customer}`, `200`): exatamente essas chaves mais `orders`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="renders the account fields on every admin customer route"`

**C28** - Um mesmo `CustomerProfile` (id, nome, e-mail e um `createdAt` fixo) é mostrado pelo `App\Modules\Identity\Http\Resources\CustomerProfileResource` e pelo `App\Modules\Customers\Http\Resources\CustomerProfileResource`. Os dois arrays resolvidos são idênticos por `toBe`, e os dois são iguais a `['id' => <id>, 'name' => <nome>, 'email' => <e-mail>, 'created_at' => <createdAt em toIso8601String()>]`, nessa ordem de chaves (AC 26, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="renders the customer account in the same shape in identity and customers"`

**C29** - Três mudanças de propósito no resource do Customers, uma de cada vez, fazem o teste de formato falhar. Revertidas, ele passa (AC 27, door 4):
- (a) a chave `email` vira `e_mail`;
- (b) uma chave `role` é acrescentada;
- (c) o `created_at` sai em `toDateTimeString()`.

Proof: `docker compose exec -T api ./vendor/bin/pest --filter="renders the customer account in the same shape in identity and customers"` com cada mudança aplicada, uma de cada vez: sai com código diferente de zero

### S4 - NormalizesStateInput · 5 arquivos · 15 KB · ~4k

**C30** - `POST /api/account/addresses` com `state` `" sp "` responde `201` com `data.state` `"SP"`, e o endereço é gravado com `SP` (AC 28)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes the state and an empty complement"`

**C31** - `PUT /api/account/addresses/{address}` do próprio cliente com `state` `" sp "` responde `200` com `data.state` `"SP"`, e o endereço passa a ter `SP` (AC 28)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes the state when updating an address"`

**C32** - `GET /api/shipping/quote?state=%20sp%20` responde `200` com `data.state` `"SP"` (AC 29)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes a state with spaces in the quote"`

**C33** - `state` `"XX"` responde `422` com erro em `errors.state` no `POST /api/account/addresses`, no `PUT /api/account/addresses/{address}` e no `GET /api/shipping/quote`. Nenhum endereço é criado nem alterado (AC 30)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a state outside the 27 states"`
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a quote without a valid state"`

O primeiro filtro pega também o teste novo "rejects a state outside the 27 states when updating an address".

**C34** - Um `state` que não é string (`["SP"]` no corpo, `?state[]=SP` na cotação) responde `422` com erro em `errors.state` nas mesmas três rotas, e nunca `500` (Test policy: a linha "não é string" do `NormalizesStateInput` de cada módulo)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="rejects a state that is not a string"`

**C35** - `class_uses(CustomerAddressRequest)` contém `App\Modules\Customers\Http\Requests\Concerns\NormalizesStateInput`, e `class_uses(ShippingQuoteRequest)` contém `App\Modules\Fulfillment\Http\Requests\Concerns\NormalizesStateInput` (AC 31, door 4)
Proof: `docker compose exec -T api ./vendor/bin/pest --filter="normalizes the state with the trait of its own module"`

**C36** - O arquivo `backend/app/Modules/Ordering/Http/Requests/Concerns/NormalizesStateInput.php` não existe (AC 32)
Proof: `test ! -e backend/app/Modules/Ordering/Http/Requests/Concerns/NormalizesStateInput.php`

### Documentação · 3 arquivos · 150 KB · ~38k

**C37** - O README, a análise de domínio e o `AGENTS.md` descrevem a regra nova e não citam mais o estado antigo (plano, Impact, linha docs):
- nenhum dos três contém "até a HEL-10";
- nenhum dos três contém o caminho `Ordering/Http/Requests/Concerns`;
- a pendência "Um módulo usa `Http`, `ValueObjects`, `Enums` e `DTOs` de outro", em "Pendências depois do plano" na análise de domínio, passa a começar com "✅ Resolvida:", no mesmo padrão da linha dos eventos.

Proof: `! grep -rniq "até a HEL-10" README.md AGENTS.md docs/domain-analysis.md`
Proof: `! grep -rnq "Ordering/Http/Requests/Concerns" README.md AGENTS.md docs/domain-analysis.md`
Proof: `grep -qi "^| ✅ Resolvida: um módulo usa .Http., .ValueObjects., .Enums. e .DTOs. de outro" docs/domain-analysis.md`

## Coverage

| Set (size) | Member -> proof | Unproven |
| --- | --- | --- |
| `POST /api/admin/customers` statuses (2) | 201 C17, C20, C21 · 422 C18, C19, C20, C21, C22 | - |
| `PUT /api/admin/customers/{customer}` statuses (2) | 200 C17, C20, C21, C27 · 422 C18, C19, C20, C21, C22 | - |
| `PUT /api/account/profile` statuses (2) | 200 C17, C20, C21, C23, C26 · 422 C18, C19, C20, C21, C22 | - |
| `GET /api/account` statuses (1) | 200 C25 | - |
| `GET /api/admin/customers` e `GET /api/admin/customers/{customer}` statuses (1) | 200 C27 | - |
| `POST /api/account/addresses` statuses (2) | 201 C30 · 422 C33, C34 | - |
| `PUT /api/account/addresses/{address}` statuses (2) | 200 C31 · 422 C33, C34 | - |
| `GET /api/shipping/quote` statuses (2) | 200 C32 · 422 C33, C34 | - |
| categorias de violação (4) | value object C13 · enum C13 · DTO C13 · `Http` C13, C6 | - |
| pastas públicas (2) | `Contracts` C6, C1 · `Events` C6, C1 | - |
| pastas privadas por namespace (10) | C6, table-driven sobre as pastas que existem, afirmando as 10: `Exceptions`, `Gateways`, `Http`, `Jobs`, `Listeners`, `Models`, `Policies`, `Repositories`, `Services`, `UseCases` · violação de `Http` vista falhando C13 | - |
| classes de vocabulário fora do vocabulário hoje (23) | C5, table-driven sobre os arquivos que existem, afirmando pelo menos 23 · violação vista falhando C13 | - |
| regras do percurso das assinaturas (12) | parâmetro C2 · retorno anulável C2 · união C2 · propriedade pública C2 · parâmetro de construtor C2 · transitivo C2, C4 · propriedade privada fora C2 · método privado fora C2 · docblock fora C3 · `Shared` fora C2 · framework, embutidos e `static` fora C2 · só classes do próprio módulo C1 | - |
| de onde o vocabulário é lido (2) | interfaces de `Contracts` C1 · classes de `Events` C1, C8 | - |
| membros conhecidos do vocabulário (9) | `Email` dentro C4 · `Password` dentro C4 · `OrderStatus` dentro C4 · `DeclineReason` dentro C4 · `ValidatedCart` fora C4 · `OrderLines` fora C4 · `ProductQuantities` fora C4 · `LoginCredentialsDTO` fora C4 · `UserRole` fora C4 | - |
| propriedades da regra que não dependem de editar o teste (3) | tipo novo numa assinatura fica público C8 · classe nova fica privada C9 · contrato de outro módulo não libera C10 | - |
| travessias de `Http` de hoje (14) | `StoreCustomerRequest` → `EmailRule` C16 · `UpdateCustomerRequest` → `EmailRule` C16 · `UpdateProfileRequest` → `EmailRule` C16 · `StoreCustomerRequest` → `PasswordRule` C16 · `UpdateCustomerRequest` → `PasswordRule` C16 · `UpdateProfileRequest` → `PasswordRule` C16 · `StoreCustomerRequest` → `NormalizesEmailInput` C16 · `UpdateCustomerRequest` → `NormalizesEmailInput` C16 · `UpdateProfileRequest` → `NormalizesEmailInput` C16 · `AccountController` → `CustomerProfileResource` C25, C6 · `ProfileController` → `CustomerProfileResource` C26, C6 · `CustomerSummaryResource` → `CustomerProfileResource` C27, C6 · `CustomerAddressRequest` → `NormalizesStateInput` C35, C6 · `ShippingQuoteRequest` → `NormalizesStateInput` C35, C6 | - |
| exceções da HEL-6 (9) | `Backoffice` → `Catalog\Repositories` C11 · `Backoffice` → `ProductStatus` C11, C12 · `Backoffice` → `Identity\Repositories` C11 · `Backoffice` → `Inventory\Repositories` C11 · `Backoffice` → `Ordering\Repositories` C11 · `Catalog\Models` → `Stock` C11 · `Inventory\Models` → `Product` C11 · `Catalog\Http` → `PurchaseAvailabilityService` C11 · `Inventory\Http` → `PurchaseAvailabilityService` C11 | - |
| módulos que não podem usar o `ProductStatus` (6) | C12, table-driven sobre os 6: Customers, Fulfillment, Identity, Inventory, Ordering, Payment · violação vista falhando C12 | - |
| adaptadores novos (6) | Customers `EmailRule` C24, C16 · Customers `PasswordRule` C24, C16 · Customers `NormalizesEmailInput` C24, C16 · Customers `NormalizesStateInput` C24, C35 · Customers `CustomerProfileResource` C24, C28 · Fulfillment `NormalizesStateInput` C24, C35 | - |
| linhas do `EmailRule` do Customers (4) | não é string C22 · mais de 255 C20 · mal formado C19 · válido C17, C20 | - |
| linhas do `PasswordRule` do Customers (3) | não é string C22 · menos de 8 C21 · 8 ou mais C21 | - |
| linhas do `NormalizesEmailInput` do Customers (2) | string, normalizada C17, C18 · não é string, intocada C22 | - |
| linhas dos dois `NormalizesStateInput` (4) | Customers com string C30, C31 · Customers sem string C34 · Fulfillment com string C32 · Fulfillment sem string C34 | - |
| limite do e-mail (2 bordas) | 255 aceito C20 · 256 recusado C20 | - |
| limite da senha (2 bordas) | 7 recusada C21 · 8 aceita C21 | - |
| senha em branco na edição do perfil (1) | hash mantido C23 | - |
| chaves da conta (4) | `id` C25, C26, C27, C28 · `name` C25, C26, C27, C28 · `email` C25, C26, C27, C28 · `created_at` C25, C26, C27, C28 | - |
| lugares que mostram a conta (4) | `GET /api/account` C25 · `PUT /api/account/profile` C26 · admin de clientes C27 · Identity e Customers lado a lado C28 | - |
| mudanças de formato que o teste de formato pega (3) | chave renomeada C29 · chave acrescentada C29 · formato do `created_at` C29 | - |
| requests de conta (7) | `RegisterRequest` C16 · `LoginRequest` C16 · `StoreStaffMemberRequest` C16 · `UpdateStaffMemberRequest` C16 · `StoreCustomerRequest` C16 · `UpdateCustomerRequest` C16 · `UpdateProfileRequest` C16 | - |
| regras que o teste já tem (21) | pastas privadas C6 · classes da raiz C14 · contratos só com interfaces C14 · contratos falam em dados C14 · ligações dos contratos C14 · estoque só por contrato C14 · Catalog sem models e repositories do Ordering C14 · Identity sem Ordering C14 · sem o `User` da equipe C14 · sem o `OrderRepository` no Payment e no Fulfillment C14 · Payment sem Fulfillment C14 · Fulfillment sem Payment C14 · Ordering sem Customers C14 · Ordering só com os eventos do Fulfillment C14 · Fulfillment sem Customers C14 · gateway só pelo contrato C14 · Shared sem módulos de negócio C14 · repositories sem services C14 · módulos nomeados existem C14 · value objects do Identity C14 · política de senha C14 | - |
| doors do `Landing` (4) | door 1 C1, C2, C3, C4, C5, C7, C8, C9, C10, C13 · door 2 C6, C13 · door 3 C11, C12 · door 4 C16, C24, C25, C28, C29, C35 | - |
| documentos (3) | README C37 · análise de domínio C37 · `AGENTS.md` C37 | - |

- As afirmações sobre status, rota ou formato de resposta (C17 a C23, C25 a C27, C30 a C34) têm prova que atravessa o HTTP.
- C1 a C6, C11, C12 e C14 são provas estruturais por arch test ou reflexão. C2 e C3 provam o percurso na própria camada, com classes de teste. C8 a C10, C12, C13 e C29 são injeções de falha que mostram que essas provas falham.
- C16, C24, C28 e C35 provam a ligação dos adaptadores na própria camada, além das provas de borda.
- O plano não tem `Surface` nem `Relations`. As linhas de status acima cobrem as rotas que os critérios citam, todas sem mudança de assinatura.
- Testes novos: C1 a C5, "generates the vocabulary rules from the classes that exist" e C17 a C20. Também são novos C21 ("accepts a password of 8 characters on every customer route"), C22 a C24, C27 a C28, C31, C32, a parte de PUT de C33, C34 e C35. Os demais reaproveitam testes existentes, reforçados sem afrouxar asserções.

## Test policy

O README diz onde ficam e como rodar os testes, mas não diz qual nível prova cada tipo de código. As linhas abaixo valem para esta feature, no mesmo molde das features anteriores.

| Code | Required proofs | Coverage expectation |
| --- | --- | --- |
| Regra de fronteira calculada (o percurso das assinaturas no `ModuleBoundariesTest`) | uma na própria camada, com classes de teste, **e** uma sobre o código real, mais a injeção de falha | um caso por regra do percurso: o que entra e o que fica de fora |
| Regra de fronteira gerada (expectativas por classe e por namespace) | uma expectativa de arquitetura por classe ou namespace, gerada do que existe, vista falhando com uma violação de propósito | uma violação por categoria; a lista de exceções comparada literalmente |
| Adaptador HTTP que decide (`EmailRule`, `PasswordRule`, `NormalizesEmailInput`, `NormalizesStateInput`) | uma na borda, pelo adaptador de cada módulo, e a ligação do adaptador ao request na própria camada | um caso por linha da tabela de decisão, em cada rota que usa o adaptador |
| Instrumentação (`CustomerProfileResource`, Form Requests que só montam regras, controllers) | nenhuma própria além do teste de formato da conta | coberta pelas provas de borda, pelo teste de formato e pelo `AccountRequestRulesTest` |

Evidence:

- percurso das assinaturas: 8 pontos de decisão (tipo embutido, `self` ou `static`, união, prefixo `App\Modules`, `Shared`, visibilidade, já visitado, dono do tipo) → decide
- `EmailRule` do Identity, que o do Customers copia: 3 pontos de decisão, 4 linhas (não é string, longo demais, mal formado, válido) → decide
- `PasswordRule`: 1 ponto composto, 3 linhas (não é string, curta, válida) → decide
- `NormalizesEmailInput` e `NormalizesStateInput`: 1 ponto cada, 2 linhas (string, não string) → decide
- `CustomerProfileResource`: mapeia 4 campos sem condição → instrumentação
- análogos no repositório: o `EmailRule` e o `PasswordRule` do Identity são provados na borda, rota a rota, pelo `EmailAndPasswordTest` ("rejects a malformed email on every account route", "bounds the email at 255 characters", "rejects an email that is not a string"). A ligação aos requests é provada pelo `AccountRequestRulesTest`. A leitura de assinaturas por reflexão é provada por "speaks only data in every contract signature"

Cost: 12 testes novos. 2 deles provam o percurso com classes de teste (C2, C3) e 2 provam as linhas "não é string" que hoje só têm prova no cadastro (C22, C34). Somam-se 4 conjuntos de injeção de falha (C8 a C10, C12, C13, C29). Sem estas linhas, as linhas "não é string" dos adaptadores copiados e as regras de exclusão do percurso seriam provadas só pelo caminho que o código de hoje atravessa.

## Swept

- validation: C17 a C22, porque e-mail e senha continuam com os mesmos limites e mensagens nas três rotas de cliente; C33 e C34, pela UF
- failure modes: C22 e C34, porque um valor que não é string chega à regra `string` e responde `422`, nunca `500`, nos adaptadores copiados
- idempotency: n/a - nenhuma escrita nova; as rotas tocadas fazem as mesmas gravações de hoje, e repetir um `PUT` continua dando o mesmo resultado
- authorization: existing - `auth:customer` em `/api/account*`, o guard `staff` em `/api/admin/customers*` e a `CustomerAddressPolicy` no `PUT` de endereço; nenhuma rota, middleware ou policy muda
- concurrency: n/a - nenhuma escrita nem ordem nova; o e-mail único continua garantido pelo índice único e pelo `CHECK customers_email_normalized` do banco
- data lifecycle: n/a - nenhuma tabela, coluna ou dado muda (plano, Relations)
- dependency failure: n/a - nenhuma dependência externa entra nem sai; o teste de fronteira só lê o código por reflexão e por análise estática
- state transitions: n/a - nenhum status de pedido, pagamento ou conta muda
- observability: C5, porque a falha do teste nomeia o módulo de origem e a classe atravessada; nenhum log da aplicação muda

## Handoff

- S1 = 4k, S2 = 4k, S3 = 4k e S4 = 4k: 16k de código e testes (`wc -c` dos arquivos que cada slice toca, dividido por 4), mais 38k do README, da análise de domínio e do `AGENTS.md`. Somam 54k, abaixo do budget de 150k - one builder
- Mechanism: one builder (cabe no orçamento, sem pergunta)
- **Boundary:** C1-C37 fechados em `92be3cf..HEAD` (S2 `67441ab`, S3 `ae6eeb2`, S4 `5eb3047`, S1 `af7a9ac`, docs `afceab8`, C24 e C35 no último commit)
- **Settled mid-build:** (1) C24: o `declare(strict_types=1)` do cabeçalho, presente em 208 dos 209 arquivos do `app`, tem um `T_LNUMBER`; o usuário escolheu que o teste ignore os tokens do `declare(...)` em vez de tirar o `declare` dos adaptadores, e o texto do C24 diz isso. (2) C9: a prova ganhou `-e COLUMNS=250`, porque a saída padrão corta o nome da classe (`ValueObj…yProbe`). (3) C13 (d): como o `UpdateProfileRequest` agora importa o `EmailRule` do Customers, o `use App\Modules\Identity\Http\Rules\EmailRule;` literal colide com esse nome e o parser do Pest aborta com "the name is already in use" (sai com código diferente de zero, mas por erro de análise). Com `use App\Modules\Identity\Http\Rules\EmailRule as IdentityEmailRule;`, falha exatamente a regra `Customers reaches another module only through its public directories: Identity\Http`
- **Abandoned:** nada
- **Fixed after verification round 1:** o exercício do C10 punha o `ValidatedCart` só num `use` do `StockLevels`, que falha também com o vocabulário calculado da união das facades de todos os módulos (a alternativa que o `Landing` rejeita; mutante F1 do relatório). O exercício passou a pôr o tipo numa assinatura de contrato do Inventory: com a regra de `HEAD`, 1 failed; com o mutante F1, exit 0. O código não mudou
