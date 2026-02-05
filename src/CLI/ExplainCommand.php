<?php

declare(strict_types=1);

namespace PhpDecide\CLI;

use PhpDecide\Explain\ExplainService;
use PhpDecide\Decision\FileDecisionRepository;
use PhpDecide\Decision\YamlDecisionLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $question = (string)$input->getArgument('question');

        $loader = new YamlDecisionLoader('.decisions');
        $repository = new FileDecisionRepository($loader);
        $explainService = new ExplainService($repository);
        $explanation = $explainService->explain($question);

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
        $output->writeln($explanation->message());
        $output->writeln('');

        return Command::SUCCESS;
    }
}
