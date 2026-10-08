<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTime;
use InvalidArgumentException;
use ReynoTECH\QueryBuilderCustom\DateFormats;

class DateFilter extends BaseAdvancedFilter
{
    protected string $default = 'eq';
    protected string $formatKind = 'date';
    protected bool $withTime = false;

    public function __construct(private ?string $format = null, private ?string $monthFormat = null)
    {
    }

    protected function inputFormat(): string
    {
        return $this->format ?? DateFormats::format($this->formatKind, 'filter');
    }


    private function wrapDateColumn($query, $property): string
    {
        $column = $query->getGrammar()->wrap($property);
        return $this->withTime ? $column : "DATE({$column})";
    }

    private function parseDate(?string $value, string $format): ?string
    {
        $date = $this->createDateFromFormat($format, $value);

        if ($date === null) {
            return null;
        }

        return $date->format($this->withTime ? 'Y-m-d H:i:s' : 'Y-m-d');
    }

    private function parseMonthYear(?string $value): ?array
    {
        $date = $this->createDateFromFormat($this->monthFormat ?? DateFormats::format('month', 'filter'), $value);

        if ($date === null) {
            return null;
        }

        return [(int) $date->format('n'), (int) $date->format('Y')];
    }

    private function applyMonthYearFilter($query, $property, ?string $value, bool $nullIsNull): void
    {
        if ($nullIsNull && ($value === null || $value === 'null')) {
            $query->whereNull($property);
            return;
        }
        $parsed = $this->parseMonthYear($value);
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

    private function applyRelativeFilter($query, $property, mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $range = $this->resolveRelativeRange($value);
        if ($range === null) {
            return;
        }

        $column = "DATE({$query->getGrammar()->wrap($property)})";
        $query->whereRaw("{$column} between ? and ?", $range);
    }

    /** @return array{string, string}|null */
    private function resolveRelativeRange(string $value): ?array
    {
        $timezone = config('query_builder_custom.filters.boolean_expressions.timezone')
            ?: config('app.timezone', 'UTC');
        $today = CarbonImmutable::now((string) $timezone)->startOfDay();
        $preset = strtolower(str_replace('-', '_', trim($value)));

        $range = match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last_7_days' => [$today->subDays(6), $today],
            'last_30_days' => [$today->subDays(29), $today],
            'this_week' => [$today->startOfWeek(CarbonInterface::MONDAY), $today->endOfWeek(CarbonInterface::SUNDAY)],
            'last_week' => [
                $today->subWeek()->startOfWeek(CarbonInterface::MONDAY),
                $today->subWeek()->endOfWeek(CarbonInterface::SUNDAY),
            ],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
            default => null,
        };

        return $range === null
            ? null
            : [$range[0]->format('Y-m-d'), $range[1]->format('Y-m-d')];
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
        $soloFormat = $this->inputFormat();

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
            'relative' => [
                'query' => function($query, $value, $property) {
                    $this->applyRelativeFilter($query, $property, $value[0] ?? null);
                }
            ],
            'my' => [
                'query' => function($query, $value, $property) {
                    $this->applyMonthYearFilter($query, $property, $value[0] ?? null, false);
                }
            ],
            'myn' => [
                'query' => function($query, $value, $property) {
                    $this->applyMonthYearFilter($query, $property, $value[0] ?? null, true);
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

        $value = is_array($value) && in_array($operation, ['bw', 'in', 'bmy'], true)
            ? [$value]
            : $this->splitDelimiterValues($value);

        if (!array_key_exists($operation, $filters)) {
            return;
        }

        $filters[$operation]['query']($query, $value, $property);
    }

    protected function defaultKey(): ?string
    {
        return 'date';
    }

    protected function validateExpressionCondition(string $operator, mixed $value): void
    {
        if (in_array($operator, ['null', 'nnull'], true)) {
            return;
        }

        if ($operator === 'relative') {
            if (! is_string($value) || $this->resolveRelativeRange($value) === null) {
                throw new InvalidArgumentException('Date filter operator "relative" contains an unknown preset.');
            }
            return;
        }

        if (in_array($operator, ['my', 'myn'], true)) {
            if ($operator === 'myn' && ($value === null || $value === 'null')) {
                return;
            }
            if (! is_string($value) || $this->parseMonthYear($value) === null) {
                throw new InvalidArgumentException('Date month/year filters require a value matching the configured month format.');
            }
            return;
        }

        if (in_array($operator, ['bw', 'bmy'], true)) {
            $range = $this->splitRangeValue($value);
            $valid = count($range) === 2;
            foreach ($range as $item) {
                $valid = $valid && is_string($item) && ($operator === 'bw'
                    ? $this->parseDate($item, $this->inputFormat()) !== null
                    : $this->parseMonthYear($item) !== null);
            }
            if (! $valid) {
                throw new InvalidArgumentException("Date filter operator \"{$operator}\" requires exactly two valid values.");
            }
            return;
        }

        if ($operator === 'in') {
            $values = $this->splitListValue($value);
            $format = $this->inputFormat();
            $invalid = $values === [] || array_filter(
                $values,
                fn (mixed $item): bool => ! is_string($item) || $this->parseDate($item, $format) === null,
            ) !== [];
            if ($invalid) {
                throw new InvalidArgumentException('Date filter operator "in" requires one or more valid dates.');
            }
            return;
        }

        $format = $this->inputFormat();
        if (! is_string($value) || $this->parseDate($value, $format) === null) {
            throw new InvalidArgumentException("Date filter operator \"{$operator}\" requires a valid {$format} date.");
        }
    }
}
