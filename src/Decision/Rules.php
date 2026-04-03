<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use InvalidArgumentException;

final class Rules
{
    public function __construct(
        array $forbid = [],
        array $allow = []
    ) {
        $this->forbid = self::normalizeTokenList($forbid, 'rules.forbid');
        $this->allow = self::normalizeTokenList($allow, 'rules.allow');
    }

    /** @var list<string> */
    private readonly array $forbid;

    /** @var list<string> */
    private readonly array $allow;
    
    public function forbid(): array
    {
        return $this->forbid;
    }
    
    public function allow(): array
    {
        return $this->allow;
    }
    
    public function hasRules(): bool
    {
        return !empty($this->forbid) || !empty($this->allow);
    }

    /**
     * @param array<mixed> $tokens
     * @return list<string>
     */
    private static function normalizeTokenList(array $tokens, string $field): array
    {
        $normalized = [];

        foreach ($tokens as $index => $token) {
            if (!is_string($token)) {
                throw new InvalidArgumentException(sprintf('%s[%s] must be a non-empty string.', $field, (string) $index));
            }

            $token = trim($token);
            if ($token === '') {
                throw new InvalidArgumentException(sprintf('%s[%s] must be a non-empty string.', $field, (string) $index));
            }

            $normalized[] = $token;
        }

        return $normalized;
    }
}
