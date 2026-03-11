<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

final class DlpScanner
{
    /**
     * @return list<array{id: string, category: string, severity: 'low'|'medium'|'high'|'critical', pattern: string, message: string}>
     */
    private function rules(): array
    {
        return [
            [
                'id' => 'dlp.openai_api_key',
                'category' => 'dlp.secret',
                'severity' => 'critical',
                'pattern' => '/\bsk-[A-Za-z0-9]{20,}\b/',
                'message' => 'Possible OpenAI API key detected.',
            ],
            [
                'id' => 'dlp.github_token',
                'category' => 'dlp.secret',
                'severity' => 'critical',
                'pattern' => '/\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{20,}\b/',
                'message' => 'Possible GitHub token detected.',
            ],
            [
                'id' => 'dlp.github_pat',
                'category' => 'dlp.secret',
                'severity' => 'critical',
                'pattern' => '/\bgithub_pat_[A-Za-z0-9_]{20,}\b/',
                'message' => 'Possible GitHub fine-grained PAT detected.',
            ],
            [
                'id' => 'dlp.private_key_block',
                'category' => 'dlp.secret',
                'severity' => 'critical',
                'pattern' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i',
                'message' => 'Private key material detected.',
            ],
            [
                'id' => 'dlp.aws_access_key_id',
                'category' => 'dlp.secret',
                'severity' => 'high',
                'pattern' => '/\bAKIA[0-9A-Z]{16}\b/',
                'message' => 'Possible AWS access key id detected.',
            ],
            [
                'id' => 'dlp.jwt',
                'category' => 'dlp.secret',
                'severity' => 'high',
                'pattern' => '/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\b/',
                'message' => 'Possible JWT detected.',
            ],
        ];
    }

    /**
     * @return list<Finding>
     */
    public function scan(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $findings = [];
        foreach ($this->rules() as $rule) {
            if (@preg_match($rule['pattern'], $text) !== 1) {
                continue;
            }

            $findings[] = new Finding(
                id: $rule['id'],
                severity: $rule['severity'],
                category: $rule['category'],
                message: $rule['message'],
                evidence: [
                    // Do not include the matching secret in evidence.
                    'patternId' => $rule['id'],
                ],
            );
        }

        return $findings;
    }

    public function redact(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $out = $text;
        foreach ($this->rules() as $rule) {
            $replacement = '[REDACTED:' . strtoupper($rule['id']) . ']';
            $out = preg_replace($rule['pattern'], $replacement, $out) ?? $out;
        }

        return $out;
    }
}
