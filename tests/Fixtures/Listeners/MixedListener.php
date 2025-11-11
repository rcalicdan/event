<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'mixed.default')]
class MixedListener
{
    public function handle(): void
    {
        echo 'Mixed default handler';
    }

    #[Listener(event: 'mixed.method1')]
    public function handleMethod1(): void
    {
        echo 'Mixed method 1 handler';
    }

    #[Listener(event: 'mixed.method2')]
    public function handleMethod2(): void
    {
        echo 'Mixed method 2 handler';
    }
}