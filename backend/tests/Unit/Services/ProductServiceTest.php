<?php

use App\Modules\Catalog\Services\ProductService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

it('builds a default image url from the product id', function () {
    expect((new ProductService)->defaultImageUrl(42))->toBe('https://picsum.photos/seed/product-42/600/600');
});

it('allows deleting products that were never ordered', function () {
    (new ProductService)->ensureCanBeDeleted(false);
})->throwsNoExceptions();

it('does not allow deleting products that appear in orders', function () {
    expect(fn () => (new ProductService)->ensureCanBeDeleted(true))
        ->toThrow(BusinessRuleException::class, 'Este produto possui pedidos e não pode ser excluído. Desative-o em vez disso.');
});
