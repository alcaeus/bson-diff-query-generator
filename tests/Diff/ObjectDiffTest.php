<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Diff;

use Alcaeus\BsonDiffQueryGenerator\Diff\EmptyDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ObjectDiff::class)]
#[UsesClass(EmptyDiff::class)]
final class ObjectDiffTest extends TestCase
{
    public function testInitialValues(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedFields = ['baz'];

        $diff = new ObjectDiff($addedValues, $changedValues, $removedFields);
        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedFields, $diff->removedFields);
    }

    public function testWithAddedValues(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedFields = ['baz'];

        $diff = new ObjectDiff([], $changedValues, $removedFields);
        $diff = $diff->with(addedValues: $addedValues);

        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedFields, $diff->removedFields);
    }

    public function testWithChangedValues(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedFields = ['baz'];

        $diff = new ObjectDiff($addedValues, [], $removedFields);
        $diff = $diff->with(changedValues: $changedValues);

        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedFields, $diff->removedFields);
    }

    public function testWithRemovedFields(): void
    {
        $addedValues = ['foo' => 'bar'];
        $changedValues = ['bar' => new EmptyDiff()];
        $removedFields = ['baz'];

        $diff = new ObjectDiff($addedValues, $changedValues, []);
        $diff = $diff->with(removedFields: $removedFields);

        self::assertSame($addedValues, $diff->addedValues);
        self::assertSame($changedValues, $diff->changedValues);
        self::assertSame($removedFields, $diff->removedFields);
    }
}
