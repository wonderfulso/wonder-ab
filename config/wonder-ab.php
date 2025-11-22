<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how user sessions are identified and tracked
    | for A/B testing purposes.
    |
    */

    'cache_key' => 'wonder_ab_user',

    'request_param' => env('WONDER_AB_REQUEST_PARAM', 'abid'),

    'allow_param' => env('WONDER_AB_ALLOW_PARAM', false),

    'param_rate_limit' => env('WONDER_AB_PARAM_RATE_LIMIT', 10), // per minute

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for experiment definitions. Set enabled to
    | false to disable caching entirely. Driver can be null to use Laravel's
    | default cache driver, or specify: redis, memcached, file, database, etc.
    |
    */

    'cache' => [
        'enabled' => env('WONDER_AB_CACHE_ENABLED', true),
        'driver' => env('WONDER_AB_CACHE_DRIVER', null), // null = use default
        'ttl' => env('WONDER_AB_CACHE_TTL', 86400), // 24 hours
        'prefix' => 'laravel_ab',
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Authentication
    |--------------------------------------------------------------------------
    |
    | Control access to the built-in web reports.
    | Options: none, basic, closure, middleware
    |
    | - none: No authentication (development only!)
    | - basic: HTTP Basic Authentication
    | - closure: Custom callback function (set report_auth_callback)
    | - middleware: Use your own Laravel middleware stack
    |
    */

    'report_auth' => env('WONDER_AB_REPORT_AUTH', 'none'),

    'report_url' => env('WONDER_AB_REPORT_URL', '/ab/report'),

    // For 'basic' auth
    'report_username' => env('WONDER_AB_REPORT_USERNAME', null),
    'report_password' => env('WONDER_AB_REPORT_PASSWORD', null),

    // For 'closure' auth - set this to a callable in your service provider
    'report_auth_callback' => null,

    // For 'middleware' auth - specify middleware to apply
    'report_middleware' => [], // ['auth', 'can:view-reports']

    /*
    |--------------------------------------------------------------------------
    | Analytics Integration
    |--------------------------------------------------------------------------
    |
    | Configure where A/B test data should be sent for analysis.
    | Available drivers: none, log, google, plausible, webhook, pivotal
    |
    | - none: Local database only (use built-in reports)
    | - log: Send to Laravel logs
    | - google: Google Analytics 4
    | - plausible: Plausible Analytics
    | - webhook: Generic webhook (Zapier, n8n, custom endpoint)
    | - pivotal: Pivotal AB service
    | - custom: Specify custom_driver class
    |
    */

    'analytics' => [
        'driver' => env('WONDER_AB_ANALYTICS_DRIVER', 'none'),

        // Webhook driver
        'webhook_url' => env('WONDER_AB_WEBHOOK_URL'),
        'webhook_secret' => env('WONDER_AB_WEBHOOK_SECRET'),

        // Google Analytics 4
        'google' => [
            'measurement_id' => env('WONDER_AB_GA4_MEASUREMENT_ID'),
            'api_secret' => env('WONDER_AB_GA4_API_SECRET'),
        ],

        // Plausible Analytics
        'plausible' => [
            'domain' => env('WONDER_AB_PLAUSIBLE_DOMAIN'),
            'api_key' => env('WONDER_AB_PLAUSIBLE_API_KEY'),
        ],

        // Pivotal AB Service
        'pivotal' => [
            'api_key' => env('WONDER_AB_API_KEY', ''),
            'api_url' => env('WONDER_AB_API_URL', 'https://ab.pivotal.so'),
        ],

        // Custom driver class (must implement AnalyticsDriver interface)
        'custom_driver' => null,
    ],
];
