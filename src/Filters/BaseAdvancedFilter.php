<?php

namespace ReynoTECH\QueryBuilderCustom\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

abstract class BaseAdvancedFilter implements Filter
{
    protected string $default = 'eq';

    public function __invoke(Builder $query, $value, string $property): void
    {
        if (!is_array($value)) {
            $this->processQuery($query, [$this->default, $value], $property);
            return;
        }

        $this->processQuery($query, $value, $property);
    }

    abstract public function processQuery($query, $value, $property);
}
