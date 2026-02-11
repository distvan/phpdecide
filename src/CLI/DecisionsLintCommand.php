<?php

declare(strict_types=1);

namespace PhpDecide\CLI;

use DirectoryIterator;
use PhpDecide\Config\PhpDecideDefaults;
use PhpDecide\Decision\DecisionFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;
use InvalidArgumentException;

#[AsCommand(
    name: 'decisions:lint',
    description: 'Validate .decisions/*.yaml files (syntax + schema) for CI usage.'
)]
final class DecisionsLintCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'dir',
            null,
            InputOption::VALUE_REQUIRED,
            'Directory that contains decision files.',
            PhpDecideDefaults::DECISIONS_DIR
        );

        $this->addOption(
            'require-any',
            null,
            InputOption::VALUE_NONE,
            'Fail if no .yaml decision files exist.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->resolveDir($input);
        $requireAny = (bool) $input->getOption('require-any');

        $exitCode = Command::SUCCESS;

        if (!is_dir($dir)) {
            $output->writeln(sprintf('<error>Decisions directory not found:</error> %s', $dir));
            return Command::FAILURE;
        }

        try {
            [$yamlFiles, $errors] = $this->collectDecisionFiles($dir);
        } catch (UnexpectedValueException) {
            $output->writeln(sprintf('<error>Unable to read directory:</error> %s', $dir));
            return Command::FAILURE;
        }

        if (empty($yamlFiles)) {
            $exitCode = $this->reportNoYamlFiles($output, $dir, $errors, $requireAny);
        } else {
            [$decisions, $lintErrors] = $this->lintDecisionFiles($yamlFiles);
            $errors = array_merge($errors, $lintErrors);
            $errors = array_merge($errors, $this->duplicateIdErrors($decisions));

            if (!empty($errors)) {
                $this->printErrors($output, $errors);
                $exitCode = Command::FAILURE;
            } else {
                $output->writeln(sprintf(
                    '<info>OK</info> Linted %d file(s), loaded %d decision(s).',
                    count($yamlFiles),
                    count($decisions)
                ));
                $exitCode = Command::SUCCESS;
            }
        }

        return $exitCode;
    }

    private function resolveDir(InputInterface $input): string
    {
        $dir = (string) $input->getOption('dir');
        if ($dir === '') {
            return PhpDecideDefaults::DECISIONS_DIR;
        }

        return $dir;
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function collectDecisionFiles(string $dir): array
    {
        $yamlFiles = [];
        $errors = [];

        foreach (new DirectoryIterator($dir) as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->isLink()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());
            if ($ext === 'yml') {
                $errors[] = sprintf(
                    'Unsupported extension ".yml" (loader only reads ".yaml"): %s',
                    $fileInfo->getFilename()
                );
                continue;
            }

            if ($ext === 'yaml') {
                $yamlFiles[] = $fileInfo->getPathname();
            }
        }

        sort($yamlFiles, SORT_STRING);

        return [$yamlFiles, $errors];
    }

    /**
     * @param list<string> $errors
     */
    private function reportNoYamlFiles(OutputInterface $output, string $dir, array $errors, bool $requireAny): int
    {
        if (!empty($errors)) {
            $this->printErrors($output, $errors);
            return Command::FAILURE;
        }

        $message = sprintf('No decision files found in %s', $dir);
        if ($requireAny) {
            $output->writeln('<error>' . $message . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<comment>' . $message . '</comment>');
        return Command::SUCCESS;
    }

    /**
     * @param list<string> $files
     * @return array{0: list<\PhpDecide\Decision\Decision>, 1: list<string>}
     */
    private function lintDecisionFiles(array $files): array
    {
        $decisions = [];
        $errors = [];

        foreach ($files as $file) {
            $fileLabel = basename($file);

            try {
                $data = Yaml::parseFile($file, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            } catch (ParseException $e) {
                $errors[] = sprintf('%s: invalid YAML (%s)', $fileLabel, $e->getMessage());
                continue;
            }

            if (!is_array($data)) {
                $errors[] = sprintf('%s: decision file must parse to a YAML mapping/object', $fileLabel);
                continue;
            }

            try {
                $decision = DecisionFactory::fromArray($data);
                $decisions[] = $decision;
            } catch (InvalidArgumentException $e) {
                $errors[] = sprintf('%s: %s', $fileLabel, $e->getMessage());
                continue;
            }

            $errors = array_merge($errors, $this->validateDecisionArrays($data, $fileLabel));
        }

        return [$decisions, $errors];
    }

    /**
     * @param list<\PhpDecide\Decision\Decision> $decisions
     * @return list<string>
     */
    private function duplicateIdErrors(array $decisions): array
    {
        $errors = [];
        $seenIds = [];

        foreach ($decisions as $decision) {
            $id = $decision->id()->value();
            if (isset($seenIds[$id])) {
                $errors[] = sprintf('Duplicate decision id "%s" (seen in multiple files)', $id);
                continue;
            }
            $seenIds[$id] = true;
        }

        return $errors;
    }

    /**
     * @param list<string> $errors
     */
    private function printErrors(OutputInterface $output, array $errors): void
    {
        $output->writeln('<error>Decision lint failed.</error>');
        foreach ($errors as $error) {
            $output->writeln(' - ' . $error);
        }
    }

    /**
     * @return list<string>
     */
    private function validateDecisionArrays(array $data, string $fileLabel): array
    {
        $errors = [];

        $errors = array_merge($errors, $this->validateStringListField($data, ['scope', 'paths'], 'scope.paths', $fileLabel));
        $errors = array_merge($errors, $this->validateStringListField($data, ['decision', 'rationale'], 'decision.rationale', $fileLabel));
        $errors = array_merge($errors, $this->validateStringListField($data, ['decision', 'alternatives'], 'decision.alternatives', $fileLabel));
        $errors = array_merge($errors, $this->validateStringListField($data, ['examples', 'allowed'], 'examples.allowed', $fileLabel));
        $errors = array_merge($errors, $this->validateStringListField($data, ['examples', 'forbidden'], 'examples.forbidden', $fileLabel));
        $errors = array_merge($errors, $this->validateStringListField($data, ['rules', 'forbid'], 'rules.forbid', $fileLabel));
        $errors = array_merge($errors, $this->validateStringListField($data, ['rules', 'allow'], 'rules.allow', $fileLabel));
        $errors = array_merge($errors, $this->validateStringListField($data, ['ai', 'keywords'], 'ai.keywords', $fileLabel));

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     * @return list<string>
     */
    private function validateStringListField(array $data, array $path, string $fieldLabel, string $fileLabel): array
    {
        $value = $this->getNestedValue($data, $path);
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            return [sprintf('%s: %s must be an array of strings', $fileLabel, $fieldLabel)];
        }

        $errors = [];
        foreach ($value as $i => $item) {
            if (!is_string($item) || trim($item) === '') {
                $errors[] = sprintf('%s: %s[%d] must be a non-empty string', $fileLabel, $fieldLabel, (int) $i);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function getNestedValue(array $data, array $path): mixed
    {
        $current = $data;

        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }

            $current = $current[$key];
        }

        return $current;
    }
}
