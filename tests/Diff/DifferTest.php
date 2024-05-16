<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Diff;

use Alcaeus\BsonDiffQueryGenerator\Diff\ArrayDiffer;
use Alcaeus\BsonDiffQueryGenerator\Diff\Differ;
use Alcaeus\BsonDiffQueryGenerator\Diff\EmptyDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ListDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiffer;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiffer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Differ::class)]
#[CoversClass(EmptyDiff::class)]
#[UsesClass(ArrayDiffer::class)]
#[UsesClass(ListDiff::class)]
#[UsesClass(ValueDiff::class)]
#[UsesClass(ValueDiffer::class)]
#[UsesClass(ObjectDiff::class)]
#[UsesClass(ObjectDiffer::class)]
final class DifferTest extends TestCase
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

    public function testValueDifferWithList(): void
    {
        self::assertInstanceOf(
            ListDiff::class,
            (new Differ())->getDiff([1, 2, 3], [1, 2]),
        );
    }

    public function testValueDifferWithStruct(): void
    {
        self::assertInstanceOf(
            ObjectDiff::class,
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
