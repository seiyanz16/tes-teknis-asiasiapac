<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RedisService
{
    public function setClientCache(string $slug, array $data)
    {
        Cache::put($slug, json_encode($data), now()->addMinutes(60));
    }

    public function getClientCache(string $slug)
    {
        return Cache::get($slug);
    }

    public function deleteClientCache(string $slug)
    {
        Cache::forget($slug);
    }
}