<?php

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Catalog\ValueObjects\CategoryIds;
use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Customers\Repositories\CustomerAddressRepository;
use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\DTOs\LoginCredentialsDTO;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\CustomerAccountRepository;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;
use App\Modules\Inventory\Contracts\StockInitializer;
use App\Modules\Inventory\Contracts\StockLevels;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Inventory\Repositories\StockRepository;
use App\Modules\Inventory\ValueObjects\StockQuantities;
use App\Modules\Ordering\Contracts\CustomerOrderHistory;
use App\Modules\Ordering\Contracts\DeliveryAddressBook;
use App\Modules\Ordering\Contracts\PayableOrders;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Ordering\ValueObjects\DeliveryAddress;
use App\Modules\Ordering\ValueObjects\OrderForPayment;
use App\Modules\Ordering\ValueObjects\OrderLines;
use App\Modules\Ordering\ValueObjects\ProductQuantities;
use App\Modules\Ordering\ValueObjects\ShippingQuote;
use App\Modules\Ordering\ValueObjects\ValidatedCart;
use App\Modules\Ordering\ValueObjects\ValidatedCartLine;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Enums\DeclineReason;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Payment\Gateways\FakePaymentGateway;
use App\Modules\Shared\Enums\ApiErrorCode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Tests\Unit\Architecture\Fixtures\DocblockProbeContract;
use Tests\Unit\Architecture\Fixtures\SignatureProbeContract;
use Tests\Unit\Architecture\Fixtures\SignatureProbeEvent;

/*
 * Boundaries between the modules (see docs/domain-analysis.md).
 *
 * Always one namespace per expectation: with an array of namespaces, `not->toUse` never fails.
 * And an expectation on a namespace that does not exist passes without checking anything,
 * so the last test makes sure every module named here still exists.
 */

const MODULES = ['Backoffice', 'Catalog', 'Customers', 'Fulfillment', 'Identity', 'Inventory', 'Ordering', 'Payment', 'Shared'];

const MODULES_PATH = __DIR__.'/../../../app/Modules';

/*
 * A module's facade is its Contracts and its Events: every other folder is private, so a new
 * folder is private without touching this file. Http included: each module has its own
 * adapters, even when they translate the same value object.
 */
const PUBLIC_DIRECTORIES = ['Contracts', 'Events'];

/*
 * The folders where a module's vocabulary lives. A class in them is public only when the
 * signatures of the module's facade reach it (see vocabularyOf), so the rule is computed on every
 * run and never kept as a list here.
 */
const VOCABULARY_DIRECTORIES = ['DTOs', 'ValueObjects', 'Enums'];

/*
 * Crossings into private folders, or into classes outside the vocabulary, that stay until HEL-6
 * builds the read side: the dashboard read model, and the storefront and stock list availability
 * (Product::stock, Stock::product and the one availability rule). Each entry: [source namespace,
 * target namespace or class, issue].
 */
const BOUNDARY_EXCEPTIONS = [
    ['App\\Modules\\Backoffice', 'App\\Modules\\Catalog\\Repositories', 'HEL-6'],
    ['App\\Modules\\Backoffice', 'App\\Modules\\Catalog\\Enums\\ProductStatus', 'HEL-6'],
    ['App\\Modules\\Backoffice', 'App\\Modules\\Identity\\Repositories', 'HEL-6'],
    ['App\\Modules\\Backoffice', 'App\\Modules\\Inventory\\Repositories', 'HEL-6'],
    ['App\\Modules\\Backoffice', 'App\\Modules\\Ordering\\Repositories', 'HEL-6'],
    ['App\\Modules\\Catalog\\Models', 'App\\Modules\\Inventory\\Models\\Stock', 'HEL-6'],
    ['App\\Modules\\Inventory\\Models', 'App\\Modules\\Catalog\\Models\\Product', 'HEL-6'],
    ['App\\Modules\\Catalog\\Http', 'App\\Modules\\Ordering\\Services\\PurchaseAvailabilityService', 'HEL-6'],
    ['App\\Modules\\Inventory\\Http', 'App\\Modules\\Ordering\\Services\\PurchaseAvailabilityService', 'HEL-6'],
];

/** @return list<string> the folders of a module that no other module may use */
function privateDirectoriesOf(string $module): array
{
    return array_values(array_diff(array_map('basename', glob(MODULES_PATH."/{$module}/*", GLOB_ONLYDIR)), PUBLIC_DIRECTORIES, VOCABULARY_DIRECTORIES));
}

/** @return list<class-string> the contract interfaces and the event classes of a module */
function facadeOf(string $module): array
{
    return array_map(
        fn (string $file) => 'App\\Modules\\'.$module.'\\'.basename(dirname($file)).'\\'.basename($file, '.php'),
        [...glob(MODULES_PATH."/{$module}/Contracts/*.php"), ...glob(MODULES_PATH."/{$module}/Events/*.php")],
    );
}

/** @return list<string> the names in a native type, with unions and intersections flattened */
function namedTypesIn(?ReflectionType $type): array
{
    if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
        return array_merge(...array_map('namedTypesIn', $type->getTypes()));
    }

    return $type instanceof ReflectionNamedType ? [$type->getName()] : [];
}

/**
 * The business module classes named by the native types of a class's public signature: the
 * parameters and return types of its public methods (the constructor included) and the types
 * of its public properties. Docblocks, built-in names, `self`, `static`, the framework and the
 * Shared kernel never count.
 *
 * @return list<class-string>
 */
function signatureTypesOf(string $class): array
{
    $reflection = new ReflectionClass($class);
    $types = array_map(fn (ReflectionProperty $property) => $property->getType(), $reflection->getProperties(ReflectionProperty::IS_PUBLIC));

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        $types = [...$types, $method->getReturnType(), ...array_map(fn (ReflectionParameter $parameter) => $parameter->getType(), $method->getParameters())];
    }

    return array_values(array_unique(array_filter(
        array_merge(...array_map('namedTypesIn', $types)),
        fn (string $name) => str_starts_with($name, 'App\\Modules\\') && ! str_starts_with($name, 'App\\Modules\\Shared\\'),
    )));
}

/** @return list<class-string> every business module class the signatures of these classes reach, transitively */
function reachedFrom(array $classes): array
{
    $reached = [];
    $pending = $classes;

    while ($pending !== []) {
        foreach (signatureTypesOf(array_pop($pending)) as $type) {
            if (! in_array($type, $reached, true)) {
                $reached[] = $type;
                $pending[] = $type;
            }
        }
    }

    return $reached;
}

/**
 * A module's public vocabulary: the classes it declares that its own facade reaches. A contract
 * of another module never makes them public, so B decides its vocabulary alone.
 *
 * @return list<class-string>
 */
function vocabularyOf(string $module): array
{
    return array_values(array_filter(
        reachedFrom(facadeOf($module)),
        fn (string $class) => str_starts_with($class, 'App\\Modules\\'.$module.'\\'),
    ));
}

/** @return list<class-string> the classes in a module's vocabulary folders that its facade does not reach */
function classesOutsideVocabularyOf(string $module): array
{
    $classes = [];

    foreach (VOCABULARY_DIRECTORIES as $directory) {
        foreach (glob(MODULES_PATH."/{$module}/{$directory}/*.php") as $file) {
            $classes[] = 'App\\Modules\\'.$module.'\\'.$directory.'\\'.basename($file, '.php');
        }
    }

    return array_values(array_diff($classes, vocabularyOf($module)));
}

/** @return list<string> the classes declared directly in a module folder (its service provider) */
function rootClassesOf(string $module): array
{
    return array_map(fn (string $file) => 'App\\Modules\\'.$module.'\\'.basename($file, '.php'), glob(MODULES_PATH."/{$module}/*.php"));
}

// Privacy: a module reaches another one only through its public folders (one namespace per expectation).
foreach (MODULES as $source) {
    foreach (array_diff(MODULES, [$source, 'Shared']) as $target) {
        foreach (privateDirectoriesOf($target) as $directory) {
            $targetNamespace = 'App\\Modules\\'.$target.'\\'.$directory;
            $exceptions = array_filter(BOUNDARY_EXCEPTIONS, fn (array $exception) => str_starts_with($exception[0], 'App\\Modules\\'.$source)
                && ($exception[1] === $targetNamespace || str_starts_with($exception[1], $targetNamespace.'\\')));

            // The whole folder is allowed to this source (the dashboard read model).
            if (array_filter($exceptions, fn (array $exception) => $exception[1] === $targetNamespace) !== []) {
                continue;
            }

            arch("{$source} reaches another module only through its public directories: {$target}\\{$directory}")
                ->expect('App\\Modules\\'.$source)
                ->not->toUse($targetNamespace)
                ->ignoring(array_values(array_map(fn (array $exception) => $exception[0], $exceptions)));

            // An excepted source may use the one excepted class, and nothing else in that folder.
            foreach ($exceptions as [$exceptedSource, $exceptedClass]) {
                foreach (glob(MODULES_PATH."/{$target}/{$directory}/*.php") as $file) {
                    $class = $targetNamespace.'\\'.basename($file, '.php');

                    if ($class !== $exceptedClass) {
                        arch("{$source} reaches another module only through its public directories: {$target}\\{$directory}\\".basename($file, '.php'))
                            ->expect($exceptedSource)
                            ->not->toUse($class);
                    }
                }
            }
        }
    }
}

it('generates the private directory rules from the folders that exist', function () {
    $private = array_unique(array_merge(...array_map('privateDirectoriesOf', MODULES)));
    $httpExceptions = array_filter(BOUNDARY_EXCEPTIONS, fn (array $exception) => preg_match('/\\\\Http(\\\\|$)/', $exception[1]) === 1);

    expect(PUBLIC_DIRECTORIES)->toBe(['Contracts', 'Events'])
        ->and($private)->toContain('Exceptions', 'Gateways', 'Http', 'Jobs', 'Listeners', 'Models', 'Policies', 'Repositories', 'Services', 'UseCases')
        ->and($private)->not->toContain('Contracts')
        ->and($private)->not->toContain('Events')
        ->and($private)->not->toContain('DTOs')
        ->and($private)->not->toContain('Enums')
        ->and($private)->not->toContain('ValueObjects')
        ->and($httpExceptions)->toBeEmpty();

    // Every pair of modules gets the Http rule: each business module has its own Http folder.
    foreach (array_diff(MODULES, ['Shared']) as $module) {
        expect(privateDirectoriesOf($module))->toContain('Http');
    }
});

// Vocabulary: a module uses another one's DTOs, ValueObjects and Enums only when that module's
// facade exposes them (one class per expectation).
foreach (array_diff(MODULES, ['Shared']) as $target) {
    foreach (classesOutsideVocabularyOf($target) as $class) {
        foreach (array_diff(MODULES, [$target]) as $source) {
            $excepted = array_filter(BOUNDARY_EXCEPTIONS, fn (array $exception) => $exception[0] === 'App\\Modules\\'.$source && $exception[1] === $class);

            if ($excepted !== []) {
                continue;
            }

            arch("{$source} uses only the public vocabulary of {$target}: {$class}")
                ->expect('App\\Modules\\'.$source)
                ->not->toUse($class);
        }
    }
}

it('generates the vocabulary rules from the classes that exist', function () {
    $outside = array_merge(...array_map('classesOutsideVocabularyOf', array_values(array_diff(MODULES, ['Shared']))));

    expect(count($outside))->toBeGreaterThanOrEqual(23)
        ->and($outside)->toContain(ValidatedCart::class, LoginCredentialsDTO::class, UserRole::class, CategoryIds::class)
        ->and($outside)->not->toContain(Email::class)
        ->and($outside)->not->toContain(OrderStatus::class)
        ->and($outside)->not->toContain(CatalogProducts::class);
});

it('computes the vocabulary of a module from its own contracts and events', function () {
    expect(facadeOf('Ordering'))->toContain(CustomerOrderHistory::class, OrderPlaced::class)
        ->and(facadeOf('Payment'))->toContain(PaymentGateway::class, PaymentApproved::class)
        ->and(vocabularyOf('Inventory'))->toContain(StockQuantities::class)
        ->and(vocabularyOf('Inventory'))->not->toContain(ProductIds::class)
        ->and(vocabularyOf('Catalog'))->toContain(ProductIds::class);
});

it('follows the native parameter, return and public property types transitively', function () {
    $reached = reachedFrom([SignatureProbeContract::class, SignatureProbeEvent::class]);

    expect($reached)->toContain(
        OrderForPayment::class,
        CustomerProfile::class,
        ShippingQuote::class,
        DeliveryAddress::class,
        CreateUserDTO::class,
        Email::class,
        Password::class,
        OrderStatus::class,
    );

    foreach ([CarbonImmutable::class, ApiErrorCode::class, 'int', 'void', 'static', ValidatedCartLine::class, OrderLines::class, ProductQuantities::class] as $left) {
        expect($reached)->not->toContain($left);
    }

    // The event alone reaches the type of its public property, and nothing it keeps private.
    expect(reachedFrom([SignatureProbeEvent::class]))->toBe([OrderStatus::class]);
});

it('keeps a type out of the vocabulary when only a docblock names it', function () {
    $reached = reachedFrom([DocblockProbeContract::class]);

    expect($reached)->not->toContain(ValidatedCart::class)
        ->and($reached)->not->toContain(OrderLines::class);
});

it('computes the vocabulary the contracts expose today', function () {
    expect(vocabularyOf('Identity'))->toContain(Email::class, Password::class)
        ->and(vocabularyOf('Ordering'))->toContain(OrderStatus::class)
        ->and(vocabularyOf('Payment'))->toContain(DeclineReason::class);

    foreach ([ValidatedCart::class, OrderLines::class, ProductQuantities::class] as $private) {
        expect(vocabularyOf('Ordering'))->not->toContain($private);
    }

    foreach ([LoginCredentialsDTO::class, UserRole::class] as $private) {
        expect(vocabularyOf('Identity'))->not->toContain($private);
    }
});

// A module root class (its service provider) is wiring, never something another module calls.
foreach (MODULES as $source) {
    foreach (array_diff(MODULES, [$source]) as $target) {
        foreach (rootClassesOf($target) as $class) {
            arch("{$source} does not use another module root class: ".class_basename($class))
                ->expect('App\\Modules\\'.$source)
                ->not->toUse($class);
        }
    }
}

it('does not use another module root class: every module root is covered', function () {
    expect(count(array_merge(...array_map('rootClassesOf', MODULES))))->toBeGreaterThanOrEqual(7);
});

it('declares exactly the HEL-6 exceptions', function () {
    expect(BOUNDARY_EXCEPTIONS)->toBe([
        ['App\\Modules\\Backoffice', 'App\\Modules\\Catalog\\Repositories', 'HEL-6'],
        ['App\\Modules\\Backoffice', 'App\\Modules\\Catalog\\Enums\\ProductStatus', 'HEL-6'],
        ['App\\Modules\\Backoffice', 'App\\Modules\\Identity\\Repositories', 'HEL-6'],
        ['App\\Modules\\Backoffice', 'App\\Modules\\Inventory\\Repositories', 'HEL-6'],
        ['App\\Modules\\Backoffice', 'App\\Modules\\Ordering\\Repositories', 'HEL-6'],
        ['App\\Modules\\Catalog\\Models', 'App\\Modules\\Inventory\\Models\\Stock', 'HEL-6'],
        ['App\\Modules\\Inventory\\Models', 'App\\Modules\\Catalog\\Models\\Product', 'HEL-6'],
        ['App\\Modules\\Catalog\\Http', 'App\\Modules\\Ordering\\Services\\PurchaseAvailabilityService', 'HEL-6'],
        ['App\\Modules\\Inventory\\Http', 'App\\Modules\\Ordering\\Services\\PurchaseAvailabilityService', 'HEL-6'],
    ]);
});

// Contracts: interfaces only, in every module that publishes or defines one.
$modulesWithContracts = array_values(array_filter(MODULES, fn (string $module) => is_dir(MODULES_PATH."/{$module}/Contracts")));

foreach ($modulesWithContracts as $module) {
    arch("keeps only interfaces in every module contracts: {$module}")
        ->expect('App\\Modules\\'.$module.'\\Contracts')
        ->toBeInterfaces();
}

it('keeps only interfaces in every module contracts: every module is covered', function () use ($modulesWithContracts) {
    expect($modulesWithContracts)->toBe(['Catalog', 'Identity', 'Inventory', 'Ordering', 'Payment']);
});

// Contracts speak data: no Eloquent model nor Eloquent collection crosses a module boundary.
it('speaks only data in every contract signature', function () {
    $interfaces = array_map(
        fn (string $file) => 'App\\Modules\\'.basename(dirname($file, 2)).'\\Contracts\\'.basename($file, '.php'),
        glob(MODULES_PATH.'/*/Contracts/*.php'),
    );

    expect(count($interfaces))->toBeGreaterThanOrEqual(11);

    foreach ($interfaces as $interface) {
        foreach ((new ReflectionClass($interface))->getMethods() as $method) {
            $types = [$method->getReturnType(), ...array_map(fn (ReflectionParameter $parameter) => $parameter->getType(), $method->getParameters())];

            foreach ($types as $type) {
                $named = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

                foreach ($named as $single) {
                    $name = $single instanceof ReflectionNamedType ? $single->getName() : null;

                    expect($name !== null && (is_a($name, Model::class, true) || is_a($name, EloquentCollection::class, true)))
                        ->toBeFalse("{$interface}::{$method->getName()} speaks the Eloquent type {$name}");
                }
            }
        }
    }
});

it('resolves the module contracts to their implementations', function (string $contract, string $implementation) {
    expect(app($contract))->toBeInstanceOf($implementation);
})->with([
    [ProductCatalog::class, ProductRepository::class],
    [StockLevels::class, StockRepository::class],
    [PayableOrders::class, OrderRepository::class],
    [CustomerOrderHistory::class, OrderRepository::class],
    [CustomerAccounts::class, CustomerAccountRepository::class],
]);

// Inventory: other modules use its contracts, never its repository (the dashboard read model may read it).
foreach (array_diff(MODULES, ['Inventory', 'Backoffice']) as $module) {
    arch("{$module} reaches the inventory only through its contracts")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse(StockRepository::class);
}

arch('the inventory contracts are interfaces')
    ->expect('App\Modules\Inventory\Contracts')
    ->toBeInterfaces();

// Catalog: it does not read order data; it asks through its own ProductOrderHistory contract,
// implemented by the ordering module.
foreach (['Models', 'Repositories'] as $layer) {
    arch("the catalog does not use the ordering {$layer}")
        ->expect('App\Modules\Catalog')
        ->not->toUse("App\\Modules\\Ordering\\{$layer}");
}

// Identity: the account does not know about orders.
arch('identity does not depend on ordering')
    ->expect('App\Modules\Identity')
    ->not->toUse('App\Modules\Ordering');

// The staff `User` belongs to the admin area: the order side knows the shopper account only,
// so a staff session can never be mistaken for a buyer.
foreach (['Ordering', 'Payment', 'Fulfillment'] as $module) {
    arch("{$module} does not use the staff user")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse(User::class);
}

// Payment and fulfillment only publish events: the order status is changed by ordering alone,
// and they do not know each other.
foreach (['Payment', 'Fulfillment'] as $module) {
    arch("{$module} never changes the order status itself")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse(OrderRepository::class);
}

arch('payment does not know fulfillment')
    ->expect('App\Modules\Payment')
    ->not->toUse('App\Modules\Fulfillment');

arch('fulfillment does not know payment')
    ->expect('App\Modules\Fulfillment')
    ->not->toUse('App\Modules\Payment');

// Ordering: it does not know the address book, and reaches fulfillment only through its events.
// The customers module and the fulfillment module implement the contracts ordering defines.
arch('ordering does not know customers')
    ->expect('App\Modules\Ordering')
    ->not->toUse('App\Modules\Customers');

$fulfillmentNamespaces = array_values(array_diff(
    array_map('basename', glob(__DIR__.'/../../../app/Modules/Fulfillment/*', GLOB_ONLYDIR)),
    ['Events'],
));

foreach ($fulfillmentNamespaces as $namespace) {
    arch("ordering reaches fulfillment only through its events: {$namespace}")
        ->expect('App\\Modules\\Ordering')
        ->not->toUse('App\\Modules\\Fulfillment\\'.$namespace);
}

// The classes that sit directly in `App\Modules\Fulfillment` (its service provider) are a namespace too.
$fulfillmentRootClasses = array_map(
    fn (string $file) => 'App\\Modules\\Fulfillment\\'.basename($file, '.php'),
    glob(__DIR__.'/../../../app/Modules/Fulfillment/*.php'),
);

foreach ($fulfillmentRootClasses as $class) {
    arch('ordering reaches fulfillment only through its events: '.class_basename($class))
        ->expect('App\\Modules\\Ordering')
        ->not->toUse($class);
}

it('ordering reaches fulfillment only through its events: the module root is covered', function () use ($fulfillmentRootClasses) {
    expect($fulfillmentRootClasses)->toContain('App\\Modules\\Fulfillment\\FulfillmentServiceProvider');
});

it('ordering reaches fulfillment only through its events: every namespace but Events is covered', function () use ($fulfillmentNamespaces) {
    expect(count($fulfillmentNamespaces))->toBeGreaterThanOrEqual(4)
        ->and($fulfillmentNamespaces)->not->toContain('Events');
});

arch('the ordering contracts are interfaces')
    ->expect('App\Modules\Ordering\Contracts')
    ->toBeInterfaces();

// Fulfillment answers the ordering side through the contracts ordering defines, and never reads
// the customer's address book: the order carries the address copy it needs.
arch('fulfillment does not know customers')
    ->expect('App\Modules\Fulfillment')
    ->not->toUse('App\Modules\Customers');

// Payment charges through its own PaymentGateway port: only the module's service provider
// names the adapter, so swapping the gateway is a new class and one binding.
foreach (['UseCases', 'Services', 'Http'] as $layer) {
    arch("payment {$layer} reaches the payment gateway only through its contract")
        ->expect("App\\Modules\\Payment\\{$layer}")
        ->not->toUse(FakePaymentGateway::class);
}

arch('the payment contracts are interfaces')
    ->expect('App\Modules\Payment\Contracts')
    ->toBeInterfaces();

// Shared kernel: infrastructure only, no business module.
foreach (array_diff(MODULES, ['Shared']) as $module) {
    arch("the shared kernel does not depend on {$module}")
        ->expect('App\Modules\Shared')
        ->not->toUse("App\\Modules\\{$module}");
}

// Layers: a repository only reads and writes the database. The business rules (services) are
// applied before it is called, so a repository never uses its own module services.
$modulesWithRepositoriesAndServices = array_values(array_filter(
    MODULES,
    fn (string $module) => is_dir(MODULES_PATH."/{$module}/Repositories") && is_dir(MODULES_PATH."/{$module}/Services"),
));

foreach ($modulesWithRepositoriesAndServices as $module) {
    arch("repositories do not use the module services: {$module}")
        ->expect("App\\Modules\\{$module}\\Repositories")
        ->not->toUse("App\\Modules\\{$module}\\Services");
}

it('repositories do not use the module services: every module with both layers is covered', function () use ($modulesWithRepositoriesAndServices) {
    expect($modulesWithRepositoriesAndServices)->toBe(['Catalog', 'Customers', 'Inventory', 'Ordering', 'Payment']);
});

it('resolves the catalog order history to the order repository', function () {
    expect(app(ProductOrderHistory::class))->toBeInstanceOf(OrderRepository::class);
});

it('resolves the delivery address book to the customers module', function () {
    expect(app(DeliveryAddressBook::class))->toBeInstanceOf(CustomerAddressRepository::class);
});

it('resolves the inventory contracts to the stock repository', function (string $contract) {
    expect(app($contract))->toBeInstanceOf(StockRepository::class);
})->with([StockInitializer::class, StockReservation::class]);

it('resolves the payment gateway to the fake gateway', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

it('keeps every module named by these rules', function (string $module) {
    expect(glob(app_path("Modules/{$module}/*")))->not->toBeEmpty();
})->with(MODULES);

// Value objects: immutable by construction, and the account rules live in them only.
arch('identity value objects are final and readonly')
    ->expect('App\Modules\Identity\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

foreach ([
    'App\Modules\Identity\Http\Requests\RegisterRequest',
    'App\Modules\Identity\Http\Requests\LoginRequest',
    'App\Modules\Identity\Http\Requests\Admin\StoreStaffMemberRequest',
    'App\Modules\Identity\Http\Requests\Admin\UpdateStaffMemberRequest',
    'App\Modules\Customers\Http\Requests\Admin\StoreCustomerRequest',
    'App\Modules\Customers\Http\Requests\Admin\UpdateCustomerRequest',
    'App\Modules\Customers\Http\Requests\UpdateProfileRequest',
] as $request) {
    arch("{$request} does not write the password policy itself")
        ->expect($request)
        ->not->toUse('Illuminate\Validation\Rules\Password');
}
