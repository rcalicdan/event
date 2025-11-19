<?php

declare(strict_types=1);

namespace Tests\Fixtures\InvalidListeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'throwing.event')]
class ThrowingListener
{
    public function handle(): void
    {
        throw new \RuntimeException('Listener error');
    }
}