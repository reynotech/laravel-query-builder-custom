<?php

return [
    'filters' => [
        'delimiter' => '|',
        'separator' => ',',
        'defaults' => [
            'string' => 'con',
            'number' => 'eq',
            'date' => 'eq',
        ],
        'json_casts' => [
            'string' => 'CHAR',
            'number' => [
                'type' => 'DECIMAL',
                'precision' => 20,
                'scale' => 6,
            ],
        ],
        'operator_aliases' => [
            // 'contains' => 'con',
            // 'not_contains' => 'ncon',
            // 'before' => 'lt',
            // 'after' => 'gt',
        ],
    ],
    'distinct' => [
        'request_keys' => [
            'field' => '_dist',
            'filter' => '_fdist',
            'cursor' => '_dcur',
        ],
        'per_page' => 50,
        'value_alias' => 'value',
        'default_filterer' => ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter::class,
        'order_by' => 'value',
        'filter_separator' => ',',
    ],
    'has_query_definition' => [
        'addons' => [
            'dates' => [
                'enabled' => true,
                'fields' => ['created_at', 'updated_at'],
                'filter' => ReynoTECH\QueryBuilderCustom\Filters\DateFilter::class,
                'sort' => true,
            ],
        ],
        'array_value_delimiter' => '|',
    ],
    'casts' => [
        'date' => [
            'storage_format' => 'Y-m-d',
            'get_format' => null,
            'set_format' => null,
            'set_parsing_default' => false,
        ],
        'datetime' => [
            'storage_format' => 'Y-m-d H:i:s',
            'get_format' => null,
            'set_format' => null,
            'set_parsing_default' => false,
        ],
        'mongo' => [
            'set_parsing_default' => true,
        ],
    ],
];
