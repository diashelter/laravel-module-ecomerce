<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\CoreUsesHttp;

use Modules\Fake\Http\Controllers\Thing;

/** Deliberate violation: Core names a class of the same module's Http. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return Thing::class;
    }
}
