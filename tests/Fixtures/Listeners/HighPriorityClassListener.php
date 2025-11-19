<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.class.event', priority: 100)]
class HighPriorityClassListener
{
    public function handle(): void
    {
        echo 'High priority class | ';
    }
}