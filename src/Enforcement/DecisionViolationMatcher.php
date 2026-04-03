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

        return new EnforcementMatchResult(
            $this->sortViolationsByDecisionId($violationsByDecisionId),
            $this->sortFindings($unmappedFindings),
        );
    }

    /**
     * @param array<string, list<DecisionViolation>> $violationsByDecisionId
     * @return array<string, list<DecisionViolation>>
     */
    private function sortViolationsByDecisionId(array $violationsByDecisionId): array
    {
        foreach ($violationsByDecisionId as $decisionId => $violations) {
            usort(
                $violations,
                fn(DecisionViolation $left, DecisionViolation $right): int => $this->compareFindings($left->finding(), $right->finding())
            );

            $violationsByDecisionId[$decisionId] = $violations;
        }

        ksort($violationsByDecisionId);

        return $violationsByDecisionId;
    }

    /**
     * @param list<AnalyzerFinding> $findings
     * @return list<AnalyzerFinding>
     */
    private function sortFindings(array $findings): array
    {
        usort(
            $findings,
            fn(AnalyzerFinding $left, AnalyzerFinding $right): int => $this->compareFindings($left, $right)
        );

        return $findings;
    }

    private function compareFindings(AnalyzerFinding $left, AnalyzerFinding $right): int
    {
        return [
            $left->path(),
            $left->line() ?? -1,
            $left->tool(),
            $left->ruleId(),
            $left->severity() ?? '',
            $left->message(),
        ] <=> [
            $right->path(),
            $right->line() ?? -1,
            $right->tool(),
            $right->ruleId(),
            $right->severity() ?? '',
            $right->message(),
        ];
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
