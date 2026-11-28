<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(public int $cents) {}

    public static function fromDecimal(string $amount): self
    {
        $normalized = trim($amount);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException("Invalid monetary amount [{$amount}].");
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');

        return new self(((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0'));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Quantity cannot be negative [{$quantity}].");
        }

        return new self($this->cents * $quantity);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function toDecimal(): string
    {
        return intdiv($this->cents, 100).'.'.str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
