<?php

declare(strict_types=1);

namespace PhpDecide\Tests\CLI;

use PhpDecide\CLI\EnforceCommand;
use PhpDecide\Config\PhpDecideDefaults;
use PhpDecide\Tests\Support\TestFilesystemException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class EnforceCommandTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    public function testFailsWhenReportContainsDecisionLinkedViolations(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $this->writeFile(
            $reportPath,
            json_encode([
                'findings' => [[
                    'tool' => 'semgrep',
                    'rule_id' => 'doctrine/orm',
                    'path' => 'src/Order/OrderService.php',
                    'line' => 12,
                    'severity' => 'error',
                    'message' => 'Doctrine ORM import detected.',
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $display = $tester->getDisplay(true);
        self::assertStringContainsString('Decision enforcement failed.', $display);
        self::assertStringContainsString('[DEC-0003] No ORM in Order domain', $display);
        self::assertStringContainsString('src/Order/OrderService.php:12 [doctrine/orm via semgrep]', $display);
    }

    public function testJsonFormatReturnsStructuredViolations(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $this->writeFile(
            $reportPath,
            json_encode([
                'findings' => [[
                    'tool' => 'semgrep',
                    'rule_id' => 'doctrine/orm',
                    'path' => 'src/Order/OrderService.php',
                    'line' => 12,
                    'severity' => 'error',
                    'message' => 'Doctrine ORM import detected.',
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertFalse($payload['ok']);
        self::assertSame(1, $payload['summary']['decision_count']);
        self::assertSame(1, $payload['summary']['violation_count']);
        self::assertSame('DEC-0003', $payload['violations_by_decision'][0]['decision_id']);
        self::assertSame('No ORM in Order domain', $payload['violations_by_decision'][0]['decision_title']);
        self::assertSame('doctrine/orm', $payload['violations_by_decision'][0]['violations'][0]['rule_id']);
        self::assertSame('src/Order/OrderService.php', $payload['violations_by_decision'][0]['violations'][0]['path']);
    }

    public function testSucceedsWhenFindingsDoNotMapToScopedDecisionRules(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $this->writeFile(
            $reportPath,
            json_encode([[
                'tool' => 'semgrep',
                'rule_id' => 'doctrine/orm',
                'path' => 'src/Infrastructure/Persistence/Doctrine/OrderRecord.php',
                'message' => 'Doctrine ORM import detected.',
            ]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $display = $tester->getDisplay(true);
        self::assertStringContainsString('No decision-linked violations found.', $display);
        self::assertStringContainsString('1 finding(s) did not map to any active scoped decision rule.', $display);
    }

    public function testJsonFormatReturnsStructuredSuccessWithUnmappedFindings(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $this->writeFile(
            $reportPath,
            json_encode([[
                'tool' => 'semgrep',
                'rule_id' => 'doctrine/orm',
                'path' => 'src/Infrastructure/Persistence/Doctrine/OrderRecord.php',
                'message' => 'Doctrine ORM import detected.',
            ]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertTrue($payload['ok']);
        self::assertSame(0, $payload['summary']['decision_count']);
        self::assertSame(0, $payload['summary']['violation_count']);
        self::assertSame(1, $payload['summary']['unmapped_finding_count']);
        self::assertCount(0, $payload['violations_by_decision']);
        self::assertSame('doctrine/orm', $payload['unmapped_findings'][0]['rule_id']);
    }

    public function testLoadsGenericReportEncodedAsUtf16Json(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $json = json_encode([
            'findings' => [[
                'tool' => 'semgrep',
                'rule_id' => 'doctrine/orm',
                'path' => 'src/Order/OrderService.php',
                'line' => 12,
                'severity' => 'error',
                'message' => 'Doctrine ORM import detected.',
            ]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        self::assertIsString($json);

        file_put_contents($reportPath, mb_convert_encoding($json, 'UTF-16LE', 'UTF-8'));

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertFalse($payload['ok']);
        self::assertSame('DEC-0003', $payload['violations_by_decision'][0]['decision_id']);
    }

    public function testFailsForInvalidReportShape(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $reportPath,
            json_encode([
                'findings' => [[
                    'tool' => 'semgrep',
                    'path' => 'src/Order/OrderService.php',
                    'message' => 'Doctrine ORM import detected.',
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('findings[0].rule_id must be a non-empty string', $tester->getDisplay(true));
    }

    public function testFailsWhenNativeSemgrepReportContainsDecisionLinkedViolations(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'semgrep.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $this->writeFile(
            $reportPath,
            json_encode([
                'results' => [[
                    'check_id' => 'doctrine/orm',
                    'path' => 'src/Order/OrderService.php',
                    'start' => ['line' => 7],
                    'extra' => [
                        'message' => 'Doctrine ORM import detected.',
                        'severity' => 'error',
                    ],
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--semgrep-report' => $reportPath,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $display = $tester->getDisplay(true);
        self::assertStringContainsString('[DEC-0003] No ORM in Order domain', $display);
        self::assertStringContainsString('src/Order/OrderService.php:7 [doctrine/orm via semgrep]', $display);
    }

    public function testLoadsSemgrepReportEncodedAsUtf16Json(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'semgrep.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $json = json_encode([
            'results' => [[
                'check_id' => 'doctrine/orm',
                'path' => 'src/Order/OrderService.php',
                'start' => ['line' => 7],
                'extra' => [
                    'message' => 'Doctrine ORM import detected.',
                    'severity' => 'error',
                ],
            ]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        self::assertIsString($json);

        file_put_contents($reportPath, mb_convert_encoding($json, 'UTF-16LE', 'UTF-8'));

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--semgrep-report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertFalse($payload['ok']);
        self::assertSame('DEC-0003', $payload['violations_by_decision'][0]['decision_id']);
    }

    public function testFailsWhenNativePhpStanReportContainsDecisionLinkedViolations(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'phpstan.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0005-phpstan-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0005', 'No ORM in Order domain via PHPStan', ['src/Order/*'], ['phpstan.doctrine.orm'])
        );

        $this->writeFile(
            $reportPath,
            json_encode([
                'totals' => [
                    'errors' => 0,
                    'file_errors' => 1,
                ],
                'files' => [
                    'src/Order/OrderService.php' => [
                        'errors' => 1,
                        'messages' => [[
                            'message' => 'Doctrine ORM usage is not allowed in the Order domain.',
                            'line' => 14,
                            'identifier' => 'phpstan.doctrine.orm',
                        ]],
                    ],
                ],
                'errors' => [],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--phpstan-report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertFalse($payload['ok']);
        self::assertSame('DEC-0005', $payload['violations_by_decision'][0]['decision_id']);
        self::assertSame('phpstan', $payload['violations_by_decision'][0]['violations'][0]['tool']);
        self::assertSame('phpstan.doctrine.orm', $payload['violations_by_decision'][0]['violations'][0]['rule_id']);
    }

    public function testLoadsPhpStanReportEncodedAsUtf16Json(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'phpstan.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0005-phpstan-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0005', 'No ORM in Order domain via PHPStan', ['src/Order/*'], ['phpstan.doctrine.orm'])
        );

        $json = json_encode([
            'totals' => [
                'errors' => 0,
                'file_errors' => 1,
            ],
            'files' => [
                'src/Order/OrderService.php' => [
                    'errors' => 1,
                    'messages' => [[
                        'message' => 'Doctrine ORM usage is not allowed in the Order domain.',
                        'line' => 14,
                        'identifier' => 'phpstan.doctrine.orm',
                    ]],
                ],
            ],
            'errors' => [],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        self::assertIsString($json);

        file_put_contents($reportPath, mb_convert_encoding($json, 'UTF-16LE', 'UTF-8'));

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--phpstan-report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertFalse($payload['ok']);
        self::assertSame('DEC-0005', $payload['violations_by_decision'][0]['decision_id']);
    }

    public function testPhpStanReportMapsToDec0005WithoutUsingRepositoryDecisions(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'phpstan.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0005-phpstan-no-orm.yaml',
            $this->decisionYamlWithRules(
                'DEC-0005',
                'No ORM in Order domain via PHPStan',
                ['examples/fixtures/phpstan/src/Order/*', 'examples/fixtures/phpstan/src/Order/**/*'],
                ['phpstan.doctrine.orm']
            )
        );

        $this->writeFile(
            $reportPath,
            json_encode([
                'totals' => [
                    'errors' => 0,
                    'file_errors' => 1,
                ],
                'files' => [
                    'examples/fixtures/phpstan/src/Order/OrderService.php' => [
                        'errors' => 1,
                        'messages' => [[
                            'message' => 'Doctrine ORM symbol detected in the Order domain example.',
                            'line' => 7,
                            'identifier' => 'phpstan.doctrine.orm',
                        ]],
                    ],
                ],
                'errors' => [],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--phpstan-report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertFalse($payload['ok']);
        self::assertSame(1, $payload['summary']['decision_count']);
        self::assertSame(1, $payload['summary']['violation_count']);
        self::assertSame(0, $payload['summary']['unmapped_finding_count']);
        self::assertSame('DEC-0005', $payload['violations_by_decision'][0]['decision_id']);
        self::assertSame('No ORM in Order domain via PHPStan', $payload['violations_by_decision'][0]['decision_title']);
        self::assertSame('phpstan', $payload['violations_by_decision'][0]['violations'][0]['tool']);
        self::assertSame('phpstan.doctrine.orm', $payload['violations_by_decision'][0]['violations'][0]['rule_id']);
        self::assertSame('examples/fixtures/phpstan/src/Order/OrderService.php', $payload['violations_by_decision'][0]['violations'][0]['path']);
        self::assertSame(7, $payload['violations_by_decision'][0]['violations'][0]['line']);
    }

    public function testFailsWhenNativeSemgrepReportMatchesTemplateDecision(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'semgrep.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0004-no-template-logic.yaml',
            $this->decisionYamlWithRules(
                'DEC-0004',
                'No business logic in templates',
                ['examples/fixtures/templates/*', 'examples/fixtures/templates/**/*'],
                ['twig/business-logic']
            )
        );

        $this->writeFile(
            $reportPath,
            json_encode([
                'results' => [[
                    'check_id' => 'twig/business-logic',
                    'path' => 'examples/fixtures/templates/order/calculate_total.html.twig',
                    'start' => ['line' => 1],
                    'extra' => [
                        'message' => 'Business logic is not allowed in templates.',
                        'severity' => 'error',
                    ],
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--semgrep-report' => $reportPath,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $display = $tester->getDisplay(true);
        self::assertStringContainsString('[DEC-0004] No business logic in templates', $display);
        self::assertStringContainsString('examples/fixtures/templates/order/calculate_total.html.twig:1 [twig/business-logic via semgrep]', $display);
    }

    public function testFailsWhenBothReportOptionsAreProvided(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';
        $semgrepReportPath = $projectDir . DIRECTORY_SEPARATOR . 'semgrep.json';
        $phpStanReportPath = $projectDir . DIRECTORY_SEPARATOR . 'phpstan.json';

        $this->writeFile($reportPath, json_encode([], JSON_THROW_ON_ERROR));
        $this->writeFile($semgrepReportPath, json_encode(['results' => []], JSON_THROW_ON_ERROR));
        $this->writeFile($phpStanReportPath, json_encode(['files' => [], 'errors' => []], JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
            '--semgrep-report' => $semgrepReportPath,
            '--phpstan-report' => $phpStanReportPath,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Use only one of --report, --semgrep-report, or --phpstan-report.', $tester->getDisplay(true));
    }

    public function testJsonFormatReturnsStructuredErrorForInvalidReport(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $reportPath,
            json_encode([
                'findings' => [[
                    'tool' => 'semgrep',
                    'path' => 'src/Order/OrderService.php',
                    'message' => 'Doctrine ORM import detected.',
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertFalse($payload['ok']);
        self::assertStringContainsString('findings[0].rule_id must be a non-empty string', $payload['error']);
    }

    public function testJsonFormatReturnsStructuredErrorWhenInitialRenderFails(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $this->writeFile(
            $reportPath,
            json_encode([
                'findings' => [[
                    'tool' => 'semgrep',
                    'rule_id' => 'doctrine/orm',
                    'path' => 'src/Order/OrderService.php',
                    'line' => 12,
                    'severity' => 'error',
                    'message' => 'Doctrine ORM import detected.',
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $command = new EnforceCommand();
        $output = new class extends BufferedOutput {
            private bool $shouldFail = true;

            public function writeln(string|iterable $messages, int $options = self::OUTPUT_NORMAL): void
            {
                if ($this->shouldFail) {
                    $this->shouldFail = false;

                    throw new TestFilesystemException('Simulated output failure.');
                }

                parent::writeln($messages, $options);
            }
        };

        $exitCode = $command->run(new ArrayInput([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
            '--format' => 'json',
        ]), $output);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($output->fetch());
        self::assertFalse($payload['ok']);
        self::assertStringContainsString('Unable to render enforcement output: Simulated output failure.', $payload['error']);
    }

    public function testJsonFormatFallbackOutputIsValidJsonWhenErrorRenderingFailsTwice(): void
    {
        $projectDir = $this->createTempProjectDir();
        $decisionsDir = $projectDir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';

        $this->writeFile(
            $decisionsDir . DIRECTORY_SEPARATOR . 'DEC-0003-no-orm.yaml',
            $this->decisionYamlWithRules('DEC-0003', 'No ORM in Order domain', ['src/Order/*'], ['doctrine/orm'])
        );

        $this->writeFile(
            $reportPath,
            json_encode([
                'findings' => [[
                    'tool' => 'semgrep',
                    'rule_id' => 'doctrine/orm',
                    'path' => 'src/Order/OrderService.php',
                    'line' => 12,
                    'severity' => 'error',
                    'message' => 'Doctrine ORM import detected.',
                ]],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );

        $command = new EnforceCommand();
        $output = new class extends BufferedOutput {
            private int $failureCount = 0;

            public function writeln(string|iterable $messages, int $options = self::OUTPUT_NORMAL): void
            {
                if ($this->failureCount < 2) {
                    $this->failureCount++;

                    throw new TestFilesystemException('Simulated repeated output failure.');
                }

                parent::writeln($messages, $options);
            }
        };

        $exitCode = $command->run(new ArrayInput([
            '--dir' => $decisionsDir,
            '--report' => $reportPath,
            '--format' => 'json',
        ]), $output);

        self::assertSame(Command::FAILURE, $exitCode);
        $rawOutput = trim($output->fetch());
        self::assertSame(
            '{"ok":false,"error":"Unable to render enforcement output."}',
            $rawOutput
        );

        $payload = $this->decodeJsonOutput($rawOutput);
        self::assertFalse($payload['ok']);
        self::assertSame('Unable to render enforcement output.', $payload['error']);
    }

    public function testJsonFormatSubstitutesInvalidUtf8InErrorMessages(): void
    {
        $projectDir = $this->createTempProjectDir();
        $reportPath = $projectDir . DIRECTORY_SEPARATOR . 'findings.json';
        $invalidDir = $projectDir . DIRECTORY_SEPARATOR . "bad\xB1dir";

        $this->writeFile($reportPath, json_encode([], JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new EnforceCommand());
        $exitCode = $tester->execute([
            '--dir' => $invalidDir,
            '--report' => $reportPath,
            '--format' => 'json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $payload = $this->decodeJsonOutput($tester->getDisplay(true));
        self::assertFalse($payload['ok']);
        self::assertStringContainsString('Decisions directory not found:', $payload['error']);
        self::assertStringContainsString('efbfbd', bin2hex($payload['error']));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirRecursive($dir);
            }
        }

        $this->tempDirs = [];
    }

    private function createTempProjectDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpdecide_enforce_tests_' . bin2hex(random_bytes(8));
        if (!mkdir($dir) && !is_dir($dir)) {
            throw new TestFilesystemException('Unable to create temp dir: ' . $dir);
        }

        $decisionsDir = $dir . DIRECTORY_SEPARATOR . PhpDecideDefaults::DECISIONS_DIR;
        if (!mkdir($decisionsDir) && !is_dir($decisionsDir)) {
            throw new TestFilesystemException('Unable to create decisions dir: ' . $decisionsDir);
        }

        $this->tempDirs[] = $dir;

        return $dir;
    }

    /** @param list<string> $paths
     *  @param list<string> $forbidRules
     */
    private function decisionYamlWithRules(string $id, string $title, array $paths, array $forbidRules): string
    {
        $yamlPaths = '';
        foreach ($paths as $path) {
            $yamlPaths .= "    - {$path}\n";
        }

        $yamlRules = '';
        foreach ($forbidRules as $rule) {
            $yamlRules .= "    - {$rule}\n";
        }

        return <<<YAML
id: {$id}
title: {$title}
status: active
date: '2026-03-19'
scope:
    type: path
    paths:
{$yamlPaths}decision:
    summary: Keep ORM out of the domain layer.
    rationale:
        - Preserve persistence ignorance.
rules:
    forbid:
{$yamlRules}    allow: []
YAML;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new TestFilesystemException(sprintf('Unable to write file: %s', $path));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonOutput(string $output): array
    {
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function removeDirRecursive(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($dir);
    }
}
