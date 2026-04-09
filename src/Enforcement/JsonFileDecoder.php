<?php

declare(strict_types=1);

namespace PhpDecide\Enforcement;

use InvalidArgumentException;
use JsonException;

final class JsonFileDecoder
{
    /**
     * @return array<mixed>
     */
    public function decodeFile(string $filePath, string $label): array
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException(sprintf('%s not found: %s', $label, $filePath));
        }

        if (!is_readable($filePath)) {
            throw new InvalidArgumentException(sprintf('Unable to read %s: %s', strtolower($label), $filePath));
        }

        $contents = $this->trapWarnings(static fn() => file_get_contents($filePath));
        if (!is_string($contents)) {
            throw new InvalidArgumentException(sprintf('Unable to read %s: %s', strtolower($label), $filePath));
        }

        try {
            $decoded = json_decode($this->normalizeEncoding($contents), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('Invalid %s JSON: %s', strtolower($label), $e->getMessage()), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException(sprintf('%s must decode to a JSON object or array.', $label));
        }

        return $decoded;
    }

    private function normalizeEncoding(string $contents): string
    {
        $encoding = mb_detect_encoding(
            $contents,
            ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'UTF-32LE', 'UTF-32BE'],
            true
        );

        if ($encoding === false || $encoding === 'UTF-8') {
            return $this->removeUtf8Bom($contents);
        }

        return $this->removeUtf8Bom(mb_convert_encoding($contents, 'UTF-8', $encoding));
    }

    private function removeUtf8Bom(string $contents): string
    {
        $bom = "\xEF\xBB\xBF";
        if (str_starts_with($contents, $bom)) {
            return substr($contents, 3);
        }

        return $contents;
    }

    private function trapWarnings(callable $fn): mixed
    {
        $hadWarning = false;
        set_error_handler(
            static function (int $_) use (&$hadWarning): bool {
                $hadWarning = true;
                return true;
            },
            E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE
        );

        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }

        return $hadWarning ? null : $result;
    }
}

