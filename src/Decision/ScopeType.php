<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

enum ScopeType: string
{
    case GLOBAL = 'global';
    case PATH = 'path';
    case MODULE = 'module';
}
