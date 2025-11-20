<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;
use Rcalicdan\Event\ListenerDiscovery;

beforeEach(function () {
    Event::reset();
    ListenerDiscovery::reset();
});

function getMemoryGrowth(callable $logic, int $iterations = 10000): int
{
    // 1. Warmup (Let PHP allocate internal string caches/JIT)
    for ($i = 0; $i < 100; $i++) {
        $logic();
    }

    gc_collect_cycles();
    $startMem = memory_get_usage(false);

    for ($i = 0; $i < $iterations; $i++) {
        $logic();
    }

    gc_collect_cycles();
    $endMem = memory_get_usage(false);

    return max(0, $endMem - $startMem);
}

expect()->extend('toNotLeakMemory', function () {
    return $this->toBeLessThanOrEqual(64);
});

test('static stream emitting does not leak memory', function () {
    Event::on('stream', fn ($data) => true);

    $growth = getMemoryGrowth(function () {
        Event::emit('stream', 'payload data', 123);
    }, 100_000); 

    expect($growth)->toNotLeakMemory();
});

test('adding and removing listeners does not leak memory', function () {
    $growth = getMemoryGrowth(function () {
        $cb = fn () => true;
        Event::on('dynamic', $cb);
        Event::emit('dynamic');
        Event::removeListener('dynamic', $cb);
    }, 50_000);

    expect($growth)->toNotLeakMemory();
});

test('once listeners self-destruct without leaking memory', function () {
    $growth = getMemoryGrowth(function () {
        Event::once('oneshot', fn () => true);
        Event::emit('oneshot');
    }, 50_000);

    expect($growth)->toNotLeakMemory();
});

test('enum events do not leak memory', function () {
    enum MemoryTestEvents: string {
        case TEST = 'test';
    }

    Event::on(MemoryTestEvents::TEST, fn () => true);

    $growth = getMemoryGrowth(function () {
        Event::emit(MemoryTestEvents::TEST);
    }, 50_000);

    expect($growth)->toNotLeakMemory();
});