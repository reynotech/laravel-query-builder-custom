<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class DateCast implements CastsAttributes
{
    private $setParsing;
    private $getFormat;
    private $setFormat;

    public function __construct($setParsing = false, $getFormat = false, $setFormat = false)
    {
        $this->setParsing = boolval($setParsing);
        $this->getFormat = $getFormat ?: config('app.date_format_solo', 'd/m/y');
        $this->setFormat = $setFormat ?: config('app.date_format_solo', 'd/m/y');
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
                    date('Y-m-d', strtotime($value)),
                    'Y-m-d',
                    $this->getFormat
                ) : null;
        } else {
            return $this->formatDate($value, 'Y-m-d', $this->getFormat);
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
            return $this->formatDate($value, $this->setFormat, 'Y-m-d');
        } else {
            return $value;
        }
    }

    public function formatDate($date, $originFormat = 'Y-m-d', $toFormat = false)
    {
        if(!$toFormat) $toFormat = config('app.date_format_solo');
        return $date !== null ? Carbon::createFromFormat($originFormat, $date)->translatedFormat($toFormat) : null;
    }
}
