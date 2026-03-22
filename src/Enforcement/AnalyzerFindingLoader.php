<?php

declare(strict_types=1);

namespace PhpDecide\Enforcement;

use InvalidArgumentException;

final class AnalyzerFindingLoader
{
    /**
     * @return list<AnalyzerFinding>
     */
    public function loadFromFile(string $filePath): array
    {
        $decoded = (new JsonFileDecoder())->decodeFile($filePath, 'Enforcement report');

        $findingsData = $this->extractFindings($decoded);
        $findings = [];

        foreach ($findingsData as $index => $findingData) {
            if (!is_array($findingData)) {
                throw new InvalidArgumentException(sprintf('findings[%d] must be an object', $index));
            }

            $findings[] = new AnalyzerFinding(
                tool: $this->stringField($findingData, 'tool', $index),
                ruleId: $this->stringField($findingData, 'rule_id', $index),
                path: $this->stringField($findingData, 'path', $index),
                message: $this->stringField($findingData, 'message', $index),
                line: $this->optionalPositiveIntField($findingData, 'line', $index),
                severity: $this->optionalStringField($findingData, 'severity', $index),
            );
        }

        return $findings;
    }

    /**
     * @return list<mixed>
     */
    private function extractFindings(mixed $decoded): array
    {
        $findings = $decoded;

        if (is_array($decoded) && array_key_exists('findings', $decoded)) {
            $findings = $decoded['findings'];
        }

        if (!is_array($findings)) {
            throw new InvalidArgumentException('Enforcement report must be a JSON array or an object with a findings array.');
        }

        return array_values($findings);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringField(array $data, string $field, int $index): string
    {
        if (!array_key_exists($field, $data) || !is_string($data[$field]) || trim($data[$field]) === '') {
            throw new InvalidArgumentException(sprintf('findings[%d].%s must be a non-empty string', $index, $field));
        }

        return trim($data[$field]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalPositiveIntField(array $data, string $field, int $index): ?int
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!is_int($data[$field]) || $data[$field] < 1) {
            throw new InvalidArgumentException(sprintf('findings[%d].%s must be a positive integer', $index, $field));
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalStringField(array $data, string $field, int $index): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!is_string($data[$field]) || trim($data[$field]) === '') {
            throw new InvalidArgumentException(sprintf('findings[%d].%s must be a non-empty string', $index, $field));
        }

        return trim($data[$field]);
    }
}
