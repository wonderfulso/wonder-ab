<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Wonderfulso\WonderAb\Models\Goal;
use Wonderfulso\WonderAb\Models\Instance;

beforeEach(function () {
    config()->set('wonder-ab.webhook.enabled', true);
    config()->set('wonder-ab.webhook.secret', 'test-secret-key');
    config()->set('wonder-ab.webhook.rate_limit', 60);
    config()->set('wonder-ab.webhook.timestamp_tolerance', 300);
    config()->set('wonder-ab.webhook.idempotency_ttl', 86400);
    Cache::flush();
});

function generateSignature(array $payload, string $secret = 'test-secret-key'): string
{
    return hash_hmac('sha256', json_encode($payload), $secret);
}

test('webhook endpoint rejects requests when disabled', function () {
    config()->set('wonder-ab.webhook.enabled', false);

    $payload = [
        'instance' => 'test-instance',
        'goal' => 'purchase',
        'timestamp' => now()->toIso8601String(),
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'success' => false,
        'error' => 'Webhook endpoint disabled',
    ]);
});

test('webhook endpoint rejects requests without signature', function () {
    $payload = [
        'instance' => 'test-instance',
        'goal' => 'purchase',
        'timestamp' => now()->toIso8601String(),
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload);

    $response->assertStatus(401);
    $response->assertJson([
        'success' => false,
        'error' => 'Missing signature',
    ]);
});

test('webhook endpoint rejects requests with invalid signature', function () {
    $payload = [
        'instance' => 'test-instance',
        'goal' => 'purchase',
        'timestamp' => now()->toIso8601String(),
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => 'invalid-signature',
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'success' => false,
        'error' => 'Invalid signature',
    ]);
});

test('webhook endpoint validates required fields', function () {
    $payload = [
        'goal' => 'purchase',
        // Missing instance, timestamp, idempotency_key
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'error' => 'Validation failed',
    ]);
    $response->assertJsonStructure(['details']);
});

test('webhook endpoint rejects old timestamps', function () {
    $instance = Instance::create(['instance' => 'test-instance-123']);

    $payload = [
        'instance' => $instance->instance,
        'goal' => 'purchase',
        'timestamp' => now()->subMinutes(10)->toIso8601String(), // Too old
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'error' => 'Invalid timestamp',
    ]);
});

test('webhook endpoint rejects future timestamps', function () {
    $instance = Instance::create(['instance' => 'test-instance-123']);

    $payload = [
        'instance' => $instance->instance,
        'goal' => 'purchase',
        'timestamp' => now()->addMinutes(10)->toIso8601String(), // In future
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
        'error' => 'Invalid timestamp',
    ]);
});

test('webhook endpoint returns 404 for non-existent instance', function () {
    $payload = [
        'instance' => 'non-existent-instance',
        'goal' => 'purchase',
        'timestamp' => now()->toIso8601String(),
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response->assertStatus(404);
    $response->assertJson([
        'success' => false,
        'error' => 'Instance not found',
    ]);
});

test('webhook endpoint successfully registers goal', function () {
    $instance = Instance::create(['instance' => 'test-instance-123']);

    $payload = [
        'instance' => $instance->instance,
        'goal' => 'purchase',
        'value' => '99.99',
        'timestamp' => now()->toIso8601String(),
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'success' => true,
        'message' => 'Goal registered successfully',
    ]);
    $response->assertJsonStructure([
        'success',
        'goal_id',
        'instance_id',
        'message',
    ]);

    // Verify goal was created in database
    $goal = Goal::where('goal', 'purchase')->first();
    expect($goal)->not->toBeNull();
    expect($goal->instance_id)->toBe($instance->id);
    expect($goal->value)->toBe('99.99');
});

test('webhook endpoint handles goals without value', function () {
    $instance = Instance::create(['instance' => 'test-instance-123']);

    $payload = [
        'instance' => $instance->instance,
        'goal' => 'signup',
        'timestamp' => now()->toIso8601String(),
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response->assertStatus(201);

    $goal = Goal::where('goal', 'signup')->first();
    expect($goal)->not->toBeNull();
    expect($goal->value)->toBeNull();
});

test('webhook endpoint prevents duplicate requests with idempotency key', function () {
    $instance = Instance::create(['instance' => 'test-instance-123']);

    $payload = [
        'instance' => $instance->instance,
        'goal' => 'purchase',
        'value' => '99.99',
        'timestamp' => now()->toIso8601String(),
        'idempotency_key' => 'unique-request-id-12345',
    ];

    // First request
    $response1 = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response1->assertStatus(201);
    $goalId1 = $response1->json('goal_id');

    // Second request with same idempotency key
    $response2 = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response2->assertStatus(200);
    $response2->assertJson([
        'success' => true,
        'goal_id' => $goalId1,
        'duplicate' => true,
    ]);

    // Verify only one goal was created
    expect(Goal::where('goal', 'purchase')->count())->toBe(1);
});

test('webhook endpoint respects rate limiting', function () {
    config()->set('wonder-ab.webhook.rate_limit', 2);

    $instance = Instance::create(['instance' => 'test-instance-123']);

    // Make requests up to the rate limit
    for ($i = 1; $i <= 2; $i++) {
        $payload = [
            'instance' => $instance->instance,
            'goal' => 'purchase',
            'timestamp' => now()->toIso8601String(),
            'idempotency_key' => Str::random(32),
        ];

        $response = $this->postJson('/api/ab/webhook/goal', $payload, [
            'X-AB-Signature' => generateSignature($payload),
        ]);

        $response->assertStatus(201);
    }

    // This should be rate limited
    $payload = [
        'instance' => $instance->instance,
        'goal' => 'purchase',
        'timestamp' => now()->toIso8601String(),
        'idempotency_key' => Str::random(32),
    ];

    $response = $this->postJson('/api/ab/webhook/goal', $payload, [
        'X-AB-Signature' => generateSignature($payload),
    ]);

    $response->assertStatus(429); // Too Many Requests
});
