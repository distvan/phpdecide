<?php

declare(strict_types=1);

namespace PhpDecide\Tests\Decision;

use InvalidArgumentException;
use PhpDecide\Decision\DecisionId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecisionIdTest extends TestCase
{
    public function testFromStringAcceptsValidFormat(): void
    {
        $id = DecisionId::fromString('DEC-0001');
        self::assertSame('DEC-0001', $id->value());
    }

    #[DataProvider('invalidIds')]
    public function testFromStringRejectsInvalidFormat(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecisionId::fromString($value);
    }

    /** @return array<string, array{0: string}> */
    public static function invalidIds(): array
    {
        return [
            'empty' => [''],
            'lowercase prefix' => ['dec-0001'],
            'too few digits' => ['DEC-001'],
            'too many digits' => ['DEC-00001'],
            'missing dash' => ['DEC0001'],
            'wrong prefix' => ['ABC-0001'],
            'non digits' => ['DEC-ABCD'],
            'spaces' => [' DEC-0001 '],
        ];
    }

    public function testEqualsComparesValue(): void
    {
        $a = DecisionId::fromString('DEC-0001');
        $b = DecisionId::fromString('DEC-0001');
        $c = DecisionId::fromString('DEC-0002');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
