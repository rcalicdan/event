<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

use function Rcalicdan\ConfigLoader\env;

use Rcalicdan\ConfigLoader\Exceptions\EnvFileNotFoundException;

trait EventEmitterTrait
{
    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    private array $listeners = [];

    /** @var array<string, array<int, array{callback: callable, priority: int}>> */
    private array $wildcardListeners = [];

    /** @var int */
    private int $listenerIdCounter = 0;

    /** @var bool|null */
    private ?bool $throwOnListenerError = null;

    /** @var array<string, bool> */
    private array $sortedEvents = [];

    /** @var int */
    private int $maxListeners = 0;

    /** @var array<string, bool> */
    private array $maxListenersWarned = [];

    /**
     * Configure whether to throw exceptions from listeners or emit them as 'error' events
     */
    public function setThrowOnListenerError(bool $throw): self
    {
        $this->throwOnListenerError = $throw;

        return $this;
    }

    /**
     * Set the maximum number of listeners for an event before a warning is emitted.
     * Set to 0 to disable the warning.
     */
    public function setMaxListeners(int $max): self
    {
        $this->maxListeners = max(0, $max);

        return $this;
    }

    /**
     * Get the current maximum listeners setting
     */
    public function getMaxListeners(): int
    {
        return $this->maxListeners;
    }

    /**
     * Attaches a callback to an event, enabling code to react to the event state changes.
     * Supports wildcard patterns using '*' to match multiple events.
     *
     * @param string|\BackedEnum $event The name of the event to listen for (supports wildcards like 'user.*').
     * @param callable $callback The function to execute when the event occurs.
     * @param int $priority The priority of the listener (higher = executed first). Default: 0
     * @return static
     */
    public function on(string|\BackedEnum $event, callable $callback, int $priority = 0): self
    {
        $eventName = $this->normalizeEvent($event);

        if (strpos($eventName, '*') !== false) {
            $this->wildcardListeners[$eventName] ??= [];
            $this->wildcardListeners[$eventName][$this->listenerIdCounter++] = [
                'callback' => $callback,
                'priority' => $priority,
            ];
        } else {
            $this->listeners[$eventName] ??= [];
            $this->listeners[$eventName][$this->listenerIdCounter++] = [
                'callback' => $callback,
                'priority' => $priority,
            ];
        }

        $this->sortedEvents[$eventName] = false;
        $this->checkMaxListeners($eventName);

        return $this;
    }

    /**
     * Attaches a callback that is automatically removed after its first execution.
     * Useful for one-time setup or teardown logic without manual cleanup.
     *
     * @param string|\BackedEnum $event The name of the event to listen for.
     * @param callable $callback The function to execute once.
     * @param int $priority The priority of the listener (higher = executed first). Default: 0
     * @return static
     */
    public function once(string|\BackedEnum $event, callable $callback, int $priority = 0): self
    {
        $wrapper = null;
        $wrapper = function (...$args) use ($event, $callback, &$wrapper) {
            $this->removeListener($event, $wrapper);

            return $callback(...$args);
        };

        $this->on($event, $wrapper, $priority);

        return $this;
    }

    /**
     * Detaches a specific callback from an event to prevent memory leaks and manage resources.
     *
     * @param string|\BackedEnum $event The name of the event.
     * @param callable $callback The specific listener to remove.
     * @return static
     */
    public function removeListener(string|\BackedEnum $event, callable $callback): self
    {
        $eventName = $this->normalizeEvent($event);

        if (! isset($this->listeners[$eventName])) {
            return $this;
        }

        foreach ($this->listeners[$eventName] as $id => $listener) {
            if ($listener['callback'] === $callback) {
                unset($this->listeners[$eventName][$id]);
            }
        }

        if ($this->listeners[$eventName] === []) {
            unset($this->listeners[$eventName]);
            unset($this->sortedEvents[$eventName]);
            unset($this->maxListenersWarned[$eventName]);
        }

        return $this;
    }

    /**
     * Broadcasts an event to all registered listeners, announcing that something meaningful has occurred.
     * Matches both exact event names and wildcard patterns.
     *
     * @param string|\BackedEnum $event The name of the event to broadcast.
     * @param mixed ...$args The data to pass to each listener.
     */
    public function emit(string|\BackedEnum $event, mixed ...$args): void
    {
        $eventName = $this->normalizeEvent($event);
        $matchingListeners = $this->getMatchingListeners($eventName);

        if ($matchingListeners === []) {
            return;
        }

        foreach ($matchingListeners as $listener) {
            try {
                $result = $listener['callback'](...$args);

                // Stop propagation if listener returns false
                if ($result === false) {
                    break;
                }
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
     * @param string|\BackedEnum $event The name of the event to check.
     */
    public function hasListeners(string|\BackedEnum $event): bool
    {
        $eventName = $this->normalizeEvent($event);

        return isset($this->listeners[$eventName]) && $this->listeners[$eventName] !== [];
    }

    /**
     * Detaches all listeners, a crucial cleanup step to prevent memory leaks when a stream is closed.
     *
     * @param string|\BackedEnum|null $event The event to clear, or null to clear all events.
     */
    public function removeAllListeners(string|\BackedEnum|null $event = null): void
    {
        if ($event === null) {
            $this->listeners = [];
            $this->sortedEvents = [];
            $this->maxListenersWarned = [];
        } else {
            $eventName = $this->normalizeEvent($event);
            unset($this->listeners[$eventName]);
            unset($this->sortedEvents[$eventName]);
            unset($this->maxListenersWarned[$eventName]);
        }
    }

    /**
     * Get the number of listeners for a specific event or all events
     *
     * @param string|\BackedEnum|null $event The event name, or null to count all listeners
     * @return int
     */
    public function listenerCount(string|\BackedEnum|null $event = null): int
    {
        if ($event === null) {
            $count = 0;
            foreach ($this->listeners as $listeners) {
                $count += count($listeners);
            }

            return $count;
        }

        $eventName = $this->normalizeEvent($event);

        return isset($this->listeners[$eventName]) ? count($this->listeners[$eventName]) : 0;
    }

    /**
     * Get all event names that have registered listeners
     *
     * @return array<int, string>
     */
    public function eventNames(): array
    {
        return array_keys($this->listeners);
    }

    /**
     * Get all listeners for a specific event
     *
     * @param string|\BackedEnum $event The event name
     * @return array<int, callable>
     */
    public function listeners(string|\BackedEnum $event): array
    {
        $eventName = $this->normalizeEvent($event);

        if (! isset($this->listeners[$eventName])) {
            return [];
        }

        $this->sortListeners($eventName);

        return array_values(array_map(
            fn ($listener) => $listener['callback'],
            $this->listeners[$eventName]
        ));
    }

    /**
     * Get the raw listeners data for a specific event (includes priority info)
     *
     * @param string|\BackedEnum $event The event name
     * @return array<int, array{callback: callable, priority: int}>
     */
    public function rawListeners(string|\BackedEnum $event): array
    {
        $eventName = $this->normalizeEvent($event);

        if (! isset($this->listeners[$eventName])) {
            return [];
        }

        $this->sortListeners($eventName);

        return $this->listeners[$eventName];
    }

    /**
     * Normalize event to string
     */
    private function normalizeEvent(string|\BackedEnum $event): string
    {
        return $event instanceof \BackedEnum ? (string) $event->value : $event;
    }

    /**
     * Sort listeners by priority (higher priority first)
     */
    private function sortListeners(string $eventName): void
    {
        if (! isset($this->listeners[$eventName]) || ($this->sortedEvents[$eventName] ?? false)) {
            return;
        }

        uasort($this->listeners[$eventName], function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        $this->sortedEvents[$eventName] = true;
    }

    /**
     * Get all listeners that match the event name (exact + wildcards)
     *
     * @return array<int, array{callback: callable, priority: int}>
     */
    private function getMatchingListeners(string $eventName): array
    {
        $exactListeners = [];
        $wildcardListeners = [];

        if (isset($this->listeners[$eventName])) {
            $this->sortListeners($eventName);
            $exactListeners = $this->listeners[$eventName];
        }

        if ($this->wildcardListeners !== []) {
            foreach ($this->wildcardListeners as $pattern => $listeners) {
                if ($this->matchesPattern($pattern, $eventName)) {
                    $this->sortListeners($pattern);
                    $wildcardListeners = array_merge($wildcardListeners, $listeners);
                }
            }
        }

        if ($exactListeners === [] && $wildcardListeners === []) {
            return [];
        }

        if ($wildcardListeners === []) {
            return $exactListeners;
        }

        $matchingListeners = array_merge($exactListeners, $wildcardListeners);
        usort($matchingListeners, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $matchingListeners;
    }

    /**
     * Check if an event name matches a pattern (supports wildcards)
     */
    private function matchesPattern(string $pattern, string $eventName): bool
    {
        if ($pattern === $eventName) {
            return true;
        }

        if (strpos($pattern, '*') === false) {
            return false;
        }

        $regex = '/^' . str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        ) . '$/';

        return preg_match($regex, $eventName) === 1;
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

            return (bool) env('EVENT_THROW_ON_ERROR', false);
        } catch (EnvFileNotFoundException) {
            return false;
        }
    }

    /**
     * Check if the number of listeners exceeds the maximum and emit a warning
     */
    private function checkMaxListeners(string $eventName): void
    {
        if ($this->maxListeners === 0) {
            return;
        }

        if (isset($this->maxListenersWarned[$eventName])) {
            return;
        }

        $count = count($this->listeners[$eventName] ?? []) + count($this->wildcardListeners[$eventName] ?? []);

        if ($count > $this->maxListeners) {
            $this->maxListenersWarned[$eventName] = true;

            $message = sprintf(
                'Possible EventEmitter memory leak detected. %d listeners added for event "%s". ' .
                    'Use setMaxListeners() to increase limit or set to 0 to disable this warning.',
                $count,
                $eventName
            );

            if (function_exists('trigger_error')) {
                trigger_error($message, E_USER_WARNING);
            } else {
                error_log($message);
            }
        }
    }
}
