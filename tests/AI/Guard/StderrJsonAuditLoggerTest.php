<?php

declare(strict_types=1);

namespace PhpDecide\Tests\AI\Guard;

use PhpDecide\AI\Guard\StderrJsonAuditLogger;
use PHPUnit\Framework\TestCase;

final class StderrJsonAuditLoggerTest extends TestCase
{
    public function testLogDoesNotThrowForValidEvent(): void
    {
        $logger = new StderrJsonAuditLogger();

        $logger->log([
            'type' => 'phpdecide.ai_guard.allow',
            'timestamp' => date(DATE_ATOM),
            'details' => ['policyId' => 'test-policy'],
        ]);

        self::addToAssertionCount(1);
    }

    public function testLogSilentlyReturnsWhenJsonEncodingFails(): void
    {
        $logger = new StderrJsonAuditLogger();

        // Invalid UTF-8 triggers JsonException with JSON_THROW_ON_ERROR.
        $logger->log([
            'type' => "bad\xB1utf8",
        ]);

        self::addToAssertionCount(1);
    }
}
