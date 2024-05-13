<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Diff;

use Alcaeus\BsonDiffQueryGenerator\Diff\ArrayDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\EmptyDiff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayDiff::class)]
#[UsesClass(EmptyDiff::class)]
class ArrayDiffTest extends TestCase
{
    public function testInitialValues(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedKeys = ['baz'];

        $diff = new ArrayDiff($addedValues, $changedValues, $removedKeys);
        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedKeys, $diff->removedKeys);
    }

    public function testWithAddedValues(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedKeys = ['baz'];

        $diff = new ArrayDiff([], $changedValues, $removedKeys);
        $diff = $diff->with(addedValues: $addedValues);

        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedKeys, $diff->removedKeys);
    }

    public function testWithChangedValues(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedKeys = ['baz'];

        $diff = new ArrayDiff($addedValues, [], $removedKeys);
        $diff = $diff->with(changedValues: $changedValues);

        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedKeys, $diff->removedKeys);
    }

    public function testWithRemovedKeys(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedKeys = ['baz'];

        $diff = new ArrayDiff($addedValues, $changedValues, []);
        $diff = $diff->with(removedKeys: $removedKeys);

        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedKeys, $diff->removedKeys);
    }
}
