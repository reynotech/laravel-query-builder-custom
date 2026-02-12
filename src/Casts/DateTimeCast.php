<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

use Carbon\Carbon;

class DateTimeCast extends BaseDateCast
{
    public const TOONLYDATE = self::class.':true,d/m/y';

    protected $storageFormat = 'Y-m-d H:i:s';
    protected $configKey = 'app.date_format';
    protected $configFallback = 'd/m/y h:i A';

    public function formatDate($date, $originFormat = 'Y-m-d H:i:s', $toFormat = false)
    {
        if (!$toFormat) $toFormat = config('app.date_format', 'd/m/y h:i A');
        return $date ? Carbon::createFromFormat($originFormat, $date)->translatedFormat($toFormat) : null;
    }
}
