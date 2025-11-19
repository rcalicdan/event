<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

class VisibilityListener
{
    #[ListenTo(event: 'public.event')]
    public function publicMethod(): void
    {
        echo 'Public method handler';
    }

    #[ListenTo(event: 'protected.event')]
    protected function protectedMethod(): void
    {
        echo 'Protected method handler';
    }

    #[ListenTo(event: 'private.event')]
    private function privateMethod(): void
    {
        echo 'Private method handler';
    }
}