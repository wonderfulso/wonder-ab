<?php

use Wonderfulso\WonderAb\Models\Goal;
use Wonderfulso\WonderAb\Models\Instance;

test('goal can be created', function () {
    $instance = Instance::create(['instance' => 'goal-test-instance']);

    $goal = Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'purchase',
        'value' => 99.99,
    ]);

    expect($goal)->toBeInstanceOf(Goal::class);
    expect($goal->goal)->toBe('purchase');
    expect($goal->value)->toBe(99.99);
});

test('goal can be created without value', function () {
    $instance = Instance::create(['instance' => 'goal-no-value']);

    $goal = Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'signup',
    ]);

    expect($goal->goal)->toBe('signup');
    expect($goal->value)->toBeNull();
});

test('goal has instance relationship', function () {
    $instance = Instance::create(['instance' => 'goal-instance-rel']);

    $goal = Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'click',
    ]);

    expect($goal->instance())->not->toBeNull();
    expect($goal->instance()->first())->toBeInstanceOf(Instance::class);
    expect($goal->instance()->first()->id)->toBe($instance->id);
});

test('goal value can be numeric', function () {
    $instance = Instance::create(['instance' => 'numeric-goal']);

    $goal = Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'revenue',
        'value' => 1234.56,
    ]);

    expect($goal->value)->toBe(1234.56);
});

test('goal value can be integer', function () {
    $instance = Instance::create(['instance' => 'int-goal']);

    $goal = Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'items_purchased',
        'value' => 5,
    ]);

    expect($goal->value)->toBe(5);
});

test('multiple goals can be tracked for same instance', function () {
    $instance = Instance::create(['instance' => 'multi-goals']);

    Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'viewed_product',
    ]);

    Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'added_to_cart',
    ]);

    Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'purchased',
        'value' => 50.00,
    ]);

    expect($instance->goals()->count())->toBe(3);
});

test('goals can be queried by name', function () {
    $instance = Instance::create(['instance' => 'query-goals']);

    Goal::create([
        'instance_id' => $instance->id,
        'goal' => 'specific-goal',
        'value' => 100,
    ]);

    $found = Goal::where('goal', 'specific-goal')->first();

    expect($found)->not->toBeNull();
    expect($found->goal)->toBe('specific-goal');
    expect((int) $found->value)->toBe(100);
});
