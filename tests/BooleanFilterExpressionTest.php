<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\BooleanFilterExpression;

final class BooleanFilterExpressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'query_builder_custom.filters.boolean_expressions.max_depth' => 4,
            'query_builder_custom.filters.boolean_expressions.max_conditions' => 3,
            'query_builder_custom.filters.boolean_expressions.max_values_per_condition' => 3,
            'query_builder_custom.filters.boolean_expressions.max_value_length' => 20,
            'query_builder_custom.filters.boolean_expressions.max_payload_length' => 1000,
        ]);
    }

    public function test_validates_normalizes_and_round_trips_an_expression(): void
    {
        $expression = BooleanFilterExpression::fromPayload(json_encode([
            'type' => 'group',
            'operator' => 'OR',
            'children' => [
                ['type' => 'condition', 'operator' => 'EQ', 'value' => 'active'],
                ['type' => 'condition', 'op' => 'in', 'value' => ['paused', 'archived'], 'not' => true],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame([
            'type' => 'group',
            'operator' => 'or',
            'children' => [
                ['type' => 'condition', 'op' => 'eq', 'value' => 'active'],
                ['type' => 'condition', 'op' => 'in', 'value' => ['paused', 'archived'], 'not' => true],
            ],
        ], $expression->root());
        $this->assertSame($expression->root(), BooleanFilterExpression::fromEncoded($expression->encode())->root());
    }

    public function test_rejects_excessive_depth(): void
    {
        config(['query_builder_custom.filters.boolean_expressions.max_depth' => 2]);

        $this->expectException(InvalidArgumentException::class);
        BooleanFilterExpression::fromPayload([
            'type' => 'group',
            'children' => [[
                'type' => 'group',
                'children' => [['type' => 'condition', 'op' => 'eq', 'value' => 'active']],
            ]],
        ]);
    }

    public function test_rejects_excessive_conditions(): void
    {
        config(['query_builder_custom.filters.boolean_expressions.max_conditions' => 1]);

        $this->expectException(InvalidArgumentException::class);
        BooleanFilterExpression::fromPayload([
            'type' => 'group',
            'children' => [
                ['type' => 'condition', 'op' => 'eq', 'value' => 'active'],
                ['type' => 'condition', 'op' => 'eq', 'value' => 'paused'],
            ],
        ]);
    }

    public function test_rejects_excessive_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BooleanFilterExpression::fromPayload([
            'type' => 'condition',
            'op' => 'in',
            'value' => ['one', 'two', 'three', 'four'],
        ]);
    }

    public function test_rejects_oversized_json_payloads(): void
    {
        config(['query_builder_custom.filters.boolean_expressions.max_payload_length' => 10]);

        $this->expectException(InvalidArgumentException::class);
        BooleanFilterExpression::fromPayload('{"type":"condition","op":"eq","value":"active"}');
    }

    public function test_rejects_unknown_node_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BooleanFilterExpression::fromPayload([
            'type' => 'condition',
            'op' => 'eq',
            'value' => 'active',
            'field' => 'private_column',
        ]);
    }

    public function test_rejects_objects_as_condition_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BooleanFilterExpression::fromPayload([
            'type' => 'condition',
            'op' => 'eq',
            'value' => ['unexpected' => 'object'],
        ]);
    }
}
