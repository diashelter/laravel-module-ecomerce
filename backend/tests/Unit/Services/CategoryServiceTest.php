<?php

use App\Modules\Catalog\Services\CategoryService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

it('allows deleting empty categories', function () {
    (new CategoryService)->ensureCanBeDeleted(false);
})->throwsNoExceptions();

it('does not allow deleting categories with products', function () {
    expect(fn () => (new CategoryService)->ensureCanBeDeleted(true))
        ->toThrow(BusinessRuleException::class, 'Esta categoria possui produtos associados e não pode ser excluída.');
});
