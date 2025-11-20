<?php

declare(strict_types=1);

namespace Tests\Fixtures\Events;

enum PaymentEvents: string
{
    case PROCESSING = 'payment.processing';
    case SUCCESS = 'payment.success';
    case FAILED = 'payment.failed';
}
