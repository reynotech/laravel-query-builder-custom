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

    protected array $filters = [
        'eq' => [
            'op' => '='
        ],
        'neq' => [
            'op' => '<>'
        ],
        'con' => [
            'op' => 'LIKE',
            'string' => "%?%"
        ],
        'ncon' => [
            'op' => 'NOT LIKE',
            'string' => "%?%"
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
            'op' => 'LIKE',
            'string' => "?%"
        ],
        'ew' => [
            'op' => 'LIKE',
            'string' => "%?"
        ],
        'nbw' => [
            'op' => 'NOT LIKE',
            'string' => "?%"
        ],
        'new' => [
            'op' => 'NOT LIKE',
            'string' => "%?"
        ],
        'in' => [
            'op' => 'in'
        ]
    ];

    public function __construct(Closure $query = null)
    {
        $this->closuredQuery = $query;
    }

    public function __invoke(Builder $query, $value, string $property): void
    {
        if (!is_array($value)) {
            $this->processQuery($query, [$this->default, $value], $property);
        } else {
            $this->processQuery($query, $value, $property);
        }
    }

    public function processQuery($query, $value, $property): void
    {
        list($operation, $value) = $value;

        if($this->filters[$operation]) {
            $operation = $this->filters[$operation];

            if (isset($operation['rawString'])) {
                $ocurrences = [
                    ':col:' => $property
                ];
                $query->where(DB::raw(strtr($operation['string'], $ocurrences)));
            }
            else if ($operation['op'] === 'in') {
                $query->whereIn($property, explode(',', $value));
            }
            else
            {
                if(isset($operation['raw'])) {
                    $op = DB::raw($operation['op']);
                } else {
                    $op = $operation['op'];
                }

                if(isset($operation['string'])) {
                    $val = Str::replaceArray('?', [$value], $operation['string']);
                } else {
                    $val = $value;
                }

                $query->where($property, $op, $val);
            }
        }
    }
}
