<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

use DateTimeImmutable;
use InvalidArgumentException;
use Exception;
use ValueError;

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

        $scopeData = self::arrayField($data['scope'], 'scope');
        $decisionData = self::arrayField($data['decision'], 'decision');
        $examplesData = self::arrayField($data['examples'] ?? [], 'examples');

        $rulesData = $data['rules'] ?? null;
        if ($rulesData !== null) {
            $rulesData = self::arrayField($rulesData, 'rules');
        }

        $aiData = $data['ai'] ?? null;
        if ($aiData !== null) {
            $aiData = self::arrayField($aiData, 'ai');
        }

        $referencesData = self::arrayField($data['references'] ?? [], 'references');

        $id = self::string($data['id'], 'id');

        return new Decision(
            id: DecisionId::fromString($id),
            title: self::string($data['title'], 'title'),
            status: self::status($data['status']),
            date: self::date($data['date']),
            scope: self::scope($scopeData),
            content: self::content($decisionData),
            examples: self::examples($examplesData),
            rules:  self::rules($rulesData),
            aiMetadata: self::aiMetadata($aiData),
            references: self::references($referencesData)
        );
    }

    private static function status(mixed $value): DecisionStatus
    {
        $value = self::string($value, 'status');

        try {
            return DecisionStatus::from($value);
        } catch (ValueError) {
            throw new InvalidArgumentException("Invalid status: {$value}");
        }
    }
    
    private static function date(mixed $value): DateTimeImmutable
    {
        $value = self::string($value, 'date');

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
        $typeValue = self::string($data['type'], 'scope.type');
        try {
            $type = ScopeType::from($typeValue);
        } catch (ValueError) {
            throw new InvalidArgumentException("Invalid scope type: {$typeValue}");
        }
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

        if (!is_array($data['rationale'])) {
            throw new InvalidArgumentException("Decision rationale must be an array.");
        }

        $alternatives = $data['alternatives'] ?? [];
        if (!is_array($alternatives)) {
            throw new InvalidArgumentException('Decision alternatives must be an array.');
        }

        return new DecisionContent(
            summary: self::string($data['summary'], 'decision.summary'),
            rationale: $data['rationale'],
            alternatives: $alternatives,
        );
    }

    private static function examples(array $data): Examples
    {
        $allowed = $data['allowed'] ?? [];
        $forbidden = $data['forbidden'] ?? [];

        if (!is_array($allowed)) {
            throw new InvalidArgumentException('examples.allowed must be an array.');
        }
        if (!is_array($forbidden)) {
            throw new InvalidArgumentException('examples.forbidden must be an array.');
        }

        return new Examples(
            allowed: $allowed,
            forbidden: $forbidden
        );
    }

    private static function rules(?array $data): ?Rules
    {
        if ($data === null) {
            return null;
        }

        $forbid = $data['forbid'] ?? [];
        $allow = $data['allow'] ?? [];

        if (!is_array($forbid)) {
            throw new InvalidArgumentException('rules.forbid must be an array.');
        }
        if (!is_array($allow)) {
            throw new InvalidArgumentException('rules.allow must be an array.');
        }

        return new Rules(
            forbid: $forbid,
            allow: $allow
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

        $keywords = $data['keywords'] ?? [];
        if (!is_array($keywords)) {
            throw new InvalidArgumentException('ai.keywords must be an array.');
        }

        return new AiMetadata(
            explainStyle: self::string($data['explain_style'], 'ai.explain_style'),
            keywords: $keywords
        );
    }

    private static function references(?array $data): References
    {
        $issues = $data['issues'] ?? [];
        $commits = $data['commits'] ?? [];
        $adr = $data['adr'] ?? null;

        if (!is_array($issues)) {
            throw new InvalidArgumentException('references.issues must be an array.');
        }
        if (!is_array($commits)) {
            throw new InvalidArgumentException('references.commits must be an array.');
        }
        if ($adr !== null && !is_string($adr)) {
            throw new InvalidArgumentException("Field 'references.adr' must be a string or null.");
        }

        return new References(
            issues: $issues,
            commits: $commits,
            adr: $adr
        );
    }

    /**
     * @return array<mixed>
     */
    private static function arrayField(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Field '{$field}' must be an array.");
        }

        return $value;
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
