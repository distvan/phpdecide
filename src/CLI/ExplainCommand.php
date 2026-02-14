<?php

declare(strict_types=1);

namespace PhpDecide\CLI;

use PhpDecide\AI\AiClientFactory;
use PhpDecide\Config\PhpDecideDefaults;
use PhpDecide\Explain\ExplainService;
use PhpDecide\Decision\FileDecisionRepository;
use PhpDecide\Decision\YamlDecisionLoader;
use PhpDecide\Explain\AiClientExplainer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'explain',
    description: 'Explain architectural decisions based on recorded project decisions.'
)]
final class ExplainCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
                'question',
                InputArgument::REQUIRED,
                'The question you want to ask about project decisions.'
            );

        $this->addOption(
            'path',
            null,
            InputOption::VALUE_REQUIRED,
            'Optional file path to filter decisions by scope (only decisions applicable to this path will be considered).'
        );

        $this->addOption(
            'ai',
            null,
            InputOption::VALUE_NONE,
            'Use AI to summarize recorded decisions (presentation layer only; requires env configuration).'
        );

        $this->addOption(
            'ai-strict',
            null,
            InputOption::VALUE_NONE,
            'Fail if AI is enabled but unavailable or errors occur (default is to fall back to plain text).'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $question = (string)$input->getArgument('question');
        $path = (string)$input->getOption('path');
        $useAi = (bool) $input->getOption('ai');
        $aiStrict = (bool) $input->getOption('ai-strict');

        $loader = new YamlDecisionLoader(PhpDecideDefaults::DECISIONS_DIR);
        $repository = new FileDecisionRepository($loader);

        $aiExplainer = null;
        if ($useAi) {
            $client = AiClientFactory::fromEnvironment();
            if ($client === null) {
                $output->writeln('<error>AI is not configured.</error>');
                $output->writeln('Set env vars like PHPDECIDE_AI_API_KEY and PHPDECIDE_AI_MODEL to enable AI mode.');
                $output->writeln('If you hit TLS/certificate errors on Windows, set PHPDECIDE_AI_CAINFO (or CURL_CA_BUNDLE) to a CA bundle path.');

                if ($aiStrict) {
                    return Command::FAILURE;
                }

                $output->writeln('<comment>Falling back to plain explanation.</comment>');
            } else {
                $aiExplainer = new AiClientExplainer($client);
            }
        }

        $explainService = new ExplainService($repository, $aiExplainer);

        try {
            $explanation = $explainService->explain($question, $path);
        } catch (Throwable $e) {
            if (!$useAi || $aiStrict) {
                throw $e;
            }

            $output->writeln('<comment>AI failed; falling back to plain explanation.</comment>');
            $output->writeln('<comment>AI error: ' . $e->getMessage() . '</comment>');
            $aiExplainer = null;
            $explainService = new ExplainService($repository);
            $explanation = $explainService->explain($question, $path);
        }

        if (!$explanation->hasDecisions()) {
            $output->writeln('<comment>No recorded decision covers this topic.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Found %d relevant decision(s)</info>',
            count($explanation->decisions())
        ));
        $output->writeln('');

        if ($useAi && $aiExplainer !== null) {
            $output->writeln('<comment>AI summary (presentation-only; does not invent rules)</comment>');
            $output->writeln('');
        }

        $output->writeln($explanation->message());
        $output->writeln('');

        return Command::SUCCESS;
    }
}
