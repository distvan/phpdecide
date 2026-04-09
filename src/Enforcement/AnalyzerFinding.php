<?php

declare(strict_types=1);

namespace PhpDecide\Enforcement;

final class AnalyzerFinding
{
    public function __construct(
        private readonly string $tool,
        private readonly string $ruleId,
        private readonly string $path,
        private readonly string $message,
        private readonly ?int $line = null,
        private readonly ?string $severity = null,
    ) {}

    public function tool(): string
    {
        return $this->tool;
    }

    public function ruleId(): string
    {
        return $this->ruleId;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function line(): ?int
    {
        return $this->line;
    }

    public function severity(): ?string
    {
        return $this->severity;
    }
}
