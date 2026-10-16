<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

/**
 * A second consumer of SampleEvent, a class of its own.
 */
final class SecondSpyConsumer extends SpyConsumer {}
