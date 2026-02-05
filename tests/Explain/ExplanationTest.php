<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Explain;

use PhpDecide\Decision\DecisionFactory;
use PhpDecide\Explain\Explanation;
use PHPUnit\Framework\TestCase;

final class ExplanationTest extends TestCase
{
    public function testHasDecisionsIsFalseWhenEmpty(): void
    {
        $explanation = new Explanation([], 'Nope');

        self::assertFalse($explanation->hasDecisions());
        self::assertSame('Nope', $explanation->message());
        self::assertSame([], $explanation->decisions());
    }

    public function testHasDecisionsIsTrueWhenNonEmpty(): void
    {
        $decision = DecisionFactory::fromArray([
            'id' => 'DEC-0001',
            'title' => 'No ORMs',
            'status' => 'active',
            'date' => '2026-02-03',
            'scope' => ['type' => 'global'],
            'decision' => [
                'summary' => 'Use raw SQL',
                'rationale' => ['Because.'],
            ],
        ]);

        $explanation = new Explanation([$decision], 'Yup');

        self::assertTrue($explanation->hasDecisions());
        self::assertSame('Yup', $explanation->message());
        self::assertCount(1, $explanation->decisions());
    }
}
