<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Enforcement;

use InvalidArgumentException;
use PhpDecide\Enforcement\AnalyzerFindingLoader;
use PHPUnit\Framework\TestCase;

final class AnalyzerFindingLoaderTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    public function testLoadFromFileRejectsObjectWithoutFindingsArray(): void
    {
        $reportPath = $this->createTempJsonFile([
            'results' => [[
                'check_id' => 'doctrine/orm',
                'path' => 'src/Order/OrderService.php',
            ]],
        ]);

        $loader = new AnalyzerFindingLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Enforcement report must be a JSON array or an object with a findings array.');

        $loader->loadFromFile($reportPath);
    }

    public function testLoadFromFileAcceptsObjectWithFindingsArray(): void
    {
        $reportPath = $this->createTempJsonFile([
            'findings' => [[
                'tool' => 'semgrep',
                'rule_id' => 'doctrine/orm',
                'path' => 'src/Order/OrderService.php',
                'message' => 'Doctrine ORM import detected.',
                'line' => 12,
            ]],
        ]);

        $findings = (new AnalyzerFindingLoader())->loadFromFile($reportPath);

        self::assertCount(1, $findings);
        self::assertSame('semgrep', $findings[0]->tool());
        self::assertSame('doctrine/orm', $findings[0]->ruleId());
        self::assertSame('src/Order/OrderService.php', $findings[0]->path());
        self::assertSame('Doctrine ORM import detected.', $findings[0]->message());
        self::assertSame(12, $findings[0]->line());
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }

        $this->tempFiles = [];
    }

    /**
     * @param array<string, mixed>|list<mixed> $payload
     */
    private function createTempJsonFile(array $payload): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpdecide_analyzer_findings_');
        if ($path === false) {
            self::fail('Unable to create temp JSON file.');
        }

        $written = file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if ($written === false) {
            self::fail('Unable to write temp JSON file.');
        }

        $this->tempFiles[] = $path;

        return $path;
    }
}
