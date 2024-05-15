<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Diff;

use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueDiff::class)]
final class ValueDiffTest extends TestCase
{
    public function testStoresValue(): void
    {
        $value = uniqid();
        $diff = new ValueDiff($value);

        self::assertSame($value, $diff->value);
    }
}
