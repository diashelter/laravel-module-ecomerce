<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ connection
    |--------------------------------------------------------------------------
    |
    | The broker behind the "rabbitmq" value of the event-transport feature flag. The dev and load
    | stacks use the "/" vhost; `make test` gives each checkout a vhost of its own.
    |
    */

    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'ecommerce'),
    'password' => env('RABBITMQ_PASSWORD', 'secret'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

];
