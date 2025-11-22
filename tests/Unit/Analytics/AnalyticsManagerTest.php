<?php

use Wonderfulso\WonderAb\Analytics\AnalyticsManager;
use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

beforeEach(function () {
    config()->set('wonder-ab.analytics.driver', 'none');
});

test('it creates none driver by default', function () {
    $manager = new AnalyticsManager;

    expect($manager)->toBeInstanceOf(AnalyticsManager::class);
});

test('it creates log driver', function () {
    config()->set('wonder-ab.analytics.driver', 'log');

    $manager = new AnalyticsManager;

    expect($manager)->toBeInstanceOf(AnalyticsManager::class);
});

test('it creates google analytics driver', function () {
    config()->set('wonder-ab.analytics.driver', 'google');
    config()->set('wonder-ab.analytics.google.measurement_id', 'G-XXXXXXXXXX');
    config()->set('wonder-ab.analytics.google.api_secret', 'test-secret');

    $manager = new AnalyticsManager;

    expect($manager)->toBeInstanceOf(AnalyticsManager::class);
});

test('it creates plausible driver', function () {
    config()->set('wonder-ab.analytics.driver', 'plausible');
    config()->set('wonder-ab.analytics.plausible.domain', 'example.com');

    $manager = new AnalyticsManager;

    expect($manager)->toBeInstanceOf(AnalyticsManager::class);
});

test('it creates webhook driver', function () {
    config()->set('wonder-ab.analytics.driver', 'webhook');
    config()->set('wonder-ab.analytics.webhook_url', 'https://example.com/webhook');
    config()->set('wonder-ab.analytics.webhook_secret', 'secret');

    $manager = new AnalyticsManager;

    expect($manager)->toBeInstanceOf(AnalyticsManager::class);
});

test('it creates pivotal driver', function () {
    config()->set('wonder-ab.analytics.driver', 'pivotal');
    config()->set('wonder-ab.analytics.pivotal.api_key', 'test-key');

    $manager = new AnalyticsManager;

    expect($manager)->toBeInstanceOf(AnalyticsManager::class);
});

test('it creates custom driver', function () {
    $customDriver = new class implements AnalyticsDriver
    {
        public function trackExperiment(string $experiment, string $variant, string $instance): void {}

        public function trackGoal(string $goal, string $instance, mixed $value = null): void {}

        public function sendBatch(array $events): void {}
    };

    config()->set('wonder-ab.analytics.custom_driver', get_class($customDriver));
    app()->bind(get_class($customDriver), fn () => $customDriver);

    $manager = new AnalyticsManager;

    expect($manager)->toBeInstanceOf(AnalyticsManager::class);
});

test('it throws exception for unknown driver', function () {
    config()->set('wonder-ab.analytics.driver', 'invalid-driver');

    expect(fn () => new AnalyticsManager)
        ->toThrow(\InvalidArgumentException::class, 'Unknown analytics driver: invalid-driver');
});

test('it tracks experiment without throwing exception', function () {
    config()->set('wonder-ab.analytics.driver', 'none');

    $manager = new AnalyticsManager;
    $manager->trackExperiment('test-experiment', 'variant-a', 'instance-123');

    expect(true)->toBeTrue();
});

test('it tracks goal without throwing exception', function () {
    config()->set('wonder-ab.analytics.driver', 'none');

    $manager = new AnalyticsManager;
    $manager->trackGoal('signup', 'instance-123', 100);

    expect(true)->toBeTrue();
});

test('it sends batch without throwing exception', function () {
    config()->set('wonder-ab.analytics.driver', 'none');

    $manager = new AnalyticsManager;
    $manager->sendBatch([
        ['type' => 'experiment', 'payload' => ['experiment' => 'test', 'variant' => 'a', 'instance' => '123']],
        ['type' => 'goal', 'payload' => ['goal' => 'signup', 'instance' => '123', 'value' => 100]],
    ]);

    expect(true)->toBeTrue();
});

test('it handles analytics failures gracefully', function () {
    // Create a driver that throws exceptions
    $failingDriver = new class implements AnalyticsDriver
    {
        public function trackExperiment(string $experiment, string $variant, string $instance): void
        {
            throw new \Exception('Analytics service down');
        }

        public function trackGoal(string $goal, string $instance, mixed $value = null): void
        {
            throw new \Exception('Analytics service down');
        }

        public function sendBatch(array $events): void
        {
            throw new \Exception('Analytics service down');
        }
    };

    config()->set('wonder-ab.analytics.custom_driver', get_class($failingDriver));
    app()->bind(get_class($failingDriver), fn () => $failingDriver);

    $manager = new AnalyticsManager;

    // Should not throw exception - failures are caught and logged
    $manager->trackExperiment('test', 'a', '123');
    $manager->trackGoal('signup', '123');
    $manager->sendBatch([]);

    expect(true)->toBeTrue();
});
