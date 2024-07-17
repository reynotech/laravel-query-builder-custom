<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\Filters\Filter;

class NumberAdvancedFilter implements Filter
{
    protected $default = 'eq';

    protected $filters = [
        'eq' => [
            'op' => '='
        ],
        'bw' => [
            'string' => ':col: BETWEEN ? AND ?',
            'rawString' => true
        ],
    ];

    public function __invoke(Builder $query, $value, string $property)
    {
        if (!is_array($value)) {
            $this->processQuery($query, [$this->default, $value], $property);
        } else {
            $this->processQuery($query, $value, $property);
        }
    }

    public function processQuery($query, $value, $property)
    {
        list($operation, $value) = $value;

        if($this->filters[$operation]) {
            $operation = $this->filters[$operation];

            if (isset($operation['rawString'])) {
                $ocurrences = [
                    ':col:' => $property
                ];
                $query->where(DB::raw(strtr($operation['string'], $ocurrences)));
            } else {
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
