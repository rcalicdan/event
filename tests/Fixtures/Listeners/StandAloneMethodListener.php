<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

class StandaloneMethodListener
{
    #[Listener(event: 'standalone.method')]
    public function handleStandalone(): void
    {
        echo 'Standalone method handler';
    }
}