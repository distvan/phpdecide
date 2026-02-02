<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use DateTimeImmutable;
use InvalidArgumentException;
use Exception;

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
            id: DecisionId::fromString($data['id']),
            title: self::string($data['title'], 'title'),
            status: self::status($data['status']),
            date: self::date($data['date']),
            scope: self::scope($data['scope']),
            content: self::content($data['decision']),
            examples: self::examples($data['examples'] ?? []),
            rules:  self::rules($data['rules'] ?? null),
            aiMetadata: self::aiMetadata($data['ai'] ?? null),
            references: self::references($data['references'] ?? [])
        );
    }

    private static function status(string $value): DecisionStatus
    {
       return DecisionStatus::from($value);
    }
    
    private static function date(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        }
        catch (Exception $e) {
            throw new InvalidArgumentException("Invalid date format: {$value}");
        }
    }

    private static function scope(array $data): Scope
    {
        self::assertRequired($data, ['type']);
        $type = ScopeType::from($data['type']);
        $paths = $data['paths'] ?? [];

        if (!is_array($paths)) {
            throw new InvalidArgumentException("Scope paths must be an array.");
        }

        return new Scope($type, $paths);
    }

    private static function content(array $data): DecisionContent
    {
        self::assertRequired($data, [
            'summary',
            'rationale',
        ]);

        if(!is_array($data['rationale'])) {
            throw new InvalidArgumentException("Decision rationale must be an array.");
        }

        return new DecisionContent(
            summary: self::string($data['summary'], 'decision.summary'),
            rationale: $data['rationale'],
            alternatives: $data['alternatives'] ?? [],
        );
    }

    private static function examples(array $data): Examples
    {
        return new Examples(
            allowed: $data['allowed'] ?? [],
            forbidden: $data['forbidden'] ?? []
        );
    }

    private static function rules(?array $data): ?Rules
    {
        if ($data === null) {
            return null;
        }

        return new Rules(
            forbid: $data['forbid'] ?? [],
            allow: $data['allow'] ?? []
        );
    }

    private static function aiMetadata(?array $data): ?AiMetadata
    {
        if ($data === null) {
            return null;
        }

        self::assertRequired($data, [
            'explain_style',
        ]);

        return new AiMetadata(
            explainStyle: self::string($data['explain_style'], 'ai.explain_style'),
            keywords: $data['keywords'] ?? []
        );
    }

    private static function references(?array $data): References
    {
        return new References(
            issues: $data['issues'] ?? [],
            commits: $data['commits'] ?? [],
            adr: $data['adr'] ?? null
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

    private static function string(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Field '{$field}' must be a non-empty string.");
        }
        return trim($value);
    }
}
