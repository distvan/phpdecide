<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

use SebastianBergmann\CodeCoverage\Report\PHP;

final class StderrJsonAuditLogger implements AuditLogger
{
    public function log(array $event): void
    {
        // CI-friendly: JSON line to stderr.
        try {
            $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Avoid throwing from audit paths.
            return;
        }

        fwrite(STDERR, $line . PHP_EOL);
    }
}
