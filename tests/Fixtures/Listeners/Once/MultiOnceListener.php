<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners\Once;

use Rcalicdan\Event\Attributes\ListenOnce;

class MultiOnceListener
{
    #[ListenOnce('once.event1')]
    #[ListenOnce('once.event2')]
    public function handleMultiple(string $event): void
    {
        echo "Multi-once handler: {$event}";
    }
}