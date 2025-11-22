<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Wonderfulso\WonderAb\Http\Middleware\WonderAbAuthMiddleware;

beforeEach(function () {
    Route::middleware(WonderAbAuthMiddleware::class)->get('/test-route', function () {
        return response()->json(['status' => 'ok']);
    });
});

test('none auth allows access without credentials', function () {
    config()->set('wonder-ab.report_auth', 'none');

    $response = $this->get('/test-route');

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});

test('basic auth requires credentials', function () {
    config()->set('wonder-ab.report_auth', 'basic');
    config()->set('wonder-ab.report_username', 'admin');
    config()->set('wonder-ab.report_password', 'secret');

    $response = $this->get('/test-route');

    $response->assertStatus(401);
});

test('basic auth allows access with correct credentials', function () {
    config()->set('wonder-ab.report_auth', 'basic');
    config()->set('wonder-ab.report_username', 'admin');
    config()->set('wonder-ab.report_password', 'secret');

    $credentials = base64_encode('admin:secret');
    $response = $this->withHeaders([
        'Authorization' => "Basic {$credentials}",
    ])->get('/test-route');

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});

test('basic auth denies access with incorrect credentials', function () {
    config()->set('wonder-ab.report_auth', 'basic');
    config()->set('wonder-ab.report_username', 'admin');
    config()->set('wonder-ab.report_password', 'secret');

    $credentials = base64_encode('admin:wrong');
    $response = $this->withHeaders([
        'Authorization' => "Basic {$credentials}",
    ])->get('/test-route');

    $response->assertStatus(401);
});

test('closure auth uses custom callback', function () {
    config()->set('wonder-ab.report_auth', 'closure');
    config()->set('wonder-ab.report_auth_callback', function (Request $request) {
        return $request->header('X-Custom-Token') === 'valid-token';
    });

    $response = $this->get('/test-route');
    $response->assertStatus(403);

    $response = $this->withHeaders([
        'X-Custom-Token' => 'valid-token',
    ])->get('/test-route');
    $response->assertStatus(200);
});

test('middleware auth strategy allows access', function () {
    config()->set('wonder-ab.report_auth', 'middleware');

    $response = $this->get('/test-route');

    $response->assertStatus(200);
});

test('invalid auth type returns 403', function () {
    config()->set('wonder-ab.report_auth', 'invalid-type');

    $response = $this->get('/test-route');

    $response->assertStatus(403);
});
