<?php

namespace Alcaeus\BsonDiffQueryGenerator\Update;

use MongoDB\Builder\Expression as BaseExpression;
use function MongoDB\object;

/** @internal */
final class Expression
{
    /**
     * Generates an expression to turn the given array into an object.
     *
     * The resulting object contains numbered keys for each array element
     *
     * @param BaseExpression\ResolvesToArray $array
     * @return BaseExpression\ResolvesToObject
     */
    public static function listToObject(BaseExpression\ResolvesToArray $array): BaseExpression\ResolvesToObject
    {
        return BaseExpression::arrayToObject(
            // Generate a list of objects { k: <index>, v: <element> }
            BaseExpression::map(
                // Generate a list of arrays: [{ k: <index> }, { v: <element} }]
                input: BaseExpression::zip(
                    inputs: [
                        self::generateKeyObjectList($array),
                        self::generateValueObjectList($array),
                    ],
                ),
                in: BaseExpression::mergeObjects(BaseExpression::variable('this')),
            ),
        );
    }

    public static function objectToList(BaseExpression\ResolvesToObject $object): BaseExpression\ResolvesToArray
    {
        // Extract only the value from the list of key/value objects
        return BaseExpression::map(
            // Sort arrays by their key value, as we can't rely on key order in objects
            input: BaseExpression::sortArray(
                // Convert the keys to integers to ensure proper sort order
                input: BaseExpression::map(
                    // Convert an object to an array containing { k: <key>, v: <value> } objects
                    input: BaseExpression::objectToArray($object),
                    in: object(
                        k: BaseExpression::toInt(BaseExpression::variable('this.k')),
                        v: BaseExpression::variable('this.v'),
                    ),
                ),
                sortBy: object(k: 1)
            ),
            in: BaseExpression::variable('this.v'),
        );
    }

    /**
     * For a given list, returns an array containing the indexes for the list
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
     * For a given list, generates objects in the form { k: <value> } to be used in the $arrayToObject operator
     */
    private static function generateKeyObjectList(BaseExpression\ResolvesToArray $array): BaseExpression\MapOperator
    {
        return BaseExpression::map(
            input: self::getListKeyRange($array),
            // The key for $arrayToObject must be a string
            in: object(k: BaseExpression::toString(BaseExpression::variable('this'))),
        );
    }

    /**
     * For a given list, generates objects in the form { v: <value> } to be used in the $arrayToObject operator
     */
    private static function generateValueObjectList(BaseExpression\ResolvesToArray $array): BaseExpression\MapOperator
    {
        return BaseExpression::map(
            input: $array,
            in: object(v: BaseExpression::variable('this')),
        );
    }
}
