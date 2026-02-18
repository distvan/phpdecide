<?php

declare(strict_types=1);

namespace PhpDecide\AI {
    /**
     * Simple controllable stub for cURL functions.
     *
     * We define functions in the PhpDecide\AI namespace so calls in
     * OpenAiChatCompletionsClient resolve here first.
     */
    final class CurlStub
    {
        public static string|false $nextRaw = '';
        public static int $nextStatus = 200;
        public static int $nextErrno = 0;
        public static string $nextError = '';

        public static ?string $lastUrl = null;

        /** @var array<int, mixed> */
        public static array $setopts = [];

        /** @var array<int, mixed> */
        public static array $setoptArray = [];

        public static function reset(): void
        {
            self::$nextRaw = '';
            self::$nextStatus = 200;
            self::$nextErrno = 0;
            self::$nextError = '';
            self::$lastUrl = null;
            self::$setopts = [];
            self::$setoptArray = [];
        }
    }

    function curl_init(string $url): \CurlHandle|false  //NOSONAR
    {
        CurlStub::$lastUrl = $url;
        return \curl_init($url);
    }

    /** @param array<int, mixed> $options */
    function curl_setopt_array(\CurlHandle $ch, array $options): bool // NOSONAR
    {
        CurlStub::$setoptArray = $options;
        return true;
    }

    function curl_setopt(\CurlHandle $ch, int $option, mixed $value): bool // NOSONAR
    {
        CurlStub::$setopts[$option] = $value;
        return true;
    }

    function curl_exec(\CurlHandle $ch): string|false // NOSONAR
    {
        return CurlStub::$nextRaw;
    }

    function curl_errno(\CurlHandle $ch): int // NOSONAR
    {
        return CurlStub::$nextErrno;
    }

    function curl_error(\CurlHandle $ch): string // NOSONAR
    {
        return CurlStub::$nextError;
    }

    function curl_getinfo(\CurlHandle $ch, int $option = 0): mixed // NOSONAR
    {
        if ($option === CURLINFO_HTTP_CODE) {
            return CurlStub::$nextStatus;
        }

        return null;
    }
}

namespace PhpDecide\Tests\AI {

    use PhpDecide\AI\AiClientException;
    use PhpDecide\AI\CurlStub;
    use PhpDecide\AI\OpenAiChatCompletionsClient;
    use PHPUnit\Framework\TestCase;

    final class OpenAiChatCompletionsClientTest extends TestCase
    {
        private const TEST_BASE_URL = 'https://example.test';

        protected function setUp(): void
        {
            parent::setUp();
            CurlStub::reset();
        }

        public function testExplainDecisionReturnsTrimmedContent(): void
        {
            CurlStub::$nextRaw = json_encode([
                'choices' => [
                    ['message' => ['content' => "  Hello world\n"]],
                ],
            ], JSON_THROW_ON_ERROR);
            CurlStub::$nextStatus = 200;

            $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm');

            $out = $client->explainDecision('Q?', []);

            self::assertSame('Hello world', $out);
        }

        public function testExplainDecisionThrowsOnInvalidJson(): void
        {
            CurlStub::$nextRaw = 'not-json';
            CurlStub::$nextStatus = 200;

            $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm');

            $this->expectException(AiClientException::class);
            $this->expectExceptionMessage('AI response was not valid JSON');
            $client->explainDecision('Q?', []);
        }

        public function testExplainDecisionThrowsOnHttpErrorWithMessage(): void
        {
            CurlStub::$nextRaw = json_encode([
                'error' => ['message' => 'boom'],
            ], JSON_THROW_ON_ERROR);
            CurlStub::$nextStatus = 500;

            $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm');

            $this->expectException(AiClientException::class);
            $this->expectExceptionMessage('HTTP 500');
            $this->expectExceptionMessage('boom');
            $client->explainDecision('Q?', []);
        }

        public function testExplainDecisionAddsOpenRouterHintOn402(): void
        {
            CurlStub::$nextRaw = json_encode([
                'error' => ['message' => 'Provider returned error'],
            ], JSON_THROW_ON_ERROR);
            CurlStub::$nextStatus = 402;

            $client = new OpenAiChatCompletionsClient(
                apiKey: 'k',
                model: 'm',
                baseUrl: 'https://openrouter.ai/api'
            );

            $this->expectException(AiClientException::class);
            $this->expectExceptionMessage('OpenRouter returned HTTP 402');
            $client->explainDecision('Q?', []);
        }

        public function testInsecureSkipVerifyIsRejected(): void
        {
            CurlStub::$nextRaw = json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR);
            CurlStub::$nextStatus = 200;

            $client = new OpenAiChatCompletionsClient(apiKey: 'k', model: 'm', insecureSkipVerify: true);

            $this->expectException(AiClientException::class);
            $this->expectExceptionMessage('Insecure TLS verification');
            $client->explainDecision('Q?', []);
        }

        public function testHeaderValueNewlinesAreRejected(): void
        {
            $this->expectException(AiClientException::class);
            $this->expectExceptionMessage('contains newline characters');

            // The constructor is expected to throw before explainDecision() runs.
            (new OpenAiChatCompletionsClient(apiKey: "k\n", model: 'm'))->explainDecision('Q?', []);
        }

        public function testChatCompletionsPathCanBeCustomized(): void
        {
            CurlStub::$nextRaw = json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR);
            CurlStub::$nextStatus = 200;

            $client = new OpenAiChatCompletionsClient(
                apiKey: 'k',
                model: 'm',
                baseUrl: self::TEST_BASE_URL,
                chatCompletionsPath: '/openai/v1/chat/completions'
            );

            $client->explainDecision('Q?', []);

            self::assertSame('https://example.test/openai/v1/chat/completions', CurlStub::$lastUrl);
        }

        public function testModelIsOmittedWhenEmpty(): void
        {
            CurlStub::$nextRaw = json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR);
            CurlStub::$nextStatus = 200;

            $client = new OpenAiChatCompletionsClient(
                apiKey: 'k',
                model: '',
                baseUrl: self::TEST_BASE_URL
            );

            $client->explainDecision('Q?', []);

            $postFields = CurlStub::$setoptArray[CURLOPT_POSTFIELDS] ?? null;
            self::assertIsString($postFields);

            $decoded = json_decode($postFields, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            self::assertArrayNotHasKey('model', $decoded);
        }

        public function testCustomAuthHeaderNameAndPrefixAreApplied(): void
        {
            CurlStub::$nextRaw = json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], JSON_THROW_ON_ERROR);
            CurlStub::$nextStatus = 200;

            $client = new OpenAiChatCompletionsClient(
                apiKey: 'secret',
                model: 'm',
                baseUrl: self::TEST_BASE_URL,
                authHeaderName: 'Api-Key',
                authPrefix: ''
            );

            $client->explainDecision('Q?', []);

            $headers = CurlStub::$setoptArray[CURLOPT_HTTPHEADER] ?? null;
            self::assertIsArray($headers);
            self::assertContains('Api-Key: secret', $headers);
        }

        public function testNonJsonHttpErrorIncludesAuthDiagnostics(): void
        {
            CurlStub::$nextRaw = 'Bad Authorization header';
            CurlStub::$nextStatus = 401;

            $client = new OpenAiChatCompletionsClient(
                apiKey: 'secret',
                model: 'm',
                baseUrl: self::TEST_BASE_URL,
                authHeaderName: 'Api-Key',
                authPrefix: ''
            );

            $this->expectException(AiClientException::class);
            $this->expectExceptionMessage('HTTP 401');
            $this->expectExceptionMessage('Auth header sent: Api-Key');
            $this->expectExceptionMessage('prefix: [empty]');
            $this->expectExceptionMessage('Bad Authorization header');

            $client->explainDecision('Q?', []);
        }
    }
}
