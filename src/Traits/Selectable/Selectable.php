<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Traits\Selectable;

use Illuminate\Database\Eloquent\Builder;

trait Selectable
{
    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function newCollection(array $models = []): SelectableCollection
    {
        return new SelectableCollection($models, get_class($this));
    }

    public function scopeToSelectPaginate(Builder $query, int $perPage): array
    {
        $results = $query->paginate($perPage);

        return [
            'data' => $results->getCollection()->toSelect(),
            'has_more' => $results->hasMorePages(),
            'per_page' => $results->perPage(),
            'current_page' => $results->currentPage(),
        ];
    }
}
