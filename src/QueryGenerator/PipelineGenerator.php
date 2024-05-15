<?php

namespace Alcaeus\BsonDiffQueryGenerator\QueryGenerator;

use MongoDB\Builder\Expression;
use function MongoDB\object;

final class PipelineGenerator
{
    /**
     * Generates an expression to turn the given array into an object.
     *
     * The resulting object contains numbered keys for each array element
     *
     * @param Expression\ResolvesToArray $array
     * @return Expression\ResolvesToObject
     */
    public static function listToObject(Expression\ResolvesToArray $array): Expression\ResolvesToObject
    {
        return Expression::arrayToObject(
            // Generate a list of objects { k: <index>, v: <element> }
            Expression::map(
                // Generate a list of arrays: [{ k: <index> }, { v: <element} }]
                input: Expression::zip(
                    inputs: [
                        self::generateKeyObjectList($array),
                        self::generateValueObjectList($array),
                    ],
                ),
                in: Expression::mergeObjects(Expression::variable('this')),
            ),
        );
    }

    public static function objectToList(Expression\ResolvesToObject $object): Expression\ResolvesToArray
    {
        // Extract only the value from the list of key/value objects
        return Expression::map(
            // Sort arrays by their key value, as we can't rely on key order in objects
            input: Expression::sortArray(
                // Convert the keys to integers to ensure proper sort order
                input: Expression::map(
                    // Convert an object to an array containing { k: <key>, v: <value> } objects
                    input: Expression::objectToArray($object),
                    in: object(
                        k: Expression::toInt(Expression::variable('this.k')),
                        v: Expression::variable('this.v'),
                    ),
                ),
                sortBy: object(k: 1)
            ),
            in: Expression::variable('this.v'),
        );
    }

    /**
     * For a given list, returns an array containing the indexes for the list
     */
    private static function getListKeyRange(Expression\ResolvesToArray $array): Expression\ResolvesToArray
    {
        return Expression::range(
            0,
            Expression::size($array),
            1,
        );
    }

    /**
     * For a given list, generates objects in the form { k: <value> } to be used in the $arrayToObject operator
     */
    private static function generateKeyObjectList(Expression\ResolvesToArray $array): Expression\MapOperator
    {
        return Expression::map(
            input: self::getListKeyRange($array),
            // The key for $arrayToObject must be a string
            in: object(k: Expression::toString(Expression::variable('this'))),
        );
    }

    /**
     * For a given list, generates objects in the form { v: <value> } to be used in the $arrayToObject operator
     */
    private static function generateValueObjectList(Expression\ResolvesToArray $array): Expression\MapOperator
    {
        return Expression::map(
            input: $array,
            in: object(v: Expression::variable('this')),
        );
    }
}
