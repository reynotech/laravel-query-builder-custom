<?php namespace ReynoTECH\QueryBuilderCustom\Traits;

use ReynoTECH\QueryBuilderCustom\Filters\DateFilter;
use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\Traits\Builders\DistinctValuesQueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilderRequest;

/*
$def = [
    'id', // defaults to sortable, StringFilter and no property name
    'name' => 'clients.name', // defaults to sortable, StringFilter and property name set
    'created_at' => [ // defaults only to sortable
        'filter' => new DateFilter
        'property' => 'clients.created_at'
    ],
    'roles' => [ // custom filter and no sortable, same property
        'filter' => new CallbackFilter(function ($query, $data) {
            $query->whereHas('roles', function ($q) use ($data) {
                $q->where('name', $data);
            })
        }),
        'sortable' => false
    ]
];

$table() = Current table, same field
$table('name') = Current table, different field
$table('name', Client::class) = Other table, different name
$table(table: Client::class) = Other table, same field
 */
trait HasQueryDefinition
{
    public static function bootHasQueryDefinition()
    {
        $delimiter = (string) config(
            'query_builder_custom.has_query_definition.array_value_delimiter',
            config('query_builder_custom.filters.delimiter', '|')
        );
        QueryBuilderRequest::setArrayValueDelimiter($delimiter !== '' ? $delimiter : '|');
    }

    public static function getQueryDefinitionInternalName($what)
    {
        $fields = static::tableQueryDefinitionAll('filters');

        foreach ($fields as $field) {
            if($field->getName() === $what) return $field->getInternalName();
        }

        return null;
    }

    public static function tableQueryDefinitionAll($what): array
    {
        $def = static::queryDefinitionOrFail();
        $base = $def[$what] ?? [];

        if (!is_array($base)) {
            throw new \UnexpectedValueException(static::class . "::queryDefinition() must define [$what] as an array.");
        }

        $addons = $def['addons'] ?? [];
        if (!is_array($addons)) {
            throw new \UnexpectedValueException(static::class . "::queryDefinition() addons must be an array.");
        }

        $all = [];
        foreach ($addons as $addon => $addonDef) {
            if (!is_array($addonDef)) {
                continue;
            }
            if (!array_key_exists($what, $addonDef) || !is_array($addonDef[$what])) {
                continue;
            }
            $all = array_merge($all, $addonDef[$what]);
        }

        return array_merge($base, $all);
    }

    public static function tableQueryDefinition($what, $add = []): array
    {
        $def = static::queryDefinitionOrFail();
        $base = $def[$what] ?? [];

        if (!is_array($base)) {
            throw new \UnexpectedValueException(static::class . "::queryDefinition() must define [$what] as an array.");
        }

        if (is_string($add)) {
            $addons = array_filter(array_map('trim', explode(',', $add)), static fn ($addon) => $addon !== '');

            $all = [];
            foreach ($addons as $addon) {
                if (!isset($def['addons']) || !is_array($def['addons']) || !array_key_exists($addon, $def['addons'])) {
                    throw new \InvalidArgumentException("Unknown addon [$addon].");
                }
                if (!array_key_exists($what, $def['addons'][$addon]) || !is_array($def['addons'][$addon][$what])) {
                    throw new \InvalidArgumentException("Addon [$addon] does not define [$what].");
                }
                $all = array_merge($all, $def['addons'][$addon][$what]);
            }

            return array_merge($base, $all);
        }

        if (!is_array($add)) {
            throw new \InvalidArgumentException('Addon definitions must be an array or comma-separated string.');
        }

        return array_merge($base, $add);
    }

    public static function tableQueryDefinitionFilters($add = []): array
    {
        return static::tableQueryDefinitionFiltersNew($add);
    }

    public static function tableQueryDefinitionSorts($add = []): array
    {
        return static::tableQueryDefinition('sorts', $add);
    }

    public static function tableQueryDefinitions($add = [])
    {
        return new QueryDefinitionAccessor(new static(), $add);
    }

//    abstract public function queryFilters($table) : array;


    /**
     * @return array{0: array, 1: array} [filters, sorts]
     */
    public static function tableQueryDefinitionFiltersNew($adds = [])
    {
        $tableName = (new static)->getTable();
        $getTableName = function ($field = null, $table = null) use($tableName) {
            if (!$field && !$table) return $tableName;
            else if ($field && !$table) return $tableName . '.' . $field;
            else {
                $tableRef = (new $table)->getTable();

                if ($table && !$field) return $tableRef;
                else return $tableRef . '.' . $field;
            }
        };

        $defs = method_exists(static::class, 'queryFilters') ? (new static)->queryFilters($getTableName) : [];
        if (!is_array($defs)) {
            throw new \UnexpectedValueException(static::class . '::queryFilters() must return an array.');
        }
        $filters = static::processFilters($defs);
        $sorts = static::processSorts($defs);

        $addonDefs = method_exists(static::class, 'queryAddons') ? (new static)->queryAddons($getTableName) : [];
        if (!is_array($addonDefs)) {
            throw new \UnexpectedValueException(static::class . '::queryAddons() must return an array.');
        }
        $addons = array_merge(
            static::resolveDefaultAddons(),
            $addonDefs
        );

        if ($adds === '*') {
            foreach ($addons as $addon) {
                $filters = array_merge($filters, static::processFilters($addon));
                $sorts = array_merge($sorts, static::processSorts($addon));
            }
        } else {
            if (is_string($adds)) {
                $adds = array_filter(array_map('trim', explode(',', $adds)), static fn ($add) => $add !== '');
            } elseif (!is_array($adds)) {
                throw new \InvalidArgumentException('Addon names must be an array, "*" or comma-separated string.');
            }

            foreach ($adds as $add) {
                if (!array_key_exists($add, $addons)) {
                    throw new \InvalidArgumentException("Unknown addon [$add].");
                }
                $filters = array_merge($filters, static::processFilters($addons[$add]));
                $sorts = array_merge($sorts, static::processSorts($addons[$add]));
            }
        }

        return [$filters, $sorts];
    }

    protected static function resolveDefaultAddons(): array
    {
        $configAddons = config('query_builder_custom.has_query_definition.addons', []);
        if (!is_array($configAddons) || $configAddons === []) {
            return [
                'dates' => [
                    'created_at' => ['filter' => DateFilter::class, 'sort' => true],
                    'updated_at' => ['filter' => DateFilter::class, 'sort' => true],
                ],
            ];
        }

        $addons = [];
        foreach ($configAddons as $name => $addon) {
            if (!is_array($addon)) {
                continue;
            }

            if (array_key_exists('enabled', $addon) && $addon['enabled'] === false) {
                continue;
            }

            $fields = $addon['fields'] ?? [];
            if (!is_array($fields) || $fields === []) {
                continue;
            }

            $filter = $addon['filter'] ?? DateFilter::class;
            $sort = $addon['sort'] ?? false;

            $defs = [];
            foreach ($fields as $field) {
                if (!is_string($field) || $field === '') {
                    continue;
                }
                $defs[$field] = [
                    'filter' => $filter,
                    'sort' => $sort,
                ];
            }

            if ($defs !== []) {
                $addons[$name] = $defs;
            }
        }

        return $addons;
    }

    /**
     * @param $defs
     * @return array
     */
    public static function processFilters($defs): array
    {
        $defaultFilter = new StringAdvancedFilter;
        $filters = [];

        foreach ($defs as $field => $def) {
            // is only field
            if (is_int($field) && is_string($def)) {
                $filters[] = AllowedFilter::custom($def, $defaultFilter);
            } // is field with internal name
            elseif (is_string($field) && is_string($def)) {
                $filters[] = AllowedFilter::custom($field, $defaultFilter, $def);
            } // field string and def array (multiple options)
            else {
                if (!is_array($def)) {
                    throw new \InvalidArgumentException("Filter definition for [$field] must be an array.");
                }
                $internal = array_key_exists('internal', $def) ? static::processInternalName($def['internal'], $field) : null;
                if (array_key_exists('filter', $def)) {
                    $interpret = is_string($def['filter']) && class_exists($def['filter']) ? new $def['filter'] : $def['filter'];
                    $filters[] = AllowedFilter::custom($field, $def['filter'] instanceof \Closure ? $def['filter']() : $interpret, $internal);
                } else if (array_key_exists('cfilter', $def)) {
                    $filters[] = AllowedFilter::callback($field, $def['cfilter'], $internal);
                } else {
                    $filters[] = AllowedFilter::custom($field, $defaultFilter, $internal);
                }
            }
        }
        return $filters;
    }

    public static function processSorts($defs): array
    {
        $sorts = [];

        foreach ($defs as $field => $def) {
            // default sortable for simple definitions
            if (is_int($field) && is_string($def)) {
                $sorts[] = $def;
                continue;
            }
            if (is_string($field) && is_string($def)) {
                $sorts[] = AllowedSort::field($field, $def);
                continue;
            }
            if (!is_array($def)) {
                continue;
            }

            $internal = array_key_exists('internal', $def) ? static::processInternalName($def['internal'], $field) : null;

            if (array_key_exists('csort', $def)) {
                $sorts[] = AllowedSort::callback($field, $def['csort']);
            } else if (array_key_exists('sort', $def)) {
                if ($def['sort']) {
                    $sorts[] = $internal ? AllowedSort::field($field, $internal) : $field;
                }
            }
        }

        return $sorts;
    }

    public static function processInternalName($name, $field)
    {
        if ($name instanceof \Closure) {
            $name = $name();
        }
        if (!is_string($name) || $name === '') {
            return null;
        }
        return !str_contains($name, '.')  ? $name . '.' . $field : $name;
    }

    /**
     * @return array{0: array, 1: array} [filters, sorts]
     */
    public static function queryDefinitionsFromArray($defs)
    {
        $filters = static::processFilters($defs);
        $sorts = static::processSorts($defs);

        return [$filters, $sorts];
    }

    protected static function queryDefinitionOrFail(): array
    {
        if (!method_exists(static::class, 'queryDefinition')) {
            throw new \BadMethodCallException(static::class . ' must implement queryDefinition().');
        }

        $def = (new static)->queryDefinition();
        if (!is_array($def)) {
            throw new \UnexpectedValueException(static::class . '::queryDefinition() must return an array.');
        }

        return $def;
    }

    public function newEloquentBuilder($query)
    {
        return new DistinctValuesQueryBuilder($query);
    }
}

class QueryDefinitionAccessor
{
    private object $model;
    private mixed $add;

    public function __construct(object $model, mixed $add)
    {
        $this->model = $model;
        $this->add = $add;
    }

    public function filters(): array
    {
        return $this->model->tableQueryDefinition('filters', $this->add);
    }

    public function sorts(): array
    {
        return $this->model->tableQueryDefinition('sorts', $this->add);
    }
}
