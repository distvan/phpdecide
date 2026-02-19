<?php

declare(strict_types=1);

namespace PhpDecide\Decision;

/**
 * Optional capability interface.
 *
 * Repositories that already maintain a precomputed text index for decisions can
 * implement this to allow explain/matching to reuse it.
 */
interface SearchIndexedDecisionRepository
{
    /**
     * Returns a lowercase text "haystack" for a decision.
     *
     * Implementations should ideally return a precomputed value.
     */
    public function haystackLowerFor(Decision $decision): string;
}
