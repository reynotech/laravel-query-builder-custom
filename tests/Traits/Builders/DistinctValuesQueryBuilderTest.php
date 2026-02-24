<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Traits\Builders;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\Traits\Builders\DistinctValuesQueryBuilder;
use ReynoTECH\QueryBuilderCustom\Traits\HasQueryDefinition;
use Spatie\QueryBuilder\AllowedFilter;

final class DistinctValuesQueryBuilderTest extends TestCase
{
    public function test_resolve_distinct_internal_name_returns_internal(): void
    {
        $builder = $this->makeBuilder(new TestDistinctModel());

        $result = $this->callPrivate($builder, 'resolveDistinctInternalName', ['name']);

        $this->assertSame('clients.name', $result);
    }

    public function test_resolve_distinct_internal_name_throws_on_missing_field(): void
    {
        $builder = $this->makeBuilder(new TestDistinctModel());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(400);

        $this->callPrivate($builder, 'resolveDistinctInternalName', ['missing']);
    }

    public function test_normalize_distinct_filter_value_splits_string(): void
    {
        $builder = $this->makeBuilder(new TestDistinctModel());

        $result = $this->callPrivate($builder, 'normalizeDistinctFilterValue', ['a,b']);

        $this->assertSame(['a', 'b'], $result);
    }

    public function test_normalize_distinct_filter_value_passthrough(): void
    {
        $builder = $this->makeBuilder(new TestDistinctModel());

        $result = $this->callPrivate($builder, 'normalizeDistinctFilterValue', [['a', 'b']]);

        $this->assertSame(['a', 'b'], $result);
    }

    public function test_resolve_distinct_filterer_default(): void
    {
        $builder = $this->makeBuilder(new TestDistinctModel());

        $result = $this->callPrivate($builder, 'resolveDistinctFilterer');

        $this->assertInstanceOf(StringAdvancedFilter::class, $result);
    }

    public function test_resolve_distinct_filterer_from_closure(): void
    {
        $builder = $this->makeBuilder(new TestDistinctModel());
        $builder->hasDistinctValues(filterer: static fn () => new StringAdvancedFilter());

        $result = $this->callPrivate($builder, 'resolveDistinctFilterer');

        $this->assertInstanceOf(StringAdvancedFilter::class, $result);
    }

    public function test_resolve_distinct_filterer_throws_for_missing_class(): void
    {
        $builder = $this->makeBuilder(new TestDistinctModel());
        $builder->hasDistinctValues(filterer: 'MissingFilterClass');

        $this->expectException(InvalidArgumentException::class);

        $this->callPrivate($builder, 'resolveDistinctFilterer');
    }

    public function test_resolve_distinct_filterer_throws_for_non_object(): void
    {
        $builder = $this->makeBuilder(new TestDistinctModel());
        $builder->hasDistinctValues(filterer: static fn () => 123);

        $this->expectException(InvalidArgumentException::class);

        $this->callPrivate($builder, 'resolveDistinctFilterer');
    }

    public function test_mysql_distinct_values_flow(): void
    {
        $this->requireMysql();

        $schema = Capsule::schema();
        $table = 'distinct_values_' . bin2hex(random_bytes(4));

        $schema->create($table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('status');
        });

        try {
            Capsule::table($table)->insert([
                ['name' => 'Alice', 'status' => 'active'],
                ['name' => 'Alina', 'status' => 'active'],
                ['name' => 'Bob', 'status' => 'inactive'],
            ]);

            MysqlDistinctModel::setTestTable($table);

            $builder = MysqlDistinctModel::query()->hasDistinctValues();
            $internal = $this->callPrivate($builder, 'resolveDistinctInternalName', ['name']);

            $this->assertSame($table . '.name', $internal);

            $this->callPrivate($builder, 'applyDistinctSelect', [$internal]);
            $this->callPrivate($builder, 'applyDistinctFilter', ['name', $internal, 'Ali']);

            $values = $builder->pluck('value')->all();

            $this->assertSame(['Alice', 'Alina'], $values);
        } finally {
            $schema->dropIfExists($table);
        }
    }

    private function makeBuilder(object $model): DistinctValuesQueryBuilder
    {
        $builder = $this->getMockBuilder(DistinctValuesQueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getModel'])
            ->getMock();

        $builder->method('getModel')->willReturn($model);

        return $builder;
    }

    private function callPrivate(object $object, string $method, array $args = [])
    {
        $caller = \Closure::bind(
            function () use ($method, $args) {
                return $this->$method(...$args);
            },
            $object,
            DistinctValuesQueryBuilder::class
        );

        return $caller();
    }

    private function requireMysql(): void
    {
        $config = $this->mysqlConfig();
        if ($config === null) {
            $this->markTestSkipped('MySQL not configured. Set MYSQL_* or DB_* env vars.');
        }

        static $booted = false;

        if (! $booted) {
            $capsule = new Capsule();
            $capsule->addConnection($config);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            try {
                Capsule::connection()->getPdo();
            } catch (\Throwable $e) {
                $this->markTestSkipped('MySQL not available: ' . $e->getMessage());
            }

            $booted = true;
        }
    }

    private function mysqlConfig(): ?array
    {
        $host = getenv('MYSQL_HOST') ?: getenv('DB_HOST');
        $port = getenv('MYSQL_PORT') ?: getenv('DB_PORT') ?: 3306;
        $database = getenv('MYSQL_DATABASE') ?: getenv('DB_DATABASE');
        $username = getenv('MYSQL_USERNAME') ?: getenv('DB_USERNAME');
        $password = getenv('MYSQL_PASSWORD') ?: getenv('DB_PASSWORD') ?: '';

        if (! $host || ! $database || ! $username) {
            return null;
        }

        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => (int) $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }
}

final class TestDistinctModel
{
    public static function tableQueryDefinitionFiltersNew($adds = []): array
    {
        return [[AllowedFilter::custom('name', new StringAdvancedFilter(), 'clients.name')], []];
    }
}

final class MysqlDistinctModel extends Model
{
    use HasQueryDefinition;

    public $timestamps = false;

    protected $guarded = [];

    protected static string $dynamicTable = 'tests';

    public static function setTestTable(string $table): void
    {
        static::$dynamicTable = $table;
    }

    public function getTable(): string
    {
        return static::$dynamicTable;
    }

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
}
