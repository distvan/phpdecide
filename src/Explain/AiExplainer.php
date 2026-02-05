<?php

declare(strict_types=1);

namespace PhpDecide\Explain;

use PhpDecide\Decision\Decision;

interface AiExplainer
{
    /**
     * @param string $question
     * @param Decision[] $decisions
     * @return string
     */
    public function explain(string $question, array $decisions): string;
}
