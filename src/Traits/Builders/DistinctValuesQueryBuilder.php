<?php namespace ReynoTECH\QueryBuilderCustom\Traits\Builders;

use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DistinctValuesQueryBuilder extends Builder
{
    protected bool $hasDistinctValues = false;
    protected \Closure $distinctPreQuery;
    protected $distinctFilter = StringAdvancedFilter::class;

    public function hasDistinctValues($preQuery = false, $filterer = new StringAdvancedFilter): static
    {
        $this->hasDistinctValues = true;
        return $this;
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        $request = Request::capture();
        if ($request->input('_dist')) {
            $filterer = new StringAdvancedFilter;
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

    private function processDistinctValues()
    {

    }
}
