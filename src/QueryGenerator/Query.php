<?php

namespace Alcaeus\BsonDiffQueryGenerator\QueryGenerator;

final readonly class Query
{
    /**
     * @param array<string, mixed> $set
     * @param array<string, mixed> $unset
     */
    public function __construct(
        public array $set = [],
        public array $unset = [],
    ) {}

    /**
     * @param array<string, mixed> $set
     * @param array<string, mixed> $unset
     */
    public function with(
        ?array $set = null,
        ?array $unset = null,
    ): self {
        return new self(
            $set ?? $this->set,
            $unset ?? $this->unset,
        );
    }

    /**
     * @param array<string, mixed> $set
     * @param array<string, mixed> $unset
     */
    public function combineWith(array $set = [], array $unset = []): self
    {
        return new self(
            $this->set + $set,
            $this->unset + $unset,
        );
    }

    public function combineWithQuery(self $other): self
    {
        return new self(
            $this->set + $other->set,
            $this->unset + $other->unset,
        );
    }

    public function combineWithPrefixedQuery(self $other, string $prefixKey): self
    {
        return new self(
            $this->set + $this->prefixKeys($other->set, $prefixKey),
            $this->unset + $this->prefixKeys($other->unset, $prefixKey),
        );
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function prefixKeys(array $values, string $prefixKey): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[$prefixKey . '.' . $key] = $value;
        }

        return $result;
    }
}
