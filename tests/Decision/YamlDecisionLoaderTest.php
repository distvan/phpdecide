<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Decision;

use InvalidArgumentException;
use PhpDecide\Decision\Decision;
use PhpDecide\Decision\YamlDecisionLoader;
use PHPUnit\Framework\TestCase;

final class YamlDecisionLoaderTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    public function testLoadYieldsDecisionsFromYamlFilesInStableOrder(): void
    {
        $dir = $this->createTempDir();

        // Intentionally write in reverse order to ensure loader ordering is stable.
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'DEC-0002.yaml', $this->validDecisionYaml('DEC-0002', 'Second decision'));
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'DEC-0001.yaml', $this->validDecisionYaml('DEC-0001', 'First decision'));

        // Should be ignored
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'README.txt', "hello\n");
        mkdir($dir . DIRECTORY_SEPARATOR . 'subdir');

        $loader = new YamlDecisionLoader($dir);
        $decisions = iterator_to_array($loader->load(), false);

        self::assertCount(2, $decisions);
        self::assertContainsOnlyInstancesOf(Decision::class, $decisions);
        self::assertSame('DEC-0001', $decisions[0]->id()->value());
        self::assertSame('DEC-0002', $decisions[1]->id()->value());
    }

    public function testConstructorThrowsIfDirectoryDoesNotExist(): void
    {
        $path = $this->createTempDir() . DIRECTORY_SEPARATOR . 'missing';
        try {
            $loader = new YamlDecisionLoader($path);
            iterator_to_array($loader->load(), false);
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('not a directory', $e->getMessage());
        }
    }

    public function testLoadThrowsIfYamlDoesNotParseToArray(): void
    {
        $dir = $this->createTempDir();
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'DEC-0001.yaml', "just-a-string\n");

        $loader = new YamlDecisionLoader($dir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid decision file:');

        // Force generator execution
        iterator_to_array($loader->load(), false);
    }

    public function testLoadUsesCacheWhenSignatureIsUnchanged(): void
    {
        $dir = $this->createTempDir();
        $file = $dir . DIRECTORY_SEPARATOR . 'DEC-0001.yaml';

        $yaml = $this->validDecisionYaml('DEC-0001', 'First decision');
        file_put_contents($file, $yaml);

        $mtime = filemtime($file);
        self::assertIsInt($mtime);

        $loader = new YamlDecisionLoader($dir);
        $first = iterator_to_array($loader->load(), false);

        self::assertCount(1, $first);
        self::assertSame('DEC-0001', $first[0]->id()->value());

        // Corrupt the YAML file but keep (mtime, size) unchanged so the cache signature matches.
        $bad = str_repeat('x', strlen($yaml));
        self::assertSame(strlen($yaml), strlen($bad));
        file_put_contents($file, $bad);
        touch($file, $mtime);

        $loader2 = new YamlDecisionLoader($dir);
        $second = iterator_to_array($loader2->load(), false);

        // If the loader parsed YAML again, it would throw. Success here implies the cache was used.
        self::assertCount(1, $second);
        self::assertSame('DEC-0001', $second[0]->id()->value());
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirRecursive($dir);
            }
        }

        $this->tempDirs = [];
    }

    private function validDecisionYaml(string $id, string $title): string
    {
        return <<<YAML
id: {$id}
title: {$title}
status: active
date: '2026-02-03'
scope:
  type: global
decision:
  summary: Summary for {$id}
  rationale:
    - Because it helps.
YAML;
    }

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdecide_tests_' . bin2hex(random_bytes(8));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new TempDirectoryCreationFailed("Unable to create temp dir: {$dir}");
        }

        $this->tempDirs[] = $dir;
        return $dir;
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

final class TempDirectoryCreationFailed extends \RuntimeException
{
}
