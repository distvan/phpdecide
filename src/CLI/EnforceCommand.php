<?php

declare(strict_types=1);

namespace PhpDecide\CLI;

use InvalidArgumentException;
use PhpDecide\Config\PhpDecideDefaults;
use PhpDecide\Decision\FileDecisionRepository;
use PhpDecide\Decision\YamlDecisionLoader;
use PhpDecide\Enforcement\AnalyzerFinding;
use PhpDecide\Enforcement\AnalyzerFindingLoader;
use PhpDecide\Enforcement\DecisionViolation;
use PhpDecide\Enforcement\DecisionViolationMatcher;
use PhpDecide\Enforcement\EnforcementMatchResult;
use PhpDecide\Enforcement\PhpStanFindingLoader;
use PhpDecide\Enforcement\SemgrepFindingLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JsonException;
use Throwable;

#[AsCommand(
    name: 'enforce',
    description: 'Map external analyzer findings to decision IDs and report decision-linked violations.'
)]
final class EnforceCommand extends Command
{
    private const FORMAT_TEXT = 'text';
    private const FORMAT_JSON = 'json';

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
            'report',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to a JSON analyzer report. Expected fields per finding: tool, rule_id, path, message, optional line and severity.'
        );

        $this->addOption(
            'semgrep-report',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to a native Semgrep JSON report (object with results array).'
        );

        $this->addOption(
            'phpstan-report',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to a native PHPStan JSON report (object with files/messages structure).'
        );

        $this->addOption(
            'format',
            null,
            InputOption::VALUE_REQUIRED,
            'Output format: text or json.',
            self::FORMAT_TEXT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->resolveDir($input);
        $reportPath = (string) $input->getOption('report');
        $semgrepReportPath = (string) $input->getOption('semgrep-report');
        $phpStanReportPath = (string) $input->getOption('phpstan-report');
        $format = $this->normalizeFormat((string) $input->getOption('format'));

        $validationError = $this->validateInputs($dir, $reportPath, $semgrepReportPath, $phpStanReportPath, $format);
        if ($validationError !== null) {
            return $this->renderFailure($output, $validationError, $format);
        }

        try {
            $repository = new FileDecisionRepository(new YamlDecisionLoader($dir));
            $findings = $this->loadFindings($reportPath, $semgrepReportPath, $phpStanReportPath);
            $result = (new DecisionViolationMatcher())->match($repository, $findings);
        } catch (Throwable $e) {
            $message = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : sprintf('Unable to run enforcement mapping: %s', $e->getMessage());

            return $this->renderFailure($output, $message, $format);
        }

        $exitCode = Command::FAILURE;

        try {
            $exitCode = $this->renderResult($output, $result, $format);
        } catch (Throwable $e) {
            $exitCode = $this->renderFailure(
                $output,
                sprintf('Unable to render enforcement output: %s', $e->getMessage()),
                $format
            );
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

    private function validateInputs(string $dir, string $reportPath, string $semgrepReportPath, string $phpStanReportPath, string $format): ?string
    {
        $hasGenericReport = $reportPath !== '';
        $hasSemgrepReport = $semgrepReportPath !== '';
        $hasPhpStanReport = $phpStanReportPath !== '';
        $error = null;
        $selectedReportCount = 0;

        if ($hasGenericReport) {
            $selectedReportCount++;
        }
        if ($hasSemgrepReport) {
            $selectedReportCount++;
        }
        if ($hasPhpStanReport) {
            $selectedReportCount++;
        }

        if (!in_array($format, [self::FORMAT_TEXT, self::FORMAT_JSON], true)) {
            $error = sprintf('Unsupported format: %s. Expected one of: text, json.', $format);
        }

        if ($error === null && $selectedReportCount === 0) {
            $error = 'One of --report, --semgrep-report, or --phpstan-report is required.';
        }

        if ($error === null && $selectedReportCount > 1) {
            $error = 'Use only one of --report, --semgrep-report, or --phpstan-report.';
        }

        if ($error === null && !is_dir($dir)) {
            $error = sprintf('Decisions directory not found: %s', $dir);
        }

        return $error;
    }

    /**
     * @return list<AnalyzerFinding>
     */
    private function loadFindings(string $reportPath, string $semgrepReportPath, string $phpStanReportPath): array
    {
        if ($semgrepReportPath !== '') {
            return (new SemgrepFindingLoader())->loadFromFile($semgrepReportPath);
        }

        if ($phpStanReportPath !== '') {
            return (new PhpStanFindingLoader())->loadFromFile($phpStanReportPath);
        }

        return (new AnalyzerFindingLoader())->loadFromFile($reportPath);
    }

    private function renderResult(OutputInterface $output, EnforcementMatchResult $result, string $format): int
    {
        if ($format === self::FORMAT_JSON) {
            return $this->renderJsonResult($output, $result);
        }

        return $this->renderTextResult($output, $result);
    }

    private function renderTextResult(OutputInterface $output, EnforcementMatchResult $result): int
    {
        if (!$result->hasViolations()) {
            $output->writeln('<info>No decision-linked violations found.</info>');
            $this->printUnmappedSummary($output, $result->unmappedFindings());
            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<error>Decision enforcement failed.</error> Matched %d violation(s) across %d decision(s).',
            $result->totalViolations(),
            count($result->violationsByDecisionId())
        ));

        foreach ($result->violationsByDecisionId() as $violations) {
            if ($violations === []) {
                continue;
            }

            $decision = $violations[0]->decision();
            $output->writeln('');
            $output->writeln(sprintf('[%s] %s', $decision->id()->value(), $decision->title()));

            foreach ($violations as $violation) {
                $output->writeln(' - ' . $this->formatViolation($violation));
            }
        }

        $this->printUnmappedSummary($output, $result->unmappedFindings());

        return Command::FAILURE;
    }

    private function renderJsonResult(OutputInterface $output, EnforcementMatchResult $result): int
    {
        $exitCode = $result->hasViolations() ? Command::FAILURE : Command::SUCCESS;
        $payload = [
            'ok' => !$result->hasViolations(),
            'summary' => [
                'decision_count' => count($result->violationsByDecisionId()),
                'violation_count' => $result->totalViolations(),
                'unmapped_finding_count' => count($result->unmappedFindings()),
            ],
            'violations_by_decision' => $this->violationsByDecisionPayload($result),
            'unmapped_findings' => $this->findingsPayload($result->unmappedFindings()),
        ];

        $this->writeJson($output, $payload);

        return $exitCode;
    }

    private function renderError(OutputInterface $output, string $message, string $format): void
    {
        if ($format === self::FORMAT_JSON) {
            $this->writeJson($output, [
                'ok' => false,
                'error' => $message,
            ]);

            return;
        }

        $output->writeln('<error>' . $message . '</error>');
    }

    private function renderFailure(OutputInterface $output, string $message, string $format): int
    {
        try {
            $this->renderError($output, $message, $format);
        } catch (Throwable) {
            try {
                if ($format === self::FORMAT_JSON) {
                    $output->writeln('{"ok":false,"error":"Unable to render enforcement output."}');
                } else {
                    $output->writeln('<error>Unable to render enforcement output.</error>');
                }
            } catch (Throwable $ignored) {
                return Command::FAILURE;
            }
        }

        return Command::FAILURE;
    }

    private function normalizeFormat(string $format): string
    {
        return strtolower(trim($format));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function violationsByDecisionPayload(EnforcementMatchResult $result): array
    {
        $payload = [];

        foreach ($result->violationsByDecisionId() as $violations) {
            if ($violations === []) {
                continue;
            }

            $decision = $violations[0]->decision();
            $payload[] = [
                'decision_id' => $decision->id()->value(),
                'decision_title' => $decision->title(),
                'violations' => $this->violationsPayload($violations),
            ];
        }

        return $payload;
    }

    /**
     * @param list<DecisionViolation> $violations
     * @return list<array<string, mixed>>
     */
    private function violationsPayload(array $violations): array
    {
        $payload = [];

        foreach ($violations as $violation) {
            $payload[] = $this->findingPayload($violation->finding());
        }

        return $payload;
    }

    /**
     * @param list<AnalyzerFinding> $findings
     * @return list<array<string, mixed>>
     */
    private function findingsPayload(array $findings): array
    {
        $payload = [];

        foreach ($findings as $finding) {
            $payload[] = $this->findingPayload($finding);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function findingPayload(AnalyzerFinding $finding): array
    {
        return [
            'tool' => $finding->tool(),
            'rule_id' => $finding->ruleId(),
            'path' => $finding->path(),
            'line' => $finding->line(),
            'severity' => $finding->severity(),
            'message' => $finding->message(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(OutputInterface $output, array $payload): void
    {
        try {
            $output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Unable to encode JSON enforcement output.', 0, $e);
        }
    }

    private function formatViolation(DecisionViolation $violation): string
    {
        $finding = $violation->finding();
        $lineSuffix = $this->lineSuffix($finding);
        $severity = $this->severityPrefix($finding);

        return sprintf(
            '%s%s [%s via %s] %s',
            $finding->path(),
            $lineSuffix,
            $finding->ruleId(),
            $finding->tool(),
            $severity . $finding->message()
        );
    }

    /**
     * @param list<AnalyzerFinding> $unmappedFindings
     */
    private function printUnmappedSummary(OutputInterface $output, array $unmappedFindings): void
    {
        if ($unmappedFindings === []) {
            return;
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<comment>%d finding(s) did not map to any active scoped decision rule.</comment>',
            count($unmappedFindings)
        ));
    }

    private function lineSuffix(AnalyzerFinding $finding): string
    {
        $line = $finding->line();
        if ($line === null) {
            return '';
        }

        return ':' . (string) $line;
    }

    private function severityPrefix(AnalyzerFinding $finding): string
    {
        $severity = $finding->severity();
        if ($severity === null) {
            return '';
        }

        return strtoupper($severity) . ': ';
    }
}
