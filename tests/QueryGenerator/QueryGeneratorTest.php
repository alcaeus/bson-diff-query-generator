<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\QueryGenerator;

use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use Alcaeus\BsonDiffQueryGenerator\QueryGenerator\Query;
use Alcaeus\BsonDiffQueryGenerator\QueryGenerator\QueryGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryGenerator::class)]
#[UsesClass(Query::class)]
#[UsesClass(ObjectDiff::class)]
#[UsesClass(ValueDiff::class)]
class QueryGeneratorTest extends TestCase
{
    public function testSimpleObject(): void
    {
        $diff = new ObjectDiff(
            ['foo' => 'bar'],
            ['bar' => new ValueDiff('baz')],
            ['baz'],
        );

        $query = (new QueryGenerator())->generateQueryObject($diff);

        self::assertSame(
            ['foo' => 'bar', 'bar' => 'baz'],
            $query->set,
        );
        self::assertSame(
            ['baz' => true],
            $query->unset,
        );
    }

    public function testObjectWithNestedDiff(): void
    {
        $diff = new ObjectDiff(
            ['foo' => (object) ['foo' => 'bar']],
            [
                'bar' => new ValueDiff('baz'),
                'nested' => new ObjectDiff(
                    ['foo' => 'bar'],
                    ['bar' => new ValueDiff('baz')],
                    ['baz'],
                ),
            ],
            ['baz'],
        );

        $query = (new QueryGenerator())->generateQueryObject($diff);

        self::assertEquals(
            [
                'foo' => (object) ['foo' => 'bar'],
                'bar' => 'baz',
                'nested.foo' => 'bar',
                'nested.bar' => 'baz',
            ],
            $query->set,
        );
        self::assertSame(
            ['baz' => true, 'nested.baz' => true],
            $query->unset,
        );
    }
}
