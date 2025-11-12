<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

interface EventEmitterInterface
{
    /**
     * Attaches a callback to an event, enabling code to react to the event state changes.
     *
     * @param string|\BackedEnum $event The name of the event to listen for.
     * @param callable $callback The function to execute when the event occurs.
     * @param int $priority The priority of the listener (higher = executed first). Default: 0
     * @return static
     */
    public function on(string|\BackedEnum $event, callable $callback, int $priority = 0): self;

    /**
     * Attaches a callback that is automatically removed after its first execution.
     * Useful for one-time setup or teardown logic without manual cleanup.
     *
     * @param string|\BackedEnum $event The name of the event to listen for.
     * @param callable $callback The function to execute once.
     * @param int $priority The priority of the listener (higher = executed first). Default: 0
     * @return static
     */
    public function once(string|\BackedEnum $event, callable $callback, int $priority = 0): self;

    /**
     * Detaches a specific callback from an event to prevent memory leaks and manage resources.
     *
     * @param string|\BackedEnum $event The name of the event.
     * @param callable $callback The specific listener to remove.
     * @return static
     */
    public function removeListener(string|\BackedEnum $event, callable $callback): self;

    /**
     * Broadcasts an event to all registered listeners, announcing that something meaningful has occurred.
     *
     * @param string|\BackedEnum $event The name of the event to broadcast.
     * @param mixed ...$args The data to pass to each listener.
     */
    public function emit(string|\BackedEnum $event, mixed ...$args): void;

    /**
     * Checks if any listeners are registered, which can be used to avoid expensive work if no one is listening.
     *
     * @param string|\BackedEnum $event The name of the event to check.
     * @return bool
     */
    public function hasListeners(string|\BackedEnum $event): bool;

    /**
     * Detaches all listeners, a crucial cleanup step to prevent memory leaks when a stream is closed.
     *
     * @param string|\BackedEnum|null $event The event to clear, or null to clear all events.
     */
    public function removeAllListeners(string|\BackedEnum|null $event = null): void;

    /**
     * Configure whether to throw exceptions from listeners or emit them as 'error' events
     *
     * @param bool $throw If true, exceptions will be thrown. If false, they'll be emitted as 'error' events.
     * @return static
     */
    public function setThrowOnListenerError(bool $throw): self;

    /**
     * Get the number of listeners for a specific event or all events
     *
     * @param string|\BackedEnum|null $event The event name, or null to count all listeners
     * @return int
     */
    public function listenerCount(string|\BackedEnum|null $event = null): int;

    /**
     * Get all event names that have registered listeners
     *
     * @return array<int, string>
     */
    public function eventNames(): array;

    /**
     * Get all listeners for a specific event
     *
     * @param string|\BackedEnum $event The event name
     * @return array<int, callable>
     */
    public function listeners(string|\BackedEnum $event): array;

    /**
     * Get the raw listeners data for a specific event (includes priority info)
     *
     * @param string|\BackedEnum $event The event name
     * @return array<int, array{callback: callable, priority: int}>
     */
    public function rawListeners(string|\BackedEnum $event): array;

    /**
     * Set the maximum number of listeners for an event before a warning is emitted.
     * Set to 0 to disable the warning.
     *
     * @param int $max The maximum number of listeners (0 = unlimited)
     * @return static
     */
    public function setMaxListeners(int $max): self;

    /**
     * Get the current maximum listeners setting
     *
     * @return int
     */
    public function getMaxListeners(): int;
}