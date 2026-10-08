## ReynoTECH Laravel Query Builder Custom

Extensions and helpers for `spatie/laravel-query-builder`:
- Advanced string/number/date/select filters with consistent operators and boolean expressions
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

### Releasing a version

Composer reads stable versions from Git tags; `composer.json` does not need a `version` field.
Run the release script from this checkout:

```bash
composer release -- patch --dry-run
composer release -- patch
```

Use `major`, `minor`, or `patch`. With no existing version tag, `patch` starts at `v0.0.1`.
A normal release fetches remote tags, validates the package manifest with
`composer validate --no-check-lock` (consumer projects use their own lockfiles), runs PHPUnit, commits pending
changes, creates an annotated tag, and pushes the branch and tag together to `origin`.
The script refuses pre-staged changes and a branch that is behind or diverged from its remote.
PHPUnit's result cache and local agent files are excluded from the release commit.

Use `--no-push` to keep the commit and tag local; this mode uses only local tags.
`--dry-run` previews the release without tests, commits, tags, or network access. A normal
release fetches tags again, so its version may differ from an earlier preview.
Set `REMOTE` to use a remote other than `origin`.

### Features Overview
- **Advanced filters** for string, number, date, and select values with common operator keys.
- **Boolean expressions** with nested `AND`, `OR`, and `NOT`, validated per allowed field.
- **Query definition DSL** for filters and sorts (plus addons).
- **Distinct values API** via cursor pagination and optional filtering.
- **Selectable collections** to transform models into select-friendly payloads.
- **Casts** for dates and MongoDB `UTCDateTime` with configurable formats.

## Query Definitions (HasQueryDefinition)
Use the `HasQueryDefinition` trait to define filters/sorts once and reuse them when building `QueryBuilder`.

### Filter request contracts

The historical (default) contract is unchanged and keeps the operator in the value:

```txt
filter[score]=gte|10
```

An opt-in `spatie-v2` adapter also supports the operator in the filter key. Enable it in `config/query_builder_custom.php` and normalize the request before creating the Spatie builder:

```php
'filters' => [
    // ...
    'spatie_v2' => ['enabled' => true],
],

use ReynoTECH\QueryBuilderCustom\SpatieV2FilterRequestAdapter;

$builder = QueryBuilder::for(
    Client::class,
    SpatieV2FilterRequestAdapter::normalize(request()),
)->allowedFilters($filters);
```

The adapter reduces the key to the canonical allowed-filter name before Spatie validates it, so `filter[score|gte]` still requires an allowed filter named `score` (aliases and internal SQL names do not bypass that check). Operators continue to use `filters.delimiter`, `filters.separator`, and `filters.operator_aliases`.

```txt
# scalar condition
filter[score|gte]=10

# two OR conditions; indexed arrays preserve repeated values in PHP/Laravel
filter[score|or:neq][0]=20
filter[score|or:neq][1]=30
```

Use indexed arrays when the same `field|join:operator` key is repeated. Repeating an unindexed PHP query-string key can discard earlier values before Laravel sees the request. The adapter preserves each indexed item as an ordered condition; use the configured value separator for a single `in` or `bw` condition (for example `filter[score|in]=10,20`).

### Nested boolean expressions

For an advanced table filter, send one expression under `filter[field|expr]`. The value may be a JSON string or a Laravel-style nested array. Expressions are scoped to that one logical field; they cannot contain a field name or SQL fragment.

```php
$request = [
    'filter' => [
        'created_at|expr' => json_encode([
            'type' => 'group',
            'operator' => 'or',
            'children' => [
                ['type' => 'condition', 'op' => 'relative', 'value' => 'today'],
                ['type' => 'condition', 'op' => 'relative', 'value' => 'last_week'],
                [
                    'type' => 'condition',
                    'op' => 'eq',
                    'value' => '03/05/2007',
                    'not' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ],
];
```

There are two node types:

```php
// All children use the group's operator. A group can also be negated.
[
    'type' => 'group',
    'operator' => 'and', // and | or
    'children' => [/* condition or group nodes */],
    'not' => true,       // optional
]

// The operator must be supported by the concrete allowed filter.
[
    'type' => 'condition',
    'op' => 'eq',
    'value' => 'active',
    'not' => true,       // optional
]
```

Different logical fields remain separate Spatie filters and therefore combine with `AND`. A scalar status such as `active OR paused` should use an `or` group or the `in` operator. An `and` group is useful when the field can satisfy both predicates, such as `score >= 10 AND score <= 50`.

The adapter converts `field|expr` back to the canonical `field` before Spatie processes the request. Therefore an expression for `private_status` is rejected unless `private_status` is explicitly present in `allowedFilters()`. Expressions cannot be mixed with legacy or flat-v2 conditions for the same field.

Register select-backed fields with the dedicated filter, optionally constraining values on the server:

```php
use ReynoTECH\QueryBuilderCustom\Filters\SelectAdvancedFilter;

'status' => [
    'filter' => new SelectAdvancedFilter(['active', 'paused', 'archived']),
    'internal' => $table('status'),
],
```

The expression limits are configurable and are applied before compiling the query:

```php
'boolean_expressions' => [
    'operator' => 'expr',
    'max_payload_length' => 65536,
    'max_depth' => 4,
    'max_conditions' => 25,
    'max_values_per_condition' => 100,
    'max_value_length' => 2000,
    'timezone' => null, // falls back to app.timezone
],
```

Malformed ASTs, unsupported operators, invalid typed values, and disallowed select values throw `InvalidArgumentException`; they are not silently treated as an empty filter. The historical and flat-v2 contracts remain unchanged.

Custom classes derived from `BaseAdvancedFilter` must expose a `getFilters()` operator map or override `supportsExpressionOperator()` to opt operators into boolean expressions. Filters without an explicit operator allowlist fail closed for `field|expr`; this does not change their legacy or flat-v2 behavior.

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
- `relative` (`today`, `yesterday`, `last_7_days`, `last_30_days`, `this_week`, `last_week`, `this_month`, `last_month`, `this_year`, `last_year`)
- `my`, `myn`, `bmy` (month/year filters using `m/Y`)
- `null`, `nnull`

Examples (separator defaults to comma `,`):
- `filter[event_date]=eq|10/02/2026`
- `filter[event_date]=bw|10/02/2026,15/02/2026`
- `filter[event_date]=in|10/02/2026,15/02/2026`

Date parsing uses `query_builder_custom.dates.filter.date`, then `dates.date`, then
`config('app.date_format_solo', 'd/m/Y')`. Month operators use `dates.filter.month`, then
`dates.month`, then `m/Y`. Per-field overrides: `new DateFilter($format, $monthFormat)`.

### DateTimeFilter

`DateTimeFilter` supports the same operators and request encodings as `DateFilter`, while
comparing the full timestamp for `eq`, `neq`, comparisons, `bw` and `in`. It reads
`dates.filter.dateTime`, then `dates.dateTime`, then legacy `app.date_format`.
Relative periods continue to cover whole calendar days.

```php
use ReynoTECH\QueryBuilderCustom\Filters\DateTimeFilter;

AllowedFilter::custom('created_at', new DateTimeFilter());
```


### SelectAdvancedFilter

Operators:
- `eq`, `neq`
- `in`, `nin`
- `null`, `nnull`

Pass an allowed-values list to the constructor when the API owns a finite enum/status set. Omitting the list keeps the operation allowlist but accepts any scalar value.

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

## Remote async selects

The package does not register HTTP routes. Applications can connect their existing `POST /customers/search` controller to `RemoteSelect`; it reads only `search` and `page`. Search, label, and value columns are declared by the controller, never accepted as request column names.

### Remote entities (customers/users)

```php
use ReynoTECH\QueryBuilderCustom\RemoteSelect;

return response()->json(RemoteSelect::paginate(
    Customer::query(),
    $request,
    searchColumns: ['name', 'code'],
    perPage: 20,
    labelColumn: 'name',
    valueColumn: 'id',
));
```

It returns `data`, `current_page`, `has_more`, and `per_page`; each item is `{label, value}`. Configure the frontend with `optionLabel: 'label'` and `optionValue: 'value'`. If the model uses `Selectable`, pass `selector: 'remote'` (or `selector: true` for `selector()`) and set `labelColumn`/`valueColumn` to keys returned by that selector.

### Distinct values (status/provider)

For unique values of an allowed filter on the same table, keep the legacy `_dist`, `_fdist`, `_dcur` endpoint unchanged, or use the page-based helper in a remote controller:

```php
return response()->json(
    Order::query()->hasDistinctValues()->distinctSelectPaginate($request, 'status', 20)
);
```

`status` is validated through the model's logical allowed filters before its internal column is used. This is different from entity selects: distinct returns unique values from one allowed table field, while `RemoteSelect` searches remote customer/user records.

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

## Shared date configuration for PHP and Quasar

Set `dates` in the published `config/query_builder_custom.php` using PHP date tokens:

```php
'dates' => [
    'date' => 'm/d/Y',
    'dateTime' => 'm/d/Y h:i A',
    'month' => 'm/Y',
    'value' => ['date' => 'Y-m-d', 'dateTime' => 'Y-m-d H:i:s', 'month' => 'Y-m'],
    'filter' => ['date' => 'Y-m-d', 'dateTime' => 'Y-m-d H:i:s', 'month' => 'Y-m'],
],
```

Top-level patterns control frontend display; `value` controls cast form strings;
`filter` controls date/date-time/month filter payloads. Missing purpose-specific options
fall back to the top-level setting, then legacy `app.date_format_solo` / `app.date_format`
(or `m/Y` for months). Null defaults preserve existing applications and cast behavior.

Return the shared contract from an application-owned settings endpoint:

```php
use ReynoTECH\QueryBuilderCustom\DateFormats;

Route::get('/settings/date-formats', fn () => DateFormats::frontendConfig());
```

Load that JSON in a Quasar boot file and pass it directly to
`configureUtilsDates(await response.json())`. The exporter translates PHP numeric tokens
`Y y m n d j H G h g i s a A` and escaped literals into Day.js/Moment tokens. It rejects
unsupported tokens instead of silently generating an incompatible frontend format.
Backend-only patterns may use other PHP tokens. Two-digit years retain each engine's century
inference; use `Y` / `YYYY` in the shared contract to avoid ambiguity. Display, form values and filter payloads
can use different patterns: the example shows `07/08/2026` and sends `2026-07-08`.

`DateCast`, `DateTimeCast`, `MongoDateCast` and `MongoDateTimeCast` read `dates.value.*` by
default. Explicit cast arguments and existing `casts.*` format overrides take precedence.
SQL string parsing remains opt-in: use `DateCast::SETPARSED`,
`DateTimeCast::class . ':true'`, or set `casts.date.set_parsing_default` and
`casts.datetime.set_parsing_default` to `true` to parse incoming form strings on writes.
The exporter reflects the global contract; fields with cast-specific patterns need matching
frontend overrides. Storage formats are still separate cast options.

Display changes are reactive after `configureUtilsDates()` on the frontend. Changing
payload formats requires switching both sides and converting/reloading existing form values,
saved filters and URLs. Backend filters resolve global formats for each invocation; casts
resolve formats on construction, so recreate cached casts after runtime settings changes.

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

Global format precedence is `dates.value.date` / `dates.value.dateTime`, then `dates.date` /
`dates.dateTime`, then the legacy settings:
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
