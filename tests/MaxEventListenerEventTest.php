<?php

declare(strict_types=1);

use Rcalicdan\Event\EventEmitter;

describe('EventEmitter max listeners', function () {
    it('has default max listeners set to 0', function () {
        $emitter = new EventEmitter();

        expect($emitter->getMaxListeners())->toBe(0);
    });

    it('can set custom max listeners limit', function () {
        $emitter = new EventEmitter();

        $emitter->setMaxListeners(20);

        expect($emitter->getMaxListeners())->toBe(20);
    });

    it('can disable max listeners warning by setting to 0', function () {
        $emitter = new EventEmitter();

        $emitter->setMaxListeners(0);

        expect($emitter->getMaxListeners())->toBe(0);
    });

    it('returns self for fluent interface on setMaxListeners', function () {
        $emitter = new EventEmitter();

        $result = $emitter->setMaxListeners(15);

        expect($result)->toBe($emitter);
    });

    it('does not accept negative max listeners', function () {
        $emitter = new EventEmitter();

        $emitter->setMaxListeners(-5);

        expect($emitter->getMaxListeners())->toBe(0);
    });

    it('emits warning when exceeding max listeners', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(3);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        // Add 4 listeners (exceeds limit of 3)
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});
        $emitter->on('test', function () {}); // This should trigger warning

        restore_error_handler();

        expect(count($warnings))->toBe(1);
        expect($warnings[0])->toContain('Possible EventEmitter memory leak detected');
        expect($warnings[0])->toContain('4 listeners');
        expect($warnings[0])->toContain('event "test"');
    });

    it('does not emit warning when at max listeners limit', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(3);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        // Add exactly 3 listeners (at limit, not exceeding)
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});

        restore_error_handler();

        expect(count($warnings))->toBe(0);
    });

    it('does not emit warning when max listeners is 0', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(0);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        // Add many listeners
        for ($i = 0; $i < 100; $i++) {
            $emitter->on('test', function () {});
        }

        restore_error_handler();

        expect(count($warnings))->toBe(0);
    });

    it('only warns once per event', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(2);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        // Add listeners that exceed the limit multiple times
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});
        $emitter->on('test', function () {}); // Warning triggered here
        $emitter->on('test', function () {}); // Should not warn again
        $emitter->on('test', function () {}); // Should not warn again

        restore_error_handler();

        expect(count($warnings))->toBe(1);
    });

    it('warns separately for different events', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(2);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        // Exceed limit for 'test1'
        $emitter->on('test1', function () {});
        $emitter->on('test1', function () {});
        $emitter->on('test1', function () {});

        // Exceed limit for 'test2'
        $emitter->on('test2', function () {});
        $emitter->on('test2', function () {});
        $emitter->on('test2', function () {});

        restore_error_handler();

        expect(count($warnings))->toBe(2);
        expect($warnings[0])->toContain('event "test1"');
        expect($warnings[1])->toContain('event "test2"');
    });

    it('resets warning state when all listeners are removed', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(2);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        // Trigger warning
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});

        expect(count($warnings))->toBe(1);

        // Remove all listeners
        $emitter->removeAllListeners('test');

        // Add listeners again - should warn again
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});

        restore_error_handler();

        expect(count($warnings))->toBe(2);
    });

    it('resets warning state when last listener is removed individually', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(2);

        $callback1 = function () {};
        $callback2 = function () {};
        $callback3 = function () {};

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        // Trigger warning
        $emitter->on('test', $callback1);
        $emitter->on('test', $callback2);
        $emitter->on('test', $callback3);

        expect(count($warnings))->toBe(1);

        // Remove all listeners individually
        $emitter->removeListener('test', $callback1);
        $emitter->removeListener('test', $callback2);
        $emitter->removeListener('test', $callback3);

        // Add listeners again - should warn again
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});
        $emitter->on('test', function () {});

        restore_error_handler();

        expect(count($warnings))->toBe(2);
    });

    it('warning message includes helpful instructions', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(1);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        $emitter->on('test', function () {});
        $emitter->on('test', function () {});

        restore_error_handler();

        expect($warnings[0])->toContain('setMaxListeners()');
        expect($warnings[0])->toContain('set to 0 to disable');
    });

    it('does not interfere with normal event emission', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(2);
        $counter = 0;

        set_error_handler(function () {
            return true;
        });

        // Exceed limit
        $emitter->on('test', function () use (&$counter) {
            $counter++;
        });
        $emitter->on('test', function () use (&$counter) {
            $counter++;
        });
        $emitter->on('test', function () use (&$counter) {
            $counter++;
        });

        restore_error_handler();

        $emitter->emit('test');

        expect($counter)->toBe(3);
    });

    it('works with once listeners', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(2);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        $emitter->once('test', function () {});
        $emitter->once('test', function () {});
        $emitter->once('test', function () {});

        restore_error_handler();

        expect(count($warnings))->toBe(1);
    });

    it('works with wildcard events', function () {
        $emitter = new EventEmitter();
        $emitter->setMaxListeners(2);

        $warnings = [];
        set_error_handler(function ($errno, $errstr) use (&$warnings) {
            $warnings[] = $errstr;

            return true;
        });

        $emitter->on('user.*', function () {});
        $emitter->on('user.*', function () {});
        $emitter->on('user.*', function () {});

        restore_error_handler();

        expect(count($warnings))->toBe(1);
        expect($warnings[0])->toContain('event "user.*"');
    });
});
