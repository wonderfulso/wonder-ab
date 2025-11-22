<?php

namespace Wonderfulso\WonderAb\Analytics;

use Illuminate\Support\Facades\Log;
use Wonderfulso\WonderAb\Analytics\Drivers\GoogleAnalytics4Driver;
use Wonderfulso\WonderAb\Analytics\Drivers\LogDriver;
use Wonderfulso\WonderAb\Analytics\Drivers\NoneDriver;
use Wonderfulso\WonderAb\Analytics\Drivers\PivotalDriver;
use Wonderfulso\WonderAb\Analytics\Drivers\PlausibleDriver;
use Wonderfulso\WonderAb\Analytics\Drivers\WebhookDriver;
use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class AnalyticsManager
{
    protected AnalyticsDriver $driver;

    public function __construct()
    {
        $this->driver = $this->createDriver();
    }

    protected function createDriver(): AnalyticsDriver
    {
        $driverName = config('wonder-ab.analytics.driver', 'none');

        // Check for custom driver first
        $customDriver = config('wonder-ab.analytics.custom_driver');
        if ($customDriver && class_exists($customDriver)) {
            return app($customDriver);
        }

        return match ($driverName) {
            'none' => new NoneDriver,
            'log' => new LogDriver,
            'google' => new GoogleAnalytics4Driver,
            'plausible' => new PlausibleDriver,
            'webhook' => new WebhookDriver,
            'pivotal' => new PivotalDriver,
            default => throw new \InvalidArgumentException("Unknown analytics driver: {$driverName}")
        };
    }

    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        try {
            $this->driver->trackExperiment($experiment, $variant, $instance);
        } catch (\Exception $e) {
            // Don't let analytics failures break the app
            Log::warning('Failed to track AB experiment', [
                'error' => $e->getMessage(),
                'experiment' => $experiment,
            ]);
        }
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        try {
            $this->driver->trackGoal($goal, $instance, $value);
        } catch (\Exception $e) {
            Log::warning('Failed to track AB goal', [
                'error' => $e->getMessage(),
                'goal' => $goal,
            ]);
        }
    }

    public function sendBatch(array $events): void
    {
        try {
            $this->driver->sendBatch($events);
        } catch (\Exception $e) {
            Log::warning('Failed to send AB events batch', [
                'error' => $e->getMessage(),
                'count' => count($events),
            ]);
        }
    }
}
