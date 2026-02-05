<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Explain;

use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionFactory;
use PhpDecide\Explain\DecisionMatcher;
use PHPUnit\Framework\TestCase;

final class DecisionMatcherTest extends TestCase
{
    private const TITLE_NO_ORMS = 'No ORMs';

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
            $this->decision('DEC-0001', self::TITLE_NO_ORMS, 'Use raw SQL', []),
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
            $this->decision('DEC-0001', self::TITLE_NO_ORMS, 'Use raw SQL', ['database', 'orms']),
        ]);

        $matcher = new DecisionMatcher($repo);
        $matches = $matcher->match('How should we structure our database layer?');

        self::assertCount(1, $matches);
        self::assertSame('DEC-0001', $matches[0]->id()->value());
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
