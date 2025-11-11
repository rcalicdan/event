<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

class MethodLevelListener
{
    #[Listener(event: 'method.test')]
    public function handleMethodTest(): void
    {
        echo 'method listener called';
    }
}