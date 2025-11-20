<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

class MixedMethodListener
{
    #[ListenTo(event: 'priority.mixed.event', priority: 80)]
    public function handlePriorityEvent(): void
    {
        echo 'Method priority 80 | ';
    }
}
