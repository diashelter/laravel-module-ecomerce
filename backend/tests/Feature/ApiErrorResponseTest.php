<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Ordering\Exceptions\InsufficientStockException;
use App\Modules\Shared\Http\Responses\ApiErrorResponse;
use Illuminate\Validation\ValidationException;

it('builds the standard validation error shape', function () {
    $response = ApiErrorResponse::fromValidationException(
        ValidationException::withMessages(['email' => ['E-mail inválido.']]),
    );

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe([
            'code' => 'VALIDATION_FAILED',
            'message' => 'E-mail inválido.',
            'errors' => ['email' => ['E-mail inválido.']],
            'request_id' => null,
        ]);
});

it('answers form request failures with the standard shape', function () {
    $response = $this->postJson('/api/auth/register', [])->assertUnprocessable();

    expect(array_keys($response->json()))->toBe(['code', 'message', 'errors', 'request_id'])
        ->and($response->json('code'))->toBe('VALIDATION_FAILED')
        ->and(array_keys($response->json('errors')))->toBe(['name', 'email', 'password']);
});

it('uses the same shape for validation errors thrown manually', function () {
    $response = $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

    assertApiError($response, 'VALIDATION_FAILED', 'E-mail ou senha inválidos.', [
        'email' => ['E-mail ou senha inválidos.'],
    ]);
});

it('identifies business rule violations', function () {
    $category = Category::factory()->create();
    productWithStock(1)->categories()->attach($category);

    $response = $this->actingAs(admin())->deleteJson("/api/admin/categories/{$category->id}")->assertConflict();

    assertApiError($response, 'BUSINESS_RULE_VIOLATION', 'Esta categoria possui produtos associados e não pode ser excluída.');
});

it('identifies insufficient stock with its own code', function () {
    $response = (new InsufficientStockException(['items.1' => ['Produto não encontrado.']]))->render();

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true)['code'])->toBe('INSUFFICIENT_STOCK');
});

it('maps http errors to stable codes', function () {
    $response = $this->deleteJson('/api/products')->assertMethodNotAllowed();

    expect($response->json('code'))->toBe('METHOD_NOT_ALLOWED');
});

it('forbids caching of error responses', function () {
    $this->getJson('/api/products/999999')
        ->assertNotFound()
        ->assertHeader('Cache-Control', 'no-store, private');
});
