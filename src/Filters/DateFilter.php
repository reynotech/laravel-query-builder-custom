<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\Filters\Filter;

class DateFilter implements Filter
{
    protected $default = 'eq';

    public function getFilters()
    {
        $soloFormat = config('app.date_format_solo', 'd/m/Y');

        return [
            'eq' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $query->whereRaw(
                        "DATE({$query->getGrammar()->wrap($property)}) = ?",
                        [DateTime::createFromFormat($soloFormat, $value[0])->format('Y-m-d')]
                    );
                }
            ],
            'bw' => [
                'query' => function($query, $value, $property) use ($soloFormat) {
                    $query->whereRaw(
                        "DATE({$query->getGrammar()->wrap($property)},'$soloFormat') between ? and ?",
                        [
                            DateTime::createFromFormat($soloFormat, $value[0])->format('Y-m-d'),
                            DateTime::createFromFormat($soloFormat, $value[1])->format('Y-m-d')
                        ]
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

        [$operation, $value] = $value;

        $value = explode('|', $value);

        if($filters[$operation]) {
            $operation = $filters[$operation];

            $operation['query']($query, $value, $property);
        }
    }
}
