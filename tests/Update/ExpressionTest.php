<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Update;

use Alcaeus\BsonDiffQueryGenerator\Update\Expression;
use Alcaeus\BsonDiffQueryGenerator\Tests\FunctionalTestCase;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Builder\Expression as BaseExpression;
use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use function array_shift;
use function iterator_to_array;

#[CoversClass(Expression::class)]
final class ExpressionTest extends FunctionalTestCase
{
    private const TYPEMAP = ['root' => 'array', 'array' => 'array', 'document' => 'object'];

    public function testListToObject(): void
    {
        $collection = $this->getCollection();
        $collection->insertOne(['_id' => 1, 'list' => [1, 2, 3, 4, 5]]);

        $pipeline = new Pipeline(
            Stage::set(list: Expression::listToObject(BaseExpression::arrayFieldPath('list'))),
        );

        $result = iterator_to_array(
            $collection->aggregate(
                (new BuilderEncoder())->encode($pipeline),
                ['typeMap' => self::TYPEMAP],
            ),
        );

        self::assertEquals(
            ['_id' => 1, 'list' => (object) [1, 2, 3, 4, 5]],
            array_shift($result),
        );
    }

    public function testObjectToList(): void
    {
        $collection = $this->getCollection();
        $collection->insertOne(['_id' => 1, 'list' => (object) [1, 2, 3, 4, 5]]);

        $pipeline = new Pipeline(
            Stage::set(list: Expression::objectToList(BaseExpression::objectFieldPath('list'))),
        );

        $result = iterator_to_array(
            $collection->aggregate(
                (new BuilderEncoder())->encode($pipeline),
                ['typeMap' => self::TYPEMAP],
            ),
        );

        self::assertEquals(
            ['_id' => 1, 'list' => [1, 2, 3, 4, 5]],
            array_shift($result),
        );
    }

    public function testObjectToListWithOutOfOrderKeys(): void
    {
        $collection = $this->getCollection();

        $object = (object) [
            '1' => 1,
            '10' => 10,
            '11' => 11,
            '2' => 2,
            '0' => 0,
        ];

        $collection->insertOne(['_id' => 1, 'list' => $object]);

        $pipeline = new Pipeline(
            Stage::set(list: Expression::objectToList(BaseExpression::objectFieldPath('list'))),
        );

        $result = iterator_to_array(
            $collection->aggregate(
                (new BuilderEncoder())->encode($pipeline),
                ['typeMap' => self::TYPEMAP],
            ),
        );

        self::assertEquals(
            ['_id' => 1, 'list' => [0, 1, 2, 10, 11]],
            array_shift($result),
        );
    }
}
