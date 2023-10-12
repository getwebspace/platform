<?php declare(strict_types=1);

namespace App\Domain\References;

class Plugin
{
    // possible type
    public const TYPE_LANGUAGE = 'language';
    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_PAYMENT = 'payment';

    // list of types
    public const TYPES = [
        self::TYPE_LANGUAGE,
        self::TYPE_DELIVERY,
        self::TYPE_PAYMENT,
    ];
}
