<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'priority.class.event', priority: 50)]
class MediumPriorityClassListener
{
    public function handle(): void
    {
        echo 'Medium priority class | ';
    }
}