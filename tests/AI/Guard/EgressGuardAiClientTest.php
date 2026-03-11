<?php

declare(strict_types=1);

namespace PhpDecide\Tests\AI\Guard;

use PhpDecide\AI\AiClient;
use PhpDecide\AI\AiClientException;
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
            $guard->explainDecision('My key is sk-1234567890123456789012345', []);
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

        $guard->explainDecision('Token sk-1234567890123456789012345', []);

        self::assertSame(1, $inner->calls);
        self::assertIsString($inner->lastQuestion);
        self::assertStringNotContainsString('sk-1234567890123456789012345', $inner->lastQuestion);
        self::assertStringContainsString('[REDACTED:DLP.OPENAI_API_KEY]', $inner->lastQuestion);
    }

    public function testRedactsOutputWhenConfigured(): void
    {
        $inner = new FakeAiClient('Here is your token: ghp_abcdefghijklmnopqrstuvwxyz012345');

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
        self::assertStringNotContainsString('ghp_abcdefghijklmnopqrstuvwxyz012345', $out);
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
                'rationale' => ['sk-1234567890123456789012345'],
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
