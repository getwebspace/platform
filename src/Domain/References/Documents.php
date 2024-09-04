<?php declare(strict_types=1);

namespace App\Domain\References;

class Documents
{
    public const ORDER_TARGET = 'Order';

    // list of document groups
    public const DOCUMENT_TARGET = [
        self::ORDER_TARGET,
    ];
}
