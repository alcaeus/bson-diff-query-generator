<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\QueryGenerator;

use Alcaeus\BsonDiffQueryGenerator\QueryGenerator\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Query::class)]
final class QueryTest extends TestCase
{
    public function testStoresValues(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['bar'], $query->unset);
        self::assertSame(['baz' => [1]], $query->push);
    }

    public function testWithSet(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->with(set: ['foo' => 'baz']);

        self::assertSame(['foo' => 'baz'], $query->set);
        self::assertSame(['bar'], $query->unset);
        self::assertSame(['baz' => [1]], $query->push);
    }

    public function testWithUnset(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->with(unset: ['baz']);

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['baz'], $query->unset);
        self::assertSame(['baz' => [1]], $query->push);
    }

    public function testWithPush(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->with(push: ['qaz' => [3]]);

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['bar'], $query->unset);
        self::assertSame(['qaz' => [3]], $query->push);
    }

    public function testCombineWithSet(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->combineWith(set: ['baz' => 'foo']);

        self::assertSame(['foo' => 'bar', 'baz' => 'foo'], $query->set);
        self::assertSame(['bar'], $query->unset);
        self::assertSame(['baz' => [1]], $query->push);
    }

    public function testCombineWithUnset(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->combineWith(unset: ['baz']);

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['bar', 'baz'], $query->unset);
        self::assertSame(['baz' => [1]], $query->push);
    }

    public function testCombineWithPush(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->combineWith(push: ['qaz' => [3]]);

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['bar'], $query->unset);
        self::assertSame(['baz' => [1], 'qaz' => [3]], $query->push);
    }

    public function testCombineWithQuery(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->combineWithQuery(
            new Query(
                ['baz' => 'foo'],
                ['foobar'],
                ['qaz' => [3]],
            )
        );

        self::assertSame(['foo' => 'bar', 'baz' => 'foo'], $query->set);
        self::assertSame(['bar', 'foobar'], $query->unset);
        self::assertSame(['baz' => [1], 'qaz' => [3]], $query->push);
    }

    public function testCombineWithQueryDuplicateKeys(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->combineWithQuery(
            new Query(
                ['foo' => 'baz'],
                ['foobar'],
                ['qaz' => [3]],
            )
        );

        self::assertSame(['foo' => 'bar'], $query->set);
        self::assertSame(['bar', 'foobar'], $query->unset);
        self::assertSame(['baz' => [1], 'qaz' => [3]], $query->push);
    }

    public function testCombineWithPrefixedQuery(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->combineWithPrefixedQuery(
            new Query(
                ['baz' => 'foo'],
                ['foobar'],
            ),
            'qaz',
        );

        self::assertSame(['foo' => 'bar', 'qaz.baz' => 'foo'], $query->set);
        self::assertSame(['bar', 'qaz.foobar'], $query->unset);
        self::assertSame(['baz' => [1]], $query->push);
        self::assertSame([], $query->lists);
    }

    public function testCombineWithPrefixedList(): void
    {
        $query = new Query(
            ['foo' => 'bar'],
            ['bar'],
            ['baz' => [1]],
        );

        $query = $query->combineWithPrefixedQuery(
            new Query(
                ['baz' => 'foo'],
                ['foobar'],
                // An empty name means we're pushing to the element we're prefixing with
                ['' => [3]],
            ),
            'qaz',
            isList: true,
        );

        self::assertSame(['foo' => 'bar', 'qaz.baz' => 'foo'], $query->set);
        self::assertSame(['bar', 'qaz.foobar'], $query->unset);
        self::assertSame(['baz' => [1], 'qaz' => [3]], $query->push);
        self::assertSame(['qaz'], $query->lists);
    }
}
