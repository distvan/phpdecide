<?php

declare(strict_types=1);

namespace PhpDecide\Explain;

use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionRepository;

final class ExplainService
{
    private DecisionMatcher $matcher;

    public function __construct(
        DecisionRepository $repository,
        private readonly ?AiExplainer $aiExplainer = null
    ) {
        $this->matcher = new DecisionMatcher($repository);
    }
    
    /**
     * Explain the given question by matching it against recorded decisions.
     *
     * @param string $question
     * @return Explanation
     */
    public function explain(string $question): Explanation
    {
        $decisions = $this->matcher->match($question);

        if (empty($decisions)) {
            return new Explanation([], 'No recorded decision covers this topic.');
        }

        $message = $this->buildExplanation($question, $decisions);

        return new Explanation($decisions, $message);
    }

    /**
     * Build an explanation message based on the matched decisions.
     *
     * @param string $question
     * @param Decision[] $decisions
     * @return string
     */
    private function buildExplanation(string $question, array $decisions): string
    {
        if ($this->aiExplainer !== null) {
            return $this->aiExplainer->explain($question, $decisions);
        }

        return $this->plainTextExplanation($decisions);
    }

    /**
     * Generate a plain text explanation from the given decisions.
     *
     * @param Decision[] $decisions
     * @return string
     */
    private function plainTextExplanation(array $decisions): string
    {
        $lines = [];
        
        foreach ($decisions as $decision) {
            $lines[] = sprintf(
                '- [%s] %s\n %s',
                $decision->id()->value(),
                $decision->title(),
                $decision->content()->summary()
            );
        }

        return implode(PHP_EOL . PHP_EOL, $lines);
    }
}
