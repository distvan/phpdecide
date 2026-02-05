<?php

declare(strict_types=1);

namespace PhpDecide\Explain;

use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionRepository;

final class DecisionMatcher
{
    public function __construct(
        private readonly DecisionRepository $repository
    ){}

    /** @var array<string, string> */
    private array $haystackLowerByDecisionId = [];

    /**
     * @return Decision[]
     */
    public function match(string $question): array
    {
        $tokens = $this->tokenize($question);
        
        if (empty($tokens)) {
            return [];
        }

        $matches = [];
        foreach ($this->repository->active() as $decision) {
            if ($this->matchesDecision($decision, $tokens)) {
                $matches[] = $decision;
            }
        }

        return $matches;
    }

    private function matchesDecision(Decision $decision, array $tokens): bool
    {
        $id = $decision->id()->value();
        $haystack = $this->haystackLowerByDecisionId[$id] ??= mb_strtolower(implode(' ', [
            $decision->title(),
            $decision->content()->summary(),
            implode(' ', $decision->content()->rationale()),
            implode(' ', $decision->aiMetadata()?->keywords() ?? []),
        ]));

        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }

    private function tokenize(string $question): array
    {
        $question = mb_strtolower($question);
        $question = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $question) ?? '';

        $parts = preg_split('/\s+/u', trim($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_filter($parts, fn(string $part): bool => mb_strlen($part) > 2);
        $tokens = array_values(array_unique($tokens));

        return $tokens;
    }
}
