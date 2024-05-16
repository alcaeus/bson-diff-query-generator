<?php

namespace Alcaeus\BsonDiffQueryGenerator\Update;

use Alcaeus\BsonDiffQueryGenerator\Diff\Diff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ListDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use MongoDB\Builder\Expression as BaseExpression;
use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Stage;
use MongoDB\Builder\Stage\SetStage;
use MongoDB\Builder\Type\StageInterface;
use function array_keys;
use function array_map;
use function array_reduce;
use function array_values;
use function rsort;

final class UpdateGenerator
{
    public function generateUpdatePipeline(ObjectDiff $objectDiff): Pipeline
    {
        $query = $this->generateQueryObject($objectDiff);

        /** @var list<StageInterface|Pipeline> $stages */
        $stages = [];

        if ($query->lists !== []) {
            $sortedLists = $query->lists;
            sort($sortedLists);

            $stages = array_merge(
                $stages,
                array_map(
                    $this->convertListToObject(...),
                    $sortedLists,
                ),
            );
        }

        if ($query->set !== []) {
            $stages[] = Stage::set(...$query->set);
        }

        if ($query->unset !== []) {
            $stages[] = Stage::unset(...$query->unset);
        }

        if ($query->lists !== []) {
            $stages = array_merge(
                $stages,
                $this->pushNewElementsAndConvertList($query),
            );
        }

        return new Pipeline(...$stages);
    }

    private function generateQueryObject(ObjectDiff $objectDiff): Update
    {
        $query = new Update(
            $objectDiff->addedValues,
            $objectDiff->removedFields,
        );

        return array_reduce(
            array_keys($objectDiff->changedValues),
            fn (Update $query, string $key) => $this->updateQueryForDiff(
                $key,
                $objectDiff->changedValues[$key],
                $query,
            ),
            $query,
        );
    }

    private function updateQueryForDiff(string $key, Diff $diff, Update $query): Update
    {
        return match ($diff::class) {
            // Simple value: add to $set operator
            ValueDiff::class => $query->combineWith(set: [$key => $diff->value]),

            // Object diff: apply with a prefix
            ObjectDiff::class => $query->combineWithPrefixedQuery(
                $this->generateQueryObject($diff),
                $key,
            ),

            ListDiff::class => $query->combineWithPrefixedQuery(
                $this->generateListQueryObject($diff),
                $key,
                isList: true,
            ),
        };
    }

    private function generateListQueryObject(ListDiff $listDiff): Update
    {
        $query = new Update(
            unset: $listDiff->removedKeys,
            push: ['' => array_values($listDiff->addedValues)],
        );

        return array_reduce(
            array_keys($listDiff->changedValues),
            fn (Update $query, int|string $index) => $this->updateQueryForDiff(
                (string) $index,
                $listDiff->changedValues[$index],
                $query,
            ),
            $query,
        );
    }

    private function convertListToObject(string $key): SetStage
    {
        return Stage::set(...[$key => Expression::listToObject(BaseExpression::arrayFieldPath($key))]);
    }

    private function convertObjectToList(string $key): SetStage
    {
        return Stage::set(...[$key => Expression::objectToList(BaseExpression::objectFieldPath($key))]);
    }

    private function pushNewListElements(string $key, array $elements): SetStage
    {
        return Stage::set(
            ...[$key => BaseExpression::concatArrays(
                BaseExpression::arrayFieldPath($key),
                $elements,
            )],
        );
    }

    /** @return list<SetStage|Pipeline> */
    private function pushNewElementsAndConvertList(Update $query): array
    {
        $lists = $query->lists;

        // Sort in reverse order - this will generally ensure that nested lists are handled first
        rsort($lists);

        return array_map(
            function (string $list) use ($query): Pipeline|SetStage
            {
                if ($query->push[$list] !== []) {
                    return new Pipeline(
                        $this->convertObjectToList($list),
                        $this->pushNewListElements($list, $query->push[$list]),
                    );
                }

                return $this->convertObjectToList($list);
            },
            $lists,
        );
    }
}
