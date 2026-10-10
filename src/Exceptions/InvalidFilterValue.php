<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Exceptions;

use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

/**
 * A filter asked for with a value its column cannot take: one outside a
 * select's allowed values, a range with one end, text where a number goes.
 * Answered as 400, like the malformed query it is.
 */
final class InvalidFilterValue extends InvalidQuery
{
    public static function because(string $reason): self
    {
        return new self(Response::HTTP_BAD_REQUEST, $reason);
    }
}
