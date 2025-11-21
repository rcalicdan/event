<?php

declare(strict_types=1);

use DI\Container;
use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;
use Tests\Fixtures\Classes\Psr11\ContainerListener;
use Tests\Fixtures\Classes\Psr11\DependencyService;

$cacheDir = __DIR__ . '/temp_psr11_cache';
$scanDir = __DIR__ . '/Fixtures/Classes/Psr11';

beforeEach(function () use ($cacheDir) {
    Event::reset();
    ListenerDiscovery::reset();
    if (! is_dir($cacheDir)) {
        mkdir($cacheDir);
    }
});

afterEach(function () use ($cacheDir) {
    Event::reset();
    ListenerDiscovery::reset();
    array_map('unlink', glob("$cacheDir/*.*"));
    if (is_dir($cacheDir)) {
        rmdir($cacheDir);
    }
});

test('listeners are resolved via real PHP-DI container', function () use ($cacheDir, $scanDir) {
    $container = new Container();
    $spyService = new DependencyService();
    $container->set(DependencyService::class, $spyService);
    $container->set(ContainerListener::class, \DI\autowire(ContainerListener::class));

    ListenerDiscovery::discover(
        directory: $scanDir,
        cachePath: $cacheDir,
        refreshCache: true,
        container: $container
    );

    Event::emit('test.psr11');

    expect($spyService->called)->toBeTrue();
});

test('container resolution works even from cached discovery', function () use ($cacheDir, $scanDir) {
    // 1. Setup Container
    $container = new Container();
    $spyService = new DependencyService();
    $container->set(DependencyService::class, $spyService);
    $container->set(ContainerListener::class, \DI\autowire(ContainerListener::class));

    // 2. Phase 1: Generate Cache (Cold Boot)
    ListenerDiscovery::discover(
        directory: $scanDir,
        cachePath: $cacheDir,
        refreshCache: true,
        container: $container
    );

    // Reset State (Simulate new request)
    Event::reset();
    ListenerDiscovery::reset();

    // 3. Phase 2: Load from Cache (Hot Boot)
    ListenerDiscovery::discover(
        directory: $scanDir,
        cachePath: $cacheDir,
        refreshCache: false, // Force cache usage
        container: $container
    );

    Event::emit('test.psr11');

    expect($spyService->called)->toBeTrue();
});
