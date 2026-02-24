<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Traits\Selectable;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class SelectableCollection extends EloquentCollection
{
    protected ?string $class = null;

    public function __construct($items = [], ?string $class = null)
    {
        parent::__construct($items);

        $this->class = $class;
    }

    public function toSelect(?string $type = null): Collection
    {
        if ($this->isEmpty()) {
            return new static([], $this->class);
        }

        $class = $this->resolveSelectableClass();
        if ($class === null) {
            return new static([], $this->class);
        }

        $selector = new $class();
        $method = $this->resolveSelectorMethod('selector', $type, 'selector');

        $items = $this->mapItems(fn($model) => $selector->$method($model));

        return new static($items, $class);
    }

    public function toSelectSingle(?string $type = null): Collection
    {
        $class = $this->resolveSelectableClass();
        if ($class === null || $this->isEmpty()) {
            return new Collection();
        }

        $selector = new $class();
        $method = $this->resolveSelectorMethod('selectorSingle', $type, 'selector');

        return new Collection($this->mapItems(fn($model) => $selector->$method($model)));
    }

    public function toSelectArray(): array
    {
        return $this->toSelect()->toArray();
    }

    public function toDataContentAttribute(): array
    {
        $class = $this->resolveSelectableClass();
        if ($class === null || $this->isEmpty()) {
            return [];
        }

        $selector = new $class();

        return $this
            ->mapWithKeys(fn($model) => $selector->content($model))
            ->toArray();
    }

    private function resolveSelectableClass(): ?string
    {
        if ($this->class !== null) {
            return $this->class;
        }

        $first = $this->first();

        return $first ? get_class($first) : null;
    }

    private function resolveSelectorMethod(string $base, ?string $type, ?string $fallback = null): string
    {
        if ($type !== null && $type !== '') {
            return $base . ucfirst($type);
        }

        return $fallback ?? $base;
    }

    private function mapItems(callable $callback): array
    {
        $mapped = [];

        foreach ($this->items as $key => $item) {
            $mapped[$key] = $callback($item, $key);
        }

        return $mapped;
    }
}
