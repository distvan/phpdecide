<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Explain;

use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionId;
use PhpDecide\Decision\DecisionRepository;

final class InMemoryDecisionRepository implements DecisionRepository
{
    /** @var Decision[] */
    private array $decisions;

    /** @param Decision[] $decisions */
    public function __construct(array $decisions)
    {
        $this->decisions = $decisions;
    }

    public function all(): array
    {
        return $this->decisions;
    }

    public function active(): array
    {
        // These tests don’t cover status filtering; the matcher queries active() only.
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
}
