<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use Psr\Container\ContainerInterface;

class PublicationRSS extends AbstractAction
{
    protected PublicationCategoryService $publicationCategoryService;

    protected PublicationService $publicationService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->publicationService = $container->get(PublicationService::class);
        $this->publicationCategoryService = $container->get(PublicationCategoryService::class);
    }

    protected function action(): \Slim\Psr7\Response
    {
        $url = $this->parameter('common_homepage', (string) $this->request->getUri()->withPath('/'));
        $feed = new \Bhaktaraz\RSSGenerator\Feed();

        if ($channel = $this->resolveArg('channel')) {
            $category = $this->publicationCategoryService->read(['address' => $channel]);

            $channel = new \Bhaktaraz\RSSGenerator\Channel();
            $channel
                ->title($category->getTitle())
                ->description(strip_tags($category->getDescription()))
                ->url($url . $category->getAddress())
                ->atomLinkSelf($url . 'rss/' . $category->getAddress())
                ->appendTo($feed);

            /** @var \App\Domain\Entities\Publication $publication */
            foreach ($this->publicationService->read(['category_uuid' => $category->getUuid(), 'order' => [$category->getSort()['by'] => $category->getSort()['direction']]]) as $publication) {
                $item = new \Bhaktaraz\RSSGenerator\Item();
                $item
                    ->guid($publication->getUuid()->toString())
                    ->title($publication->getTitle())
                    ->category($category->getTitle())
                    ->description(strip_tags($publication->getContent()['short']))
                    ->content($publication->getContent()['full'])
                    ->pubDate($publication->getDate()->getTimestamp())
                    ->url($url . $publication->getAddress())
                    ->appendTo($channel);
            }
        }

        // todo replace RSS lib
        $this->response->getBody()->write(@$feed->render());

        return $this->response->withAddedHeader('Content-Type', 'application/rss+xml');
    }
}
