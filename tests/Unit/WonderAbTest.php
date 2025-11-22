<?php

use Illuminate\Support\Facades\Request;
use Wonderfulso\WonderAb\Models\Instance;

beforeEach(function () {
    $ab = app()->make('Ab');
    $ab::resetSession();
});

test('it can create instance', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $instance = $ab::getSession();

    expect($instance)->toBeInstanceOf(Instance::class);
    expect($instance->instance)->not->toBeNull();
});

test('it can get instance id', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $instanceId = $ab::getInstanceId();

    expect($instanceId)->toBeString();
    expect($instanceId)->not->toBeEmpty();
    expect($instanceId)->toBe($ab::getSession()->instance);
});

test('it preserves instance across calls', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $firstInstance = $ab::getSession();
    $secondInstance = $ab::getSession();

    expect($firstInstance->instance)->toBe($secondInstance->instance);
});

test('it can reset session', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $firstInstance = $ab::getSession();
    $firstId = $firstInstance->instance;

    // Clear session completely
    session()->flush();

    $ab::resetSession();
    $ab::initUser();

    $secondInstance = $ab::getSession();
    $secondId = $secondInstance->instance;

    expect($firstId)->not->toBe($secondId);
});

test('it returns active tests', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $ab::choice('test-active', [
        'a' => 'Variant A',
        'b' => 'Variant B',
    ]);

    $activeTests = $ab::getActiveTests();

    expect($activeTests)->toBeArray();
});

test('choice method creates experiment', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $experiment = $ab::choice('color-test', [
        'red' => 'Red color',
        'blue' => 'Blue color',
        'green' => 'Green color',
    ]);

    expect($experiment)->toBeInstanceOf(Wonderfulso\WonderAb\WonderAb::class);
});

test('choice method is consistent for same session', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $firstChoice = $ab::choice('consistency-test', [
        'a' => 'Option A',
        'b' => 'Option B',
    ]);

    $secondChoice = $ab::choice('consistency-test', [
        'a' => 'Option A',
        'b' => 'Option B',
    ]);

    expect($firstChoice)->toEqual($secondChoice);
});

test('weighted conditions work with choice method', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $experiment = $ab::choice('weighted-test', [
        'always[100]' => 'This should show',
        'never[0]' => 'This should not show',
    ]);

    expect($experiment)->toBeInstanceOf(Wonderfulso\WonderAb\WonderAb::class);
});

test('weighted conditions distribute traffic correctly', function () {
    $results = ['heavy' => 0, 'light' => 0];

    // Run 500 experiments to test distribution
    for ($i = 0; $i < 500; $i++) {
        $ab = app()->make('Ab');
        $ab::resetSession();
        session()->flush(); // Force new instance each time

        $ab::initUser();

        $experiment = $ab::choice('weight-dist-test', [
            'heavy[80]',
            'light[20]',
        ]);

        // Call track to trigger variant selection
        $selected = $experiment->track('test-goal');

        if (strpos($selected, 'heavy') !== false) {
            $results['heavy']++;
        } elseif (strpos($selected, 'light') !== false) {
            $results['light']++;
        }
    }

    // With 80:20 ratio, we expect roughly 400:100
    // Allow some variance (65-95% for heavy, 5-35% for light)
    $heavyPercent = ($results['heavy'] / 500) * 100;
    $lightPercent = ($results['light'] / 500) * 100;

    expect($heavyPercent)->toBeGreaterThan(65);
    expect($heavyPercent)->toBeLessThan(95);
    expect($lightPercent)->toBeGreaterThan(5);
    expect($lightPercent)->toBeLessThan(35);
});

test('goal tracking creates goal record', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $experiment = $ab->experiment('goal-test');
    $experiment->condition('a');
    $experiment->condition('b');
    $experiment->track('purchase');

    $goal = $ab::goal('purchase', 99.99);
    $ab::saveSession();

    expect($goal)->not->toBeNull();
    expect($goal->goal)->toBe('purchase');
    expect($goal->value)->toBe(99.99);
});

test('goal can be tracked without value', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $experiment = $ab->experiment('goal-no-value');
    $experiment->condition('a');
    $experiment->track('signup');

    $goal = $ab::goal('signup');
    $ab::saveSession();

    expect($goal)->not->toBeNull();
    expect($goal->goal)->toBe('signup');
    expect($goal->value)->toBeNull();
});

test('multiple experiments can be created in same session', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $exp1 = $ab::choice('test-1', [
        'a' => 'Option A',
        'b' => 'Option B',
    ]);

    $exp2 = $ab::choice('test-2', [
        'x' => 'Option X',
        'y' => 'Option Y',
    ]);

    expect($exp1)->toBeInstanceOf(Wonderfulso\WonderAb\WonderAb::class);
    expect($exp2)->toBeInstanceOf(Wonderfulso\WonderAb\WonderAb::class);
});

test('custom instance id can be set via request parameter', function () {
    putenv('LARAVEL_AB_ALLOW_PARAM=true');
    config()->set('wonder-ab.allow_param', true);

    $ab = app()->make('Ab');
    $request = Request::create('http://localhost', 'GET', ['abid' => 'custom-instance-123']);

    $ab::initUser($request);

    expect(session()->get('wonder_ab_user'))->toBe('custom-instance-123');
});

test('instance identifier can be set', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $instance = $ab::getSession();
    $instance->identifier = 'user@example.com';
    $instance->save();

    expect($instance->identifier)->toBe('user@example.com');
});

test('metadata can be stored on instance', function () {
    $ab = app()->make('Ab');
    $ab::initUser();

    $instance = $ab::getSession();
    $instance->metadata = ['browser' => 'Chrome', 'platform' => 'macOS'];
    $instance->save();

    // Reload from database
    $reloaded = Instance::find($instance->id);

    expect($reloaded->metadata)->toBeArray();
    expect($reloaded->metadata['browser'])->toBe('Chrome');
    expect($reloaded->metadata['platform'])->toBe('macOS');
});
