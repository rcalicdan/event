<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

class PriorityMethodListener
{
    #[ListenTo(event: 'priority.method.event', priority: 100)]
    public function highPriority(): void
    {
        echo 'High priority method | ';
    }

    #[ListenTo(event: 'priority.method.event', priority: 50)]
    public function mediumPriority(): void
    {
        echo 'Medium priority method | ';
    }

    #[ListenTo(event: 'priority.method.event', priority: 10)]
    public function lowPriority(): void
    {
        echo 'Low priority method | ';
    }
}
