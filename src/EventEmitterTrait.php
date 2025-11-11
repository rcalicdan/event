<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

use Rcalicdan\ConfigLoader\Exceptions\EnvFileNotFoundException;

use function Rcalicdan\ConfigLoader\env;

trait EventEmitterTrait
{
    /** @var array<string, array<int, callable>> */
    private array $listeners = [];

    /** @var int */
    private int $listenerIdCounter = 0;

    /** @var bool|null */
    private ?bool $throwOnListenerError = null;

    /**
     * Configure whether to throw exceptions from listeners or emit them as 'error' events
     */
    public function setThrowOnListenerError(bool $throw): self
    {
        $this->throwOnListenerError = $throw;
        return $this;
    }

    /**
     * Get the throw on listener error setting, checking env if not explicitly set
     */
    private function shouldThrowOnListenerError(): bool
    {
        try {
            if ($this->throwOnListenerError !== null) {
                return $this->throwOnListenerError;
            }

            return env('EVENT_THROW_ON_ERROR', false);
        } catch (EnvFileNotFoundException) {
            // If env file is not found, default to false
            return false;
        }
    }

    /**
     * Attaches a callback to an event, enabling code to react to the event state changes.
     *
     * @param string|EventEnum $event The name of the event to listen for.
     * @param callable $callback The function to execute when the event occurs.
     * @return static
     */
    public function on(string|EventEnum $event, callable $callback): self
    {
        $eventName = $this->normalizeEvent($event);
        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][$this->listenerIdCounter++] = $callback;

        return $this;
    }

    /**
     * Attaches a callback that is automatically removed after its first execution.
     * Useful for one-time setup or teardown logic without manual cleanup.
     *
     * @param string|EventEnum $event The name of the event to listen for.
     * @param callable $callback The function to execute once.
     * @return static
     */
    public function once(string|EventEnum $event, callable $callback): self
    {
        $wrapper = null;
        $wrapper = function (...$args) use ($event, $callback, &$wrapper) {
            $this->removeListener($event, $wrapper);
            $callback(...$args);
        };

        $this->on($event, $wrapper);

        return $this;
    }

    /**
     * Detaches a specific callback from an event to prevent memory leaks and manage resources.
     *
     * @param string|EventEnum $event The name of the event.
     * @param callable $callback The specific listener to remove.
     * @return static
     */
    public function removeListener(string|EventEnum $event, callable $callback): self
    {
        $eventName = $this->normalizeEvent($event);

        if (! isset($this->listeners[$eventName])) {
            return $this;
        }

        foreach ($this->listeners[$eventName] as $id => $listener) {
            if ($listener === $callback) {
                unset($this->listeners[$eventName][$id]);
            }
        }

        if ($this->listeners[$eventName] === []) {
            unset($this->listeners[$eventName]);
        }

        return $this;
    }

    /**
     * Broadcasts an event to all registered listeners, announcing that something meaningful has occurred.
     *
     * @param string|EventEnum $event The name of the event to broadcast.
     * @param mixed ...$args The data to pass to each listener.
     */
    public function emit(string|EventEnum $event, mixed ...$args): void
    {
        $eventName = $this->normalizeEvent($event);

        if (! isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            try {
                $listener(...$args);
            } catch (\Throwable $e) {
                if ($this->shouldThrowOnListenerError()) {
                    throw $e;
                }

                if ($eventName !== 'error') {
                    $this->emit('error', $e);
                } else {
                    // Avoid an infinite loop if the error handler itself throws.
                    fwrite(STDERR, sprintf(
                        "Unhandled Event Error: %s\nFile: %s:%d\nStack trace:\n%s\n",
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getTraceAsString()
                    ));
                }
            }
        }
    }

    /**
     * Checks if any listeners are registered, which can be used to avoid expensive work if no one is listening.
     *
     * @param string|EventEnum $event The name of the event to check.
     */
    public function hasListeners(string|EventEnum $event): bool
    {
        $eventName = $this->normalizeEvent($event);
        return isset($this->listeners[$eventName]) && $this->listeners[$eventName] !== [];
    }

    /**
     * Detaches all listeners, a crucial cleanup step to prevent memory leaks when a stream is closed.
     *
     * @param string|EventEnum|null $event The event to clear, or null to clear all events.
     */
    public function removeAllListeners(string|EventEnum|null $event = null): void
    {
        if ($event === null) {
            $this->listeners = [];
        } else {
            $eventName = $this->normalizeEvent($event);
            unset($this->listeners[$eventName]);
        }
    }

    /**
     * Normalize event to string
     */
    private function normalizeEvent(string|EventEnum $event): string
    {
        return $event instanceof EventEnum ? $event->getName() : $event;
    }
}
