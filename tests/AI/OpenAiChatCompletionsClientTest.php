<?php

declare(strict_types=1);

namespace PhpDecide\Tests\AI;

use PhpDecide\AI\AiClientException;
use PhpDecide\AI\Http\HttpClient;
use PhpDecide\AI\Http\HttpResponse;
use PhpDecide\AI\OpenAiChatCompletionsClient;
use PhpDecide\Decision\Decision;
use PhpDecide\Decision\DecisionFactory;
use PHPUnit\Framework\TestCase;

final class OpenAiChatCompletionsClientTest extends TestCase
{
    private const TEST_BASE_URL = 'https://example.test';

    public function testExplainDecisionReturnsTrimmedContent(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => "  Hello world\n"]],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm', httpClient: $http);
        $out = $client->explainDecision('Q?', []);

        self::assertSame('Hello world', $out);
    }

    public function testExplainDecisionThrowsOnInvalidJson(): void
    {
        $http = new FakeHttpClient(new HttpResponse(statusCode: 200, body: 'not-json'));
        $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm', httpClient: $http);

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('AI response was not valid JSON');
        $client->explainDecision('Q?', []);
    }

    public function testExplainDecisionThrowsOnHttpErrorWithMessage(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 500,
            body: json_encode(['error' => ['message' => 'boom']], JSON_THROW_ON_ERROR)
        ));
        $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm', httpClient: $http);

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('HTTP 500');
        $this->expectExceptionMessage('boom');
        $client->explainDecision('Q?', []);
    }

    public function testExplainDecisionAddsOpenRouterHintOn402(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 402,
            body: json_encode(['error' => ['message' => 'Provider returned error']], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(
            apiKey: 'k',
            model: 'm',
            baseUrl: 'https://openrouter.ai/api',
            httpClient: $http,
        );

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('OpenRouter returned HTTP 402');
        $client->explainDecision('Q?', []);
    }

    public function testInsecureSkipVerifyIsRejected(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm', insecureSkipVerify: true, httpClient: $http);

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('Insecure TLS verification');
        $client->explainDecision('Q?', []);
    }

    public function testHeaderValueNewlinesAreRejected(): void
    {
        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('contains newline characters');

        // The constructor is expected to throw before explainDecision() runs.
        (new OpenAiChatCompletionsClient(apiKey: "k\n", model: 'm', httpClient: new FakeHttpClient()))
            ->explainDecision('Q?', []);
    }

    public function testChatCompletionsPathCanBeCustomized(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(
            apiKey: 'k',
            model: 'm',
            baseUrl: self::TEST_BASE_URL,
            chatCompletionsPath: '/openai/v1/chat/completions',
            httpClient: $http,
        );

        $client->explainDecision('Q?', []);

        self::assertSame('https://example.test/openai/v1/chat/completions', $http->lastUrl);
    }

    public function testModelIsOmittedWhenEmpty(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: '', baseUrl: self::TEST_BASE_URL, httpClient: $http);
        $client->explainDecision('Q?', []);

        self::assertIsString($http->lastBody);
        $decoded = json_decode($http->lastBody, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayNotHasKey('model', $decoded);
    }

    public function testCustomAuthHeaderNameAndPrefixAreApplied(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(
            apiKey: 'secret',
            model: 'm',
            baseUrl: self::TEST_BASE_URL,
            authHeaderName: 'Api-Key',
            authPrefix: '',
            httpClient: $http,
        );

        $client->explainDecision('Q?', []);

        self::assertIsArray($http->lastHeaders);
        self::assertContains('Api-Key: secret', $http->lastHeaders);
    }

    public function testNonJsonHttpErrorIncludesAuthDiagnostics(): void
    {
        $http = new FakeHttpClient(new HttpResponse(statusCode: 401, body: 'Bad Authorization header'));

        $client = new OpenAiChatCompletionsClient(
            apiKey: 'secret',
            model: 'm',
            baseUrl: self::TEST_BASE_URL,
            authHeaderName: 'Api-Key',
            authPrefix: '',
            httpClient: $http,
        );

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('HTTP 401');
        $this->expectExceptionMessage('Auth header sent: Api-Key');
        $this->expectExceptionMessage('prefix: [empty]');
        $this->expectExceptionMessage('Bad Authorization header');

        $client->explainDecision('Q?', []);
    }

    public function testOrganizationAndProjectHeadersAreAddedWhenProvided(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(
            apiKey: 'k',
            model: 'm',
            organization: 'org_123',
            project: 'proj_456',
            httpClient: $http,
        );

        $client->explainDecision('Q?', []);

        self::assertIsArray($http->lastHeaders);
        self::assertContains('OpenAI-Organization: org_123', $http->lastHeaders);
        self::assertContains('OpenAI-Project: proj_456', $http->lastHeaders);
    }

    public function testSystemPromptOverrideIsUsed(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(
            apiKey: 'k',
            model: 'm',
            systemPromptOverride: 'SYSTEM OVERRIDE',
            httpClient: $http,
        );

        $client->explainDecision('Q?', []);

        self::assertIsString($http->lastBody);
        $decoded = json_decode($http->lastBody, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded['messages']);
        self::assertSame('system', $decoded['messages'][0]['role']);
        self::assertSame('SYSTEM OVERRIDE', $decoded['messages'][0]['content']);
    }

    public function testUtf8BomIsStrippedFromJsonResponse(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: "\xEF\xBB\xBF" . json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm', httpClient: $http);
        self::assertSame('ok', $client->explainDecision('Q?', []));
    }

    public function testThrowsWhenMessageContentMissing(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => []],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm', httpClient: $http);

        $this->expectException(AiClientException::class);
        $this->expectExceptionMessage('missing message content');
        $client->explainDecision('Q?', []);
    }

    public function testSendsCompactDecisionPayloadInUserMessage(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $decisionWithoutRulesOrPaths = $this->makeDecision(
            id: 'DEC-0001',
            scopeType: 'global',
            scopePaths: [],
            rules: null,
        );

        $decisionWithRulesAndPaths = $this->makeDecision(
            id: 'DEC-0002',
            scopeType: 'path',
            scopePaths: ['src/'],
            rules: ['allow' => ['use-psr-4'], 'forbid' => ['do-evil']],
        );

        $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm', httpClient: $http);
        $client->explainDecision('What is decided?', [$decisionWithoutRulesOrPaths, $decisionWithRulesAndPaths]);

        self::assertIsString($http->lastBody);
        $decoded = json_decode($http->lastBody, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        self::assertSame(0.2, $decoded['temperature']);
        self::assertFalse($decoded['stream']);

        $userContent = $decoded['messages'][1]['content'] ?? null;
        self::assertIsString($userContent);

        $json = $this->extractDecisionJsonFromUserMessage($userContent);
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertCount(2, $payload);

        self::assertSame('DEC-0001', $payload[0]['id']);
        self::assertArrayHasKey('scope', $payload[0]);
        self::assertSame('global', $payload[0]['scope']['type']);
        self::assertArrayNotHasKey('paths', $payload[0]['scope']);
        self::assertArrayNotHasKey('rules', $payload[0]);

        self::assertSame('DEC-0002', $payload[1]['id']);
        self::assertSame('path', $payload[1]['scope']['type']);
        self::assertSame(['src/'], $payload[1]['scope']['paths']);
        self::assertSame(['do-evil'], $payload[1]['rules']['forbid']);
        self::assertSame(['use-psr-4'], $payload[1]['rules']['allow']);
    }

    public function testCaInfoPathIsForwardedToHttpClient(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR)
        ));

        $client = new OpenAiChatCompletionsClient(
            apiKey: 'k',
            model: 'm',
            caInfoPath: 'C:\\tmp\\cacert.pem',
            httpClient: $http,
        );

        $client->explainDecision('Q?', []);

        self::assertSame('C:\\tmp\\cacert.pem', $http->lastCaInfoPath);
    }

    private function makeDecision(
        string $id,
        string $scopeType,
        array $scopePaths,
        ?array $rules,
    ): Decision {
        $data = [
            'id' => $id,
            'title' => 'Title ' . $id,
            'status' => 'active',
            'date' => '2026-01-01',
            'scope' => [
                'type' => $scopeType,
                'paths' => $scopePaths,
            ],
            'decision' => [
                'summary' => 'Summary ' . $id,
                'rationale' => ['Because.'],
            ],
        ];

        if ($rules !== null) {
            $data['rules'] = $rules;
        }

        return DecisionFactory::fromArray($data);
    }

    private function extractDecisionJsonFromUserMessage(string $userContent): string
    {
        $markerStart = "Recorded decisions (authoritative source of truth):\n";
        $markerEnd = "\n\nTask:";

        $start = strpos($userContent, $markerStart);
        self::assertNotFalse($start);
        $start += strlen($markerStart);

        $end = strpos($userContent, $markerEnd, $start);
        self::assertNotFalse($end);

        return substr($userContent, $start, $end - $start);
    }
}

final class FakeHttpClient implements HttpClient
{
    public ?string $lastUrl = null;
    /** @var list<string>|null */
    public ?array $lastHeaders = null;
    public ?string $lastBody = null;
    public ?int $lastTimeoutSeconds = null;
    public ?string $lastCaInfoPath = null;

    private HttpResponse $nextResponse;

    public function __construct(?HttpResponse $nextResponse = null)
    {
        $this->nextResponse = $nextResponse ?? new HttpResponse(statusCode: 200, body: '');
    }

    public function post(
        string $url,
        array $headers,
        string $body,
        int $timeoutSeconds,
        ?string $caInfoPath = null,
    ): HttpResponse {
        $this->lastUrl = $url;
        $this->lastHeaders = $headers;
        $this->lastBody = $body;
        $this->lastTimeoutSeconds = $timeoutSeconds;
        $this->lastCaInfoPath = $caInfoPath;

        return $this->nextResponse;
    }
}
