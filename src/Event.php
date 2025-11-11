<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

class Event
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
     * Attaches a callback to an event.
     */
    public static function on(string|EventEnum $event, callable $callback): EventEmitterInterface
    {
        return self::getInstance()->on($event, $callback);
    }

    /**
     * Attaches a one-time callback to an event.
     */
    public static function once(string|EventEnum $event, callable $callback): EventEmitterInterface
    {
        return self::getInstance()->once($event, $callback);
    }

    /**
     * Removes a specific listener from an event.
     */
    public static function removeListener(string|EventEnum $event, callable $callback): EventEmitterInterface
    {
        return self::getInstance()->removeListener($event, $callback);
    }

    /**
     * Emits an event to all registered listeners.
     */
    public static function emit(string|EventEnum $event, mixed ...$args): void
    {
        self::getInstance()->emit($event, ...$args);
    }

    /**
     * Checks if an event has any listeners.
     */
    public static function hasListeners(string|EventEnum $event): bool
    {
        return self::getInstance()->hasListeners($event);
    }

    /**
     * Removes all listeners for a specific event or all events.
     */
    public static function removeAllListeners(string|EventEnum|null $event = null): void
    {
        self::getInstance()->removeAllListeners($event);
    }
}