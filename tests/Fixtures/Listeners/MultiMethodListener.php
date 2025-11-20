<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

class MultiMethodListener
{
    #[ListenTo(event: 'order.created')]
    public function onOrderCreated(string $orderId): void
    {
        echo "Order created: {$orderId}";
    }

    #[ListenTo(event: 'order.paid')]
    public function onOrderPaid(string $orderId): void
    {
        echo "Order paid: {$orderId}";
    }

    #[ListenTo(event: 'order.shipped')]
    public function onOrderShipped(string $orderId): void
    {
        echo "Order shipped: {$orderId}";
    }
}
