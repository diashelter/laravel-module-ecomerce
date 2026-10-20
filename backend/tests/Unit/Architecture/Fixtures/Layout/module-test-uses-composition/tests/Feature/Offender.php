<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\ModuleTestUsesComposition;

use Modules\Messaging\Tests\Fixtures\SpyConsumer;
use Tests\TestCase;
use Tests\Unit\Architecture\Support\ModuleLayout;

/** Deliberate violation: a test of the messaging module names the support of the composition suite. Never loaded. */
final class Offender
{
    /** @return list<string> */
    public function make(): array
    {
        return [ModuleLayout::class, SpyConsumer::class, TestCase::class];
    }
}
