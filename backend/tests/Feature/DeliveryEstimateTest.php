<?php

use Carbon\CarbonImmutable;

it('shows the delivery estimate after the payment is approved', function () {
    // Wednesday 10:00 in São Paulo, two business days to SP: Friday.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Sao_Paulo'));
    $user = customer();
    $product = productWithStock(5);
    $this->actingAs($user);

    $orderId = $this->postJson('/api/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'address_id' => addressOf($user, ['state' => 'SP'])->id,
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/orders/{$orderId}")->assertOk()->assertJsonPath('data.delivery.estimated_on', null);

    $this->postJson("/api/orders/{$orderId}/payment", ['card_token' => 'fake_card_approved'])->assertAccepted();

    // QUEUE_CONNECTION=sync: PaymentApproved -> OrderPaid -> DeliveryScheduled already ran.
    $this->getJson("/api/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.delivery.estimated_on', '2026-10-09')
        ->assertJsonPath('data.delivery.business_days', 2);
});

it('shows the same delivery block to the admin', function () {
    $user = customer();
    $product = productWithStock(5);
    $this->actingAs($user);
    $orderId = $this->postJson('/api/orders', [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'address_id' => addressOf($user, ['state' => 'RJ', 'complement' => 'Sala 5'])->id,
    ])->assertCreated()->json('data.id');

    $store = $this->getJson("/api/orders/{$orderId}")->assertOk()->json('data');
    $admin = $this->actingAs(admin())->getJson("/api/admin/orders/{$orderId}")->assertOk()->json('data');

    expect($admin['items_total_cents'])->toBe($store['items_total_cents'])
        ->and($admin['shipping_cents'])->toBe($store['shipping_cents'])
        ->and($admin['delivery'])->toBe($store['delivery'])
        ->and($store['shipping_cents'])->toBe(2200)
        ->and($store['delivery']['address']['state'])->toBe('RJ');
});
