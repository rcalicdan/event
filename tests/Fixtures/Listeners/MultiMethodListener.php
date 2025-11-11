<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

class MultiMethodListener
{
    #[Listener(event: 'order.created')]
    public function onOrderCreated(string $orderId): void
    {
        echo "Order created: {$orderId}";
    }

    #[Listener(event: 'order.paid')]
    public function onOrderPaid(string $orderId): void
    {
        echo "Order paid: {$orderId}";
    }

    #[Listener(event: 'order.shipped')]
    public function onOrderShipped(string $orderId): void
    {
        echo "Order shipped: {$orderId}";
    }
}