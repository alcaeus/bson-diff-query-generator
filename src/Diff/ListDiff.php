<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

/** @internal */
final readonly class ListDiff implements Diff
{
    /**
     * @param array<array-key, mixed> $addedValues
     * @param array<array-key, Diff> $changedValues
     * @param list<array-key|ConditionalDiff> $removedKeys
     */
    public function __construct(
        public array $addedValues = [],
        public array $changedValues = [],
        public array $removedKeys = [],
    ) {}

    /**
     * @param array<array-key, mixed> $addedValues
     * @param array<array-key, Diff> $changedValues
     * @param list<array-key|ConditionalDiff> $removedKeys
     */
    public function with(
        ?array $addedValues = null,
        ?array $changedValues = null,
        ?array $removedKeys = null,
    ): self {
        return new self(
            $addedValues ?? $this->addedValues,
            $changedValues ?? $this->changedValues,
            $removedKeys ?? $this->removedKeys,
        );
    }
}
