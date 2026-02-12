<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

abstract class BaseDateCast implements CastsAttributes
{
    protected $setParsing;
    protected $getFormat;
    protected $setFormat;
    protected $storageFormat;
    protected $configKey;
    protected $configFallback;

    public function __construct($setParsing = false, $getFormat = false, $setFormat = false)
    {
        $this->setParsing = (bool) $setParsing;
        $this->getFormat = $getFormat ?: config($this->configKey, $this->configFallback);
        $this->setFormat = $setFormat ?: config($this->configKey, $this->configFallback);
    }

    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, $key, $value, $attributes)
    {
        if (in_array($key, $model->getDates())) {
            return $value ?
                $this->formatDate(
                    date($this->storageFormat, strtotime($value)),
                    $this->storageFormat,
                    $this->getFormat
                ) : null;
        }

        return $this->formatDate($value, $this->storageFormat, $this->getFormat);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  array  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, $key, $value, $attributes)
    {
        if ($this->setParsing) {
            return $this->formatDate($value, $this->setFormat, $this->storageFormat);
        }

        return $value;
    }

    abstract public function formatDate($date, $originFormat, $toFormat = false);
}
