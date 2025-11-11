<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'priority.mixed.event', priority: 50)]
class MediumPriorityMixedListener
{
    public function handle(): void
    {
        echo 'Class priority 50 | ';
    }
}