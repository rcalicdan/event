<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;
use Tests\Fixtures\Events\PaymentEvents;

class EnumMethodListener
{
    #[Listener(PaymentEvents::PROCESSING)]
    public function onProcessing(): void
    {
        echo 'Payment processing';
    }

    #[Listener(PaymentEvents::SUCCESS)]
    public function onSuccess(): void
    {
        echo 'Payment success';
    }

    #[Listener(PaymentEvents::FAILED)]
    public function onFailed(): void
    {
        echo 'Payment failed';
    }
}