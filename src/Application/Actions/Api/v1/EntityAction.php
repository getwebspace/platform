<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Domain\AbstractException;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\Exception\CategoryNotFoundException as CatalogCategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\Exception\FileNotFoundException;
use App\Domain\Service\File\FileService;
use App\Domain\Service\GuestBook\Exception\EntryNotFoundException;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\CategoryNotFoundException as PublicationCategoryNotFoundException;
use App\Domain\Service\Publication\Exception\PublicationNotFoundException;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\User\Exception\UserGroupNotFoundException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\UserService;
use Illuminate\Support\Collection;

class EntityAction extends ActionApi
{
    protected function action(): \Slim\Psr7\Response
    {
        $status = 401;
        $params = [
            'entity' => null,
            'order' => [],
            'limit' => 1000,
            'offset' => 0,
        ];
        $params = array_merge($params, $this->request->getQueryParams());
        $params['entity'] = ltrim($this->resolveArg('args'), '/');
        $result = [];

        if (($access = $this->isAccessAllowed()) !== false) {
            $status = 200;
            $params['access'] = $access;
            $service = null;

            // read section
            switch ($params['entity']) {
                case 'catalog/category':
                    $service = $this->container->get(CatalogCategoryService::class);

                    try {
                        $result = $service->read(
                            array_merge(
                                ['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK],
                                $params
                            )
                        );
                    } catch (CatalogCategoryNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'catalog/product':
                    $service = $this->container->get(CatalogProductService::class);

                    try {
                        $result = $service->read(
                            array_merge(
                                ['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK],
                                $params
                            )
                        );
                    } catch (ProductNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'catalog/order':
                    $service = $this->container->get(CatalogOrderService::class);

                    try {
                        $result = $service->read($params);
                    } catch (OrderNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'file':
                    $service = $this->container->get(FileService::class);

                    try {
                        $result = $service->read($params);
                    } catch (FileNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'guestbook':
                    $service = $this->container->get(GuestBookService::class);

                    try {
                        $result = $service->read($params);
                    } catch (EntryNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'page':
                    $service = $this->container->get(PageService::class);

                    try {
                        $result = $service->read($params);
                    } catch (PageNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'publication':
                    $service = $this->container->get(PublicationService::class);

                    try {
                        $result = $service->read($params);
                    } catch (PublicationNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'publication/category':
                    $service = $this->container->get(PublicationCategoryService::class);

                    try {
                        $result = $service->read($params);
                    } catch (PublicationCategoryNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'user':
                    $service = $this->container->get(UserService::class);

                    try {
                        $result = $service->read($params);
                    } catch (UserNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'user/group':
                    $service = $this->container->get(UserGroupService::class);

                    try {
                        $result = $service->read($params);
                    } catch (UserGroupNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                default:
                    $status = 204;
                    $result = 'unknown type: ' . $params['entity'];
            }

            // update section
            if (!empty($access['key']) && $this->isPost()) {
                try {
                    switch ($status) {
                        case 200:
                            if ($result) {
                                if (!is_array($result) && !is_a($result, Collection::class)) {
                                    $result = [$result];
                                }

                                foreach ($result as $index => $item) {
                                    $result[$index] = $service->update($item, $this->getParams());
                                }
                                $status = 202;

                                $this->container->get(\App\Application\PubSub::class)->publish('api:' . str_replace('/', ':', $params['entity']) . ':edit', $result);
                            }

                            break;

                        case 404:
                            $result = $service->create($this->getParams());
                            $status = 201;

                            $this->container->get(\App\Application\PubSub::class)->publish('api:' . str_replace('/', ':', $params['entity']) . ':create', $result);

                            break;
                    }

                    $this->logger->notice('Update entity via API', $params);
                } catch (AbstractException $exception) {
                    $status = 500;
                    $result = $exception->getTitle();
                }
            }
        }

        return $this->respondWithJson([
            'status' => $status,
            'params' => $params,
            'data' => is_a($result, Collection::class) ? $result->toArray() : $result,
        ]);
    }
}
