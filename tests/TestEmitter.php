<?php

declare(strict_types=1);

namespace Tests;

use Rcalicdan\Event\EventEmitterTrait;

class TestEmitter
{
    use EventEmitterTrait;

    public function triggerEvent(string $event, mixed ...$args): void
    {
        $this->emit($event, ...$args);
    }

    public function checkHasListeners(string $event): bool
    {
        return $this->hasListeners($event);
    }

    public function clearListeners(?string $event = null): void
    {
        $this->removeAllListeners($event);
    }
}
