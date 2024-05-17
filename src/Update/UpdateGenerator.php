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
    public static function generateUpdatePipelineForDiff(ObjectDiff $objectDiff): Pipeline
    {
        /** @psalm-suppress MixedArgument */
        return new Pipeline(
            Stage::set(...self::generateUpdateObject($objectDiff)),
        );
    }

    /**
     * Generates an update object for a single ObjectDiff
     *
     * The update object is meant to be used directly in a $set pipeline stage, or as an expression to update a nested
     * document by supplying a prefix
     *
     * @return array<string, mixed>
     */
    private static function generateUpdateObject(ObjectDiff $objectDiff, BaseExpression\FieldPath|BaseExpression\Variable|null $prefix = null): array
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
            // Added values can be set directly, wrapped in a $literal operator
            array_map(
                /** @psalm-suppress MixedArgument */
                static fn (mixed $value): BaseExpression\LiteralOperator => BaseExpression::literal($value),
                $objectDiff->addedValues,
            ),
            // Removed fields can be unset by leveraging the $$REMOVE variable, avoiding the need for a separate
            // $unset pipeline stage
            array_combine(
                $objectDiff->removedFields,
                array_fill(0, count($objectDiff->removedFields), BaseExpression::variable('REMOVE')),
            ),
            // For changed values, generate field expressions for each field diff
            array_combine(
                array_keys($objectDiff->changedValues),
                array_map(
                    static fn (string $key, Diff $diff) => self::generateFieldExpression($getFieldValue($key), $diff),
                    array_keys($objectDiff->changedValues),
                    $objectDiff->changedValues,
                ),
            ),
        );
    }

    /**
     * Generates an update expression for a ListDiff
     *
     * The process is as follows:
     * 1. Create an intermediate list of key/value objects
     * 2. Use $map to apply changes for each list element
     * 3. Use $filter to filter out removed elements
     * 4. Unwrap intermediate list back to the original format
     * 5. Use $concatArrays to append new items
     */
    private static function generateListUpdate(BaseExpression\ResolvesToArray $input, ListDiff $diff): BaseExpression\ResolvesToArray
    {
        return Expression::appendItemsToList(
            Expression::extractValuesFromList(
                self::createRemovedListItemFilter(
                    input: self::generateListItemUpdates(
                        input: Expression::wrapValuesWithKeys($input),
                        changedValues: $diff->changedValues,
                    ),
                    removedKeys: $diff->removedKeys,
                ),
            ),
            $diff->addedValues,
        );
    }

    /**
     * Generates a $map expression to apply updates to list items
     *
     * The in portion of $map consists of a $switch statement with a branch for each item that needs updating.
     *
     * @param array<array-key, Diff> $changedValues
     */
    private static function generateListItemUpdates(BaseExpression\ResolvesToArray $input, array $changedValues): BaseExpression\ResolvesToArray
    {
        if ($changedValues === []) {
            return $input;
        }

        return BaseExpression::map(
            input: $input,
            in: BaseExpression::switch(
                array_map(
                    self::generateListItemUpdateBranch(...),
                    array_values($changedValues),
                    array_keys($changedValues),
                ),
                default: BaseExpression::variable('this'),
            ),
        );
    }

    /**
     * Generates a single branch for an item update inside the $switch expression
     */
    private static function generateListItemUpdateBranch(Diff $fieldDiff, int|string $key): BaseExpression\CaseOperator
    {
        $comparisonKey = $key;
        $diff = $fieldDiff;

        if ($fieldDiff instanceof ConditionalDiff) {
            $comparisonKey = $fieldDiff;
            $diff = $fieldDiff->diff ?? throw new LogicException('Cannot generate update for empty diff');
        }

        return BaseExpression::case(
            case: self::generateListItemMatchCondition($comparisonKey),
            then: BaseExpression::mergeObjects(
                BaseExpression::variable('this'),
                object(v: self::generateFieldExpression(BaseExpression::variable('this.v'), $diff)),
            ),
        );
    }

    /**
     * Returns a $filter expression to filter out removed items
     *
     * @param list<int|string|ConditionalDiff> $removedKeys
     */
    private static function createRemovedListItemFilter(BaseExpression\ResolvesToArray $input, array $removedKeys): BaseExpression\ResolvesToArray
    {
        if ($removedKeys === []) {
            return $input;
        }

        return BaseExpression::filter(
            $input,
            BaseExpression::and(
                ...array_map(
                    static fn (int|string|ConditionalDiff $value): BaseExpression\ResolvesToBool => self::generateListItemMatchCondition($value, negate: true),
                    $removedKeys,
                ),
            ),
        );
    }

    /**
     * Generates an expression to update a field value based on a given diff
     */
    private static function generateFieldExpression(BaseExpression\FieldPath|BaseExpression\Variable $path, Diff $fieldDiff): BaseExpression\ResolvesToAny|BaseExpression\ResolvesToObject|BaseExpression\ResolvesToArray
    {
        /** @psalm-suppress MixedArgument */
        return match ($fieldDiff::class) {
            // ValueDiff: return new value wrapped in a $literal operator to prevent execution of pipeline operators
            ValueDiff::class => BaseExpression::literal($fieldDiff->value),

            /* ObjectDiff: use $mergeObjects to merge the original object value with the update object for the diff
             * The field names in the generated update object are prefixed with the field path to support embedded
             * documents. */
            ObjectDiff::class => BaseExpression::mergeObjects(
                $path,
                self::generateUpdateObject($fieldDiff, $path),
            ),

            // ListDiff: generate list updates based on the current field path
            ListDiff::class => self::generateListUpdate(
                $path,
                $fieldDiff,
            ),
        };
    }

    /**
     * Generates a match condition for a list item
     *
     * For ConditionalDiff values, creates a comparison based on the _id field of the value. For other values, it
     * assumes them to be the key. The negate parameter is used to receive a $ne condition to be used for filtering
     * out removed items.
     */
    private static function generateListItemMatchCondition(int|string|ConditionalDiff $value, bool $negate = false): BaseExpression\ResolvesToBool
    {
        $comparison = $negate
            ? static fn (BaseExpression\Variable $variable, Type|ExpressionInterface|stdClass|array|bool|float|int|string|null $value): BaseExpression\ResolvesToBool => BaseExpression::ne($variable, $value)
            : static fn (BaseExpression\Variable $variable, Type|ExpressionInterface|stdClass|array|bool|float|int|string|null $value): BaseExpression\ResolvesToBool => BaseExpression::eq($variable, $value);

        return $value instanceof ConditionalDiff
            ? $comparison(BaseExpression::variable('this.v._id'), $value->identifier)
            : $comparison(BaseExpression::variable('this.k'), $value);
    }
}
