<?php

declare(strict_types=1);

namespace PhpDecide\Tests\CLI;

use PhpDecide\Tests\Support\TestFilesystemException;
use PHPUnit\Framework\TestCase;

final class RenderPhpDecidePrCommentTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->tempFiles = [];
    }

    public function testRendererUsesViolatingDecisionCountSummaryField(): void
    {
        $inputPath = $this->createTempJsonFile(json_encode([
            'ok' => false,
            'summary' => [
                'violating_decision_count' => 2,
                'violation_count' => 3,
                'unmapped_finding_count' => 1,
            ],
            'violations_by_decision' => [],
            'unmapped_findings' => [],
        ], JSON_THROW_ON_ERROR));

        $result = $this->runRenderer($inputPath);

        self::assertSame(0, $result['exitCode'], $result['stderr']);
        self::assertStringContainsString("\n- violating_decision_count: 2\n", $result['stdout']);
        self::assertStringNotContainsString("\n- decision_count: 2\n", $result['stdout']);
    }

    private function createTempJsonFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdecide_pr_comment_');
        if ($path === false) {
            throw new TestFilesystemException('Unable to create temp file for PR comment renderer test.');
        }

        if (file_put_contents($path, $contents) === false) {
            throw new TestFilesystemException(sprintf('Unable to write temp file: %s', $path));
        }

        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runRenderer(string $inputPath): array
    {
        $scriptPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'github-actions' . DIRECTORY_SEPARATOR . 'render-phpdecide-pr-comment.php';
        $command = sprintf('%s %s %s', escapeshellarg(PHP_BINARY), escapeshellarg($scriptPath), escapeshellarg($inputPath));

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new TestFilesystemException('Unable to start PR comment renderer process.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }
}
