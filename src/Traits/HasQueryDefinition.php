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
        QueryBuilderRequest::setArrayValueDelimiter('|');
    }

    public static function getQueryDefinitionInternalName($what)
    {
        $fields = self::tableQueryDefinitionAll('filters');

        foreach ($fields as $field) {
            if($field->getName() === $what) return $field->getInternalName();
        }
    }

    public static function tableQueryDefinitionAll($what): array
    {
        $def = with(new self)->queryDefinition();

        if (array_key_exists('addons', $def)) {
            $all = [];
            foreach (array_keys($def['addons']) as $addon) {
                $all = array_merge($all, $def['addons'][$addon][$what]);
            }

            return array_merge($def[$what], $all);
        }

        return array_merge($def[$what], $add);
    }

    public static function tableQueryDefinition($what, $add = []): array
    {
        $def = with(new self)->queryDefinition();

        if (is_string($add)) {
            $addons = explode(',', $add);

            $all = [];
            foreach ($addons as $addon) {
                $all = array_merge($all, $def['addons'][$addon][$what]);
            }

            return array_merge($def[$what], $all);
        }

        return array_merge($def[$what], $add);
    }

    public static function tableQueryDefinitionFilters($add = []): array
    {
        return self::tableQueryDefinitionFiltersNew($add);
    }

    public static function tableQueryDefinitionSorts($add = []): array
    {
        return self::tableQueryDefinition('sorts', $add);
    }

    public static function tableQueryDefinitions($add = [])
    {
        return new class($add, self::class) {
            private $add;
            private $old;

            public function __construct($add, $old)
            {
                $this->add = $add;
                $this->old = with(new $old);
            }

            public function filters()
            {
                return $this->old->tableQueryDefinition('filters', $this->add);
            }

            public function sorts()
            {
                return $this->old->tableQueryDefinition('sorts', $this->add);
            }
        };
    }

//    abstract public function queryFilters($table) : array;


    public static function tableQueryDefinitionFiltersNew($adds = [])
    {
        $tableName = with(new static)->getTable();
        $getTableName = function ($field = null, $table = null) use($tableName) {
            if (!$field && !$table) return $tableName;
            else if ($field && !$table) return $tableName . '.' . $field;
            else {
                $tableRef = with(new $table)->getTable();

                if ($table && !$field) return $tableRef;
                else return $tableRef . '.' . $field;
            }
        };

        $defs = with(new self)->queryFilters($getTableName);
        $filters = self::processFilters($defs);
        $sorts = self::processSorts($defs);

        $addons = array_merge([
                'dates' => [
                    'created_at' => ['filter' => new DateFilter(), 'sort' => true],
                    'updated_at' => ['filter' => new DateFilter(), 'sort' => true],
                ]
            ],
            method_exists(self::class, 'queryAddons') ? with(new self)->queryAddons($getTableName) : []
        );

        if ($adds === '*') {
            foreach ($addons as $addon) {
                $filters = array_merge($filters, self::processFilters($addon));
                $sorts = array_merge($sorts, self::processSorts($addon));
            }
        } else {
            if (is_string($adds)) {
                $adds = explode(',', $adds);
            }

            foreach ($adds as $add) {
                $filters = array_merge($filters, self::processFilters($addons[$add]));
                $sorts = array_merge($sorts, self::processSorts($addons[$add]));
            }
        }

        return [$filters, $sorts];
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
                $internal = array_key_exists('internal', $def) ? self::processInternalName($def['internal'], $field) : null;
                if (array_key_exists('filter', $def)) {
                    $filters[] = AllowedFilter::custom($field, $def['filter'] instanceof \Closure ? $def['filter']() : $def['filter'], $internal);
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
            if (array_key_exists('sort', $def)) {
                $internal = array_key_exists('internal', $def) ? self::processInternalName($def['internal'], $field) : null;

                if ($def['sort']) {
                    $sorts[] = $internal ? AllowedSort::field($field, $internal) : $field;
                }
            }
        }

        return $sorts;
    }

    public static function processInternalName($name, $field)
    {
        return !str_contains($name, '.')  ? $name.'.'.$field : $name;
    }

    public static function queryDefinitionsFromArray($defs)
    {
        $filters = self::processFilters($defs);
        $sorts = self::processSorts($defs);

        return [$filters, $sorts];
    }

    public function newEloquentBuilder($query)
    {
        return new DistinctValuesQueryBuilder($query);
    }
}
