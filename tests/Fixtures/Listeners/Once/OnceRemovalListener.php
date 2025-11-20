<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners\Once;

use Rcalicdan\Event\Attributes\ListenOnce;

class OnceRemovalListener
{
    #[ListenOnce('once.removal.test')]
    public function testRemoval(): void
    {
        echo 'Removal test';
    }
}
