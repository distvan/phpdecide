<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

final class Examples
{
    public function __construct(
        private readonly array $allowed = [],
        private readonly array $forbidden = []
    ) {}

    public function allowed(): array
    {
        return $this->allowed;
    }
    public function forbidden(): array
    {
        return $this->forbidden;
    }
}
