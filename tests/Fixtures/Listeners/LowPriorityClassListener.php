<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.class.event', priority: 10)]
class LowPriorityClassListener
{
    public function handle(): void
    {
        echo 'Low priority class | ';
    }
}