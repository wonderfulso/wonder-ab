<?php

namespace Wonderfulso\WonderAb\Analytics\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class GoogleAnalytics4Driver implements AnalyticsDriver
{
    protected string $measurementId;

    protected string $apiSecret;

    protected Client $client;

    public function __construct()
    {
        $this->measurementId = config('wonder-ab.analytics.google.measurement_id', '');
        $this->apiSecret = config('wonder-ab.analytics.google.api_secret', '');
        $this->client = new Client(['timeout' => 5.0]);
    }

    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        $this->sendEvent($instance, 'ab_experiment', [
            'experiment_name' => $experiment,
            'variant_name' => $variant,
        ]);
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        $params = ['goal_name' => $goal];
        if ($value !== null) {
            $params['goal_value'] = is_numeric($value) ? (float) $value : $value;
        }

        $this->sendEvent($instance, 'ab_goal', $params);
    }

    protected function sendEvent(string $userId, string $eventName, array $params): void
    {
        if (empty($this->measurementId) || empty($this->apiSecret)) {
            return;
        }

        try {
            $this->client->post('https://www.google-analytics.com/mp/collect', [
                'query' => [
                    'measurement_id' => $this->measurementId,
                    'api_secret' => $this->apiSecret,
                ],
                'json' => [
                    'client_id' => $userId,
                    'events' => [
                        [
                            'name' => $eventName,
                            'params' => $params,
                        ],
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            Log::warning('Failed to send event to Google Analytics 4', [
                'error' => $e->getMessage(),
                'event' => $eventName,
            ]);
        }
    }

    public function sendBatch(array $events): void
    {
        if (empty($this->measurementId) || empty($this->apiSecret)) {
            return;
        }

        // GA4 supports up to 25 events per request
        $chunks = array_chunk($events, 25);

        foreach ($chunks as $chunk) {
            $clientId = $chunk[0]['instance'] ?? 'batch';

            $formattedEvents = collect($chunk)->map(function ($event) {
                $eventType = $event['type'] ?? 'experiment';
                $payload = $event['payload'] ?? [];

                if ($eventType === 'goal') {
                    return [
                        'name' => 'ab_goal',
                        'params' => [
                            'goal_name' => $payload['goal'] ?? '',
                            'goal_value' => $payload['value'] ?? null,
                        ],
                    ];
                }

                return [
                    'name' => 'ab_experiment',
                    'params' => [
                        'experiment_name' => $payload['experiment'] ?? '',
                        'variant_name' => $payload['variant'] ?? '',
                    ],
                ];
            })->all();

            try {
                $this->client->post('https://www.google-analytics.com/mp/collect', [
                    'query' => [
                        'measurement_id' => $this->measurementId,
                        'api_secret' => $this->apiSecret,
                    ],
                    'json' => [
                        'client_id' => $clientId,
                        'events' => $formattedEvents,
                    ],
                ]);
            } catch (GuzzleException $e) {
                Log::warning('Failed to send batch to Google Analytics 4', [
                    'error' => $e->getMessage(),
                    'count' => count($chunk),
                ]);
            }
        }
    }
}
