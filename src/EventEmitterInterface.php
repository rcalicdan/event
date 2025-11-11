<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

interface EventEmitterInterface
{
    /**
     * Attaches a callback to an event, enabling code to react to the event state changes.
     *
     * @param string|EventEnum $event The name of the event to listen for.
     * @param callable $callback The function to execute when the event occurs.
     * @return static
     */
    public function on(string|EventEnum $event, callable $callback): self;

    /**
     * Attaches a callback that is automatically removed after its first execution.
     * Useful for one-time setup or teardown logic without manual cleanup.
     *
     * @param string|EventEnum $event The name of the event to listen for.
     * @param callable $callback The function to execute once.
     * @return static
     */
    public function once(string|EventEnum $event, callable $callback): self;

    /**
     * Detaches a specific callback from an event to prevent memory leaks and manage resources.
     *
     * @param string|EventEnum $event The name of the event.
     * @param callable $callback The specific listener to remove.
     * @return static
     */
    public function removeListener(string|EventEnum $event, callable $callback): self;

    /**
     * Broadcasts an event to all registered listeners, announcing that something meaningful has occurred.
     *
     * @param string|EventEnum $event The name of the event to broadcast.
     * @param mixed ...$args The data to pass to each listener.
     */
    public function emit(string|EventEnum $event, mixed ...$args): void;

    /**
     * Checks if any listeners are registered, which can be used to avoid expensive work if no one is listening.
     *
     * @param string|EventEnum $event The name of the event to check.
     * @return bool
     */
    public function hasListeners(string|EventEnum $event): bool;

    /**
     * Detaches all listeners, a crucial cleanup step to prevent memory leaks when a stream is closed.
     *
     * @param string|EventEnum|null $event The event to clear, or null to clear all events.
     */
    public function removeAllListeners(string|EventEnum|null $event = null): void;

    /**
     * Configure whether to throw exceptions from listeners or emit them as 'error' events
     *
     * @param bool $throw If true, exceptions will be thrown. If false, they'll be emitted as 'error' events.
     * @return static
     */
    public function setThrowOnListenerError(bool $throw): self;
}
