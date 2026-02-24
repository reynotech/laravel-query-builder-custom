<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use DateTime;

class DateFilter extends BaseAdvancedFilter
{
    protected string $default = 'eq';

    private function wrapDateColumn($query, $property): string
    {
        return "DATE({$query->getGrammar()->wrap($property)})";
    }

    private function parseDate(?string $value, string $format): ?string
    {
        $date = $this->createDateFromFormat($format, $value);

        if ($date === null) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function parseMonthYear(?string $value): ?array
    {
        $date = $this->createDateFromFormat('m/Y', $value);

        if ($date === null) {
            return null;
        }

        return [(int) $date->format('n'), (int) $date->format('Y')];
    }

    private function createDateFromFormat(string $format, ?string $value): ?DateTime
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTime::createFromFormat('!' . $format, $value);
        if ($date === false) {
            return null;
        }

        $errors = DateTime::getLastErrors();
        if (is_array($errors)) {
            $warningCount = (int) ($errors['warning_count'] ?? 0);
            $errorCount = (int) ($errors['error_count'] ?? 0);

            if ($warningCount > 0 || $errorCount > 0) {
                return null;
            }
        }

        if ($date->format($format) !== $value) {
            return null;
        }

        return $date;
    }

    public function getFilters()
    {
        $soloFormat = config('app.date_format_solo', 'd/m/Y');

        return [
            'eq' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $date = $this->parseDate($value[0], $soloFormat);
                    if ($date === null) {
                        return;
                    }
                    $query->whereRaw(
                        "{$column} = ?",
                        [$date]
                    );
                }
            ],
            'neq' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $date = $this->parseDate($value[0], $soloFormat);
                    if ($date === null) {
                        return;
                    }
                    $query->whereRaw(
                        "{$column} <> ?",
                        [$date]
                    );
                }
            ],
            'lt' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $date = $this->parseDate($value[0], $soloFormat);
                    if ($date === null) {
                        return;
                    }
                    $query->whereRaw(
                        "{$column} < ?",
                        [$date]
                    );
                }
            ],
            'lte' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $date = $this->parseDate($value[0], $soloFormat);
                    if ($date === null) {
                        return;
                    }
                    $query->whereRaw(
                        "{$column} <= ?",
                        [$date]
                    );
                }
            ],
            'gt' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $date = $this->parseDate($value[0], $soloFormat);
                    if ($date === null) {
                        return;
                    }
                    $query->whereRaw(
                        "{$column} > ?",
                        [$date]
                    );
                }
            ],
            'gte' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $column = $this->wrapDateColumn($query, $property);
                    $date = $this->parseDate($value[0], $soloFormat);
                    if ($date === null) {
                        return;
                    }
                    $query->whereRaw(
                        "{$column} >= ?",
                        [$date]
                    );
                }
            ],
            'bw' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $range = $this->splitRangeValue($value[0]);
                    $start = $range[0] ?? null;
                    $end = $range[1] ?? $start;
                    $startDate = $this->parseDate($start, $soloFormat);
                    $endDate = $this->parseDate($end, $soloFormat);
                    if ($startDate === null || $endDate === null) {
                        return;
                    }
                    $column = $this->wrapDateColumn($query, $property);

                    $query->whereRaw(
                        "{$column} between ? and ?",
                        [
                            $startDate,
                            $endDate
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
                    $dates = [];
                    foreach ($list as $item) {
                        $date = $this->parseDate($item, $soloFormat);
                        if ($date === null) {
                            return;
                        }
                        $dates[] = $date;
                    }
                    $column = $this->wrapDateColumn($query, $property);
                    $placeholders = implode(',', array_fill(0, count($list), '?'));
                    $query->whereRaw("{$column} IN ({$placeholders})", $dates);
                }
            ],
            'my' => [
                'query' => function($query, $value, $property) {
                    $parsed = $this->parseMonthYear($value[0]);
                    if ($parsed === null) {
                        return;
                    }
                    [$month, $year] = $parsed;
                    $column = $query->getGrammar()->wrap($property);

                    $query->whereRaw(
                        "MONTH({$column}) = ? AND YEAR({$column}) = ?",
                        [$month, $year]
                    );
                }
            ],
            'bmy' => [
                'query' => function($query, $value, $property) {
                    $range = $this->splitRangeValue($value[0]);
                    $start = $range[0] ?? null;
                    $end = $range[1] ?? $start;
                    $startParsed = $this->parseMonthYear($start);
                    $endParsed = $this->parseMonthYear($end);
                    if ($startParsed === null || $endParsed === null) {
                        return;
                    }
                    [$startMonth, $startYear] = $startParsed;
                    [$endMonth, $endYear] = $endParsed;

                    $startKey = ($startYear * 100) + $startMonth;
                    $endKey = ($endYear * 100) + $endMonth;
                    $column = $query->getGrammar()->wrap($property);

                    $query->whereRaw(
                        "(YEAR({$column}) * 100 + MONTH({$column})) BETWEEN ? AND ?",
                        [$startKey, $endKey]
                    );
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
