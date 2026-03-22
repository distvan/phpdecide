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

        $contents = file_get_contents($filePath);
        if ($contents === false) {
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
}

