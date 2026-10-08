<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom;

use InvalidArgumentException;
use JsonException;

/**
 * Validated, field-agnostic boolean expression accepted by advanced filters.
 *
 * The expression deliberately contains no column name or SQL fragment. Spatie
 * still resolves the canonical allowed-filter name and each concrete filter
 * remains responsible for authorizing and applying its operators.
 */
final class BooleanFilterExpression
{
    private const PREFIX = '__reynotech_boolean_filter_expression__:';

    /** @param array<string, mixed> $root */
    private function __construct(private readonly array $root)
    {
    }

    /** @return array<string, mixed> */
    public function root(): array
    {
        return $this->root;
    }

    public function encode(): string
    {
        $json = json_encode($this->root, JSON_THROW_ON_ERROR);

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
            throw new InvalidArgumentException('The boolean filter expression encoding is invalid.');
        }

        return self::fromPayload($json);
    }

    public static function fromPayload(mixed $payload): self
    {
        if (is_string($payload)) {
            $maxPayloadLength = max(1, (int) config(
                'query_builder_custom.filters.boolean_expressions.max_payload_length',
                65536,
            ));
            if (strlen($payload) > $maxPayloadLength) {
                throw new InvalidArgumentException("Boolean filter expression payloads may not exceed {$maxPayloadLength} bytes.");
            }

            try {
                $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException('The boolean filter expression must be valid JSON.', previous: $exception);
            }
        }

        if (! is_array($payload)) {
            throw new InvalidArgumentException('The boolean filter expression root must be an object.');
        }

        $conditionCount = 0;
        $root = self::normalizeNode(
            $payload,
            depth: 1,
            conditionCount: $conditionCount,
            maxDepth: max(1, (int) config('query_builder_custom.filters.boolean_expressions.max_depth', 4)),
            maxConditions: max(1, (int) config('query_builder_custom.filters.boolean_expressions.max_conditions', 25)),
        );

        return new self($root);
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function normalizeNode(
        array $node,
        int $depth,
        int &$conditionCount,
        int $maxDepth,
        int $maxConditions,
    ): array {
        if ($depth > $maxDepth) {
            throw new InvalidArgumentException("Boolean filter expressions may not exceed {$maxDepth} levels.");
        }

        $type = strtolower((string) ($node['type'] ?? ''));
        if ($type === 'group') {
            self::assertOnlyKeys($node, ['type', 'operator', 'children', 'not']);

            $operator = strtolower((string) ($node['operator'] ?? 'and'));
            if (! in_array($operator, ['and', 'or'], true)) {
                throw new InvalidArgumentException('A boolean filter group operator must be "and" or "or".');
            }

            $children = $node['children'] ?? null;
            if (! is_array($children) || ! array_is_list($children) || $children === []) {
                throw new InvalidArgumentException('A boolean filter group must contain a non-empty children list.');
            }

            $normalizedChildren = [];
            foreach ($children as $child) {
                if (! is_array($child)) {
                    throw new InvalidArgumentException('Every boolean filter child must be an object.');
                }
                $normalizedChildren[] = self::normalizeNode(
                    $child,
                    $depth + 1,
                    $conditionCount,
                    $maxDepth,
                    $maxConditions,
                );
            }

            return self::withNot([
                'type' => 'group',
                'operator' => $operator,
                'children' => $normalizedChildren,
            ], $node);
        }

        if ($type !== 'condition') {
            throw new InvalidArgumentException('A boolean filter node type must be "group" or "condition".');
        }

        self::assertOnlyKeys($node, ['type', 'op', 'operator', 'value', 'not']);
        if (array_key_exists('op', $node) && array_key_exists('operator', $node)) {
            throw new InvalidArgumentException('A boolean filter condition must use either "op" or "operator", not both.');
        }

        $conditionCount++;
        if ($conditionCount > $maxConditions) {
            throw new InvalidArgumentException("Boolean filter expressions may not exceed {$maxConditions} conditions.");
        }

        $operator = strtolower((string) ($node['op'] ?? $node['operator'] ?? ''));
        if (! preg_match('/^[a-z][a-z0-9_:-]{0,31}$/', $operator)) {
            throw new InvalidArgumentException('A boolean filter condition contains an invalid operator.');
        }
        if (! array_key_exists('value', $node)) {
            throw new InvalidArgumentException('A boolean filter condition must contain a value key.');
        }

        return self::withNot([
            'type' => 'condition',
            'op' => $operator,
            'value' => self::normalizeValue($node['value']),
        ], $node);
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $maxValues = max(1, (int) config('query_builder_custom.filters.boolean_expressions.max_values_per_condition', 100));
            if (! array_is_list($value) || count($value) > $maxValues) {
                throw new InvalidArgumentException("A boolean filter condition value must be a list of at most {$maxValues} scalar values.");
            }

            return array_map(static fn (mixed $item): mixed => self::normalizeScalar($item), $value);
        }

        return self::normalizeScalar($value);
    }

    private static function normalizeScalar(mixed $value): mixed
    {
        if (! is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Boolean filter condition values must be scalar, null, or scalar lists.');
        }

        if (is_string($value)) {
            $maxLength = max(1, (int) config('query_builder_custom.filters.boolean_expressions.max_value_length', 2000));
            if (strlen($value) > $maxLength) {
                throw new InvalidArgumentException("Boolean filter string values may not exceed {$maxLength} bytes.");
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $normalized
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function withNot(array $normalized, array $source): array
    {
        if (array_key_exists('not', $source) && ! is_bool($source['not'])) {
            throw new InvalidArgumentException('The boolean filter "not" flag must be a boolean.');
        }

        if (($source['not'] ?? false) === true) {
            $normalized['not'] = true;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $allowed
     */
    private static function assertOnlyKeys(array $node, array $allowed): void
    {
        $unexpected = array_values(array_diff(array_keys($node), $allowed));
        if ($unexpected !== []) {
            throw new InvalidArgumentException(sprintf(
                'Boolean filter node contains unsupported key(s): %s.',
                implode(', ', $unexpected),
            ));
        }
    }
}
