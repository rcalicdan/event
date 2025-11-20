<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners\Once;

use Rcalicdan\Event\Attributes\ListenOnce;

class OnceEnumListener
{
    #[ListenOnce('payment.refunded')]
    public function onRefunded(): void
    {
        echo 'Payment refunded once';
    }
}
