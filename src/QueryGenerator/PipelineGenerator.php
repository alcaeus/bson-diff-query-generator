<?php

namespace Alcaeus\BsonDiffQueryGenerator\QueryGenerator;

use MongoDB\Builder\Expression;
use function MongoDB\object;

class PipelineGenerator
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
                        // Generate a list of objects { k: <index> }
                        Expression::map(
                            // Generate the range of indexes since it's not available any different way
                            input: Expression::range(
                                0,
                                Expression::size($array),
                                1,
                            ),
                            // The key for $arrayToObject must be a string
                            in: object(k: Expression::toString(Expression::variable('this'))),
                        ),
                        // Generate a list of objects { v: <element> }
                        Expression::map(
                            input: $array,
                            in: object(v: Expression::variable('this')),
                        ),
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
                // Convert an object to an array containing { k: <key>, v: <value> } objects
                input: Expression::objectToArray($object),
                sortBy: object(k: 1)
            ),
            in: Expression::variable('this.v'),
        );
    }
}
