<?php

use Wonderfulso\WonderAb\Models\Events;
use Wonderfulso\WonderAb\Models\Instance;

test('instance can be created', function () {
    $instance = Instance::create([
        'instance' => 'test-instance-123',
        'identifier' => 'user@example.com',
    ]);

    expect($instance)->toBeInstanceOf(Instance::class);
    expect($instance->instance)->toBe('test-instance-123');
    expect($instance->identifier)->toBe('user@example.com');
});

test('instance metadata is cast to array', function () {
    $instance = Instance::create([
        'instance' => 'test-metadata',
        'metadata' => ['key' => 'value', 'number' => 42],
    ]);

    expect($instance->metadata)->toBeArray();
    expect($instance->metadata['key'])->toBe('value');
    expect($instance->metadata['number'])->toBe(42);
});

test('instance metadata persists as JSON', function () {
    $instance = Instance::create([
        'instance' => 'test-json',
        'metadata' => ['browser' => 'Firefox', 'version' => 120],
    ]);

    $reloaded = Instance::find($instance->id);

    expect($reloaded->metadata)->toBeArray();
    expect($reloaded->metadata['browser'])->toBe('Firefox');
    expect($reloaded->metadata['version'])->toBe(120);
});

test('instance has events relationship', function () {
    $instance = Instance::create([
        'instance' => 'test-relationships',
    ]);

    expect($instance->events())->not->toBeNull();
});

test('instance has goals relationship', function () {
    $instance = Instance::create([
        'instance' => 'test-goals-rel',
    ]);

    expect($instance->goals())->not->toBeNull();
});

test('instance can have multiple events', function () {
    $instance = Instance::create([
        'instance' => 'test-multiple-events',
    ]);

    Events::create([
        'instance_id' => $instance->id,
        'name' => 'test-1',
        'value' => 'a',
    ]);

    Events::create([
        'instance_id' => $instance->id,
        'name' => 'test-2',
        'value' => 'b',
    ]);

    expect($instance->events()->count())->toBe(2);
});

test('instance is unique', function () {
    Instance::create([
        'instance' => 'unique-test',
    ]);

    expect(fn () => Instance::create([
        'instance' => 'unique-test',
    ]))->toThrow(\Exception::class);
});

test('instance can be found by instance id', function () {
    Instance::create([
        'instance' => 'findable-123',
    ]);

    $found = Instance::where('instance', 'findable-123')->first();

    expect($found)->not->toBeNull();
    expect($found->instance)->toBe('findable-123');
});
