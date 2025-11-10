<?php

use Rcalicdan\Event\EventEmitter;
use Rcalicdan\Event\EventEmitterInterface;

describe('EventEmitter class', function () {
    it('implements EventEmitterInterface', function () {
        $emitter = new EventEmitter();
        
        expect($emitter)->toBeInstanceOf(EventEmitterInterface::class);
    });

    it('can be instantiated', function () {
        $emitter = new EventEmitter();
        
        expect($emitter)->toBeInstanceOf(EventEmitter::class);
    });

    it('provides all interface methods', function () {
        $emitter = new EventEmitter();
        
        expect($emitter)->toHaveMethod('on');
        expect($emitter)->toHaveMethod('once');
        expect($emitter)->toHaveMethod('removeListener');
        expect($emitter)->toHaveMethod('emit');
        expect($emitter)->toHaveMethod('hasListeners');
        expect($emitter)->toHaveMethod('removeAllListeners');
    });
});

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
            ->on('event1', function () use (&$counter) { $counter++; })
            ->on('event2', function () use (&$counter) { $counter++; })
            ->once('event3', function () use (&$counter) { $counter++; });
        
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
        
        $callback1 = function () use (&$counter) { $counter++; };
        $callback2 = function () use (&$counter) { $counter += 10; };
        
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
        
        $emitter->on('test', function () use (&$counter) { $counter++; });
        $emitter->on('test', function () use (&$counter) { $counter++; });
        $emitter->on('other', function () use (&$counter) { $counter++; });
        
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
            throw new \RuntimeException('Test error');
        });
        
        $emitter->on('test', function () use (&$normalListenerCalled) {
            $normalListenerCalled = true;
        });
        
        $emitter->emit('test');
        
        expect($errorCaught)->toBeTrue();
        expect($normalListenerCalled)->toBeTrue();
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