<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

class MultiAttributeMethodListener
{
    #[ListenTo(event: 'email.sent')]
    #[ListenTo(event: 'sms.sent')]
    public function handleNotification(): void
    {
        echo 'Notification sent';
    }
}
