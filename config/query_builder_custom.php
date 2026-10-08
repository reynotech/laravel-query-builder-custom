<?php

return [
    // PHP date() tokens. Null retains legacy app.date_format_solo/app.date_format
    // defaults. Display, form values and filter payloads can vary independently.
    // DateFormats::frontendConfig() exports this contract for Quasar Utils.
    'dates' => [
        'date' => null,
        'dateTime' => null,
        'month' => null,
        'value' => ['date' => null, 'dateTime' => null, 'month' => null],
        'filter' => ['date' => null, 'dateTime' => null, 'month' => null],
    ],
    'filters' => [
        'delimiter' => '|',
        'separator' => ',',
        'defaults' => [
            'string' => 'con',
            'number' => 'eq',
            'date' => 'eq',
            'datetime' => 'eq',
            'select' => 'eq',
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
        'spatie_v2' => [
            // Enable only where QueryBuilder is given
            // SpatieV2FilterRequestAdapter::normalize($request).
            'enabled' => false,
        ],
        'boolean_expressions' => [
            'operator' => 'expr',
            'max_payload_length' => 65536,
            'max_depth' => 4,
            'max_conditions' => 25,
            'max_values_per_condition' => 100,
            'max_value_length' => 2000,
            'timezone' => null,
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
