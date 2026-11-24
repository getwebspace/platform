<?php declare(strict_types=1);

namespace App\Domain\References;

class Delivery
{
    public const KIND_PICKUP = 'pickup';
    public const KIND_COURIER = 'courier';
    public const KIND_CARRIER = 'carrier';

    // kinds of a delivery: self-pickup, courier, transport company
    public const KIND = [
        self::KIND_PICKUP,
        self::KIND_COURIER,
        self::KIND_CARRIER,
    ];
}
