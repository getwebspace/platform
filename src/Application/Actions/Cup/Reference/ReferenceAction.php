<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\AbstractAction;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Casts\Reference\Type as ReferenceType;
use Psr\Container\ContainerInterface;

abstract class ReferenceAction extends AbstractAction
{
    protected ReferenceService $referenceService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->referenceService = $container->get(ReferenceService::class);
    }

    protected function resolveReferenceType(string $type): string
    {
        switch ($type) {
            case 'currencies':
                return ReferenceType::TYPE_CURRENCY;

            case 'deliveries':
                return ReferenceType::TYPE_DELIVERY;

            case 'payments':
                return ReferenceType::TYPE_PAYMENT;

            case 'countries':
                return ReferenceType::TYPE_COUNTRY;

            case 'order-status':
                return ReferenceType::TYPE_ORDER_STATUS;

            case 'stock-status':
                return ReferenceType::TYPE_STOCK_STATUS;

            case 'tax-rates':
                return ReferenceType::TYPE_TAX_RATE;

            case 'length-classes':
                return ReferenceType::TYPE_LENGTH_CLASS;

            case 'weight-classes':
                return ReferenceType::TYPE_WEIGHT_CLASS;

            case 'address-format':
                return ReferenceType::TYPE_ADDRESS_FORMAT;

            case 'store-locations':
                return ReferenceType::TYPE_STORE_LOCATION;

            case 'social-networks':
                return ReferenceType::TYPE_SOCIAL_NETWORKS;

            case 'order-dispatch':
            case 'order-invoice':

            default:
                return ReferenceType::TYPE_TEXT;
        }
    }
}
