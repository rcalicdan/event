<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;
use Tests\Fixtures\Events\PaymentEvents;

#[Listener('function.simple')]
function simpleFunction(): void
{
    echo 'Simple function called';
}

#[Listener('function.with-args')]
function functionWithArgs(string $message, int $count): void
{
    echo "Function args: {$message}, {$count}";
}

#[Listener('function.multi1')]
#[Listener('function.multi2')]
function multiFunctionListener(): void
{
    echo 'Multi-event function called';
}

#[Listener(PaymentEvents::PROCESSING)]
function enumFunctionListener(): void
{
    echo 'Enum function listener called';
}

// Function without listener attribute (should not be registered)
function regularFunction(): void
{
    echo 'Regular function';
}

#[Listener('function.async')]
function asyncStyleFunction(string $data): void
{
    echo "Async-style handler: {$data}";
}