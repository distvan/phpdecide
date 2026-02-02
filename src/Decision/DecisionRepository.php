<?php

declare(strict_types=1);

namespace PhpDecide\Decision;


interface DecisionRepository
{
    /**
     * @return Decision[]
     */
    public function all(): array;

    /**
     * @return Decision[]
     */
    public function active(): array;

    public function findById(DecisionId $id): ?Decision;

    /**
     * @return Decision[]
     */
    public function applicableTo(string $path): array;

    /**
     * @return Decision[]
     */
    public function searchByKeyword(string $keyword): array;
}
