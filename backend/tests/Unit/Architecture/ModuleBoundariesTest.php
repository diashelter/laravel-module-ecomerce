<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Catalog\Contracts\CatalogMetrics;
use Modules\Catalog\Contracts\ProductCatalog;
use Modules\Catalog\Contracts\ProductOrderHistory;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Core\ValueObjects\CatalogProduct;
use Modules\Catalog\Core\ValueObjects\CatalogProducts;
use Modules\Catalog\Core\ValueObjects\CategoryIds;
use Modules\Catalog\Core\ValueObjects\ProductAvailability;
use Modules\Catalog\Core\ValueObjects\ProductCountsByStatus;
use Modules\Catalog\Core\ValueObjects\ProductIds;
use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Persistence\Queries\ProductCatalogQuery;
use Modules\Customers\Persistence\Repositories\CustomerAddressRepository;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Contracts\CustomerMetrics;
use Modules\Identity\Core\DTOs\CreateUserDTO;
use Modules\Identity\Core\DTOs\LoginCredentialsDTO;
use Modules\Identity\Core\Enums\UserRole;
use Modules\Identity\Core\ValueObjects\CustomerProfile;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Core\ValueObjects\Password;
use Modules\Identity\Persistence\Models\User;
use Modules\Identity\Persistence\Repositories\CustomerAccountRepository;
use Modules\Inventory\Contracts\StockInitializer;
use Modules\Inventory\Contracts\StockLevels;
use Modules\Inventory\Contracts\StockMetrics;
use Modules\Inventory\Contracts\StockReservation;
use Modules\Inventory\Core\ValueObjects\ProductStock;
use Modules\Inventory\Core\ValueObjects\ProductStocks;
use Modules\Inventory\Core\ValueObjects\StockQuantities;
use Modules\Inventory\Core\ValueObjects\StockRecord;
use Modules\Inventory\Core\ValueObjects\StockTotals;
use Modules\Inventory\Persistence\Queries\StockLevelsQuery;
use Modules\Inventory\Persistence\Repositories\StockRepository;
use Modules\Ordering\Contracts\CustomerOrderHistory;
use Modules\Ordering\Contracts\DeliveryAddressBook;
use Modules\Ordering\Contracts\OrderMetrics;
use Modules\Ordering\Contracts\PayableOrders;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Core\ValueObjects\DeliveryAddress;
use Modules\Ordering\Core\ValueObjects\OrderCountsByPeriod;
use Modules\Ordering\Core\ValueObjects\OrderForPayment;
use Modules\Ordering\Core\ValueObjects\OrderLines;
use Modules\Ordering\Core\ValueObjects\ProductQuantities;
use Modules\Ordering\Core\ValueObjects\ShippingQuote;
use Modules\Ordering\Core\ValueObjects\ValidatedCart;
use Modules\Ordering\Core\ValueObjects\ValidatedCartLine;
use Modules\Ordering\Events\OrderPlaced;
use Modules\Ordering\Persistence\Repositories\OrderRepository;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Core\Enums\DeclineReason;
use Modules\Payment\Events\PaymentApproved;
use Modules\Payment\Gateways\FakePaymentGateway;
use Modules\Shared\Enums\ApiErrorCode;
use Tests\Unit\Architecture\Fixtures\DocblockProbeContract;
use Tests\Unit\Architecture\Fixtures\SignatureProbeContract;
use Tests\Unit\Architecture\Fixtures\SignatureProbeEvent;
use Tests\Unit\Architecture\Support\ModuleLayout;

/*
 * Boundaries between the modules (see docs/domain-analysis.md).
 *
 * Always one namespace per expectation: with an array of namespaces, `not->toUse` never fails.
 * And an expectation on a namespace that does not exist passes without checking anything,
 * so the last test makes sure every module named here still exists.
 */

const MODULES = ['Backoffice', 'Catalog', 'Customers', 'Fulfillment', 'Identity', 'Inventory', 'Messaging', 'Ordering', 'Payment', 'Shared'];

/** The source folder of a module package: app-modules/<kebab>/src. */
function moduleSrc(string $module): string
{
    return __DIR__.'/../../../app-modules/'.Str::kebab($module).'/src';
}

/*
 * A module's facade is its Contracts and its Events, the first level of src: every other folder is
 * private, so a new folder is private without touching this file. Http included: each module has its
 * own adapters, even when they translate the same value object.
 */
const PUBLIC_DIRECTORIES = ['Contracts', 'Events'];

/*
 * The folders of Core where a module's vocabulary lives. A class in them is public only when the
 * signatures of the module's facade reach it (see vocabularyOf), so the rule is computed on every
 * run and never kept as a list here.
 */
const VOCABULARY_DIRECTORIES = ['DTOs', 'ValueObjects', 'Enums'];

/** @return list<string> the folders of a module that no other module may use, as paths under src (Core opens into its subfolders, minus the vocabulary) */
function privateDirectoriesOf(string $module): array
{
    $private = [];

    foreach (glob(moduleSrc($module).'/*', GLOB_ONLYDIR) as $directory) {
        $name = basename($directory);

        if (in_array($name, PUBLIC_DIRECTORIES, true)) {
            continue;
        }

        if ($name !== 'Core') {
            $private[] = $name;

            continue;
        }

        foreach (glob("{$directory}/*", GLOB_ONLYDIR) as $inner) {
            if (! in_array(basename($inner), VOCABULARY_DIRECTORIES, true)) {
                $private[] = 'Core/'.basename($inner);
            }
        }
    }

    return $private;
}

/** @return list<class-string> the contract interfaces and the event classes of a module */
function facadeOf(string $module): array
{
    return array_map(
        fn (string $file) => 'Modules\\'.$module.'\\'.basename(dirname($file)).'\\'.basename($file, '.php'),
        [...glob(moduleSrc($module).'/Contracts/*.php'), ...glob(moduleSrc($module).'/Events/*.php')],
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
        fn (string $name) => str_starts_with($name, 'Modules\\') && ! str_starts_with($name, 'Modules\\Shared\\') && ! str_starts_with($name, 'Modules\\Messaging\\'),
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
        fn (string $class) => str_starts_with($class, 'Modules\\'.$module.'\\'),
    ));
}

/** @return list<class-string> the classes in a module's vocabulary folders that its facade does not reach */
function classesOutsideVocabularyOf(string $module): array
{
    $classes = [];

    foreach (VOCABULARY_DIRECTORIES as $directory) {
        foreach (glob(moduleSrc($module)."/Core/{$directory}/*.php") as $file) {
            $classes[] = 'Modules\\'.$module.'\\Core\\'.$directory.'\\'.basename($file, '.php');
        }
    }

    return array_values(array_diff($classes, vocabularyOf($module)));
}

// Privacy: a module reaches another one only through its public folders (one namespace per expectation).
foreach (MODULES as $source) {
    foreach (array_diff(MODULES, [$source, 'Shared']) as $target) {
        foreach (privateDirectoriesOf($target) as $directory) {
            arch("{$source} reaches another module only through its public directories: {$target}\\{$directory}")
                ->expect('Modules\\'.$source)
                ->not->toUse('Modules\\'.$target.'\\'.str_replace('/', '\\', $directory));
        }
    }
}

it('generates the private directory rules from the folders that exist', function () {
    $private = array_unique(array_merge(...array_map('privateDirectoriesOf', MODULES)));

    expect(PUBLIC_DIRECTORIES)->toBe(['Contracts', 'Events'])
        ->and($private)->toContain('Core/Consumers', 'Core/Exceptions', 'Core/Jobs', 'Core/Services', 'Core/UseCases', 'Gateways', 'Http', 'Persistence', 'Providers', 'Console')
        ->and($private)->not->toContain('Contracts')
        ->and($private)->not->toContain('Events')
        ->and($private)->not->toContain('Core')
        ->and($private)->not->toContain('Core/DTOs')
        ->and($private)->not->toContain('Core/Enums')
        ->and($private)->not->toContain('Core/ValueObjects');

    // Every pair of modules gets the Http rule: each business module has its own Http folder.
    foreach (array_diff(MODULES, ['Shared', 'Messaging']) as $module) {
        expect(privateDirectoriesOf($module))->toContain('Http');
    }
});

// Vocabulary: a module uses another one's DTOs, ValueObjects and Enums only when that module's
// facade exposes them (one class per expectation).
foreach (array_diff(MODULES, ['Shared']) as $target) {
    foreach (classesOutsideVocabularyOf($target) as $class) {
        foreach (array_diff(MODULES, [$target]) as $source) {
            arch("{$source} uses only the public vocabulary of {$target}: {$class}")
                ->expect('Modules\\'.$source)
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

it('computes the vocabulary the read side exposes', function () {
    expect(vocabularyOf('Catalog'))->toContain(ProductStatus::class, ProductAvailability::class, CatalogProduct::class, ProductCountsByStatus::class)
        ->and(vocabularyOf('Catalog'))->not->toContain(ProductRecord::class)
        ->and(vocabularyOf('Inventory'))->toContain(ProductStocks::class, ProductStock::class, StockTotals::class)
        ->and(vocabularyOf('Inventory'))->not->toContain(StockRecord::class)
        ->and(vocabularyOf('Ordering'))->toContain(OrderCountsByPeriod::class);
});

// The service provider of a module is wiring, never something another module calls: Providers is a private folder.
it('keeps the service providers private in every module', function () {
    foreach (array_diff(MODULES, ['Shared']) as $module) {
        expect(privateDirectoriesOf($module))->toContain('Providers');
    }
});

it('declares no boundary exception', function () {
    $constants = array_keys(get_defined_constants(true)['user'] ?? []);

    expect(array_filter($constants, fn (string $name) => str_contains($name, 'EXCEPTION')))->toBe([]);
});

// Contracts: interfaces only, in every module that publishes or defines one.
$modulesWithContracts = array_values(array_filter(MODULES, fn (string $module) => is_dir(moduleSrc($module).'/Contracts')));

foreach ($modulesWithContracts as $module) {
    arch("keeps only interfaces in every module contracts: {$module}")
        ->expect('Modules\\'.$module.'\\Contracts')
        ->toBeInterfaces();
}

it('keeps only interfaces in every module contracts: every module is covered', function () use ($modulesWithContracts) {
    expect($modulesWithContracts)->toBe(['Catalog', 'Identity', 'Inventory', 'Messaging', 'Ordering', 'Payment']);
});

// Contracts speak data: no Eloquent model nor Eloquent collection crosses a module boundary.
it('speaks only data in every contract signature', function () {
    $interfaces = array_map(
        ModuleLayout::classOf(...),
        ModuleLayout::filesMatching('Contracts/*.php'),
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
    [ProductCatalog::class, ProductCatalogQuery::class],
    [StockLevels::class, StockLevelsQuery::class],
    [PayableOrders::class, OrderRepository::class],
    [CustomerOrderHistory::class, OrderRepository::class],
    [CustomerAccounts::class, CustomerAccountRepository::class],
]);

// Queries: the read side of a module. A query serves a route, reads the module's own tables, and asks
// the owner of anything else through that owner's contract. It never uses the module's repositories
// (writes) or use cases, and it never speaks the Eloquent types in a public signature.
$modulesWithQueries = array_values(array_filter(MODULES, fn (string $module) => is_dir(moduleSrc($module).'/Persistence/Queries')));

foreach ($modulesWithQueries as $module) {
    foreach (['repositories' => 'Persistence\\Repositories', 'use cases' => 'Core\\UseCases'] as $label => $layer) {
        arch("queries do not use the module repositories and use cases ({$label}): {$module}")
            ->expect("Modules\\{$module}\\Persistence\\Queries")
            ->not->toUse("Modules\\{$module}\\{$layer}");
    }
}

it('every module with queries is covered', function () use ($modulesWithQueries) {
    expect($modulesWithQueries)->toBe(['Backoffice', 'Catalog', 'Identity', 'Inventory', 'Ordering']);
});

it('speaks only data in every query signature', function () {
    $queries = array_map(
        ModuleLayout::classOf(...),
        ModuleLayout::filesMatching('Persistence/Queries/*.php'),
    );

    expect(count($queries))->toBeGreaterThanOrEqual(9);

    foreach ($queries as $query) {
        foreach ((new ReflectionClass($query))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $types = [$method->getReturnType(), ...array_map(fn (ReflectionParameter $parameter) => $parameter->getType(), $method->getParameters())];

            foreach ($types as $type) {
                foreach (namedTypesIn($type) as $name) {
                    expect(is_a($name, Model::class, true) || is_a($name, EloquentCollection::class, true))
                        ->toBeFalse("{$query}::{$method->getName()} speaks the Eloquent type {$name}");
                }
            }
        }
    }
});

it('resolves the read contracts to the owner queries', function (string $contract, string $module) {
    $implementation = app($contract);

    expect($implementation::class)->toStartWith("Modules\\{$module}\\Persistence\\Queries\\")
        ->and($implementation)->toBeInstanceOf($contract);
})->with([
    [ProductCatalog::class, 'Catalog'],
    [StockLevels::class, 'Inventory'],
    [CatalogMetrics::class, 'Catalog'],
    [StockMetrics::class, 'Inventory'],
    [CustomerMetrics::class, 'Identity'],
    [OrderMetrics::class, 'Ordering'],
]);

// Inventory: other modules use its contracts, never its repository.
foreach (array_diff(MODULES, ['Inventory']) as $module) {
    arch("{$module} reaches the inventory only through its contracts")
        ->expect("Modules\\{$module}")
        ->not->toUse(StockRepository::class);
}

arch('the inventory contracts are interfaces')
    ->expect('Modules\Inventory\Contracts')
    ->toBeInterfaces();

// Catalog: it does not read order data; it asks through its own ProductOrderHistory contract,
// implemented by the ordering module.
foreach (['Models', 'Repositories'] as $layer) {
    arch("the catalog does not use the ordering {$layer}")
        ->expect('Modules\Catalog')
        ->not->toUse("Modules\\Ordering\\Persistence\\{$layer}");
}

// Identity: the account does not know about orders.
arch('identity does not depend on ordering')
    ->expect('Modules\Identity')
    ->not->toUse('Modules\Ordering');

// The staff `User` belongs to the admin area: the order side knows the shopper account only,
// so a staff session can never be mistaken for a buyer.
foreach (['Ordering', 'Payment', 'Fulfillment'] as $module) {
    arch("{$module} does not use the staff user")
        ->expect("Modules\\{$module}")
        ->not->toUse(User::class);
}

// Payment and fulfillment only publish events: the order status is changed by ordering alone,
// and they do not know each other.
foreach (['Payment', 'Fulfillment'] as $module) {
    arch("{$module} never changes the order status itself")
        ->expect("Modules\\{$module}")
        ->not->toUse(OrderRepository::class);
}

arch('payment does not know fulfillment')
    ->expect('Modules\Payment')
    ->not->toUse('Modules\Fulfillment');

arch('fulfillment does not know payment')
    ->expect('Modules\Fulfillment')
    ->not->toUse('Modules\Payment');

// Ordering: it does not know the address book, and reaches fulfillment only through its events.
// The customers module and the fulfillment module implement the contracts ordering defines.
arch('ordering does not know customers')
    ->expect('Modules\Ordering')
    ->not->toUse('Modules\Customers');

$fulfillmentNamespaces = array_values(array_diff(
    array_map('basename', glob(moduleSrc('Fulfillment').'/*', GLOB_ONLYDIR)),
    ['Events'],
));

foreach ($fulfillmentNamespaces as $namespace) {
    arch("ordering reaches fulfillment only through its events: {$namespace}")
        ->expect('Modules\\Ordering')
        ->not->toUse('Modules\\Fulfillment\\'.$namespace);
}

it('ordering reaches fulfillment only through its events: the Providers namespace is covered', function () use ($fulfillmentNamespaces) {
    expect($fulfillmentNamespaces)->toContain('Providers');
});

it('ordering reaches fulfillment only through its events: every namespace but Events is covered', function () use ($fulfillmentNamespaces) {
    expect(count($fulfillmentNamespaces))->toBeGreaterThanOrEqual(3)
        ->and($fulfillmentNamespaces)->not->toContain('Events');
});

arch('the ordering contracts are interfaces')
    ->expect('Modules\Ordering\Contracts')
    ->toBeInterfaces();

// Fulfillment answers the ordering side through the contracts ordering defines, and never reads
// the customer's address book: the order carries the address copy it needs.
arch('fulfillment does not know customers')
    ->expect('Modules\Fulfillment')
    ->not->toUse('Modules\Customers');

// Payment charges through its own PaymentGateway port: only the module's service provider
// names the adapter, so swapping the gateway is a new class and one binding.
foreach (['Core\\UseCases', 'Core\\Services', 'Http'] as $layer) {
    arch("payment {$layer} reaches the payment gateway only through its contract")
        ->expect("Modules\\Payment\\{$layer}")
        ->not->toUse(FakePaymentGateway::class);
}

arch('the payment contracts are interfaces')
    ->expect('Modules\Payment\Contracts')
    ->toBeInterfaces();

// Shared kernel: infrastructure only, no business module.
foreach (array_diff(MODULES, ['Shared']) as $module) {
    arch("the shared kernel does not depend on {$module}")
        ->expect('Modules\Shared')
        ->not->toUse("Modules\\{$module}");
}

// The messaging module is infrastructure too: it serves the business modules and uses none of them.
foreach (array_diff(MODULES, ['Shared', 'Messaging']) as $module) {
    arch("the messaging module does not depend on {$module}")
        ->expect('Modules\\Messaging')
        ->not->toUse("Modules\\{$module}");
}

// Layers: a repository only reads and writes the database. The business rules (services) are
// applied before it is called, so a repository never uses its own module services.
$modulesWithRepositoriesAndServices = array_values(array_filter(
    MODULES,
    fn (string $module) => is_dir(moduleSrc($module).'/Persistence/Repositories') && is_dir(moduleSrc($module).'/Core/Services'),
));

foreach ($modulesWithRepositoriesAndServices as $module) {
    arch("repositories do not use the module services: {$module}")
        ->expect("Modules\\{$module}\\Persistence\\Repositories")
        ->not->toUse("Modules\\{$module}\\Core\\Services");
}

it('repositories do not use the module services: every module with both layers is covered', function () use ($modulesWithRepositoriesAndServices) {
    expect($modulesWithRepositoriesAndServices)->toBe(['Catalog', 'Customers', 'Fulfillment', 'Inventory', 'Ordering', 'Payment']);
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
    expect(glob(moduleSrc($module).'/*'))->not->toBeEmpty();
})->with(MODULES);

// Value objects: immutable by construction, and the account rules live in them only.
arch('identity value objects are final and readonly')
    ->expect('Modules\Identity\Core\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

foreach ([
    'Modules\Identity\Http\Requests\RegisterRequest',
    'Modules\Identity\Http\Requests\LoginRequest',
    'Modules\Identity\Http\Requests\Admin\StoreStaffMemberRequest',
    'Modules\Identity\Http\Requests\Admin\UpdateStaffMemberRequest',
    'Modules\Customers\Http\Requests\Admin\StoreCustomerRequest',
    'Modules\Customers\Http\Requests\Admin\UpdateCustomerRequest',
    'Modules\Customers\Http\Requests\UpdateProfileRequest',
] as $request) {
    arch("{$request} does not write the password policy itself")
        ->expect($request)
        ->not->toUse('Illuminate\Validation\Rules\Password');
}
