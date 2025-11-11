<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'priority.class.event', priority: 100)]
class HighPriorityClassListener
{
    public function handle(): void
    {
        echo 'High priority class | ';
    }
}