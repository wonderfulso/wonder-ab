<?php

namespace Wonderfulso\WonderAb\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Verify the webhook request signature
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if webhook is enabled
        if (! config('wonder-ab.webhook.enabled', false)) {
            return response()->json([
                'success' => false,
                'error' => 'Webhook endpoint disabled',
            ], 403);
        }

        // Get secret from config
        $secret = config('wonder-ab.webhook.secret');

        if (empty($secret)) {
            \Log::error('Wonder AB webhook secret not configured');

            return response()->json([
                'success' => false,
                'error' => 'Webhook not properly configured',
            ], 500);
        }

        // Get signature from header
        $providedSignature = $request->header('X-AB-Signature');

        if (empty($providedSignature)) {
            return response()->json([
                'success' => false,
                'error' => 'Missing signature',
                'message' => 'X-AB-Signature header is required',
            ], 401);
        }

        // Calculate expected signature
        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Compare signatures (timing-safe comparison)
        if (! hash_equals($expectedSignature, $providedSignature)) {
            \Log::warning('Wonder AB webhook signature verification failed', [
                'ip' => $request->ip(),
                'provided' => substr($providedSignature, 0, 10).'...',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid signature',
            ], 401);
        }

        return $next($request);
    }
}
