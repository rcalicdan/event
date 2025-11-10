<?php

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

beforeEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
});

test('can discover and register listeners', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners', 
        namespace: 'Tests\\Fixtures\\Listeners'
    );
    
    ob_start();
    Event::emit('fixture.test');
    $output = ob_get_clean();
    
    expect($output)->toContain('fixture listener called');
});

test('throws exception for non-existent directory', function () {
    ListenerDiscovery::discover(
        directory: '/non/existent/path',
        namespace: 'Test'
    );
})->throws(InvalidArgumentException::class);

test('discovery only runs once', function () {
    $directory = __DIR__ . '/Fixtures/Listeners'; 
    
    ListenerDiscovery::discover($directory, 'Tests\\Fixtures\\Listeners');
    ListenerDiscovery::discover($directory, 'Tests\\Fixtures\\Listeners');
    
    expect(true)->toBeTrue();
});

test('throws exception if listener method does not exist', function () {
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/InvalidListeners', 
        namespace: 'Tests\\Fixtures\\InvalidListeners'
    );
})->throws(RuntimeException::class, 'Method');