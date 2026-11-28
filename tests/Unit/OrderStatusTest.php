<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_only_pending_orders_can_be_cancelled(): void
    {
        $this->assertTrue(OrderStatus::Pending->canBeCancelled());
        $this->assertFalse(OrderStatus::Processing->canBeCancelled());
        $this->assertFalse(OrderStatus::Completed->canBeCancelled());
        $this->assertFalse(OrderStatus::Failed->canBeCancelled());
        $this->assertFalse(OrderStatus::Cancelled->canBeCancelled());
    }

    public function test_completed_and_cancelled_are_terminal_states(): void
    {
        $this->assertFalse(OrderStatus::Pending->isTerminal());
        $this->assertFalse(OrderStatus::Processing->isTerminal());
        $this->assertFalse(OrderStatus::Failed->isTerminal());
        $this->assertTrue(OrderStatus::Completed->isTerminal());
        $this->assertTrue(OrderStatus::Cancelled->isTerminal());
    }
}
