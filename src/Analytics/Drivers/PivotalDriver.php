<?php

namespace Wonderfulso\WonderAb\Analytics\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class PivotalDriver implements AnalyticsDriver
{
    protected string $apiKey;

    protected string $apiUrl;

    protected Client $client;

    public function __construct()
    {
        $this->apiKey = config('wonder-ab.analytics.pivotal.api_key', '');
        $this->apiUrl = config('wonder-ab.analytics.pivotal.api_url', 'https://ab.pivotal.so');

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 5.0,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->apiKey}",
            ],
        ]);
    }

    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        $this->send([
            'type' => 'Event',
            'payload' => [
                'experiment' => $experiment,
                'variant' => $variant,
                'instance' => $instance,
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        $this->send([
            'type' => 'Goal',
            'payload' => [
                'goal' => $goal,
                'instance' => $instance,
                'value' => $value,
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function sendBatch(array $events): void
    {
        if (empty($this->apiKey) || empty($events)) {
            return;
        }

        try {
            $this->client->post('api/events/track', [
                'json' => $events,
            ]);

            Log::info('AB events sent to Pivotal successfully', [
                'count' => count($events),
            ]);
        } catch (GuzzleException $e) {
            Log::error('Failed to send AB events to Pivotal', [
                'error' => $e->getMessage(),
                'events_count' => count($events),
            ]);
            throw $e;
        }
    }

    protected function send(array $data): void
    {
        if (empty($this->apiKey)) {
            return;
        }

        try {
            $this->client->post('api/events/track', [
                'json' => [$data],
            ]);
        } catch (GuzzleException $e) {
            Log::warning('Failed to send event to Pivotal AB', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
