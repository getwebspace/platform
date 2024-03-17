<?php declare(strict_types=1);

use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderProductService as CatalogOrderProductService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductAttributeService as CatalogProductAttributeService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\Task\TaskService;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use App\Domain\Service\User\TokenService as UserTokenService;
use App\Domain\Service\User\UserService;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        // catalog
        CatalogAttributeService::class => \DI\autowire(CatalogAttributeService::class),
        CatalogCategoryService::class => \DI\autowire(CatalogCategoryService::class),
        CatalogOrderProductService::class => \DI\autowire(CatalogOrderProductService::class),
        CatalogOrderService::class => \DI\autowire(CatalogOrderService::class),
        CatalogProductAttributeService::class => \DI\autowire(CatalogProductAttributeService::class),
        CatalogProductService::class => \DI\autowire(CatalogProductService::class),

        // file
        FileService::class => \DI\autowire(FileService::class),

        // form
        FormDataService::class => \DI\autowire(FormDataService::class),
        FormService::class => \DI\autowire(FormService::class),

        // guest book
        GuestBookService::class => \DI\autowire(GuestBookService::class),

        // page
        PageService::class => \DI\autowire(PageService::class),

        // parameter
        ParameterService::class => \DI\autowire(ParameterService::class),

        // publication
        PublicationCategoryService::class => \DI\autowire(PublicationCategoryService::class),
        PublicationService::class => \DI\autowire(PublicationService::class),

        // reference
        ReferenceService::class => \DI\autowire(ReferenceService::class),

        // task
        TaskService::class => \DI\autowire(TaskService::class),

        // user
        UserGroupService::class => \DI\autowire(UserGroupService::class),
        UserTokenService::class => \DI\autowire(UserTokenService::class),
        UserSubscriberService::class => \DI\autowire(UserSubscriberService::class),
        UserService::class => \DI\autowire(UserService::class),
    ]);
};
