<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Decision;

use InvalidArgumentException;
use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionFactory;
use PHPUnit\Framework\TestCase;

final class DecisionFactoryTest extends TestCase
{
    public function testFromArrayCreatesDecisionFromValidData(): void
    {
        $decision = DecisionFactory::fromArray($this->validData());

        self::assertInstanceOf(Decision::class, $decision);
        self::assertSame('DEC-0001', $decision->id()->value());
        self::assertSame('No ORMs', $decision->title());
        self::assertTrue($decision->isActive());
        self::assertSame('global', $decision->scope()->type()->value);
        self::assertSame('Use raw SQL', $decision->content()->summary());
    }

    public function testFromArrayThrowsWhenRequiredFieldMissing(): void
    {
        $data = $this->validData();
        unset($data['title']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: title');

        DecisionFactory::fromArray($data);
    }

    public function testFromArrayThrowsWhenDateInvalid(): void
    {
        $data = $this->validData();
        $data['date'] = 'not-a-date';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format:');

        DecisionFactory::fromArray($data);
    }

    public function testFromArrayThrowsWhenScopePathsNotArray(): void
    {
        $data = $this->validData();
        $data['scope'] = [
            'type' => 'path',
            'paths' => 'src/*',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope paths must be an array');

        DecisionFactory::fromArray($data);
    }

    public function testFromArrayThrowsWhenRationaleNotArray(): void
    {
        $data = $this->validData();
        $data['decision']['rationale'] = 'because';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Decision rationale must be an array');

        DecisionFactory::fromArray($data);
    }

    public function testFromArrayThrowsWhenAiMetadataMissingExplainStyle(): void
    {
        $data = $this->validData();
        $data['ai'] = [
            'keywords' => ['foo'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: explain_style');

        DecisionFactory::fromArray($data);
    }

    private function validData(): array
    {
        return [
            'id' => 'DEC-0001',
            'title' => 'No ORMs',
            'status' => 'active',
            'date' => '2026-02-03',
            'scope' => [
                'type' => 'global',
            ],
            'decision' => [
                'summary' => 'Use raw SQL',
                'rationale' => ['Performance and transparency'],
                'alternatives' => ['Use Doctrine'],
            ],
            'examples' => [
                'allowed' => ['PDO'],
                'forbidden' => ['Doctrine'],
            ],
            'rules' => [
                'forbid' => ['doctrine/*'],
                'allow' => ['pdo/*'],
            ],
            'references' => [
                'issues' => ['#123'],
                'commits' => ['abc123'],
                'adr' => 'ADR-0001',
            ],
        ];
    }
}
