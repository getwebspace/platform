<?php declare(strict_types=1);

namespace App\Domain\References;

class OrderStatus
{
    // an order in such a status does not count (statistics of "bought together", ...)
    public const KIND_CANCELED = 'canceled';

    public const KIND = [
        self::KIND_CANCELED,
    ];
}
