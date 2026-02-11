<?php

declare(strict_types=1);

namespace PhpDecide\AI;

final class AiClientFactory
{
    public static function fromEnvironment(): ?AiClient
    {
        $apiKey = self::envString('PHPDECIDE_AI_API_KEY');
        if ($apiKey === null) {
            return null;
        }

        $model = self::envString('PHPDECIDE_AI_MODEL') ?? 'gpt-4o-mini';

        $baseUrl = self::envString('PHPDECIDE_AI_BASE_URL') ?? 'https://api.openai.com';

        $timeoutSeconds = self::envPositiveInt('PHPDECIDE_AI_TIMEOUT') ?? 20;

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
            timeoutSeconds: $timeoutSeconds,
            organization: $org,
            project: $project,
            systemPromptOverride: $systemPrompt,
            caInfoPath: $caInfo,
            insecureSkipVerify: $insecureSkipVerify,
        );
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
