<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

class MongoDateCast extends BaseMongoDateCast
{
    public const SETPARSED = self::class . ':true';
    public const SETONLYMONTHYEAR = self::class . ':true,m/Y,m/Y';

    protected string $storageFormat = 'Y-m-d';
    protected string $configKey = 'app.date_format_solo';
    protected string $configFallback = 'd/m/y';

    protected function getCastConfig(): array
    {
        $date = config('query_builder_custom.casts.date', []);
        $mongo = config('query_builder_custom.casts.mongo', []);

        if (!is_array($date)) {
            $date = [];
        }
        if (!is_array($mongo)) {
            $mongo = [];
        }

        return array_merge(['set_parsing_default' => true], $date, $mongo);
    }
}
