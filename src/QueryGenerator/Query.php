<?php

namespace Alcaeus\BsonDiffQueryGenerator\QueryGenerator;

use function array_combine;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function sprintf;

/** @internal */
final readonly class Query
{
    /** @var list<string> */
    public array $unset;

    /**
     * @param array<string, mixed> $set
     * @param array<array-key> $unset
     * @param array<string, list<mixed>> $push
     * @param list<string> $lists
     */
    public function __construct(
        public array $set = [],
        array $unset = [],
        public array $push = [],
        public array $lists = [],
    ) {
        $this->unset = array_map(
            fn (int|string $key): string => (string) $key,
            array_values($unset),
        );
    }

    /**
     * @param array<string, mixed> $set
     * @param list<string> $unset
     * @param array<string, list<mixed>> $push
     */
    public function with(
        ?array $set = null,
        ?array $unset = null,
        ?array $push = null,
    ): self {
        return new self(
            $set ?? $this->set,
            $unset ?? $this->unset,
            $push ?? $this->push,
            $this->lists,
        );
    }

    /**
     * @param array<string, mixed> $set
     * @param list<string> $unset
     * @param array<string, list<mixed>> $push
     */
    public function combineWith(array $set = [], array $unset = [], array $push = []): self
    {
        return new self(
            $this->set + $set,
            array_merge($this->unset, $unset),
            $this->push + $push,
            $this->lists,
        );
    }

    public function combineWithQuery(self $other): self
    {
        return new self(
            $this->set + $other->set,
            array_merge($this->unset, $other->unset),
            $this->push + $other->push,
            array_merge($this->lists, $other->lists),
        );
    }

    public function combineWithPrefixedQuery(self $other, string $prefixKey, bool $isList = false): self
    {
        $prefixedLists = array_map(
            fn (string $key) => $this->prefixPushKey($key, $prefixKey),
            $other->lists,
        );

        if ($isList) {
            $prefixedLists[] = $prefixKey;
        }

        return new self(
            $this->set + $this->prefixKeys($other->set, $prefixKey),
            array_merge($this->unset, $this->prefixValues($other->unset, $prefixKey)),
            $this->push + $this->prefixPushKeys($other->push, $prefixKey),
            array_merge($this->lists, $prefixedLists),
        );
    }

    /**
     * @template T of mixed
     * @param array<string, T> $values
     * @return array<string, T>
     */
    private function prefixKeys(array $values, string $prefixKey): array
    {
        return array_combine(
            array_map(
                fn (string $key) => $this->prefixKey($key, $prefixKey),
                array_keys($values),
            ),
            $values,
        );
    }

    /**
     * @template T of array-key
     * @param array<T, string> $values
     * @return array<T, string>
     */
    private function prefixValues(array $values, string $prefixKey): array
    {
        return array_map(
            fn (string $key) => $this->prefixKey($key, $prefixKey),
            $values,
        );
    }

    /**
     * @template T of mixed
     * @param array<string, T> $values
     * @return array<string, T>
     */
    private function prefixPushKeys(array $values, string $prefixKey): array
    {
        return array_combine(
            array_map(
                fn (string $key) => $this->prefixPushKey($key, $prefixKey),
                array_keys($values),
            ),
            $values,
        );
    }

    private function prefixKey(string $key, string $prefix): string
    {
        return sprintf('%s.%s', $prefix, $key);
    }

    private function prefixPushKey(string $key, string $prefix): string
    {
        return $key === '' ? $prefix : sprintf('%s.%s', $prefix, $key);
    }
}
