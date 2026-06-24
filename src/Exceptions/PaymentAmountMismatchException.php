<?php

declare(strict_types=1);

namespace Lalalili\CommercePayment\Exceptions;

use RuntimeException;

class PaymentAmountMismatchException extends RuntimeException
{
    public static function forOrder(string $orderNumber, int $expectedAmount, int $actualAmount): self
    {
        return new self(
            "Payment amount mismatch for order [{$orderNumber}]: expected {$expectedAmount}, got {$actualAmount}.",
        );
    }
}
