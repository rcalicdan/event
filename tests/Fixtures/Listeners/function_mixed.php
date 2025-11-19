<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.mixed.event', priority: 90)]
function handleMixedPriorityEvent(): void
{
    echo 'Function priority 90 | ';
}

#[ListenTo(event: 'priority.mixed.event', priority: 10)]
function handleLowPriorityEvent(): void
{
    echo 'Function priority 10 | ';
}