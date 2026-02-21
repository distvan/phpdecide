<?php

declare(strict_types=1);

namespace PhpDecide\AI\Http;

interface HttpClient
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
    ): HttpResponse;
}
