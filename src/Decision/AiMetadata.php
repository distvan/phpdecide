<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

final class AiMetadata
{
    public function __construct(
        private readonly string $explainStyle,
        private readonly array $keywords = []
    ) {}

    public function explainStyle(): string
    {
        return $this->explainStyle;
    }

    public function keywords(): array
    {
        return $this->keywords;
    }
}
