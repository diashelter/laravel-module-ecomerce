<?php

use App\Modules\Fulfillment\Events\DeliveryScheduled;
use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Fulfillment\Jobs\DeliverOrder;
use App\Modules\Ordering\Events\OrderPaid;
use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Payment\Events\PaymentApproved;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

/*
 * The order events and the delivery job cross module boundaries (and, with the outbox, will be
 * stored), so they carry ids and values, never an Eloquent model.
 */
it('carries no model in the order events and the delivery job', function (string $class) {
    $properties = (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC);

    expect($properties)->not->toBeEmpty();

    foreach ($properties as $property) {
        $types = $property->getType() instanceof ReflectionUnionType
            ? $property->getType()->getTypes()
            : [$property->getType()];

        foreach ($types as $type) {
            $name = $type instanceof ReflectionNamedType ? $type->getName() : null;

            expect($name !== null && is_a($name, Model::class, true))
                ->toBeFalse("{$class}::\${$property->getName()} carries the model {$name}");
        }
    }
})->with([
    OrderPlaced::class,
    OrderPaid::class,
    PaymentApproved::class,
    DeliveryScheduled::class,
    OrderDelivered::class,
    DeliverOrder::class,
]);

it('still announces OrderPaid only after the commit', function () {
    expect(new OrderPaid(1, 3))->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});
