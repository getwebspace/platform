<?php declare(strict_types=1);

namespace App\Application\Actions\Api\Publication;

use App\Application\Actions\Api\ActionApi;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use Psr\Container\ContainerInterface;

abstract class PublicationAction extends ActionApi
{
    /**
     * @var PublicationService
     */
    protected PublicationService $publicationService;

    /**
     * @var PublicationCategoryService
     */
    protected PublicationCategoryService $publicationCategoryService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->publicationService = PublicationService::getWithContainer($container);
        $this->publicationCategoryService = PublicationCategoryService::getWithContainer($container);
    }
}
