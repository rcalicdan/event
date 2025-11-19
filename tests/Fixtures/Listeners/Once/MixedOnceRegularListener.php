<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners\Once;

use Rcalicdan\Event\Attributes\ListenOnce;
use Rcalicdan\Event\Attributes\ListenTo;

class MixedOnceRegularListener
{
    #[ListenOnce('mixed.once.regular')]
    public function onceHandler(): void
    {
        echo 'Once listener';
    }

    #[ListenTo('mixed.once.regular')]
    public function regularHandler(): void
    {
        echo 'Regular listener';
    }
}