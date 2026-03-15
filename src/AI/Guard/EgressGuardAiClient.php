<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

use PhpDecide\AI\AiClient;
use PhpDecide\AI\AiClientException;
use PhpDecide\AI\DecisionPayloadNormalizer;
use PhpDecide\AI\ExplainPromptBuilder;
use PhpDecide\Decision\Decision;

/**
 * CLI-focused security guard that prevents accidental sensitive-data egress
 * and applies basic safety/operational constraints.
 */
final class EgressGuardAiClient implements AiClient
{
    public function __construct(
        private readonly AiClient $inner,
        private readonly GuardPolicy $policy,
        private readonly DlpScanner $dlpScanner = new DlpScanner(),
        private readonly AuditLogger $auditLogger = new NullAuditLogger(),
        private readonly string $routeId = 'cli:explain',
        private readonly ?string $systemPromptOverride = null,
    ) {
    }

    /**
     * @param Decision[] $decisions
     */
    public function explainDecision(string $question, array $decisions): string
    {
        $correlationId = self::newCorrelationId();
        $skipOutputGuard = false;

        try {
            $decisionJson = $this->buildDecisionPayloadJson($decisions, $correlationId);
            $inputChars = $this->inputChars($question, $decisionJson);

            $this->enforceInputSizeLimit($correlationId, $inputChars);

            if ($this->policy->dlpEnabled) {
                $this->enforceNoSensitiveDataInDecisions($correlationId, $decisionJson);
                $question = $this->applyQuestionDlpPolicy($correlationId, $question);
            }

            $this->audit('allow', $correlationId, [
                'policy' => ['id' => $this->policy->id, 'version' => $this->policy->version],
                'routeId' => $this->routeId,
                'inputChars' => $inputChars,
                'dlpEnabled' => $this->policy->dlpEnabled,
            ]);
        } catch (AiClientException $e) {
            // Expected failures should already include correlationId.
            throw $e;
        } catch (\Throwable $e) {
            $this->handleGuardInternalFailure($correlationId, $e);

            // fail_open: call provider without guard interference.
            $skipOutputGuard = true;
        }

        // Inner client failures are not guard failures. Bubble them as-is.
        $out = $this->inner->explainDecision($question, $decisions);

        if ($this->policy->dlpEnabled && !$skipOutputGuard) {
            try {
                $out = $this->applyOutputDlpPolicy($correlationId, $out);
            } catch (AiClientException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->handleGuardInternalFailure($correlationId, $e);

                // fail_open: keep provider response without post-processing.
            }
        }

        return $out;
    }

    private function handleGuardInternalFailure(string $correlationId, \Throwable $e): void
    {
        // Guard internal failure (regex/encoding/audit/etc). Apply failure mode.
        try {
            $this->audit('guard_error', $correlationId, [
                'errorClass' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            // Guard-error auditing must never mask the original failure handling path.
        }

        if ($this->policy->failureMode === 'fail_closed') {
            throw new AiClientException(
                'AI request blocked by egress guard (internal error). ' .
                'CorrelationId: ' . $correlationId
            );
        }
    }

    /**
     * @param Decision[] $decisions
     */
    private function buildDecisionPayloadJson(array $decisions, string $correlationId): string
    {
        $compact = array_map(
            static fn(Decision $d): array => DecisionPayloadNormalizer::decisionToCompactPayload($d),
            $decisions
        );

        $decisionJson = json_encode($compact, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($decisionJson === false) {
            throw new AiClientException('AI request blocked by egress guard (unable to encode decisions). CorrelationId: ' . $correlationId);
        }

        return $decisionJson;
    }

    private function inputChars(string $question, string $decisionJson): int
    {
        return ExplainPromptBuilder::inputCharsFromDecisionPayloadJson(
            $question,
            $decisionJson,
            $this->systemPromptOverride,
        );
    }

    private function enforceInputSizeLimit(string $correlationId, int $inputChars): void
    {
        if ($inputChars <= $this->policy->inputMaxChars) {
            return;
        }

        $this->audit('blocked', $correlationId, [
            'reason' => 'size_limit',
            'inputChars' => $inputChars,
            'maxChars' => $this->policy->inputMaxChars,
        ]);

        throw new AiClientException(
            'AI request blocked by egress guard (input too large). CorrelationId: ' . $correlationId
        );
    }

    private function enforceNoSensitiveDataInDecisions(string $correlationId, string $decisionJson): void
    {
        $findings = $this->dlpScanner->scan($decisionJson);
        if ($findings === []) {
            return;
        }

        // Important limitation: the provider client builds its own payload from Decision objects.
        // We cannot reliably sanitize/redact decision content without changing the client API.
        // Therefore: any DLP hit in decisions is treated as a hard block.
        $this->audit('blocked', $correlationId, [
            'reason' => 'dlp_decisions',
            'findings' => $this->findingsToArray($findings),
        ]);

        throw new AiClientException(
            'AI request blocked by egress guard (sensitive data detected in recorded decisions payload). ' .
            'CorrelationId: ' . $correlationId
        );
    }

    private function applyQuestionDlpPolicy(string $correlationId, string $question): string
    {
        $findings = $this->dlpScanner->scan($question);
        if ($findings === []) {
            return $question;
        }

        $this->audit('input_findings', $correlationId, [
            'findings' => $this->findingsToArray($findings),
            'action' => $this->policy->inputDlpAction,
        ]);

        if ($this->policy->inputDlpAction === 'block') {
            throw new AiClientException(
                'AI request blocked by egress guard (sensitive data detected in question). CorrelationId: ' . $correlationId
            );
        }

        if ($this->policy->inputDlpAction === 'sanitize') {
            return $this->dlpScanner->redact($question);
        }

        return $question;
    }

    private function applyOutputDlpPolicy(string $correlationId, string $output): string
    {
        $findings = $this->dlpScanner->scan($output);
        if ($findings === []) {
            return $output;
        }

        $this->audit('output_findings', $correlationId, [
            'findings' => $this->findingsToArray($findings),
            'action' => $this->policy->outputDlpAction,
        ]);

        if ($this->policy->outputDlpAction === 'block') {
            throw new AiClientException(
                'AI response blocked by egress guard (sensitive data detected in output). CorrelationId: ' . $correlationId
            );
        }

        if ($this->policy->outputDlpAction === 'sanitize') {
            return $this->dlpScanner->redact($output);
        }

        return $output;
    }

    /**
     * @param list<Finding> $findings
     * @return list<array<string, mixed>>
     */
    private function findingsToArray(array $findings): array
    {
        return array_map(static fn(Finding $f): array => $f->toArray(), $findings);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function audit(string $eventType, string $correlationId, array $details): void
    {
        if (!$this->policy->auditEnabled) {
            return;
        }

        $event = [
            'type' => 'phpdecide.ai_guard.' . $eventType,
            'correlationId' => $correlationId,
            'routeId' => $this->routeId,
            'policy' => [
                'id' => $this->policy->id,
                'version' => $this->policy->version,
                'failureMode' => $this->policy->failureMode,
            ],
            'details' => $details,
        ];

        // Ensure prompt/response are never logged verbatim by this layer.
        unset($event['details']['question']);
        unset($event['details']['decisionsPayload']);
        unset($event['details']['response']);

        $this->auditLogger->log($event);
    }

    private static function newCorrelationId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            // Fall through.
        }

        // Prefer another CSPRNG if available.
        try {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $strong = false;
                $bytes = openssl_random_pseudo_bytes(16, $strong);
                if (is_string($bytes) && $bytes !== '' && $strong === true) {
                    return bin2hex($bytes);
                }
            }
        } catch (\Throwable) {
            // Fall through.
        }

        // Deterministic fallback (non-random): still provides a useful correlation id without
        // relying on pseudo-random generators.
        static $counter = 0;
        $counter++;

        $pid = function_exists('getmypid') ? (int) getmypid() : 0;
        $micros = (int) (microtime(true) * 1_000_000);

        return sprintf('corr-%d-%d-%d', $pid, $micros, $counter);
    }
}
