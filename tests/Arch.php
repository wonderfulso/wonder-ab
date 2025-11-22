<?php

use Wonderfulso\WonderAb\Contracts\AnalyticsDriver;

arch('models extend Eloquent Model')
    ->expect('pivotalso\PivotalAb\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->not->toExtend('Eloquent');

arch('analytics drivers implement interface')
    ->expect('pivotalso\PivotalAb\Analytics\Drivers')
    ->toImplement(AnalyticsDriver::class);

arch('no serialize usage')
    ->expect('pivotalso\PivotalAb')
    ->not->toUse(['serialize', 'unserialize']);

arch('controllers and models should not use facades')
    ->expect('pivotalso\PivotalAb\Models')
    ->not->toUse('Illuminate\Support\Facades');

arch('no direct $_SERVER access in middleware')
    ->expect('pivotalso\PivotalAb\Http\Middleware')
    ->not->toUse('$_SERVER')
    ->ignoring('pivotalso\PivotalAb\Http\Middleware\WonderAbAuthMiddleware'); // Basic auth needs it

arch('globals')
    ->expect(['dd', 'dump', 'die', 'var_dump', 'print_r'])
    ->not->toBeUsed();
