<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Ensure migrations are run
    if (! Schema::hasTable('ab_instance')) {
        $this->artisan('migrate');
    }
});

test('ab validate command exists', function () {
    $result = Artisan::call('ab:validate');

    expect($result)->toBe(0);
});

test('ab validate checks database tables', function () {
    $this->artisan('ab:validate')
        ->expectsOutput('Checking database tables...')
        ->assertSuccessful();
});

test('ab validate checks configuration', function () {
    $this->artisan('ab:validate')
        ->expectsOutput('Checking configuration...')
        ->assertSuccessful();
});

test('ab validate shows analytics driver', function () {
    config()->set('wonder-ab.analytics.driver', 'log');

    $this->artisan('ab:validate')
        ->expectsOutputToContain('Analytics driver: log')
        ->assertSuccessful();
});

test('ab validate shows auth type', function () {
    config()->set('wonder-ab.report_auth', 'basic');

    $this->artisan('ab:validate')
        ->expectsOutputToContain('Report auth type: basic')
        ->assertSuccessful();
});

test('ab validate shows data counts', function () {
    $this->artisan('ab:validate')
        ->expectsOutput('Checking data...')
        ->assertSuccessful();
});

test('ab validate warns about missing analytics config', function () {
    config()->set('wonder-ab.analytics.driver', 'pivotal');
    config()->set('wonder-ab.analytics.pivotal.api_key', '');

    $this->artisan('ab:validate')
        ->expectsOutputToContain('Pivotal driver selected but API key not configured')
        ->assertSuccessful();
});

test('ab validate warns about missing auth config', function () {
    config()->set('wonder-ab.report_auth', 'basic');
    config()->set('wonder-ab.report_username', '');

    $this->artisan('ab:validate')
        ->expectsOutputToContain('Basic auth selected but credentials not configured')
        ->assertSuccessful();
});
