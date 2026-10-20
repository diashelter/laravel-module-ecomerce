<?php

declare(strict_types=1);

namespace Modules\Messaging\Contracts;

/**
 * A class that reacts to one event. It has a single `handle()` whose one parameter is the event
 * class it is subscribed to (see EventSubscriptions), and it must be idempotent: a message can be
 * delivered more than once.
 */
interface EventConsumer {}
