<?php

declare(strict_types=1);

namespace Examples\Fixtures\PhpStan\Order;

final class OrderService
{
    public function dependencyMarker(): string
    {
        return 'Doctrine\\ORM\\Mapping\\ClassMetadata';
    }
}
