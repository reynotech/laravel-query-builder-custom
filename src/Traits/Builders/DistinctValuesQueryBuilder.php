<?php namespace ReynoTECH\QueryBuilderCustom\Traits\Builders;

use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DistinctValuesQueryBuilder extends Builder
{
    protected ?\Closure $distinctPreQuery = null;
    protected $distinctFilter = null;

    public function hasDistinctValues($preQuery = null, $filterer = null): static
    {
        if ($preQuery instanceof \Closure) {
            $this->distinctPreQuery = $preQuery;
        }

        if ($filterer) {
            $this->distinctFilter = $filterer;
        }

        return $this;
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        $request = Request::capture();
        if ($request->input('_dist')) {
            if ($this->distinctPreQuery) {
                ($this->distinctPreQuery)($this);
            }

            $filterer = $this->distinctFilter;
            if (!$filterer) {
                $filterer = new StringAdvancedFilter;
            } else if (is_string($filterer) && class_exists($filterer)) {
                $filterer = new $filterer;
            }
            $field = $request->input('_dist');

            $filter = $request->input('_fdist');

            $getName = function ($field) {
                $filters = $this->getModel()::tableQueryDefinitionFiltersNew('*')[0];

                foreach ($filters as $filter) {
                    $filt = with($filter);
                    if ($filt->getName() === $field) return $filt->getInternalName();
                }
            };

            $name = $getName($field);
            
            $this->select($name . ' as value')
                ->distinct($name)
                ->reorder()
                ->orderBy('value');

            if ($filter) {
                $qq = with(new QueryBuilder($this));

                $val = str_contains($filter, ',') ? explode(',', $filter) : $filter;

                AllowedFilter::custom($field, $filterer, $name)
                    ->filter($qq, $val);
            }

            $q = $this
                ->cursorPaginate(perPage: 50, cursorName: '_dcur')->toArray();

            $q['data'] = collect($q['data'])->pluck('value');

            return response()->json($q);
        }


        return parent::paginate($perPage, $columns, $pageName, $page, $total);
    }
}
