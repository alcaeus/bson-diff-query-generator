<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Update;

use Alcaeus\BsonDiffQueryGenerator\Update\Expression;
use Alcaeus\BsonDiffQueryGenerator\Tests\FunctionalTestCase;
use Generator;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Builder\Expression as BaseExpression;
use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use function array_shift;
use function iterator_to_array;

#[CoversClass(Expression::class)]
final class ExpressionTest extends FunctionalTestCase
{
    private const TYPEMAP = ['root' => 'array', 'array' => 'array', 'document' => 'object'];

    public static function provideWrappedAndUnwrappedLists(): Generator
    {
        yield 'Scalars' => [
            'unwrapped' => [1, 2, 3, 4, 5],
            'wrapped' => [
                (object) ['k' => 0, 'v' => 1],
                (object) ['k' => 1, 'v' => 2],
                (object) ['k' => 2, 'v' => 3],
                (object) ['k' => 3, 'v' => 4],
                (object) ['k' => 4, 'v' => 5],
            ],
        ];

        yield 'Documents' => [
            'unwrapped' => [
                (object) ['_id' => 1],
                (object) ['_id' => 2],
                (object) ['_id' => 3],
                (object) ['_id' => 4],
                (object) ['_id' => 5],
            ],
            'wrapped' => [
                (object) [
                    'k' => 0,
                    'v' => (object) ['_id' => 1],
                ],
                (object) [
                    'k' => 1,
                    'v' => (object) ['_id' => 2],
                ],
                (object) [
                    'k' => 2,
                    'v' => (object) ['_id' => 3],
                ],
                (object) [
                    'k' => 3,
                    'v' => (object) ['_id' => 4],
                ],
                (object) [
                    'k' => 4,
                    'v' => (object) ['_id' => 5],
                ],
            ],
        ];
    }

    #[DataProvider('provideWrappedAndUnwrappedLists')]
    public function testWrapValuesWithKeys(array $unwrapped, array $wrapped): void
    {
        $collection = $this->getCollection();
        $collection->insertOne(['_id' => 1, 'list' => $unwrapped]);

        $pipeline = new Pipeline(
            Stage::set(list: Expression::wrapValuesWithKeys(BaseExpression::arrayFieldPath('list'))),
        );

        $result = iterator_to_array(
            $collection->aggregate(
                (new BuilderEncoder())->encode($pipeline),
                ['typeMap' => self::TYPEMAP],
            ),
        );

        self::assertEquals(
            [
                '_id' => 1,
                'list' => $wrapped,
            ],
            array_shift($result),
        );
    }

    #[DataProvider('provideWrappedAndUnwrappedLists')]
    public function testExtractValuesFromList(array $unwrapped, array $wrapped): void
    {
        $collection = $this->getCollection();
        $collection->insertOne(['_id' => 1, 'list' => $wrapped]);

        $pipeline = new Pipeline(
            Stage::set(list: Expression::extractValuesFromList(BaseExpression::arrayFieldPath('list'))),
        );

        $result = iterator_to_array(
            $collection->aggregate(
                (new BuilderEncoder())->encode($pipeline),
                ['typeMap' => self::TYPEMAP],
            ),
        );

        self::assertEquals(
            ['_id' => 1, 'list' => $unwrapped],
            array_shift($result),
        );
    }
}
