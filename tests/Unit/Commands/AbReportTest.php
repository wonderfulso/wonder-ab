<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasTable('ab_instance')) {
        $this->artisan('migrate');
    }
});

test('ab report command exists', function () {
    $result = Artisan::call('ab:report');

    expect($result)->toBe(0);
});

test('ab report shows report data', function () {
    $this->artisan('ab:report')
        ->assertSuccessful();
});
