<?php

namespace Alcaeus\BsonDiffQueryGenerator\Update;

use Alcaeus\BsonDiffQueryGenerator\Diff\ConditionalDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\Diff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ListDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use LogicException;
use MongoDB\BSON\Type;
use MongoDB\Builder\Expression as BaseExpression;
use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Stage;
use MongoDB\Builder\Type\ExpressionInterface;
use stdClass;

use function array_combine;
use function array_fill;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function MongoDB\object;

final class UpdateGenerator
{
    public function generateUpdatePipeline(ObjectDiff $objectDiff): Pipeline
    {
        return new Pipeline(
            Stage::set(...$this->generateUpdateObject($objectDiff)),
        );
    }

    /** @return array<string, mixed> */
    private function generateUpdateObject(ObjectDiff $objectDiff, BaseExpression\FieldPath|BaseExpression\Variable|null $prefix = null): array
    {
        $prefixKey = match (true) {
            $prefix instanceof BaseExpression\FieldPath => $prefix->name . '.',
            $prefix instanceof BaseExpression\Variable => $prefix->name . '.',
            $prefix === null => '',
        };

        $getFieldValue = $prefix instanceof BaseExpression\Variable
            ? static fn (string $key): BaseExpression\Variable => BaseExpression::variable($prefixKey . $key)
            : static fn (string $key): BaseExpression\FieldPath => BaseExpression::fieldPath($prefixKey . $key);

        return array_merge(
            array_map(
                static fn (mixed $value): BaseExpression\LiteralOperator => BaseExpression::literal($value),
                $objectDiff->addedValues,
            ),
            array_combine(
                $objectDiff->removedFields,
                array_fill(0, count($objectDiff->removedFields), BaseExpression::variable('REMOVE')),
            ),
            array_combine(
                array_keys($objectDiff->changedValues),
                array_map(
                    fn (string $key, Diff $diff) => $this->generateFieldExpression($getFieldValue($key), $diff),
                    array_keys($objectDiff->changedValues),
                    $objectDiff->changedValues,
                ),
            ),
        );
    }

    private function generateListUpdate(BaseExpression\ResolvesToArray $input, ListDiff $diff): BaseExpression\ResolvesToArray
    {
        return $this->appendItemsToList(
            Expression::extractValuesFromList(
                $this->createRemovedListItemFilter(
                    input: $this->generateListItemUpdates(
                        input: Expression::wrapValuesWithKeys($input),
                        changedValues: $diff->changedValues,
                    ),
                    removedKeys: $diff->removedKeys,
                ),
            ),
            $diff->addedValues,
        );
    }

    /** @param array<array-key, Diff> $changedValues */
    private function generateListItemUpdates(BaseExpression\ResolvesToArray $input, array $changedValues): BaseExpression\ResolvesToArray
    {
        if ($changedValues === []) {
            return $input;
        }

        return BaseExpression::map(
            input: $input,
            in: BaseExpression::switch(
                array_map(
                    $this->generateListItemUpdateBranch(...),
                    array_values($changedValues),
                    array_keys($changedValues),
                ),
                default: BaseExpression::variable('this'),
            ),
        );
    }

    private function generateListItemUpdateBranch(Diff $fieldDiff, int|string $key): BaseExpression\CaseOperator
    {
        if ($fieldDiff instanceof ConditionalDiff) {
            $comparisonKey = $fieldDiff;
            $diff = $fieldDiff->diff ?? throw new LogicException('Cannot generate update for empty diff');
        }

        $comparisonKey = $key;
        $diff = $fieldDiff;

        return BaseExpression::case(
            case: $this->generateListItemMatchCondition($comparisonKey),
            then: BaseExpression::mergeObjects(
                BaseExpression::variable('this'),
                object(v: $this->generateFieldExpression(BaseExpression::variable('this.v'), $diff)),
            ),
        );
    }

    /** @param list<int|string|ConditionalDiff> $removedKeys */
    private function createRemovedListItemFilter(BaseExpression\ResolvesToArray $input, array $removedKeys): BaseExpression\ResolvesToArray
    {
        if ($removedKeys === []) {
            return $input;
        }

        return BaseExpression::filter(
            $input,
            BaseExpression::and(
                ...array_map(
                    fn (int|string|ConditionalDiff $value): BaseExpression\ResolvesToBool => $this->generateListItemMatchCondition($value, negate: true),
                    $removedKeys,
                ),
            ),
        );
    }

    private function generateFieldExpression(BaseExpression\FieldPath|BaseExpression\Variable $path, Diff $fieldDiff): BaseExpression\ResolvesToAny|BaseExpression\ResolvesToObject|BaseExpression\ResolvesToArray
    {
        return match ($fieldDiff::class) {
            // Simple value: return wrapped in a $literal operator to prevent execution of dollars
            ValueDiff::class => BaseExpression::literal($fieldDiff->value),

            // Object diff: apply with a prefix
            ObjectDiff::class => BaseExpression::mergeObjects(
                $path,
                $this->generateUpdateObject($fieldDiff, $path),
            ),

            // List diff: use the $map operator to traverse the list and update elements
            ListDiff::class => $this->generateListUpdate(
                $path,
                $fieldDiff,
            ),
        };
    }

    private function appendItemsToList(BaseExpression\ResolvesToArray $input, array $addedValues): BaseExpression\ResolvesToArray
    {
        if ($addedValues === []) {
            return $input;
        }

        return BaseExpression::concatArrays(
            $input,
            array_map(
                static fn (mixed $value): BaseExpression\LiteralOperator => BaseExpression::literal($value),
                array_values($addedValues),
            ),
        );
    }

    private function generateListItemMatchCondition(int|string|ConditionalDiff $value, bool $negate = false): BaseExpression\ResolvesToBool
    {
        $comparison = $negate
            ? static fn (BaseExpression\Variable $variable, Type|ExpressionInterface|stdClass|array|bool|float|int|string|null $value): BaseExpression\ResolvesToBool => BaseExpression::ne($variable, $value)
            : static fn (BaseExpression\Variable $variable, Type|ExpressionInterface|stdClass|array|bool|float|int|string|null $value): BaseExpression\ResolvesToBool => BaseExpression::eq($variable, $value);

        return $value instanceof ConditionalDiff
            ? $comparison(BaseExpression::variable('this.v._id'), $value->identifier)
            : $comparison(BaseExpression::variable('this.k'), $value);
    }
}
