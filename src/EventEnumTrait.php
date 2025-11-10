<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

trait EventEnumTrait
{
    /**
     * Get the event name from the enum value
     */
    public function getName(): string
    {
        return $this->value;
    }
}