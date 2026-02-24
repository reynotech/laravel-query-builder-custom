<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Filters;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\Filters\DateFilter;
use ReynoTECH\QueryBuilderCustom\Filters\NumberAdvancedFilter;
use ReynoTECH\QueryBuilderCustom\Filters\StringAdvancedFilter;

final class AdvancedFiltersMysqlTest extends TestCase
{
    public function test_string_advanced_filter_edges_mysql(): void
    {
        $this->requireMysql();

        $schema = Capsule::schema();
        $table = 'filters_' . bin2hex(random_bytes(4));

        $schema->create($table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('note')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->date('event_date')->nullable();
            $table->json('meta')->nullable();
            $table->json('numbers')->nullable();
        });

        try {
            $this->seedTable($table);
            MysqlFilterModel::setTestTable($table);

            $filter = new StringAdvancedFilter();

            $this->assertSame(['Bob'], $this->applyFilter($filter, ['eq', 'Bob'], 'name'));
            $this->assertSame(['Alpha', 'Alina', 'Carl'], $this->applyFilter($filter, ['neq', 'Bob'], 'name'));
            $this->assertSame(['Alina'], $this->applyFilter($filter, ['con', 'li'], 'name'));
            $this->assertSame(['Alpha', 'Bob', 'Carl'], $this->applyFilter($filter, ['ncon', 'li'], 'name'));
            $this->assertSame(['Alpha', 'Alina'], $this->applyFilter($filter, ['bw', 'Al'], 'name'));
            $this->assertSame(['Alpha', 'Alina'], $this->applyFilter($filter, ['ew', 'a'], 'name'));
            $this->assertSame(['Bob', 'Carl'], $this->applyFilter($filter, ['nbw', 'Al'], 'name'));
            $this->assertSame(['Bob', 'Carl'], $this->applyFilter($filter, ['new', 'a'], 'name'));
            $this->assertSame(['Alpha', 'Bob'], $this->applyFilter($filter, ['in', 'Alpha,Bob'], 'name'));

            $this->assertSame(['Alpha', 'Alina'], $this->applyFilter($filter, ['e', ''], 'note'));
            $this->assertSame(['Alpha', 'Alina'], $this->applyFilter($filter, ['missing', ''], 'note'));
            $this->assertSame(['Alina', 'Bob', 'Carl'], $this->applyFilter($filter, ['ne', ''], 'note'));

            $this->assertSame(['Alpha', 'Alina'], $this->applyFilter($filter, ['con', 'Al'], 'meta->label'));
        } finally {
            $schema->dropIfExists($table);
        }
    }

    public function test_number_advanced_filter_edges_mysql(): void
    {
        $this->requireMysql();

        $schema = Capsule::schema();
        $table = 'filters_' . bin2hex(random_bytes(4));

        $schema->create($table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('note')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->date('event_date')->nullable();
            $table->json('meta')->nullable();
            $table->json('numbers')->nullable();
        });

        try {
            $this->seedTable($table);
            MysqlFilterModel::setTestTable($table);

            $filter = new NumberAdvancedFilter();

            $this->assertSame(['Bob'], $this->applyFilter($filter, ['eq', '20'], 'score'));
            $this->assertSame(['Alpha', 'Alina', 'Carl'], $this->applyFilter($filter, ['neq', '20'], 'score'));
            $this->assertSame(['Alpha'], $this->applyFilter($filter, ['lt', '15'], 'score'));
            $this->assertSame(['Alpha', 'Alina'], $this->applyFilter($filter, ['lte', '15'], 'score'));
            $this->assertSame(['Bob', 'Carl'], $this->applyFilter($filter, ['gt', '15'], 'score'));
            $this->assertSame(['Bob', 'Carl'], $this->applyFilter($filter, ['gte', '20'], 'score'));
            $this->assertSame(['Alpha', 'Alina', 'Bob'], $this->applyFilter($filter, ['bw', '10,20'], 'score'));
            $this->assertSame(['Alina'], $this->applyFilter($filter, ['bw', '15'], 'score'));
            $this->assertSame(['Alpha', 'Carl'], $this->applyFilter($filter, ['in', '10.50,30'], 'score'));

            $this->assertSame(['Alina'], $this->applyFilter($filter, ['eq', '7'], 'numbers->value'));
        } finally {
            $schema->dropIfExists($table);
        }
    }

    public function test_date_filter_edges_mysql(): void
    {
        $this->requireMysql();
        config(['app.date_format_solo' => 'd/m/Y']);

        $schema = Capsule::schema();
        $table = 'filters_' . bin2hex(random_bytes(4));

        $schema->create($table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('note')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->date('event_date')->nullable();
            $table->json('meta')->nullable();
            $table->json('numbers')->nullable();
        });

        try {
            $this->seedTable($table);
            MysqlFilterModel::setTestTable($table);

            $filter = new DateFilter();

            $this->assertSame(['Alpha'], $this->applyFilter($filter, ['eq', '10/02/2026'], 'event_date'));
            $this->assertSame(['Alina', 'Bob'], $this->applyFilter($filter, ['neq', '10/02/2026'], 'event_date'));
            $this->assertSame(['Alpha'], $this->applyFilter($filter, ['lt', '12/02/2026'], 'event_date'));
            $this->assertSame(['Alpha', 'Alina'], $this->applyFilter($filter, ['lte', '12/02/2026'], 'event_date'));
            $this->assertSame(['Bob'], $this->applyFilter($filter, ['gt', '12/02/2026'], 'event_date'));
            $this->assertSame(['Alina', 'Bob'], $this->applyFilter($filter, ['gte', '12/02/2026'], 'event_date'));
            $this->assertSame(['Alpha', 'Alina', 'Bob'], $this->applyFilter($filter, ['bw', '10/02/2026,15/02/2026'], 'event_date'));
            $this->assertSame(['Alina'], $this->applyFilter($filter, ['bw', '12/02/2026'], 'event_date'));
            $this->assertSame(['Alpha', 'Bob'], $this->applyFilter($filter, ['in', '10/02/2026,15/02/2026'], 'event_date'));
            $this->assertSame(['Alpha', 'Alina', 'Bob'], $this->applyFilter($filter, ['my', '02/2026'], 'event_date'));
            $this->assertSame(['Alpha', 'Alina', 'Bob'], $this->applyFilter($filter, ['bmy', '02/2026,02/2026'], 'event_date'));
            $this->assertSame(['Carl'], $this->applyFilter($filter, ['null', ''], 'event_date'));
            $this->assertSame(['Alpha', 'Alina', 'Bob'], $this->applyFilter($filter, ['nnull', ''], 'event_date'));
            $this->assertSame(['Alpha', 'Alina', 'Bob', 'Carl'], $this->applyFilter($filter, ['my', '2/2026'], 'event_date'));
            $this->assertSame(['Alpha', 'Alina', 'Bob', 'Carl'], $this->applyFilter($filter, ['bmy', '02/2026,2/2026'], 'event_date'));
            $this->assertSame(['Alpha', 'Alina', 'Bob', 'Carl'], $this->applyFilter($filter, ['eq', '31/02/2026'], 'event_date'));
        } finally {
            $schema->dropIfExists($table);
        }
    }

    public function test_custom_delimiter_and_separator_mysql(): void
    {
        $this->requireMysql();

        $schema = Capsule::schema();
        $table = 'filters_' . bin2hex(random_bytes(4));

        $schema->create($table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('note')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->date('event_date')->nullable();
            $table->json('meta')->nullable();
            $table->json('numbers')->nullable();
        });

        try {
            $this->seedTable($table);
            MysqlFilterModel::setTestTable($table);

            config([
                'query_builder_custom.filters.delimiter' => '::',
                'query_builder_custom.filters.separator' => ';',
            ]);

            $stringFilter = new StringAdvancedFilter();
            $numberFilter = new NumberAdvancedFilter();

            $this->assertSame(
                ['Alpha', 'Bob'],
                $this->applyFilter($stringFilter, 'in::Alpha;Bob', 'name')
            );

            $this->assertSame(
                ['Alpha', 'Alina', 'Bob'],
                $this->applyFilter($numberFilter, 'bw::10;20', 'score')
            );
        } finally {
            config([
                'query_builder_custom.filters.delimiter' => '|',
                'query_builder_custom.filters.separator' => ',',
            ]);
            $schema->dropIfExists($table);
        }
    }

    private function applyFilter(object $filter, mixed $value, string $property): array
    {
        $builder = MysqlFilterModel::query()->orderBy('id');
        $filter($builder, $value, $property);

        return $builder->pluck('name')->all();
    }

    private function seedTable(string $table): void
    {
        Capsule::table($table)->insert([
            [
                'name' => 'Alpha',
                'note' => null,
                'score' => '10.50',
                'event_date' => '2026-02-10',
                'meta' => json_encode(['label' => 'Alpha']),
                'numbers' => json_encode(['value' => 5]),
            ],
            [
                'name' => 'Alina',
                'note' => '',
                'score' => '15.00',
                'event_date' => '2026-02-12',
                'meta' => json_encode(['label' => 'Alina']),
                'numbers' => json_encode(['value' => 7]),
            ],
            [
                'name' => 'Bob',
                'note' => 'memo',
                'score' => '20.00',
                'event_date' => '2026-02-15',
                'meta' => json_encode(['label' => 'Bob']),
                'numbers' => json_encode(['value' => 20]),
            ],
            [
                'name' => 'Carl',
                'note' => 'note',
                'score' => '30.00',
                'event_date' => null,
                'meta' => json_encode(['label' => 'Carl']),
                'numbers' => json_encode(['value' => 25]),
            ],
        ]);
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
            $container = $capsule->getContainer();
            $container->instance('db', $capsule->getDatabaseManager());
            Facade::setFacadeApplication($container);

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

final class MysqlFilterModel extends Model
{
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
}
