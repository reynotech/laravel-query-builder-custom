<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Filters;

/** Exact date/time comparisons; DateFilter continues to compare calendar days. */
class DateTimeFilter extends DateFilter
{
    protected string $formatKind = 'dateTime';
    protected bool $withTime = true;

    protected function defaultKey(): ?string
    {
        return 'datetime';
    }
}
