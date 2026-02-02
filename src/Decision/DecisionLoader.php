<?php

declare(strict_types=1);

namespace PhpDecide\Decision;


interface DecisionLoader
{
    /**
     * @return Decision[]
     */
    public function load(): iterable;
}
