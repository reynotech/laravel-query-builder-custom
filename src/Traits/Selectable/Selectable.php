<?php namespace ReynoTECH\QueryBuilderCustom\Traits\Selectable;

trait Selectable
{
    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function newCollection(array $models = [])
    {
        return new SelectableCollection($models, get_class($this));
    }

    public function scopeToSelectPaginate($query, $perPage) {
        $results = $query->paginate($perPage);

        $real = [];

        $real['data'] = $results->toSelect();
        $real['has_more'] = $results->hasMorePages();
        $real['per_page'] = $results->perPage();
        $real['current_page'] = $results->currentPage();

        return $real;
    }
}
