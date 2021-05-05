<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Domain\AbstractAction;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use Psr\Container\ContainerInterface;

abstract class PublicationAction extends AbstractAction
{
    protected PublicationCategoryService $publicationCategoryService;

    protected PublicationService $publicationService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->publicationCategoryService = PublicationCategoryService::getWithContainer($container);
        $this->publicationService = PublicationService::getWithContainer($container);
    }
}
