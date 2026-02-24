<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

class MongoDateTimeCast extends BaseMongoDateCast
{
    public const TOONLYDATE = self::class . ':true,d/m/y';

    protected string $storageFormat = 'Y-m-d H:i:s';
    protected string $configKey = 'app.date_format';
    protected string $configFallback = 'd/m/y h:i A';

    protected function getCastConfig(): array
    {
        $datetime = config('query_builder_custom.casts.datetime', []);
        $mongo = config('query_builder_custom.casts.mongo', []);

        if (!is_array($datetime)) {
            $datetime = [];
        }
        if (!is_array($mongo)) {
            $mongo = [];
        }

        return array_merge(['set_parsing_default' => true], $datetime, $mongo);
    }
}
