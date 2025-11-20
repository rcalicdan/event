<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners\Once;

use Rcalicdan\Event\Attributes\ListenOnce;

class OncePriorityListener
{
    #[ListenOnce('once.priority.event', priority: 100)]
    public function highPriority(): void
    {
        echo 'Once high priority';
    }

    #[ListenOnce('once.priority.event', priority: 10)]
    public function lowPriority(): void
    {
        echo 'Once low priority';
    }
}
