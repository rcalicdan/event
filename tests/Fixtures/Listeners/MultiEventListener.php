<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'user.created', method: 'onUserCreated')]
#[Listener(event: 'user.updated', method: 'onUserUpdated')]
class MultiEventListener
{
    public function onUserCreated(string $username): void
    {
        echo "User created: {$username}";
    }

    public function onUserUpdated(string $username): void
    {
        echo "User updated: {$username}";
    }
}