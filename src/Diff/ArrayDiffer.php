<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

use function array_combine;
use function array_diff_key;
use function array_intersect;
use function array_is_list;
use function array_keys;
use function array_map;
use function is_int;
use function is_object;

/** @internal */
final class ArrayDiffer implements DifferInterface
{
    public function getDiff(?array $old, ?array $new, bool $forceObjectDiff = false): EmptyDiff|ValueDiff|ListDiff|ObjectDiff
    {
        // Identical arrays: no diff
        if ($old === $new) {
            return new EmptyDiff();
        }

        // If either value is not an array, use a ValueDiff as we can't apply updates
        if ($old === null || $new === null) {
            return new ValueDiff($new);
        }

        $oldIsList = array_is_list($old);
        $newCouldBeList = $this->couldBeList($new);

        // If an array changed from a list to a non-list (or vice-versa), overwrite the value as the BSON type would
        // change
        if ($oldIsList xor $newCouldBeList) {
            return new ValueDiff($new);
        }

        // For common keys, recursively diff all values and store the non-empty diffs
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
        $removedKeys = array_keys(array_diff_key($old, $new));

        // If we're dealing with a list, convert any diffs to conditional diffs.
        // Conditional diffs can be used when a list element is an object with an _id field.
        if ($newCouldBeList) {
            $changedValues = array_combine(
                array_keys($changedValues),
                array_map(
                    /** @psalm-suppress MixedArgument */
                    static fn (Diff $diff, int|string $changedKey): Diff => is_int($changedKey) && is_object($old[$changedKey]) && isset($old[$changedKey]->_id)
                        ? new ConditionalDiff($old[$changedKey]->_id, $diff)
                        : $diff,
                    $changedValues,
                    array_keys($changedValues),
                ),
            );

            $removedKeys = array_map(
            /** @psalm-suppress MixedArgument */
                static fn (int|string $removedKey): int|string|ConditionalDiff => is_int($removedKey) && is_object($old[$removedKey]) && isset($old[$removedKey]->_id)
                    ? new ConditionalDiff($old[$removedKey]->_id)
                    : $removedKey,
                $removedKeys,
            );
        }

        // TODO: consider checking values in added and removed keys to determine
        // renamed keys

        $className = $newCouldBeList && ! $forceObjectDiff ? ListDiff::class : ObjectDiff::class;

        return new $className(
            $addedValues,
            $changedValues,
            $removedKeys,
        );
    }

    /**
     * Returns whether a given array with arbitrary keys could be a list
     *
     * A list (packed array in PHP) is a special array with sequential integer keys starting with 0.
     * For an array to be a potential list, we require:
     * - All keys MUST be integers
     * - Keys MUST be in order; plugging gaps is not allowed
     *
     * @param array<array-key, mixed> $array
     */
    private function couldBeList(array $array): bool
    {
        $previousKey = null;
        foreach (array_keys($array) as $key) {
            if (! is_int($key)) {
                return false;
            }

            if ($previousKey !== null && $previousKey > $key) {
                return false;
            }

            $previousKey = $key;
        }

        return true;
    }

    /** @return array<array-key> */
    private function getCommonKeys(array $old, array $new): array
    {
        return array_intersect(array_keys($old), array_keys($new));
    }
}
