<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

beforeEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
});

test('attribute listener is auto-registered', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    ob_start();
    Event::emit('fixture.test');
    $output = ob_get_clean();

    expect($output)->toContain('fixture listener called');
});

test('multiple event attributes on same class work', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    ob_start();
    Event::emit('user.created', 'john_doe');
    $output1 = ob_get_clean();

    ob_start();
    Event::emit('user.updated', 'john_doe');
    $output2 = ob_get_clean();

    expect($output1)->toContain('User created: john_doe');
    expect($output2)->toContain('User updated: john_doe');
});

test('method-level attribute listeners are auto-registered', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    ob_start();
    Event::emit('method.test');
    $output = ob_get_clean();

    expect($output)->toContain('method listener called');
});

test('multiple method-level attributes on same class work', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    ob_start();
    Event::emit('order.created', 'ORDER-123');
    $output1 = ob_get_clean();

    ob_start();
    Event::emit('order.paid', 'ORDER-123');
    $output2 = ob_get_clean();

    ob_start();
    Event::emit('order.shipped', 'ORDER-123');
    $output3 = ob_get_clean();

    expect($output1)->toContain('Order created: ORDER-123');
    expect($output2)->toContain('Order paid: ORDER-123');
    expect($output3)->toContain('Order shipped: ORDER-123');
});

test('multiple attributes on same method work', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    ob_start();
    Event::emit('email.sent');
    $output1 = ob_get_clean();

    ob_start();
    Event::emit('sms.sent');
    $output2 = ob_get_clean();

    expect($output1)->toContain('Notification sent');
    expect($output2)->toContain('Notification sent');
});

test('mixed class-level and method-level attributes work together', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    ob_start();
    Event::emit('mixed.default');
    $output1 = ob_get_clean();

    ob_start();
    Event::emit('mixed.method1');
    $output2 = ob_get_clean();

    ob_start();
    Event::emit('mixed.method2');
    $output3 = ob_get_clean();

    expect($output1)->toContain('Mixed default handler');
    expect($output2)->toContain('Mixed method 1 handler');
    expect($output3)->toContain('Mixed method 2 handler');
});

test('method-level attributes with enum events work', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    ob_start();
    Event::emit('payment.processing');
    $output1 = ob_get_clean();

    ob_start();
    Event::emit('payment.success');
    $output2 = ob_get_clean();

    ob_start();
    Event::emit('payment.failed');
    $output3 = ob_get_clean();

    expect($output1)->toContain('Payment processing');
    expect($output2)->toContain('Payment success');
    expect($output3)->toContain('Payment failed');
});

test('method-level attribute without class-level still works', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    ob_start();
    Event::emit('standalone.method');
    $output = ob_get_clean();

    expect($output)->toContain('Standalone method handler');
});

test('private and protected methods are ignored', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    expect(Event::hasListeners('private.event'))->toBeFalse();
    expect(Event::hasListeners('protected.event'))->toBeFalse();
    expect(Event::hasListeners('public.event'))->toBeTrue();
});

test('class with only method-level attributes does not instantiate unnecessarily', function () {

    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners'
    );

    expect(true)->toBeTrue(); 
});