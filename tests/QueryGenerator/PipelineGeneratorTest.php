<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\QueryGenerator;

use Alcaeus\BsonDiffQueryGenerator\QueryGenerator\PipelineGenerator;
use Alcaeus\BsonDiffQueryGenerator\Tests\FunctionalTestCase;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Builder\Expression;
use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function array_shift;
use function iterator_to_array;

#[CoversClass(PipelineGenerator::class)]
final class PipelineGeneratorTest extends FunctionalTestCase
{
    private const TYPEMAP = ['root' => 'array', 'array' => 'array', 'document' => 'object'];

    public function testListToObject(): void
    {
        $collection = $this->getCollection();
        $collection->insertOne(['_id' => 1, 'list' => [1, 2, 3, 4, 5]]);

        $pipeline = new Pipeline(
            Stage::set(list: PipelineGenerator::listToObject(Expression::arrayFieldPath('list'))),
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
            Stage::set(list: PipelineGenerator::objectToList(Expression::objectFieldPath('list'))),
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
            Stage::set(list: PipelineGenerator::objectToList(Expression::objectFieldPath('list'))),
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
