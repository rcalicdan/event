<?php

declare(strict_types=1);

namespace Tests\Events;

class OrderCreated
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly float $total
    ) {
    }
}