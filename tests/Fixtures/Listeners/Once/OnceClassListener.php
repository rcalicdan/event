<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners\Once;

use Rcalicdan\Event\Attributes\ListenOnce;

#[ListenOnce('once.class.event')]
class OnceClassListener
{
    public function handle(): void
    {
        echo 'Once class listener called';
    }
}
