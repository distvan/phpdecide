<?php

declare(strict_types=1);

namespace PhpDecide\Tests\CLI;

use PhpDecide\CLI\DecisionsLintCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DecisionsLintCommandTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirRecursive($dir);
            }
        }

        $this->tempDirs = [];
    }

    public function testSucceedsForValidDecisionYaml(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . '.decisions';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-no-orms.yaml',
            $this->minimalDecisionYaml('DEC-0001', 'No ORMs', 'Use raw SQL')
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('OK', $tester->getDisplay(true));
    }

    public function testFailsForInvalidYaml(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . '.decisions';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-broken.yaml',
            "id: DEC-0001\ntitle: Broken\nstatus: active\ndate: '2026-02-03'\nscope: { type: global }\ndecision: [unterminated"
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Decision lint failed', $tester->getDisplay(true));
        self::assertStringContainsString('DEC-0001-broken.yaml', $tester->getDisplay(true));
    }

    public function testFailsWhenRequiredFieldMissing(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . '.decisions';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-missing-title.yaml',
            <<<YAML
id: DEC-0001
status: active
date: '2026-02-03'
scope:
  type: global
decision:
  summary: Something
  rationale:
    - Because.
YAML
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Missing required field: title', $tester->getDisplay(true));
    }

    public function testFailsOnDuplicateDecisionIds(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . '.decisions';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-a.yaml',
            $this->minimalDecisionYaml('DEC-0001', 'A', 'S')
        );
        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-b.yaml',
            $this->minimalDecisionYaml('DEC-0001', 'B', 'S')
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Duplicate decision id', $tester->getDisplay(true));
        self::assertStringContainsString('DEC-0001', $tester->getDisplay(true));
    }

    public function testFailsWhenYmlExtensionIsUsed(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . '.decisions';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001.yml',
            $this->minimalDecisionYaml('DEC-0001', 'No ORMs', 'Use raw SQL')
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir, '--require-any' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Unsupported extension', $tester->getDisplay(true));
    }

    public function testSucceedsWhenNoDecisionFilesExistByDefault(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . '.decisions';

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No decision files found', $tester->getDisplay(true));
    }

    private function createTempProjectDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdecide_lint_tests_' . bin2hex(random_bytes(8));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create temp dir: {$dir}");
        }

        $this->tempDirs[] = $dir;

        mkdir($dir . DIRECTORY_SEPARATOR . '.decisions');

        return $dir;
    }

    private function writeFile(string $path, string $contents): void
    {
        $bytes = file_put_contents($path, $contents);
        if ($bytes === false) {
            throw new \RuntimeException("Unable to write file: {$path}");
        }
    }

    private function minimalDecisionYaml(string $id, string $title, string $summary): string
    {
        return <<<YAML
id: {$id}
title: {$title}
status: active
date: '2026-02-03'
scope:
  type: global
decision:
  summary: {$summary}
  rationale:
    - Because it helps.
YAML;
    }

    private function removeDirRecursive(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
                continue;
            }
            unlink($fileInfo->getPathname());
        }

        rmdir($dir);
    }
}
