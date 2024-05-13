<?php


use Alcaeus\BsonDiffQueryGenerator\ArrayDiff;
use Alcaeus\BsonDiffQueryGenerator\ArrayDiffer;
use Alcaeus\BsonDiffQueryGenerator\Differ;
use Alcaeus\BsonDiffQueryGenerator\EmptyDiff;
use Alcaeus\BsonDiffQueryGenerator\ValueDiff;
use Alcaeus\BsonDiffQueryGenerator\ValueDiffer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayDiffer::class)]
#[UsesClass(ArrayDiff::class)]
#[UsesClass(Differ::class)]
#[UsesClass(EmptyDiff::class)]
#[UsesClass(ValueDiff::class)]
#[UsesClass(ValueDiffer::class)]
class ArrayDifferTest extends TestCase
{
    public function testWithSameArray(): void
    {
        self::assertEquals(
            new EmptyDiff(),
            (new ArrayDiffer())->getDiff(['foo' => 'bar'], ['foo' => 'bar']),
        );
    }

    public function testWithNull(): void
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

    public function testAddedValues(): void
    {
        $old = ['foo' => 'bar'];
        $new = ['foo' => 'bar', 'bar' => 'baz'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ArrayDiff::class, $diff);
        $this->assertSame(['bar' => 'baz'], $diff->addedValues);
    }

    public function testChangedValues(): void
    {
        $old = ['foo' => 'bar'];
        $new = ['foo' => 'foo', 'bar' => 'baz'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ArrayDiff::class, $diff);
        $this->assertEquals(['foo' => new ValueDiff('foo')], $diff->changedValues);
    }

    public function testChangedValuesWithNullValue(): void
    {
        $old = ['foo' => 'bar'];
        $new = ['foo' => null, 'bar' => 'baz'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ArrayDiff::class, $diff);
        $this->assertEquals(['foo' => new ValueDiff(null)], $diff->changedValues);
    }

    public function testChangedValuesContainRecursiveDiff(): void
    {
        $old = ['foo' => ['bar' => 'baz']];
        $new = ['foo' => ['baz' => 'foo']];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ArrayDiff::class, $diff);

        $this->assertEquals(
            [
                'foo' => new ArrayDiff(['baz' => 'foo'], [], ['bar']),
            ],
            $diff->changedValues,
        );
    }

    public function testRemovedKeys(): void
    {
        $old = ['foo' => 'bar'];
        $new = ['bar' => 'baz'];

        $diff = (new ArrayDiffer())->getDiff($old, $new);

        self::assertInstanceOf(ArrayDiff::class, $diff);
        $this->assertSame(['foo'], $diff->removedKeys);
    }
}
