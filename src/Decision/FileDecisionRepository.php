<?php

declare(strict_types=1);

namespace PhpDecide\Decision;


final class FileDecisionRepository implements DecisionRepository
{
    /** @var Decision[] */
    private readonly array $decisions;

    public function __construct(DecisionLoader $loader)
    {
        $decisions = [];
        foreach ($loader->load() as $decision) {
            $decisions[$decision->id()->value()] = $decision;
        }

        $this->decisions = $decisions;
    }

    /**
     * @return Decision[]
     */
    public function all(): array
    {
        return array_values($this->decisions);
    }

    /**
     * @return Decision[]
     */
    public function active(): array
    {
        return array_values(array_filter(
            $this->decisions,
            fn(Decision $decision) => $decision->isActive()
        ));
    }

    public function findById(DecisionId $id): ?Decision
    {
        return $this->decisions[$id->value()] ?? null;
    }

    /**
     * @return Decision[]
     */
    public function applicableTo(string $path): array
    {
        return array_values(array_filter(
            $this->active(),
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
            $this->decisions,
            function (Decision $decision) use ($keyword):bool {
                return $this->matchesKeyword($decision, $keyword);
            }
        ));
    }

    private function matchesKeyword(Decision $decision, string $keyword): bool
    {
        $haystack = implode(' ', [
            $decision->title(),
            $decision->content()->summary(),
            implode(' ', $decision->content()->rationale()),
            implode(' ', $decision->aiMetadata()?->keywords() ?? []),
        ]);

        return str_contains(
            mb_strtolower($haystack),
            $keyword
        );
    }
}
