<?php

declare(strict_types=1);

namespace Rcalicdan\Event\Attributes;

use Rcalicdan\Event\EventEnum;

#[\Attribute(
    \Attribute::TARGET_CLASS | 
    \Attribute::TARGET_METHOD |
    \Attribute::TARGET_FUNCTION |  
    \Attribute::IS_REPEATABLE
)]
class Listener
{
    public readonly string $event;

    public function __construct(
        string|EventEnum $event,
        public string $method = 'handle',
        public int $priority = 0  
    ) {
        $this->event = $event instanceof EventEnum ? $event->getName() : $event;
    }
}