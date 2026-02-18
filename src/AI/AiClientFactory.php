<?php

declare(strict_types=1);

namespace PhpDecide\AI;

use PhpDecide\AI\Http\CurlHttpClient;
use PhpDecide\AI\Http\HttpClient;

final class AiClientFactory
{
    public static function fromEnvironment(?HttpClient $httpClient = null): ?AiClient
    {
        $config = AiClientConfig::fromEnvironment();
        if ($config === null) {
            return null;
        }

        return self::fromConfig($config, $httpClient);
    }

    public static function fromConfig(AiClientConfig $config, ?HttpClient $httpClient = null): AiClient
    {
        $httpClient = $httpClient ?? new CurlHttpClient();

        return new OpenAiChatCompletionsClient(
            apiKey: $config->apiKey,
            model: $config->model,
            baseUrl: $config->baseUrl,
            chatCompletionsPath: $config->chatCompletionsPath,
            authHeaderName: $config->authHeaderName,
            authPrefix: $config->authPrefix,
            timeoutSeconds: $config->timeoutSeconds,
            organization: $config->organization,
            project: $config->project,
            systemPromptOverride: $config->systemPromptOverride,
            caInfoPath: $config->caInfoPath,
            insecureSkipVerify: $config->insecureSkipVerify,
            httpClient: $httpClient,
        );
    }
}
