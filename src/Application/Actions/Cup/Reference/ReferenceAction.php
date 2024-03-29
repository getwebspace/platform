<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\AbstractAction;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Types\ReferenceTypeType;
use Psr\Container\ContainerInterface;

abstract class ReferenceAction extends AbstractAction
{
    protected ReferenceService $referenceService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->referenceService = $container->get(ReferenceService::class);
    }

    protected function getReferenceType(string $type): string
    {
        switch ($type) {
            case 'currencies':
                return ReferenceTypeType::TYPE_CURRENCY;

            case 'deliveries':
                return ReferenceTypeType::TYPE_DELIVERY;

            case 'payments':
                return ReferenceTypeType::TYPE_PAYMENT;

            case 'countries':
                return ReferenceTypeType::TYPE_COUNTRY;

            case 'order-status':
                return ReferenceTypeType::TYPE_ORDER_STATUS;

            case 'stock-status':
                return ReferenceTypeType::TYPE_STOCK_STATUS;

            case 'tax-rates':
                return ReferenceTypeType::TYPE_TAX_RATE;

            case 'length-classes':
                return ReferenceTypeType::TYPE_LENGTH_CLASS;

            case 'weight-classes':
                return ReferenceTypeType::TYPE_WEIGHT_CLASS;

            case 'address-format':
                return ReferenceTypeType::TYPE_ADDRESS_FORMAT;

            case 'store-locations':
                return ReferenceTypeType::TYPE_STORE_LOCATION;

            case 'social-networks':
                return ReferenceTypeType::TYPE_SOCIAL_NETWORKS;

            case 'order-dispatch':
            case 'order-invoice':

            default:
                return ReferenceTypeType::TYPE_TEXT;
        }
    }
}
