<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Diff;

use Alcaeus\BsonDiffQueryGenerator\Diff\ArrayDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ArrayDiffer;
use Alcaeus\BsonDiffQueryGenerator\Diff\Differ;
use Alcaeus\BsonDiffQueryGenerator\Diff\EmptyDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiffer;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiffer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObjectDiffer::class)]
#[UsesClass(ArrayDiff::class)]
#[UsesClass(ArrayDiffer::class)]
#[UsesClass(Differ::class)]
#[UsesClass(EmptyDiff::class)]
#[UsesClass(ObjectDiff::class)]
#[UsesClass(ValueDiff::class)]
#[UsesClass(ValueDiffer::class)]
class ObjectDifferTest extends TestCase
{
    public function testWithSameObjectValue(): void
    {
        self::assertEquals(
            new EmptyDiff(),
            (new ObjectDiffer())->getDiff(
                (object) ['foo' => 'bar'],
                (object) ['foo' => 'bar'],
            ),
        );
    }

    public function testWithSameObjectInstance(): void
    {
        $object = (object) ['foo' => 'bar'];

        self::assertEquals(
            new EmptyDiff(),
            (new ObjectDiffer())->getDiff($object, $object),
        );
    }

    public function testWithNull(): void
    {
        self::assertEquals(
            new ValueDiff(null),
            (new ObjectDiffer())->getDiff((object) ['foo' => 'bar'], null),
        );

        self::assertEquals(
            new ValueDiff((object) ['foo' => 'bar']),
            (new ObjectDiffer())->getDiff(null, (object) ['foo' => 'bar']),
        );
    }

    public function testAddedValues(): void
    {
        $old = (object) ['foo' => 'bar'];
        $new = (object) ['foo' => 'bar', 'bar' => 'baz'];

        $diff = (new ObjectDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);
        $this->assertSame(['bar' => 'baz'], $diff->addedValues);
    }

    public function testChangedValues(): void
    {
        $old = (object) ['foo' => 'bar'];
        $new = (object) ['foo' => 'foo', 'bar' => 'baz'];

        $diff = (new ObjectDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);
        $this->assertEquals(['foo' => new ValueDiff('foo')], $diff->changedValues);
    }

    public function testChangedValuesWithNullValue(): void
    {
        $old = (object) ['foo' => 'bar'];
        $new = (object) ['foo' => null, 'bar' => 'baz'];

        $diff = (new ObjectDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);
        $this->assertEquals(['foo' => new ValueDiff(null)], $diff->changedValues);
    }

    public function testChangedValuesContainRecursiveDiff(): void
    {
        $old = (object) ['foo' => (object) ['bar' => 'baz']];
        $new = (object) ['foo' => (object) ['baz' => 'foo']];

        $diff = (new ObjectDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);

        $this->assertEquals(
            [
                'foo' => new ObjectDiff(['baz' => 'foo'], [], ['bar']),
            ],
            $diff->changedValues,
        );
    }

    public function testRemovedFields(): void
    {
        $old = (object) ['foo' => 'bar'];
        $new = (object) ['bar' => 'baz'];

        $diff = (new ObjectDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);
        $this->assertSame(['foo'], $diff->removedFields);
    }
}
