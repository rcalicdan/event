<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'priority.same.event', priority: 50)]
class PrioritySameListener3
{
    public function handle(): void
    {
        echo 'Third listener | ';
    }
}