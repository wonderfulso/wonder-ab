<?php

use Illuminate\Support\Facades\Log;
use Wonderfulso\WonderAb\Analytics\Drivers\LogDriver;

test('log driver can be instantiated', function () {
    $driver = new LogDriver;

    expect($driver)->toBeInstanceOf(LogDriver::class);
});

test('log driver tracks experiment to log', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('AB Experiment', \Mockery::type('array'));

    $driver = new LogDriver;
    $driver->trackExperiment('test-experiment', 'variant-a', 'instance-123');
});

test('log driver tracks goal to log', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('AB Goal', \Mockery::type('array'));

    $driver = new LogDriver;
    $driver->trackGoal('signup', 'instance-123', 100);
});

test('log driver sends batch to log', function () {
    $events = [
        ['type' => 'experiment', 'payload' => ['experiment' => 'test', 'variant' => 'a', 'instance' => '123']],
        ['type' => 'goal', 'payload' => ['goal' => 'signup', 'instance' => '123', 'value' => 100]],
    ];

    Log::shouldReceive('info')
        ->once()
        ->with('AB Events Batch', [
            'count' => 2,
            'events' => $events,
        ]);

    $driver = new LogDriver;
    $driver->sendBatch($events);
});
