<?php

declare(strict_types=1);

namespace Modules\Messaging\Core;

use LogicException;
use Modules\Messaging\Contracts\DomainEvent;
use Modules\Messaging\Contracts\EventConsumer;
use Modules\Messaging\Contracts\EventSubscriptions;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Which consumers react to each event name. The module that consumes subscribes in the boot() of
 * its service provider; the producer never lists its consumers. A single instance per application
 * (a singleton of MessagingServiceProvider).
 *
 * Every consumer also has a stable name ("ordering.mark_order_as_paid"), given at the subscription:
 * it names the consumer's RabbitMQ queue, so it must outlive any rename or move of the PHP class.
 */
final class EventSubscriptionRegistry implements EventSubscriptions
{
    private const CONSUMER_NAME_PATTERN = '/^[a-z]+\.[a-z_]+$/';

    /** @var array<string, class-string<DomainEvent>> the event class read back for each name */
    private array $events = [];

    /** @var array<string, list<class-string<EventConsumer>>> */
    private array $consumers = [];

    /** @var array<string, class-string<EventConsumer>> the consumer class of each stable name */
    private array $consumerClasses = [];

    /** @var array<class-string<EventConsumer>, string> the stable name of each consumer class */
    private array $consumerNames = [];

    /** @var array<string, list<string>> the event names each consumer subscribes to, by stable name */
    private array $eventNamesOfConsumer = [];

    /**
     * @param  class-string<DomainEvent>  $event
     * @param  class-string  $consumer
     *
     * @throws LogicException when the consumer does not take exactly that event, when another
     *                        event class already uses the same name, when the consumer name is not
     *                        "<module>.<snake_case_name>", or when a name and a consumer class
     *                        would no longer be one-to-one
     */
    public function subscribe(string $event, string $consumer, string $consumerName): void
    {
        $name = $event::eventName();

        if (isset($this->events[$name]) && $this->events[$name] !== $event) {
            throw new LogicException("The event name {$name} already belongs to {$this->events[$name]}, so {$event} cannot use it.");
        }

        if (preg_match(self::CONSUMER_NAME_PATTERN, $consumerName) !== 1) {
            throw new LogicException("The consumer name {$consumerName} must look like ordering.mark_order_as_paid.");
        }

        if (isset($this->consumerClasses[$consumerName]) && $this->consumerClasses[$consumerName] !== $consumer) {
            throw new LogicException("The consumer name {$consumerName} already belongs to {$this->consumerClasses[$consumerName]}, so {$consumer} cannot use it.");
        }

        if (isset($this->consumerNames[$consumer]) && $this->consumerNames[$consumer] !== $consumerName) {
            throw new LogicException("{$consumer} already has the consumer name {$this->consumerNames[$consumer]}, so it cannot also be {$consumerName}.");
        }

        if (! is_subclass_of($consumer, EventConsumer::class)) {
            throw new LogicException("{$consumer} must implement ".EventConsumer::class.' to consume '.$name.'.');
        }

        if (! $this->handles($consumer, $event)) {
            throw new LogicException("{$consumer}::handle() must take a single {$event}.");
        }

        $this->events[$name] = $event;
        $this->consumers[$name][] = $consumer;
        $this->consumerClasses[$consumerName] = $consumer;
        $this->consumerNames[$consumer] = $consumerName;
        $this->eventNamesOfConsumer[$consumerName][] = $name;
    }

    /** @return list<class-string<EventConsumer>> */
    public function consumersOf(string $name): array
    {
        return $this->consumers[$name] ?? [];
    }

    /** @return class-string<DomainEvent>|null */
    public function eventOf(string $name): ?string
    {
        return $this->events[$name] ?? null;
    }

    /** @return array<string, class-string<EventConsumer>> every consumer class, by stable name */
    public function consumerClasses(): array
    {
        return $this->consumerClasses;
    }

    /** @return class-string<EventConsumer>|null */
    public function consumerOf(string $consumerName): ?string
    {
        return $this->consumerClasses[$consumerName] ?? null;
    }

    public function nameOf(string $consumer): ?string
    {
        return $this->consumerNames[$consumer] ?? null;
    }

    /** @return list<string> the names of the events the consumer is subscribed to */
    public function eventNamesOf(string $consumerName): array
    {
        return $this->eventNamesOfConsumer[$consumerName] ?? [];
    }

    /** @return array<string, list<class-string<EventConsumer>>> every consumer, by event name */
    public function all(): array
    {
        return $this->consumers;
    }

    private function handles(string $consumer, string $event): bool
    {
        if (! method_exists($consumer, 'handle')) {
            return false;
        }

        $parameters = (new ReflectionMethod($consumer, 'handle'))->getParameters();
        $type = count($parameters) === 1 ? $parameters[0]->getType() : null;

        return $type instanceof ReflectionNamedType && $type->getName() === $event;
    }
}
