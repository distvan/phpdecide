<?php

declare(strict_types=1);

namespace PhpDecide\Tests\AI;

use PhpDecide\AI\AiClientException;
use PhpDecide\AI\Http\HttpClient;
use PhpDecide\AI\Http\HttpResponse;
use PhpDecide\AI\OpenAiChatCompletionsClient;
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
