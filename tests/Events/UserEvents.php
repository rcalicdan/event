<?php

declare(strict_types=1);

namespace Tests\Events;

enum UserEvents: string
{
    case REGISTERED = 'user.registered';
    case UPDATED = 'user.updated';
    case DELETED = 'user.deleted';
}