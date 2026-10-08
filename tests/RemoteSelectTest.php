<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\RemoteSelect;
use ReynoTECH\QueryBuilderCustom\Traits\Selectable\Selectable;

final class RemoteSelectTest extends TestCase
{
    public function test_searches_only_declared_columns_and_returns_table_async_payload(): void
    {
        $nested = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()->onlyMethods(['orWhere'])->getMock();
        $searched = [];
        $nested->method('orWhere')->willReturnCallback(function ($column, $operator, $value) use (&$searched, $nested) {
            $searched[] = [$column, $operator, $value];
            return $nested;
        });

        $builder = $this->builder(new RemoteSelectPaginator(
            new EloquentCollection([new RemoteSelectModel(['id' => 'north', 'name' => 'Norte Componentes'])]),
            false,
            20,
            2,
        ));
        $builder->method('where')->willReturnCallback(function (callable $callback) use ($nested, $builder) {
            $callback($nested);
            return $builder;
        });

        $payload = RemoteSelect::paginate(
            $builder,
            ['search' => 'Norte', 'page' => 2, 'column' => 'password'],
            ['name', 'code'],
            20,
            'name',
            'id',
        );

        $this->assertSame([
            ['name', 'like', '%Norte%'],
            ['code', 'like', '%Norte%'],
        ], $searched);
        $this->assertSame([
            'data' => [['label' => 'Norte Componentes', 'value' => 'north']],
            'current_page' => 2,
            'has_more' => false,
            'per_page' => 20,
        ], $payload);
    }

    public function test_supports_page_empty_results_and_configured_label_value(): void
    {
        $builder = $this->builder(new RemoteSelectPaginator(new EloquentCollection(), false, 10, 3));

        $payload = RemoteSelect::paginate(
            $builder,
            ['page' => 3],
            ['display_name'],
            10,
            'display_name',
            'uuid',
        );

        $this->assertSame([], $payload['data']);
        $this->assertSame(3, $payload['current_page']);
        $this->assertFalse($payload['has_more']);
        $this->assertSame(10, $payload['per_page']);
    }

    public function test_selector_contract_is_reused_when_model_is_selectable(): void
    {
        $model = new RemoteSelectableModel();
        $collection = $model->newCollection([new RemoteSelectableModel(['code' => 'north', 'name' => 'Norte'])]);
        $builder = $this->builder(new RemoteSelectPaginator($collection, true, 15, 1));

        $payload = RemoteSelect::paginate($builder, [], ['name'], 15, 'text', 'key', 'remote');

        $this->assertSame([['label' => 'Norte', 'value' => 'north']], $payload['data']);
        $this->assertTrue($payload['has_more']);
    }

    public function test_rejects_non_whitelisted_search_column_definitions(): void
    {
        $builder = $this->getMockBuilder(Builder::class)->disableOriginalConstructor()->getMock();

        $this->expectException(InvalidArgumentException::class);

        RemoteSelect::paginate($builder, [], ['name', '']);
    }

    private function builder(RemoteSelectPaginator $paginator): Builder
    {
        $builder = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'paginate'])
            ->getMock();
        $builder->method('paginate')->willReturn($paginator);

        return $builder;
    }
}

final class RemoteSelectPaginator
{
    public function __construct(
        private EloquentCollection $collection,
        private bool $hasMore,
        private int $perPage,
        private int $currentPage,
    ) {
    }

    public function getCollection(): EloquentCollection
    {
        return $this->collection;
    }

    public function hasMorePages(): bool
    {
        return $this->hasMore;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }
}

final class RemoteSelectModel extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];
}

final class RemoteSelectableModel extends Model
{
    use Selectable;

    public $timestamps = false;

    protected $guarded = [];

    public function selectorRemote(Model $model): array
    {
        return ['text' => $model->name, 'key' => $model->code];
    }
}
