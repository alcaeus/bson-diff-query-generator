<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\QueryGenerator;

use Alcaeus\BsonDiffQueryGenerator\QueryGenerator\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Query::class)]
class QueryTest extends TestCase
{
    public function testStoresValues(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar' => true],
        );

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['bar' => true], $query->unset);
    }

    public function testWithSet(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar' => true],
        );

        $query = $query->with(set: ['foo' => 'baz']);

        self::assertSame(['foo' => 'baz'], $query->set);
        self::assertSame(['bar' => true], $query->unset);
    }

    public function testWithUnset(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar' => true],
        );

        $query = $query->with(unset: ['baz' => true]);

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['baz' => true], $query->unset);
    }

    public function testCombineWithSet(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar' => true],
        );

        $query = $query->combineWith(set: ['baz' => 'foo']);

        self::assertSame(['foo' => 'bar', 'baz' => 'foo'], $query->set);
        self::assertSame(['bar' => true], $query->unset);
    }

    public function testCombineWithUnset(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar' => true],
        );

        $query = $query->combineWith(unset: ['baz' => true]);

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['bar' => true, 'baz' => true], $query->unset);
    }

    public function testCombineWithQuery(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar' => true],
        );

        $query = $query->combineWithQuery(
            new Query(
                ['baz' => 'foo'],
                ['foobar' => true],
            )
        );

        self::assertSame(['foo' => 'bar', 'baz' => 'foo'], $query->set);
        self::assertSame(['bar' => true, 'foobar' => true], $query->unset);
    }

    public function testCombineWithQueryDuplicateKeys(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar' => true],
        );

        $query = $query->combineWithQuery(
            new Query(
                ['foo' => 'baz'],
                ['foobar' => true],
            )
        );

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['bar' => true, 'foobar' => true], $query->unset);
    }

    public function testCombineWithPrefixedQuery(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar' => true],
        );

        $query = $query->combineWithPrefixedQuery(
            new Query(
                ['baz' => 'foo'],
                ['foobar' => true],
            ),
            'baz',
        );

        self::assertSame(['foo' => 'bar', 'baz.baz' => 'foo'], $query->set);
        self::assertSame(['bar' => true, 'baz.foobar' => true], $query->unset);
    }
}
