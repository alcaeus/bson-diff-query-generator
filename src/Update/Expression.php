<?php

namespace Alcaeus\BsonDiffQueryGenerator\Update;

use MongoDB\Builder\Expression as BaseExpression;

use function MongoDB\object;

/** @internal */
final class Expression
{
    /**
     * Extracts the previously nested value from a list
     */
    public static function extractValuesFromList(BaseExpression\ResolvesToArray $input): BaseExpression\MapOperator
    {
        return BaseExpression::map(
            input: $input,
            in: BaseExpression::variable('this.v'),
        );
    }

    /**
     * For a given list, generates objects in the form { k: <index>, v: <value> } for each element in the list
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
            in: BaseExpression::mergeObjects(BaseExpression::variable('this')),
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
            in: object(k: BaseExpression::variable('this')),
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
