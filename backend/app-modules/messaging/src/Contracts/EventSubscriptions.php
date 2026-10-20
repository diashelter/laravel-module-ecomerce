<?php

declare(strict_types=1);

namespace Modules\Messaging\Contracts;

use LogicException;

/**
 * Where a module subscribes its consumers to the events they react to. A consumer has a stable name
 * (ordering.mark_order_as_paid) that names its queue in the broker, so the name never derives from the class.
 */
interface EventSubscriptions
{
    /**
     * @param  class-string<DomainEvent>  $event
     * @param  class-string<EventConsumer>  $consumer
     *
     * @throws LogicException when the names or the types do not fit the subscription rules
     */
    public function subscribe(string $event, string $consumer, string $consumerName): void;
}
