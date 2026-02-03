<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

final class Scope
{
    public function __construct(
        private readonly ScopeType $type,
        private readonly array $paths = [],
    )
    {}

    public function type(): ScopeType
    {
        return $this->type;
    }
    
    public function paths(): array
    {
        return $this->paths;
    }

    public function appliesTo(string $path): bool
    {
        if ($this->type === ScopeType::GLOBAL) {
            return true;
        }

        foreach ($this->paths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
