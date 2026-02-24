<?php

namespace ReynoTECH\QueryBuilderCustom\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Throwable;

abstract class BaseDateCast implements CastsAttributes
{
    protected bool $setParsing = false;
    protected string $getFormat;
    protected string $setFormat;
    protected string $storageFormat;
    protected string $configKey;
    protected string $configFallback;

    public function __construct(bool|string|null $setParsing = null, string|bool|null $getFormat = null, string|bool|null $setFormat = null)
    {
        $config = $this->getCastConfig();
        $setParsingDefault = $config['set_parsing_default'] ?? $this->setParsing;
        $this->setParsing = $this->parseBoolean($setParsing ?? $setParsingDefault);

        $configGetFormat = $config['get_format'] ?? null;
        $configSetFormat = $config['set_format'] ?? null;

        $this->getFormat = $this->resolveFormat($getFormat ?? $configGetFormat);
        $this->setFormat = $this->resolveFormat($setFormat ?? $configSetFormat);

        $storageFormat = $config['storage_format'] ?? null;
        if (is_string($storageFormat) && $storageFormat !== '') {
            $this->storageFormat = $storageFormat;
        }
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
        if ($this->isEmptyValue($value)) {
            return null;
        }

        $originFormat = $this->resolveOriginFormat($model, $key, $value);

        return $this->formatDate($value, $originFormat, $this->getFormat);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, $key, $value, $attributes)
    {
        if ($this->isEmptyValue($value)) {
            return null;
        }

        if ($this->setParsing) {
            return $this->formatDateForStorage($value, $this->setFormat);
        }

        return $this->normalizeSetValue($value);
    }

    protected function formatDate($date, ?string $originFormat, ?string $toFormat): ?string
    {
        $carbon = $this->toCarbon($date, $originFormat);
        if ($carbon === null) {
            return null;
        }

        $format = $this->resolveFormat($toFormat);

        return $carbon->translatedFormat($format);
    }

    protected function formatDateForStorage($date, ?string $originFormat): mixed
    {
        $carbon = $this->toCarbon($date, $originFormat);
        if ($carbon === null) {
            return null;
        }

        return $carbon->format($this->storageFormat);
    }

    protected function toCarbon($value, ?string $originFormat): ?Carbon
    {
        if ($this->isEmptyValue($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_int($value) || is_float($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && ctype_digit($value)) {
            $length = strlen($value);
            if ($length === 10) {
                return Carbon::createFromTimestamp((int) $value);
            }

            if ($length === 13) {
                return Carbon::createFromTimestamp(intdiv((int) $value, 1000));
            }
        }

        $value = (string) $value;

        if ($originFormat !== null && $originFormat !== '') {
            try {
                return Carbon::createFromFormat($originFormat, $value);
            } catch (Throwable) {
                // Fallback to Carbon::parse() below.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function resolveOriginFormat($model, string $key, $value): ?string
    {
        if (!is_string($value)) {
            return $this->storageFormat;
        }

        if ($model && method_exists($model, 'getDates') && in_array($key, $model->getDates(), true)) {
            if (method_exists($model, 'getDateFormat')) {
                return $model->getDateFormat();
            }
        }

        return $this->storageFormat;
    }

    protected function resolveFormat(string|bool|null $format): string
    {
        if ($format === null || $format === '' || $format === false || $format === 'false') {
            return (string) config($this->configKey, $this->configFallback);
        }

        return (string) $format;
    }

    protected function getCastConfig(): array
    {
        return [];
    }

    protected function parseBoolean(bool|string|null $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    protected function normalizeSetValue($value)
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($this->storageFormat);
        }

        return $value;
    }

    protected function isEmptyValue($value): bool
    {
        return $value === null || $value === '';
    }
}
