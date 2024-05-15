<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

/** @internal */
interface DifferInterface
{
    public function getDiff(null $old, null $new): Diff;
}
