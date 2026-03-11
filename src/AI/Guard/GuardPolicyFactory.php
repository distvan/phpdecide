<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

final class GuardPolicyFactory
{
    public static function isEnabledFromEnvironment(): bool
    {
        $value = getenv('PHPDECIDE_AI_GUARD');
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function fromEnvironment(): GuardPolicy
    {
        $baseline = GuardPolicy::baselineEnterprise();

        $failureMode = self::envEnum('PHPDECIDE_AI_GUARD_FAILURE_MODE', ['fail_open', 'fail_closed'])
            ?? $baseline->failureMode;

        $inputMaxChars = self::envPositiveInt('PHPDECIDE_AI_GUARD_INPUT_MAX_CHARS')
            ?? $baseline->inputMaxChars;

        $dlpEnabled = self::envBoolDefault('PHPDECIDE_AI_GUARD_DLP_ENABLED', $baseline->dlpEnabled);

        $inputDlpAction = self::envEnum('PHPDECIDE_AI_GUARD_INPUT_DLP_ACTION', ['block', 'monitor', 'sanitize'])
            ?? $baseline->inputDlpAction;

        $outputDlpAction = self::envEnum('PHPDECIDE_AI_GUARD_OUTPUT_DLP_ACTION', ['sanitize', 'monitor', 'block'])
            ?? $baseline->outputDlpAction;

        $auditEnabled = self::envBoolDefault('PHPDECIDE_AI_GUARD_AUDIT_ENABLED', $baseline->auditEnabled);

        $auditLogPrompt = self::envEnum('PHPDECIDE_AI_GUARD_AUDIT_LOG_PROMPT', ['none', 'hash', 'redact'])
            ?? $baseline->auditLogPrompt;

        $auditLogResponse = self::envEnum('PHPDECIDE_AI_GUARD_AUDIT_LOG_RESPONSE', ['none', 'hash', 'redact'])
            ?? $baseline->auditLogResponse;

        return new GuardPolicy(
            id: $baseline->id,
            version: $baseline->version,
            failureMode: $failureMode,
            inputMaxChars: $inputMaxChars,
            dlpEnabled: $dlpEnabled,
            inputDlpAction: $inputDlpAction,
            outputDlpAction: $outputDlpAction,
            auditLogPrompt: $auditLogPrompt,
            auditLogResponse: $auditLogResponse,
            auditEnabled: $auditEnabled,
        );
    }

    private static function envPositiveInt(string $name): ?int
    {
        $value = getenv($name);
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return max(1, (int) $value);
    }

    private static function envBoolDefault(string $name, bool $default): bool
    {
        $value = getenv($name);
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        $value = strtolower(trim($value));

        $parsed = $default;
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            $parsed = true;
        } elseif (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            $parsed = false;
        }

        return $parsed;
    }

    /**
     * @param list<string> $allowed
     */
    private static function envEnum(string $name, array $allowed): ?string
    {
        $value = getenv($name);
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return in_array($value, $allowed, true) ? $value : null;
    }
}
