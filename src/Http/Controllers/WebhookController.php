<?php

namespace Wonderfulso\WonderAb\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Wonderfulso\WonderAb\Models\Goal;
use Wonderfulso\WonderAb\Models\Instance;

class WebhookController
{
    /**
     * Receive and process a goal registration webhook
     */
    public function receiveGoal(Request $request): JsonResponse
    {
        // Validate request payload
        $validator = Validator::make($request->all(), [
            'instance' => 'required|string|max:255',
            'goal' => 'required|string|max:255',
            'value' => 'nullable',
            'timestamp' => 'required|date|date_format:Y-m-d\TH:i:sP',
            'idempotency_key' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Validate timestamp (prevent replay attacks)
        $timestamp = \Carbon\Carbon::parse($data['timestamp']);
        $tolerance = config('wonder-ab.webhook.timestamp_tolerance', 300);
        $now = \Carbon\Carbon::now();

        if ($timestamp->diffInSeconds($now, false) > $tolerance || $timestamp->isFuture()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid timestamp',
                'message' => 'Timestamp must be within ' . $tolerance . ' seconds of current time',
            ], 422);
        }

        // Check idempotency key (prevent duplicate requests)
        $idempotencyKey = 'webhook_idempotency:' . $data['idempotency_key'];
        if (Cache::has($idempotencyKey)) {
            $cached = Cache::get($idempotencyKey);
            return response()->json([
                'success' => true,
                'goal_id' => $cached['goal_id'],
                'instance_id' => $cached['instance_id'],
                'message' => 'Goal already registered (idempotent)',
                'duplicate' => true,
            ], 200);
        }

        // Find instance
        $instance = Instance::where('instance', $data['instance'])->first();

        if (!$instance) {
            return response()->json([
                'success' => false,
                'error' => 'Instance not found',
                'message' => 'No A/B testing instance found with ID: ' . $data['instance'],
            ], 404);
        }

        // Create goal
        $goal = Goal::create([
            'instance_id' => $instance->id,
            'goal' => $data['goal'],
            'value' => $data['value'] ?? null,
        ]);

        // Save to instance relationship (triggers analytics)
        $instance->goals()->save($goal);

        // Cache result for idempotency
        $ttl = config('wonder-ab.webhook.idempotency_ttl', 86400);
        Cache::put($idempotencyKey, [
            'goal_id' => $goal->id,
            'instance_id' => $instance->id,
        ], $ttl);

        return response()->json([
            'success' => true,
            'goal_id' => $goal->id,
            'instance_id' => $instance->id,
            'message' => 'Goal registered successfully',
        ], 201);
    }
}
