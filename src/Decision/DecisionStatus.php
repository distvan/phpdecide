<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

enum DecisionStatus: string
{
    case ACTIVE = 'active';
    case DEPRECATED = 'deprecated';
    case SUPERSEDED = 'superseded';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
