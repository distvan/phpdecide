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
    private const QUESTION_WHY_NO_ORMS = 'Why no ORMs?';

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
        $explanation = $service->explain(self::QUESTION_WHY_NO_ORMS);

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
        $explanation = $service->explain(self::QUESTION_WHY_NO_ORMS);

        self::assertSame('AI explanation', $explanation->message());
    }

    public function testExplainFiltersDecisionsByPathWhenProvided(): void
    {
        $repo = new InMemoryDecisionRepository([
            $this->decisionWithScopePaths('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_RAW_SQL, ['src/*']),
        ]);

        $service = new ExplainService($repo);

        $notApplicable = $service->explain(self::QUESTION_WHY_NO_ORMS, 'tests/ExampleTest.php');
        self::assertFalse($notApplicable->hasDecisions());

        $applicable = $service->explain(self::QUESTION_WHY_NO_ORMS, 'src/Service/Foo.php');
        self::assertTrue($applicable->hasDecisions());
        self::assertCount(1, $applicable->decisions());
        self::assertStringContainsString('[DEC-0001]', $applicable->message());
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

    /** @param string[] $paths */
    private function decisionWithScopePaths(string $id, string $title, string $summary, array $paths): Decision
    {
        return DecisionFactory::fromArray([
            'id' => $id,
            'title' => $title,
            'status' => 'active',
            'date' => '2026-02-03',
            'scope' => [
                'type' => 'path',
                'paths' => $paths,
            ],
            'decision' => [
                'summary' => $summary,
                'rationale' => ['Because.'],
            ],
        ]);
    }
}
