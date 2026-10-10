<?php

declare(strict_types=1);

namespace ReynoTECH\QueryBuilderCustom\Exceptions;

use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

/**
 * A filter asked for with an operator its column does not have.
 *
 * Answered as 400, like any other malformed query, rather than dropping the
 * filter: a list that silently shows every row looks filtered and is not.
 */
final class InvalidFilterOperator extends InvalidQuery
{
    public static function for(string $operator, string $filter): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            sprintf('Operator "%s" is not supported by %s.', $operator, $filter),
        );
    }
}
