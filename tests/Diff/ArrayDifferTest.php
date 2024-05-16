<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Diff;

use Alcaeus\BsonDiffQueryGenerator\Diff\ConditionalDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ListDiff;
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
use function DeepCopy\deep_copy;

#[CoversClass(ArrayDiffer::class)]
#[CoversClass(ConditionalDiff::class)]
#[CoversClass(EmptyDiff::class)]
#[CoversClass(ListDiff::class)]
#[CoversClass(ObjectDiff::class)]
#[CoversClass(ValueDiff::class)]
#[UsesClass(Differ::class)]
#[UsesClass(ObjectDiffer::class)]
#[UsesClass(ValueDiffer::class)]
final class ArrayDifferTest extends TestCase
{
    public function testWithSameList(): void
    {
        self::assertEquals(
            new EmptyDiff(),
            (new ArrayDiffer())->getDiff([1, 2, 3], [1, 2, 3]),
        );
    }

    public function testListWithNull(): void
    {
        self::assertEquals(
            new ValueDiff(null),
            (new ArrayDiffer())->getDiff([1, 2, 3], null),
        );

        self::assertEquals(
            new ValueDiff([1, 2, 3]),
            (new ArrayDiffer())->getDiff(null, [1, 2, 3]),
        );
    }

    public function testAddedValuesToList(): void
    {
        $old = [1, 2, 3];
        $new = [1, 2, 3, 4];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ListDiff::class, $diff);
        $this->assertSame([3 => 4], $diff->addedValues);
    }

    public function testChangedValuesInList(): void
    {
        $old = [1, 2, 3];
        $new = [1, 4, 3];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ListDiff::class, $diff);
        $this->assertEquals([1 => new ValueDiff(4)], $diff->changedValues);
    }

    public function testChangedValuesInListWithNullValue(): void
    {
        $old = [1, 2, 3];
        $new = [1, null, 3];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ListDiff::class, $diff);
        $this->assertEquals([1 => new ValueDiff(null)], $diff->changedValues);
    }

    public function testChangedValuesInListContainRecursiveDiff(): void
    {
        $old = [[1, 2, 3]];
        $new = [[1, 2, 3 => 4]];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ListDiff::class, $diff);

        $this->assertEquals(
            [
                0 => new ListDiff([3 => 4], [], [2]),
            ],
            $diff->changedValues,
        );
    }

    public function testRemovedKeysInList(): void
    {
        $old = [1, 2, 3];
        $new = [1, 2 => 3];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ListDiff::class, $diff);
        $this->assertSame([1], $diff->removedKeys);
    }

    public function testWithSameStruct(): void
    {
        self::assertEquals(
            new EmptyDiff(),
            (new ArrayDiffer())->getDiff(['foo' => 'bar'], ['foo' => 'bar']),
        );
    }

    public function testStructWithNull(): void
    {
        self::assertEquals(
            new ValueDiff(null),
            (new ArrayDiffer())->getDiff(['foo' => 'bar'], null),
        );

        self::assertEquals(
            new ValueDiff(['foo' => 'bar']),
            (new ArrayDiffer())->getDiff(null, ['foo' => 'bar']),
        );
    }

    public function testAddedValuesInStruct(): void
    {
        $old = ['foo' => 'bar'];
        $new = ['foo' => 'bar', 'bar' => 'baz'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);
        $this->assertSame(['bar' => 'baz'], $diff->addedValues);
    }

    public function testChangedValuesInStruct(): void
    {
        $old = ['foo' => 'bar'];
        $new = ['foo' => 'foo', 'bar' => 'baz'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);
        $this->assertEquals(['foo' => new ValueDiff('foo')], $diff->changedValues);
    }

    public function testChangedValuesInStructWithNullValue(): void
    {
        $old = ['foo' => 'bar'];
        $new = ['foo' => null, 'bar' => 'baz'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);
        $this->assertEquals(['foo' => new ValueDiff(null)], $diff->changedValues);
    }

    public function testChangedValuesInStructContainRecursiveDiff(): void
    {
        $old = ['foo' => ['bar' => 'baz']];
        $new = ['foo' => ['baz' => 'foo']];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);

        $this->assertEquals(
            [
                'foo' => new ObjectDiff(['baz' => 'foo'], [], ['bar']),
            ],
            $diff->changedValues,
        );
    }

    public function testRemovedKeysInStruct(): void
    {
        $old = ['foo' => 'bar'];
        $new = ['bar' => 'baz'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ObjectDiff::class, $diff);
        $this->assertSame(['foo'], $diff->removedFields);
    }

    public function testListToNonListArray(): void
    {
        $old = [1, 2, 3];
        $new = ['foo' => 'bar'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ValueDiff::class, $diff);
        $this->assertSame($new, $diff->value);
    }

    public function testNonListToListArray(): void
    {
        $old = ['foo' => 'bar'];
        $new = [1, 2, 3];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ValueDiff::class, $diff);
        $this->assertSame($new, $diff->value);
    }

    public function testListToListWithOutOfOrderKeys(): void
    {
        $old = [1, 2, 3];
        $new = [0 => 1, 2 => 3, 1 => 2];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ValueDiff::class, $diff);
        $this->assertSame($new, $diff->value);
    }

    public function testListOfEmbeddedDocumentsUsesFilter(): void
    {
        $old = [
            (object) ['_id' => 1],
            (object) ['_id' => 2],
            (object) ['_id' => 3],
        ];

        /** @var list<object{_id: int}> $new */
        $new = deep_copy($old);
        unset($new[1]);
        $new[2]->foo = 'bar';
        $new[] = (object) ['_id' => 4];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ListDiff::class, $diff);

        $this->assertEquals([3 => (object) ['_id' => 4]], $diff->addedValues);
        $this->assertEquals([2 => new ConditionalDiff(3, new ObjectDiff(['foo' => 'bar']))], $diff->changedValues);
        $this->assertEquals([new ConditionalDiff(2)], $diff->removedKeys);
    }
}
