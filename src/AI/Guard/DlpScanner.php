<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

final class DlpScanner
{
    /**
     * @return array{id: string, category: string, severity: 'low'|'medium'|'high'|'critical', pattern: string, message: string}
     */
    private function rule(string $id, string $severity, string $pattern, string $message): array
    {
        return [
            'id' => $id,
            'category' => 'dlp.secret',
            'severity' => $severity,
            'pattern' => $pattern,
            'message' => $message,
        ];
    }

    /**
     * @return list<array{id: string, category: string, severity: 'low'|'medium'|'high'|'critical', pattern: string, message: string}>
     */
    private function rules(): array
    {
        return [
            $this->rule('dlp.openai_api_key', 'critical', '/\bsk-[A-Za-z0-9]{20,}\b/', 'Possible OpenAI API key detected.'),
            $this->rule('dlp.github_token',   'critical', '/\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{20,}\b/', 'Possible GitHub token detected.'),
            $this->rule('dlp.github_pat',     'critical', '/\bgithub_pat_[A-Za-z0-9_]{20,}\b/', 'Possible GitHub fine-grained PAT detected.'),
            $this->rule('dlp.private_key_block', 'critical', '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i', 'Private key material detected.'),
            $this->rule('dlp.aws_access_key_id', 'high', '/\bAKIA[0-9A-Z]{16}\b/', 'Possible AWS access key id detected.'),
            $this->rule('dlp.jwt',            'high', '/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\b/', 'Possible JWT detected.'),
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
