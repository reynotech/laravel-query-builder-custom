<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\Filters\Filter;

class DateFilter implements Filter
{
    protected $default = 'eq';

    public function getFilters()
    {
        $soloFormat = config('app.date_format_mysql_solo', '%d/%m/%y');

        return [
            'eq' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $query->whereRaw(
                        "DATE_FORMAT($property,'$soloFormat') = ?",
                        [$value[0]]
                    );
                }
            ],
            'bw' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $query->whereRaw(
                        "DATE_FORMAT($property,'$soloFormat') between ? and ?",
                        [$value[0], $value[1]]
                    );
                }
            ],
        ];
    }

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
        $filters = $this->getFilters();

        list($operation, $value) = $value;

        $value = explode('|', $value);

        if($filters[$operation]) {
            $operation = $filters[$operation];

            $operation['query']($query, $value, $property);
        }
    }
}
