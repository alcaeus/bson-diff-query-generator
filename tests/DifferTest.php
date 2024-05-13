<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests;

use Alcaeus\BsonDiffQueryGenerator\ArrayDiff;
use Alcaeus\BsonDiffQueryGenerator\ArrayDiffer;
use Alcaeus\BsonDiffQueryGenerator\Differ;
use Alcaeus\BsonDiffQueryGenerator\EmptyDiff;
use Alcaeus\BsonDiffQueryGenerator\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\ObjectDiffer;
use Alcaeus\BsonDiffQueryGenerator\ValueDiff;
use Alcaeus\BsonDiffQueryGenerator\ValueDiffer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Differ::class)]
#[CoversClass(EmptyDiff::class)]
#[UsesClass(ArrayDiff::class)]
#[UsesClass(ArrayDiffer::class)]
#[UsesClass(ValueDiff::class)]
#[UsesClass(ValueDiffer::class)]
#[UsesClass(ObjectDiff::class)]
#[UsesClass(ObjectDiffer::class)]
class DifferTest extends TestCase
{
    #[DataProvider('provideDifferTests')]
    public function testValueDiffer($expected, $old, $new): void
    {
        self::assertEquals(
            $expected,
            (new Differ())->getDiff($old, $new),
        );
    }

    public static function provideDifferTests(): Generator
    {
        $emptyDiff = new EmptyDiff();

        yield 'Null values' => [$emptyDiff, null, null];

        yield 'Scalar values' => [new ValueDiff('bar'), 'foo', 'bar'];

        yield 'Old scalar, new array' => [new ValueDiff(['bar' => 'baz']), 'foo', ['bar' => 'baz']];

        yield 'Old array, new scalar' => [new ValueDiff('bar'), ['foo' => 'bar'], 'bar'];

        yield 'Old scalar, new object' => [new ValueDiff((object) ['bar' => 'baz']), 'foo', (object) ['bar' => 'baz']];

        yield 'Old object, new scalar' => [new ValueDiff('bar'), (object) ['foo' => 'bar'], 'bar'];
    }

    public function testValueDifferWithArray(): void
    {
        self::assertInstanceOf(
            ArrayDiff::class,
            (new Differ())->getDiff(['foo' => 'bar'], ['bar' => 'baz']),
        );
    }

    public function testValueDifferWithObject(): void
    {
        self::assertInstanceOf(
            ObjectDiff::class,
            (new Differ())->getDiff((object) ['foo' => 'bar'], (object) ['bar' => 'baz']),
        );
    }
}
