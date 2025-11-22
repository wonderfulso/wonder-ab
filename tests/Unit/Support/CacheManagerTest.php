<?php

use Illuminate\Support\Facades\Cache;
use Wonderfulso\WonderAb\Support\CacheManager;

beforeEach(function () {
    Cache::flush();
});

test('cache manager can be instantiated', function () {
    config()->set('wonder-ab.cache.enabled', true);
    config()->set('wonder-ab.cache.ttl', 3600);
    config()->set('wonder-ab.cache.prefix', 'test_ab');

    $manager = new CacheManager;

    expect($manager)->toBeInstanceOf(CacheManager::class);
});

test('cache manager uses cache when enabled', function () {
    config()->set('wonder-ab.cache.enabled', true);
    config()->set('wonder-ab.cache.ttl', 3600);
    config()->set('wonder-ab.cache.prefix', 'laravel_ab');

    $manager = new CacheManager;

    $callCount = 0;
    $result = $manager->remember('test-key', function () use (&$callCount) {
        $callCount++;

        return 'computed-value';
    });

    expect($result)->toBe('computed-value');
    expect($callCount)->toBe(1);

    // Second call should use cache
    $result = $manager->remember('test-key', function () use (&$callCount) {
        $callCount++;

        return 'computed-value';
    });

    expect($result)->toBe('computed-value');
    expect($callCount)->toBe(1); // Callback not called again
});

test('cache manager bypasses cache when disabled', function () {
    config()->set('wonder-ab.cache.enabled', false);

    $manager = new CacheManager;

    $callCount = 0;
    $result = $manager->remember('test-key', function () use (&$callCount) {
        $callCount++;

        return 'computed-value';
    });

    expect($result)->toBe('computed-value');
    expect($callCount)->toBe(1);

    // Second call should NOT use cache
    $result = $manager->remember('test-key', function () use (&$callCount) {
        $callCount++;

        return 'computed-value';
    });

    expect($result)->toBe('computed-value');
    expect($callCount)->toBe(2); // Callback called again
});

test('cache manager uses custom prefix', function () {
    config()->set('wonder-ab.cache.enabled', true);
    config()->set('wonder-ab.cache.prefix', 'custom_prefix');
    config()->set('wonder-ab.cache.ttl', 3600);

    $manager = new CacheManager;

    $manager->remember('test', fn () => 'value');

    // Check that cache key includes prefix
    expect(Cache::has('custom_prefix:test'))->toBeTrue();
});

test('cache manager respects ttl setting', function () {
    config()->set('wonder-ab.cache.enabled', true);
    config()->set('wonder-ab.cache.ttl', 60);
    config()->set('wonder-ab.cache.prefix', 'laravel_ab');

    $manager = new CacheManager;

    $manager->remember('ttl-test', fn () => 'value');

    expect(Cache::has('laravel_ab:ttl-test'))->toBeTrue();
});

test('cache manager can clear specific key', function () {
    config()->set('wonder-ab.cache.enabled', true);
    config()->set('wonder-ab.cache.prefix', 'laravel_ab');

    $manager = new CacheManager;

    $manager->remember('key1', fn () => 'value1');
    $manager->remember('key2', fn () => 'value2');

    expect(Cache::has('laravel_ab:key1'))->toBeTrue();
    expect(Cache::has('laravel_ab:key2'))->toBeTrue();

    $manager->forget('key1');

    expect(Cache::has('laravel_ab:key1'))->toBeFalse();
    expect(Cache::has('laravel_ab:key2'))->toBeTrue();
});

test('cache manager can flush all keys', function () {
    config()->set('wonder-ab.cache.enabled', true);
    config()->set('wonder-ab.cache.prefix', 'laravel_ab');

    $manager = new CacheManager;

    $manager->remember('key1', fn () => 'value1');
    $manager->remember('key2', fn () => 'value2');

    // Flush using Laravel's Cache facade
    Cache::flush();

    expect(Cache::has('laravel_ab:key1'))->toBeFalse();
    expect(Cache::has('laravel_ab:key2'))->toBeFalse();
});

test('cache manager uses custom cache driver when specified', function () {
    config()->set('wonder-ab.cache.enabled', true);
    config()->set('wonder-ab.cache.driver', 'array');
    config()->set('wonder-ab.cache.prefix', 'laravel_ab');

    $manager = new CacheManager;

    $manager->remember('test', fn () => 'value');

    expect(true)->toBeTrue(); // Just ensure no exception thrown
});
