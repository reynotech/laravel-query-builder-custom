<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Builds the payload consumed by remote async selects without owning routes.
 * Columns are supplied by application code, never read from the request.
 */
final class RemoteSelect
{
    /**
     * @param array<int, string> $searchColumns Allowed model columns to search.
     * @return array{data: array<int, array{label: mixed, value: mixed}>, current_page: int, has_more: bool, per_page: int}
     */
    public static function paginate(
        Builder $query,
        Request|array $request,
        array $searchColumns,
        int $perPage = 50,
        string $labelColumn = 'name',
        string $valueColumn = 'id',
        string|bool|null $selector = null,
    ): array {
        self::assertColumns($searchColumns);
        [$search, $page] = self::requestValues($request);

        if ($search !== '' && $searchColumns !== []) {
            $query->where(function (Builder $nested) use ($searchColumns, $search): void {
                foreach ($searchColumns as $column) {
                    $nested->orWhere($column, 'like', '%' . self::escapeLike($search) . '%');
                }
            });
        }

        $paginator = $query->paginate(max(1, $perPage), ['*'], 'page', $page);

        return self::paginatorResponse($paginator, function ($collection) use ($labelColumn, $valueColumn, $selector): array {
            if ($selector !== null) {
                if (! method_exists($collection, 'toSelect')) {
                    throw new InvalidArgumentException('A selector requires a model using the Selectable trait.');
                }

                $collection = $collection->toSelect($selector === true ? null : $selector);
            }

            return $collection->map(static fn ($item) => [
                'label' => data_get($item, $labelColumn),
                'value' => data_get($item, $valueColumn),
            ])->values()->all();
        });
    }

    /** @return array{0: string, 1: int} */
    public static function requestValues(Request|array $request): array
    {
        $input = $request instanceof Request
            ? static fn (string $key, mixed $default) => $request->input($key, $default)
            : static fn (string $key, mixed $default) => $request[$key] ?? $default;

        $search = $input('search', '');
        $page = $input('page', 1);

        return [is_scalar($search) ? trim((string) $search) : '', is_numeric($page) ? max(1, (int) $page) : 1];
    }

    /** @return array{data: array, current_page: int, has_more: bool, per_page: int} */
    public static function paginatorResponse(object $paginator, callable $map): array
    {
        return [
            'data' => $map($paginator->getCollection()),
            'current_page' => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /** @param array<int, string> $columns */
    private static function assertColumns(array $columns): void
    {
        foreach ($columns as $column) {
            if (! is_string($column) || $column === '') {
                throw new InvalidArgumentException('Remote select search columns must be non-empty strings.');
            }
        }
    }

    private static function escapeLike(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    }
}
