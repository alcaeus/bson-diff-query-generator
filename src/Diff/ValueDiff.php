<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

final readonly class ValueDiff implements Diff
{
    public function __construct(public mixed $value) {}
}
