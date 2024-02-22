<?php declare(strict_types=1);

namespace App\Domain\Plugin;

use App\Domain\AbstractPlugin;
use App\Domain\Entities\Catalog\Order;

abstract class AbstractPaymentPlugin extends AbstractPlugin {
    abstract public function getRedirectURL(Order $order): ?string;
}
