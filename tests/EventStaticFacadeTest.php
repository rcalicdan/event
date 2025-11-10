<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;
use Rcalicdan\Event\EventEmitter;

describe('Event static facade', function () {
    afterEach(function () {
        Event::reset();
    });

    it('provides static access to event emitter', function () {
        $called = false;

        Event::on('test', function () use (&$called) {
            $called = true;
        });

        Event::emit('test');

        expect($called)->toBeTrue();
    });

    it('uses singleton instance', function () {
        $instance1 = Event::getInstance();
        $instance2 = Event::getInstance();

        expect($instance1)->toBe($instance2);
    });

    it('can set custom instance', function () {
        $customEmitter = new EventEmitter();
        Event::setInstance($customEmitter);

        expect(Event::getInstance())->toBe($customEmitter);
    });

    it('can reset instance', function () {
        $instance1 = Event::getInstance();
        Event::reset();
        $instance2 = Event::getInstance();

        expect($instance1)->not->toBe($instance2);
    });

    it('resets all listeners when reset is called', function () {
        Event::on('test', function () {});

        expect(Event::hasListeners('test'))->toBeTrue();

        Event::reset();

        expect(Event::hasListeners('test'))->toBeFalse();
    });

    it('passes data through static methods', function () {
        $received = null;

        Event::on('data', function ($data) use (&$received) {
            $received = $data;
        });

        Event::emit('data', ['message' => 'Hello']);

        expect($received)->toBe(['message' => 'Hello']);
    });

    it('cannot be instantiated directly', function () {
        $reflection = new ReflectionClass(Event::class);
        $constructor = $reflection->getConstructor();

        expect($constructor->isPrivate())->toBeTrue();
    });

    it('properly isolates tests through reset', function () {
        Event::on('test1', function () {});
        Event::on('test2', function () {});

        expect(Event::hasListeners('test1'))->toBeTrue();
        expect(Event::hasListeners('test2'))->toBeTrue();

        Event::reset();

        expect(Event::hasListeners('test1'))->toBeFalse();
        expect(Event::hasListeners('test2'))->toBeFalse();

        Event::on('test3', function () {});
        expect(Event::hasListeners('test3'))->toBeTrue();
    });
});
