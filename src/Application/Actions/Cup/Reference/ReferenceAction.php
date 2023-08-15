<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\AbstractAction;
use App\Domain\Entities\Reference;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\MeasureService as CatalogMeasureService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\OrderStatusService as CatalogOrderStatusService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Notification\NotificationService;
use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class ReferenceAction extends AbstractAction
{
    protected ReferenceService $referenceService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->referenceService = $container->get(ReferenceService::class);
    }

    /**
     * @throws TitleAlreadyExistsException
     * @throws MissingTitleValueException
     */
    protected function doCreateAction(string $type): Reference
    {
        $ref = $this->referenceService->create([
            'type' => $type,
            'title' => $this->getParam('title'),
            'value' => $this->getParam('value'),
            'order' => $this->getParam('order'),
            'status' => $this->getParam('status'),
        ]);

        $this->container->get(\App\Application\PubSub::class)->publish('cup:reference:create', $ref);

        return $ref;
    }

    /**
     * @throws ReferenceNotFoundException
     * @throws TitleAlreadyExistsException
     */
    protected function doUpdateAction(Reference $ref, string $type): Reference
    {
        $ref = $this->referenceService->update($ref, [
            'type' => $type,
            'title' => $this->getParam('title'),
            'value' => $this->getParam('value'),
            'order' => $this->getParam('order'),
            'status' => $this->getParam('status'),
        ]);

        $this->container->get(\App\Application\PubSub::class)->publish('cup:reference:edit', $ref);

        return $ref;
    }
}
