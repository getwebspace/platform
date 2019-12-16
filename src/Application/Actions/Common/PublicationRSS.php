<?php

namespace App\Application\Actions\Common;

use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

class PublicationRSS extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $publicationCategoryRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $publicationRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->publicationCategoryRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication\Category::class);
        $this->publicationRepository = $this->entityManager->getRepository(\App\Domain\Entities\Publication::class);
    }

    protected function action(): \Slim\Http\Response
    {
        $feed = new \Bhaktaraz\RSSGenerator\Feed();

        /** @var \App\Domain\Entities\Publication\Category $category */
        foreach ($this->publicationCategoryRepository->findAll() as $category) {
            $channel = new \Bhaktaraz\RSSGenerator\Channel();
            $channel
                ->title($category->title)
                ->description($category->description)
                ->url($category->address)
                ->appendTo($feed);

            /** @var \App\Domain\Entities\Publication $publication */
            foreach ($this->publicationRepository->findBy(['category' => $category->uuid], [$category->sort['by'] => $category->sort['direction']]) as $publication) {
                $item = new \Bhaktaraz\RSSGenerator\Item();
                $item
                    ->title($publication->title)
                    ->description($publication->content['short'])
                    ->url($publication->address)
                    ->appendTo($channel);
            }
        }

        return $this->response->withAddedHeader('Content-Type', 'application/rss+xml')->write($feed->render());
    }
}
