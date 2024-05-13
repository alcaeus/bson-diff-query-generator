<?php

namespace Alcaeus\BsonDiffQueryGenerator;

use function is_array;

final class Differ implements DifferInterface
{
    public function getDiff(mixed $old, mixed $new): Diff
    {
        if (is_array($new) && is_array($old)) {
            return (new ArrayDiffer())->getDiff($old, $new);
        }

        if (is_object($new) && is_object($old)) {
            return (new ObjectDiffer())->getDiff($old, $new);
        }

        return (new ValueDiffer())->getDiff($old, $new);
    }
}
