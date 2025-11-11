<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;
use Tests\Fixtures\Events\PaymentEvents;


beforeEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
    
    ListenerDiscovery::discover(
        __DIR__ . '/Fixtures/FunctionListeners',
        'Tests\\Fixtures\\FunctionListeners'
    );
});
afterEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
});

test('simple function listener works', function () {
    ob_start();
    Event::emit('function.simple');
    $output = ob_get_clean();

    expect($output)->toBe('Simple function called');
});

test('function with arguments works', function () {
    ob_start();
    Event::emit('function.with-args', 'Hello World', 42);
    $output = ob_get_clean();

    expect($output)->toBe('Function args: Hello World, 42');
});

test('multi-event function listener works', function () {
    ob_start();
    Event::emit('function.multi1');
    Event::emit('function.multi2');
    $output = ob_get_clean();

    expect($output)->toBe('Multi-event function calledMulti-event function called');
});

test('enum function listener works', function () {
    ob_start();
    Event::emit(PaymentEvents::PROCESSING);
    $output = ob_get_clean();

    expect($output)->toBe('Enum function listener called');
});

test('regular function without attribute is not registered', function () {
    expect(Event::hasListeners('regular.function'))->toBeFalse();
});

test('function listener has listeners check works', function () {
    expect(Event::hasListeners('function.simple'))->toBeTrue()
        ->and(Event::hasListeners('function.with-args'))->toBeTrue()
        ->and(Event::hasListeners('function.multi1'))->toBeTrue()
        ->and(Event::hasListeners('function.multi2'))->toBeTrue();
});

test('mixed class and function listeners work together', function () {
    ListenerDiscovery::reset();
    ListenerDiscovery::discover(
        __DIR__ . '/Fixtures/Listeners',
        'Tests\\Fixtures\\Listeners'
    );
    ListenerDiscovery::discover(
        __DIR__ . '/Fixtures/FunctionListeners',
        'Tests\\Fixtures\\FunctionListeners'
    );
    
    ob_start();
    Event::emit('fixture.test');
    Event::emit('function.simple');
    $output = ob_get_clean();

    expect($output)->toBe('fixture listener calledSimple function called');
});

test('function listener can be called multiple times', function () {
    ob_start();
    Event::emit('function.simple');
    Event::emit('function.simple');
    Event::emit('function.simple');
    $output = ob_get_clean();

    expect($output)->toBe('Simple function calledSimple function calledSimple function called');
});

test('async-style function listener works', function () {
    ob_start();
    Event::emit('function.async', 'test data');
    $output = ob_get_clean();

    expect($output)->toBe('Async-style handler: test data');
});

test('function is not registered twice on reset and rediscover', function () {
    // This test already has discovery from beforeEach
    // Manually reset and rediscover
    Event::reset();
    ListenerDiscovery::reset();

    ListenerDiscovery::discover(
        __DIR__ . '/Fixtures/FunctionListeners',
        'Tests\\Fixtures\\FunctionListeners'
    );

    ob_start();
    Event::emit('function.simple');
    $output = ob_get_clean();

    expect($output)->toBe('Simple function called');
});

test('function listener with complex data works', function () {
    Event::on('complex.data', function (array $data): void {
        echo json_encode($data);
    });

    ob_start();
    Event::emit('complex.data', ['name' => 'John', 'age' => 30]);
    $output = ob_get_clean();

    expect($output)->toBe('{"name":"John","age":30}');
});

test('function listener can be removed', function () {
    expect(Event::hasListeners('function.simple'))->toBeTrue();
    
    Event::removeAllListeners('function.simple');
    
    expect(Event::hasListeners('function.simple'))->toBeFalse();
    
    ob_start();
    Event::emit('function.simple');
    $output = ob_get_clean();

    expect($output)->toBe('');
});

test('function listener error handling works', function () {
    Event::on('error', function (\Throwable $e): void {
        echo "Error caught: {$e->getMessage()}";
    });

    require_once __DIR__ . '/Fixtures/InvalidListeners/throwing_function.php';
    
    Event::on('throwing.function', 'Tests\\Fixtures\\InvalidListeners\\throwingFunction');

    ob_start();
    Event::emit('throwing.function');
    $output = ob_get_clean();

    expect($output)->toBe('Error caught: Function listener error');
});

test('functions are discovered in subdirectories', function () {
    // All function listeners should be registered
    $events = [
        'function.simple',
        'function.with-args',
        'function.multi1',
        'function.multi2',
        'function.async',
    ];

    foreach ($events as $event) {
        expect(Event::hasListeners($event))->toBeTrue("Event '{$event}' should have listeners");
    }
});

test('function listener with no parameters works', function () {
    ob_start();
    Event::emit('function.simple');
    $output = ob_get_clean();

    expect($output)->toBe('Simple function called');
});

test('multiple function listeners on same event', function () {
    Event::on('function.simple', function (): void {
        echo ' and another listener';
    });

    ob_start();
    Event::emit('function.simple');
    $output = ob_get_clean();

    expect($output)->toBe('Simple function called and another listener');
});

test('function listener receives correct number of arguments', function () {
    ob_start();
    Event::emit('function.with-args', 'Test', 123);
    $output = ob_get_clean();

    expect($output)->toContain('Test')
        ->and($output)->toContain('123');
});

test('enum function listener with enum case', function () {
    ob_start();
    Event::emit(PaymentEvents::PROCESSING);
    $output = ob_get_clean();

    expect($output)->toBe('Enum function listener called');
});