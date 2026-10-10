<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\BooleanFilterExpression;
use ReynoTECH\QueryBuilderCustom\Exceptions\InvalidFilterOperator;
use ReynoTECH\QueryBuilderCustom\Filters\DateFilter;
use ReynoTECH\QueryBuilderCustom\Filters\NumberAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\Filters\SelectAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\SpatieV2FilterConditions;

final class BooleanExpressionsSqliteTest extends TestCase
{
    private string $table;

    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $container = $capsule->getContainer();
        $container->instance('db', $capsule->getDatabaseManager());
        Facade::setFacadeApplication($container);

        config([
            'app.date_format_solo' => 'd/m/Y',
            'app.timezone' => 'UTC',
            'query_builder_custom.filters.spatie_v2.enabled' => true,
            'query_builder_custom.filters.boolean_expressions.max_depth' => 4,
            'query_builder_custom.filters.boolean_expressions.max_conditions' => 25,
            'query_builder_custom.filters.boolean_expressions.max_values_per_condition' => 100,
            'query_builder_custom.filters.boolean_expressions.max_value_length' => 2000,
            'query_builder_custom.filters.boolean_expressions.timezone' => 'UTC',
        ]);

        $this->table = 'boolean_filters';
        Capsule::schema()->create($this->table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('status');
            $table->decimal('score', 10, 2);
            $table->date('event_date');
        });
        BooleanExpressionModel::setTestTable($this->table);
        Capsule::table($this->table)->insert([
            ['name' => 'Alpha', 'status' => 'active', 'score' => 10, 'event_date' => '2026-09-06'],
            ['name' => 'Beta', 'status' => 'paused', 'score' => 20, 'event_date' => '2026-08-28'],
            ['name' => 'Gamma', 'status' => 'archived', 'score' => 30, 'event_date' => '2007-05-03'],
            ['name' => 'Delta', 'status' => 'paused', 'score' => 40, 'event_date' => '2026-09-06'],
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_select_filter_supports_or_groups_and_in_lists(): void
    {
        $filter = new SelectAdvancedFilter(['active', 'paused', 'archived']);
        $orExpression = $this->expression([
            'type' => 'group',
            'operator' => 'or',
            'children' => [
                ['type' => 'condition', 'op' => 'eq', 'value' => 'active'],
                ['type' => 'condition', 'op' => 'eq', 'value' => 'paused'],
            ],
        ]);
        $inExpression = $this->expression([
            'type' => 'condition',
            'op' => 'in',
            'value' => ['active', 'paused'],
        ]);

        $this->assertSame(['Alpha', 'Beta', 'Delta'], $this->apply($filter, $orExpression, 'status'));
        $this->assertSame(['Alpha', 'Beta', 'Delta'], $this->apply($filter, $inExpression, 'status'));
    }

    public function test_or_conditions_stay_inside_the_constraints_already_on_the_query(): void
    {
        $conditions = (new SpatieV2FilterConditions([
            ['join' => 'and', 'operator' => 'eq', 'value' => 'active', 'index' => null],
            ['join' => 'or', 'operator' => 'eq', 'value' => 'archived', 'index' => null],
        ]))->encode();

        // A scope (a tenant, a visibility rule) is already on the query: the
        // `or` must not reach past it to Gamma, which the scope excludes.
        $query = BooleanExpressionModel::query()->where('score', '<', 25)->orderBy('id');
        (new SelectAdvancedFilter(['active', 'paused', 'archived']))($query, $conditions, 'status');

        $this->assertSame(['Alpha'], $query->pluck('name')->all());
    }

    public function test_a_whole_group_can_be_negated(): void
    {
        $expression = $this->expression([
            'type' => 'group',
            'operator' => 'or',
            'not' => true,
            'children' => [
                ['type' => 'condition', 'op' => 'eq', 'value' => 'active'],
                ['type' => 'condition', 'op' => 'eq', 'value' => 'archived'],
            ],
        ]);

        $this->assertSame(
            ['Beta', 'Delta'],
            $this->apply(new SelectAdvancedFilter(['active', 'paused', 'archived']), $expression, 'status'),
        );
    }

    public function test_nested_date_groups_support_relative_periods_and_not(): void
    {
        CarbonImmutable::setTestNow('2026-09-06 12:00:00 UTC');
        $expression = $this->expression([
            'type' => 'group',
            'operator' => 'and',
            'children' => [
                [
                    'type' => 'group',
                    'operator' => 'or',
                    'children' => [
                        ['type' => 'condition', 'op' => 'relative', 'value' => 'today'],
                        ['type' => 'condition', 'op' => 'relative', 'value' => 'last_week'],
                    ],
                ],
                ['type' => 'condition', 'op' => 'eq', 'value' => '03/05/2007', 'not' => true],
            ],
        ]);

        $this->assertSame(['Alpha', 'Beta', 'Delta'], $this->apply(new DateFilter(), $expression, 'event_date'));
    }

    public function test_number_and_string_filters_share_the_boolean_compiler(): void
    {
        $number = $this->expression([
            'type' => 'group',
            'operator' => 'and',
            'children' => [
                ['type' => 'condition', 'op' => 'gte', 'value' => 20],
                ['type' => 'condition', 'op' => 'eq', 'value' => 30, 'not' => true],
            ],
        ]);
        $string = $this->expression([
            'type' => 'group',
            'operator' => 'or',
            'children' => [
                ['type' => 'condition', 'op' => 'bw', 'value' => 'Al'],
                ['type' => 'condition', 'op' => 'eq', 'value' => 'Beta'],
            ],
        ]);

        $this->assertSame(['Beta', 'Delta'], $this->apply(new NumberAdvancedFilter(), $number, 'score'));
        $this->assertSame(['Alpha', 'Beta'], $this->apply(new StringAdvancedFilter(), $string, 'name'));
    }

    public function test_string_filter_takes_a_list_in_or_out(): void
    {
        $in = $this->expression(['type' => 'condition', 'op' => 'in', 'value' => ['Alpha', 'Gamma']]);
        $notIn = $this->expression(['type' => 'condition', 'op' => 'nin', 'value' => ['Alpha', 'Gamma']]);

        $this->assertSame(['Alpha', 'Gamma'], $this->apply(new StringAdvancedFilter(), $in, 'name'));
        $this->assertSame(['Beta', 'Delta'], $this->apply(new StringAdvancedFilter(), $notIn, 'name'));
    }

    public function test_number_filter_takes_a_list_out_and_empty_or_not(): void
    {
        $notIn = $this->expression(['type' => 'condition', 'op' => 'nin', 'value' => [10, 30]]);
        $notEmpty = $this->expression(['type' => 'condition', 'op' => 'nnull', 'value' => null]);

        $this->assertSame(['Beta', 'Delta'], $this->apply(new NumberAdvancedFilter(), $notIn, 'score'));
        $this->assertSame(['Alpha', 'Beta', 'Gamma', 'Delta'], $this->apply(new NumberAdvancedFilter(), $notEmpty, 'score'));
    }

    public function test_string_filter_reads_null_and_nnull_as_empty_and_not_empty(): void
    {
        Capsule::table($this->table)->insert([
            ['name' => '', 'status' => 'blank', 'score' => 50, 'event_date' => '2026-09-06'],
        ]);
        $empty = $this->expression(['type' => 'condition', 'op' => 'null', 'value' => null]);
        $notEmpty = $this->expression(['type' => 'condition', 'op' => 'nnull', 'value' => null]);
        $legacyNotEmpty = $this->expression(['type' => 'condition', 'op' => 'ne', 'value' => null]);

        $this->assertSame([''], $this->apply(new StringAdvancedFilter(), $empty, 'name'));
        $this->assertSame(['Alpha', 'Beta', 'Gamma', 'Delta'], $this->apply(new StringAdvancedFilter(), $notEmpty, 'name'));
        // "Not empty" used to let an empty string through.
        $this->assertSame(['Alpha', 'Beta', 'Gamma', 'Delta'], $this->apply(new StringAdvancedFilter(), $legacyNotEmpty, 'name'));
    }

    public function test_an_operator_named_in_the_key_that_the_column_lacks_is_a_bad_request(): void
    {
        $this->expectException(InvalidFilterOperator::class);
        $this->apply(new SelectAdvancedFilter(), (new SpatieV2FilterConditions([
            ['join' => 'and', 'operator' => 'con', 'value' => 'act', 'index' => null],
        ]))->encode(), 'status');
    }

    public function test_text_with_the_delimiter_in_it_is_read_whole(): void
    {
        Capsule::table($this->table)->insert([
            ['name' => 'A|B', 'status' => 'piped', 'score' => 60, 'event_date' => '2026-09-06'],
        ]);
        $query = BooleanExpressionModel::query();
        (new StringAdvancedFilter())($query, 'A|B', 'name');

        $this->assertSame(['A|B'], $query->pluck('name')->all());
    }

    public function test_rejects_operators_not_supported_by_the_concrete_filter(): void
    {
        // A malformed query, answered as 400.
        $this->expectException(InvalidFilterOperator::class);
        $this->apply(new SelectAdvancedFilter(), $this->expression([
            'type' => 'condition',
            'op' => 'contains',
            'value' => 'active',
        ]), 'status');
    }

    public function test_rejects_invalid_typed_values_instead_of_silently_skipping_them(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->apply(new DateFilter(), $this->expression([
            'type' => 'condition',
            'op' => 'eq',
            'value' => '31/02/2026',
        ]), 'event_date');
    }

    public function test_rejects_incomplete_numeric_ranges(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->apply(new NumberAdvancedFilter(), $this->expression([
            'type' => 'condition',
            'op' => 'bw',
            'value' => [20],
        ]), 'score');
    }

    public function test_rejects_select_values_outside_the_server_allowlist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->apply(new SelectAdvancedFilter(['active', 'paused']), $this->expression([
            'type' => 'condition',
            'op' => 'eq',
            'value' => 'archived',
        ]), 'status');
    }

    private function expression(array $payload): string
    {
        return BooleanFilterExpression::fromPayload($payload)->encode();
    }

    private function apply(object $filter, string $expression, string $property): array
    {
        $query = BooleanExpressionModel::query()->orderBy('id');
        $filter($query, $expression, $property);

        return $query->pluck('name')->all();
    }
}

final class BooleanExpressionModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static string $dynamicTable = 'boolean_filters';

    public static function setTestTable(string $table): void
    {
        static::$dynamicTable = $table;
    }

    public function getTable(): string
    {
        return static::$dynamicTable;
    }
}
