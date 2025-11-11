<?php

declare(strict_types=1);

namespace Tests\Fixtures\Events;

use Rcalicdan\Event\EventEnum;
use Rcalicdan\Event\EventEnumTrait;

enum PaymentEvents: string implements EventEnum
{
    use EventEnumTrait;

    case PROCESSING = 'payment.processing';
    case SUCCESS = 'payment.success';
    case FAILED = 'payment.failed';
}