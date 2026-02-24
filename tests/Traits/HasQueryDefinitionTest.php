<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Traits;

use BadMethodCallException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\Traits\HasQueryDefinition;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class HasQueryDefinitionTest extends TestCase
{
    public function test_process_sorts_accepts_string_definitions(): void
    {
        $defs = [
            'id',
            'name' => 'clients.name',
            'created_at' => ['sort' => true, 'internal' => 'clients'],
        ];

        $sorts = TestQueryDefinitionModel::processSorts($defs);

        $this->assertSame('id', $sorts[0]);
        $this->assertInstanceOf(AllowedSort::class, $sorts[1]);
        $this->assertInstanceOf(AllowedSort::class, $sorts[2]);
    }

    public function test_table_query_definition_all_merges_addons(): void
    {
        $filters = TestQueryDefinitionModel::tableQueryDefinitionAll('filters');

        $this->assertSame(['id', 'status', 'created_at'], $filters);
    }

    public function test_table_query_definition_unknown_addon_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestQueryDefinitionModel::tableQueryDefinition('filters', 'missing');
    }

    public function test_table_query_definition_filters_new_unknown_addon_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestQueryFiltersModel::tableQueryDefinitionFiltersNew('missing');
    }

    public function test_query_definition_missing_throws(): void
    {
        $this->expectException(BadMethodCallException::class);

        TestNoQueryDefinitionModel::tableQueryDefinitionAll('filters');
    }

    public function test_process_internal_name_returns_null_for_invalid(): void
    {
        $this->assertNull(TestQueryDefinitionModel::processInternalName(['invalid'], 'field'));
        $this->assertNull(TestQueryDefinitionModel::processInternalName(static fn () => null, 'field'));
    }

    public function test_process_filters_rejects_invalid_definition(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TestQueryDefinitionModel::processFilters(['name' => 123]);
    }

    public function test_mysql_filters_and_sorts_apply(): void
    {
        $capsule = $this->requireMysql();
        $schema = Capsule::schema();
        $table = 'query_definition_' . bin2hex(random_bytes(4));

        $schema->create($table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });

        try {
            Capsule::table($table)->insert([
                ['name' => 'Alice', 'status' => 'active'],
                ['name' => 'Alina', 'status' => 'active'],
                ['name' => 'Bob', 'status' => 'inactive'],
            ]);

            MysqlQueryDefinitionModel::setTestTable($table);

            [$filters, $sorts] = MysqlQueryDefinitionModel::tableQueryDefinitionFiltersNew('*');
            $builder = QueryBuilder::for(MysqlQueryDefinitionModel::query());

            $this->findFilter($filters, 'name')->filter($builder, ['con', 'Ali']);
            $this->findFilter($filters, 'status')->filter($builder, ['eq', 'active']);

            $this->applySort($sorts, 'name', $builder);

            $names = $builder->pluck('name')->all();

            $this->assertSame(['Alice', 'Alina'], $names);
        } finally {
            $schema->dropIfExists($table);
        }
    }

    private function requireMysql(): Capsule
    {
        $config = $this->mysqlConfig();
        if ($config === null) {
            $this->markTestSkipped('MySQL not configured. Set MYSQL_* or DB_* env vars.');
        }

        static $booted = false;
        static $capsule = null;

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

        return $capsule;
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

    private function findFilter(array $filters, string $name): AllowedFilter
    {
        foreach ($filters as $filter) {
            if ($filter instanceof AllowedFilter && $filter->getName() === $name) {
                return $filter;
            }
        }

        $this->fail("Filter [$name] not found.");
    }

    private function applySort(array $sorts, string $name, QueryBuilder $builder): void
    {
        foreach ($sorts as $sort) {
            if ($sort instanceof AllowedSort && $sort->getName() === $name) {
                $sort->sort($builder);
                return;
            }
            if (is_string($sort) && ltrim($sort, '-') === $name) {
                $builder->orderBy($name);
                return;
            }
        }

        $this->fail("Sort [$name] not found.");
    }
}

final class TestQueryDefinitionModel
{
    use HasQueryDefinition;

    public function getTable(): string
    {
        return 'tests';
    }

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
}

final class TestQueryFiltersModel
{
    use HasQueryDefinition;

    public function getTable(): string
    {
        return 'tests';
    }

    public function queryFilters(callable $table): array
    {
        return ['id'];
    }

    public function queryAddons(callable $table): array
    {
        return [
            'extra' => [
                'status' => ['sort' => true],
            ],
        ];
    }
}

final class TestNoQueryDefinitionModel
{
    use HasQueryDefinition;

    public function getTable(): string
    {
        return 'tests';
    }
}

final class MysqlQueryDefinitionModel extends Model
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
