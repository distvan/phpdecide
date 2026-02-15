<?php

declare(strict_types=1);

namespace PhpDecide\Tests\CLI;

use PhpDecide\CLI\DecisionsLintCommand;
use PhpDecide\Config\PhpDecideDefaults;
use PhpDecide\Tests\Support\TestFilesystemException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DecisionsLintCommandTest extends TestCase
{
    private const DECISION_TITLE_NO_ORMS = 'No ORMs';
    private const DECISION_SUMMARY_USE_RAW_SQL = 'Use raw SQL';

    /** @var list<string> */
    private array $tempDirs = [];

    private ?string $originalCwd = null;

    protected function tearDown(): void
    {
        if ($this->originalCwd !== null) {
            @chdir($this->originalCwd);
            $this->originalCwd = null;
        }

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
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-no-orms.yaml',
            $this->minimalDecisionYaml('DEC-0001', self::DECISION_TITLE_NO_ORMS, self::DECISION_SUMMARY_USE_RAW_SQL)
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('OK', $tester->getDisplay(true));
    }

    public function testFailsForInvalidYaml(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

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
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

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
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

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
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001.yml',
            $this->minimalDecisionYaml('DEC-0001', self::DECISION_TITLE_NO_ORMS, self::DECISION_SUMMARY_USE_RAW_SQL)
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir, '--require-any' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Unsupported extension', $tester->getDisplay(true));
    }

    public function testSucceedsWhenNoDecisionFilesExistByDefault(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('No decision files found', $tester->getDisplay(true));
    }

    public function testFailsWhenNoDecisionFilesExistAndRequireAnyIsSet(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir, '--require-any' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('No decision files found', $tester->getDisplay(true));
    }

    public function testFailsWhenDecisionsDirectoryDoesNotExist(): void
    {
        $projectDir = $this->createTempProjectDir();
        $missingDir = $projectDir . DIRECTORY_SEPARATOR . 'does-not-exist';

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $missingDir]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Decisions directory not found', $tester->getDisplay(true));
    }

    public function testFailsWhenDecisionFileParsesToScalarInsteadOfMapping(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-scalar.yaml',
            "hello"
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('decision file must parse to a YAML mapping/object', $tester->getDisplay(true));
        self::assertStringContainsString('DEC-0001-scalar.yaml', $tester->getDisplay(true));
    }

    public function testFailsWhenStringListFieldsContainNonStringOrEmptyItems(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-invalid-lists.yaml',
            <<<YAML
id: DEC-0001
title: Invalid lists
status: active
date: '2026-02-03'
scope:
  type: path
  paths:
    - src/*
    - 123
decision:
  summary: Something
  rationale:
    - Because.
    - ''
  alternatives:
    - 456
examples:
  allowed:
    - ''
rules:
  allow:
    - 1
ai:
  explain_style: short
  keywords:
    - 2
YAML
        );

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => $decisionsDir]);

        self::assertSame(Command::FAILURE, $exitCode);
        $display = $tester->getDisplay(true);
        self::assertStringContainsString('Decision lint failed', $display);
        self::assertStringContainsString('scope.paths[1] must be a non-empty string', $display);
        self::assertStringContainsString('decision.rationale[1] must be a non-empty string', $display);
        self::assertStringContainsString('decision.alternatives[0] must be a non-empty string', $display);
        self::assertStringContainsString('rules.allow[0] must be a non-empty string', $display);
        self::assertStringContainsString('ai.keywords[0] must be a non-empty string', $display);
    }

    public function testDefaultsToProjectDecisionsDirWhenDirOptionIsEmptyString(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0001-no-orms.yaml',
            $this->minimalDecisionYaml('DEC-0001', self::DECISION_TITLE_NO_ORMS, self::DECISION_SUMMARY_USE_RAW_SQL)
        );

        $this->originalCwd = (string) getcwd();
        if (!chdir($projectDir)) {
            throw new TestFilesystemException("Unable to change cwd to: {$projectDir}");
        }

        $tester = new CommandTester(new DecisionsLintCommand());
        $exitCode = $tester->execute(['--dir' => '']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('OK', $tester->getDisplay(true));
    }

    private function createTempProjectDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdecide_lint_tests_' . bin2hex(random_bytes(8));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new TestFilesystemException("Unable to create temp dir: {$dir}");
        }

        $this->tempDirs[] = $dir;

        mkdir($dir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR);

        return $dir;
    }

    private function writeFile(string $path, string $contents): void
    {
        $bytes = file_put_contents($path, $contents);
        if ($bytes === false) {
            throw new TestFilesystemException("Unable to write file: {$path}");
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
