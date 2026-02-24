<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom;

use Illuminate\Support\ServiceProvider;

class QueryBuilderCustomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/query_builder_custom.php',
            'query_builder_custom'
        );
    }

    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/query_builder_custom.php' => config_path('query_builder_custom.php'),
            ],
            'query-builder-custom-config'
        );
    }
}
