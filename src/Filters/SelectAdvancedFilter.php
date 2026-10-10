<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Filters;

use ReynoTECH\QueryBuilderCustom\Exceptions\InvalidFilterValue;

/**
 * Select filter with optional server-owned allowed values.
 *
 * Boolean grouping and NOT are supplied by BaseAdvancedFilter; this class only
 * owns operations that are meaningful for a select-backed column.
 */
final class SelectAdvancedFilter extends BaseAdvancedFilter
{
    /** @param list<int|float|string|bool>|null $allowedValues */
    public function __construct(private readonly ?array $allowedValues = null)
    {
    }

    public function getFilters(): array
    {
        return [
            'eq' => ['op' => '='],
            'neq' => ['op' => '<>'],
            'in' => ['op' => 'in'],
            'nin' => ['op' => 'not in'],
            'null' => ['op' => 'null'],
            'nnull' => ['op' => 'not null'],
        ];
    }

    public function processQuery($query, $value, $property): void
    {
        [$operation, $value] = $value;
        if (! array_key_exists($operation, $this->getFilters())) {
            return;
        }

        if ($operation === 'null') {
            $query->whereNull($property);
            return;
        }
        if ($operation === 'nnull') {
            $query->whereNotNull($property);
            return;
        }

        if (in_array($operation, ['in', 'nin'], true)) {
            $values = $this->splitListValue($value);
            if ($values === []) {
                return;
            }
            $this->assertAllowed($values);
            $operation === 'in'
                ? $query->whereIn($property, $values)
                : $query->whereNotIn($property, $values);
            return;
        }

        $this->assertAllowed([$value]);
        $query->where($property, $operation === 'eq' ? '=' : '<>', $value);
    }

    protected function defaultKey(): ?string
    {
        return 'select';
    }

    protected function validateExpressionCondition(string $operator, mixed $value): void
    {
        if (in_array($operator, ['null', 'nnull'], true)) {
            return;
        }

        if (in_array($operator, ['in', 'nin'], true)) {
            if ($this->splitListValue($value) === []) {
                throw InvalidFilterValue::because("Select filter operator \"{$operator}\" requires at least one value.");
            }
            return;
        }

        if (is_array($value)) {
            throw InvalidFilterValue::because("Select filter operator \"{$operator}\" requires a scalar value.");
        }
    }

    /** @param list<mixed> $values */
    private function assertAllowed(array $values): void
    {
        if ($this->allowedValues === null) {
            return;
        }

        $allowed = array_map(static fn (mixed $value): string => (string) $value, $this->allowedValues);
        foreach ($values as $value) {
            if (! in_array((string) $value, $allowed, true)) {
                throw InvalidFilterValue::because(sprintf('Select filter value "%s" is not allowed.', (string) $value));
            }
        }
    }
}
