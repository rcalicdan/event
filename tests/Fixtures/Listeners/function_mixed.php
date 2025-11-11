<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'priority.mixed.event', priority: 90)]
function handleMixedPriorityEvent(): void
{
    echo 'Function priority 90 | ';
}

#[Listener(event: 'priority.mixed.event', priority: 10)]
function handleLowPriorityEvent(): void
{
    echo 'Function priority 10 | ';
}