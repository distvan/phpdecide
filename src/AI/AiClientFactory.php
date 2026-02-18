<?php

declare(strict_types=1);

namespace PhpDecide\AI;

use InvalidArgumentException;

final class AiClientFactory
{
    public static function fromEnvironment(): ?AiClient
    {
        $apiKey = self::envString('PHPDECIDE_AI_API_KEY');
        if ($apiKey === null) {
            return null;
        }

        $model = self::envString('PHPDECIDE_AI_MODEL') ?? 'gpt-4o-mini';

        if (self::envBool('PHPDECIDE_AI_OMIT_MODEL')) {
            $model = '';
        }

        $baseUrl = self::envString('PHPDECIDE_AI_BASE_URL') ?? 'https://api.openai.com';
        self::assertSafeBaseUrl($baseUrl);

        $chatCompletionsPath = self::envString('PHPDECIDE_AI_CHAT_COMPLETIONS_PATH')
            ?? '/v1/chat/completions';

        $timeoutSeconds = self::envPositiveInt('PHPDECIDE_AI_TIMEOUT') ?? 20;

        $authHeaderName = self::envString('PHPDECIDE_AI_AUTH_HEADER_NAME') ?? 'Authorization';
        $authPrefix = self::envStringAllowEmpty('PHPDECIDE_AI_AUTH_PREFIX');
        if ($authPrefix === null) {
            $authPrefix = 'Bearer ';
        } elseif ($authPrefix !== '' && !str_ends_with($authPrefix, ' ')) {
            $authPrefix .= ' ';
        }

        $org = self::envString('PHPDECIDE_AI_ORG');
        $project = self::envString('PHPDECIDE_AI_PROJECT');
        $systemPrompt = self::envString('PHPDECIDE_AI_SYSTEM_PROMPT');

        $caInfo = self::envString('PHPDECIDE_AI_CAINFO')
            ?? self::envString('CURL_CA_BUNDLE');

        $insecureSkipVerify = self::envBool('PHPDECIDE_AI_INSECURE');

        return new OpenAiChatCompletionsClient(
            apiKey: $apiKey,
            model: $model,
            baseUrl: rtrim($baseUrl, '/'),
            chatCompletionsPath: $chatCompletionsPath,
            authHeaderName: $authHeaderName,
            authPrefix: $authPrefix,
            timeoutSeconds: $timeoutSeconds,
            organization: $org,
            project: $project,
            systemPromptOverride: $systemPrompt,
            caInfoPath: $caInfo,
            insecureSkipVerify: $insecureSkipVerify,
        );
    }

    private static function assertSafeBaseUrl(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        if (!is_array($parts)) {
            throw new InvalidArgumentException('AI base URL is not a valid URL.');
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (!is_string($scheme) || $scheme === '') {
            throw new InvalidArgumentException('AI base URL must include a URL scheme (https://...).');
        }

        $scheme = strtolower($scheme);
        if ($scheme === 'https') {
            return;
        }

        if ($scheme === 'http') {
            if (!is_string($host) || $host === '') {
                throw new InvalidArgumentException('AI base URL must include a host.');
            }

            $host = strtolower($host);
            $isLoopback = $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
            if ($isLoopback) {
                return;
            }

            throw new InvalidArgumentException('AI base URL must use https:// (http:// is only allowed for localhost).');
        }

        throw new InvalidArgumentException('AI base URL must use https:// (or http://localhost for local testing).');
    }

    private static function envString(string $name): ?string
    {
        $value = getenv($name);
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private static function envStringAllowEmpty(string $name): ?string
    {
        $value = getenv($name);
        if (!is_string($value)) {
            return null;
        }

        return $value;
    }

    private static function envPositiveInt(string $name): ?int
    {
        $value = self::envString($name);
        if ($value === null) {
            return null;
        }

        if (!ctype_digit($value)) {
            return null;
        }

        return max(1, (int) $value);
    }

    private static function envBool(string $name): bool
    {
        $value = self::envString($name);
        if ($value === null) {
            return false;
        }

        return $value === '1' || mb_strtolower($value) === 'true';
    }
}
