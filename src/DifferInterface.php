<?php

namespace Alcaeus\BsonDiffQueryGenerator;

interface DifferInterface
{
    public function getDiff(null $old, null $new): Diff;
}
