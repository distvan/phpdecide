<?php

declare(strict_types=1);

namespace PhpDecide\Enforcement;

use InvalidArgumentException;

final class SemgrepFindingLoader
{
    /**
     * @return list<AnalyzerFinding>
     */
    public function loadFromFile(string $filePath): array
    {
        $decoded = (new JsonFileDecoder())->decodeFile($filePath, 'Semgrep report');

        if (!is_array($decoded) || !array_key_exists('results', $decoded) || !is_array($decoded['results'])) {
            throw new InvalidArgumentException('Semgrep report must be a JSON object with a results array.');
        }

        $findings = [];
        foreach (array_values($decoded['results']) as $index => $result) {
            $findings[] = $this->parseResult($result, $index);
        }

        return $findings;
    }

    private function parseResult(mixed $result, int $index): AnalyzerFinding
    {
        if (!is_array($result)) {
            throw new InvalidArgumentException(sprintf('results[%d] must be an object', $index));
        }

        $extra = $this->extraData($result, $index);

        return new AnalyzerFinding(
            tool: 'semgrep',
            ruleId: $this->stringValue($result['check_id'] ?? null, sprintf('results[%d].check_id', $index)),
            path: $this->stringValue($result['path'] ?? null, sprintf('results[%d].path', $index)),
            message: $this->stringValue($extra['message'] ?? null, sprintf('results[%d].extra.message', $index)),
            line: $this->startLine($result, $index),
            severity: $this->optionalStringValue($extra['severity'] ?? null, sprintf('results[%d].extra.severity', $index)),
        );
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function extraData(array $result, int $index): array
    {
        $extra = $result['extra'] ?? null;
        if ($extra === null) {
            return [];
        }

        if (!is_array($extra)) {
            throw new InvalidArgumentException(sprintf('results[%d].extra must be an object when provided', $index));
        }

        return $extra;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function startLine(array $result, int $index): ?int
    {
        $start = $result['start'] ?? null;
        if ($start === null) {
            return null;
        }

        if (!is_array($start)) {
            throw new InvalidArgumentException(sprintf('results[%d].start must be an object when provided', $index));
        }

        return $this->optionalPositiveIntValue($start['line'] ?? null, sprintf('results[%d].start.line', $index));
    }

    private function stringValue(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must be a non-empty string', $field));
        }

        return trim($value);
    }

    private function optionalStringValue(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must be a non-empty string when provided', $field));
        }

        return trim($value);
    }

    private function optionalPositiveIntValue(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer when provided', $field));
        }

        return $value;
    }
}

