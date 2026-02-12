<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

class DateCast extends BaseDateCast
{
    public const SETPARSED = self::class . ':true';
    public const SETONLYMONTHYEAR = self::class . ':true,m/Y,m/Y';

    protected string $storageFormat = 'Y-m-d';
    protected string $configKey = 'app.date_format_solo';
    protected string $configFallback = 'd/m/y';
}
