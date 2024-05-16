<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Update;

use Alcaeus\BsonDiffQueryGenerator\Diff\ArrayDiffer;
use Alcaeus\BsonDiffQueryGenerator\Diff\Diff;
use Alcaeus\BsonDiffQueryGenerator\Diff\Differ;
use Alcaeus\BsonDiffQueryGenerator\Diff\ListDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiffer;
use Alcaeus\BsonDiffQueryGenerator\Update\Expression;
use Alcaeus\BsonDiffQueryGenerator\Update\Update;
use Alcaeus\BsonDiffQueryGenerator\Update\UpdateGenerator;
use MongoDB\Builder\Expression as BaseExpression;
use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateGenerator::class)]
#[UsesClass(ArrayDiffer::class)]
#[UsesClass(Differ::class)]
#[UsesClass(ListDiff::class)]
#[UsesClass(Expression::class)]
#[UsesClass(Update::class)]
#[UsesClass(ObjectDiff::class)]
#[UsesClass(ValueDiff::class)]
#[UsesClass(ValueDiffer::class)]
final class UpdateGeneratorTest extends TestCase
{
    public function testSimpleObject(): void
    {
        $diff = new ObjectDiff(
            ['foo' => 'bar'],
            ['bar' => new ValueDiff('baz')],
            ['baz'],
        );

        self::assertEquals(
            new Pipeline(
                Stage::set(foo: 'bar', bar: 'baz'),
                Stage::unset('baz'),
            ),
            $this->generateUpdatePipeline($diff),
        );
    }

    public function testObjectWithNestedDiff(): void
    {
        $diff = new ObjectDiff(
            ['foo' => (object) ['foo' => 'bar']],
            [
                'bar' => new ValueDiff('baz'),
                'nested' => new ObjectDiff(
                    ['foo' => 'bar'],
                    ['bar' => new ValueDiff('baz')],
                    ['baz'],
                ),
            ],
            ['baz'],
        );

        self::assertEquals(
            new Pipeline(
                Stage::set(...[
                    'foo' => (object) ['foo' => 'bar'],
                    'bar' => 'baz',
                    'nested.foo' => 'bar',
                    'nested.bar' => 'baz',
                ]),
                Stage::unset('baz', 'nested.baz'),
            ),
            $this->generateUpdatePipeline($diff),
        );
    }

    public function testObjectWithList(): void
    {
        $list = [0, 1, 2, 3, 4, 5];
        $old = [
            'list' => $list,
        ];

        unset($list[0], $list[3], $list[5]);
        $list[] = 6;

        $new = [
            'list' => $list,
        ];

        $diff = $this->generateDiff($old, $new);
        self::assertInstanceOf(ObjectDiff::class, $diff);

        self::assertEquals(
            new Pipeline(
                Stage::set(list: Expression::listToObject(BaseExpression::arrayFieldPath('list'))),
                Stage::unset('list.0', 'list.3', 'list.5'),
                Stage::set(list: Expression::objectToList(BaseExpression::objectFieldPath('list'))),
                Stage::set(list: BaseExpression::concatArrays(BaseExpression::arrayFieldPath('list'), [6])),
            ),
            $this->generateUpdatePipeline($diff),
        );
    }

    public function testObjectWithNestedList(): void
    {
        $list = [0, 1, 2, 3, 4, 5];
        $old = [
            'nested' => ['list' => $list],
        ];

        unset($list[0], $list[3], $list[5]);
        $list[] = 6;

        $new = [
            'nested' => ['list' => $list],
        ];

        $diff = $this->generateDiff($old, $new);
        self::assertInstanceOf(ObjectDiff::class, $diff);

        self::assertEquals(
            new Pipeline(
                Stage::set(...['nested.list' => Expression::listToObject(BaseExpression::arrayFieldPath('nested.list'))]),
                Stage::unset('nested.list.0', 'nested.list.3', 'nested.list.5'),
                Stage::set(...['nested.list' => Expression::objectToList(BaseExpression::objectFieldPath('nested.list'))]),
                Stage::set(...['nested.list' => BaseExpression::concatArrays(BaseExpression::arrayFieldPath('nested.list'), [6])]),
            ),
            $this->generateUpdatePipeline($diff),
        );
    }

    public function testObjectWithListNestedInList(): void
    {
        $old = [
            'list' => [
                [1, 2, 3, 4],
                [1, 4, 9, 16],
            ],
        ];

        $new = $old;

        unset($new['list'][0][1], $new['list'][1][2]);
        $new['list'][0][0] = 0;
        $new['list'][0][] = 5;
        $new['list'][1][0] = -1;
        $new['list'][] = [-2, -3];

        $diff = $this->generateDiff($old, $new);
        self::assertInstanceOf(ObjectDiff::class, $diff);

        self::assertEquals(
            new Pipeline(
                Stage::set(...['list' => Expression::listToObject(BaseExpression::arrayFieldPath('list'))]),
                Stage::set(...['list.0' => Expression::listToObject(BaseExpression::arrayFieldPath('list.0'))]),
                Stage::set(...['list.1' => Expression::listToObject(BaseExpression::arrayFieldPath('list.1'))]),
                Stage::set(...['list.0.0' => 0, 'list.1.0' => -1]),
                Stage::unset('list.0.1', 'list.1.2'),
                Stage::set(...['list.1' => Expression::objectToList(BaseExpression::objectFieldPath('list.1'))]),
                Stage::set(...['list.0' => Expression::objectToList(BaseExpression::objectFieldPath('list.0'))]),
                Stage::set(...['list.0' => BaseExpression::concatArrays(BaseExpression::arrayFieldPath('list.0'), [5])]),
                Stage::set(...['list' => Expression::objectToList(BaseExpression::objectFieldPath('list'))]),
                Stage::set(...['list' => BaseExpression::concatArrays(BaseExpression::arrayFieldPath('list'), [[-2, -3]])]),
            ),
            $this->generateUpdatePipeline($diff),
        );
    }

    private function generateDiff(mixed $old, mixed $new): Diff
    {
        return (new Differ())->getDiff($old, $new);
    }

    private function generateUpdatePipeline(ObjectDiff $diff): Pipeline
    {
        return (new UpdateGenerator())->generateUpdatePipeline($diff);
    }
}
