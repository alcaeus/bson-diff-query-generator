<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

final readonly class ObjectDiff implements Diff
{
    /**
     * @param array<array-key, mixed> $addedValues
     * @param array<array-key, Diff> $changedValues
     * @param list<array-key> $removedFields
     */
    public function __construct(
        public array $addedValues = [],
        public array $changedValues = [],
        public array $removedFields = [],
    ) {}

    /**
     * @param array<array-key, mixed> $addedValues
     * @param array<array-key, Diff> $changedValues
     * @param list<array-key> $removedFields
     */
    public function with(
        ?array $addedValues = null,
        ?array $changedValues = null,
        ?array $removedFields = null,
    ): self {
        return new self(
            $addedValues ?? $this->addedValues,
            $changedValues ?? $this->changedValues,
            $removedFields ?? $this->removedFields,
        );
    }
}
