<?php

namespace Alcaeus\BsonDiffQueryGenerator;

final readonly class ValueDiff implements Diff
{
    public function __construct(public mixed $value) {}
}
