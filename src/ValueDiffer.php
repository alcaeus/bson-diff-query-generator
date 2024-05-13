<?php

namespace Alcaeus\BsonDiffQueryGenerator;

final class ValueDiffer implements DifferInterface
{
    public function getDiff(mixed $old, mixed $new): EmptyDiff|ValueDiff
    {
        return $old === $new
            ? new EmptyDiff()
            : new ValueDiff($new);
    }
}
