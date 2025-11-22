<?php

use Wonderfulso\WonderAb\Models\Events;
use Wonderfulso\WonderAb\Models\Experiments;

test('experiment can be created', function () {
    $experiment = Experiments::create([
        'experiment' => 'test-experiment',
        'goal' => 'conversion',
    ]);

    expect($experiment)->toBeInstanceOf(Experiments::class);
    expect($experiment->experiment)->toBe('test-experiment');
    expect($experiment->goal)->toBe('conversion');
});

test('experiment has events relationship', function () {
    $experiment = Experiments::create([
        'experiment' => 'test-events-rel',
        'goal' => 'signup',
    ]);

    expect($experiment->events())->not->toBeNull();
});

test('experiment can have multiple events', function () {
    $experiment = Experiments::create([
        'experiment' => 'multi-events',
        'goal' => 'click',
    ]);

    Events::create([
        'experiments_id' => $experiment->id,
        'name' => 'multi-events',
        'value' => 'variant-a',
    ]);

    Events::create([
        'experiments_id' => $experiment->id,
        'name' => 'multi-events',
        'value' => 'variant-b',
    ]);

    expect($experiment->events()->count())->toBe(2);
});

test('experiment can be updated', function () {
    $experiment = Experiments::create([
        'experiment' => 'update-test',
        'goal' => 'pageview',
    ]);

    $experiment->goal = 'conversion';
    $experiment->save();

    $reloaded = Experiments::find($experiment->id);

    expect($reloaded->goal)->toBe('conversion');
});

test('experiment can be found by name and goal', function () {
    Experiments::create([
        'experiment' => 'find-test',
        'goal' => 'purchase',
    ]);

    $found = Experiments::where([
        'experiment' => 'find-test',
        'goal' => 'purchase',
    ])->first();

    expect($found)->not->toBeNull();
    expect($found->experiment)->toBe('find-test');
    expect($found->goal)->toBe('purchase');
});
