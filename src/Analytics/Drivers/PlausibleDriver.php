<?php

namespace Wonderfulso\WonderAb\Analytics\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class PlausibleDriver implements AnalyticsDriver
{
    protected string $domain;

    protected ?string $apiKey;

    protected Client $client;

    public function __construct()
    {
        $this->domain = config('wonder-ab.analytics.plausible.domain', '');
        $this->apiKey = config('wonder-ab.analytics.plausible.api_key');

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        $this->client = new Client([
            'base_uri' => 'https://plausible.io',
            'timeout' => 5.0,
            'headers' => $headers,
        ]);
    }

    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        $this->sendEvent($instance, "AB Test: {$experiment}", [
            'variant' => $variant,
        ]);
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        $props = [];
        if ($value !== null) {
            $props['value'] = $value;
        }

        $this->sendEvent($instance, "Goal: {$goal}", $props);
    }

    protected function sendEvent(string $userId, string $name, array $props): void
    {
        if (empty($this->domain)) {
            return;
        }

        try {
            $this->client->post('/api/event', [
                'json' => [
                    'domain' => $this->domain,
                    'name' => $name,
                    'url' => request()->fullUrl() ?? 'https://'.$this->domain,
                    'props' => $props,
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::warning('Failed to send event to Plausible', [
                'error' => $e->getMessage(),
                'event' => $name,
            ]);
        }
    }

    public function sendBatch(array $events): void
    {
        // Plausible doesn't have native batch API, send individually
        foreach ($events as $event) {
            $eventType = $event['type'] ?? 'experiment';
            $payload = $event['payload'] ?? [];
            $instance = $event['instance'] ?? $payload['instance'] ?? 'unknown';

            if ($eventType === 'goal') {
                $this->trackGoal(
                    $payload['goal'] ?? '',
                    $instance,
                    $payload['value'] ?? null
                );
            } else {
                $this->trackExperiment(
                    $payload['experiment'] ?? '',
                    $payload['variant'] ?? '',
                    $instance
                );
            }
        }
    }
}
