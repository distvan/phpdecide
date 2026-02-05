<?php

declare(strict_types=1);

namespace PhpDecide\Explain;

use PhpDecide\Decision\Decision;

final class Explanation
{
    public function __construct(
        private readonly array $decisions,
        private readonly string $message
    ){}

    /**
     * @return Decision[]
     */
    public function decisions(): array
    {
        return $this->decisions;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function hasDecisions(): bool
    {
        return !empty($this->decisions);
    }
}
