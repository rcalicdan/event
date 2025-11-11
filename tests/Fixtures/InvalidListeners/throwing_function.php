<?php

declare(strict_types=1);

namespace Tests\Fixtures\InvalidListeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener('throwing.function')]
function throwingFunction(): void
{
    throw new \RuntimeException('Function listener error');
}