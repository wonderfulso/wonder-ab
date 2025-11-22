<?php

use Illuminate\Support\Facades\Route;
use Wonderfulso\WonderAb\Http\Middleware\WonderAbMiddleware;

test('middleware initializes user session', function () {
    config()->set('wonder-ab.cache_key', 'wonder_ab_user');

    Route::middleware(WonderAbMiddleware::class)->get('/test', function () {
        $ab = app()->make('Ab');
        $instance = $ab::getSession();

        return response()->json(['instance' => $instance ? $instance->instance : null]);
    });

    $response = $this->get('/test');

    $response->assertStatus(200);
    expect($response->json('instance'))->not->toBeNull();
});

test('middleware preserves existing session', function () {
    Route::middleware(WonderAbMiddleware::class)->get('/test', function () {
        return response()->json(['instance' => session()->get('wonder_ab_user')]);
    });

    $firstResponse = $this->get('/test');
    $firstInstance = $firstResponse->json('instance');

    $secondResponse = $this->get('/test');
    $secondInstance = $secondResponse->json('instance');

    expect($firstInstance)->toBe($secondInstance);
});
