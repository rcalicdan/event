<?php

declare(strict_types=1);

namespace Tests\Fixtures\InvalidListeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'invalid', method: 'nonExistentMethod')]
class InvalidListener
{
    // Method 'nonExistentMethod' does not exist
}
