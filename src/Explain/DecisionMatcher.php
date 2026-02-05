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
        $haystack = mb_strtolower(implode(' ', [
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
        $question = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $question);

        return array_filter(explode(' ', $question), fn(string $part): bool => mb_strlen($part) > 2);
    }
}
