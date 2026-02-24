<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;

abstract class BaseMongoDateCast extends BaseDateCast
{
    public function __construct(bool|string|null $setParsing = null, string|bool|null $getFormat = null, string|bool|null $setFormat = null)
    {
        parent::__construct($setParsing, $getFormat, $setFormat);
    }

    protected function formatDateForStorage($date, ?string $originFormat): ?UTCDateTime
    {
        $carbon = $this->toCarbon($date, $originFormat);
        if ($carbon === null) {
            return null;
        }

        return $this->toUtcDateTime($carbon);
    }

    protected function normalizeSetValue($value)
    {
        if ($value instanceof UTCDateTime) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $this->toUtcDateTime(Carbon::instance($value));
        }

        return $value;
    }

    protected function toCarbon($value, ?string $originFormat): ?Carbon
    {
        if ($value instanceof UTCDateTime) {
            return Carbon::instance($value->toDateTime());
        }

        return parent::toCarbon($value, $originFormat);
    }

    protected function toUtcDateTime(Carbon $carbon): UTCDateTime
    {
        $milliseconds = (int) $carbon->format('Uv');

        return new UTCDateTime($milliseconds);
    }
}
