<?php

declare(strict_types=1);

namespace Tests\Fixtures\InvalidListeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'invalid', method: 'nonExistentMethod')]
class InvalidListener
{
    // Method 'nonExistentMethod' does not exist
}
