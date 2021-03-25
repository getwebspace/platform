<?php declare(strict_types=1);

namespace App\Application\Actions\Api;

use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;
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
    protected function action(): \Slim\Http\Response
    {
        $status = 200;
        $params = [
            'order' => [],
            'limit' => 1000,
            'offset' => 0,
        ];
        $params = array_merge($params, $this->request->getQueryParams());

        // check access
        if ($this->parameter('entity_access', 'user') === 'user' && $this->request->getAttribute('user') === null) {
            return $this->respondWithJson([
                'status' => 403,
                'params' => $params,
                'data' => [],
            ]);
        }

        $service = null;
        $result = [];

        switch (ltrim($this->resolveArg('args'), '/')) {
            case 'catalog/category':
                $service = CatalogCatalogService::getWithContainer($this->container);

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
        }

        // update section
        // !! unsecure !!
        /*if ($this->request->isPost()) {
            if ($result && $status === 200) {
                if (!is_array($result) && !is_a($result, Collection::class)) {
                    $result = [$result];
                }

                foreach ($result as &$item) {
                    $item = $service->update($item, $this->request->getParams());
                }
            } else {
                $result = $service->create($this->request->getParams());
            }
        }*/

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
            if (\Ramsey\Uuid\Uuid::isValid($value) === true) {
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
