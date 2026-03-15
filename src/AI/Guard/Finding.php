<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

final class Finding
{
    /**
     * @param 'low'|'medium'|'high'|'critical' $severity
     */
    public function __construct(
        public readonly string $id,
        public readonly string $severity,
        public readonly string $category,
        public readonly string $message,
        /** @var array<string, mixed> */
        public readonly array $evidence = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity,
            'category' => $this->category,
            'message' => $this->message,
            'evidence' => $this->evidence,
        ];
    }
}
