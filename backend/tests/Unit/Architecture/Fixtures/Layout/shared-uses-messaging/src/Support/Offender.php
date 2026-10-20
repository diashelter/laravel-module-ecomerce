<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\SharedUsesMessaging;

/** Deliberate violation: Shared names the Messaging module. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return \Modules\Messaging\Contracts\EventOutbox::class;
    }
}
