<?php

declare(strict_types=1);

namespace PhpDecide\AI;

use PhpDecide\Decision\Decision;

/**
 * Shared payload normalization for any AI gateway / security layers.
 *
 * The goal is to ensure any pre-flight scanning (DLP, size checks) matches
 * the actual payload content that would be sent to an LLM provider.
 */
final class DecisionPayloadNormalizer
{
    /**
     * Keep the AI prompt payload compact: only include the fields needed to explain decisions.
     *
     * @return array<string, mixed>
     */
    public static function decisionToCompactPayload(Decision $decision): array
    {
        $scope = [
            'type' => $decision->scope()->type()->value,
        ];

        $paths = $decision->scope()->paths();
        if ($paths !== []) {
            $scope['paths'] = $paths;
        }

        $payload = [
            'id' => $decision->id()->value(),
            'title' => $decision->title(),
            'date' => $decision->date()->format('Y-m-d'),
            'scope' => $scope,
            'summary' => $decision->content()->summary(),
            'rationale' => $decision->content()->rationale(),
        ];

        $rules = $decision->rules();
        if ($rules !== null && $rules->hasRules()) {
            $payload['rules'] = [
                'forbid' => $rules->forbid(),
                'allow' => $rules->allow(),
            ];
        }

        return $payload;
    }
}
