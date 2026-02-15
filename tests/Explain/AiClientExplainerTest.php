<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Explain;

use PhpDecide\AI\AiClient;
use PhpDecide\Decision\DecisionFactory;
use PhpDecide\Explain\AiClientExplainer;
use PHPUnit\Framework\TestCase;

final class AiClientExplainerTest extends TestCase
{
    public function testExplainDelegatesToAiClient(): void
    {
        $decision = DecisionFactory::fromArray([
            'id' => 'DEC-0001',
            'title' => 'No ORMs',
            'status' => 'active',
            'date' => '2026-02-01',
            'scope' => ['type' => 'global'],
            'decision' => [
                'summary' => 'Use raw SQL',
                'rationale' => ['Because it helps.'],
            ],
        ]);

        $question = 'Why no ORMs?';
        $decisions = [$decision];
        $expected = 'Because [DEC-0001] says so.';

        $client = $this->createMock(AiClient::class);
        $client
            ->expects(self::once())
            ->method('explainDecision')
            ->with($question, $decisions)
            ->willReturn($expected);

        $explainer = new AiClientExplainer($client);

        self::assertSame($expected, $explainer->explain($question, $decisions));
    }

    public function testExplainPropagatesClientExceptions(): void
    {
        $client = $this->createMock(AiClient::class);
        $client
            ->method('explainDecision')
            ->willThrowException(new \RuntimeException('boom'));

        $explainer = new AiClientExplainer($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $explainer->explain('Q?', []);
    }
}
