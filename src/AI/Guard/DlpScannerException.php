<?php

declare(strict_types=1);

namespace PhpDecide\AI\Guard;

/**
 * Thrown when the DLP scanner encounters a PCRE error during redaction.
 */
final class DlpScannerException extends \RuntimeException
{
}
