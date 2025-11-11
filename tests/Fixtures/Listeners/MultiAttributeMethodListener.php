<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

class MultiAttributeMethodListener
{
    #[Listener(event: 'email.sent')]
    #[Listener(event: 'sms.sent')]
    public function handleNotification(): void
    {
        echo 'Notification sent';
    }
}