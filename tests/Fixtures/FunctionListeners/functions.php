<?php

declare(strict_types=1);

namespace Tests\Fixtures\FunctionListeners;

use Rcalicdan\Event\Attributes\ListenOnce;
use Rcalicdan\Event\Attributes\ListenTo;
use Tests\Fixtures\Events\PaymentEvents;

#[ListenTo('function.simple')]
function simpleFunction(): void
{
    echo 'Simple function called';
}

#[ListenTo('function.with-args')]
function functionWithArgs(string $message, int $count): void
{
    echo "Function args: {$message}, {$count}";
}

#[ListenTo('function.multi1')]
#[ListenTo('function.multi2')]
function multiFunctionListener(): void
{
    echo 'Multi-event function called';
}

#[ListenTo(PaymentEvents::PROCESSING)]
function enumFunctionListener(): void
{
    echo 'Enum function listener called';
}

// Function without listener attribute (should not be registered)
function regularFunction(): void
{
    echo 'Regular function';
}

#[ListenTo('function.async')]
function asyncStyleFunction(string $data): void
{
    echo "Async-style handler: {$data}";
}