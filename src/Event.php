<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

class Event
{
    private static ?EventEmitterInterface $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}

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
     * Attaches a callback to an event.
     */
    public static function on(string $event, callable $callback): EventEmitterInterface
    {
        return self::getInstance()->on($event, $callback);
    }

    /**
     * Attaches a one-time callback to an event.
     */
    public static function once(string $event, callable $callback): EventEmitterInterface
    {
        return self::getInstance()->once($event, $callback);
    }

    /**
     * Removes a specific listener from an event.
     */
    public static function removeListener(string $event, callable $callback): EventEmitterInterface
    {
        return self::getInstance()->removeListener($event, $callback);
    }

    /**
     * Emits an event to all registered listeners.
     */
    public static function emit(string $event, mixed ...$args): void
    {
        self::getInstance()->emit($event, ...$args);
    }

    /**
     * Checks if an event has any listeners.
     */
    public static function hasListeners(string $event): bool
    {
        return self::getInstance()->hasListeners($event);
    }

    /**
     * Removes all listeners for a specific event or all events.
     */
    public static function removeAllListeners(?string $event = null): void
    {
        self::getInstance()->removeAllListeners($event);
    }
}
