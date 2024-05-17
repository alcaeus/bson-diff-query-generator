<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

use function get_object_vars;

/** @internal */
final class ObjectDiffer implements DifferInterface
{
    private ArrayDiffer $arrayDiffer;

    public function __construct()
    {
        $this->arrayDiffer = new ArrayDiffer();
    }

    public function getDiff(?object $old, ?object $new): Diff
    {
        if ($old === null || $new === null) {
            return new ValueDiff($new);
        }

        return $this->arrayDiffer->getDiff(
            get_object_vars($old),
            get_object_vars($new),
            forceObjectDiff: true,
        );
    }
}
