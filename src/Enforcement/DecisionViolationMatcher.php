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

        $applicableDecisionsByPath = [];
        foreach ($findings as $finding) {
            $matched = false;
            $path = $finding->path();
            
            if (!array_key_exists($path, $applicableDecisionsByPath)) {
                $applicableDecisionsByPath[$path] = $repository->applicableTo($path);
            }

            foreach ($applicableDecisionsByPath[$path] as $decision) {
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
