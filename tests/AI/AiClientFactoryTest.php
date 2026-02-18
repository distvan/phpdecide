<?php

declare(strict_types=1);

namespace PhpDecide\Tests\AI;

use PhpDecide\AI\AiClient;
use PhpDecide\AI\AiClientFactory;
use PhpDecide\AI\OpenAiChatCompletionsClient;
use PHPUnit\Framework\TestCase;

final class AiClientFactoryTest extends TestCase
{
    private const API_KEY_ENV = 'PHPDECIDE_AI_API_KEY=dummy';

    /** @var array<string, string|false> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotEnv([
            'PHPDECIDE_AI_API_KEY',
            'PHPDECIDE_AI_MODEL',
            'PHPDECIDE_AI_BASE_URL',
            'PHPDECIDE_AI_CHAT_COMPLETIONS_PATH',
            'PHPDECIDE_AI_AUTH_HEADER_NAME',
            'PHPDECIDE_AI_AUTH_PREFIX',
            'PHPDECIDE_AI_TIMEOUT',
            'PHPDECIDE_AI_ORG',
            'PHPDECIDE_AI_PROJECT',
            'PHPDECIDE_AI_SYSTEM_PROMPT',
            'PHPDECIDE_AI_CAINFO',
            'CURL_CA_BUNDLE',
            'PHPDECIDE_AI_INSECURE',
            'PHPDECIDE_AI_OMIT_MODEL',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $name => $value) {
            if ($value !== false) {
                putenv($name . '=' . $value);
            } else {
                putenv($name);
            }
        }

        parent::tearDown();
    }

    public function testFromEnvironmentReturnsNullWhenApiKeyMissing(): void
    {
        putenv('PHPDECIDE_AI_API_KEY');

        self::assertNull(AiClientFactory::fromEnvironment());
    }

    public function testFromEnvironmentRejectsNonHttpsBaseUrlExceptLocalhost(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_BASE_URL=http://example.com');

        $this->expectException(\InvalidArgumentException::class);
        AiClientFactory::fromEnvironment();
    }

    public function testFromEnvironmentAllowsLocalhostHttpBaseUrl(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_BASE_URL=http://localhost:1234');

        $client = AiClientFactory::fromEnvironment();

        self::assertInstanceOf(AiClient::class, $client);
        self::assertInstanceOf(OpenAiChatCompletionsClient::class, $client);
    }

    public function testFromEnvironmentClampsTimeoutToAtLeastOneSecond(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_TIMEOUT=0');

        $client = AiClientFactory::fromEnvironment();
        self::assertInstanceOf(OpenAiChatCompletionsClient::class, $client);

        self::assertSame(1, $this->readPrivatePropertyInt($client, 'timeoutSeconds'));
    }

    public function testFromEnvironmentUsesCaInfoAndInsecureFlags(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_CAINFO=C:\\tmp\\cacert.pem');
        putenv('PHPDECIDE_AI_INSECURE=true');

        $client = AiClientFactory::fromEnvironment();
        self::assertInstanceOf(OpenAiChatCompletionsClient::class, $client);

        self::assertSame('C:\\tmp\\cacert.pem', $this->readPrivatePropertyString($client, 'caInfoPath'));
        self::assertTrue($this->readPrivatePropertyBool($client, 'insecureSkipVerify'));
    }

    public function testFromEnvironmentCanOmitModel(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_OMIT_MODEL=true');

        $client = AiClientFactory::fromEnvironment();
        self::assertInstanceOf(OpenAiChatCompletionsClient::class, $client);

        self::assertSame('', $this->readPrivatePropertyString($client, 'model'));
    }

    public function testFromEnvironmentNormalizesBearerPrefixWithSpace(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_AUTH_HEADER_NAME=Authorization');
        putenv('PHPDECIDE_AI_AUTH_PREFIX=Bearer');

        $client = AiClientFactory::fromEnvironment();
        self::assertInstanceOf(OpenAiChatCompletionsClient::class, $client);

        self::assertSame('Bearer ', $this->readPrivatePropertyString($client, 'authPrefix'));
    }

    /** @param list<string> $names */
    private function snapshotEnv(array $names): void
    {
        foreach ($names as $name) {
            $this->previousEnv[$name] = getenv($name);
        }
    }

    private function readPrivatePropertyInt(object $object, string $property): int
    {
        $value = (\Closure::bind(function () use ($property) {
            return $this->{$property};
        }, $object, $object))();
        self::assertIsInt($value);

        return $value;
    }

    private function readPrivatePropertyString(object $object, string $property): ?string
    {
        $value = (\Closure::bind(function () use ($property) {
            return $this->{$property};
        }, $object, $object))();
        if ($value === null) {
            return null;
        }

        self::assertIsString($value);
        return $value;
    }

    private function readPrivatePropertyBool(object $object, string $property): bool
    {
        $value = (\Closure::bind(function () use ($property) {
            return $this->{$property};
        }, $object, $object))();
        self::assertIsBool($value);

        return $value;
    }
}
