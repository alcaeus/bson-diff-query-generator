<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Diff;

use Alcaeus\BsonDiffQueryGenerator\Diff\ListDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\EmptyDiff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListDiff::class)]
#[UsesClass(EmptyDiff::class)]
final class ListDiffTest extends TestCase
{
    public function testInitialValues(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedKeys = ['baz'];

        $diff = new ListDiff($addedValues, $changedValues, $removedKeys);
        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedKeys, $diff->removedKeys);
    }

    public function testWithAddedValues(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedKeys = ['baz'];

        $diff = new ListDiff([], $changedValues, $removedKeys);
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

        $diff = new ListDiff($addedValues, [], $removedKeys);
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

        $diff = new ListDiff($addedValues, $changedValues, []);
        $diff = $diff->with(removedKeys: $removedKeys);

        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedKeys, $diff->removedKeys);
    }
}
