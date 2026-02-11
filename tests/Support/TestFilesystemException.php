<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Support;

/**
 * Filesystem/IO failures inside tests (temp dir creation, writing fixtures, etc).
 */
final class TestFilesystemException extends \RuntimeException
{
}
