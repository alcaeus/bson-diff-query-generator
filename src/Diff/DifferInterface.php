<?php

namespace Alcaeus\BsonDiffQueryGenerator\Diff;

interface DifferInterface
{
    public function getDiff(null $old, null $new): Diff;
}
