<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging;

/**
 * How a relay cycle ended, which decides how long the relay waits before the next one.
 */
enum RelayCycle
{
    /** No pending message. */
    case Idle;

    /** Every claimed message was accepted by the transport. */
    case Published;

    /** The transport refused a message: it and the later ones stay pending. */
    case Failed;
}
