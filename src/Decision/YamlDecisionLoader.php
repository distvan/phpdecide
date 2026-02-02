<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use InvalidArgumentException;

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
        // Implementation for loading decisions from a YAML file goes here.
        return [];
    }
}
