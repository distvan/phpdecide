<?php

declare(strict_types=1);

namespace PhpDecide\Enforcement;

use InvalidArgumentException;

final class PhpStanFindingLoader
{
    private const UNKNOWN_IDENTIFIER = 'phpstan.unknown';
    private const GENERAL_ERROR_IDENTIFIER = 'phpstan.general-error';

    /**
     * @return list<AnalyzerFinding>
     */
    public function loadFromFile(string $filePath): array
    {
        $decoded = (new JsonFileDecoder())->decodeFile($filePath, 'PHPStan report');
        $this->assertReportShape($decoded);

        $findings = $this->fileFindings($decoded['files']);
        foreach ($this->generalErrorFindings($decoded['errors'] ?? []) as $errorFinding) {
            $findings[] = $errorFinding;
        }

        return $findings;
    }

    /**
     * @param array<mixed> $decoded
     */
    private function assertReportShape(array $decoded): void
    {
        $files = $decoded['files'] ?? null;
        $errors = $decoded['errors'] ?? null;

        if (!is_array($files)) {
            throw new InvalidArgumentException('PHPStan report must contain a files object.');
        }

        if ($errors !== null && !is_array($errors)) {
            throw new InvalidArgumentException('PHPStan report errors field must be an array when provided.');
        }
    }

    /**
     * @param array<mixed> $files
     * @return list<AnalyzerFinding>
     */
    private function fileFindings(array $files): array
    {
        $findings = [];

        foreach ($files as $path => $fileData) {
            if (!is_string($path) || trim($path) === '') {
                throw new InvalidArgumentException('PHPStan report file keys must be non-empty strings.');
            }

            if (!is_array($fileData)) {
                throw new InvalidArgumentException(sprintf('PHPStan file entry for %s must be an object.', $path));
            }

            $messages = $fileData['messages'] ?? null;
            if (!is_array($messages)) {
                throw new InvalidArgumentException(sprintf('PHPStan file entry for %s must contain a messages array.', $path));
            }

            foreach (array_values($messages) as $index => $messageData) {
                $findings[] = $this->messageFinding($path, $messageData, $index);
            }
        }

        return $findings;
    }

    private function messageFinding(string $path, mixed $messageData, int $index): AnalyzerFinding
    {
        if (!is_array($messageData)) {
            throw new InvalidArgumentException(sprintf('PHPStan message %d for %s must be an object.', $index, $path));
        }

        return new AnalyzerFinding(
            tool: 'phpstan',
            ruleId: $this->identifierValue($messageData['identifier'] ?? null),
            path: trim($path),
            message: $this->stringValue($messageData['message'] ?? null, sprintf('PHPStan message %d for %s', $index, $path)),
            line: $this->optionalPositiveIntValue($messageData['line'] ?? null, sprintf('PHPStan message %d line for %s', $index, $path)),
            severity: 'error',
        );
    }

    /**
     * @param array<mixed> $errors
     * @return list<AnalyzerFinding>
     */
    private function generalErrorFindings(array $errors): array
    {
        $findings = [];

        foreach (array_values($errors) as $index => $error) {
            if (!is_string($error) || trim($error) === '') {
                throw new InvalidArgumentException(sprintf('PHPStan general error %d must be a non-empty string.', $index));
            }

            $findings[] = new AnalyzerFinding(
                tool: 'phpstan',
                ruleId: self::GENERAL_ERROR_IDENTIFIER,
                path: '.',
                message: trim($error),
                line: null,
                severity: 'error',
            );
        }

        return $findings;
    }

    private function identifierValue(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return self::UNKNOWN_IDENTIFIER;
        }

        return trim($value);
    }

    private function stringValue(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('%s must be a non-empty string.', $field));
        }

        return trim($value);
    }

    private function optionalPositiveIntValue(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf('%s must be a positive integer when provided.', $field));
        }

        return $value;
    }
}
