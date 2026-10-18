<?php

// Every test of this folder runs on the RabbitMQ vhost of the checkout (`make test` creates it), emptied
// before the test by the beforeEach in tests/Pest.php.

it('connects to the checkout vhost', function () {
    $connection = rabbitConnection();

    try {
        expect(env('RABBITMQ_VHOST'))->toBe(config('rabbitmq.vhost'))
            ->and(env('RABBITMQ_VHOST'))->toStartWith('ecommerce_testing')
            ->and($connection->isConnected())->toBeTrue();
    } finally {
        $connection->close();
    }
});

it('starts every broker test on an empty vhost', function () {
    expect(brokerObjects())->toBe(['queues' => [], 'exchanges' => []]);

    // Left behind on purpose: the second run only passes if the start of the test cleaned them up.
    $connection = rabbitConnection();
    $channel = $connection->channel();
    $channel->exchange_declare('testing.leftover', 'direct', false, true, false);
    $channel->queue_declare('testing.leftover', false, true, false, false);
    $connection->close();

    expect(brokerObjects())->toBe(['queues' => ['testing.leftover'], 'exchanges' => ['testing.leftover']]);
})->repeat(2);
