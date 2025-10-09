<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait Cacheable
{
    public function cacheQuery($key, $ttl, $callback)
    {
        return Cache::remember($key, $ttl, $callback);
    }
}
