<?php

declare(strict_types=1);

namespace Tests\Fixtures\InvalidListeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'invalid.method', method: 'nonExistentMethod')]
class InvalidMethodListener
{
    public function handle(): void
    {
        echo 'This is the wrong method';
    }
}