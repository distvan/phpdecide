<?php

declare(strict_types=1);

namespace PhpDecide\Tests\AI\Guard;

use PhpDecide\AI\AiClient;
use PhpDecide\AI\AiClientException;
use PhpDecide\AI\Guard\AuditLogger;
use PhpDecide\AI\Guard\DlpScanner;
use PhpDecide\AI\Guard\EgressGuardAiClient;
use PhpDecide\AI\Guard\GuardPolicy;
use PhpDecide\AI\Guard\NullAuditLogger;
use PhpDecide\Decision\DecisionFactory;
use PHPUnit\Framework\TestCase;

final class EgressGuardAiClientTest extends TestCase
{
    public function testBlocksWhenSecretDetectedInQuestion(): void
    {
        $inner = new FakeAiClient('ok');

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: true,
            inputDlpAction: 'block',
            outputDlpAction: 'sanitize',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: false,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new NullAuditLogger(),
        );

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('blocked by egress guard');

        try {
            $guard->explainDecision('My key is ' . self::fakeOpenAiKey(), []);
        } finally {
            self::assertSame(0, $inner->calls);
        }
    }

    public function testSanitizesQuestionWhenConfigured(): void
    {
        $inner = new FakeAiClient('ok');

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: true,
            inputDlpAction: 'sanitize',
            outputDlpAction: 'sanitize',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: false,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new NullAuditLogger(),
        );

        $guard->explainDecision('Token ' . self::fakeOpenAiKey(), []);

        self::assertSame(1, $inner->calls);
        self::assertIsString($inner->lastQuestion);
        self::assertStringNotContainsString(self::fakeOpenAiKey(), $inner->lastQuestion);
        self::assertStringContainsString('[REDACTED:DLP.OPENAI_API_KEY]', $inner->lastQuestion);
    }

    public function testRedactsOutputWhenConfigured(): void
    {
        $inner = new FakeAiClient('Here is your token: ' . self::fakeGithubToken());

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: true,
            inputDlpAction: 'monitor',
            outputDlpAction: 'sanitize',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: false,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new NullAuditLogger(),
        );

        $out = $guard->explainDecision('Q', []);
        self::assertStringNotContainsString(self::fakeGithubToken(), $out);
        self::assertStringContainsString('[REDACTED:DLP.GITHUB_TOKEN]', $out);
    }

    public function testBlocksWhenSensitiveDataDetectedInDecisionsPayload(): void
    {
        $inner = new FakeAiClient('ok');

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: true,
            inputDlpAction: 'sanitize',
            outputDlpAction: 'sanitize',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: false,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new NullAuditLogger(),
        );

        $decision = DecisionFactory::fromArray([
            'id' => 'DEC-0001',
            'title' => 'Contains secret',
            'status' => 'active',
            'date' => '2026-01-01',
            'scope' => ['type' => 'global'],
            'decision' => [
                'summary' => 'Do not leak',
                'rationale' => [self::fakeOpenAiKey()],
            ],
        ]);

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('sensitive data detected in recorded decisions payload');

        try {
            $guard->explainDecision('Q', [$decision]);
        } finally {
            self::assertSame(0, $inner->calls);
        }
    }

    public function testBlocksWhenEffectivePromptExceedsMaxChars(): void
    {
        $inner = new FakeAiClient('ok');

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 4,
            dlpEnabled: false,
            inputDlpAction: 'monitor',
            outputDlpAction: 'sanitize',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: false,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new NullAuditLogger(),
        );

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('input too large');

        try {
            $guard->explainDecision('Q', []);
        } finally {
            self::assertSame(0, $inner->calls);
        }
    }

    public function testOutputBlockActionThrowsWhenSecretDetectedInOutput(): void
    {
        $inner = new FakeAiClient('Token: ' . self::fakeGithubToken());

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: true,
            inputDlpAction: 'monitor',
            outputDlpAction: 'block',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: false,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new NullAuditLogger(),
        );

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('AI response blocked by egress guard');

        $guard->explainDecision('Q', []);
    }

    public function testMonitorActionsDoNotModifyInputOrOutputButWriteAuditEvents(): void
    {
        $inner = new FakeAiClient('Output token ' . self::fakeGithubToken());
        $audit = new SpyAuditLogger();

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: true,
            inputDlpAction: 'monitor',
            outputDlpAction: 'monitor',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: true,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: $audit,
        );

        $question = 'Input token ' . self::fakeOpenAiKey();
        $out = $guard->explainDecision($question, []);

        self::assertSame($question, $inner->lastQuestion);
        self::assertSame('Output token ' . self::fakeGithubToken(), $out);
        self::assertCount(3, $audit->events);
        self::assertSame('phpdecide.ai_guard.input_findings', $audit->events[0]['type']);
        self::assertSame('phpdecide.ai_guard.allow', $audit->events[1]['type']);
        self::assertSame('phpdecide.ai_guard.output_findings', $audit->events[2]['type']);
    }

    public function testDisablesAuditWhenAuditIsOff(): void
    {
        $inner = new FakeAiClient('ok');
        $audit = new SpyAuditLogger();

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: false,
            inputDlpAction: 'monitor',
            outputDlpAction: 'monitor',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: false,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: $audit,
        );

        self::assertSame('ok', $guard->explainDecision('Q', []));
        self::assertCount(0, $audit->events);
    }

    public function testGuardInternalErrorInFailClosedIsWrappedAsGuardBlock(): void
    {
        $inner = new FakeAiClient('ok');

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: false,
            inputDlpAction: 'monitor',
            outputDlpAction: 'monitor',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: true,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new ThrowingAuditLogger(new \RuntimeException('audit sink crash')),
        );

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('internal error');

        try {
            $guard->explainDecision('Q', []);
        } finally {
            self::assertSame(0, $inner->calls);
        }
    }

    public function testGuardInternalErrorInFailOpenFallsBackToInnerClient(): void
    {
        $inner = new FakeAiClient('ok-after-fallback');

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_open',
            inputMaxChars: 8000,
            dlpEnabled: false,
            inputDlpAction: 'monitor',
            outputDlpAction: 'monitor',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: true,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new ThrowingAuditLogger(new \RuntimeException('audit sink crash')),
        );

        self::assertSame('ok-after-fallback', $guard->explainDecision('Q', []));
        self::assertSame(1, $inner->calls);
    }

    public function testInnerRuntimeErrorInFailOpenBubblesWithoutRetry(): void
    {
        $inner = new ThrowOnceThenReturnAiClient(new \RuntimeException('provider crash'), 'ok-after-fallback');

        $policy = new GuardPolicy(
            id: 't',
            version: 'v',
            failureMode: 'fail_open',
            inputMaxChars: 8000,
            dlpEnabled: false,
            inputDlpAction: 'monitor',
            outputDlpAction: 'monitor',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: false,
        );

        $guard = new EgressGuardAiClient(
            inner: $inner,
            policy: $policy,
            dlpScanner: new DlpScanner(),
            auditLogger: new NullAuditLogger(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('provider crash');

        try {
            $guard->explainDecision('Q', []);
        } finally {
            self::assertSame(1, $inner->calls);
        }
    }

    private static function fakeOpenAiKey(): string
    {
        return 'sk' . '-1234567890123456789012345';
    }

    private static function fakeGithubToken(): string
    {
        return 'ghp' . '_abcdefghijklmnopqrstuvwxyz012345';
    }
}

final class FakeAiClient implements AiClient
{
    public int $calls = 0;
    public ?string $lastQuestion = null;

    /** @var list<\PhpDecide\Decision\Decision>|null */
    public ?array $lastDecisions = null;

    public function __construct(private readonly string $response)
    {
    }

    public function explainDecision(string $question, array $decisions): string
    {
        $this->calls++;
        $this->lastQuestion = $question;
        $this->lastDecisions = $decisions;

        return $this->response;
    }
}

final class SpyAuditLogger implements AuditLogger
{
    /** @var list<array<string, mixed>> */
    public array $events = [];

    public function log(array $event): void
    {
        $this->events[] = $event;
    }
}

final class ThrowingAuditLogger implements AuditLogger
{
    public function __construct(private readonly \Throwable $throwable)
    {
    }

    public function log(array $event): void
    {
        throw $this->throwable;
    }
}

final class ThrowOnceThenReturnAiClient implements AiClient
{
    public int $calls = 0;
    private bool $thrown = false;

    public function __construct(private readonly \Throwable $throwable, private readonly string $response)
    {
    }

    public function explainDecision(string $question, array $decisions): string
    {
        $this->calls++;

        if (!$this->thrown) {
            $this->thrown = true;
            throw $this->throwable;
        }

        return $this->response;
    }
}
