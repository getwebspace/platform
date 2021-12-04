<?php declare(strict_types=1);

namespace App\Application\Actions\Api;

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
                    $service = CatalogCategoryService::getWithContainer($this->container);

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
                    $service = CatalogProductService::getWithContainer($this->container);

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
                    $service = CatalogOrderService::getWithContainer($this->container);

                    try {
                        $result = $service->read($params);
                    } catch (OrderNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'file':
                    $service = FileService::getWithContainer($this->container);

                    try {
                        $result = $service->read($params);
                    } catch (FileNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'guestbook':
                    $service = GuestBookService::getWithContainer($this->container);

                    try {
                        $result = $service->read($params);
                    } catch (EntryNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'page':
                    $service = PageService::getWithContainer($this->container);

                    try {
                        $result = $service->read($params);
                    } catch (PageNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'publication':
                    $service = PublicationService::getWithContainer($this->container);

                    try {
                        $result = $service->read($params);
                    } catch (PublicationNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'publication/category':
                    $service = PublicationCategoryService::getWithContainer($this->container);

                    try {
                        $result = $service->read($params);
                    } catch (PublicationCategoryNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'user':
                    $service = UserService::getWithContainer($this->container);

                    try {
                        $result = $service->read($params);
                    } catch (UserNotFoundException $e) {
                        $status = 404;
                    }

                    break;

                case 'user/group':
                    $service = UserGroupService::getWithContainer($this->container);

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
                            }

                            break;

                        case 404:
                            $result = $service->create($this->getParams());
                            $status = 201;

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

    protected function array_criteria_uuid($data): array
    {
        $result = [];

        if (!is_array($data)) {
            $data = [$data];
        }

        foreach ($data as $value) {
            if (\Ramsey\Uuid\Uuid::isValid((string) $value) === true) {
                $result[] = $value;
            }
        }

        return $result;
    }

    protected function array_criteria($data): array
    {
        $result = [];

        if (!is_array($data)) {
            $data = [$data];
        }
        foreach ($data as $value) {
            $result[] = $value;
        }

        return $result;
    }
}
