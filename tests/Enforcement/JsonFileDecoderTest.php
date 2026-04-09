<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Enforcement;

use InvalidArgumentException;
use PhpDecide\Enforcement\JsonFileDecoder;
use PHPUnit\Framework\TestCase;

final class JsonFileDecoderTest extends TestCase
{
    public const STREAM_SCHEME = 'json-file-decoder-test';

    public static function setUpBeforeClass(): void
    {
        if (!in_array(self::STREAM_SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::STREAM_SCHEME, JsonFileDecoderTestStreamWrapper::class);
        }
    }

    protected function tearDown(): void
    {
        JsonFileDecoderTestStreamWrapper::$files = [];
    }

    public function testDecodeFileFailsCleanlyWhenFileIsNotReadable(): void
    {
        JsonFileDecoderTestStreamWrapper::$files['report.json'] = [
            'contents' => '{}',
            'readable' => false,
            'open_succeeds' => true,
        ];

        $decoder = new JsonFileDecoder();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to read enforcement report: ' . self::STREAM_SCHEME . '://report.json');

        $decoder->decodeFile(self::STREAM_SCHEME . '://report.json', 'Enforcement report');
    }

    public function testDecodeFileSuppressesWarningsWhenFileReadFails(): void
    {
        JsonFileDecoderTestStreamWrapper::$files['warning.json'] = [
            'contents' => '{}',
            'readable' => true,
            'open_succeeds' => false,
        ];

        $decoder = new JsonFileDecoder();
        $warnings = [];

        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];
            return true;
        });

        try {
            try {
                $decoder->decodeFile(self::STREAM_SCHEME . '://warning.json', 'Enforcement report');
                self::fail('Expected InvalidArgumentException to be thrown.');
            } catch (InvalidArgumentException $e) {
                self::assertSame(
                    'Unable to read enforcement report: ' . self::STREAM_SCHEME . '://warning.json',
                    $e->getMessage()
                );
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
    }
}

final class JsonFileDecoderTestStreamWrapper
{
    /** @var array<string, array{contents: string, readable: bool, open_succeeds: bool}> */
    public static array $files = [];

    public mixed $context = null;

    private string $path = '';
    private int $position = 0;

    public function stream_open(string $path, string $_mode, int $_options, ?string &$openedPath): bool // NOSONAR
    {
        $key = self::normalizePath($path);
        $file = self::$files[$key] ?? null;
        if ($file === null) {
            return false;
        }

        if ($file['open_succeeds'] !== true) {
            return false;
        }

        $this->path = $key;
        $this->position = 0;
        $openedPath = $path;

        return true;
    }

    public function stream_read(int $count): string // NOSONAR
    {
        $contents = self::$files[$this->path]['contents'];
        $chunk = substr($contents, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool // NOSONAR
    {
        return $this->position >= strlen(self::$files[$this->path]['contents']);
    }

    public function stream_stat(): array // NOSONAR
    {
        return $this->buildStat($this->path);
    }

    public function url_stat(string $path, int $_flags): array|false // NOSONAR
    {
        $key = self::normalizePath($path);
        if (!isset(self::$files[$key])) {
            return false;
        }

        return $this->buildStat($key);
    }

    private function buildStat(string $key): array
    {
        $stat = stat(__FILE__);
        if ($stat === false) {
            return array_fill(0, 13, 0);
        }

        $mode = 0100000 | (self::$files[$key]['readable'] ? 0444 : 0000);
        $stat[2] = $mode;
        $stat['mode'] = $mode;

        return $stat;
    }

    private static function normalizePath(string $path): string
    {
        return substr($path, strlen(JsonFileDecoderTest::STREAM_SCHEME . '://'));
    }
}
