<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'priority.negative.event', priority: 50)]
class PositivePriorityListener
{
    public function handle(): void
    {
        echo 'Positive priority | ';
    }
}