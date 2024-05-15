<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

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

        // TODO: This will convert any object with sequential numeric 0-based keys to a list
        return $this->arrayDiffer->getDiff((array) $old, (array) $new);
    }
}
