<?php

namespace Wonderfulso\WonderAb\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class CacheManager
{
    protected bool $enabled;

    protected Repository $store;

    protected int $ttl;

    protected string $prefix;

    public function __construct()
    {
        $this->enabled = config('wonder-ab.cache.enabled', true);
        $this->ttl = config('wonder-ab.cache.ttl', 86400);
        $this->prefix = config('wonder-ab.cache.prefix', 'laravel_ab');

        // Use configured driver, or fall back to Laravel's default
        $driver = config('wonder-ab.cache.driver');
        $this->store = $driver ? Cache::store($driver) : Cache::store();
    }

    public function remember(string $key, callable $callback): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        return $this->store->remember(
            $this->prefix.':'.$key,
            $this->ttl,
            $callback
        );
    }

    public function forget(string $key): void
    {
        if ($this->enabled) {
            $this->store->forget($this->prefix.':'.$key);
        }
    }

    public function flush(): void
    {
        if (! $this->enabled) {
            return;
        }

        // If using Redis/Memcached with tags support
        if (method_exists($this->store, 'tags')) {
            $this->store->tags([$this->prefix])->flush();
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
