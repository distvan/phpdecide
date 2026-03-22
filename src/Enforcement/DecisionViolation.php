<?php

declare(strict_types=1);

namespace PhpDecide\Enforcement;

use PhpDecide\Decision\Decision;

final class DecisionViolation
{
    public function __construct(
        private readonly Decision $decision,
        private readonly AnalyzerFinding $finding,
    ) {}

    public function decision(): Decision
    {
        return $this->decision;
    }

    public function finding(): AnalyzerFinding
    {
        return $this->finding;
    }
}
