<?php

use Wonderfulso\WonderAb\Analytics\Drivers\NoneDriver;

test('none driver can be instantiated', function () {
    $driver = new NoneDriver;

    expect($driver)->toBeInstanceOf(NoneDriver::class);
});

test('none driver track experiment does nothing', function () {
    $driver = new NoneDriver;
    $driver->trackExperiment('test-experiment', 'variant-a', 'instance-123');

    expect(true)->toBeTrue();
});

test('none driver track goal does nothing', function () {
    $driver = new NoneDriver;
    $driver->trackGoal('signup', 'instance-123', 100);

    expect(true)->toBeTrue();
});

test('none driver send batch does nothing', function () {
    $driver = new NoneDriver;
    $driver->sendBatch([
        ['type' => 'experiment', 'payload' => ['experiment' => 'test', 'variant' => 'a', 'instance' => '123']],
    ]);

    expect(true)->toBeTrue();
});
