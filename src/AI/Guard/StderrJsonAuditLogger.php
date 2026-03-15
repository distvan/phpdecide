<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;


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

        $stream = $this->openStderrStream();
        if (!is_resource($stream)) {
            return;
        }

        $openedByLogger = !(defined('STDERR') && STDERR === $stream);
        $this->writeBestEffort($stream, $line . PHP_EOL);

        if ($openedByLogger) {
            fclose($stream);
        }
    }

    /**
     * @return resource|null
     */
    private function openStderrStream()
    {
        if (defined('STDERR') && is_resource(STDERR)) {
            return STDERR;
        }

        $hadWarning = false;
        set_error_handler(
            static function (int $_) use (&$hadWarning): bool {
                $hadWarning = true;
                return true;
            },
            E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE
        );

        try {
            $stream = fopen('php://stderr', 'wb');
        } finally {
            restore_error_handler();
        }

        if ($hadWarning || !is_resource($stream)) {
            return null;
        }

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function writeBestEffort($stream, string $line): void
    {
        set_error_handler(
            static function (): bool {
                return true;
            },
            E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE
        );

        try {
            fwrite($stream, $line);
        } finally {
            restore_error_handler();
        }
    }
}
