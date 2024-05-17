<?php

namespace Alcaeus\BsonDiffQueryGenerator\Update;

use MongoDB\Builder\Expression as BaseExpression;

use function array_map;
use function array_values;
use function MongoDB\object;

/** @internal */
final class Expression
{
    /**
     * Extracts the previously nested value from a list
     *
     * For a list consisting of {k: <index>, v: <value>} objects, returns only the value.
     * This is used to undo changes made by {@see self::wrapValuesWithKeys()}.
     */
    public static function extractValuesFromList(BaseExpression\ResolvesToArray $input): BaseExpression\MapOperator
    {
        return BaseExpression::map(
            input: $input,
            in: BaseExpression::variable('this.v'),
        );
    }

    /**
     * Generates key/value objects for all elements in a list
     *
     * For a list of values, it generates objects in the form {k: <index>, v: <value>}, with <index> being the position
     * of the item in the list, and <value> being the value.
     * Can be undone using {@see self::extractValuesFromList()}
     */
    public static function wrapValuesWithKeys(BaseExpression\ResolvesToArray $array): BaseExpression\MapOperator
    {
        return BaseExpression::map(
            // Generate a list of arrays: [{ k: <index> }, { v: <element} }]
            input: BaseExpression::zip(
                inputs: [
                    self::generateKeyObjectList($array),
                    self::generateValueObjectList($array),
                ],
            ),
            // Combines the two objects in each item into a single object
            in: BaseExpression::mergeObjects(BaseExpression::variable('this')),
        );
    }

    /**
     * Appends new items to a list
     *
     * New values are wrapped in a $literal operator to avoid running any pipeline operators
     * If no items are given, returns the original input without decoration.
     */
    public static function appendItemsToList(BaseExpression\ResolvesToArray $input, array $items): BaseExpression\ResolvesToArray
    {
        if ($items === []) {
            return $input;
        }

        return BaseExpression::concatArrays(
            $input,
            array_map(
                /** @psalm-suppress MixedArgument */
                static fn (mixed $value): BaseExpression\LiteralOperator => BaseExpression::literal($value),
                array_values($items),
            ),
        );
    }

    /**
     * Generates a list of 0-based indexes based on the size of the original list
     */
    private static function getListKeyRange(BaseExpression\ResolvesToArray $array): BaseExpression\ResolvesToArray
    {
        return BaseExpression::range(
            0,
            BaseExpression::size($array),
            1,
        );
    }

    /**
     * For a given list, generates objects in the form {k: <value>}
     */
    private static function generateKeyObjectList(BaseExpression\ResolvesToArray $array): BaseExpression\MapOperator
    {
        return BaseExpression::map(
            input: self::getListKeyRange($array),
            in: object(k: BaseExpression::variable('this')),
        );
    }

    /**
     * For a given list, generates objects in the form {v: <value>}
     */
    private static function generateValueObjectList(BaseExpression\ResolvesToArray $array): BaseExpression\MapOperator
    {
        return BaseExpression::map(
            input: $array,
            in: object(v: BaseExpression::variable('this')),
        );
    }
}
