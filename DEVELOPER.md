# Developer Documentation

This document provides technical details for developers who want to contribute to Wonder AB or build custom integrations.

## Table of Contents

- [Architecture](#architecture)
- [Database Schema](#database-schema)
- [Custom Analytics Drivers](#custom-analytics-drivers)
- [Webhook Goal Registration API](#webhook-goal-registration-api)
- [Testing](#testing)
- [Code Quality](#code-quality)
- [Authentication Strategies](#authentication-strategies)
- [Cache Strategy](#cache-strategy)
- [Rate Limiting](#rate-limiting)
- [Contributing](#contributing)
- [Debugging](#debugging)

## Architecture

### Core Components

```
src/
├── Analytics/              # Analytics system
│   ├── AnalyticsManager.php
│   └── Drivers/           # Built-in analytics drivers
├── Commands/              # Artisan commands
├── Contracts/             # Interfaces
├── Events/                # Laravel events
├── Facades/               # Laravel facades
├── Http/
│   ├── Controllers/       # Web report controller
│   └── Middleware/        # Session & auth middleware
├── Jobs/                  # Report generation jobs
├── Listeners/             # Event listeners
├── Models/                # Eloquent models
├── Support/               # Helper classes
│   └── CacheManager.php   # Optional caching layer
├── WonderAb.php          # Main experiment class
└── WonderAbServiceProvider.php
```

### Request Flow

1. **Middleware**: `WonderAbMiddleware` initializes user session
2. **Blade Parsing**: Blade compiler encounters `@ab` directive
3. **Experiment Creation**: `WonderAb::experiment()` creates experiment instance
4. **Variant Selection**: Weighted random selection or sticky session retrieval
5. **Rendering**: Selected variant content is output
6. **Tracking**: Goal tracking via `@track` or `Ab::goal()`
7. **Persistence**: `WonderAb::saveSession()` stores to database
8. **Analytics**: `AnalyticsManager` sends events to configured driver

### Session Management

Users are identified by:
1. **Session Cookie** - Primary identifier (sticky sessions)
2. **Custom Parameter** - Optional `?abid=xxx` for testing (rate-limited)
3. **Identifier** - Optional user identifier (email, user ID, etc.)

Sessions are persisted in the `ab_instance` table with:
- Unique instance ID (UUID-like)
- Optional user identifier
- JSON metadata

## Database Schema

### Tables

#### `ab_instance`
Stores unique user sessions.

```sql
CREATE TABLE ab_instance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instance VARCHAR(255) UNIQUE NOT NULL,  -- Session ID
    identifier VARCHAR(255) NULL,           -- Optional user ID/email
    metadata TEXT NULL,                     -- JSON metadata
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_identifier (identifier)
);
```

#### `ab_experiments`
Stores experiment definitions.

```sql
CREATE TABLE ab_experiments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    experiment VARCHAR(255) NOT NULL,       -- Experiment name
    goal VARCHAR(255) NULL,                 -- Associated goal name
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_experiment (experiment),
    INDEX idx_goal (goal)
);
```

#### `ab_events`
Stores variant assignments.

```sql
CREATE TABLE ab_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instance_id INT NOT NULL,               -- FK to ab_instance
    experiments_id INT NULL,                -- FK to ab_experiments
    name VARCHAR(255) NOT NULL,             -- Experiment name
    value VARCHAR(255) NOT NULL,            -- Selected variant
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_instance (instance_id),
    INDEX idx_experiment (experiments_id),
    INDEX idx_name_value (name, value),
    INDEX idx_created (created_at)
);
```

#### `ab_goal`
Stores goal conversions.

```sql
CREATE TABLE ab_goal (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instance_id INT NOT NULL,               -- FK to ab_instance
    goal VARCHAR(255) NOT NULL,             -- Goal name
    value VARCHAR(255) NULL,                -- Optional value (revenue, etc)
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_instance (instance_id),
    INDEX idx_goal (goal),
    INDEX idx_created (created_at)
);
```

### Indexes

Performance indexes are automatically created via the `migrate_metadata_to_json_and_add_indexes` migration.

## Custom Analytics Drivers

### Creating a Driver

1. **Implement the Contract**

```php
<?php

namespace App\Analytics;

use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class MyCustomDriver implements AnalyticsDriver
{
    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        // Send experiment assignment to your analytics platform
        // Example: POST to external API
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        // Send goal conversion to your analytics platform
    }

    public function sendBatch(array $events): void
    {
        // Optional: Send multiple events in a single request
        // Format: [
        //     ['type' => 'experiment', 'payload' => [...]],
        //     ['type' => 'goal', 'payload' => [...]]
        // ]
    }
}
```

2. **Configure in config/wonder-ab.php**

```php
'analytics' => [
    'driver' => 'custom',
    'custom_driver' => \App\Analytics\MyCustomDriver::class,
],
```

### Example: Segment Driver

```php
<?php

namespace App\Analytics;

use Segment\Segment;
use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

class SegmentDriver implements AnalyticsDriver
{
    protected string $writeKey;

    public function __construct()
    {
        $this->writeKey = config('services.segment.write_key');
        Segment::init($this->writeKey);
    }

    public function trackExperiment(string $experiment, string $variant, string $instance): void
    {
        Segment::track([
            'userId' => $instance,
            'event' => 'Experiment Viewed',
            'properties' => [
                'experiment_name' => $experiment,
                'variant_name' => $variant,
            ],
        ]);
    }

    public function trackGoal(string $goal, string $instance, mixed $value = null): void
    {
        Segment::track([
            'userId' => $instance,
            'event' => 'Goal Completed',
            'properties' => [
                'goal_name' => $goal,
                'value' => $value,
            ],
        ]);
    }

    public function sendBatch(array $events): void
    {
        // Segment has its own batching
        foreach ($events as $event) {
            $type = $event['type'] ?? 'experiment';
            $payload = $event['payload'] ?? [];

            if ($type === 'goal') {
                $this->trackGoal(
                    $payload['goal'] ?? '',
                    $payload['instance'] ?? '',
                    $payload['value'] ?? null
                );
            } else {
                $this->trackExperiment(
                    $payload['experiment'] ?? '',
                    $payload['variant'] ?? '',
                    $payload['instance'] ?? ''
                );
            }
        }
    }
}
```

### Built-in Drivers

#### NoneDriver
Stores events in database only, no external tracking.

#### LogDriver
Writes events to Laravel logs (`storage/logs/laravel.log`).

#### GoogleAnalytics4Driver
Sends events to Google Analytics 4 Measurement Protocol API.
- Supports batching (up to 25 events per request)
- Requires `measurement_id` and `api_secret`

#### PlausibleDriver
Sends events to Plausible Analytics Events API.
- Optional API key for server-side tracking
- Requires `domain`

#### WebhookDriver
POST events to a custom webhook endpoint.
- HMAC-SHA256 signature in `X-AB-Signature` header
- Configurable URL and secret

#### PivotalDriver
Sends events to Pivotal AB SaaS platform (optional paid service).

## Testing

### Running Tests

```bash
# All tests
composer test

# With coverage (requires xdebug or pcov)
composer test-coverage

# Specific test file
vendor/bin/pest tests/Unit/Analytics/AnalyticsManagerTest.php

# Specific test
vendor/bin/pest --filter="it creates none driver by default"
```

### Test Structure

```
tests/
├── Unit/
│   ├── Analytics/          # Analytics driver tests
│   ├── Commands/           # Artisan command tests
│   ├── Middleware/         # Middleware tests
│   ├── Models/             # Model tests
│   ├── Support/            # Helper class tests
│   ├── PivotalAbTest.php   # Core functionality
│   └── RateLimitingTest.php
└── Feature/
    └── RenderTest.php      # Blade rendering tests
```

### Writing Tests

Tests use **Pest PHP** with **Orchestra Testbench**.

```php
<?php

use Wonderfulso\WonderAb\Analytics\AnalyticsManager;

test('it creates custom driver', function () {
    $customDriver = new class implements AnalyticsDriver {
        public function trackExperiment(string $experiment, string $variant, string $instance): void {}
        public function trackGoal(string $goal, string $instance, mixed $value = null): void {}
        public function sendBatch(array $events): void {}
    };

    config()->set('wonder-ab.analytics.custom_driver', get_class($customDriver));
    app()->bind(get_class($customDriver), fn () => $customDriver);

    $manager = new AnalyticsManager;

    expect($manager)->toBeInstanceOf(AnalyticsManager::class);
});
```

## Code Quality

### PHPStan

Static analysis at level 5:

```bash
composer analyse
```

Configuration: `phpstan.neon.dist`

### Laravel Pint

Code formatting following Laravel conventions:

```bash
composer format
```

Configuration: `pint.json`

### Pre-commit Checks

Recommended git hook (`.git/hooks/pre-commit`):

```bash
#!/bin/sh

echo "Running tests..."
composer test || exit 1

echo "Running static analysis..."
composer analyse || exit 1

echo "Checking code format..."
composer format --test || exit 1

echo "All checks passed!"
```

## Authentication Strategies

### None
No authentication required.

### Basic
HTTP Basic authentication.

```php
'report_auth' => 'basic',
'report_username' => env('WONDER_AB_REPORT_USERNAME'),
'report_password' => env('WONDER_AB_REPORT_PASSWORD'),
```

### Closure
Custom callback function.

```php
'report_auth' => 'closure',
'report_auth_callback' => function (Request $request) {
    return $request->user()?->isAdmin() ?? false;
},
```

### Middleware
Use Laravel middleware stack.

```php
'report_auth' => 'middleware',
'report_middleware' => ['auth', 'can:view-ab-reports'],
```

## Cache Strategy

The `CacheManager` provides an optional caching layer for experiment lookups:

```php
$cacheManager = app(CacheManager::class);

$experiment = $cacheManager->remember(
    "experiment:{$name}:{$goal}",
    fn () => Experiments::firstOrCreate([...])
);
```

### Configuration

```php
'cache' => [
    'enabled' => true,              // Enable/disable caching
    'driver' => 'redis',            // Specific driver or null for default
    'ttl' => 86400,                 // Cache TTL in seconds
    'prefix' => 'wonder_ab',        // Cache key prefix
],
```

### Methods

- `remember(string $key, callable $callback): mixed` - Cache result of callback
- `forget(string $key): void` - Remove cached item
- `flush(): void` - Clear all cached items (tags-based if available)
- `isEnabled(): bool` - Check if caching is enabled

## Rate Limiting

The `?abid` parameter allows overriding the instance ID for testing, but is rate-limited to prevent abuse:

```php
'allow_param' => true,              // Enable parameter override
'param_rate_limit' => 10,           // Max 10 overrides per IP per minute
```

Implementation in `WonderAb::initUser()`:

```php
$rateLimiter->hit($rateKey, 60);  // 60 second window
if ($rateLimiter->tooManyAttempts($rateKey, $maxAttempts)) {
    throw new ThrottleRequestsException('Too many instance ID overrides');
}
```

## Webhook Goal Registration API

Wonder AB provides a secure webhook endpoint for external services to register goals. This is useful for tracking server-side conversions like "payment completed" or "user verified email".

### Architecture

**Components:**
- **Route**: `POST /api/ab/webhook/goal` (defined in `routes/api.php`)
- **Controller**: `WebhookController::receiveGoal()`
- **Middleware**: `VerifyWebhookSignature` (HMAC-SHA256 verification)
- **Rate Limiter**: Named limiter `wonder-ab-webhook` (60/min per IP)

**Request Flow:**
1. Client calculates HMAC-SHA256 signature of JSON payload
2. POST request to `/api/ab/webhook/goal` with `X-AB-Signature` header
3. `VerifyWebhookSignature` middleware validates signature
4. Rate limiter checks IP-based throttling
5. Controller validates payload and timestamps
6. Idempotency check via cache
7. Goal created and associated with instance
8. Analytics triggered automatically
9. Response with goal ID and instance ID

### Security Features

**1. Signature Verification (HMAC-SHA256)**

```php
// Server calculates expected signature
$payload = $request->getContent();
$expectedSignature = hash_hmac('sha256', $payload, $secret);

// Timing-safe comparison
if (!hash_equals($expectedSignature, $providedSignature)) {
    return 401; // Unauthorized
}
```

**2. Timestamp Validation**

```php
// Prevents replay attacks
$timestamp = Carbon::parse($data['timestamp']);
$tolerance = config('wonder-ab.webhook.timestamp_tolerance', 300); // 5 min

if ($timestamp->diffInSeconds($now, false) > $tolerance || $timestamp->isFuture()) {
    return 422; // Invalid timestamp
}
```

**3. Idempotency**

```php
// Prevents duplicate goal registration
$idempotencyKey = 'webhook_idempotency:' . $data['idempotency_key'];
if (Cache::has($idempotencyKey)) {
    return cached response; // 200 with duplicate flag
}

// Cache result for 24 hours (configurable)
Cache::put($idempotencyKey, $result, $ttl);
```

**4. Rate Limiting**

```php
RateLimiter::for('wonder-ab-webhook', function (Request $request) {
    return Limit::perMinute(config('wonder-ab.webhook.rate_limit', 60))
        ->by($request->ip());
});
```

### Request Format

**Endpoint**: `POST /api/ab/webhook/goal`

**Headers:**
```
Content-Type: application/json
X-AB-Signature: <hmac-sha256-signature>
```

**Payload:**
```json
{
  "instance": "abc123def456...",           // Required: 32-char instance ID
  "goal": "purchase",                       // Required: goal name
  "value": "99.99",                         // Optional: goal value
  "timestamp": "2024-11-21T12:00:00+00:00", // Required: ISO 8601 (within 5 min)
  "idempotency_key": "unique-request-id"    // Required: prevents duplicates
}
```

**Success Response (201 Created):**
```json
{
  "success": true,
  "goal_id": 123,
  "instance_id": 456,
  "message": "Goal registered successfully"
}
```

**Duplicate Response (200 OK):**
```json
{
  "success": true,
  "goal_id": 123,
  "instance_id": 456,
  "message": "Goal already registered (idempotent)",
  "duplicate": true
}
```

**Error Responses:**
- `401` - Missing or invalid signature
- `403` - Webhook endpoint disabled
- `404` - Instance not found
- `422` - Validation failed (invalid fields, old timestamp, etc.)
- `429` - Rate limit exceeded
- `500` - Server error (secret not configured)

### Configuration

```php
'webhook' => [
    'enabled' => env('WONDER_AB_WEBHOOK_ENABLED', false),
    'secret' => env('WONDER_AB_WEBHOOK_SECRET'),          // Generate with artisan command
    'path' => env('WONDER_AB_WEBHOOK_PATH', '/ab/webhook/goal'),
    'rate_limit' => env('WONDER_AB_WEBHOOK_RATE_LIMIT', 60),
    'timestamp_tolerance' => env('WONDER_AB_WEBHOOK_TIMESTAMP_TOLERANCE', 300), // seconds
    'idempotency_ttl' => env('WONDER_AB_WEBHOOK_IDEMPOTENCY_TTL', 86400),      // seconds
],
```

### Client Implementation Examples

**PHP (with Guzzle):**
```php
use GuzzleHttp\Client;

$secret = config('services.wonder_ab.webhook_secret');
$payload = [
    'instance' => $user->ab_instance_id,
    'goal' => 'purchase',
    'value' => $order->total,
    'timestamp' => now()->toIso8601String(),
    'idempotency_key' => 'order_' . $order->id,
];

$body = json_encode($payload);
$signature = hash_hmac('sha256', $body, $secret);

$client = new Client();
$response = $client->post('https://yoursite.com/api/ab/webhook/goal', [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-AB-Signature' => $signature,
    ],
    'body' => $body,
]);
```

**Node.js:**
```javascript
const crypto = require('crypto');
const axios = require('axios');

const secret = process.env.WONDER_AB_WEBHOOK_SECRET;
const payload = {
  instance: user.abInstanceId,
  goal: 'signup',
  timestamp: new Date().toISOString(),
  idempotency_key: `signup_${user.id}`,
};

const body = JSON.stringify(payload);
const signature = crypto
  .createHmac('sha256', secret)
  .update(body)
  .digest('hex');

await axios.post('https://yoursite.com/api/ab/webhook/goal', payload, {
  headers: {
    'Content-Type': 'application/json',
    'X-AB-Signature': signature,
  },
});
```

**Python:**
```python
import hmac
import hashlib
import json
import requests
from datetime import datetime

secret = os.getenv('WONDER_AB_WEBHOOK_SECRET')
payload = {
    'instance': user.ab_instance_id,
    'goal': 'trial_started',
    'timestamp': datetime.utcnow().isoformat() + 'Z',
    'idempotency_key': f'trial_{user.id}',
}

body = json.dumps(payload)
signature = hmac.new(
    secret.encode(),
    body.encode(),
    hashlib.sha256
).hexdigest()

response = requests.post(
    'https://yoursite.com/api/ab/webhook/goal',
    data=body,
    headers={
        'Content-Type': 'application/json',
        'X-AB-Signature': signature,
    }
)
```

### Testing

**Generate Secret:**
```bash
php artisan ab:webhook-secret
```

**Test Webhook:**
```bash
# Set environment variables
export WONDER_AB_WEBHOOK_ENABLED=true
export WONDER_AB_WEBHOOK_SECRET="your-secret-here"

# Run test suite
vendor/bin/pest tests/Feature/WebhookGoalTest.php
```

**Test Cases Covered:**
- ✓ Signature verification (valid/invalid/missing)
- ✓ Payload validation (required fields, formats)
- ✓ Timestamp validation (old/future timestamps)
- ✓ Instance existence check
- ✓ Idempotency (duplicate requests)
- ✓ Rate limiting
- ✓ Goal creation with/without value
- ✓ Webhook disabled state

### Common Integration Patterns

**1. Payment Gateway Webhooks:**
```php
// Stripe webhook handler
public function handleStripeWebhook(Request $request)
{
    $event = $request->all();

    if ($event['type'] === 'payment_intent.succeeded') {
        $metadata = $event['data']['object']['metadata'];

        // Send to Wonder AB
        WonderAbWebhook::send([
            'instance' => $metadata['ab_instance'],
            'goal' => 'purchase',
            'value' => $event['data']['object']['amount'] / 100,
        ]);
    }
}
```

**2. Background Jobs:**
```php
// Laravel job
class TrackEmailVerification implements ShouldQueue
{
    public function handle()
    {
        WonderAbWebhook::send([
            'instance' => $this->user->ab_instance_id,
            'goal' => 'email_verified',
        ]);
    }
}
```

**3. Third-Party Services (Zapier, n8n):**
Configure webhook actions to POST to your Wonder AB endpoint with proper signature generation.

## Contributing

### Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test`)
5. Ensure PHPStan passes (`composer analyse`)
6. Format code (`composer format`)
7. Commit changes with clear messages
8. Push to your fork and submit a pull request

### Code Standards

- Follow PSR-12 coding standard
- Add PHPDoc blocks for public methods
- Type hint all parameters and return types
- Write tests for new features
- Update documentation as needed

### Release Process

1. Update version in `composer.json`
2. Run full test suite
3. Update README.md if needed
4. Create git tag (`git tag v1.0.0`)
5. Push tag (`git push origin v1.0.0`)
6. GitHub Actions will run tests and create release

## Debugging

### Enable Debug Mode

Add to `.env`:

```env
WONDER_AB_ANALYTICS_DRIVER=log
APP_DEBUG=true
```

This will log all analytics events to `storage/logs/laravel.log`.

### Test with Fixed Instance

```env
WONDER_AB_ALLOW_PARAM=true
```

Then visit: `https://yourapp.com?abid=test-user-123`

### Database Queries

Enable query logging:

```php
DB::enableQueryLog();
// ... run experiment
dd(DB::getQueryLog());
```

### Validate Setup

```bash
php artisan ab:validate
```

Checks:
- Database tables exist
- Indexes are created (MySQL only)
- Configuration is valid
- Analytics driver is configured
- Shows data statistics

## License

MIT License - see [LICENSE.md](LICENSE.md)
