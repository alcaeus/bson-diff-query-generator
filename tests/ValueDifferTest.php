<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests;

use Alcaeus\BsonDiffQueryGenerator\EmptyDiff;
use Alcaeus\BsonDiffQueryGenerator\ValueDiff;
use Alcaeus\BsonDiffQueryGenerator\ValueDiffer;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueDiffer::class)]
#[UsesClass(ValueDiff::class)]
class ValueDifferTest extends TestCase
{
    #[DataProvider('provideDifferTests')]
    public function testValueDiffer($expected, $old, $new): void
    {
        self:self::assertEquals(
            $expected,
            (new ValueDiffer())->getDiff($old, $new),
        );
    }

    public static function provideDifferTests(): Generator
    {
        $emptyDiff = new EmptyDiff();

        yield 'Null values' => [$emptyDiff, null, null];

        yield 'Scalar values' => [new ValueDiff('bar'), 'foo', 'bar'];

        yield 'Array as new value' => [new ValueDiff(['bar' => 'baz']), ['foo' => 'bar'], ['bar' => 'baz']];

        yield 'Object as new value' => [new ValueDiff((object) ['bar' => 'baz']), (object) ['foo' => 'bar'], (object) ['bar' => 'baz']];

        yield 'Same array value' => [$emptyDiff, ['foo' => 'bar'], ['foo' => 'bar']];

        yield 'Same object value' => [new ValueDiff((object) ['foo' => 'bar']), (object) ['foo' => 'bar'], (object) ['foo' => 'bar']];

        $instance = (object) ['foo' => 'bar'];
        yield 'Same object instance' => [$emptyDiff, $instance, $instance];
    }
}
