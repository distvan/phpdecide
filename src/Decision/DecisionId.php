<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use InvalidArgumentException;

final class DecisionId
{
    public function __construct(
        private string $value
    ) {}

    public static function fromString(string $value): self
    {
        if (!preg_match('/^DEC-\d{4}$/', $value)) {
            throw new InvalidArgumentException('Invalid Decision ID format.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
