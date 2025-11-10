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
