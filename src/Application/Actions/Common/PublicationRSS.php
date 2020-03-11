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

        if (
            ($url = $this->getParameter('common_homepage', false)) !== false &&
            ($channel = $this->resolveArg('channel'))
        ) {
            /** @var \App\Domain\Entities\Publication\Category $category */
            $category = $this->publicationCategoryRepository->findOneBy(['address' => str_escape($channel)]);

            $channel = new \Bhaktaraz\RSSGenerator\Channel();
            $channel
                ->title($category->title)
                ->description($category->description ? $category->description : null)
                ->url($url . $category->address)
                ->atomLinkSelf($url . 'rss/' . $category->address)
                ->appendTo($feed);

            /** @var \App\Domain\Entities\Publication $publication */
            foreach ($this->publicationRepository->findBy(['category' => $category->uuid], [$category->sort['by'] => $category->sort['direction']]) as $publication) {
                $item = new \Bhaktaraz\RSSGenerator\Item();
                $item
                    ->guid($publication->uuid->toString())
                    ->title($publication->title)
                    ->category($category->title)
                    ->description($publication->content['short'])
                    ->content($publication->content['full'])
                    ->pubDate($publication->date->getTimestamp())
                    ->url($url . $publication->address)
                    ->appendTo($channel);
            }
        }

        return $this->response->withAddedHeader('Content-Type', 'application/rss+xml')->write($feed->render());
    }
}
