<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

/** @internal */
final readonly class ObjectDiff implements Diff
{
    /**
     * @param array<string, mixed> $addedValues
     * @param array<string, Diff>  $changedValues
     * @param list<string>         $removedFields
     */
    public function __construct(
        public array $addedValues = [],
        public array $changedValues = [],
        public array $removedFields = [],
    ) {
    }

    /**
     * @param array<string, mixed> $addedValues
     * @param array<string, Diff>  $changedValues
     * @param list<string>         $removedFields
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
