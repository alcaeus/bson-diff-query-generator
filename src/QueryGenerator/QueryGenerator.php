<?php

namespace Alcaeus\BsonDiffQueryGenerator\QueryGenerator;

use Alcaeus\BsonDiffQueryGenerator\Diff\ListDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use function array_combine;
use function array_fill;

final class QueryGenerator
{
    public function generateQueryObject(ObjectDiff $objectDiff): Query
    {
        $query = new Query(
            $objectDiff->addedValues,
            array_combine(
                $objectDiff->removedFields,
                array_fill(0, count($objectDiff->removedFields), true),
            ),
        );

        /** @var array<string, mixed> $set */
        $set = [];
        foreach ($objectDiff->changedValues as $key => $diff) {
            // ValueDiff always uses `$set` to replace the original value
            if ($diff instanceof ValueDiff) {
                $set[$key] = $diff->value;
                continue;
            }

            // For objects, merge our query with that of the nested object, prefixing with the current key
            if ($diff instanceof ObjectDiff) {
                $query = $query->combineWithPrefixedQuery(
                    $this->generateQueryObject($diff),
                    $key,
                );

                continue;
            }

            // Arrays need special handling. An array could map to an object, or it could represent a list
            if ($diff instanceof ListDiff) {
                // TODO: Array diff
            }
        }

        return $query->combineWith(set: $set);
    }
}
