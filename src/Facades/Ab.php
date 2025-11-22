<?php

namespace Wonderfulso\WonderAb\Facades;

use Illuminate\Support\Facades\Facade;
use Wonderfulso\WonderAb\Models\Goal;
use Wonderfulso\WonderAb\Models\Instance;

/**
 * @method static void initUser(?\Illuminate\Http\Request $request = null)
 * @method static string saveSession()
 * @method static Goal|null goal(string $goal, mixed $value = null)
 * @method static \Wonderfulso\WonderAb\WonderAb choice(string $experiment, array $conditions)
 * @method static void resetSession()
 * @method static Instance|null getSession()
 * @method static string|null getInstanceId()
 * @method static array getActiveTests()
 * @method static void sendEvent(string $event, mixed $payload)
 *
 * @see \Wonderfulso\WonderAb\WonderAb
 */
class Ab extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Wonderfulso\WonderAb\WonderAb::class;
    }
}
