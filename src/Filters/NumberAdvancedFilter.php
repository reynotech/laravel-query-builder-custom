<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

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
                'query' => function($query, $value, $property) {
                    $list = $this->splitListValue($value);
                    if ($list === []) {
                        return;
                    }
                    $column = $this->wrapNumericColumn($query, $property);
                    $placeholders = implode(',', array_fill(0, count($list), '?'));
                    $query->whereRaw("{$column} IN ({$placeholders})", $list);
                }
            ],
        ];
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
            $query->whereRaw(strtr($operation['string'], $occurrences));
            return;
        }

        $query->where($property, $operation['op'], $value);
    }

    protected function defaultKey(): ?string
    {
        return 'number';
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
