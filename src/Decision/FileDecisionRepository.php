<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

final class FileDecisionRepository implements DecisionRepository, SearchIndexedDecisionRepository
{
    /** @var array<string, Decision> */
    private readonly array $decisionsById;

    /** @var Decision[] */
    private readonly array $allDecisions;

    /** @var Decision[] */
    private readonly array $activeDecisions;

    /** @var array<string, string> */
    private readonly array $searchIndexLowerById;

    public function __construct(DecisionLoader $loader)
    {
        $decisionsById = [];
        $allDecisions = [];
        $activeDecisions = [];
        $searchIndexLowerById = [];

        foreach ($loader->load() as $decision) {
            $id = $decision->id()->value();
            $decisionsById[$id] = $decision;
            $allDecisions[] = $decision;

            $searchIndexLowerById[$id] = $this->buildSearchHaystackLower($decision);

            if ($decision->isActive()) {
                $activeDecisions[] = $decision;
            }
        }

        $this->decisionsById = $decisionsById;
        $this->allDecisions = $allDecisions;
        $this->activeDecisions = $activeDecisions;
        $this->searchIndexLowerById = $searchIndexLowerById;
    }

    /**
     * @return Decision[]
     */
    public function all(): array
    {
        return $this->allDecisions;
    }

    /**
     * @return Decision[]
     */
    public function active(): array
    {
        return $this->activeDecisions;
    }

    public function findById(DecisionId $id): ?Decision
    {
        return $this->decisionsById[$id->value()] ?? null;
    }

    /**
     * @return Decision[]
     */
    public function applicableTo(string $path): array
    {
        return array_values(array_filter(
            $this->activeDecisions,
            fn(Decision $decision) => $decision->scope()->appliesTo($path)
        ));
    }

    /**
     * @return Decision[]
     */
    public function searchByKeyword(string $keyword): array
    {
        $keyword = mb_strtolower($keyword);

        return array_values(array_filter(
            $this->allDecisions,
            function (Decision $decision) use ($keyword):bool {
                return $this->matchesKeyword($decision, $keyword);
            }
        ));
    }

    private function matchesKeyword(Decision $decision, string $keyword): bool
    {
        $haystackLower = $this->haystackLowerFor($decision);

        return str_contains($haystackLower, $keyword);
    }

    public function haystackLowerFor(Decision $decision): string
    {
        $id = $decision->id()->value();
        return $this->searchIndexLowerById[$id] ?? $this->buildSearchHaystackLower($decision);
    }

    private function buildSearchHaystackLower(Decision $decision): string
    {
        $haystack = implode(' ', [
            $decision->title(),
            $decision->content()->summary(),
            implode(' ', $decision->content()->rationale()),
            implode(' ', $decision->aiMetadata()?->keywords() ?? []),
        ]);

        return mb_strtolower($haystack);
    }
}
