<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

final class NullAuditLogger implements AuditLogger
{
    public function log(array $event): void
    {
        // Intentionally no-op.
    }
}
