<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class DateTimeCast implements CastsAttributes
{
    private $setParsing;
    private $getFormat;
    private $setFormat;

    public function __construct($setParsing = false, $getFormat = false, $setFormat = false)
    {
        $this->setParsing = boolval($setParsing);
        $this->getFormat = $getFormat ? $getFormat : config('app.date_format', 'd/m/y h:i A');
        $this->setFormat = $setFormat ? $setFormat : config('app.date_format', 'd/m/y h:i A');
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
                    date('Y-m-d H:i:s', strtotime($value)),
                    'Y-m-d H:i:s',
                    $this->getFormat
                ) : null;
        } else {
            return $this->formatDate($value, 'Y-m-d H:i:s', $this->getFormat);
        }
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
            return $this->formatDate($value, $this->setFormat,'Y-m-d H:i:s');
        } else {
            return $value;
        }
    }

    public function formatDate($date, $originFormat = 'Y-m-d H:i:s', $toFormat = false)
    {
        if(!$toFormat) $toFormat = config('app.date_format', 'd/m/y h:i A');
        return $date ? Carbon::createFromFormat($originFormat, $date)->translatedFormat($toFormat) : null;
    }
}
