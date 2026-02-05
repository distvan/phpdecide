<?php

declare(strict_types=1);

namespace PhpDecide\Tests\CLI;

use PhpDecide\CLI\ExplainCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ExplainCommandTest extends TestCase
{
    private const TITLE_NO_ORMS = 'No ORMs';
    private const SUMMARY_RAW_SQL = 'Use raw SQL';
    private const QUESTION_WHY_NO_ORMS = 'Why no ORMs?';

    private string $originalCwd;

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new UnableToDetermineCwd('Unable to determine current working directory.');
        }

        $this->originalCwd = $cwd;
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirRecursive($dir);
            }
        }

        $this->tempDirs = [];
    }

    public function testExecutePrintsNoDecisionMessageWhenNothingMatches(): void
    {
        $projectDir = $this->createTempProjectDir();
        $this->writeDecisionYaml(
            $projectDir,
            'DEC-0001-no-orms.yaml',
            $this->decisionYaml('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_RAW_SQL)
        );

        chdir($projectDir);

        $tester = new CommandTester(new ExplainCommand());
        $exitCode = $tester->execute(['question' => 'What about event sourcing?']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No recorded decision covers this topic.', $tester->getDisplay(true));
    }

    public function testExecutePrintsExplanationWhenDecisionMatches(): void
    {
        $projectDir = $this->createTempProjectDir();
        $this->writeDecisionYaml(
            $projectDir,
            'DEC-0001-no-orms.yaml',
            $this->decisionYaml('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_RAW_SQL)
        );

        chdir($projectDir);

        $tester = new CommandTester(new ExplainCommand());
        $exitCode = $tester->execute(['question' => self::QUESTION_WHY_NO_ORMS]);

        self::assertSame(0, $exitCode);

        $out = $tester->getDisplay(true);
        self::assertStringContainsString('Found 1 relevant decision(s)', $out);
        self::assertStringContainsString('[DEC-0001]', $out);
        self::assertStringContainsString('No ORMs', $out);
        self::assertStringContainsString('Use raw SQL', $out);
    }

    public function testExecuteThrowsWhenDecisionsDirectoryMissing(): void
    {
        $projectDir = $this->createTempProjectDir(withDecisionsDir: false);
        chdir($projectDir);

        $tester = new CommandTester(new ExplainCommand());

        $this->expectException(\InvalidArgumentException::class);
        $tester->execute(['question' => self::QUESTION_WHY_NO_ORMS]);
    }

    public function testExecuteFiltersByPathWhenProvided(): void
    {
        $projectDir = $this->createTempProjectDir();
        $this->writeDecisionYaml(
            $projectDir,
            'DEC-0001-no-orms.yaml',
            $this->decisionYamlWithScopePaths('DEC-0001', self::TITLE_NO_ORMS, self::SUMMARY_RAW_SQL, ['src/*'])
        );

        chdir($projectDir);

        $tester = new CommandTester(new ExplainCommand());

        $exitCode = $tester->execute(['question' => self::QUESTION_WHY_NO_ORMS, '--path' => 'tests/SomeTest.php']);
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No recorded decision covers this topic.', $tester->getDisplay(true));

        $exitCode = $tester->execute(['question' => self::QUESTION_WHY_NO_ORMS, '--path' => 'src/Foo.php']);
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('[DEC-0001]', $tester->getDisplay(true));
    }

    private function createTempProjectDir(bool $withDecisionsDir = true): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdecide_cli_tests_' . bin2hex(random_bytes(8));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new TempDirectoryCreationFailed("Unable to create temp dir: {$dir}");
        }

        $this->tempDirs[] = $dir;

        if ($withDecisionsDir) {
            mkdir($dir . DIRECTORY_SEPARATOR . '.decisions');
        }

        return $dir;
    }

    private function writeDecisionYaml(string $projectDir, string $filename, string $contents): void
    {
        $path = $projectDir . DIRECTORY_SEPARATOR . '.decisions' . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $contents);
    }

    private function decisionYaml(string $id, string $title, string $summary): string
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

        /** @param string[] $paths */
        private function decisionYamlWithScopePaths(string $id, string $title, string $summary, array $paths): string
        {
                $yamlPaths = '';
                foreach ($paths as $path) {
                        $yamlPaths .= "    - {$path}\n";
                }

                return <<<YAML
id: {$id}
title: {$title}
status: active
date: '2026-02-03'
scope:
    type: path
    paths:
{$yamlPaths}decision:
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

final class UnableToDetermineCwd extends \RuntimeException
{
}

final class TempDirectoryCreationFailed extends \RuntimeException
{
}
