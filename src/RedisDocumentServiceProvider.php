<?php

namespace Ed9\RedisDatabase;

use Illuminate\Support\ServiceProvider;
use Averias\RedisJson\Factory\RedisJsonClientFactory;

class RedisDocumentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
