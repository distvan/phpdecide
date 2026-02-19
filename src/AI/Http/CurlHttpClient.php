<?php

declare(strict_types=1);

namespace PhpDecide\AI\Http;

use PhpDecide\AI\AiClientException;

final class CurlHttpClient implements HttpClient
{
    /**
     * @param list<string> $headers
     */
    public function post(
        string $url,
        array $headers,
        string $body,
        int $timeoutSeconds,
        ?string $caInfoPath = null,
    ): HttpResponse {
        $this->assertCurlAvailable();

        $ch = curl_init($url);
        if ($ch === false) {
            throw new AiClientException('Unable to initialize cURL.');
        }

        $connectTimeout = max(1, min(10, $timeoutSeconds));

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);

        // TLS verification is always enabled.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $caInfoPath = trim((string) $caInfoPath);
        if ($caInfoPath !== '') {
            curl_setopt($ch, CURLOPT_CAINFO, $caInfoPath);
        }

        try {
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } finally {
            unset($ch);
        }

        if ($raw === false) {
            throw new AiClientException(sprintf('AI request failed (%d): %s', $errno, $error));
        }

        return new HttpResponse(statusCode: $status, body: $raw);
    }

    private function assertCurlAvailable(): void
    {
        if (!function_exists('curl_init')) {
            throw new AiClientException('cURL extension is required for AI support.');
        }
    }
}
