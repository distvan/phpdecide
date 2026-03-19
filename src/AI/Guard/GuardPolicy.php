<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

/**
 * Minimal policy for CLI-focused LLM egress guarding.
 *
 * v0.1 intentionally keeps policy surface small and deterministic.
 */
final class GuardPolicy
{
    /**
     * @param 'fail_open'|'fail_closed' $failureMode
     * @param 'block'|'monitor'|'sanitize' $inputDlpAction
     * @param 'sanitize'|'monitor'|'block' $outputDlpAction
     * @param 'none'|'hash'|'redact' $auditLogPrompt
     * @param 'none'|'hash'|'redact' $auditLogResponse
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $failureMode,
        public readonly int $inputMaxChars,
        public readonly bool $dlpEnabled,
        public readonly string $inputDlpAction,
        public readonly string $outputDlpAction,
        public readonly string $auditLogPrompt,
        public readonly string $auditLogResponse,
        public readonly bool $auditEnabled,
    ) {
    }

    public static function baselineEnterprise(): self
    {
        return new self(
            id: 'baseline-enterprise',
            version: '2026-03-11',
            failureMode: 'fail_closed',
            inputMaxChars: 8000,
            dlpEnabled: true,
            inputDlpAction: 'block',
            outputDlpAction: 'sanitize',
            auditLogPrompt: 'redact',
            auditLogResponse: 'redact',
            auditEnabled: true,
        );
    }
}
