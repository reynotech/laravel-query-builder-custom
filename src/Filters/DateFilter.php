<?php namespace ReynoTECH\QueryBuilderCustom\Filters;

use DateTime;

class DateFilter extends BaseAdvancedFilter
{
    protected string $default = 'eq';

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
                    $value = str_contains($value[0], ',') ? explode(',', $value[0]) : $value[0];

                    $query->whereRaw(
                        "DATE({$query->getGrammar()->wrap($property)}) between ? and ?",
                        [
                            DateTime::createFromFormat($soloFormat, $value[0])->format('Y-m-d'),
                            DateTime::createFromFormat($soloFormat, $value[1])->format('Y-m-d')
                        ]
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

        $value = explode('|', $value);

        if (!array_key_exists($operation, $filters)) {
            return;
        }

        $filters[$operation]['query']($query, $value, $property);
    }
}
