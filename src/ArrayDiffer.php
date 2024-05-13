<?php

namespace Alcaeus\BsonDiffQueryGenerator;

use function array_diff_key;
use function array_keys;

class ArrayDiffer implements DifferInterface
{
    public function getDiff(?array $old, ?array $new): Diff
    {
        if ($old === $new) {
            return new EmptyDiff();
        }

        if ($old === null || $new === null) {
            return new ValueDiff($new);
        }

        $changedValues = [];
        $differ = new Differ();
        foreach ($this->getCommonKeys($old, $new) as $key) {
            $diff = $differ->getDiff($old[$key], $new[$key]);
            if ($diff instanceof EmptyDiff) {
                continue;
            }

            $changedValues[$key] = $diff;
        }

        $addedValues = array_diff_key($new, $old);
        $removedKeys = array_diff_key($old, $new);

        // TODO: consider checking values in added and removed keys to determine
        // renamed keys

        return new ArrayDiff(
            addedValues: $addedValues,
            changedValues: $changedValues,
            removedKeys: array_keys($removedKeys),
        );
    }

    /** @return array<array-key> */
    private function getCommonKeys(array $old, array $new): array
    {
        return array_intersect(array_keys($old), array_keys($new));
    }
}
