<?php

declare(strict_types=1);

namespace PhpDecide\Tests\AI;

use PhpDecide\AI\AiClientConfig;
use PHPUnit\Framework\TestCase;

final class AiClientConfigTest extends TestCase
{
    private const API_KEY_ENV = 'PHPDECIDE_AI_API_KEY=dummy';

    /** @var array<string, string|false> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $names = [
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
        ];

        $this->snapshotEnv($names);

        // Ensure tests run in a clean, deterministic env even if the developer
        // has sourced env.bash.sh or otherwise configured these variables.
        foreach ($names as $name) {
            putenv($name);
        }
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

        self::assertNull(AiClientConfig::fromEnvironment());
    }

    public function testFromEnvironmentRejectsNonHttpsBaseUrlExceptLocalhost(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_BASE_URL=http://example.com');

        $this->expectException(\InvalidArgumentException::class);
        AiClientConfig::fromEnvironment();
    }

    public function testFromEnvironmentAllowsLocalhostHttpBaseUrl(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_BASE_URL=http://localhost:1234');

        $config = AiClientConfig::fromEnvironment();

        self::assertNotNull($config);
        self::assertSame('http://localhost:1234', $config->baseUrl);
    }

    public function testFromEnvironmentTrimsApiKeyAndRejectsWhitespaceOnly(): void
    {
        putenv('PHPDECIDE_AI_API_KEY=   ');

        self::assertNull(AiClientConfig::fromEnvironment());
    }

    public function testFromEnvironmentCanOmitModel(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_OMIT_MODEL=true');

        $config = AiClientConfig::fromEnvironment();

        self::assertNotNull($config);
        self::assertSame('', $config->model);
    }

    public function testFromEnvironmentNormalizesBearerPrefixWithSpace(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_AUTH_PREFIX=Bearer');

        $config = AiClientConfig::fromEnvironment();

        self::assertNotNull($config);
        self::assertSame('Bearer ', $config->authPrefix);
    }

    public function testFromEnvironmentAllowsEmptyAuthPrefix(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_AUTH_PREFIX=');

        $config = AiClientConfig::fromEnvironment();

        self::assertNotNull($config);
        self::assertSame('', $config->authPrefix);
    }

    public function testFromEnvironmentClampsTimeoutToAtLeastOneSecond(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_TIMEOUT=0');

        $config = AiClientConfig::fromEnvironment();

        self::assertNotNull($config);
        self::assertSame(1, $config->timeoutSeconds);
    }

    public function testFromEnvironmentUsesCaInfoAndFallsBackToCurlCaBundle(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('CURL_CA_BUNDLE=C:\\tmp\\cacert.pem');

        $config = AiClientConfig::fromEnvironment();

        self::assertNotNull($config);
        self::assertSame('C:\\tmp\\cacert.pem', $config->caInfoPath);

        putenv('PHPDECIDE_AI_CAINFO=C:\\custom\\ca.pem');

        $config2 = AiClientConfig::fromEnvironment();

        self::assertNotNull($config2);
        self::assertSame('C:\\custom\\ca.pem', $config2->caInfoPath);
    }

    public function testFromEnvironmentParsesInsecureFlag(): void
    {
        putenv(self::API_KEY_ENV);
        putenv('PHPDECIDE_AI_INSECURE=true');

        $config = AiClientConfig::fromEnvironment();

        self::assertNotNull($config);
        self::assertTrue($config->insecureSkipVerify);
    }

    /** @param list<string> $names */
    private function snapshotEnv(array $names): void
    {
        foreach ($names as $name) {
            $this->previousEnv[$name] = getenv($name);
        }
    }
}
