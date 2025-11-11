<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

class VisibilityListener
{
    #[Listener(event: 'public.event')]
    public function publicMethod(): void
    {
        echo 'Public method handler';
    }

    #[Listener(event: 'protected.event')]
    protected function protectedMethod(): void
    {
        echo 'Protected method handler';
    }

    #[Listener(event: 'private.event')]
    private function privateMethod(): void
    {
        echo 'Private method handler';
    }
}