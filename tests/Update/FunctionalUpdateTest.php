<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests\Update;

use Alcaeus\BsonDiffQueryGenerator\Diff\ObjectDiffer;
use Alcaeus\BsonDiffQueryGenerator\Tests\FunctionalTestCase;
use Alcaeus\BsonDiffQueryGenerator\Update\UpdateGenerator;
use Generator;
use MongoDB\Builder\BuilderEncoder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_shift;
use function array_values;
use function DeepCopy\deep_copy;
use function iterator_to_array;

#[CoversNothing]
final class FunctionalUpdateTest extends FunctionalTestCase
{
    private const TYPEMAP = ['root' => 'object', 'array' => 'array', 'document' => 'object'];

    #[DataProvider('provideTestDocuments')]
    public function testUpdatingDocument(object $old, object $new): void
    {
        $collection = $this->getCollection();
        $collection->insertOne($old);

        $diff = (new ObjectDiffer())->getDiff($old, $new);
        $pipeline = UpdateGenerator::generateUpdatePipelineForDiff($diff);

        $result = iterator_to_array(
            $collection->aggregate(
                (new BuilderEncoder())->encode($pipeline),
                ['typeMap' => self::TYPEMAP],
            ),
        );

        self::assertEquals([$new], $result);
    }

    public static function provideTestDocuments(): Generator
    {
        yield 'Simple Document' => [
            'old' => (object) [
                '_id' => 1,
                'name' => 'alcaeus',
            ],
            'new' => (object) [
                '_id' => 1,
                'name' => 'sueacla',
            ],
        ];

        yield 'Single embedded document' => [
            'old' => (object) [
                '_id' => 1,
                'name' => 'alcaeus',
                'address' => (object) [
                    'line1' => 'xxx',
                    'city' => 'Olching',
                    'country' => 'Germany',
                ],
            ],
            'new' => (object) [
                '_id' => 1,
                'name' => 'sueacla',
                'address' => (object) [
                    'line1' => '<redacted>',
                    'city' => 'Olching',
                    'country' => 'Germany',
                ],
                'phone' => '<redacted>',
            ],
        ];

        yield 'Document With List' => [
            'old' => (object) [
                '_id' => 1,
                'name' => 'alcaeus',
                'languages' => ['German', 'Italian'],
            ],
            'new' => (object) [
                '_id' => 1,
                'name' => 'sueacla',
                'languages' => ['German', 'English'],
            ],
        ];

        yield 'Document With Embedded Documents' => [
            'old' => (object) [
                '_id' => 1,
                'name' => 'alcaeus',
                'addresses' => [
                    (object) [
                        'line1' => 'xxx',
                        'city' => 'Olching',
                        'country' => 'Germany',
                    ],
                ],
            ],
            'new' => (object) [
                '_id' => 1,
                'name' => 'sueacla',
                'addresses' => [
                    (object) [
                        'line1' => '<redacted>',
                        'city' => 'Olching',
                        'country' => 'Germany',
                    ],
                    (object) [
                        'line1' => '<redacted>',
                        'city' => 'Pula',
                        'country' => 'Croatia',
                    ],
                ],
            ],
        ];

        yield 'List in Embedded Document' => [
            'old' => (object) [
                '_id' => 1,
                'name' => 'alcaeus',
                'addresses' => [
                    (object) [
                        'line1' => 'xxx',
                        'city' => 'Olching',
                        'country' => 'Germany',
                        'type' => ['private', 'vacation', 'work'],
                    ],
                ],
            ],
            'new' => (object) [
                '_id' => 1,
                'name' => 'sueacla',
                'addresses' => [
                    (object) [
                        'line1' => '<redacted>',
                        'city' => 'Olching',
                        'country' => 'Germany',
                        'type' => ['private', 'work'],
                    ],
                    (object) [
                        'line1' => '<redacted>',
                        'city' => 'Pula',
                        'country' => 'Croatia',
                        'type' => ['vacation'],
                    ],
                ],
            ],
        ];
    }

    public function testRemovingListItemsBeforePersistingChanges(): void
    {
        $old = (object) [
            '_id' => 1,
            'name' => 'alcaeus',
            'embeddedDocs' => [
                (object) [
                    '_id' => 1,
                    'name' => 'foo',
                ],
                (object) [
                    '_id' => 2,
                    'name' => 'bar',
                ],
                (object) [
                    '_id' => 3,
                    'name' => 'baz',
                ],
            ],
        ];

        $new = deep_copy($old);
        unset($new->embeddedDocs[1]);

        $collection = $this->getCollection();
        $collection->insertOne($old);

        // Update the document directly in the database - this simulates that a document was modified between
        // reading from the database and computing changes

        $collection->updateOne(['_id' => 1], ['$pull' => ['embeddedDocs' => ['_id' => 2]]]);

        $diff = (new ObjectDiffer())->getDiff($old, $new);

        // Use array_values to deal with the missing key
        $new->embeddedDocs = array_values($new->embeddedDocs);

        $pipeline = UpdateGenerator::generateUpdatePipelineForDiff($diff);

        $result = iterator_to_array(
            $collection->aggregate(
                (new BuilderEncoder())->encode($pipeline),
                ['typeMap' => self::TYPEMAP],
            ),
        );

        self::assertEquals([$new], $result);
    }

    public function testModifyingListItemsBeforePersistingChanges(): void
    {
        $old = (object) [
            '_id' => 1,
            'name' => 'alcaeus',
            'embeddedDocs' => [
                (object) [
                    '_id' => 1,
                    'name' => 'foo',
                ],
                (object) [
                    '_id' => 2,
                    'name' => 'bar',
                ],
                (object) [
                    '_id' => 3,
                    'name' => 'baz',
                ],
            ],
        ];

        /** @var object{_id: int, name: string, embeddedDocs: list<object{_id: int, name: string}>} $new */
        $new = deep_copy($old);

        $new->embeddedDocs[1]->name = 'new_bar';
        $new->embeddedDocs[2]->name = 'new_baz';

        $collection = $this->getCollection();
        $collection->insertOne($old);

        // Update the document directly in the database - this simulates that a document was modified between
        // reading from the database and computing changes

        $collection->updateOne(['_id' => 1], ['$pull' => ['embeddedDocs' => ['_id' => 2]]]);

        $diff = (new ObjectDiffer())->getDiff($old, $new);
        $pipeline = UpdateGenerator::generateUpdatePipelineForDiff($diff);

        $result = iterator_to_array(
            $collection->aggregate(
                (new BuilderEncoder())->encode($pipeline),
                ['typeMap' => self::TYPEMAP],
            ),
        );

        self::assertEquals(
            (object) [
                '_id' => 1,
                'name' => 'alcaeus',
                'embeddedDocs' => [
                    (object) [
                        '_id' => 1,
                        'name' => 'foo',
                    ],
                    (object) [
                        '_id' => 3,
                        'name' => 'new_baz',
                    ],
                ],
            ],
            array_shift($result),
        );
    }
}
