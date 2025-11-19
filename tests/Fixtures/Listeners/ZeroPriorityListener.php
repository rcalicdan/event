<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.negative.event', priority: 0)]
class ZeroPriorityListener
{
    public function handle(): void
    {
        echo 'Zero priority | ';
    }
}