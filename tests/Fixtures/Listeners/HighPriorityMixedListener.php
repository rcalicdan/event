<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.mixed.event', priority: 100)]
class HighPriorityMixedListener
{
    public function handle(): void
    {
        echo 'Class priority 100 | ';
    }
}