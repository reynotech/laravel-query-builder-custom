<?php namespace ReynoTECH\QueryBuilderCustom\Traits\Selectable;

class SelectableCollection extends \Illuminate\Database\Eloquent\Collection
{
    public $class;

    public function __construct($items = [], $class = null)
    {
        parent::__construct($items);

        $this->class = $class;
    }

    public function toSelect($type = null)
    {
        if (!$this->items) return [];

        $class = $this->class ?? get_class($this->items[0]);


        return $this
            ->transform(function($m) use($class,$type){
                if($type) {
                    return with(new $class)->{'selector'.ucfirst($type)}($m);
                } else {
                    return with(new $class)->selector($m);
                }
            });
    }

    public function toSelectSingle($type = null)
    {
        $class = $this->class;

        return $this
            ->map(function($m) use($class,$type){
                if($type) {
                    return with(new $class)->{'selectorSingle'.ucfirst($type)}($m);
                } else {
                    return with(new $class)->selector($m);
                }
            });
    }

    public function toSelectArray()
    {
        return $this->toSelect()->toArray();
    }

    public function toDataContentAttribute()
    {
        $class = $this->class;

        return $this
            ->mapWithKeys(function($m) use($class){
                return with(new $class)->content($m);
            })->toArray();
    }
}
