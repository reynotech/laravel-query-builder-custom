<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Tests\Traits\Selectable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReynoTECH\QueryBuilderCustom\Traits\Selectable\Selectable;
use ReynoTECH\QueryBuilderCustom\Traits\Selectable\SelectableCollection;

final class SelectableCollectionTest extends TestCase
{
    public function test_to_select_returns_new_collection_without_mutation(): void
    {
        $model = new TestSelectableModel();
        $collection = $model->newCollection([
            new TestSelectableModel(['id' => 1, 'name' => 'Alpha']),
            new TestSelectableModel(['id' => 2, 'name' => 'Beta']),
        ]);

        $selected = $collection->toSelect();

        $this->assertInstanceOf(SelectableCollection::class, $selected);
        $this->assertSame(
            [
                ['id' => 1, 'name' => 'Alpha'],
                ['id' => 2, 'name' => 'Beta'],
            ],
            $selected->toArray()
        );

        $this->assertSame('Alpha', $collection->first()->name);
        $this->assertSame(2, $collection->count());
    }

    public function test_to_select_with_type_uses_typed_selector(): void
    {
        $model = new TestSelectableModel();
        $collection = $model->newCollection([
            new TestSelectableModel(['id' => 1, 'name' => 'Alpha']),
        ]);

        $selected = $collection->toSelect('foo');

        $this->assertSame([['value' => 'foo-Alpha']], $selected->toArray());
    }

    public function test_to_select_single_defaults_to_selector(): void
    {
        $model = new TestSelectableModel();
        $collection = $model->newCollection([
            new TestSelectableModel(['id' => 1, 'name' => 'Alpha']),
        ]);

        $selected = $collection->toSelectSingle();

        $this->assertInstanceOf(Collection::class, $selected);
        $this->assertSame([['id' => 1, 'name' => 'Alpha']], $selected->toArray());
    }

    public function test_to_select_single_with_type_uses_typed_single_selector(): void
    {
        $model = new TestSelectableModel();
        $collection = $model->newCollection([
            new TestSelectableModel(['id' => 1, 'name' => 'Alpha']),
        ]);

        $selected = $collection->toSelectSingle('foo');

        $this->assertSame(['single-foo-Alpha'], $selected->toArray());
    }

    public function test_to_select_array_and_content_attribute(): void
    {
        $model = new TestSelectableModel();
        $collection = $model->newCollection([
            new TestSelectableModel(['id' => 1, 'name' => 'Alpha']),
            new TestSelectableModel(['id' => 2, 'name' => 'Beta']),
        ]);

        $this->assertSame(
            [
                ['id' => 1, 'name' => 'Alpha'],
                ['id' => 2, 'name' => 'Beta'],
            ],
            $collection->toSelectArray()
        );

        $this->assertSame(
            [
                1 => 'Alpha',
                2 => 'Beta',
            ],
            $collection->toDataContentAttribute()
        );
    }

    public function test_empty_collection_returns_empty_outputs(): void
    {
        $model = new TestSelectableModel();
        $collection = $model->newCollection();

        $this->assertInstanceOf(SelectableCollection::class, $collection->toSelect());
        $this->assertSame([], $collection->toSelect()->toArray());
        $this->assertSame([], $collection->toSelectSingle()->toArray());
        $this->assertSame([], $collection->toDataContentAttribute());
    }

    public function test_scope_to_select_paginate_shape(): void
    {
        $model = new TestSelectableModel();
        $collection = $model->newCollection([
            new TestSelectableModel(['id' => 1, 'name' => 'Alpha']),
        ]);

        $paginator = new FakePaginator($collection, true, 15, 2);

        $builder = $this->createMock(Builder::class);
        $builder->method('paginate')->with(15)->willReturn($paginator);

        $result = $model->scopeToSelectPaginate($builder, 15);

        $this->assertSame(true, $result['has_more']);
        $this->assertSame(15, $result['per_page']);
        $this->assertSame(2, $result['current_page']);
        $this->assertSame([['id' => 1, 'name' => 'Alpha']], $result['data']->toArray());
    }
}

final class FakePaginator
{
    public function __construct(
        private SelectableCollection $collection,
        private bool $hasMore,
        private int $perPage,
        private int $currentPage
    ) {
    }

    public function getCollection(): SelectableCollection
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

final class TestSelectableModel extends Model
{
    use Selectable;

    public $timestamps = false;

    protected $guarded = [];

    public function selector(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
        ];
    }

    public function selectorFoo(Model $model): array
    {
        return [
            'value' => 'foo-' . $model->name,
        ];
    }

    public function selectorSingle(Model $model): string
    {
        return 'single-' . $model->name;
    }

    public function selectorSingleFoo(Model $model): string
    {
        return 'single-foo-' . $model->name;
    }

    public function content(Model $model): array
    {
        return [$model->id => $model->name];
    }
}
