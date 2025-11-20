<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners\Once;

use Rcalicdan\Event\Attributes\ListenOnce;

#[ListenOnce('once.function.event')]
function onOnceFunction(): void
{
    echo 'Once function listener called';
}

#[ListenOnce('once.priority.function.high', priority: 100)]
function onOncePriorityHigh(): void
{
    echo 'Once function high priority';
}

#[ListenOnce('once.priority.function.low', priority: 10)]
function onOncePriorityLow(): void
{
    echo 'Once function low priority';
}
