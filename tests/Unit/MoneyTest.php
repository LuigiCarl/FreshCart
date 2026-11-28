<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_parses_decimals_multiplies_adds_and_formats_without_float_drift(): void
    {
        $apples = Money::fromDecimal('19.99')->multiply(3);
        $milk = Money::fromDecimal('4.5')->multiply(2);
        $total = Money::zero()->add($apples)->add($milk);

        $this->assertSame(5997, $apples->cents);
        $this->assertSame('59.97', $apples->toDecimal());
        $this->assertSame('9.00', $milk->toDecimal());
        $this->assertSame('68.97', $total->toDecimal());
    }

    public function test_it_rejects_invalid_decimal_strings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('-10.00');
    }

    public function test_it_rejects_negative_quantities(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('10.00')->multiply(-1);
    }
}
