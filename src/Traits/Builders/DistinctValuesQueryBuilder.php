<?php namespace ReynoTECH\QueryBuilderCustom\Traits\Builders;

use Closure;
use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DistinctValuesQueryBuilder extends Builder
{
    protected ?Closure $distinctPreQuery = null;
    protected string|object|null $distinctFilter = null;

    public function hasDistinctValues(?Closure $preQuery = null, string|object|null $filterer = null): static
    {
        if ($preQuery instanceof Closure) {
            $this->distinctPreQuery = $preQuery;
        }

        if ($filterer) {
            $this->distinctFilter = $filterer;
        }

        return $this;
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        $request = request();
        $fieldKey = (string) config('query_builder_custom.distinct.request_keys.field', '_dist');
        $field = $request->input($fieldKey);
        if (!$field) {
            return parent::paginate($perPage, $columns, $pageName, $page, $total);
        }

        if (!is_string($field) || $field === '') {
            abort(400, 'Invalid distinct field.');
        }

        $this->applyDistinctPreQuery();

        $internalName = $this->resolveDistinctInternalName($field);
        $valueAlias = $this->getDistinctValueAlias();
        $this->applyDistinctSelect($internalName, $valueAlias);

        $filterKey = (string) config('query_builder_custom.distinct.request_keys.filter', '_fdist');
        $this->applyDistinctFilter($field, $internalName, $request->input($filterKey));

        $cursorKey = (string) config('query_builder_custom.distinct.request_keys.cursor', '_dcur');
        $paginator = $this->cursorPaginate(
            perPage: $perPage ?? config('query_builder_custom.distinct.per_page', 50),
            cursorName: $cursorKey
        );
        $paginator->through(static fn ($item) => $item->{$valueAlias});

        return $paginator;
    }

    private function applyDistinctPreQuery(): void
    {
        if ($this->distinctPreQuery) {
            ($this->distinctPreQuery)($this);
        }
    }

    private function getDistinctValueAlias(): string
    {
        $alias = (string) config('query_builder_custom.distinct.value_alias', 'value');

        return $alias !== '' ? $alias : 'value';
    }

    private function resolveDistinctInternalName(string $field): string
    {
        [$filters] = $this->getModel()::tableQueryDefinitionFiltersNew('*');

        foreach ($filters as $filter) {
            if ($filter->getName() === $field) {
                return $filter->getInternalName() ?: $field;
            }
        }

        abort(400, 'Invalid distinct field.');
    }

    private function applyDistinctSelect(string $internalName, ?string $valueAlias = null): void
    {
        $valueAlias = $valueAlias ?: $this->getDistinctValueAlias();
        $orderBy = (string) config('query_builder_custom.distinct.order_by', $valueAlias);
        if ($orderBy === '') {
            $orderBy = $valueAlias;
        }

        $this->select($internalName . ' as ' . $valueAlias)
            ->distinct()
            ->reorder()
            ->orderBy($orderBy);
    }

    private function applyDistinctFilter(string $field, string $internalName, mixed $filter): void
    {
        if ($filter === null || $filter === '') {
            return;
        }

        $filterer = $this->resolveDistinctFilterer();
        $queryBuilder = new QueryBuilder($this);
        $value = $this->normalizeDistinctFilterValue($filter);

        AllowedFilter::custom($field, $filterer, $internalName)
            ->filter($queryBuilder, $value);
    }

    private function normalizeDistinctFilterValue(mixed $filter): mixed
    {
        $separator = (string) config(
            'query_builder_custom.distinct.filter_separator',
            config('query_builder_custom.filters.separator', ',')
        );

        if (is_string($filter) && $separator !== '' && str_contains($filter, $separator)) {
            return array_map('trim', explode($separator, $filter));
        }

        return $filter;
    }

    private function resolveDistinctFilterer(): object
    {
        $filterer = $this->distinctFilter ?? config(
            'query_builder_custom.distinct.default_filterer',
            new StringAdvancedFilter()
        );

        if ($filterer instanceof Closure) {
            $filterer = $filterer();
        } elseif (is_string($filterer)) {
            if (!class_exists($filterer)) {
                throw new \InvalidArgumentException("Distinct filterer class [$filterer] does not exist.");
            }
            $filterer = app($filterer);
        }

        if (!is_object($filterer)) {
            throw new \InvalidArgumentException('Distinct filterer must be an object.');
        }

        return $filterer;
    }
}
