<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StringAdvancedFilter extends BaseAdvancedFilter
{
    protected string $default = 'con';

    protected ?Closure $closuredQuery = null;

    public function __construct(?Closure $query = null)
    {
        $this->closuredQuery = $query;
    }

    public function getFilters()
    {
        $likeFn = fn($cond = 'LIKE', $par = null) => fn($query, $value, $property) => str_contains($property, '->') ?
            $query->whereRaw(
                "CAST({$query->getGrammar()->wrap($property)} as {$this->getJsonCastType()}) {$cond} ?",
                ['%' . $value . '%']
            ) : $query->where($property, $cond, $par ? $par($value) : '%' . $value . '%');

        return [
            'eq' => [
                'op' => '='
            ],
            'neq' => [
                'op' => '<>'
            ],
            'con' => [
                'query' => fn($query, $value, $property) => $likeFn()($query, $value, $property)
            ],
            'ncon' => [
                'query' => fn($query, $value, $property) => $likeFn('NOT LIKE')($query, $value, $property)
            ],
            'e' => [
                'string' => ':col: IS NULL OR :col: = \'\'',
                'rawString' => true
            ],
            'ne' => [
                'string' => ':col: IS NOT NULL AND :col: <> \'\'',
                'rawString' => true
            ],
            'missing' => [
                'string' => ':col: IS NULL OR :col: = \'\'',
                'rawString' => true
            ],
            // The names every other filter uses for empty and not empty.
            'null' => [
                'string' => ':col: IS NULL OR :col: = \'\'',
                'rawString' => true
            ],
            'nnull' => [
                'string' => ':col: IS NOT NULL AND :col: <> \'\'',
                'rawString' => true
            ],
            'bw' => [
                'query' => fn($query, $value, $property) => $likeFn(par: fn($v) => $v . '%')($query, $value, $property)
            ],
            'ew' => [
                'query' => fn($query, $value, $property) => $likeFn(par: fn($v) => '%' . $v)($query, $value, $property)
            ],
            'nbw' => [
                'query' => fn($query, $value, $property) => $likeFn('NOT LIKE', fn($v) => $v . '%')($query, $value, $property)
            ],
            'new' => [
                'query' => fn($query, $value, $property) => $likeFn('NOT LIKE', fn($v) => '%' . $v)($query, $value, $property)
            ],
            'in' => [
                'op' => 'in'
            ],
            'nin' => [
                'op' => 'not in'
            ]
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
        } else {
            if (isset($operation['rawString'])) {
                $column = $query->getGrammar()->wrap($property);
                if (str_contains($property, '->')) {
                    $column = "CAST({$column} as {$this->getJsonCastType()})";
                }
                $occurrences = [
                    ':col:' => $column,
                ];
                // Grouped: an OR inside must not reach the query's other constraints.
                $query->whereRaw('(' . strtr($operation['string'], $occurrences) . ')');
            } else if ($operation['op'] === 'in') {
                $query->whereIn($property, $this->splitListValue($value));
            } else if ($operation['op'] === 'not in') {
                $query->whereNotIn($property, $this->splitListValue($value));
            } else {
                if (isset($operation['raw'])) {
                    $op = DB::raw($operation['op']);
                } else {
                    $op = $operation['op'];
                }

                if (isset($operation['string'])) {
                    $val = Str::replaceArray('?', [$value], $operation['string']);
                } else {
                    $val = $value;
                }

                $query->where($property, $op, $val);
            }
        }
    }

    protected function defaultKey(): ?string
    {
        return 'string';
    }

    protected function validateExpressionCondition(string $operator, mixed $value): void
    {
        if (in_array($operator, ['e', 'ne', 'missing', 'null', 'nnull'], true)) {
            return;
        }

        if (in_array($operator, ['in', 'nin'], true)) {
            if ($this->splitListValue($value) === []) {
                throw new InvalidArgumentException("String filter operator \"{$operator}\" requires at least one value.");
            }
            return;
        }

        if (is_array($value)) {
            throw new InvalidArgumentException("String filter operator \"{$operator}\" requires a scalar value.");
        }
    }

    private function getJsonCastType(): string
    {
        $type = config('query_builder_custom.filters.json_casts.string', 'CHAR');

        if (!is_string($type) || $type === '') {
            return 'CHAR';
        }

        return $type;
    }
}
