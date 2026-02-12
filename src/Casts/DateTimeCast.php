<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

class DateTimeCast extends BaseDateCast
{
    public const TOONLYDATE = self::class . ':true,d/m/y';

    protected string $storageFormat = 'Y-m-d H:i:s';
    protected string $configKey = 'app.date_format';
    protected string $configFallback = 'd/m/y h:i A';
}
