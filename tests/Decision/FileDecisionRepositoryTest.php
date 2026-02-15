<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Decision;

use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionFactory;
use PhpDecide\Decision\DecisionId;
use PhpDecide\Decision\DecisionLoader;
use PhpDecide\Decision\FileDecisionRepository;
use PHPUnit\Framework\TestCase;

final class FileDecisionRepositoryTest extends TestCase
{
    private const TITLE_NO_ORMS = 'No ORMs';
    private const SUMMARY_RAW_SQL = 'Use raw SQL';
    private const RATIONALE_PERFORMANCE = 'Because performance.';

    private const TITLE_USE_SYMFONY = 'Use Symfony';
    private const SUMMARY_SYMFONY = 'Prefer Symfony components';
    private const RATIONALE_CONSISTENCY = 'Because consistency.';

    public function testAllReturnsAllDecisionsInLoaderOrder(): void
    {
        $decisions = [
            $this->decision('DEC-0001', 'active', [
                'scopeType' => 'global',
                'title' => self::TITLE_NO_ORMS,
                'summary' => self::SUMMARY_RAW_SQL,
                'rationale' => [self::RATIONALE_PERFORMANCE],
                'aiKeywords' => ['orm', 'doctrine'],
            ]),
            $this->decision('DEC-0002', 'deprecated', [
                'scopeType' => 'path',
                'paths' => ['src/*'],
                'title' => self::TITLE_USE_SYMFONY,
                'summary' => self::SUMMARY_SYMFONY,
                'rationale' => [self::RATIONALE_CONSISTENCY],
            ]),
            $this->decision('DEC-0003', 'active', [
                'scopeType' => 'path',
                'paths' => ['tests/*'],
                'title' => 'No snapshots',
                'summary' => 'Avoid brittle tests',
                'rationale' => ['Because stability.'],
            ]),
        ];

        $repo = new FileDecisionRepository($this->loader($decisions));

        $all = $repo->all();
        self::assertCount(3, $all);
        self::assertSame('DEC-0001', $all[0]->id()->value());
        self::assertSame('DEC-0002', $all[1]->id()->value());
        self::assertSame('DEC-0003', $all[2]->id()->value());
    }

    public function testActiveReturnsOnlyActiveDecisions(): void
    {
        $repo = new FileDecisionRepository($this->loader([
            $this->decision('DEC-0001', 'active', [
                'scopeType' => 'global',
                'title' => self::TITLE_NO_ORMS,
                'summary' => self::SUMMARY_RAW_SQL,
                'rationale' => [self::RATIONALE_PERFORMANCE],
            ]),
            $this->decision('DEC-0002', 'deprecated', [
                'scopeType' => 'global',
                'title' => 'Old rule',
                'summary' => 'Old summary',
                'rationale' => ['Because old.'],
            ]),
        ]));

        $active = $repo->active();
        self::assertCount(1, $active);
        self::assertSame('DEC-0001', $active[0]->id()->value());
    }

    public function testFindByIdReturnsDecisionOrNull(): void
    {
        $repo = new FileDecisionRepository($this->loader([
            $this->decision('DEC-0001', 'active', [
                'scopeType' => 'global',
                'title' => self::TITLE_NO_ORMS,
                'summary' => self::SUMMARY_RAW_SQL,
                'rationale' => [self::RATIONALE_PERFORMANCE],
            ]),
        ]));

        self::assertNotNull($repo->findById(DecisionId::fromString('DEC-0001')));
        self::assertNull($repo->findById(DecisionId::fromString('DEC-9999')));
    }

    public function testApplicableToFiltersToActiveAndMatchingScope(): void
    {
        $repo = new FileDecisionRepository($this->loader([
            $this->decision('DEC-0001', 'active', [
                'scopeType' => 'global',
                'title' => self::TITLE_NO_ORMS,
                'summary' => self::SUMMARY_RAW_SQL,
                'rationale' => [self::RATIONALE_PERFORMANCE],
            ]),
            $this->decision('DEC-0002', 'deprecated', [
                'scopeType' => 'path',
                'paths' => ['src/*'],
                'title' => self::TITLE_USE_SYMFONY,
                'summary' => self::SUMMARY_SYMFONY,
                'rationale' => [self::RATIONALE_CONSISTENCY],
            ]),
            $this->decision('DEC-0003', 'active', [
                'scopeType' => 'path',
                'paths' => ['tests/*'],
                'title' => 'No snapshots',
                'summary' => 'Avoid brittle tests',
                'rationale' => ['Because stability.'],
            ]),
        ]));

        $forSrc = $repo->applicableTo('src/Foo.php');
        self::assertSame(['DEC-0001'], array_map(static fn(Decision $d) => $d->id()->value(), $forSrc));

        $forTests = $repo->applicableTo('tests/BarTest.php');
        self::assertSame(['DEC-0001', 'DEC-0003'], array_map(static fn(Decision $d) => $d->id()->value(), $forTests));
    }

    public function testSearchByKeywordIsCaseInsensitiveAndSearchesMultipleFields(): void
    {
        $repo = new FileDecisionRepository($this->loader([
            $this->decision('DEC-0001', 'active', [
                'scopeType' => 'global',
                'title' => self::TITLE_NO_ORMS,
                'summary' => self::SUMMARY_RAW_SQL,
                'rationale' => ['Because Performance.'],
                'aiKeywords' => ['orm', 'doctrine'],
            ]),
            $this->decision('DEC-0002', 'deprecated', [
                'scopeType' => 'global',
                'title' => self::TITLE_USE_SYMFONY,
                'summary' => self::SUMMARY_SYMFONY,
                'rationale' => [self::RATIONALE_CONSISTENCY],
            ]),
        ]));

        self::assertSame(['DEC-0001'], $this->ids($repo->searchByKeyword('raw')));
        self::assertSame(['DEC-0001'], $this->ids($repo->searchByKeyword('ORM'))); // title
        self::assertSame(['DEC-0001'], $this->ids($repo->searchByKeyword('performance'))); // rationale
        self::assertSame(['DEC-0001'], $this->ids($repo->searchByKeyword('doctrine'))); // ai keywords

        // Search includes non-active decisions too (search runs over all decisions).
        self::assertSame(['DEC-0002'], $this->ids($repo->searchByKeyword('symfony')));

        self::assertSame([], $this->ids($repo->searchByKeyword('no-such-keyword')));
    }

    /** @param Decision[] $decisions */
    private function loader(array $decisions): DecisionLoader
    {
        return new class($decisions) implements DecisionLoader {
            /** @param Decision[] $decisions */
            public function __construct(private readonly array $decisions) {}

            public function load(): iterable
            {
                return $this->decisions;
            }
        };
    }

    /**
     * @param array{
     *     scopeType?: 'global'|'path',
     *     paths?: list<string>,
     *     title?: string,
     *     summary?: string,
     *     rationale?: list<string>,
     *     aiKeywords?: list<string>
     * } $definition
     */
    private function decision(string $id, string $status, array $definition = []): Decision
    {
        $scopeType = $definition['scopeType'] ?? 'global';
        $paths = $definition['paths'] ?? [];
        $title = $definition['title'] ?? ('Decision ' . $id);
        $summary = $definition['summary'] ?? 'Summary';
        $rationale = $definition['rationale'] ?? ['Rationale'];
        $aiKeywords = $definition['aiKeywords'] ?? [];

        $data = [
            'id' => $id,
            'title' => $title,
            'status' => $status,
            'date' => '2026-02-01',
            'scope' => array_filter([
                'type' => $scopeType,
                'paths' => $scopeType === 'global' ? null : $paths,
            ], static fn(mixed $v) => $v !== null),
            'decision' => [
                'summary' => $summary,
                'rationale' => $rationale,
            ],
        ];

        if ($aiKeywords !== []) {
            $data['ai'] = [
                'explain_style' => 'concise',
                'keywords' => $aiKeywords,
            ];
        }

        return DecisionFactory::fromArray($data);
    }

    /** @param Decision[] $decisions */
    private function ids(array $decisions): array
    {
        return array_map(static fn(Decision $d) => $d->id()->value(), $decisions);
    }
}
