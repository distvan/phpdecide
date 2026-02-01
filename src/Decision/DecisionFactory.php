<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use InvalidArgumentException;

final class DecisionFactory
{
    public static function fromArray(array $data): Decision
    {
        self::assertRequired($data,[
            'id',
            'title',
            'status',
            'date',
            'scope',
            'decision'
        ]);
        
        return new Decision(
            id: '',
            title: '',
            status: '',
            date: '',
            scope: '',
            content: '',
            examples: '',
            rules: '',
            aiMetadata: '',
            references: ''
        );
    }

    private static function assertRequired(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
        }
    }
}
