# Wonder AB

**Blade-based A/B testing for Laravel 12+ with multiple analytics integrations.**

[![Tests](https://github.com/wonderfulso/wonder-ab/actions/workflows/tests.yml/badge.svg)](https://github.com/wonderfulso/wonder-ab/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Wonder AB makes it easy to create and manage A/B tests directly in your Laravel Blade templates with minimal configuration. Track experiments with your choice of analytics platform.

## Features

- 🎯 **Blade Directives** - Test variants directly in your templates
- 📊 **Multiple Analytics** - Built-in support for Google Analytics 4, Plausible, webhooks, and more
- 🔐 **Flexible Authentication** - Optional authentication for reporting dashboards
- ⚡ **Performance Optimized** - Optional caching and database indexes
- 🎲 **Weighted Variants** - Control traffic distribution
- 🪆 **Nested Tests** - Run experiments within experiments
- 🔒 **Sticky Sessions** - Consistent user experience across visits

## Requirements

- PHP 8.3+
- Laravel 12.0+

## Installation

Install via Composer:

```bash
composer require wonderfulso/wonder-ab
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag="wonder-ab-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="wonder-ab-config"
```

## Quick Start

### 1. Basic A/B Test in Blade

```blade
@ab('hero-text')
    @condition('welcome')
        <h1>Welcome to Our Site</h1>
    @condition('get-started')
        <h1>Ready to Get Started?</h1>
    @track('signup')
```

**Note**: `@track` ends the experiment block - there is no `@endab` directive.

### 2. Track Goals

```blade
@ab('pricing-test')
    @condition('monthly')
        <button>$9/month</button>
    @condition('yearly')
        <button>$99/year</button>
    @track('purchase')

{{-- Later in your code, when purchase is completed --}}
@goal('purchase', 99.00)
```

**How it works**:
- `@track('purchase')` - Associates this experiment with the "purchase" goal
- `@goal('purchase', 99.00)` - Records that the goal was achieved (with optional value)

### 3. Controller-Based Tests

```php
use Wonderfulso\WonderAb\Facades\Ab;

$variant = Ab::choice('checkout-flow', [
    'one-step' => 'single page checkout',
    'multi-step' => 'wizard checkout',
]);

// Use $variant to determine which view to show
```

### 4. Weighted Variants

Control traffic distribution with weights in square brackets:

```blade
@ab('feature-test')
    @condition('new-feature[80]')
        <div class="new-design">New Feature</div>
    @condition('old-feature[20]')
        <div class="old-design">Old Feature</div>
    @track('conversion')
```

**Weights explained**:
- `'new-feature[80]'` - 80% probability
- `'old-feature[20]'` - 20% probability
- Weights are relative: `[80]` and `[20]` = 80:20 ratio
- Without weights, all variants have equal probability

### 5. Nested Tests

```blade
@ab('homepage-layout')
    @condition('modern')
        <div class="modern-layout">
            @ab('cta-button')
                @condition('green')
                    <button class="bg-green">Sign Up</button>
                @condition('blue')
                    <button class="bg-blue">Sign Up</button>
                @track('click')
        </div>
    @condition('classic')
        <div class="classic-layout">...</div>
    @track('engagement')
```

## Analytics Setup

Wonder AB supports multiple analytics platforms. Configure in `config/wonder-ab.php`:

### None (Default)
```php
'analytics' => [
    'driver' => 'none', // Events stored in database only
],
```

### Google Analytics 4
```php
'analytics' => [
    'driver' => 'google',
    'google' => [
        'measurement_id' => env('WONDER_AB_GA4_MEASUREMENT_ID'),
        'api_secret' => env('WONDER_AB_GA4_API_SECRET'),
    ],
],
```

### Plausible Analytics
```php
'analytics' => [
    'driver' => 'plausible',
    'plausible' => [
        'domain' => env('WONDER_AB_PLAUSIBLE_DOMAIN'),
        'api_key' => env('WONDER_AB_PLAUSIBLE_API_KEY'), // optional
    ],
],
```

### Webhook
```php
'analytics' => [
    'driver' => 'webhook',
    'webhook_url' => env('WONDER_AB_WEBHOOK_URL'),
    'webhook_secret' => env('WONDER_AB_WEBHOOK_SECRET'),
],
```

### Custom Driver
```php
'analytics' => [
    'driver' => 'custom',
    'custom_driver' => \App\Analytics\MyCustomDriver::class,
],
```

## Webhook Goal Registration

Track server-side events (like "user signed up" or "payment completed") via webhook API:

### 1. Generate Webhook Secret

```bash
php artisan ab:webhook-secret
```

### 2. Configure Environment

```env
WONDER_AB_WEBHOOK_ENABLED=true
WONDER_AB_WEBHOOK_SECRET=your-generated-secret-here
```

### 3. Get Instance ID for External Apps

When redirecting users to external services (like payment processors), pass the instance ID:

```php
use Wonderfulso\WonderAb\Facades\Ab;

// Get the current user's instance ID
$instanceId = Ab::getInstanceId();

// Pass it to external service
return redirect("https://payment-processor.com/checkout?return_id={$instanceId}");
```

The external service can then send this instance ID back via webhook when the goal is achieved.

### 4. Send Goal Events

```bash
# Calculate HMAC-SHA256 signature
payload='{"instance":"abc123","goal":"purchase","value":"99.99","timestamp":"2024-11-21T12:00:00Z","idempotency_key":"unique-id"}'
signature=$(echo -n "$payload" | openssl dgst -sha256 -hmac "your-secret" | awk '{print $2}')

# POST to webhook endpoint
curl -X POST https://yoursite.com/api/ab/webhook/goal \
  -H "Content-Type: application/json" \
  -H "X-AB-Signature: $signature" \
  -d "$payload"
```

**Payload Fields:**
- `instance` (required) - User's A/B testing instance ID
- `goal` (required) - Goal name (e.g., "purchase", "signup")
- `value` (optional) - Goal value (e.g., purchase amount)
- `timestamp` (required) - ISO 8601 timestamp (must be within 5 minutes)
- `idempotency_key` (required) - Unique request ID to prevent duplicates

**Security Features:**
- HMAC-SHA256 signature verification
- Timestamp validation (prevents replay attacks)
- Idempotency keys (prevents duplicate goals)
- Rate limiting (60 requests/minute per IP)

## Viewing Results

### CLI Commands

```bash
# View all experiments and their performance
php artisan ab:report

# View specific experiment
php artisan ab:report hero-text

# List all experiments
php artisan ab:report --list

# Export data to JSON
php artisan ab:export

# Generate webhook secret
php artisan ab:webhook-secret
```

### Web Dashboard

Visit `/ab/report` in your browser (requires authentication - see configuration).

## Configuration

Key configuration options in `config/wonder-ab.php`:

```php
return [
    // Session identifier key
    'cache_key' => 'wonder_ab_user',

    // Allow ?abid parameter to set instance ID (useful for testing)
    'allow_param' => env('WONDER_AB_ALLOW_PARAM', false),
    'param_rate_limit' => env('WONDER_AB_PARAM_RATE_LIMIT', 10),

    // Caching (improves performance)
    'cache' => [
        'enabled' => env('WONDER_AB_CACHE_ENABLED', true),
        'driver' => env('WONDER_AB_CACHE_DRIVER', null), // null = default
        'ttl' => env('WONDER_AB_CACHE_TTL', 86400),
        'prefix' => 'wonder_ab',
    ],

    // Report authentication
    'report_auth' => env('WONDER_AB_REPORT_AUTH', 'none'), // none, basic, closure, middleware
    'report_username' => env('WONDER_AB_REPORT_USERNAME'),
    'report_password' => env('WONDER_AB_REPORT_PASSWORD'),

    // Analytics driver configuration
    'analytics' => [
        'driver' => env('WONDER_AB_ANALYTICS_DRIVER', 'none'),
        // ... driver-specific config
    ],
];
```

## Environment Variables

Add to your `.env` file:

```env
# Analytics (outbound - send experiment data to external services)
WONDER_AB_ANALYTICS_DRIVER=none  # none, log, google, plausible, webhook, pivotal

# Google Analytics 4
WONDER_AB_GA4_MEASUREMENT_ID=
WONDER_AB_GA4_API_SECRET=

# Plausible
WONDER_AB_PLAUSIBLE_DOMAIN=
WONDER_AB_PLAUSIBLE_API_KEY=

# Webhook (outbound analytics)
WONDER_AB_WEBHOOK_URL=
WONDER_AB_WEBHOOK_SECRET=

# Webhook Goal Registration (inbound - receive goal events from external services)
WONDER_AB_WEBHOOK_ENABLED=false
WONDER_AB_WEBHOOK_SECRET=  # Generate with: php artisan ab:webhook-secret
WONDER_AB_WEBHOOK_RATE_LIMIT=60
WONDER_AB_WEBHOOK_PATH=/ab/webhook/goal

# Report Authentication
WONDER_AB_REPORT_AUTH=basic
WONDER_AB_REPORT_USERNAME=admin
WONDER_AB_REPORT_PASSWORD=secret

# Optional: Performance
WONDER_AB_CACHE_ENABLED=true
WONDER_AB_ALLOW_PARAM=false
```

## Testing

```bash
composer test
composer analyse  # PHPStan
composer format   # Laravel Pint
```

## Security

- Rate limiting on parameter overrides
- JSON storage (no unserialize vulnerabilities)
- Webhook signature verification
- Optional authentication for reports

## Documentation

For advanced usage, custom drivers, and architecture details, see [DEVELOPER.md](DEVELOPER.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.

## Credits

- [Rulian Estivalletti](https://github.com/ruliancrafter)
- [All Contributors](../../contributors)

## Support

- **Issues**: [GitHub Issues](https://github.com/wonderfulso/wonder-ab/issues)
- **Discussions**: [GitHub Discussions](https://github.com/wonderfulso/wonder-ab/discussions)
