<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\BooleanFilterExpression;
use ReynoTECH\QueryBuilderCustom\Filters\BaseAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\SpatieV2FilterConditions;
use ReynoTECH\QueryBuilderCustom\SpatieV2FilterRequestAdapter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\QueryBuilderRequest;

final class SpatieV2FilterRequestAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'query_builder_custom.filters.spatie_v2.enabled' => true,
            'query_builder_custom.filters.delimiter' => '|',
            'query_builder_custom.filters.separator' => ',',
            'query_builder_custom.filters.operator_aliases' => ['minimum' => 'gte'],
            'query-builder.parameters.filter' => 'filter',
        ]);
        QueryBuilderRequest::setFilterArrayValueDelimiter('|');
    }

    public function test_legacy_contract_is_left_unchanged(): void
    {
        $request = $this->request(['score' => 'gte|10']);

        $normalized = SpatieV2FilterRequestAdapter::normalize($request);

        $this->assertSame('gte|10', $normalized->input('filter.score'));
        $this->assertSame(['gte', '10'], QueryBuilderRequest::fromRequest($normalized)->filters()->get('score'));
    }

    public function test_normalizes_scalar_and_indexed_or_conditions_in_order(): void
    {
        $normalized = SpatieV2FilterRequestAdapter::normalize($this->request([
            'score|gte' => '10',
            'score|or:neq' => ['20', '30'],
        ]));

        $value = SpatieV2FilterConditions::fromEncoded(
            QueryBuilderRequest::fromRequest($normalized)->filters()->get('score')
        );

        $this->assertInstanceOf(SpatieV2FilterConditions::class, $value);
        $this->assertSame([
            ['join' => 'and', 'operator' => 'gte', 'value' => '10', 'index' => null],
            ['join' => 'or', 'operator' => 'neq', 'value' => '20', 'index' => 0],
            ['join' => 'or', 'operator' => 'neq', 'value' => '30', 'index' => 1],
        ], $value->all());
        $this->assertSame(['score'], QueryBuilderRequest::fromRequest($normalized)->filters()->keys()->all());
    }

    public function test_preserves_mixed_and_or_conditions_and_operator_aliases(): void
    {
        $normalized = SpatieV2FilterRequestAdapter::normalize($this->request([
            'score|minimum' => '10',
            'score|or:neq' => ['20', '30'],
            'score|lte' => '50',
        ]));

        /** @var SpatieV2FilterConditions $value */
        $value = SpatieV2FilterConditions::fromEncoded(
            QueryBuilderRequest::fromRequest($normalized)->filters()->get('score')
        );

        $this->assertSame([
            ['join' => 'and', 'operator' => 'minimum', 'value' => '10', 'index' => null],
            ['join' => 'or', 'operator' => 'neq', 'value' => '20', 'index' => 0],
            ['join' => 'or', 'operator' => 'neq', 'value' => '30', 'index' => 1],
            ['join' => 'and', 'operator' => 'lte', 'value' => '50', 'index' => null],
        ], $value->all());

        $filter = new RecordingAdvancedFilter();
        $query = $this->queryMockForConditions();
        $filter($query, $value, 'score');

        $this->assertSame([
            ['gte', '10'],
            ['neq', '20'],
            ['neq', '30'],
            ['lte', '50'],
        ], $filter->received);
    }

    public function test_keeps_scalar_in_and_bw_values_for_string_number_and_date_filters(): void
    {
        $normalized = SpatieV2FilterRequestAdapter::normalize($this->request([
            'name|bw' => 'Al',
            'score|in' => '10,20',
            'event_date|bw' => '10/02/2026,15/02/2026',
        ]));

        $filters = QueryBuilderRequest::fromRequest($normalized)->filters();

        $this->assertSame(
            [['join' => 'and', 'operator' => 'bw', 'value' => 'Al', 'index' => null]],
            SpatieV2FilterConditions::fromEncoded($filters->get('name'))->all(),
        );
        $this->assertSame(
            [['join' => 'and', 'operator' => 'in', 'value' => '10,20', 'index' => null]],
            SpatieV2FilterConditions::fromEncoded($filters->get('score'))->all(),
        );
        $this->assertSame(
            [['join' => 'and', 'operator' => 'bw', 'value' => '10/02/2026,15/02/2026', 'index' => null]],
            SpatieV2FilterConditions::fromEncoded($filters->get('event_date'))->all(),
        );
    }

    public function test_normalizes_a_boolean_expression_to_the_canonical_allowed_filter_name(): void
    {
        $payload = [
            'type' => 'group',
            'operator' => 'or',
            'children' => [
                ['type' => 'condition', 'op' => 'eq', 'value' => 'active'],
                ['type' => 'condition', 'op' => 'eq', 'value' => 'paused'],
            ],
        ];
        $normalized = SpatieV2FilterRequestAdapter::normalize($this->request([
            'status|expr' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]));

        $filters = QueryBuilderRequest::fromRequest($normalized)->filters();
        $expression = BooleanFilterExpression::fromEncoded($filters->get('status'));

        $this->assertSame(['status'], $filters->keys()->all());
        $this->assertInstanceOf(BooleanFilterExpression::class, $expression);
        $this->assertSame($payload, $expression->root());
    }

    public function test_rejects_mixing_a_boolean_expression_with_flat_conditions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SpatieV2FilterRequestAdapter::normalize($this->request([
            'status|expr' => json_encode([
                'type' => 'condition',
                'op' => 'eq',
                'value' => 'active',
            ], JSON_THROW_ON_ERROR),
            'status|neq' => 'archived',
        ]));
    }

    public function test_unknown_logical_filter_still_reaches_spatie_validation(): void
    {
        $request = SpatieV2FilterRequestAdapter::normalize($this->request(['private_score|gte' => '10']));
        $subject = $this->getMockBuilder(Builder::class)->disableOriginalConstructor()->getMock();

        $this->expectException(InvalidFilterQuery::class);

        QueryBuilder::for($subject, $request)->allowedFilters([AllowedFilter::custom('score', new RecordingAdvancedFilter())]);
    }

    public function test_unknown_boolean_expression_field_still_reaches_spatie_validation(): void
    {
        $request = SpatieV2FilterRequestAdapter::normalize($this->request([
            'private_status|expr' => json_encode([
                'type' => 'condition',
                'op' => 'eq',
                'value' => 'active',
            ], JSON_THROW_ON_ERROR),
        ]));
        $subject = $this->getMockBuilder(Builder::class)->disableOriginalConstructor()->getMock();

        $this->expectException(InvalidFilterQuery::class);

        QueryBuilder::for($subject, $request)->allowedFilters([
            AllowedFilter::custom('status', new RecordingAdvancedFilter()),
        ]);
    }

    public function test_custom_advanced_filters_without_an_operator_allowlist_reject_expressions(): void
    {
        $filter = new RecordingAdvancedFilter();
        $query = $this->queryMockForConditions();
        $expression = BooleanFilterExpression::fromPayload([
            'type' => 'condition',
            'op' => 'eq',
            'value' => 'active',
        ])->encode();

        $this->expectException(\ReynoTECH\QueryBuilderCustom\Exceptions\InvalidFilterOperator::class);
        $filter($query, $expression, 'status');
    }

    public function test_adapter_is_disabled_by_default_configuration(): void
    {
        config(['query_builder_custom.filters.spatie_v2.enabled' => false]);
        $request = $this->request(['score|gte' => '10']);

        $this->assertSame($request, SpatieV2FilterRequestAdapter::normalize($request));
    }

    private function request(array $filters): Request
    {
        return Request::create('/', 'GET', ['filter' => $filters]);
    }

    private function queryMockForConditions(): Builder
    {
        $query = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'orWhere'])
            ->getMock();

        // A nested group is the same kind of builder, so the conditions'
        // group and each condition inside it run against this one mock.
        $query->method('where')->willReturnCallback(function (callable $callback) use ($query) {
            $callback($query);
            return $query;
        });
        $query->method('orWhere')->willReturnCallback(function (callable $callback) use ($query) {
            $callback($query);
            return $query;
        });

        return $query;
    }
}

final class RecordingAdvancedFilter extends BaseAdvancedFilter
{
    /** @var list<array> */
    public array $received = [];

    public function processQuery($query, $value, $property): void
    {
        $this->received[] = $value;
    }
}
