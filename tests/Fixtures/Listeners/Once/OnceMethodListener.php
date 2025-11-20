<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners\Once;

use Rcalicdan\Event\Attributes\ListenOnce;

class OnceMethodListener
{
    #[ListenOnce('once.method.event')]
    public function onceMethod(): void
    {
        echo 'Once method listener called';
    }
}
