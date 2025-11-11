<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

class PriorityMultiAttributeListener
{
    #[Listener(event: 'priority.multi.event1', priority: 100)]
    #[Listener(event: 'priority.multi.event2', priority: 50)]
    public function handleMulti(string $event = ''): void
    {
        if (str_contains($event, 'event1')) {
            echo 'Multi-event handler for event1 (priority 100) | ';
        } else {
            echo 'Multi-event handler for event2 (priority 50) | ';
        }
    }
}