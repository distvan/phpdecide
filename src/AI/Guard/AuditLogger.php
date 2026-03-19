<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

interface AuditLogger
{
    /**
     * @param array<string, mixed> $event
     */
    public function log(array $event): void;
}
