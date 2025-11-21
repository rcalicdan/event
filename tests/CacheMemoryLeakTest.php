<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

$cacheDir = __DIR__ . '/temp_franken_test';
$scanDir = __DIR__ . '/Fixtures/FunctionListeners';

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

expect()->extend('toBeIdempotentInMemory', function () {
    return $this->toBeLessThanOrEqual(1024);
});

test('discovery is idempotent and safe for persistent request loops', function () use ($cacheDir, $scanDir) {
    ListenerDiscovery::discover(
        directory: $scanDir,
        cachePath: $cacheDir,
        refreshCache: false
    );

    gc_collect_cycles();
    $startMem = memory_get_usage(false);

    // 3. Simulate 50,000 Requests hitting the discovery logic
    for ($i = 0; $i < 50_000; $i++) {
        // The user mistakenly calls discover() inside the loop.
        ListenerDiscovery::discover(
            directory: $scanDir,
            cachePath: $cacheDir,
            refreshCache: false
        );
    }

    gc_collect_cycles();
    $endMem = memory_get_usage(false);
    $growth = max(0, $endMem - $startMem);

    expect($growth)->toBeIdempotentInMemory();
});
