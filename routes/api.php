<?php

use Illuminate\Support\Facades\Route;
use Wonderfulso\WonderAb\Http\Controllers\WebhookController;
use Wonderfulso\WonderAb\Http\Middleware\VerifyWebhookSignature;

/*
|--------------------------------------------------------------------------
| Wonder AB API Routes
|--------------------------------------------------------------------------
|
| API routes for webhook-based goal registration and other stateless
| interactions with the Wonder AB testing system.
|
*/

Route::post(
    config('wonder-ab.webhook.path', '/ab/webhook/goal'),
    [WebhookController::class, 'receiveGoal']
)->middleware([
    'throttle:wonder-ab-webhook',
    VerifyWebhookSignature::class,
])->name('wonder-ab.webhook.goal');
