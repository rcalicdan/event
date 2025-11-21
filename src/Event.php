<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

final class Event
{
    private static ?EventEmitterInterface $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Get the shared event emitter instance.
     */
    public static function getInstance(): EventEmitterInterface
    {
        if (self::$instance === null) {
            self::$instance = new EventEmitter();
        }

        return self::$instance;
    }

    /**
     * Set a custom event emitter instance.
     */
    public static function setInstance(EventEmitterInterface $emitter): void
    {
        self::$instance = $emitter;
    }

    /**
     * Reset the instance and clear all listeners.
     * Useful for testing to ensure a clean state.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Configure whether to throw exceptions from listeners or emit them as 'error' events.
     */
    public static function setThrowOnListenerError(bool $throw): void
    {
        self::getInstance()->setThrowOnListenerError($throw);
    }

    /**
     * Enable fail-fast mode (useful for development).
     * Exceptions thrown by listeners will propagate immediately.
     */
    public static function failFast(): void
    {
        self::setThrowOnListenerError(true);
    }

    /**
     * Enable resilient mode (useful for production).
     * Exceptions thrown by listeners will be caught and emitted as 'error' events.
     */
    public static function resilient(): void
    {
        self::setThrowOnListenerError(false);
    }

    /**
     * Set the maximum number of listeners for an event before a warning is emitted.
     * Set to 0 to disable the warning.
     */
    public static function setMaxListeners(int $max): void
    {
        self::getInstance()->setMaxListeners($max);
    }

    /**
     * Get the current maximum listeners setting
     */
    public static function getMaxListeners(): int
    {
        return self::getInstance()->getMaxListeners();
    }

    /**
     * Attaches a callback to an event.
     */
    public static function on(string|\BackedEnum $event, callable $callback, int $priority = 0): EventEmitterInterface
    {
        return self::getInstance()->on($event, $callback, $priority);
    }

    /**
     * Attaches a one-time callback to an event.
     */
    public static function once(string|\BackedEnum $event, callable $callback, int $priority = 0): EventEmitterInterface
    {
        return self::getInstance()->once($event, $callback, $priority);
    }

    /**
     * Removes a specific listener from an event.
     */
    public static function removeListener(string|\BackedEnum $event, callable $callback): EventEmitterInterface
    {
        return self::getInstance()->removeListener($event, $callback);
    }

    /**
     * Emits an event to all registered listeners.
     */
    public static function emit(string|\BackedEnum $event, mixed ...$args): void
    {
        self::getInstance()->emit($event, ...$args);
    }

    /**
     * Checks if an event has any listeners.
     */
    public static function hasListeners(string|\BackedEnum $event): bool
    {
        return self::getInstance()->hasListeners($event);
    }

    /**
     * Removes all listeners for a specific event or all events.
     */
    public static function removeAllListeners(string|\BackedEnum|null $event = null): void
    {
        self::getInstance()->removeAllListeners($event);
    }

    /**
     * Get the number of listeners for a specific event or all events.
     */
    public static function listenerCount(string|\BackedEnum|null $event = null): int
    {
        return self::getInstance()->listenerCount($event);
    }

    /**
     * Get all event names that have registered listeners.
     *
     * @return array<int, string>
     */
    public static function eventNames(): array
    {
        return self::getInstance()->eventNames();
    }

    /**
     * Get all listeners for a specific event.
     *
     * @return array<int, callable>
     */
    public static function listeners(string|\BackedEnum $event): array
    {
        return self::getInstance()->listeners($event);
    }

    /**
     * Get the raw listeners data for a specific event (includes priority info).
     *
     * @return array<int, array{callback: callable, priority: int}>
     */
    public static function rawListeners(string|\BackedEnum $event): array
    {
        return self::getInstance()->rawListeners($event);
    }
}
