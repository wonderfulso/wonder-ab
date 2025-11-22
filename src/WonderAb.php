<?php

namespace Wonderfulso\WonderAb;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Wonderfulso\WonderAb\Analytics\AnalyticsManager;
use Wonderfulso\WonderAb\Models\Events;
use Wonderfulso\WonderAb\Models\Experiments;
use Wonderfulso\WonderAb\Models\Goal;
use Wonderfulso\WonderAb\Models\Instance;
use Wonderfulso\WonderAb\Support\CacheManager;

class WonderAb
{
    /**
     * Instance Object to identify user's session
     */
    protected static ?Instance $session = null;

    /**
     * Tracks every experiment->fired condition the view is initiating
     */
    protected static array $instance = [];

    /**
     * Cached events for the current instance
     */
    protected static $events = [];

    /*
     * Individual Test Parameters
     */
    protected ?string $name = null;

    protected array $conditions = [];

    protected ?string $fired = null;

    protected ?string $goal = null;

    /**
     * Initialize user session for A/B testing
     */
    public static function initUser(?Request $request = null): void
    {
        $key = config('wonder-ab.cache_key');
        $param_key = config('wonder-ab.request_param');

        if (! empty(self::$session)) {
            return;
        }

        $client = Str::random(12);
        if (! empty($request)) {
            $client = $request->getClientIp() ?? $client;
        }

        $uid = null;

        // Check if param override is allowed and present
        if (config('wonder-ab.allow_param') && ! empty($request) && $request->has($param_key)) {
            // Apply rate limiting to prevent abuse
            $rateLimiter = app(RateLimiter::class);
            $rateKey = 'ab_param_override:'.($request->ip() ?? 'cli');
            $maxAttempts = config('wonder-ab.param_rate_limit', 10);

            if ($rateLimiter->tooManyAttempts($rateKey, $maxAttempts)) {
                throw new ThrottleRequestsException(
                    'Too many instance ID overrides. Please try again later.'
                );
            }

            $rateLimiter->hit($rateKey, 60); // 1 minute window
            $uid = $request->input($param_key);
        }

        // Fallback to session, then cookie, then generate new
        if (empty($uid)) {
            $uid = session()->get($key);
        }
        if (empty($uid)) {
            $uid = Cookie::get($key);
        }
        if (empty($uid)) {
            $uid = md5(uniqid().$client);
        }

        session()->put($key, $uid);

        // Gather metadata (check for custom function)
        /** @phpstan-ignore-next-line */
        $metadata = function_exists('laravel_ab_meta') ? call_user_func('laravel_ab_meta') : [];
        $captured_data = [];

        if (! empty($request)) {
            $captured_data = [
                'user_agent' => $request->header('User-Agent'),
                'ip' => $client,
                'referrer' => $request->header('referer'),
                'url' => $request->fullUrl(),
            ];
        }

        // Create or retrieve instance
        self::$session = Instance::firstOrCreate(
            ['instance' => $uid],
            [
                'instance' => $uid,
                'identifier' => $client,
                'metadata' => array_merge($metadata, $captured_data),
            ]
        );

        self::$events = self::$session->events;
    }

    /**
     * Save all experiment events to database and send to analytics
     */
    public static function saveSession(): string
    {
        if (! empty(self::$instance)) {
            $cacheManager = app(CacheManager::class);

            DB::transaction(function () use ($cacheManager) {
                foreach (self::$instance as $event) {
                    // Use cached experiment lookup
                    $experiment = $cacheManager->remember(
                        "experiment:{$event->name}:{$event->goal}",
                        fn () => Experiments::firstOrCreate([
                            'experiment' => $event->name,
                            'goal' => $event->goal,
                        ])
                    );

                    $eventModel = Events::firstOrCreate([
                        'instance_id' => self::$session->id,
                        'experiments_id' => $experiment->id,
                        'name' => $event->name,
                        'value' => $event->fired,
                    ]);

                    self::$session->events()->save($eventModel);

                    // Send to analytics
                    try {
                        if (! empty($event->fired)) {
                            $analytics = app(AnalyticsManager::class);
                            $analytics->trackExperiment(
                                $event->name,
                                $event->fired,
                                self::$session->instance
                            );
                        }
                    } catch (\Exception $e) {
                        // Log but don't fail
                        \Log::warning('Failed to send experiment to analytics', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        }

        return session()->get(config('wonder-ab.cache_key')) ?? '';
    }

    /**
     * Select a specific option for this experiment (for testing/forcing)
     */
    public function selectOption(string $option): self
    {
        $this->fired = $option;

        return $this;
    }

    /**
     * Set the experiment name
     */
    public function experiment(string $experiment): self
    {
        $this->name = $experiment;
        self::$instance[$experiment] = $this;

        return $this;
    }

    /**
     * Track this experiment with a goal and return the selected variant
     */
    public function track(string $goal): string
    {
        $this->goal = $goal;

        if (ob_get_level()) {
            ob_end_clean();
        }

        $conditions = [];

        // Handle weighted conditions like "variant [5]"
        foreach ($this->conditions as $key => $condition) {
            if (preg_match('/\[(\d+)\]/', $key, $matches)) {
                $weight = (int) $matches[1];
                for ($i = 0; $i < $weight; $i++) {
                    $conditions[] = $key;
                }
            }
        }

        if (empty($conditions)) {
            $conditions = array_keys($this->conditions);
        }

        // Check if user already saw this experiment (sticky sessions)
        if (empty($this->fired) || empty($this->conditions[$this->fired])) {
            $fired = $this->hasExperiment($this->name);
            if (! empty($fired) && ! empty($this->conditions[$fired])) {
                $this->fired = $fired;
            } else {
                shuffle($conditions);
                $this->fired = current($conditions);
            }
        }

        return $this->conditions[$this->fired] ?? '';
    }

    /**
     * Record a goal conversion
     */
    public static function goal(string $goal, mixed $value = null): ?Goal
    {
        if (empty(self::$session)) {
            return null;
        }

        $goalModel = Goal::create([
            'instance_id' => self::$session->id,
            'goal' => $goal,
            'value' => $value,
        ]);

        self::$session->goals()->save($goalModel);

        // Send to analytics
        try {
            $analytics = app(AnalyticsManager::class);
            $analytics->trackGoal($goal, self::$session->instance, $value);
        } catch (\Exception $e) {
            \Log::warning('Failed to send goal to analytics', [
                'error' => $e->getMessage(),
            ]);
        }

        return $goalModel;
    }

    /**
     * Save a condition and its HTML content
     */
    public function condition(string $condition): void
    {
        if (count($this->conditions) !== 0) {
            ob_end_clean();
        }

        $this->saveCondition($condition, '');

        ob_start(function ($data) use ($condition) {
            $this->saveCondition($condition, $data);

            return '';
        });
    }

    /**
     * Get the current session instance
     */
    public static function getSession(): ?Instance
    {
        return self::$session;
    }

    /**
     * Get the current instance ID (for webhooks and external integrations)
     */
    public static function getInstanceId(): ?string
    {
        return self::$session?->instance;
    }

    /**
     * Save condition key-value pair
     */
    public function saveCondition(string $condition, string $data): void
    {
        $this->conditions[$condition] = $data;
    }

    /**
     * Track this experiment instance
     */
    public function instanceEvent(): void
    {
        self::$instance[$this->name ?? ''] = $this;
    }

    /**
     * Check if user has seen this experiment before
     */
    public function hasExperiment(string $experiment): string|false
    {
        foreach (self::$events as $session_event) {
            if ($session_event->name == $experiment) {
                return $session_event->value;
            }
        }

        return false;
    }

    /**
     * Create a simple experiment with conditions (for use in controllers)
     */
    public static function choice(string $experiment, array $conditions): self
    {
        $ab = new self;
        $ab->experiment($experiment);

        foreach ($conditions as $condition) {
            $ab->conditions[$condition] = $condition;
        }

        return $ab;
    }

    /**
     * Reset session for testing
     */
    public function forceReset(): void
    {
        self::resetSession();
    }

    /**
     * Convert experiment to array
     */
    public function toArray(): array
    {
        return [$this->name => $this->fired];
    }

    /**
     * Get all active experiments in this request
     */
    public function getEvents(): array
    {
        return self::$instance;
    }

    /**
     * Get all active experiments (public accessor)
     */
    public static function getActiveTests(): array
    {
        return collect(self::$instance)->map(function ($test) {
            return [
                'experiment' => $test->name,
                'variant' => $test->fired,
                'goal' => $test->goal,
            ];
        })->all();
    }

    /**
     * Reset static session state
     */
    public static function resetSession(): void
    {
        self::$session = null;
        self::$instance = [];
        self::$events = [];
    }

    /**
     * Send a custom event (for advanced usage)
     */
    public static function sendEvent(string $event, mixed $payload): void
    {
        if (empty(self::$session)) {
            return;
        }

        $model = new Goal;
        $model->goal = $event;
        $model->value = $payload;
        $model->instance_id = self::$session->id;
        $model->save();

        try {
            $analytics = app(AnalyticsManager::class);
            $analytics->trackGoal($event, self::$session->instance, $payload);
        } catch (\Exception $e) {
            \Log::warning('Failed to send custom event to analytics', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
