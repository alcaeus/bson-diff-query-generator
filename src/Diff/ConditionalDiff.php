<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

use MongoDB\BSON\Type;
use MongoDB\Builder\Type\ExpressionInterface;
use stdClass;

/** @internal */
final readonly class ConditionalDiff implements Diff
{
    public function __construct(
        public Type|ExpressionInterface|stdClass|array|bool|float|int|string|null $identifier,
        public ?Diff $diff = null,
    ) {
    }
}
