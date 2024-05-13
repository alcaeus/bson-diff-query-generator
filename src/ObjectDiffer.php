<?php

namespace Alcaeus\BsonDiffQueryGenerator;

class ObjectDiffer implements DifferInterface
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

        $diff = $this->arrayDiffer->getDiff((array) $old, (array) $new);

        if (!$diff instanceof ArrayDiff) {
            return $diff;
        }

        return new ObjectDiff(
            addedValues: $diff->addedValues,
            changedValues: $diff->changedValues,
            removedFields: $diff->removedKeys,
        );
    }
}
