<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

interface EventEnum
{
    /**
     * Get the event name
     */
    public function getName(): string;
}