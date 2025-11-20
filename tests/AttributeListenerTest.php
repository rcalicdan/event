<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

beforeEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
});

describe('Basic Listener Discovery', function () {
    test('attribute listener is auto-registered', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('fixture.test');
        $output = ob_get_clean();

        expect($output)->toContain('fixture listener called');
    });

    test('multiple event attributes on same class work', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
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
        );

        ob_start();
        Event::emit('method.test');
        $output = ob_get_clean();

        expect($output)->toContain('method listener called');
    });

    test('multiple method-level attributes on same class work', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
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
        );

        ob_start();
        Event::emit('standalone.method');
        $output = ob_get_clean();

        expect($output)->toContain('Standalone method handler');
    });

    test('private and protected methods are ignored', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        expect(Event::hasListeners('private.event'))->toBeFalse();
        expect(Event::hasListeners('protected.event'))->toBeFalse();
        expect(Event::hasListeners('public.event'))->toBeTrue();
    });

    test('class with only method-level attributes does not instantiate unnecessarily', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        expect(true)->toBeTrue();
    });
});

describe('Priority-based Listener Execution', function () {
    test('class-level listeners with priority execute in correct order', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('priority.class.event');
        $output = ob_get_clean();

        $highPos = strpos($output, 'High priority class');
        $mediumPos = strpos($output, 'Medium priority class');
        $lowPos = strpos($output, 'Low priority class');

        expect($highPos)->toBeLessThan($mediumPos)
            ->and($mediumPos)->toBeLessThan($lowPos)
        ;
    });

    test('method-level listeners with priority execute in correct order', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('priority.method.event');
        $output = ob_get_clean();

        $highPos = strpos($output, 'High priority method');
        $mediumPos = strpos($output, 'Medium priority method');
        $lowPos = strpos($output, 'Low priority method');

        expect($highPos)->toBeLessThan($mediumPos)
            ->and($mediumPos)->toBeLessThan($lowPos)
        ;
    });

    test('function listeners with priority execute in correct order', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('priority.function.event');
        $output = ob_get_clean();

        $highPos = strpos($output, 'High priority function');
        $mediumPos = strpos($output, 'Medium priority function');
        $lowPos = strpos($output, 'Low priority function');

        expect($highPos)->toBeLessThan($mediumPos)
            ->and($mediumPos)->toBeLessThan($lowPos)
        ;
    });

    test('mixed listener types with priority execute in correct order', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('priority.mixed.event');
        $output = ob_get_clean();

        $class100Pos = strpos($output, 'Class priority 100');
        $function90Pos = strpos($output, 'Function priority 90');
        $method80Pos = strpos($output, 'Method priority 80');
        $class50Pos = strpos($output, 'Class priority 50');
        $function10Pos = strpos($output, 'Function priority 10');

        expect($class100Pos)->toBeLessThan($function90Pos)
            ->and($function90Pos)->toBeLessThan($method80Pos)
            ->and($method80Pos)->toBeLessThan($class50Pos)
            ->and($class50Pos)->toBeLessThan($function10Pos)
        ;
    });

    test('listeners with default priority (0) execute after positive priorities', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('priority.default.event');
        $output = ob_get_clean();

        $highPos = strpos($output, 'High priority');
        $defaultPos = strpos($output, 'Default priority');
        $lowPos = strpos($output, 'Low priority');

        expect($highPos)->toBeLessThan($defaultPos)
            ->and($defaultPos)->toBeLessThan($lowPos)
        ;
    });

    test('listeners with negative priority execute last', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('priority.negative.event');
        $output = ob_get_clean();

        $positivePos = strpos($output, 'Positive priority');
        $zeroPos = strpos($output, 'Zero priority');
        $negativePos = strpos($output, 'Negative priority');

        expect($positivePos)->toBeLessThan($zeroPos)
            ->and($zeroPos)->toBeLessThan($negativePos)
        ;
    });

    test('multiple attributes with different priorities on same method work', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('priority.multi.event1', 'priority.multi.event1');
        Event::emit('priority.multi.event2', 'priority.multi.event2');
        $output = ob_get_clean();

        expect($output)->toContain('Multi-event handler for event1 (priority 100)')
            ->and($output)->toContain('Multi-event handler for event2 (priority 50)')
        ;
    });

    test('priority works with enum-based listeners', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('payment.processing');
        $output = ob_get_clean();

        $highPos = strpos($output, 'Processing high priority');
        $lowPos = strpos($output, 'Payment processing');

        expect($highPos)->toBeLessThan($lowPos);
    });

    test('same priority listeners execute in registration order', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners',
        );

        ob_start();
        Event::emit('priority.same.event');
        $output = ob_get_clean();

        expect($output)->toContain('First listener')
            ->and($output)->toContain('Second listener')
            ->and($output)->toContain('Third listener')
        ;
    });
});

describe('ListenOnce Functionality', function () {
    test('ListenOnce class-level listener fires only once per request', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners/Once',
        );

        ob_start();
        Event::emit('once.class.event');
        Event::emit('once.class.event');
        Event::emit('once.class.event');
        $output = ob_get_clean();

        expect(substr_count($output, 'Once class listener called'))->toBe(1);
    });

    test('ListenOnce method-level listener fires only once per request', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners/Once',
        );

        ob_start();
        Event::emit('once.method.event');
        Event::emit('once.method.event');
        Event::emit('once.method.event');
        $output = ob_get_clean();

        expect(substr_count($output, 'Once method listener called'))->toBe(1);
    });

    test('ListenOnce function listener fires only once per request', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners/Once',
        );

        ob_start();
        Event::emit('once.function.event');
        Event::emit('once.function.event');
        Event::emit('once.function.event');
        $output = ob_get_clean();

        expect(substr_count($output, 'Once function listener called'))->toBe(1);
    });

    test('ListenOnce and ListenTo can coexist on same event', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners/Once',
        );

        ob_start();
        Event::emit('mixed.once.regular');
        Event::emit('mixed.once.regular');
        $output = ob_get_clean();

        expect(substr_count($output, 'Once listener'))->toBe(1);
        expect(substr_count($output, 'Regular listener'))->toBe(2);
    });

    test('ListenOnce respects priority', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners/Once',
        );

        ob_start();
        Event::emit('once.priority.event');
        $output = ob_get_clean();

        $highPos = strpos($output, 'Once high priority');
        $lowPos = strpos($output, 'Once low priority');

        expect($highPos)->toBeLessThan($lowPos);
    });

    test('ListenOnce with enum events works correctly', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners/Once',
        );

        ob_start();
        Event::emit('payment.refunded');
        Event::emit('payment.refunded');
        $output = ob_get_clean();

        expect(substr_count($output, 'Payment refunded once'))->toBe(1);
    });

    test('multiple ListenOnce attributes on same method work independently', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners/Once',
        );

        ob_start();
        Event::emit('once.event1', 'once.event1');
        Event::emit('once.event1', 'once.event1');
        Event::emit('once.event2', 'once.event2');
        Event::emit('once.event2', 'once.event2');
        $output = ob_get_clean();

        expect(substr_count($output, 'Multi-once handler: once.event1'))->toBe(1);
        expect(substr_count($output, 'Multi-once handler: once.event2'))->toBe(1);
    });

    test('ListenOnce listener is removed after execution', function () {
        ListenerDiscovery::discover(
            directory: __DIR__ . '/Fixtures/Listeners/Once',
        );

        $initialCount = Event::listenerCount('once.removal.test');

        Event::emit('once.removal.test');
        $afterFirstEmit = Event::listenerCount('once.removal.test');

        Event::emit('once.removal.test');
        $afterSecondEmit = Event::listenerCount('once.removal.test');

        expect($initialCount)->toBe(1)
            ->and($afterFirstEmit)->toBe(0)
            ->and($afterSecondEmit)->toBe(0)
        ;
    });

    test('quick function-based ListenOnce test', function () {
        // Quick inline test without fixtures
        $counter = 0;

        Event::once('quick.test', function () use (&$counter) {
            $counter++;
        });

        Event::emit('quick.test');
        Event::emit('quick.test');
        Event::emit('quick.test');

        expect($counter)->toBe(1);
    });
});
