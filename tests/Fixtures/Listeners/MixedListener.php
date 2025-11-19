<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'mixed.default')]
class MixedListener
{
    public function handle(): void
    {
        echo 'Mixed default handler';
    }

    #[ListenTo(event: 'mixed.method1')]
    public function handleMethod1(): void
    {
        echo 'Mixed method 1 handler';
    }

    #[ListenTo(event: 'mixed.method2')]
    public function handleMethod2(): void
    {
        echo 'Mixed method 2 handler';
    }
}