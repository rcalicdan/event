<?php

use Rcalicdan\Event\EventEmitter;

describe('EventEmitter Priority Support', function () {

    it('executes listeners in priority order (highest first)', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, -10);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'high';
        }, 100);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'medium';
        }, 50);

        $emitter->emit('test.event');

        expect($executionOrder)->toBe(['high', 'medium', 'low']);
    });

    it('executes listeners with same priority in registration order', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'first';
        }, 0);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'second';
        }, 0);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'third';
        }, 0);

        $emitter->emit('test.event');

        expect($executionOrder)->toBe(['first', 'second', 'third']);
    });

    it('uses default priority of 0 when not specified', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'default';
        });

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'high';
        }, 100);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, -100);

        $emitter->emit('test.event');

        expect($executionOrder)->toBe(['high', 'default', 'low']);
    });

    it('handles negative priorities correctly', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'negative-low';
        }, -100);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'negative-high';
        }, -10);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'zero';
        }, 0);

        $emitter->emit('test.event');

        expect($executionOrder)->toBe(['zero', 'negative-high', 'negative-low']);
    });

    it('maintains priority order across multiple emissions', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, 10);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'high';
        }, 100);

        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['high', 'low']);

        $executionOrder = [];
        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['high', 'low']);
    });

    it('respects priority when adding listeners after initial emission', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'first';
        }, 10);

        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['first']);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'second-high';
        }, 100);

        $executionOrder = [];
        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['second-high', 'first']);
    });

    it('handles once listeners with priority', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->once('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'once-high';
        }, 100);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'on-low';
        }, 10);

        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['once-high', 'on-low']);

        // Emit again - once listener should not execute
        $executionOrder = [];
        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['on-low']);
    });

    it('maintains priority order with mixed once and on listeners', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'on-medium';
        }, 50);

        $emitter->once('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'once-high';
        }, 100);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'on-low';
        }, 10);

        $emitter->once('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'once-medium';
        }, 50);

        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['once-high', 'on-medium', 'once-medium', 'on-low']);
    });

    it('removes listener correctly and maintains priority order', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $listener1 = function () use (&$executionOrder) {
            $executionOrder[] = 'high';
        };

        $listener2 = function () use (&$executionOrder) {
            $executionOrder[] = 'medium';
        };

        $listener3 = function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        };

        $emitter->on('test.event', $listener1, 100);
        $emitter->on('test.event', $listener2, 50);
        $emitter->on('test.event', $listener3, 10);

        $emitter->removeListener('test.event', $listener2);

        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['high', 'low']);
    });

    it('handles very large priority values', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'normal';
        }, 0);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'very-high';
        }, PHP_INT_MAX);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'very-low';
        }, PHP_INT_MIN);

        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['very-high', 'normal', 'very-low']);
    });

    it('passes arguments correctly to prioritized listeners', function () {
        $emitter = new EventEmitter();
        $receivedArgs = [];

        $emitter->on('test.event', function ($arg1, $arg2) use (&$receivedArgs) {
            $receivedArgs[] = ['high', $arg1, $arg2];
        }, 100);

        $emitter->on('test.event', function ($arg1, $arg2) use (&$receivedArgs) {
            $receivedArgs[] = ['low', $arg1, $arg2];
        }, 10);

        $emitter->emit('test.event', 'value1', 'value2');

        expect($receivedArgs)->toBe([
            ['high', 'value1', 'value2'],
            ['low', 'value1', 'value2']
        ]);
    });

    it('works with multiple different events with different priorities', function () {
        $emitter = new EventEmitter();
        $event1Order = [];
        $event2Order = [];

        $emitter->on('event1', function () use (&$event1Order) {
            $event1Order[] = 'low';
        }, 10);

        $emitter->on('event1', function () use (&$event1Order) {
            $event1Order[] = 'high';
        }, 100);

        $emitter->on('event2', function () use (&$event2Order) {
            $event2Order[] = 'medium';
        }, 50);

        $emitter->on('event2', function () use (&$event2Order) {
            $event2Order[] = 'low';
        }, 10);

        $emitter->emit('event1');
        $emitter->emit('event2');

        expect($event1Order)->toBe(['high', 'low'])
            ->and($event2Order)->toBe(['medium', 'low']);
    });

    it('handles removeAllListeners and maintains priority on re-registration', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'first';
        }, 100);

        $emitter->removeAllListeners('test.event');

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, 10);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'high';
        }, 100);

        $emitter->emit('test.event');
        expect($executionOrder)->toBe(['high', 'low']);
    });

    it('handles priority with BackedEnum', function () {
        enum TestEvent: string
        {
            case PRIORITY_TEST = 'test.event';
        }

        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on(TestEvent::PRIORITY_TEST, function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, 10);

        $emitter->on(TestEvent::PRIORITY_TEST, function () use (&$executionOrder) {
            $executionOrder[] = 'high';
        }, 100);

        $emitter->emit(TestEvent::PRIORITY_TEST);
        expect($executionOrder)->toBe(['high', 'low']);
    });

    it('maintains priority order even with exception handling in resilient mode', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->setThrowOnListenerError(false);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'high';
        }, 100);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'error';
            throw new \Exception('Test exception');
        }, 50);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, 10);

        $emitter->on('error', function () use (&$executionOrder) {
            $executionOrder[] = 'error-handler';
        });

        $emitter->emit('test.event');

        expect($executionOrder)->toBe(['high', 'error', 'error-handler', 'low']);
    });

    it('allows chaining with priority', function () {
        $emitter = new EventEmitter();

        $result = $emitter
            ->on('event1', fn() => null, 100)
            ->on('event2', fn() => null, 50)
            ->once('event3', fn() => null, 25);

        expect($result)->toBeInstanceOf(EventEmitter::class);
    });

    it('sorts only once per event after multiple registrations', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        for ($i = 0; $i < 10; $i++) {
            $emitter->on('test.event', function () use (&$executionOrder, $i) {
                $executionOrder[] = "listener-{$i}";
            }, 100 - ($i * 10));
        }

        $emitter->emit('test.event');

        // Should be in priority order (100, 90, 80, ... 10)
        expect($executionOrder)->toBe([
            'listener-0',
            'listener-1',
            'listener-2',
            'listener-3',
            'listener-4',
            'listener-5',
            'listener-6',
            'listener-7',
            'listener-8',
            'listener-9',
        ]);
    });

    it('handles priority with zero listeners gracefully', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->emit('non-existent.event');
        expect($executionOrder)->toBe([]);
    });

    it('correctly identifies hasListeners regardless of priority', function () {
        $emitter = new EventEmitter();

        expect($emitter->hasListeners('test.event'))->toBeFalse();

        $emitter->on('test.event', fn() => null, 100);
        expect($emitter->hasListeners('test.event'))->toBeTrue();

        $emitter->on('test.event', fn() => null, -100);
        expect($emitter->hasListeners('test.event'))->toBeTrue();
    });

    it('handles complex priority scenarios with multiple operations', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'p100';
        }, 100);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'p50-a';
        }, 50);

        $emitter->once('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'p50-once';
        }, 50);

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'p0';
        });

        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'p-50';
        }, -50);

        $emitter->emit('test.event');

        expect($executionOrder)->toBe(['p100', 'p50-a', 'p50-once', 'p0', 'p-50']);
    });

    it('maintains priority when listener is re-added after removal', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $listener = function () use (&$executionOrder) {
            $executionOrder[] = 'reused';
        };

        $emitter->on('test.event', $listener, 100);
        $emitter->on('test.event', function () use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, 10);

        $emitter->removeListener('test.event', $listener);

        $emitter->on('test.event', $listener, 5);

        $emitter->emit('test.event');

        expect($executionOrder)->toBe(['low', 'reused']);
    });

    it('handles removeAllListeners with null parameter', function () {
        $emitter = new EventEmitter();
        $executionOrder = [];

        $emitter->on('event1', function () use (&$executionOrder) {
            $executionOrder[] = 'event1';
        }, 100);

        $emitter->on('event2', function () use (&$executionOrder) {
            $executionOrder[] = 'event2';
        }, 100);

        $emitter->removeAllListeners();

        $emitter->emit('event1');
        $emitter->emit('event2');

        expect($executionOrder)->toBe([]);
    });
});
