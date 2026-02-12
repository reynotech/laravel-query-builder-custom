<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NumberAdvancedFilter extends BaseAdvancedFilter
{
    protected string $default = 'eq';

    protected $filters = [
        'eq' => [
            'op' => '='
        ],
        'bw' => [
            'string' => ':col: BETWEEN ? AND ?',
            'rawString' => true
        ],
    ];

    public function processQuery($query, $value, $property)
    {
        [$operation, $value] = $value;

        if (!array_key_exists($operation, $this->filters)) {
            return;
        }

        $operation = $this->filters[$operation];

        if (isset($operation['rawString'])) {
            $ocurrences = [
                ':col:' => $property
            ];
            $query->where(DB::raw(strtr($operation['string'], $ocurrences)));
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
