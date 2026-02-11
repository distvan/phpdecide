<?php

declare(strict_types=1);

namespace PhpDecide\AI;

use PhpDecide\Decision\Decision;

interface AiClient
{
    /**
     * AI is a presentation layer only: it may summarize/explain recorded decisions,
     * but it must not invent new rules, scopes, or decisions.
     *
     * @param string $question
     * @param Decision[] $decisions
     * @return string
     */
    public function explainDecision(string $question, array $decisions): string;
}
