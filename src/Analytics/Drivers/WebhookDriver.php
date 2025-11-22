<?php

namespace Wonderfulso\WonderAb\Analytics\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class WebhookDriver implements AnalyticsDriver
{
    protected string $url;

    protected string $secret;

    protected Client $client;

    public function __construct()
    {
        $this->url = config('wonder-ab.analytics.webhook_url', '');
        $this->secret = config('wonder-ab.analytics.webhook_secret', '');
        $this->client = new Client(['timeout' => 5.0]);
    }

    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        $this->send([
            'type' => 'experiment',
            'experiment' => $experiment,
            'variant' => $variant,
            'instance' => $instance,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        $this->send([
            'type' => 'goal',
            'goal' => $goal,
            'instance' => $instance,
            'value' => $value,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function sendBatch(array $events): void
    {
        $this->send(['events' => $events]);
    }

    protected function send(array $data): void
    {
        if (empty($this->url)) {
            return;
        }

        $signature = hash_hmac('sha256', json_encode($data), $this->secret);

        try {
            $this->client->post($this->url, [
                'json' => $data,
                'headers' => [
                    'X-AB-Signature' => $signature,
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::warning('Failed to send event to webhook', [
                'error' => $e->getMessage(),
                'url' => $this->url,
            ]);
        }
    }
}
