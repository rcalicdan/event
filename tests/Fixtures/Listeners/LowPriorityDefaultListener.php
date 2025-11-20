<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.default.event', priority: -10)]
class LowPriorityDefaultListener
{
    public function handle(): void
    {
        echo 'Low priority | ';
    }
}
