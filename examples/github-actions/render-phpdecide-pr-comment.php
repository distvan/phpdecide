#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php render-phpdecide-pr-comment.php <enforce-json-path> [output-markdown-path]\n");
    exit(1);
}

$inputPath = $argv[1];
$outputPath = $argv[2] ?? null;

if (!is_file($inputPath)) {
    fwrite(STDERR, sprintf("Input JSON file not found: %s\n", $inputPath));
    exit(1);
}

$json = file_get_contents($inputPath);
if ($json === false) {
    fwrite(STDERR, sprintf("Unable to read input JSON file: %s\n", $inputPath));
    exit(1);
}

$json = normalizeInputEncoding($json);

try {
    /** @var mixed $payload */
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, sprintf("Invalid JSON input: %s\n", $exception->getMessage()));
    exit(1);
}

if (!is_array($payload)) {
    fwrite(STDERR, "JSON input must decode to an object.\n");
    exit(1);
}

$markdown = renderMarkdownComment($payload);

if ($outputPath !== null) {
    $bytes = file_put_contents($outputPath, $markdown);
    if ($bytes === false) {
        fwrite(STDERR, sprintf("Unable to write output markdown file: %s\n", $outputPath));
        exit(1);
    }

    exit(0);
}

fwrite(STDOUT, $markdown);
exit(0);

/**
 * @param array<string, mixed> $payload
 */
function renderMarkdownComment(array $payload): string
{
    $lines = ['## PHPDecide enforcement'];

    $ok = (bool) ($payload['ok'] ?? false);
    appendSummaryLines($lines, $ok, summaryData($payload));

    $error = $payload['error'] ?? null;
    if (is_string($error) && $error !== '') {
        appendErrorLines($lines, $error);

        return implode("\n", $lines) . "\n";
    }

    appendDecisionLines($lines, decisionEntries($payload));
    appendUnmappedLines($lines, findingEntries($payload, 'unmapped_findings'));

    return implode("\n", $lines) . "\n";
}

function normalizeInputEncoding(string $contents): string
{
    $encoding = mb_detect_encoding(
        $contents,
        ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'UTF-32LE', 'UTF-32BE'],
        true
    );

    if ($encoding === false) {
        return removeUtf8Bom($contents);
    }

    $normalized = $encoding === 'UTF-8'
        ? $contents
        : mb_convert_encoding($contents, 'UTF-8', $encoding);

    return removeUtf8Bom($normalized);
}

function removeUtf8Bom(string $contents): string
{
    $bom = "\xEF\xBB\xBF";
    if (str_starts_with($contents, $bom)) {
        return substr($contents, 3);
    }

    return $contents;
}

/**
 * @return array<string, mixed>
 */
function summaryData(array $payload): array
{
    return is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
}

/**
 * @param list<string> $lines
 * @param array<string, mixed> $summary
 */
function appendSummaryLines(array &$lines, bool $ok, array $summary): void
{
    $lines[] = '';
    $lines[] = sprintf('- ok: %s', $ok ? 'true' : 'false');
    $lines[] = sprintf('- decision_count: %d', (int) ($summary['decision_count'] ?? 0));
    $lines[] = sprintf('- violation_count: %d', (int) ($summary['violation_count'] ?? 0));
    $lines[] = sprintf('- unmapped_finding_count: %d', (int) ($summary['unmapped_finding_count'] ?? 0));
}

/**
 * @param list<string> $lines
 */
function appendErrorLines(array &$lines, string $error): void
{
    $lines[] = '';
    $lines[] = '### Error';
    $lines[] = '';
    $lines[] = $error;
}

/**
 * @return list<array<string, mixed>>
 */
function decisionEntries(array $payload): array
{
    return findingEntries($payload, 'violations_by_decision');
}

/**
 * @return list<array<string, mixed>>
 */
function findingEntries(array $payload, string $field): array
{
    $value = $payload[$field] ?? [];
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_filter($value, 'is_array'));
}

/**
 * @param list<string> $lines
 * @param list<array<string, mixed>> $entries
 */
function appendDecisionLines(array &$lines, array $entries): void
{
    foreach ($entries as $entry) {
        $lines[] = '';
        $lines[] = sprintf(
            '### [%s] %s',
            stringOrDefault($entry, 'decision_id', 'unknown'),
            stringOrDefault($entry, 'decision_title', 'Unknown decision')
        );

        $violations = findingEntries($entry, 'violations');
        if ($violations === []) {
            $lines[] = '';
            $lines[] = '- No violations listed.';
            continue;
        }

        $lines[] = '';
        foreach ($violations as $violation) {
            $lines[] = '- ' . renderFindingLine($violation);
        }
    }
}

/**
 * @param list<string> $lines
 * @param list<array<string, mixed>> $entries
 */
function appendUnmappedLines(array &$lines, array $entries): void
{
    if ($entries === []) {
        return;
    }

    $lines[] = '';
    $lines[] = '### Unmapped findings';
    $lines[] = '';

    foreach ($entries as $entry) {
        $lines[] = '- ' . renderFindingLine($entry);
    }
}

/**
 * @param array<string, mixed> $finding
 */
function renderFindingLine(array $finding): string
{
    $path = stringOrDefault($finding, 'path', 'unknown-path');
    $line = isset($finding['line']) && is_int($finding['line']) ? $finding['line'] : null;
    $ruleId = stringOrDefault($finding, 'rule_id', 'unknown-rule');
    $tool = stringOrDefault($finding, 'tool', 'unknown-tool');
    $message = stringOrDefault($finding, 'message', 'No message provided.');

    $lineSuffix = $line !== null ? ':' . (string) $line : '';

    return sprintf('%s%s [%s via %s] %s', $path, $lineSuffix, $ruleId, $tool, $message);
}

/**
 * @param array<string, mixed> $data
 */
function stringOrDefault(array $data, string $field, string $default): string
{
    return isset($data[$field]) && is_string($data[$field]) ? $data[$field] : $default;
}
