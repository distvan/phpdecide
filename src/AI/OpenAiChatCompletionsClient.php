<?php

declare(strict_types=1);

namespace PhpDecide\AI;

use PhpDecide\Decision\Decision;
use InvalidArgumentException;

final class OpenAiChatCompletionsClient implements AiClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.openai.com',
        private readonly int $timeoutSeconds = 20,
        private readonly ?string $organization = null,
        private readonly ?string $project = null,
        private readonly ?string $systemPromptOverride = null,
        private readonly ?string $caInfoPath = null,
        private readonly bool $insecureSkipVerify = false,
    ) {
        if (trim($this->apiKey) === '') {
            throw new InvalidArgumentException('OpenAI API key must be a non-empty string.');
        }

        self::assertHeaderValueSafe($this->apiKey, 'OpenAI API key');

        if (trim($this->model) === '') {
            throw new InvalidArgumentException('OpenAI model must be a non-empty string.');
        }

        if ($this->organization !== null) {
            self::assertHeaderValueSafe($this->organization, 'OpenAI organization');
        }
        if ($this->project !== null) {
            self::assertHeaderValueSafe($this->project, 'OpenAI project');
        }
    }

    /**
     * @param string $question
     * @param Decision[] $decisions
     */
    public function explainDecision(string $question, array $decisions): string
    {
        $systemPrompt = $this->systemPromptOverride ?? $this->defaultSystemPrompt();

        $decisionPayload = array_map(
            static fn(Decision $d): array => [
                'id' => $d->id()->value(),
                'title' => $d->title(),
                'status' => $d->status()->value,
                'date' => $d->date()->format('Y-m-d'),
                'scope' => [
                    'type' => $d->scope()->type()->value,
                    'paths' => $d->scope()->paths(),
                ],
                'summary' => $d->content()->summary(),
                'rationale' => $d->content()->rationale(),
                'alternatives' => $d->content()->alternatives(),
                'examples' => [
                    'allowed' => $d->examples()->allowed(),
                    'forbidden' => $d->examples()->forbidden(),
                ],
                'rules' => $d->rules() ? [
                    'forbid' => $d->rules()->forbid(),
                    'allow' => $d->rules()->allow(),
                ] : null,
                'references' => $d->references() ? [
                    'issues' => $d->references()->issues(),
                    'commits' => $d->references()->commits(),
                    'adr' => $d->references()->adr(),
                ] : null,
                'ai' => $d->aiMetadata() ? [
                    'explain_style' => $d->aiMetadata()->explainStyle(),
                    'keywords' => $d->aiMetadata()->keywords(),
                ] : null,
            ],
            $decisions
        );

        $userContent = "Question:\n{$question}\n\nRecorded decisions (authoritative source of truth):\n";
        $userContent .= json_encode($decisionPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]';
        $userContent .= "\n\nTask: Summarize ONLY what is recorded above. If something is missing, say it's not recorded.";

        $payload = [
            'model' => $this->model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ];

        $response = $this->postJson($this->baseUrl . '/v1/chat/completions', $payload);

        $content = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new AiClientException('AI response was missing message content.');
        }

        return trim($content);
    }

    private function defaultSystemPrompt(): string
    {
        return <<<PROMPT
You are a helpful assistant for explaining software architecture decisions.

Critical constraints:
- Decisions and rules are defined ONLY by the provided recorded decision data.
- Do NOT invent new rules, scopes, exceptions, or facts.
- If the information needed to answer is not present, explicitly say it is not recorded.

Output style:
- Be concise.
- Reference decisions by ID like [DEC-0001].
- Prefer short bullets and plain language.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $payload): array
    {
        self::assertCurlAvailable();

        $json = self::encodePayload($payload);
        $headers = $this->buildHeaders();
        $ch = $this->initCurl($url, $headers, $json);

        try {
            $this->applyTlsOptions($ch);
            [$raw, $status, $errno, $error] = $this->execCurl($ch);
        } finally {
            unset($ch);
        }

        if ($raw === false) {
            throw new AiClientException(sprintf('AI request failed (%d): %s', $errno, $error));
        }

        $decoded = self::decodeJson($raw);
        $this->throwForHttpStatus($status, $decoded, $url);

        return $decoded;
    }

    private static function assertCurlAvailable(): void
    {
        if (!function_exists('curl_init')) {
            throw new AiClientException('cURL extension is required for AI support.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function encodePayload(array $payload): string
    {
        $json = json_encode($payload);
        if ($json === false) {
            throw new AiClientException('Unable to encode AI request JSON.');
        }

        return $json;
    }

    /**
     * @return list<string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if ($this->organization !== null && trim($this->organization) !== '') {
            $org = trim($this->organization);
            self::assertHeaderValueSafe($org, 'OpenAI-Organization');
            $headers[] = 'OpenAI-Organization: ' . $org;
        }
        if ($this->project !== null && trim($this->project) !== '') {
            $project = trim($this->project);
            self::assertHeaderValueSafe($project, 'OpenAI-Project');
            $headers[] = 'OpenAI-Project: ' . $project;
        }

        return $headers;
    }

    private static function assertHeaderValueSafe(string $value, string $label): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new AiClientException($label . ' contains newline characters, which is not allowed.');
        }
    }

    /**
     * @param list<string> $headers
     * @return \CurlHandle
     */
    private function initCurl(string $url, array $headers, string $json): \CurlHandle
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new AiClientException('Unable to initialize cURL.');
        }

        $connectTimeout = max(1, min(10, $this->timeoutSeconds));

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        return $ch;
    }

    private function applyTlsOptions(\CurlHandle $ch): void
    {
        if ($this->caInfoPath !== null && trim($this->caInfoPath) !== '') {
            curl_setopt($ch, CURLOPT_CAINFO, $this->caInfoPath);
        }

        if ($this->insecureSkipVerify) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
    }

    /**
     * @return array{0: string|false, 1: int, 2: int, 3: string}
     */
    private function execCurl(\CurlHandle $ch): array
    {
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [$raw, $status, $errno, $error];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new AiClientException('AI response was not valid JSON.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function throwForHttpStatus(int $status, array $decoded, string $url): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        $message = $decoded['error']['message'] ?? null;

        // OpenRouter commonly uses HTTP 402 for "no credits" or "provider error".
        if ($status === 402 && str_contains($url, 'openrouter.ai')) {
            $hint = 'OpenRouter returned HTTP 402. This usually means the selected model is not available under your current plan/credits, or free quota is exhausted. Try a different ":free" model or add a small credit balance in OpenRouter.';

            if (is_string($message) && $message !== '') {
                throw new AiClientException(sprintf('AI request failed (HTTP %d): %s (%s)', $status, $message, $hint));
            }

            throw new AiClientException(sprintf('AI request failed (HTTP %d): %s', $status, $hint));
        }

        if (is_string($message) && $message !== '') {
            throw new AiClientException(sprintf('AI request failed (HTTP %d): %s', $status, $message));
        }

        throw new AiClientException(sprintf('AI request failed (HTTP %d).', $status));
    }
}
