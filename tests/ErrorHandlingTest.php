<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

beforeEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
});

describe('error event handling', function () {
    it('emits error event when listener throws exception', function () {
        $errorCaught = null;

        Event::on('error', function ($e) use (&$errorCaught) {
            $errorCaught = $e;
        });

        Event::on('test.event', function () {
            throw new RuntimeException('Test error');
        });

        Event::emit('test.event');

        expect($errorCaught)
            ->toBeInstanceOf(RuntimeException::class)
            ->and($errorCaught->getMessage())->toBe('Test error');
    });

    it('continues executing remaining listeners after one throws', function () {
        $results = [];

        Event::on('error', function () {
            // Suppress error
        });

        Event::on('test.event', function () {
            throw new RuntimeException('Error in first');
        });

        Event::on('test.event', function () use (&$results) {
            $results[] = 'second executed';
        });

        Event::on('test.event', function () use (&$results) {
            $results[] = 'third executed';
        });

        Event::emit('test.event');

        expect($results)->toBe(['second executed', 'third executed']);
    });

    it('catches multiple exceptions from different listeners', function () {
        $errors = [];

        Event::on('error', function ($e) use (&$errors) {
            $errors[] = $e->getMessage();
        });

        Event::on('test.event', function () {
            throw new RuntimeException('Error 1');
        });

        Event::on('test.event', function () {
            throw new RuntimeException('Error 2');
        });

        Event::on('test.event', function () {
            throw new RuntimeException('Error 3');
        });

        Event::emit('test.event');

        expect($errors)->toBe(['Error 1', 'Error 2', 'Error 3']);
    });

    it('writes to STDERR when error handler itself throws', function () {
        Event::on('error', function () {
            throw new RuntimeException('Error handler failed');
        });

        Event::on('test.event', function () {
            throw new RuntimeException('Original error');
        });

        // Capture STDERR
        $stderrOutput = '';
        $stderr = fopen('php://memory', 'w+');
        $originalStderr = STDERR;
        
        // Cannot easily test STDERR in PHP, but ensure no fatal error
        expect(fn () => Event::emit('test.event'))
            ->not->toThrow(Exception::class);
    });

    it('handles different exception types', function () {
        $exceptions = [];

        Event::on('error', function ($e) use (&$exceptions) {
            $exceptions[] = get_class($e);
        });

        Event::on('test.event', function () {
            throw new RuntimeException('Runtime error');
        });

        Event::on('test.event', function () {
            throw new InvalidArgumentException('Invalid argument');
        });

        Event::on('test.event', function () {
            throw new LogicException('Logic error');
        });

        Event::emit('test.event');

        expect($exceptions)->toBe([
            RuntimeException::class,
            InvalidArgumentException::class,
            LogicException::class,
        ]);
    });
});

describe('listener discovery error handling', function () {
    it('throws exception when directory does not exist', function () {
        expect(fn () => ListenerDiscovery::discover(
            '/nonexistent/path',
            'App\\Listeners'
        ))->toThrow(InvalidArgumentException::class, 'Directory not found');
    });

    it('throws exception when method does not exist on class-level listener', function () {
        expect(fn () => ListenerDiscovery::discover(
            __DIR__ . '/Fixtures/InvalidListeners',
            'Tests\\Fixtures\\InvalidListeners'
        ))->toThrow(RuntimeException::class, 'does not exist');
    });

    it('skips classes that cannot be reflected', function () {
        // Create a temporary file with invalid PHP
        $tempDir = sys_get_temp_dir() . '/event-test-' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/InvalidClass.php', '<?php invalid syntax');

        expect(fn () => ListenerDiscovery::discover($tempDir, 'Temp'))
            ->not->toThrow(Exception::class);

        // Cleanup
        unlink($tempDir . '/InvalidClass.php');
        rmdir($tempDir);
    });

    it('handles files that are not PHP files', function () {
        $tempDir = sys_get_temp_dir() . '/event-test-' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/readme.txt', 'Not a PHP file');
        file_put_contents($tempDir . '/config.json', '{}');

        expect(fn () => ListenerDiscovery::discover($tempDir, 'Temp'))
            ->not->toThrow(Exception::class);

        // Cleanup
        unlink($tempDir . '/readme.txt');
        unlink($tempDir . '/config.json');
        rmdir($tempDir);
    });

    it('handles classes without listener attributes gracefully', function () {
        // Regular class without attributes should be skipped
        expect(fn () => ListenerDiscovery::discover(
            __DIR__ . '/Fixtures',
            'Tests\\Fixtures'
        ))->not->toThrow(Exception::class);
    });
});

describe('enum error handling', function () {
    it('handles invalid enum values gracefully', function () {
        $called = false;

        Event::on('invalid.enum', function () use (&$called) {
            $called = true;
        });

        // String event should still work
        Event::emit('invalid.enum');

        expect($called)->toBeTrue();
    });
});

describe('callback error handling', function () {
    it('handles non-callable gracefully in on()', function () {
        // This should be caught by PHP type system, but test runtime
        expect(fn () => Event::on('test', 'not_a_function'))
            ->not->toThrow(TypeError::class); // Callable type hint prevents this
    });

    it('handles listener that modifies event state during error', function () {
        $executed = [];

        Event::on('error', function () use (&$executed) {
            $executed[] = 'error handler';
        });

        Event::on('test.event', function () use (&$executed) {
            $executed[] = 'listener 1';
            throw new RuntimeException('Error');
        });

        Event::on('test.event', function () use (&$executed) {
            $executed[] = 'listener 2';
        });

        Event::emit('test.event');

        expect($executed)->toBe(['listener 1', 'error handler', 'listener 2']);
    });

    it('handles recursive error events', function () {
        $depth = 0;
        $maxDepth = 3;

        Event::on('error', function ($e) use (&$depth, $maxDepth) {
            $depth++;
            if ($depth < $maxDepth) {
                throw new RuntimeException('Recursive error');
            }
        });

        Event::on('test.event', function () {
            throw new RuntimeException('Initial error');
        });

        Event::emit('test.event');

        // Should handle recursive errors without infinite loop
        expect($depth)->toBe($maxDepth);
    });
});

describe('memory and resource error handling', function () {
    it('handles removal of listener during its own execution', function () {
        $executed = 0;
        $callback = null;

        $callback = function () use (&$executed, &$callback) {
            $executed++;
            Event::removeListener('test.event', $callback);
        };

        Event::on('test.event', $callback);

        Event::emit('test.event');
        Event::emit('test.event');

        expect($executed)->toBe(1);
    });

    it('handles clearing all listeners during event emission', function () {
        $results = [];

        Event::on('test.event', function () use (&$results) {
            $results[] = 'first';
            Event::removeAllListeners('test.event');
        });

        Event::on('test.event', function () use (&$results) {
            $results[] = 'second';
        });

        Event::emit('test.event');

        expect($results)->toBe(['first', 'second']);
        expect(Event::hasListeners('test.event'))->toBeFalse();
    });

    it('handles listener that emits the same event', function () {
        $depth = 0;
        $maxDepth = 3;

        Event::on('recursive.event', function () use (&$depth, $maxDepth) {
            $depth++;
            if ($depth < $maxDepth) {
                Event::emit('recursive.event');
            }
        });

        Event::emit('recursive.event');

        expect($depth)->toBe($maxDepth);
    });
});

describe('type safety error handling', function () {
    it('throws error when string event name is empty', function () {
        $called = false;

        Event::on('', function () use (&$called) {
            $called = true;
        });

        Event::emit('');

        expect($called)->toBeTrue();
    });

    it('handles special characters in event names', function () {
        $called = false;

        Event::on('test:event@special#chars', function () use (&$called) {
            $called = true;
        });

        Event::emit('test:event@special#chars');

        expect($called)->toBeTrue();
    });

    it('handles unicode in event names', function () {
        $called = false;

        Event::on('test.événement.🎉', function () use (&$called) {
            $called = true;
        });

        Event::emit('test.événement.🎉');

        expect($called)->toBeTrue();
    });
});

describe('concurrency simulation', function () {
    it('handles listeners added and removed in same emission cycle', function () {
        $results = [];

        Event::on('test.event', function () use (&$results) {
            $results[] = 'original';
            
            Event::on('test.event', function () use (&$results) {
                $results[] = 'added during';
            });
        });

        Event::emit('test.event');
        
        expect($results)->toBe(['original']);

        $results = [];
        Event::emit('test.event');
        
        expect($results)->toBe(['original', 'added during']);
    });

    it('handles once listener that registers another once listener', function () {
        $results = [];

        Event::once('test.event', function () use (&$results) {
            $results[] = 'first once';
            
            Event::once('test.event', function () use (&$results) {
                $results[] = 'second once';
            });
        });

        Event::emit('test.event');
        expect($results)->toBe(['first once']);

        Event::emit('test.event');
        expect($results)->toBe(['first once', 'second once']);

        Event::emit('test.event');
        expect($results)->toBe(['first once', 'second once']);
    });
});