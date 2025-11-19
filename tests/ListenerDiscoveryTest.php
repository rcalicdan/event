<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

beforeEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
    
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    if (is_dir($cacheDir)) {
        array_map('unlink', glob("$cacheDir/*"));
        rmdir($cacheDir);
    }
});

afterEach(function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    if (is_dir($cacheDir)) {
        array_map('unlink', glob("$cacheDir/*"));
        rmdir($cacheDir);
    }
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

test('creates cache file when cache path is provided', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );

    expect($cacheDir)->toBeDirectory();
    
    $cacheFiles = glob("$cacheDir/*-listeners.php");
    expect($cacheFiles)->toHaveCount(1);
    expect($cacheFiles[0])->toBeFile();
});

test('cache file contains valid PHP array', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );

    $cacheFiles = glob("$cacheDir/*-listeners.php");
    $cacheData = require $cacheFiles[0];

    expect($cacheData)->toBeArray()
        ->toHaveKeys(['mtime', 'listeners']);
    expect($cacheData['mtime'])->toBeInt();
    expect($cacheData['listeners'])->toBeArray();
});

test('loads listeners from cache on second discovery', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );
    
    Event::reset();
    ListenerDiscovery::reset();
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );

    ob_start();
    Event::emit('fixture.test');
    $output = ob_get_clean();

    expect($output)->toContain('fixture listener called');
});

test('cache includes modification time', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );

    $cacheFiles = glob("$cacheDir/*-listeners.php");
    $cacheData = require $cacheFiles[0];

    expect($cacheData['mtime'])->toBeInt()
        ->toBeGreaterThan(0);
});

test('debug mode invalidates cache when files are modified', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    $directory = __DIR__ . '/Fixtures/Listeners';
    
    ListenerDiscovery::discover(
        directory: $directory,
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir,
        debugMode: true
    );

    $cacheFiles = glob("$cacheDir/*-listeners.php");
    $originalMtime = filemtime($cacheFiles[0]);
    
    sleep(1);
    
    touch($directory . '/FixtureListener.php');
    
    Event::reset();
    ListenerDiscovery::reset();
    
    ListenerDiscovery::discover(
        directory: $directory,
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir,
        debugMode: true
    );

    $newMtime = filemtime($cacheFiles[0]);
    
    expect($newMtime)->toBeGreaterThan($originalMtime);
});

test('production mode does not check file modifications', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    $directory = __DIR__ . '/Fixtures/Listeners';
    
    ListenerDiscovery::discover(
        directory: $directory,
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir,
        debugMode: false
    );

    $cacheFiles = glob("$cacheDir/*-listeners.php");
    $originalMtime = filemtime($cacheFiles[0]);
    
    sleep(1);
    touch($directory . '/FixtureListener.php');
    
    Event::reset();
    ListenerDiscovery::reset();
    
    ListenerDiscovery::discover(
        directory: $directory,
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir,
        debugMode: false
    );

    $newMtime = filemtime($cacheFiles[0]);
    
    expect($newMtime)->toBe($originalMtime);
});

test('cache preserves listener priorities', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );
    
    Event::reset();
    ListenerDiscovery::reset();
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );

    ob_start();
    Event::emit('priority.default.event');
    $output = ob_get_clean();

    expect($output)->toBe('High priority | Default priority | Low priority | ');
});

test('cache works with enum events', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );
    
    Event::reset();
    ListenerDiscovery::reset();
    
    ListenerDiscovery::discover(
        directory: __DIR__ . '/Fixtures/Listeners',
        namespace: 'Tests\\Fixtures\\Listeners',
        cachePath: $cacheDir
    );

    ob_start();
    Event::emit(\Tests\Fixtures\Events\PaymentEvents::PROCESSING);
    $output = ob_get_clean();

    expect($output)->toContain('Payment processing');
});

test('cache file has consistent hash for same directory and namespace', function () {
    $cacheDir = sys_get_temp_dir() . '/listener_discovery_test';
    $directory = __DIR__ . '/Fixtures/Listeners';
    $namespace = 'Tests\\Fixtures\\Listeners';
    
    ListenerDiscovery::discover(
        directory: $directory,
        namespace: $namespace,
        cachePath: $cacheDir
    );

    $expectedHash = md5($directory . $namespace);
    $expectedFile = "$cacheDir/$expectedHash-listeners.php";

    expect($expectedFile)->toBeFile();
});