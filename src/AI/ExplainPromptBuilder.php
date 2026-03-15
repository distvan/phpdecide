<?php

declare(strict_types=1);

namespace PhpDecide\AI;

final class ExplainPromptBuilder
{
    public static function systemPrompt(?string $systemPromptOverride = null): string
    {
        return $systemPromptOverride ?? self::defaultSystemPrompt();
    }

    public static function userContentFromDecisionPayloadJson(string $question, string $decisionPayloadJson): string
    {
        return "Question:\n{$question}\n\nRecorded decisions (authoritative source of truth):\n"
            . $decisionPayloadJson
            . "\n\nTask: Summarize ONLY what is recorded above. If something is missing, say it's not recorded.";
    }

    public static function inputCharsFromDecisionPayloadJson(
        string $question,
        string $decisionPayloadJson,
        ?string $systemPromptOverride = null,
    ): int {
        return mb_strlen(
            self::systemPrompt($systemPromptOverride)
            . self::userContentFromDecisionPayloadJson($question, $decisionPayloadJson)
        );
    }

    private static function defaultSystemPrompt(): string
    {
        return <<<PROMPT
You are a helpful assistant for explaining software architecture decisions.

Critical constraints:
- Decisions and rules are defined ONLY by the provided recorded decision data.
- Do NOT invent new rules, scopes, exceptions, or facts.
- If the information needed to answer is not present, explicitly say it is not recorded.

Output style:
- Be concise.
- Reference decisions by ID like [DEC-0001].
- Prefer short bullets and plain language.
PROMPT;
    }
}
