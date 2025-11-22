<?php

namespace Wonderfulso\WonderAb\Analytics\Drivers;

use Illuminate\Support\Facades\Log;
use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class LogDriver implements AnalyticsDriver
{
    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        Log::info('AB Experiment', [
            'experiment' => $experiment,
            'variant' => $variant,
            'instance' => $instance,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        Log::info('AB Goal', [
            'goal' => $goal,
            'instance' => $instance,
            'value' => $value,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function sendBatch(array $events): void
    {
        Log::info('AB Events Batch', [
            'events' => $events,
            'count' => count($events),
        ]);
    }
}
