<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;
use UnexpectedValueException;

final class YamlDecisionLoader implements DecisionLoader
{
    private const CACHE_VERSION = 1;
    private const DEFAULT_CACHE_FILENAME = '.phpdecide-decisions.cache';

    private string $directory;
    private ?string $cacheFile;
    
    public function __construct(string $directory, ?string $cacheFile = null, bool $enableCache = true)
    {
        if(!is_dir($directory)) {
            throw new InvalidArgumentException("The provided path is not a directory: {$directory}");
        }
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (!$enableCache) {
            $this->cacheFile = null;
        } else {
            $this->cacheFile = $cacheFile ?? ($this->directory . DIRECTORY_SEPARATOR . self::DEFAULT_CACHE_FILENAME);
        }
    }

    /**
     * @return Decision[]
     */
    public function load(): iterable
    {
        try {
            $files = DecisionFileCollector::collect($this->directory)['yamlFiles'];
            $signature = $this->buildSignature($files);

            if ($this->cacheFile !== null) {
                $cached = $this->tryLoadFromCache($signature);
                if ($cached !== null) {
                    foreach ($cached as $decision) {
                        yield $decision;
                    }

                    return;
                }
            }

            $decisions = [];

            foreach ($files as $file) {
                try {
                    $data = Yaml::parseFile($file, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
                } catch (ParseException $e) {
                    throw new InvalidArgumentException("Invalid YAML in decision file: {$file}", previous: $e);
                }

                if (!is_array($data)) {
                    throw new InvalidArgumentException("Invalid decision file: {$file}");
                }

                $decision = DecisionFactory::fromArray($data);
                $decisions[] = $decision;
                yield $decision;
            }

            if ($this->cacheFile !== null) {
                $this->tryWriteCache($signature, $decisions);
            }
        } catch (UnexpectedValueException $e) {
            throw new InvalidArgumentException("Unable to read directory: {$this->directory}", previous: $e);
        }
    }

    /**
     * @param list<string> $files
     * @return list<array{path: string, mtime: int, size: int}>
     */
    private function buildSignature(array $files): array
    {
        $signature = [];
        foreach ($files as $file) {
            clearstatcache(true, $file);
            $mtime = $this->safeFileMTime($file);
            $size = $this->safeFileSize($file);
            if ($mtime === null || $size === null) {
                // If we can't stat a file, don't risk using a stale cache.
                return [];
            }

            $signature[] = [
                'path' => $file,
                'mtime' => $mtime,
                'size' => $size,
            ];
        }

        return $signature;
    }

    /**
     * @param list<array{path: string, mtime: int, size: int}> $signature
     * @return Decision[]|null
     */
    private function tryLoadFromCache(array $signature): ?array
    {
        if ($signature === [] || $this->cacheFile === null) {
            return null;
        }

        $payload = $this->readCachePayload();
        if ($payload === null) {
            return null;
        }

        return $this->extractDecisionsFromCachePayload($payload, $signature);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCachePayload(): ?array
    {
        if ($this->cacheFile === null) {
            return null;
        }

        $raw = $this->safeReadFile($this->cacheFile);
        if ($raw === null || $raw === '') {
            return null;
        }

        $payload = $this->safeUnserialize($raw, ['allowed_classes' => $this->allowedCacheClasses()]);
        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<array{path: string, mtime: int, size: int}> $signature
     * @return Decision[]|null
     */
    private function extractDecisionsFromCachePayload(array $payload, array $signature): ?array
    {
        $valid = (($payload['version'] ?? null) === self::CACHE_VERSION);
        $valid = $valid && (($payload['signature'] ?? null) === $signature);

        $candidate = null;
        if ($valid) {
            $candidate = $payload['decisions'] ?? null;
            $valid = is_array($candidate);
        }

        if ($valid) {
            foreach ($candidate as $decision) {
                if (!$decision instanceof Decision) {
                    $valid = false;
                    break;
                }
            }
        }

        return $valid ? $candidate : null;
    }

    /**
     * @param list<array{path: string, mtime: int, size: int}> $signature
     * @param Decision[] $decisions
     */
    private function tryWriteCache(array $signature, array $decisions): void
    {
        if ($signature === [] || $this->cacheFile === null) {
            return;
        }

        $payload = [
            'version' => self::CACHE_VERSION,
            'signature' => $signature,
            'decisions' => $decisions,
        ];

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            $this->safeMkdir($dir, 0777, true);
        }

        // Best-effort; cache must never break normal behavior.
        $this->safeWriteFile($this->cacheFile, serialize($payload));
    }

    private function safeFileMTime(string $path): ?int
    {
        if (!is_file($path)) {
            return null;
        }

        $mtime = $this->trapWarnings(static fn() => filemtime($path));
        if (!is_int($mtime)) {
            return null;
        }

        return $mtime;
    }

    private function safeFileSize(string $path): ?int
    {
        if (!is_file($path)) {
            return null;
        }

        $size = $this->trapWarnings(static fn() => filesize($path));
        if (!is_int($size)) {
            return null;
        }

        return $size;
    }

    private function safeReadFile(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $raw = $this->trapWarnings(static fn() => file_get_contents($path));
        return is_string($raw) ? $raw : null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function safeUnserialize(string $raw, array $options): mixed
    {
        return $this->trapWarnings(static fn() => unserialize($raw, $options));
    }

    private function safeMkdir(string $dir, int $mode, bool $recursive): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        $created = $this->trapWarnings(static fn() => mkdir($dir, $mode, $recursive));
        return $created === true || is_dir($dir);
    }

    private function safeWriteFile(string $path, string $content): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !$this->safeMkdir($dir, 0777, true)) {
            return false;
        }

        if (!is_writable($dir)) {
            return false;
        }

        $written = $this->trapWarnings(static fn() => file_put_contents($path, $content, LOCK_EX));
        return is_int($written);
    }

    private function trapWarnings(callable $fn): mixed
    {
        $hadWarning = false;
        set_error_handler(
            static function (int $_) use (&$hadWarning): bool {
                $hadWarning = true;
                return true;
            },
            E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE
        );

        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }

        return $hadWarning ? null : $result;
    }

    /**
     * @return list<class-string>
     */
    private function allowedCacheClasses(): array
    {
        return [
            \DateTimeImmutable::class,
            Decision::class,
            DecisionContent::class,
            DecisionId::class,
            DecisionStatus::class,
            Examples::class,
            Rules::class,
            Scope::class,
            ScopeType::class,
            AiMetadata::class,
            References::class,
        ];
    }
}
