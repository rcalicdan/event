<?php

declare(strict_types=1);

namespace Rcalicdan\Event\Attributes;

#[\Attribute(
    \Attribute::TARGET_CLASS |
    \Attribute::TARGET_METHOD |
    \Attribute::TARGET_FUNCTION |
    \Attribute::IS_REPEATABLE
)]
class ListenOnce
{
    public readonly string|\BackedEnum $event;

    public function __construct(
        string|\BackedEnum $event,
        public string $method = 'handle',
        public int $priority = 0
    ) {
        $this->event = $event;
    }
}
