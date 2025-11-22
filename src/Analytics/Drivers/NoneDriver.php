<?php

namespace Wonderfulso\WonderAb\Analytics\Drivers;

use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class NoneDriver implements AnalyticsDriver
{
    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        // Do nothing - local tracking only
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        // Do nothing - local tracking only
    }

    public function sendBatch(array $events): void
    {
        // Do nothing - local tracking only
    }
}
