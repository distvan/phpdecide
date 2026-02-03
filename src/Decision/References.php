<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

final class References
{
    public function __construct(
        private readonly array $issues,
        private readonly array $commits,
        private readonly ?string $adr
    )
    {}
    
    public static function empty(): self
    {
        return new self([], [], null);
    }

    public function issues(): array
    {
        return $this->issues;
    }

    public function commits(): array
    {
        return $this->commits;
    }

    public function adr(): ?string
    {
        return $this->adr;
    }
}
