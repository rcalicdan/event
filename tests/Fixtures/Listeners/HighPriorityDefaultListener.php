<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.default.event', priority: 100)]
class HighPriorityDefaultListener
{
    public function handle(): void
    {
        echo 'High priority | ';
    }
}
