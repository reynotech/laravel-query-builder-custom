## ReynoTECH Laravel Query Builder Custom

Extensions and helpers for `spatie/laravel-query-builder`:
- Advanced string/number/date filters with consistent operators
- Query definition helpers (`HasQueryDefinition`)
- Distinct values pagination (`DistinctValuesQueryBuilder`)
- Selectable collections for UI payloads
- Date and Mongo date casts

### Requirements
- PHP 8.2+
- `spatie/laravel-query-builder` ^6

### Installation
```bash
composer require reynotech/laravel-query-builder-custom
```

### Features Overview
- **Advanced filters** for string, number, and date with common operator keys.
- **Query definition DSL** for filters and sorts (plus addons).
- **Distinct values API** via cursor pagination and optional filtering.
- **Selectable collections** to transform models into select-friendly payloads.
- **Casts** for dates and MongoDB `UTCDateTime` with configurable formats.

## Query Definitions (HasQueryDefinition)
Use the `HasQueryDefinition` trait to define filters/sorts once and reuse them when building `QueryBuilder`.

### Option A: `queryFilters` + `queryAddons`
```php
use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\Traits\HasQueryDefinition;

class Client extends Model
{
    use HasQueryDefinition;

    public function queryFilters(callable $table): array
    {
        return [
            'name' => [
                'filter' => StringAdvancedFilter::class,
                'sort' => true,
                'internal' => $table('name'),
            ],
            'status' => [
                'filter' => StringAdvancedFilter::class,
                'internal' => $table('status'),
            ],
        ];
    }

    public function queryAddons(callable $table): array
    {
        return [
            'extra' => [
                'role' => [
                    'filter' => StringAdvancedFilter::class,
                    'internal' => $table('role'),
                ],
            ],
        ];
    }
}
```

Build filters/sorts:
```php
use Spatie\QueryBuilder\QueryBuilder;

[$filters, $sorts] = Client::tableQueryDefinitionFiltersNew('*');

$builder = QueryBuilder::for(Client::class)
    ->allowedFilters($filters)
    ->allowedSorts($sorts);
```

Notes:
- Addon `dates` is included by default and adds `created_at`/`updated_at` using `DateFilter` (configurable).
- `HasQueryDefinition::bootHasQueryDefinition()` sets the array delimiter using `query_builder_custom.has_query_definition.array_value_delimiter` (default `|`), so `filter[name]=op|value` maps to `['op','value']`.
- `$table()` helps resolve internal table/field names:
  - `$table()` current table
  - `$table('field')` current table, different field
  - `$table('field', Other::class)` another table

### Option B: `queryDefinition`
If you prefer a single array:
```php
public function queryDefinition(): array
{
    return [
        'filters' => ['id', 'status'],
        'sorts' => ['id'],
        'addons' => [
            'dates' => [
                'filters' => ['created_at'],
                'sorts' => ['created_at'],
            ],
        ],
    ];
}
```
Use `tableQueryDefinition`, `tableQueryDefinitionAll`, or `tableQueryDefinitions()` as needed.

## Filters
Filters expect values in the form `filter[field]=op|value` (using the `|` array delimiter).  
For `in` and `bw`, use comma-separated lists (configurable via `query_builder_custom.filters.separator`).
Global operator aliases can be configured via `query_builder_custom.filters.operator_aliases`.

### StringAdvancedFilter
Operators:
- `eq`, `neq`
- `con`, `ncon` (contains / not contains)
- `bw`, `ew` (begins / ends with)
- `nbw`, `new` (not begins / not ends with)
- `e`, `ne`, `missing` (null/empty checks)
- `in`

Examples:
- `filter[name]=con|Ali`
- `filter[name]=in|Alice,Bob`
- `filter[note]=missing|`

JSON path columns (`meta->label`) are supported and wrapped/cast for `LIKE` and raw checks.

### NumberAdvancedFilter
Operators:
- `eq`, `neq`
- `lt`, `lte`, `gt`, `gte`
- `bw` (between, comma-separated)
- `in`

Examples (separator defaults to comma `,`):
- `filter[score]=gte|10`
- `filter[score]=bw|10,20`
- `filter[numbers->value]=eq|7`

JSON path columns are cast to `DECIMAL(20, 6)` for comparisons.

### DateFilter
Operators:
- `eq`, `neq`
- `lt`, `lte`, `gt`, `gte`
- `bw` (between, comma-separated)
- `in`
- `null`, `nnull`

Examples (separator defaults to comma `,`):
- `filter[event_date]=eq|10/02/2026`
- `filter[event_date]=bw|10/02/2026,15/02/2026`
- `filter[event_date]=in|10/02/2026,15/02/2026`

Date parsing uses `config('app.date_format_solo', 'd/m/Y')`.

## Distinct Values Pagination
`HasQueryDefinition` overrides the model builder to `DistinctValuesQueryBuilder`, enabling a distinct-values API.

Usage:
```php
Model::query()->hasDistinctValues();
```

Request parameters:
- `_dist` = field name to get distinct values for
- `_fdist` = optional filter value (uses the same filterer)
- `_dcur` = cursor name for pagination

Example:
```
GET /clients?_dist=status&_fdist=con|act
```

You can customize:
```php
Model::query()->hasDistinctValues(
    preQuery: fn($q) => $q->where('active', 1),
    filterer: \ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter::class
);
```

## Selectable Collections
Use the `Selectable` trait to provide select-friendly data from Eloquent collections.

```php
use ReynoTECH\QueryBuilderCustom\Traits\Selectable\Selectable;

class Client extends Model
{
    use Selectable;

    public function selector(Model $model): array
    {
        return ['id' => $model->id, 'name' => $model->name];
    }

    public function selectorFoo(Model $model): array
    {
        return ['value' => 'foo-' . $model->name];
    }

    public function selectorSingle(Model $model): string
    {
        return $model->name;
    }

    public function selectorSingleFoo(Model $model): string
    {
        return 'foo-' . $model->name;
    }

    public function content(Model $model): array
    {
        return [$model->id => $model->name];
    }
}
```

Collection helpers:
- `toSelect()` / `toSelect('foo')`
- `toSelectSingle()` / `toSelectSingle('foo')`
- `toSelectArray()`
- `toDataContentAttribute()`

Pagination helper:
```php
Client::query()->toSelectPaginate(15);
```
Returns:
```php
[
  'data' => Collection,
  'has_more' => bool,
  'per_page' => int,
  'current_page' => int,
]
```

## Casts
Date and MongoDB date casts with configurable formats.

### DateCast / DateTimeCast
```php
use ReynoTECH\QueryBuilderCustom\Casts\DateCast;
use ReynoTECH\QueryBuilderCustom\Casts\DateTimeCast;

protected $casts = [
    'date' => DateCast::class,
    'published_at' => DateTimeCast::class,
];
```

Constants:
- `DateCast::SETPARSED`
- `DateCast::SETONLYMONTHYEAR`
- `DateTimeCast::TOONLYDATE`

Formats:
- `app.date_format_solo` (default `d/m/y`)
- `app.date_format` (default `d/m/y h:i A`)
Optional cast overrides:
- `query_builder_custom.casts.date.*`
- `query_builder_custom.casts.datetime.*`

### MongoDateCast / MongoDateTimeCast
MongoDB `UTCDateTime` equivalents:
- `MongoDateCast::SETPARSED`
- `MongoDateCast::SETONLYMONTHYEAR`
- `MongoDateTimeCast::TOONLYDATE`
Optional cast overrides:
- `query_builder_custom.casts.mongo.*` (merged with date/datetime settings)

## Testing
```bash
composer test
```

MySQL-backed tests (set `MYSQL_*` or `DB_*` env vars):
```bash
composer test:mysql
```

## Configuration
Set optional overrides:
```php
config([
    'query_builder_custom.filters.delimiter' => '|',
    'query_builder_custom.filters.separator' => ',',
    'query_builder_custom.filters.operator_aliases' => [
        // 'contains' => 'con',
        // 'before' => 'lt',
    ],
]);
```
Default values live in `config/query_builder_custom.php`.

Publish the config in Laravel:
```bash
php artisan vendor:publish --tag=query-builder-custom-config
```
