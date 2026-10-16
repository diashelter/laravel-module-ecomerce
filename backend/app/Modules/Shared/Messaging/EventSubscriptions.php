<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging;

use LogicException;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Which consumers react to each event name. The module that consumes subscribes in the boot() of
 * its service provider; the producer never lists its consumers. A single instance per application
 * (bound in AppServiceProvider).
 */
final class EventSubscriptions
{
    /** @var array<string, class-string<DomainEvent>> the event class read back for each name */
    private array $events = [];

    /** @var array<string, list<class-string<EventConsumer>>> */
    private array $consumers = [];

    /**
     * @param  class-string<DomainEvent>  $event
     * @param  class-string  $consumer
     *
     * @throws LogicException when the consumer does not take exactly that event, or when another
     *                        event class already uses the same name
     */
    public function subscribe(string $event, string $consumer): void
    {
        $name = $event::eventName();

        if (isset($this->events[$name]) && $this->events[$name] !== $event) {
            throw new LogicException("The event name {$name} already belongs to {$this->events[$name]}, so {$event} cannot use it.");
        }

        if (! is_subclass_of($consumer, EventConsumer::class)) {
            throw new LogicException("{$consumer} must implement ".EventConsumer::class.' to consume '.$name.'.');
        }

        if (! $this->handles($consumer, $event)) {
            throw new LogicException("{$consumer}::handle() must take a single {$event}.");
        }

        $this->events[$name] = $event;
        $this->consumers[$name][] = $consumer;
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
