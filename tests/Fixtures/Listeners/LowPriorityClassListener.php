<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'priority.class.event', priority: 10)]
class LowPriorityClassListener
{
    public function handle(): void
    {
        echo 'Low priority class | ';
    }
}