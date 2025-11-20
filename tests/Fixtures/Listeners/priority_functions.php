<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo('priority.function.event', priority: 100)]
function highPriorityFunction(): void
{
    echo 'High priority function | ';
}

#[ListenTo('priority.function.event', priority: 50)]
function mediumPriorityFunction(): void
{
    echo 'Medium priority function | ';
}

#[ListenTo('priority.function.event', priority: 10)]
function lowPriorityFunction(): void
{
    echo 'Low priority function | ';
}
