<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use InvalidArgumentException;

class NumberAdvancedFilter extends BaseAdvancedFilter
{
    protected string $default = 'eq';

    private function wrapNumericColumn($query, $property): string
    {
        $column = $query->getGrammar()->wrap($property);

        if (str_contains($property, '->')) {
            return "CAST({$column} as {$this->getJsonCastType()})";
        }

        return $column;
    }

    public function getFilters()
    {
        $numericFn = fn($cond) => function($query, $value, $property) use ($cond) {
            $column = $this->wrapNumericColumn($query, $property);
            $query->whereRaw("{$column} {$cond} ?", [$value]);
        };

        return [
            'eq' => [
                'query' => $numericFn('=')
            ],
            'neq' => [
                'query' => $numericFn('<>')
            ],
            'lt' => [
                'query' => $numericFn('<')
            ],
            'lte' => [
                'query' => $numericFn('<=')
            ],
            'gt' => [
                'query' => $numericFn('>')
            ],
            'gte' => [
                'query' => $numericFn('>=')
            ],
            'bw' => [
                'query' => function($query, $value, $property) {
                    $range = $this->splitRangeValue($value);
                    $start = $range[0] ?? null;
                    $end = $range[1] ?? $start;
                    $column = $this->wrapNumericColumn($query, $property);
                    $query->whereRaw("{$column} BETWEEN ? AND ?", [$start, $end]);
                }
            ],
            'in' => [
                'query' => fn($query, $value, $property) => $this->whereList($query, $value, $property, 'IN')
            ],
            'nin' => [
                'query' => fn($query, $value, $property) => $this->whereList($query, $value, $property, 'NOT IN')
            ],
            'null' => [
                'query' => fn($query, $value, $property) => $query->whereNull($property)
            ],
            'nnull' => [
                'query' => fn($query, $value, $property) => $query->whereNotNull($property)
            ],
        ];
    }

    private function whereList($query, $value, $property, string $operator): void
    {
        $list = $this->splitListValue($value);
        if ($list === []) {
            return;
        }
        $column = $this->wrapNumericColumn($query, $property);
        $placeholders = implode(',', array_fill(0, count($list), '?'));
        $query->whereRaw("{$column} {$operator} ({$placeholders})", $list);
    }

    public function processQuery($query, $value, $property)
    {
        $filters = $this->getFilters();
        [$operation, $value] = $value;

        if (!array_key_exists($operation, $filters)) {
            return;
        }

        $operation = $filters[$operation];

        if (array_key_exists('query', $operation)) {
            $operation['query']($query, $value, $property);
            return;
        }

        if (isset($operation['rawString'])) {
            $column = $this->wrapNumericColumn($query, $property);
            $occurrences = [
                ':col:' => $column
            ];
            $query->whereRaw('(' . strtr($operation['string'], $occurrences) . ')');
            return;
        }

        $query->where($property, $operation['op'], $value);
    }

    protected function defaultKey(): ?string
    {
        return 'number';
    }

    protected function validateExpressionCondition(string $operator, mixed $value): void
    {
        if ($operator === 'bw') {
            $range = $this->splitRangeValue($value);
            if (count($range) !== 2 || ! is_numeric($range[0]) || ! is_numeric($range[1])) {
                throw new InvalidArgumentException('Number filter operator "bw" requires exactly two numeric values.');
            }
            return;
        }

        if (in_array($operator, ['null', 'nnull'], true)) {
            return;
        }

        if (in_array($operator, ['in', 'nin'], true)) {
            $values = $this->splitListValue($value);
            if ($values === [] || array_filter($values, static fn (mixed $item): bool => ! is_numeric($item)) !== []) {
                throw new InvalidArgumentException("Number filter operator \"{$operator}\" requires one or more numeric values.");
            }
            return;
        }

        if (is_array($value) || ! is_numeric($value)) {
            throw new InvalidArgumentException("Number filter operator \"{$operator}\" requires a numeric value.");
        }
    }

    private function getJsonCastType(): string
    {
        $config = config('query_builder_custom.filters.json_casts.number');

        if (is_string($config) && $config !== '') {
            return $config;
        }

        if (is_array($config)) {
            $type = strtoupper((string) ($config['type'] ?? 'DECIMAL'));
            $precision = $config['precision'] ?? null;
            $scale = $config['scale'] ?? null;

            if ($precision !== null && $scale !== null) {
                return sprintf('%s(%d, %d)', $type, (int) $precision, (int) $scale);
            }

            if ($precision !== null) {
                return sprintf('%s(%d)', $type, (int) $precision);
            }

            return $type;
        }

        return 'DECIMAL(20, 6)';
    }
}
