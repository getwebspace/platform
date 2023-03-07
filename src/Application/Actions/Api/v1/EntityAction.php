<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Domain\AbstractException;
use App\Domain\AbstractNotFoundException;
use App\Domain\AbstractService;
use App\Domain\Entities\User;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderProductService as CatalogOrderProductService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\UserService;
use Doctrine\DBAL\Exception;
use Illuminate\Support\Collection;
use Psr\Container\ContainerExceptionInterface;

class EntityAction extends ActionApi
{
    protected function action(): \Slim\Psr7\Response
    {
        $params = [
            'user' => null,
            'user_uuid' => null,
            'entity' => null,
            'order' => [],
            'limit' => 1000,
            'offset' => 0,
        ];
        $params = array_merge($params, $this->request->getQueryParams());
        $params['entity'] = ltrim($this->resolveArg('args'), '/');

        if (($apikey = $this->request->getAttribute('apikey', false)) !== false) {
            $params['apikey'] = $apikey;
        }

        /** @var User $user */
        if (($user = $this->request->getAttribute('user', false)) !== false) {
            $params['user'] = $user;
            $params['user_uuid'] = $user->getUuid();
        }

        return $this->process($params);
    }

    private function process(array $params): \Slim\Psr7\Response
    {
        $status = 200;
        $result = [];

        try {
            $service = $this->getService($params['entity']);

            switch ($this->request->getMethod()) {
                case 'GET':
                    try {
                        $result = $service->read($params);
                    } catch (AbstractNotFoundException|Exception $e) {
                        $status = 404;
                    }

                    break;

                case 'POST':
                case 'PUT':
                    if (!empty($params['apikey'])) {
                        $result = $service->create([...$this->getParams(), 'user' => $params['user'], 'user_uuid' => $params['user_uuid']]);
                        $result = $this->processEntityFiles($result);

                        $status = 201;

                        $this->container->get(\App\Application\PubSub::class)->publish('api:' . str_replace('/', ':', $params['entity']) . ':create', $result);
                        $this->logger->notice('Create entity via API', $params);
                    } else {
                        $status = 405;
                    }

                    break;

                case 'PATCH':
                    if (!empty($params['apikey'])) {
                        try {
                            $result = $service->read($params);

                            if ($result) {
                                if (!is_array($result) && !is_a($result, Collection::class)) {
                                    $result = [$result];
                                }

                                foreach ($result as &$item) {
                                    $item = $service->update($item, [...$this->getParams(), 'user' => $params['user'], 'user_uuid' => $params['user_uuid']]);
                                    $item = $this->processEntityFiles($item);
                                }

                                $status = 202;

                                $this->container->get(\App\Application\PubSub::class)->publish('api:' . str_replace('/', ':', $params['entity']) . ':edit', $result);
                                $this->logger->notice('Update entity via API', $params);
                            } else {
                                $status = 409;
                            }
                        } catch (AbstractNotFoundException|Exception $e) {
                            $status = 404;
                        }
                    } else {
                        $status = 405;
                    }

                    break;

                case 'DELETE':
                    if (!empty($params['apikey'])) {
                        try {
                            $result = $service->read($params);

                            if ($result) {
                                if (!is_array($result) && !is_a($result, Collection::class)) {
                                    $result = [$result];
                                }

                                foreach ($result as &$item) {
                                    $item = $service->delete($item);
                                }

                                $status = 410;

                                $this->container->get(\App\Application\PubSub::class)->publish('api:' . str_replace('/', ':', $params['entity']) . ':delete', $result);
                                $this->logger->notice('Delete entity via API', $params);
                            } else {
                                $status = 409;
                            }
                        } catch (AbstractNotFoundException|Exception $e) {
                            $status = 404;
                        }
                    } else {
                        $status = 405;
                    }

                    break;
            }
        } catch (ContainerExceptionInterface|AbstractException $exception) {
            $status = 503;
            $result = $exception->getTitle();
        }

        return $this
            ->respondWithJson([
                'status' => $status,
                'data' => is_a($result, Collection::class) ? $result->toArray() : $result,
            ])
            ->withStatus($status);
    }

    /**
     * @throws ContainerExceptionInterface
     */
    private function getService(mixed $entity): ?AbstractService
    {
        return match ($entity) {
            'catalog/category' => $this->container->get(CatalogCategoryService::class),
            'catalog/product' => $this->container->get(CatalogProductService::class),
            'catalog/order' => $this->container->get(CatalogOrderService::class),
            'catalog/order/product' => $this->container->get(CatalogOrderProductService::class),
            'file' => $this->container->get(FileService::class),
            'guestbook' => $this->container->get(GuestBookService::class),
            'page' => $this->container->get(PageService::class),
            'publication' => $this->container->get(PublicationService::class),
            'publication/category' => $this->container->get(PublicationCategoryService::class),
            'user' => $this->container->get(UserService::class),
            'user/group' => $this->container->get(UserGroupService::class),
            default => null,
        };
    }
}
