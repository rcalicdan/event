<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.default.event')]  // Default priority: 0
class DefaultPriorityListener
{
    public function handle(): void
    {
        echo 'Default priority | ';
    }
}