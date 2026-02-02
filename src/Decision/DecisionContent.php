<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

final class DecisionContent
{
    public function __construct(
        private string $summary,
        private array $rationale,
        private array $alternatives = [],
    ) {}
    public function summary(): string
    {
        return $this->summary;
    }
    public function rationale(): array
    {
        return $this->rationale;
    }
    public function alternatives(): array
    {
        return $this->alternatives;
    }
}
