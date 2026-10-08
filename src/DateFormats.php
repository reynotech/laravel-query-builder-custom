<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom;

use InvalidArgumentException;

/** PHP patterns on the server; frontendConfig() exports the equivalent Day.js patterns. */
final class DateFormats
{
    private const DEFAULTS = ['date' => 'd/m/Y', 'dateTime' => 'd/m/Y h:i a', 'month' => 'm/Y'];
    private const LEGACY_KEYS = ['date' => 'app.date_format_solo', 'dateTime' => 'app.date_format'];
    private const TOKENS = [
        'Y' => 'YYYY', 'y' => 'YY', 'm' => 'MM', 'n' => 'M', 'd' => 'DD', 'j' => 'D',
        'H' => 'HH', 'G' => 'H', 'h' => 'hh', 'g' => 'h', 'i' => 'mm', 's' => 'ss',
        'a' => 'a', 'A' => 'A',
    ];

    public static function format(string $kind, string $purpose = 'display', ?string $fallback = null): string
    {
        if (!array_key_exists($kind, self::DEFAULTS) || !in_array($purpose, ['display', 'value', 'filter'], true)) {
            throw new InvalidArgumentException('Unknown date format kind or purpose.');
        }

        if ($purpose !== 'display') {
            $pattern = config("query_builder_custom.dates.{$purpose}.{$kind}");
            if ($pattern !== null) {
                return self::requirePattern($pattern);
            }
        }

        $pattern = config("query_builder_custom.dates.{$kind}");
        if ($pattern !== null) {
            return self::requirePattern($pattern);
        }

        $default = $fallback ?? self::DEFAULTS[$kind];
        return self::requirePattern(isset(self::LEGACY_KEYS[$kind])
            ? config(self::LEGACY_KEYS[$kind], $default)
            : $default);
    }

    /**
     * Return this from the consuming application's settings endpoint, then pass the
     * JSON directly to configureUtilsDates(). Cast-specific overrides stay per-field.
     *
     * @return array<string, mixed>
     */
    public static function frontendConfig(): array
    {
        $result = [];
        foreach (array_keys(self::DEFAULTS) as $kind) {
            $result[$kind] = self::toDayjs(self::format($kind));
            // Match each cast's historical fallback even when no new settings exist.
            $castFallback = match ($kind) {
                'date' => 'd/m/y',
                'dateTime' => 'd/m/y h:i A',
                default => 'm/Y',
            };
            $result['value'][$kind] = self::toDayjs(self::format($kind, 'value', $castFallback));
            $result['filter'][$kind] = self::toDayjs(self::format($kind, 'filter'));
        }
        return $result;
    }

    public static function toDayjs(string $pattern): string
    {
        self::requirePattern($pattern);
        $result = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $character = $pattern[$i];
            if ($character === '\\') {
                if (++$i === $length || in_array($pattern[$i], ['[', ']'], true)) {
                    throw new InvalidArgumentException('Date format contains an unsupported escaped literal.');
                }
                $result .= '[' . $pattern[$i] . ']';
            } elseif (isset(self::TOKENS[$character])) {
                $result .= self::TOKENS[$character];
            } elseif (ctype_alpha($character) || in_array($character, ['[', ']'], true)) {
                throw new InvalidArgumentException("PHP date token \"{$character}\" cannot be exported to Day.js. Use numeric date/time tokens or escaped literals.");
            } else {
                $result .= $character;
            }
        }
        return $result;
    }

    private static function requirePattern(mixed $pattern): string
    {
        if (!is_string($pattern) || trim($pattern) === '') {
            throw new InvalidArgumentException('Date formats must be non-empty PHP pattern strings.');
        }
        return $pattern;
    }
}
