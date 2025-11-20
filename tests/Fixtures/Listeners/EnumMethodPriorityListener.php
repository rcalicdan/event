<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;
use Tests\Fixtures\Events\PaymentEvents;

class EnumMethodPriorityListener
{
    #[ListenTo(PaymentEvents::PROCESSING, priority: 100)]
    public function onProcessingHigh(): void
    {
        echo 'Processing high priority | ';
    }

    #[ListenTo(PaymentEvents::PROCESSING)]
    public function onProcessing(): void
    {
        echo 'Payment processing | ';
    }

    #[ListenTo(PaymentEvents::SUCCESS)]
    public function onSuccess(): void
    {
        echo 'Payment success | ';
    }

    #[ListenTo(PaymentEvents::FAILED)]
    public function onFailed(): void
    {
        echo 'Payment failed | ';
    }
}
