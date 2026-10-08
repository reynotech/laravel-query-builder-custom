<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom;

/**
 * Value object used internally after a spatie-v2 request has been normalized.
 *
 * Keeping this as an object (rather than an array marker supplied by a client)
 * makes the advanced filters opt in only to values produced by the adapter.
 */
final class SpatieV2FilterConditions
{
    private const PREFIX = '__reynotech_spatie_v2_conditions__:';

    /** @param list<array{join: 'and'|'or', operator: ?string, value: mixed, index: int|string|null}> $conditions */
    public function __construct(private readonly array $conditions)
    {
    }

    /** @return list<array{join: 'and'|'or', operator: ?string, value: mixed, index: int|string|null}> */
    public function all(): array
    {
        return $this->conditions;
    }

    /**
     * QueryBuilderRequest v6 only accepts scalar/array request values. This
     * representation deliberately contains no filter delimiter, so Spatie
     * passes it through unchanged until the allowed custom filter receives it.
     */
    public function encode(): string
    {
        $json = json_encode($this->conditions, JSON_THROW_ON_ERROR);

        return self::PREFIX . rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function fromEncoded(mixed $value): ?self
    {
        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            return null;
        }

        $payload = substr($value, strlen(self::PREFIX));
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode(strtr($payload, '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        try {
            $conditions = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($conditions)) {
            return null;
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition)
                || ! in_array($condition['join'] ?? null, ['and', 'or'], true)
                || ! array_key_exists('operator', $condition)
                || (! is_string($condition['operator']) && $condition['operator'] !== null)
                || ! array_key_exists('value', $condition)
                || ! array_key_exists('index', $condition)
                || (! is_int($condition['index']) && ! is_string($condition['index']) && $condition['index'] !== null)) {
                return null;
            }
        }

        /** @var list<array{join: 'and'|'or', operator: ?string, value: mixed, index: int|string|null}> $conditions */
        return new self($conditions);
    }
}
