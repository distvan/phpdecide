<?php

declare(strict_types=1);

namespace PhpDecide\Explain;

use PhpDecide\AI\AiClient;

final class AiClientExplainer implements AiExplainer
{
    public function __construct(
        private readonly AiClient $client
    ) {}

    public function explain(string $question, array $decisions): string
    {
        return $this->client->explainDecision($question, $decisions);
    }
}
