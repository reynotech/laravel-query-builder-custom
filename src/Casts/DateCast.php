<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

use Carbon\Carbon;

class DateCast extends BaseDateCast
{
    public const SETPARSED = self::class.':true';
    public const SETONLYMONTHYEAR = self::class.':true,m/Y,m/Y';

    protected $storageFormat = 'Y-m-d';
    protected $configKey = 'app.date_format_solo';
    protected $configFallback = 'd/m/y';

    public function formatDate($date, $originFormat = 'Y-m-d', $toFormat = false)
    {
        if (!$toFormat) $toFormat = config('app.date_format_solo');
        return $date !== null ? Carbon::createFromFormat($originFormat, $date)->translatedFormat($toFormat) : null;
    }
}
