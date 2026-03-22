<?php

declare(strict_types=1);

namespace PhpDecide\Enforcement;

final class EnforcementMatchResult
{
    /**
     * @param array<string, list<DecisionViolation>> $violationsByDecisionId
     * @param list<AnalyzerFinding> $unmappedFindings
     */
    public function __construct(
        private readonly array $violationsByDecisionId,
        private readonly array $unmappedFindings,
    ) {}

    /**
     * @return array<string, list<DecisionViolation>>
     */
    public function violationsByDecisionId(): array
    {
        return $this->violationsByDecisionId;
    }

    /**
     * @return list<AnalyzerFinding>
     */
    public function unmappedFindings(): array
    {
        return $this->unmappedFindings;
    }

    public function totalViolations(): int
    {
        $total = 0;

        foreach ($this->violationsByDecisionId as $violations) {
            $total += count($violations);
        }

        return $total;
    }

    public function hasViolations(): bool
    {
        return $this->totalViolations() > 0;
    }
}
