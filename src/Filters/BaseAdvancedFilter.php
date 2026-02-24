<?php

namespace ReynoTECH\QueryBuilderCustom\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

abstract class BaseAdvancedFilter implements Filter
{
    protected string $default = 'eq';

    public function __invoke(Builder $query, $value, string $property): void
    {
        $this->processQuery($query, $this->normalizeValue($value), $property);
    }

    abstract public function processQuery($query, $value, $property);

    protected function normalizeValue($value): array
    {
        if (is_array($value)) {
            if (array_key_exists(0, $value) && is_string($value[0])) {
                $value[0] = $this->normalizeOperator($value[0]);
            }
            return $value;
        }

        if (is_string($value)) {
            $delimiter = $this->getDelimiter();
            if ($delimiter !== '' && str_contains($value, $delimiter)) {
                $parts = explode($delimiter, $value, 2);
                if (count($parts) === 2) {
                    $parts[0] = $this->normalizeOperator($parts[0]);
                    return $parts;
                }
            }
        }

        return [$this->normalizeOperator($this->getDefaultOperation()), $value];
    }

    protected function splitDelimiterValues($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [''];
        }

        $delimiter = $this->getDelimiter();
        if ($delimiter !== '' && str_contains($value, $delimiter)) {
            return array_map('trim', explode($delimiter, $value));
        }

        return [$value];
    }

    protected function splitListValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $separator = $this->getSeparator();
        if ($separator !== '' && str_contains($value, $separator)) {
            return array_map('trim', explode($separator, $value));
        }

        return [$value];
    }

    protected function splitRangeValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $separator = $this->getSeparator();
        if ($separator !== '' && str_contains($value, $separator)) {
            return array_map('trim', explode($separator, $value));
        }

        return [$value, $value];
    }

    protected function getDelimiter(): string
    {
        $delimiter = (string) config(
            'query_builder_custom.filters.delimiter',
            config('query_builder_custom.filter_delimiter', '|')
        );

        return $delimiter !== '' ? $delimiter : '|';
    }

    protected function getSeparator(): string
    {
        $separator = (string) config(
            'query_builder_custom.filters.separator',
            config('query_builder_custom.filter_separator', ',')
        );

        return $separator !== '' ? $separator : ',';
    }

    protected function getDefaultOperation(): string
    {
        $key = $this->defaultKey();
        if ($key !== null && $key !== '') {
            $value = config("query_builder_custom.filters.defaults.{$key}");
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $this->default;
    }

    protected function defaultKey(): ?string
    {
        return null;
    }

    protected function normalizeOperator(string $operator): string
    {
        $operator = strtolower($operator);
        $aliases = config('query_builder_custom.filters.operator_aliases', []);

        if (is_array($aliases)) {
            $aliases = array_change_key_case($aliases, CASE_LOWER);
            $mapped = $aliases[$operator] ?? null;
            if (is_string($mapped) && $mapped !== '') {
                return strtolower($mapped);
            }
        }

        return $operator;
    }
}
