<?php

declare(strict_types=1);

use Rcalicdan\Event\EventEmitter;

describe('EventEmitter functionality', function () {
    it('can register and trigger events', function () {
        $emitter = new EventEmitter();
        $called = false;

        $emitter->on('test', function () use (&$called) {
            $called = true;
        });

        $emitter->emit('test');

        expect($called)->toBeTrue();
    });

    it('supports method chaining', function () {
        $emitter = new EventEmitter();
        $counter = 0;

        $result = $emitter
            ->on('event1', function () use (&$counter) {
                $counter++;
            })
            ->on('event2', function () use (&$counter) {
                $counter++;
            })
            ->once('event3', function () use (&$counter) {
                $counter++;
            })
        ;

        expect($result)->toBeInstanceOf(EventEmitter::class);

        $emitter->emit('event1');
        $emitter->emit('event2');
        $emitter->emit('event3');

        expect($counter)->toBe(3);
    });

    it('can pass data through events', function () {
        $emitter = new EventEmitter();
        $received = null;

        $emitter->on('data', function ($data) use (&$received) {
            $received = $data;
        });

        $emitter->emit('data', ['user' => 'John', 'age' => 30]);

        expect($received)->toBe(['user' => 'John', 'age' => 30]);
    });

    it('handles multiple listeners on same event', function () {
        $emitter = new EventEmitter();
        $results = [];

        $emitter->on('event', function () use (&$results) {
            $results[] = 'first';
        });

        $emitter->on('event', function () use (&$results) {
            $results[] = 'second';
        });

        $emitter->on('event', function () use (&$results) {
            $results[] = 'third';
        });

        $emitter->emit('event');

        expect($results)->toBe(['first', 'second', 'third']);
    });

    it('can remove specific listeners', function () {
        $emitter = new EventEmitter();
        $counter = 0;

        $callback1 = function () use (&$counter) {
            $counter++;
        };
        $callback2 = function () use (&$counter) {
            $counter += 10;
        };

        $emitter->on('test', $callback1);
        $emitter->on('test', $callback2);

        $emitter->emit('test');
        expect($counter)->toBe(11);

        $emitter->removeListener('test', $callback1);
        $emitter->emit('test');

        expect($counter)->toBe(21);
    });

    it('can remove all listeners', function () {
        $emitter = new EventEmitter();
        $counter = 0;

        $emitter->on('test', function () use (&$counter) {
            $counter++;
        });
        $emitter->on('test', function () use (&$counter) {
            $counter++;
        });
        $emitter->on('other', function () use (&$counter) {
            $counter++;
        });

        $emitter->removeAllListeners('test');
        $emitter->emit('test');

        expect($counter)->toBe(0);

        $emitter->emit('other');
        expect($counter)->toBe(1);
    });

    it('checks for listeners existence', function () {
        $emitter = new EventEmitter();

        expect($emitter->hasListeners('test'))->toBeFalse();

        $emitter->on('test', function () {});

        expect($emitter->hasListeners('test'))->toBeTrue();
    });

    it('handles once listeners correctly', function () {
        $emitter = new EventEmitter();
        $counter = 0;

        $emitter->once('test', function () use (&$counter) {
            $counter++;
        });

        $emitter->emit('test');
        $emitter->emit('test');
        $emitter->emit('test');

        expect($counter)->toBe(1);
    });

    it('handles errors in listeners gracefully', function () {
        $emitter = new EventEmitter();
        $errorCaught = false;
        $normalListenerCalled = false;

        $emitter->on('error', function () use (&$errorCaught) {
            $errorCaught = true;
        });

        $emitter->on('test', function () {
            throw new RuntimeException('Test error');
        });

        $emitter->on('test', function () use (&$normalListenerCalled) {
            $normalListenerCalled = true;
        });

        $emitter->emit('test');

        expect($errorCaught)->toBeTrue();
        expect($normalListenerCalled)->toBeTrue();
    });

    it('stops event propagation when listener returns false', function () {
        $emitter = new EventEmitter();
        $results = [];

        $emitter->on('test', function () use (&$results) {
            $results[] = 'first';

            return false;
        });

        $emitter->on('test', function () use (&$results) {
            $results[] = 'second';
        });

        $emitter->on('test', function () use (&$results) {
            $results[] = 'third';
        });

        $emitter->emit('test');

        expect($results)->toBe(['first']);
    });
});

describe('EventEmitter type safety', function () {
    it('returns self for fluent interface', function () {
        $emitter = new EventEmitter();

        $result1 = $emitter->on('test', function () {});
        $result2 = $emitter->once('test', function () {});
        $result3 = $emitter->removeListener('test', function () {});

        expect($result1)->toBe($emitter);
        expect($result2)->toBe($emitter);
        expect($result3)->toBe($emitter);
    });
});

describe('EventEmitter wildcard patterns', function () {
    it('supports wildcard listeners for multiple events', function () {
        $emitter = new EventEmitter();
        $results = [];

        $emitter->on('user.*', function ($action) use (&$results) {
            $results[] = "wildcard: $action";
        });

        $emitter->emit('user.created', 'create');
        $emitter->emit('user.updated', 'update');
        $emitter->emit('user.deleted', 'delete');

        expect($results)->toBe([
            'wildcard: create',
            'wildcard: update',
            'wildcard: delete',
        ]);
    });

    it('matches both exact and wildcard listeners', function () {
        $emitter = new EventEmitter();
        $results = [];

        $emitter->on('user.*', function () use (&$results) {
            $results[] = 'wildcard';
        });

        $emitter->on('user.created', function () use (&$results) {
            $results[] = 'exact';
        });

        $emitter->emit('user.created');

        expect($results)->toContain('wildcard');
        expect($results)->toContain('exact');
    });

    it('respects priority with wildcard listeners', function () {
        $emitter = new EventEmitter();
        $results = [];

        $emitter->on('user.*', function () use (&$results) {
            $results[] = 'wildcard-low';
        }, 0);

        $emitter->on('user.created', function () use (&$results) {
            $results[] = 'exact-high';
        }, 10);

        $emitter->on('user.*', function () use (&$results) {
            $results[] = 'wildcard-high';
        }, 5);

        $emitter->emit('user.created');

        expect($results)->toBe(['exact-high', 'wildcard-high', 'wildcard-low']);
    });

    it('supports multiple wildcard segments', function () {
        $emitter = new EventEmitter();
        $called = false;

        $emitter->on('*.*.*', function () use (&$called) {
            $called = true;
        });

        $emitter->emit('app.user.created');

        expect($called)->toBeTrue();
    });

    it('does not match unrelated events with wildcards', function () {
        $emitter = new EventEmitter();
        $called = false;

        $emitter->on('user.*', function () use (&$called) {
            $called = true;
        });

        $emitter->emit('product.created');

        expect($called)->toBeFalse();
    });

    it('supports catch-all wildcard', function () {
        $emitter = new EventEmitter();
        $events = [];

        $emitter->on('*', function () use (&$events) {
            $events[] = 'caught';
        });

        $emitter->emit('user.created');
        $emitter->emit('product.updated');
        $emitter->emit('anything');

        expect($events)->toBe(['caught', 'caught', 'caught']);
    });
});

describe('EventEmitter introspection', function () {
    it('counts listeners for a specific event', function () {
        $emitter = new EventEmitter();

        expect($emitter->listenerCount('test'))->toBe(0);

        $emitter->on('test', function () {});
        expect($emitter->listenerCount('test'))->toBe(1);

        $emitter->on('test', function () {});
        $emitter->on('test', function () {});
        expect($emitter->listenerCount('test'))->toBe(3);
    });

    it('counts all listeners across all events', function () {
        $emitter = new EventEmitter();

        expect($emitter->listenerCount())->toBe(0);

        $emitter->on('event1', function () {});
        $emitter->on('event1', function () {});
        $emitter->on('event2', function () {});
        $emitter->on('event3', function () {});

        expect($emitter->listenerCount())->toBe(4);
    });

    it('returns all event names', function () {
        $emitter = new EventEmitter();

        expect($emitter->eventNames())->toBe([]);

        $emitter->on('user.created', function () {});
        $emitter->on('user.updated', function () {});
        $emitter->on('product.deleted', function () {});

        $eventNames = $emitter->eventNames();

        expect($eventNames)->toContain('user.created');
        expect($eventNames)->toContain('user.updated');
        expect($eventNames)->toContain('product.deleted');
        expect(count($eventNames))->toBe(3);
    });

    it('returns listeners for a specific event', function () {
        $emitter = new EventEmitter();

        $callback1 = function () { return 'first'; };
        $callback2 = function () { return 'second'; };
        $callback3 = function () { return 'third'; };

        $emitter->on('test', $callback1);
        $emitter->on('test', $callback2);
        $emitter->on('other', $callback3);

        $listeners = $emitter->listeners('test');

        expect(count($listeners))->toBe(2);
        expect($listeners[0])->toBe($callback1);
        expect($listeners[1])->toBe($callback2);
    });

    it('returns empty array for events with no listeners', function () {
        $emitter = new EventEmitter();

        expect($emitter->listeners('nonexistent'))->toBe([]);
    });

    it('returns raw listeners with priority information', function () {
        $emitter = new EventEmitter();

        $callback1 = function () {};
        $callback2 = function () {};

        $emitter->on('test', $callback1, 10);
        $emitter->on('test', $callback2, 5);

        $rawListeners = $emitter->rawListeners('test');

        expect(count($rawListeners))->toBe(2);
        expect($rawListeners[0]['callback'])->toBe($callback1);
        expect($rawListeners[0]['priority'])->toBe(10);
        expect($rawListeners[1]['callback'])->toBe($callback2);
        expect($rawListeners[1]['priority'])->toBe(5);
    });

    it('respects priority order in listeners method', function () {
        $emitter = new EventEmitter();

        $callback1 = fn () => 'low';
        $callback2 = fn () => 'high';
        $callback3 = fn () => 'medium';

        $emitter->on('test', $callback1, 0);
        $emitter->on('test', $callback2, 10);
        $emitter->on('test', $callback3, 5);

        $listeners = $emitter->listeners('test');

        expect($listeners[0])->toBe($callback2);
        expect($listeners[1])->toBe($callback3);
        expect($listeners[2])->toBe($callback1);
    });

    it('updates counts after removing listeners', function () {
        $emitter = new EventEmitter();

        $callback = function () {};

        $emitter->on('test', $callback);
        $emitter->on('test', function () {});

        expect($emitter->listenerCount('test'))->toBe(2);

        $emitter->removeListener('test', $callback);

        expect($emitter->listenerCount('test'))->toBe(1);

        $emitter->removeAllListeners('test');

        expect($emitter->listenerCount('test'))->toBe(0);
        expect($emitter->eventNames())->not->toContain('test');
    });
});
