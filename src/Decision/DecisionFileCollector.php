<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use DirectoryIterator;

final class DecisionFileCollector
{
    /**
     * Collect decision files in a directory.
     *
     * @return array{yamlFiles: list<string>, ymlFiles: list<string>}
     */
    public static function collect(string $dir): array
    {
        $yamlFiles = [];
        $ymlFiles = [];

        foreach (new DirectoryIterator($dir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->isLink()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());
            if ($ext === 'yaml') {
                $yamlFiles[] = $fileInfo->getPathname();
                continue;
            }

            if ($ext === 'yml') {
                $ymlFiles[] = $fileInfo->getPathname();
            }
        }

        sort($yamlFiles, SORT_STRING);
        sort($ymlFiles, SORT_STRING);

        return ['yamlFiles' => $yamlFiles, 'ymlFiles' => $ymlFiles];
    }
}
