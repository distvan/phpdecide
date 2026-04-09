#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php render-phpdecide-annotations.php <enforce-json-path> [output-commands-path]\n");
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

$commands = renderAnnotationCommands($payload);

if ($outputPath !== null) {
    $bytes = file_put_contents($outputPath, $commands);
    if ($bytes === false) {
        fwrite(STDERR, sprintf("Unable to write output commands file: %s\n", $outputPath));
        exit(1);
    }

    exit(0);
}

fwrite(STDOUT, $commands);
exit(0);

/**
 * @param array<string, mixed> $payload
 */
function renderAnnotationCommands(array $payload): string
{
    $commands = [];

    $error = $payload['error'] ?? null;
    if (is_string($error) && $error !== '') {
        $commands[] = githubCommand('error', [], 'PHPDecide enforcement failed: ' . $error);

        return implode("\n", $commands) . "\n";
    }

    foreach (decisionEntries($payload) as $entry) {
        $decisionId = stringOrDefault($entry, 'decision_id', 'unknown');
        $decisionTitle = stringOrDefault($entry, 'decision_title', 'Unknown decision');

        foreach (findingEntries($entry, 'violations') as $finding) {
            $commands[] = githubCommand(
                'error',
                findingProperties($finding, sprintf('[%s] %s', $decisionId, $decisionTitle)),
                renderFindingMessage($finding)
            );
        }
    }

    foreach (findingEntries($payload, 'unmapped_findings') as $finding) {
        $commands[] = githubCommand(
            'warning',
            findingProperties($finding, 'Unmapped finding'),
            renderFindingMessage($finding)
        );
    }

    if ($commands === []) {
        $commands[] = githubCommand('notice', [], 'PHPDecide enforcement found no decision-linked violations.');
    }

    return implode("\n", $commands) . "\n";
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
 * @param array<string, mixed> $finding
 * @return array<string, string>
 */
function findingProperties(array $finding, string $title): array
{
    $properties = [
        'title' => $title,
        'file' => stringOrDefault($finding, 'path', 'unknown-path'),
    ];

    if (isset($finding['line']) && is_int($finding['line'])) {
        $properties['line'] = (string) $finding['line'];
    }

    return $properties;
}

/**
 * @param array<string, mixed> $finding
 */
function renderFindingMessage(array $finding): string
{
    return sprintf(
        '[%s via %s] %s',
        stringOrDefault($finding, 'rule_id', 'unknown-rule'),
        stringOrDefault($finding, 'tool', 'unknown-tool'),
        stringOrDefault($finding, 'message', 'No message provided.')
    );
}

/**
 * @param array<string, string> $properties
 */
function githubCommand(string $level, array $properties, string $message): string
{
    $prefix = '::' . $level;
    if ($properties === []) {
        return $prefix . '::' . escapeCommandData($message);
    }

    $pairs = [];
    foreach ($properties as $key => $value) {
        $pairs[] = $key . '=' . escapeCommandProperty($value);
    }

    return sprintf('%s %s::%s', $prefix, implode(',', $pairs), escapeCommandData($message));
}

function escapeCommandData(string $value): string
{
    return str_replace(
        ['%', "\r", "\n"],
        ['%25', '%0D', '%0A'],
        $value
    );
}

function escapeCommandProperty(string $value): string
{
    return str_replace(
        ['%', "\r", "\n", ':', ','],
        ['%25', '%0D', '%0A', '%3A', '%2C'],
        $value
    );
}

/**
 * @param array<string, mixed> $data
 */
function stringOrDefault(array $data, string $field, string $default): string
{
    return isset($data[$field]) && is_string($data[$field]) ? $data[$field] : $default;
}
