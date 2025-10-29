<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\Filters\Filter;

class StringAdvancedFilter implements Filter
{
    protected string $default = 'con';

    protected ?Closure $closuredQuery = null;

    public function __construct(Closure $query = null)
    {
        $this->closuredQuery = $query;
    }

    public function getFilters()
    {
        $likeFn = fn($cond = 'LIKE', $par = null) => fn($query, $value, $property) => str_contains($property, '->') ?
            $query->whereRaw(
                "CAST({$query->getGrammar()->wrap($property)} as CHAR) {$cond} ?",
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
                'string' => ':col: IS NOT NULL OR :col: <> \'\'',
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
            ]
        ];
    }

    public function __invoke(Builder $query, $value, string $property): void
    {
        if (!is_array($value)) {
            $this->processQuery($query, [$this->default, $value], $property);
        } else {
            $this->processQuery($query, $value, $property);
        }
    }

    public function processQuery($query, $value, $property)
    {
        $filters = $this->getFilters();
        list($operation, $value) = $value;

        if($filters[$operation]) {
            $operation = $filters[$operation];

            if (array_key_exists('query', $operation)) {
                $operation['query']($query, $value, $property);
            } else {
                if (isset($operation['rawString'])) {
                    $occurrences = [
                        ':col:' => $property,
                    ];
                    $query->where(DB::raw(strtr($operation['string'], $occurrences)));
                } else if ($operation['op'] === 'in') {
                    $query->whereIn($property, explode(',', $value));
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
    }
}
