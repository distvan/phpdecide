<?php

declare(strict_types=1);

namespace PhpDecide\Enforcement;

use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionRepository;

final class DecisionViolationMatcher
{
    /**
     * @param list<AnalyzerFinding> $findings
     */
    public function match(DecisionRepository $repository, array $findings): EnforcementMatchResult
    {
        $violationsByDecisionId = [];
        $unmappedFindings = [];

        foreach ($findings as $finding) {
            $matched = false;

            foreach ($repository->applicableTo($finding->path()) as $decision) {
                if (!$this->matchesDecision($decision, $finding)) {
                    continue;
                }

                $decisionId = $decision->id()->value();
                $violationsByDecisionId[$decisionId] ??= [];
                $violationsByDecisionId[$decisionId][] = new DecisionViolation($decision, $finding);
                $matched = true;
            }

            if (!$matched) {
                $unmappedFindings[] = $finding;
            }
        }

        return new EnforcementMatchResult($violationsByDecisionId, $unmappedFindings);
    }

    private function matchesDecision(Decision $decision, AnalyzerFinding $finding): bool
    {
        $rules = $decision->rules();
        if ($rules === null) {
            return false;
        }

        return in_array($finding->ruleId(), $rules->forbid(), true);
    }
}
