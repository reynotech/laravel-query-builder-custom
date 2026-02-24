<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use DateTime;

class DateFilter extends BaseAdvancedFilter
{
    protected string $default = 'eq';

    private function wrapDateColumn($query, $property): string
    {
        return "DATE({$query->getGrammar()->wrap($property)})";
    }

    private function parseDate(string $value, string $format): string
    {
        return DateTime::createFromFormat($format, $value)->format('Y-m-d');
    }

    public function getFilters()
    {
        $soloFormat = config('app.date_format_solo', 'd/m/Y');

        return [
            'eq' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $query->whereRaw(
                        "{$column} = ?",
                        [$this->parseDate($value[0], $soloFormat)]
                    );
                }
            ],
            'neq' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $query->whereRaw(
                        "{$column} <> ?",
                        [$this->parseDate($value[0], $soloFormat)]
                    );
                }
            ],
            'lt' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $query->whereRaw(
                        "{$column} < ?",
                        [$this->parseDate($value[0], $soloFormat)]
                    );
                }
            ],
            'lte' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $query->whereRaw(
                        "{$column} <= ?",
                        [$this->parseDate($value[0], $soloFormat)]
                    );
                }
            ],
            'gt' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $query->whereRaw(
                        "{$column} > ?",
                        [$this->parseDate($value[0], $soloFormat)]
                    );
                }
            ],
            'gte' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $query->whereRaw(
                        "{$column} >= ?",
                        [$this->parseDate($value[0], $soloFormat)]
                    );
                }
            ],
            'bw' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $range = $this->splitRangeValue($value[0]);
                    $start = $range[0] ?? null;
                    $end = $range[1] ?? $start;
                    $column = $this->wrapDateColumn($query, $property);

                    $query->whereRaw(
                        "{$column} between ? and ?",
                        [
                            $this->parseDate($start, $soloFormat),
                            $this->parseDate($end, $soloFormat)
                        ]
                    );
                }
            ],
            'in' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $list = $this->splitListValue($value[0] ?? null);
                    if ($list === []) {
                        return;
                    }
                    $column = $this->wrapDateColumn($query, $property);
                    $placeholders = implode(',', array_fill(0, count($list), '?'));
                    $dates = array_map(fn($item) => $this->parseDate($item, $soloFormat), $list);
                    $query->whereRaw("{$column} IN ({$placeholders})", $dates);
                }
            ],
            'null' => [
                'solo' => true,
                'query' => function($query, $value, $property) {
                    $query->whereNull($property);
                }
            ],
            'nnull' => [
                'solo' => true,
                'query' => function($query, $value, $property) {
                    $query->whereNotNull($property);
                }
            ],
        ];
    }

    public function processQuery($query, $value, $property)
    {
        $filters = $this->getFilters();

        [$operation, $value] = $value;

        $value = $this->splitDelimiterValues($value);

        if (!array_key_exists($operation, $filters)) {
            return;
        }

        $filters[$operation]['query']($query, $value, $property);
    }

    protected function defaultKey(): ?string
    {
        return 'date';
    }
}
