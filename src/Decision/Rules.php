<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

final class Rules
{
    public function __construct(
        private readonly array $forbid = [],
        private readonly array $allow = []
    ) {}
    
    public function forbid(): array
    {
        return $this->forbid;
    }
    
    public function allow(): array
    {
        return $this->allow;
    }
    
    public function hasRules(): bool
    {
        return !empty($this->forbid) || !empty($this->allow);
    }
}
