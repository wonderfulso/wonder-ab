<?php

use Wonderfulso\WonderAb\Models\Events;
use Wonderfulso\WonderAb\Models\Experiments;
use Wonderfulso\WonderAb\Models\Instance;

test('event can be created', function () {
    $instance = Instance::create(['instance' => 'event-instance']);
    $experiment = Experiments::create([
        'experiment' => 'event-test',
        'goal' => 'conversion',
        'visitors' => 0,
    ]);

    $event = Events::create([
        'instance_id' => $instance->id,
        'experiments_id' => $experiment->id,
        'name' => 'event-test',
        'value' => 'variant-a',
    ]);

    expect($event)->toBeInstanceOf(Events::class);
    expect($event->name)->toBe('event-test');
    expect($event->value)->toBe('variant-a');
});

test('event has instance relationship', function () {
    $instance = Instance::create(['instance' => 'event-rel-instance']);

    $event = Events::create([
        'instance_id' => $instance->id,
        'name' => 'test',
        'value' => 'a',
    ]);

    expect($event->instance())->not->toBeNull();
    expect($event->instance()->first())->toBeInstanceOf(Instance::class);
    expect($event->instance()->first()->id)->toBe($instance->id);
});

test('event has experiment relationship', function () {
    $experiment = Experiments::create([
        'experiment' => 'event-exp-rel',
        'goal' => 'test',
        'visitors' => 0,
    ]);

    $event = Events::create([
        'experiments_id' => $experiment->id,
        'name' => 'event-exp-rel',
        'value' => 'b',
    ]);

    expect($event->experiment)->toBeInstanceOf(Experiments::class);
    expect($event->experiment->id)->toBe($experiment->id);
});

test('events can be queried by name', function () {
    Events::create([
        'name' => 'searchable-event',
        'value' => 'x',
    ]);

    $found = Events::where('name', 'searchable-event')->first();

    expect($found)->not->toBeNull();
    expect($found->name)->toBe('searchable-event');
});

test('events can be queried by value', function () {
    Events::create([
        'name' => 'value-query-test',
        'value' => 'specific-variant',
    ]);

    $found = Events::where('value', 'specific-variant')->first();

    expect($found)->not->toBeNull();
    expect($found->value)->toBe('specific-variant');
});
