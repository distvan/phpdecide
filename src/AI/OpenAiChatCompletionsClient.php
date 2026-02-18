<?php

declare(strict_types=1);

namespace PhpDecide\AI;

use PhpDecide\AI\Http\CurlHttpClient;
use PhpDecide\AI\Http\HttpClient;
use PhpDecide\Decision\Decision;
use InvalidArgumentException;

final class OpenAiChatCompletionsClient implements AiClient
{
    private readonly HttpClient $httpClient;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.openai.com',
        private readonly string $chatCompletionsPath = '/v1/chat/completions',
        private readonly string $authHeaderName = 'Authorization',
        private readonly string $authPrefix = 'Bearer ',
        private readonly int $timeoutSeconds = 20,
        private readonly ?string $organization = null,
        private readonly ?string $project = null,
        private readonly ?string $systemPromptOverride = null,
        private readonly ?string $caInfoPath = null,
        private readonly bool $insecureSkipVerify = false,
        ?HttpClient $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new CurlHttpClient();

        if (trim($this->apiKey) === '') {
            throw new InvalidArgumentException('OpenAI API key must be a non-empty string.');
        }

        self::assertHeaderValueSafe($this->apiKey, 'OpenAI API key');

        // Some OpenAI-compatible gateways encode the model in the URL path.
        // In that case, the request must omit the "model" field.
        if (trim($this->model) === '') {
            // Allowed: treat as "model omitted".
        }

        if (trim($this->authHeaderName) === '') {
            throw new InvalidArgumentException('Auth header name must be a non-empty string.');
        }

        $headerName = trim($this->authHeaderName);
        if (!preg_match('/^[A-Za-z0-9-]+$/', $headerName)) {
            throw new InvalidArgumentException('Auth header name contains invalid characters.');
        }

        // Header value safety
        self::assertHeaderValueSafe($this->authPrefix, 'Auth header prefix');

        if (trim($this->chatCompletionsPath) === '') {
            throw new InvalidArgumentException('Chat completions path must be a non-empty string.');
        }

        $path = trim($this->chatCompletionsPath);
        if (!str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Chat completions path must start with a slash (e.g. /v1/chat/completions).');
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
            'temperature' => 0.2,
            // Some gateways default to streaming; we only support non-streaming JSON responses.
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
        ];

        $model = trim($this->model);
        if ($model !== '') {
            $payload['model'] = $model;
        }

        $response = $this->postJson($this->baseUrl . trim($this->chatCompletionsPath), $payload);

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
        $json = self::encodePayload($payload);
        $headers = $this->buildHeaders();

        if ($this->insecureSkipVerify) {
            throw new AiClientException(
                'Insecure TLS verification (PHPDECIDE_AI_INSECURE) is not supported. ' .
                'Configure PHPDECIDE_AI_CAINFO / CURL_CA_BUNDLE or fix your system trust store instead.'
            );
        }

        $response = $this->httpClient->post(
            url: $url,
            headers: $headers,
            body: $json,
            timeoutSeconds: $this->timeoutSeconds,
            caInfoPath: $this->caInfoPath,
        );

        $raw = $response->body;
        $status = $response->statusCode;

        if ($status < 200 || $status >= 300) {
            $decoded = self::tryDecodeJson($raw);
            if (is_array($decoded)) {
                $this->throwForHttpStatus($status, $decoded, $url);
            }

            $snippet = self::formatResponseSnippet($raw);
            $authDiag = $this->formatAuthDiagnostics();
            throw new AiClientException(sprintf(
                'AI request failed (HTTP %d). Response was not valid JSON for %s. %s Body starts with: %s',
                $status,
                $url,
                $authDiag,
                $snippet
            ));
        }

        $decoded = self::tryDecodeJson($raw);
        if (!is_array($decoded)) {
            $snippet = self::formatResponseSnippet($raw);
            $authDiag = $this->formatAuthDiagnostics();
            throw new AiClientException(sprintf(
                'AI response was not valid JSON (HTTP %d) from %s. %s Body starts with: %s',
                $status,
                $url,
                $authDiag,
                $snippet
            ));
        }

        return $decoded;
    }

    private function formatAuthDiagnostics(): string
    {
        $name = trim($this->authHeaderName);
        $prefix = $this->authPrefix;
        $prefixSummary = $prefix === '' ? '[empty]' : $prefix;

        return sprintf('Auth header sent: %s (prefix: %s).', $name, $prefixSummary);
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
            'Accept: application/json',
        ];

        $authHeaderName = trim($this->authHeaderName);
        $authValue = $this->authPrefix . $this->apiKey;
        self::assertHeaderValueSafe($authValue, $authHeaderName);
        $headers[] = $authHeaderName . ': ' . $authValue;

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
     * @return array<string, mixed>|null
     */
    private static function tryDecodeJson(string $raw): ?array
    {
        $decoded = json_decode(self::stripUtf8Bom($raw), true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function stripUtf8Bom(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }

        return $raw;
    }

    private static function formatResponseSnippet(string $raw): string
    {
        $s = trim(self::stripUtf8Bom($raw));
        if ($s === '') {
            return '[empty body]';
        }

        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $max = 300;
        if (mb_strlen($s) > $max) {
            $s = mb_substr($s, 0, $max) . '...';
        }

        return $s;
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
