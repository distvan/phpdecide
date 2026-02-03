<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;
use DirectoryIterator;
use UnexpectedValueException;

final class YamlDecisionLoader implements DecisionLoader
{
    private string $directory;
    
    public function __construct(string $directory)
    {
        if(!is_dir($directory)) {
            throw new InvalidArgumentException("The provided path is not a directory: {$directory}");
        }
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
    }

    /**
     * @return Decision[]
     */
    public function load(): iterable
    {
        try {
            foreach (new DirectoryIterator($this->directory) as $fileInfo) {
                if (!$fileInfo->isFile() || (strtolower($fileInfo->getExtension()) !== 'yaml')) {
                    continue;
                }

                $file = $fileInfo->getPathname();

                $data = Yaml::parseFile($file);
            
                if (!is_array($data)) {
                    throw new InvalidArgumentException("Invalid decision file: {$file}");
                }
                
                yield DecisionFactory::fromArray($data);
            }
        } catch (UnexpectedValueException $e) {
            throw new InvalidArgumentException("Unable to read directory: {$this->directory}", previous: $e);
        }
    }
}
