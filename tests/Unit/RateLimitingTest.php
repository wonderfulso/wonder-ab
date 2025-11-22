<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Wonderfulso\WonderAb\WonderAb;

beforeEach(function () {
    config()->set('wonder-ab.allow_param', true);
    config()->set('wonder-ab.param_rate_limit', 3);
    RateLimiter::clear('ab_param_override:127.0.0.1');
});

test('allows abid parameter within rate limit', function () {
    $ab = app()->make('Ab');

    for ($i = 1; $i <= 3; $i++) {
        $request = Request::create('http://localhost', 'GET', ['abid' => "test-{$i}"]);
        $ab::resetSession();
        $ab::initUser($request);

        expect(session()->get('wonder_ab_user'))->toBe("test-{$i}");
    }
});

test('throws exception when rate limit exceeded', function () {
    $ab = app()->make('Ab');

    // Exhaust the rate limit
    for ($i = 1; $i <= 3; $i++) {
        $request = Request::create('http://localhost', 'GET', ['abid' => "test-{$i}"]);
        $ab::resetSession();
        $ab::initUser($request);
    }

    // This should throw
    $request = Request::create('http://localhost', 'GET', ['abid' => 'test-4']);
    $ab::resetSession();

    expect(fn () => $ab::initUser($request))
        ->toThrow(\Illuminate\Http\Exceptions\ThrottleRequestsException::class);
});

test('rate limit does not apply when param not used', function () {
    $ab = app()->make('Ab');

    for ($i = 1; $i <= 10; $i++) {
        $ab::resetSession();
        $ab::initUser();
        expect(session()->get('wonder_ab_user'))->not->toBeNull();
    }
});

test('rate limit does not apply when allow_param is false', function () {
    config()->set('wonder-ab.allow_param', false);

    $ab = app()->make('Ab');

    for ($i = 1; $i <= 10; $i++) {
        $request = Request::create('http://localhost', 'GET', ['abid' => "test-{$i}"]);
        $ab::resetSession();
        $ab::initUser($request);

        // Should generate random instance, not use abid parameter
        expect(session()->get('wonder_ab_user'))->not->toBe("test-{$i}");
    }
});

test('rate limit is per IP address', function () {
    $ab = app()->make('Ab');

    // Use up rate limit for one IP
    for ($i = 1; $i <= 3; $i++) {
        $request = Request::create('http://localhost', 'GET', ['abid' => "test-{$i}"]);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $ab::resetSession();
        $ab::initUser($request);
    }

    // Different IP should still work
    RateLimiter::clear('ab_param_override:192.168.1.1');
    $request = Request::create('http://localhost', 'GET', ['abid' => 'test-new']);
    $request->server->set('REMOTE_ADDR', '192.168.1.1');
    $ab::resetSession();
    $ab::initUser($request);

    expect(session()->get('wonder_ab_user'))->toBe('test-new');
});
