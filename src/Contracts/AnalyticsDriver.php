<?php

namespace Wonderfulso\WonderAb\Contracts;

interface AnalyticsDriver
{
    /**
     * Send experiment event to analytics platform
     */
    public function trackExperiment(string $experiment, string $variant, string $instance): void;

    /**
     * Send goal conversion to analytics platform
     */
    public function trackGoal(string $goal, string $instance, mixed $value = null): void;

    /**
     * Send batch of events (for performance)
     */
    public function sendBatch(array $events): void;
}
