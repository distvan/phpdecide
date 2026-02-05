<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Explain;

use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionFactory;
use PhpDecide\Explain\AiExplainer;
use PhpDecide\Explain\ExplainService;
use PHPUnit\Framework\TestCase;

final class ExplainServiceTest extends TestCase
{
    private const TITLE_NO_ORMS = 'No ORMs';
    private const SUMMARY_RAW_SQL = 'Use raw SQL';

    public function testExplainReturnsNoMatchMessageWhenNothingMatches(): void
    {
        $repo = new InMemoryDecisionRepository([
            $this->decision('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_RAW_SQL),
        ]);

        $service = new ExplainService($repo);
        $explanation = $service->explain('What about event sourcing?');

        self::assertFalse($explanation->hasDecisions());
        self::assertSame([], $explanation->decisions());
        self::assertSame('No recorded decision covers this topic.', $explanation->message());
    }

    public function testExplainBuildsPlainTextExplanationWhenAiExplainerNotProvided(): void
    {
        $repo = new InMemoryDecisionRepository([
            $this->decision('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_RAW_SQL),
        ]);

        $service = new ExplainService($repo);
        $explanation = $service->explain('Why no ORMs?');

        self::assertTrue($explanation->hasDecisions());
        self::assertCount(1, $explanation->decisions());
        self::assertStringContainsString('[DEC-0001]', $explanation->message());
        self::assertStringContainsString(self::TITLE_NO_ORMS, $explanation->message());
        self::assertStringContainsString(self::SUMMARY_RAW_SQL, $explanation->message());
    }

    public function testExplainUsesAiExplainerWhenProvided(): void
    {
        $repo = new InMemoryDecisionRepository([
            $this->decision('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_RAW_SQL),
        ]);

        $captured = ['question' => null, 'count' => null];
        $ai = new class($captured) implements AiExplainer {
            /** @var array{question: ?string, count: ?int} */
            private array $captured;

            /** @param array{question: ?string, count: ?int} $captured */
            public function __construct(array &$captured)
            {
                $this->captured = &$captured;
            }

            public function explain(string $question, array $decisions): string
            {
                $this->captured['question'] = $question;
                $this->captured['count'] = count($decisions);
                return 'AI explanation';
            }
        };

        $service = new ExplainService($repo, $ai);
        $explanation = $service->explain('Why no ORMs?');

        self::assertSame('AI explanation', $explanation->message());
    }

    private function decision(string $id, string $title, string $summary): Decision
    {
        return DecisionFactory::fromArray([
            'id' => $id,
            'title' => $title,
            'status' => 'active',
            'date' => '2026-02-03',
            'scope' => ['type' => 'global'],
            'decision' => [
                'summary' => $summary,
                'rationale' => ['Because.'],
            ],
        ]);
    }
}
