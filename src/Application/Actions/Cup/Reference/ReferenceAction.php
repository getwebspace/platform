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
                return ReferenceType::CURRENCY;

            case 'deliveries':
                return ReferenceType::DELIVERY;

            case 'payments':
                return ReferenceType::PAYMENT;

            case 'countries':
                return ReferenceType::COUNTRY;

            case 'order-status':
                return ReferenceType::ORDER_STATUS;

            case 'stock-status':
                return ReferenceType::STOCK_STATUS;

            case 'tax-rates':
                return ReferenceType::TAX_RATE;

            case 'length-classes':
                return ReferenceType::LENGTH_CLASS;

            case 'weight-classes':
                return ReferenceType::WEIGHT_CLASS;

            case 'address-format':
                return ReferenceType::ADDRESS_FORMAT;

            case 'store-locations':
                return ReferenceType::STORE_LOCATION;

            case 'social-network':
                return ReferenceType::SOCIAL_NETWORK;

            case 'manufacturer':
                return ReferenceType::MANUFACTURER;

            case 'order-dispatch':
            case 'order-invoice':

            default:
                return ReferenceType::TEXT;
        }
    }
}
