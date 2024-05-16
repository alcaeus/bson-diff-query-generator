<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Update;

use Alcaeus\BsonDiffQueryGenerator\Diff\ArrayDiffer;
use Alcaeus\BsonDiffQueryGenerator\Diff\ConditionalDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\Diff;
use Alcaeus\BsonDiffQueryGenerator\Diff\Differ;
use Alcaeus\BsonDiffQueryGenerator\Diff\ListDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiffer;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiff;
use Alcaeus\BsonDiffQueryGenerator\Diff\ValueDiffer;
use Alcaeus\BsonDiffQueryGenerator\Update\Expression;
use Alcaeus\BsonDiffQueryGenerator\Update\UpdateGenerator;
use MongoDB\Builder\Expression as BaseExpression;
use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function DeepCopy\deep_copy;
use function MongoDB\object;

#[CoversClass(UpdateGenerator::class)]
#[UsesClass(ArrayDiffer::class)]
#[UsesClass(ConditionalDiff::class)]
#[UsesClass(Differ::class)]
#[UsesClass(ListDiff::class)]
#[UsesClass(Expression::class)]
#[UsesClass(ObjectDiff::class)]
#[UsesClass(ObjectDiffer::class)]
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
                Stage::set(
                    foo: BaseExpression::literal('bar'),
                    bar: BaseExpression::literal('baz'),
                    baz: BaseExpression::variable('REMOVE'),
                ),
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
                Stage::set(
                    foo: BaseExpression::literal((object) ['foo' => 'bar']),
                    bar: BaseExpression::literal('baz'),
                    nested: BaseExpression::mergeObjects(
                        BaseExpression::fieldPath('nested'),
                        [
                            'foo' => BaseExpression::literal('bar'),
                            'bar' => BaseExpression::literal('baz'),
                            'baz' => BaseExpression::variable('REMOVE'),
                        ],
                    ),
                    baz: BaseExpression::variable('REMOVE'),
                ),
            ),
            $this->generateUpdatePipeline($diff),
        );
    }

    public function testObjectWithList(): void
    {
        $list = [0, 1, 2, 3, 4, 5];
        $old = ['list' => $list];

        unset($list[0], $list[3], $list[5]);
        $list[] = 6;

        $new = ['list' => $list];

        $diff = $this->generateDiff($old, $new);
        self::assertInstanceOf(ObjectDiff::class, $diff);

        self::assertEquals(
            new Pipeline(
                Stage::set(list: BaseExpression::concatArrays(
                    Expression::extractValuesFromList(
                        BaseExpression::filter(
                            input: Expression::wrapValuesWithKeys(BaseExpression::fieldPath('list')),
                            cond: BaseExpression::and(
                                BaseExpression::ne(BaseExpression::variable('this.k'), 0),
                                BaseExpression::ne(BaseExpression::variable('this.k'), 3),
                                BaseExpression::ne(BaseExpression::variable('this.k'), 5),
                            ),
                        ),
                    ),
                    [BaseExpression::literal(6)],
                )),
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
                Stage::set(nested: BaseExpression::mergeObjects(
                    BaseExpression::fieldPath('nested'),
                    [
                        'list' => BaseExpression::concatArrays(
                            Expression::extractValuesFromList(
                                BaseExpression::filter(
                                    input: Expression::wrapValuesWithKeys(BaseExpression::fieldPath('nested.list')),
                                    cond: BaseExpression::and(
                                        BaseExpression::ne(BaseExpression::variable('this.k'), 0),
                                        BaseExpression::ne(BaseExpression::variable('this.k'), 3),
                                        BaseExpression::ne(BaseExpression::variable('this.k'), 5),
                                    ),
                                ),
                            ),
                            [BaseExpression::literal(6)],
                        ),
                    ],
                )),
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
                // $concatArrays is expected to append the new element (-2, -3)
                Stage::set(list: BaseExpression::concatArrays(
                    // Extract values from the update operation
                    Expression::extractValuesFromList(
                        // Actual update operation
                        BaseExpression::map(
                            // wrap values along with keys for identification
                            input: Expression::wrapValuesWithKeys(BaseExpression::fieldPath('list')),
                            // Main switch to update elements
                            in: BaseExpression::switch(
                                branches: [
                                    BaseExpression::case(
                                        // First element, update first element to 0, drop second element, append 5
                                        case: BaseExpression::eq(BaseExpression::variable('this.k'), 0),
                                        // $mergeObjects ensures we're working on this.v
                                        then: BaseExpression::mergeObjects(
                                            BaseExpression::variable('this'),
                                            // $concatArrays appends 5
                                            object(v: BaseExpression::concatArrays(
                                                Expression::extractValuesFromList(
                                                    // $filter removes item with key 1
                                                    BaseExpression::filter(
                                                        // $map to update element with key 0 to 0
                                                        BaseExpression::map(
                                                            input: Expression::wrapValuesWithKeys(BaseExpression::variable('this.v')),
                                                            in: BaseExpression::switch(
                                                                branches: [
                                                                    BaseExpression::case(
                                                                        case: BaseExpression::eq(BaseExpression::variable('this.k'), 0),
                                                                        then: BaseExpression::mergeObjects(
                                                                            BaseExpression::variable('this'),
                                                                            object(v: BaseExpression::literal(0)),
                                                                        ),
                                                                    ),
                                                                ],
                                                                default: BaseExpression::variable('this'),
                                                            ),
                                                        ),
                                                        BaseExpression::and(
                                                            BaseExpression::ne(BaseExpression::variable('this.k'), 1),
                                                        ),
                                                    ),
                                                ),
                                                [BaseExpression::literal(5)],
                                            )),
                                        ),
                                    ),
                                    BaseExpression::case(
                                        case: BaseExpression::eq(BaseExpression::variable('this.k'), 1),
                                        then: BaseExpression::mergeObjects(
                                            BaseExpression::variable('this'),
                                            object(v: Expression::extractValuesFromList(
                                                BaseExpression::filter(
                                                    BaseExpression::map(
                                                        input: Expression::wrapValuesWithKeys(BaseExpression::variable('this.v')),
                                                        in: BaseExpression::switch(
                                                            branches: [
                                                                BaseExpression::case(
                                                                    case: BaseExpression::eq(BaseExpression::variable('this.k'), 0),
                                                                    then: BaseExpression::mergeObjects(
                                                                        BaseExpression::variable('this'),
                                                                        object(v: BaseExpression::literal(-1)),
                                                                    ),
                                                                ),
                                                            ],
                                                            default: BaseExpression::variable('this'),
                                                        ),
                                                    ),
                                                    BaseExpression::and(
                                                        BaseExpression::ne(BaseExpression::variable('this.k'), 2),
                                                    ),
                                                ),
                                            )),
                                        ),
                                    ),
                                ],
                                default: BaseExpression::variable('this'),
                            ),
                        ),
                    ),
                    [BaseExpression::literal([-2, -3])],
                )),
            ),
            $this->generateUpdatePipeline($diff),
        );
    }

    public function testObjectWithEmbeddedDocuments(): void
    {
        $old = (object) [
            'list' => [
                (object) ['_id' => 1, 'foo' => 'bar'],
                (object) ['_id' => 2, 'foo' => 'baz'],
            ],
        ];

        /** @var object{list: list<object{_id: int, foo: string}>} $new */
        $new = deep_copy($old);

        unset($new->list[1]);
        $new->list[0]->foo = 'new_foo';
        $new->list[] = (object) ['_id' => 3, 'foo' => 'qaz'];

        $diff = $this->generateDiff($old, $new);
        self::assertInstanceOf(ObjectDiff::class, $diff);

        self::assertEquals(
            new Pipeline(
                Stage::set(
                    list: BaseExpression::concatArrays(
                        Expression::extractValuesFromList(
                            BaseExpression::filter(
                                BaseExpression::map(
                                    Expression::wrapValuesWithKeys(BaseExpression::fieldPath('list')),
                                    BaseExpression::switch(
                                        branches: [
                                            BaseExpression::case(
                                                case: BaseExpression::eq(BaseExpression::variable('this.v._id'), 1),
                                                then: BaseExpression::mergeObjects(
                                                    BaseExpression::variable('this'),
                                                    object(v: BaseExpression::mergeObjects(
                                                        BaseExpression::variable('this.v'),
                                                        ['foo' => BaseExpression::literal('new_foo')],
                                                    )),
                                                ),
                                            ),
                                        ],
                                        default: BaseExpression::variable('this'),
                                    ),
                                ),
                                BaseExpression::and(
                                    BaseExpression::ne(BaseExpression::variable('this.v._id'), 2),
                                ),
                            ),
                        ),
                        [BaseExpression::literal((object) ['_id' => 3, 'foo' => 'qaz'])],
                    ),
                ),
            ),
            $this->generateUpdatePipeline($diff),
        );
    }

    public function testGenerateUpdatePipelineForNestedList(): void
    {
        $diff = new ObjectDiff(changedValues: [
            'nested' => new ObjectDiff(changedValues: [
                'list' => new ListDiff(changedValues: [
                    0 => new ValueDiff(1),
                ]),
                'document' => new ObjectDiff(changedValues: [
                    'list' => new ListDiff(changedValues: [
                        0 => new ValueDiff(1),
                    ]),
                ]),
            ]),
        ]);

        self::assertEquals(
            new Pipeline(
                Stage::set(
                    nested: BaseExpression::mergeObjects(
                        BaseExpression::fieldPath('nested'),
                        [
                            'list' => Expression::extractValuesFromList(
                                BaseExpression::map(
                                    input: Expression::wrapValuesWithKeys(BaseExpression::fieldPath('nested.list')),
                                    in: BaseExpression::switch(
                                        branches: [
                                            BaseExpression::case(
                                                case: BaseExpression::eq(BaseExpression::variable('this.k'), 0),
                                                then: BaseExpression::mergeObjects(
                                                    BaseExpression::variable('this'),
                                                    object(v: BaseExpression::literal(1)),
                                                ),
                                            ),
                                        ],
                                        default: BaseExpression::variable('this'),
                                    ),
                                ),
                            ),
                            'document' => BaseExpression::mergeObjects(
                                BaseExpression::fieldPath('nested.document'),
                                [
                                    'list' => Expression::extractValuesFromList(
                                        BaseExpression::map(
                                            input: Expression::wrapValuesWithKeys(BaseExpression::fieldPath('nested.document.list')),
                                            in: BaseExpression::switch(
                                                branches: [
                                                    BaseExpression::case(
                                                        case: BaseExpression::eq(BaseExpression::variable('this.k'), 0),
                                                        then: BaseExpression::mergeObjects(
                                                            BaseExpression::variable('this'),
                                                            object(v: BaseExpression::literal(1)),
                                                        ),
                                                    ),
                                                ],
                                                default: BaseExpression::variable('this'),
                                            ),
                                        ),
                                    ),
                                ],
                            ),
                        ],
                    ),
                ),
            ),
            (new UpdateGenerator())->generateUpdatePipeline($diff),
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
