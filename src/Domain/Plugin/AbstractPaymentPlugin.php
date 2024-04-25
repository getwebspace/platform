<?php declare(strict_types=1);

namespace App\Domain\Plugin;

use App\Domain\AbstractPlugin;
use App\Domain\Models\CatalogOrder;

abstract class AbstractPaymentPlugin extends AbstractPlugin {
    abstract public function getRedirectURL(CatalogOrder $order): ?string;
}
