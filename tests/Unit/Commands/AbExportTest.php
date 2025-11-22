<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Wonderfulso\WonderAb\Models\Experiments;

beforeEach(function () {
    if (! Schema::hasTable('ab_instance')) {
        $this->artisan('migrate');
    }
});

test('ab export command exists', function () {
    $result = Artisan::call('ab:export');

    expect($result)->toBe(0);
});

test('ab export shows experiments when data exists', function () {
    // Create test experiment
    $experiment = Experiments::create([
        'experiment' => 'test-export',
        'goal' => 'conversion',
        'visitors' => 0,
    ]);

    $this->artisan('ab:export')
        ->assertSuccessful();
});

test('ab export handles empty database', function () {
    $this->artisan('ab:export')
        ->assertSuccessful();
});
