<?php

declare(strict_types=1);

namespace PhpDecide\CLI;

use DirectoryIterator;
use PhpDecide\Decision\DecisionFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

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
            '.decisions'
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
        $dir = (string) $input->getOption('dir');
        if ($dir === '') {
            $dir = '.decisions';
        }

        if (!is_dir($dir)) {
            $output->writeln(sprintf('<error>Decisions directory not found:</error> %s', $dir));
            return Command::FAILURE;
        }

        $yamlFiles = [];
        $errors = [];

        try {
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
        } catch (\UnexpectedValueException $e) {
            $output->writeln(sprintf('<error>Unable to read directory:</error> %s', $dir));
            return Command::FAILURE;
        }

        sort($yamlFiles, SORT_STRING);

        if (count($yamlFiles) === 0) {
            if (count($errors) > 0) {
                $output->writeln('<error>Decision lint failed.</error>');
                foreach ($errors as $error) {
                    $output->writeln(' - ' . $error);
                }
                return Command::FAILURE;
            }

            $message = sprintf('No decision files found in %s', $dir);
            if ((bool) $input->getOption('require-any')) {
                $output->writeln('<error>' . $message . '</error>');
                return Command::FAILURE;
            }

            $output->writeln('<comment>' . $message . '</comment>');
            return Command::SUCCESS;
        }

        $decisions = [];

        foreach ($yamlFiles as $file) {
            try {
                $data = Yaml::parseFile($file, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            } catch (ParseException $e) {
                $errors[] = sprintf('%s: invalid YAML (%s)', basename($file), $e->getMessage());
                continue;
            }

            if (!is_array($data)) {
                $errors[] = sprintf('%s: decision file must parse to a YAML mapping/object', basename($file));
                continue;
            }

            try {
                $decision = DecisionFactory::fromArray($data);
                $decisions[] = $decision;
            } catch (\InvalidArgumentException $e) {
                $errors[] = sprintf('%s: %s', basename($file), $e->getMessage());
                continue;
            }

            // Extra strictness (lint-only): ensure list-like arrays contain only strings.
            $errors = array_merge($errors, $this->validateDecisionArrays($data, basename($file)));
        }

        // Cross-file checks.
        $seenIds = [];
        foreach ($decisions as $decision) {
            $id = $decision->id()->value();
            if (isset($seenIds[$id])) {
                $errors[] = sprintf('Duplicate decision id "%s" (seen in multiple files)', $id);
                continue;
            }
            $seenIds[$id] = true;
        }

        if (count($errors) > 0) {
            $output->writeln('<error>Decision lint failed.</error>');
            foreach ($errors as $error) {
                $output->writeln(' - ' . $error);
            }
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>OK</info> Linted %d file(s), loaded %d decision(s).',
            count($yamlFiles),
            count($decisions)
        ));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function validateDecisionArrays(array $data, string $fileLabel): array
    {
        $errors = [];

        // scope.paths
        if (isset($data['scope']['paths']) && is_array($data['scope']['paths'])) {
            foreach ($data['scope']['paths'] as $i => $pattern) {
                if (!is_string($pattern) || trim($pattern) === '') {
                    $errors[] = sprintf('%s: scope.paths[%d] must be a non-empty string', $fileLabel, (int) $i);
                }
            }
        }

        // decision.rationale
        if (isset($data['decision']['rationale']) && is_array($data['decision']['rationale'])) {
            foreach ($data['decision']['rationale'] as $i => $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = sprintf('%s: decision.rationale[%d] must be a non-empty string', $fileLabel, (int) $i);
                }
            }
        }

        // decision.alternatives
        if (isset($data['decision']['alternatives']) && is_array($data['decision']['alternatives'])) {
            foreach ($data['decision']['alternatives'] as $i => $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = sprintf('%s: decision.alternatives[%d] must be a non-empty string', $fileLabel, (int) $i);
                }
            }
        }

        // examples.allowed/forbidden
        if (isset($data['examples']['allowed']) && is_array($data['examples']['allowed'])) {
            foreach ($data['examples']['allowed'] as $i => $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = sprintf('%s: examples.allowed[%d] must be a non-empty string', $fileLabel, (int) $i);
                }
            }
        }
        if (isset($data['examples']['forbidden']) && is_array($data['examples']['forbidden'])) {
            foreach ($data['examples']['forbidden'] as $i => $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = sprintf('%s: examples.forbidden[%d] must be a non-empty string', $fileLabel, (int) $i);
                }
            }
        }

        // rules.allow/forbid
        if (isset($data['rules']['forbid']) && is_array($data['rules']['forbid'])) {
            foreach ($data['rules']['forbid'] as $i => $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = sprintf('%s: rules.forbid[%d] must be a non-empty string', $fileLabel, (int) $i);
                }
            }
        }
        if (isset($data['rules']['allow']) && is_array($data['rules']['allow'])) {
            foreach ($data['rules']['allow'] as $i => $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = sprintf('%s: rules.allow[%d] must be a non-empty string', $fileLabel, (int) $i);
                }
            }
        }

        // ai.keywords
        if (isset($data['ai']['keywords']) && is_array($data['ai']['keywords'])) {
            foreach ($data['ai']['keywords'] as $i => $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = sprintf('%s: ai.keywords[%d] must be a non-empty string', $fileLabel, (int) $i);
                }
            }
        }

        return $errors;
    }
}
