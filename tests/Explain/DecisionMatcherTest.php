<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Explain;

use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionFactory;
use PhpDecide\Decision\DecisionId;
use PhpDecide\Decision\DecisionRepository;
use PhpDecide\Explain\DecisionMatcher;
use PhpDecide\Decision\SearchIndexedDecisionRepository;
use PHPUnit\Framework\TestCase;

final class DecisionMatcherTest extends TestCase
{
    private const TITLE_NO_ORMS = 'No ORMs';
    private const SUMMARY_USE_RAW_SQL = 'Use raw SQL';

    public function testMatchReturnsEmptyWhenQuestionTokenizesToNothing(): void
    {
        $repo = new InMemoryDecisionRepository([
            $this->decision('DEC-0001', self::TITLE_NO_ORMS, 'Avoid ORMs', ['orms']),
        ]);

        $matcher = new DecisionMatcher($repo);

        self::assertSame([], $matcher->match('?? !!'));
        self::assertSame([], $matcher->match('a an to is of'));
    }

    public function testMatchFindsDecisionByTitleAndIsCaseInsensitive(): void
    {
        $repo = new InMemoryDecisionRepository([
            $this->decision('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_USE_RAW_SQL, []),
            $this->decision('DEC-0002', 'Controller rules', 'Thin controllers', []),
        ]);

        $matcher = new DecisionMatcher($repo);
        $matches = $matcher->match('Why do we avoid ORMS?');

        self::assertCount(1, $matches);
        self::assertSame('DEC-0001', $matches[0]->id()->value());
    }

    public function testMatchFindsDecisionByAiKeywords(): void
    {
        $repo = new InMemoryDecisionRepository([
            $this->decision('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_USE_RAW_SQL, ['database', 'orms']),
        ]);

        $matcher = new DecisionMatcher($repo);
        $matches = $matcher->match('How should we structure our database layer?');

        self::assertCount(1, $matches);
        self::assertSame('DEC-0001', $matches[0]->id()->value());
    }

    public function testMatchReusesRepositorySearchIndexWhenAvailable(): void
    {
        $decision = $this->decision('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_USE_RAW_SQL, ['orms']);

        $calls = 0;

        $repo = new class([$decision], $calls) implements DecisionRepository, SearchIndexedDecisionRepository {
            /** @var Decision[] */
            private array $decisions;

            /** @var int */
            private $callsRef;

            /** @param Decision[] $decisions */
            public function __construct(array $decisions, int &$calls)
            {
                $this->decisions = $decisions;
                $this->callsRef = &$calls;
            }

            public function all(): array
            {
                return $this->decisions;
            }

            public function active(): array
            {
                return $this->decisions;
            }

            public function findById(DecisionId $id): ?Decision
            {
                foreach ($this->decisions as $decision) {
                    if ($decision->id()->equals($id)) {
                        return $decision;
                    }
                }

                return null;
            }

            public function applicableTo(string $path): array
            {
                return [];
            }

            public function searchByKeyword(string $keyword): array
            {
                return [];
            }

            public function haystackLowerFor(Decision $decision): string
            {
                $this->callsRef++;
                return 'no orms use raw sql orms';
            }
        };

        $matcher = new DecisionMatcher($repo);
        $matches = $matcher->match('Why avoid ORMS?');

        self::assertCount(1, $matches);
        self::assertSame('DEC-0001', $matches[0]->id()->value());
        self::assertSame(1, $calls);
    }

    private function decision(string $id, string $title, string $summary, array $keywords): Decision
    {
        $data = [
            'id' => $id,
            'title' => $title,
            'status' => 'active',
            'date' => '2026-02-03',
            'scope' => ['type' => 'global'],
            'decision' => [
                'summary' => $summary,
                'rationale' => ['Because.'],
            ],
        ];

        if ($keywords !== []) {
            $data['ai'] = [
                'explain_style' => 'plain',
                'keywords' => $keywords,
            ];
        }

        return DecisionFactory::fromArray($data);
    }
}
