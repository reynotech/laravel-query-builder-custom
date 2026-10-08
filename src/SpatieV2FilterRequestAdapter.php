<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom;

use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Converts the opt-in spatie-v2 filter-key contract into values understood by
 * the package's advanced filters, before Spatie resolves AllowedFilter names.
 */
final class SpatieV2FilterRequestAdapter
{
    public static function normalize(Request $request): Request
    {
        if (! config('query_builder_custom.filters.spatie_v2.enabled', false)) {
            return $request;
        }

        $parameter = (string) config('query-builder.parameters.filter', 'filter');
        $filters = $request->input($parameter, []);

        if (! is_array($filters)) {
            return $request;
        }

        $delimiter = (string) config('query_builder_custom.filters.delimiter', '|');
        if ($delimiter === '') {
            return $request;
        }

        $expressionOperator = strtolower((string) config(
            'query_builder_custom.filters.boolean_expressions.operator',
            'expr',
        ));

        $v2Fields = [];
        $expressionFields = [];
        foreach ($filters as $key => $_value) {
            $parsed = self::parseKey($key, $delimiter);
            if ($parsed !== null) {
                $v2Fields[$parsed['field']] = true;
                if ($parsed['operator'] === $expressionOperator) {
                    $expressionFields[$parsed['field']] = true;
                }
            }
        }

        if ($v2Fields === []) {
            return $request;
        }

        $normalized = [];
        /** @var array<string, list<array{join: 'and'|'or', operator: ?string, value: mixed, index: int|string|null}>> $conditions */
        $conditions = [];

        foreach ($filters as $key => $value) {
            $parsed = self::parseKey($key, $delimiter);

            if ($parsed !== null) {
                $field = $parsed['field'];

                if (isset($expressionFields[$field])) {
                    if ($parsed['operator'] !== $expressionOperator) {
                        throw new InvalidArgumentException("Boolean expression filter '{$field}' cannot be mixed with flat conditions.");
                    }
                    if (array_key_exists($field, $normalized)) {
                        throw new InvalidArgumentException("Boolean expression filter '{$field}' may only be declared once.");
                    }

                    $normalized[$field] = BooleanFilterExpression::fromPayload($value)->encode();
                    continue;
                }

                $conditions[$field] ??= [];

                foreach (self::valuesToConditions($value, $parsed['join'], $parsed['operator']) as $condition) {
                    $conditions[$field][] = $condition;
                }

                continue;
            }

            // Legacy values remain byte-for-byte untouched unless the same
            // logical field also has spatie-v2 conditions, in which case both
            // forms must be represented by a single AllowedFilter invocation.
            if (is_string($key) && isset($v2Fields[$key])) {
                if (isset($expressionFields[$key])) {
                    throw new InvalidArgumentException("Boolean expression filter '{$key}' cannot be mixed with a legacy value.");
                }
                $conditions[$key] ??= [];
                $conditions[$key][] = ['join' => 'and', 'operator' => null, 'value' => $value, 'index' => null];
                continue;
            }

            $normalized[$key] = $value;
        }

        // Preserve each field's condition order while replacing its spatie-v2
        // keys by one canonical key for Spatie's allowed-filter validation.
        foreach ($filters as $key => $_value) {
            $parsed = self::parseKey($key, $delimiter);
            $field = $parsed['field'] ?? (is_string($key) && isset($v2Fields[$key]) ? $key : null);

            if ($field !== null && isset($conditions[$field]) && ! array_key_exists($field, $normalized)) {
                $normalized[$field] = (new SpatieV2FilterConditions($conditions[$field]))->encode();
            }
        }

        $normalizedRequest = Request::createFrom($request);
        $normalizedRequest->merge([$parameter => $normalized]);

        return $normalizedRequest;
    }

    /** @return array{field: string, join: 'and'|'or', operator: string}|null */
    private static function parseKey(mixed $key, string $delimiter): ?array
    {
        if (! is_string($key) || ! str_contains($key, $delimiter)) {
            return null;
        }

        [$field, $operation] = explode($delimiter, $key, 2);
        if ($field === '' || $operation === '') {
            return null;
        }

        $join = 'and';
        if (str_contains($operation, ':')) {
            [$requestedJoin, $operator] = explode(':', $operation, 2);
            if ($operator === '') {
                return null;
            }

            if ($requestedJoin === 'and' || $requestedJoin === 'or') {
                $join = $requestedJoin;
                $operation = $operator;
            }
        }

        return ['field' => $field, 'join' => $join, 'operator' => $operation];
    }

    /** @return list<array{join: 'and'|'or', operator: string, value: mixed, index: int|string|null}> */
    private static function valuesToConditions(mixed $value, string $join, string $operator): array
    {
        if (! is_array($value)) {
            return [['join' => $join, 'operator' => $operator, 'value' => $value, 'index' => null]];
        }

        $conditions = [];
        foreach ($value as $index => $item) {
            $conditions[] = ['join' => $join, 'operator' => $operator, 'value' => $item, 'index' => $index];
        }

        return $conditions === []
            ? [['join' => $join, 'operator' => $operator, 'value' => $value, 'index' => null]]
            : $conditions;
    }
}
