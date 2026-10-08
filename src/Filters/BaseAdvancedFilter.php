<?php

namespace ReynoTECH\QueryBuilderCustom\Filters;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use ReynoTECH\QueryBuilderCustom\BooleanFilterExpression;
use ReynoTECH\QueryBuilderCustom\SpatieV2FilterConditions;
use Spatie\QueryBuilder\Filters\Filter;

abstract class BaseAdvancedFilter implements Filter
{
    protected string $default = 'eq';

    public function __invoke(Builder $query, $value, string $property): void
    {
        if (config('query_builder_custom.filters.spatie_v2.enabled', false)) {
            $value = BooleanFilterExpression::fromEncoded($value)
                ?? SpatieV2FilterConditions::fromEncoded($value)
                ?? $value;
        }

        if ($value instanceof BooleanFilterExpression) {
            $this->applyExpressionNode($query, $value->root(), $property);
            return;
        }

        if ($value instanceof SpatieV2FilterConditions) {
            // One group for the whole field: an `or` joins this field's own
            // conditions and must never reach the constraints already on the
            // query (`tenant = 1 AND a OR b` would return every tenant's b).
            $query->where(function (Builder $group) use ($value, $property): void {
                foreach ($value->all() as $condition) {
                    $normalized = $condition['operator'] === null
                        ? $this->normalizeValue($condition['value'])
                        : [$this->normalizeOperator($condition['operator']), $condition['value']];

                    $apply = function (Builder $nested) use ($normalized, $property): void {
                        $this->processQuery($nested, $normalized, $property);
                    };

                    if ($condition['join'] === 'or') {
                        $group->orWhere($apply);
                    } else {
                        $group->where($apply);
                    }
                }
            });

            return;
        }

        $this->processQuery($query, $this->normalizeValue($value), $property);
    }

    /** @param array<string, mixed> $node */
    private function applyExpressionNode(Builder $query, array $node, string $property, string $boolean = 'and'): void
    {
        $apply = function (Builder $nested) use ($node, $property): void {
            if ($node['type'] === 'condition') {
                $operator = $this->normalizeOperator($node['op']);
                if (! $this->supportsExpressionOperator($operator)) {
                    throw new InvalidArgumentException(sprintf(
                        'Operator "%s" is not supported by %s.',
                        $operator,
                        static::class,
                    ));
                }

                $this->validateExpressionCondition($operator, $node['value']);
                $this->processQuery($nested, [$operator, $node['value']], $property);
                return;
            }

            foreach ($node['children'] as $index => $child) {
                $this->applyExpressionNode(
                    $nested,
                    $child,
                    $property,
                    $index === 0 ? 'and' : $node['operator'],
                );
            }
        };

        $not = ($node['not'] ?? false) === true;
        $method = match ([$boolean, $not]) {
            ['or', true] => 'orWhereNot',
            ['or', false] => 'orWhere',
            ['and', true] => 'whereNot',
            default => 'where',
        };

        $query->{$method}($apply);
    }

    protected function supportsExpressionOperator(string $operator): bool
    {
        if (! method_exists($this, 'getFilters')) {
            return false;
        }

        $filters = $this->getFilters();

        return is_array($filters) && array_key_exists($operator, $filters);
    }

    protected function validateExpressionCondition(string $operator, mixed $value): void
    {
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
