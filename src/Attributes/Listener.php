<?php

declare(strict_types=1);

namespace Rcalicdan\Event\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Listener
{
    public function __construct(
        public string $event,
        public string $method = 'handle'
    ) {
    }
}
