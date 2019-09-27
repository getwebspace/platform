<?php

namespace App\Application;

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Slim\Http\Uri;

class TwigExtension extends \Twig\Extension\AbstractExtension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var \Slim\Interfaces\RouterInterface
     */
    protected $router;

    /**
     * @var string|\Slim\Http\Uri
     */
    protected $uri;

    public function __construct(ContainerInterface $container, $uri)
    {
        $this->container = $container;
        $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        $this->router = $container->get('router');
        $this->uri = $uri;
    }

    public function getName()
    {
        return '0x12f';
    }

    public function getFilters()
    {
        return [
            new \Twig\TwigFilter('count', [$this, 'count']),
        ];
    }

    public function getFunctions()
    {
        return [
            // slim functions
            new \Twig\TwigFunction('path_for', [$this, 'pathFor']),
            new \Twig\TwigFunction('full_url_for', [$this, 'fullUrlFor']),
            new \Twig\TwigFunction('base_url', [$this, 'baseUrl']),
            new \Twig\TwigFunction('is_current_path', [$this, 'isCurrentPath']),
            new \Twig\TwigFunction('current_path', [$this, 'currentPath']),

            // 0x12f functions
            new \Twig\TwigFunction('form', [$this, 'form'], ['is_safe' => ['html']]),
            new \Twig\TwigFunction('reference', [$this, 'reference']),
            new \Twig\TwigFunction('pre', [$this, 'pre']),
            new \Twig\TwigFunction('count', [$this, 'count']),
            new \Twig\TwigFunction('collect', [$this, 'collect']),
            new \Twig\TwigFunction('non_page_path', [$this, 'non_page_path']),
            new \Twig\TwigFunction('current_page_number', [$this, 'current_page_number']),
            new \Twig\TwigFunction('is_current_page_number', [$this, 'is_current_page_number']),

            // publication functions
            new \Twig\TwigFunction('files', [$this, 'files']),

            // publication functions
            new \Twig\TwigFunction('publication', [$this, 'publication']),
            new \Twig\TwigFunction('publication_category', [$this, 'publication_category']),

            // catalog functions
            new \Twig\TwigFunction('catalog_category', [$this, 'catalog_category']),
            new \Twig\TwigFunction('catalog_breadcrumb', [$this, 'catalog_breadcrumb']),
            new \Twig\TwigFunction('catalog_product', [$this, 'catalog_product']),
            new \Twig\TwigFunction('catalog_product_view', [$this, 'catalog_product_view']),
            new \Twig\TwigFunction('catalog_order', [$this, 'catalog_order']),

            // trademaster
            new \Twig\TwigFunction('tm_api', [$this, 'tm_api']),
        ];
    }

    /*
     * slim functions
     */

    public function pathFor($name, $data = [], $queryParams = [])
    {
        return $this->router->pathFor($name, $data, $queryParams);
    }

    /**
     * Similar to pathFor but returns a fully qualified URL
     *
     * @param string $name The name of the route
     * @param array  $data Route placeholders
     * @param array  $queryParams
     *
     * @return string fully qualified URL
     */
    public function fullUrlFor($name, $data = [], $queryParams = [])
    {
        $path = $this->pathFor($name, $data, $queryParams);

        /** @var Uri $uri */
        if (is_string($this->uri)) {
            $uri = Uri::createFromString($this->uri);
        } else {
            $uri = $this->uri;
        }

        $scheme = $uri->getScheme();
        $authority = $uri->getAuthority();

        $host = ($scheme ? $scheme . ':' : '')
            . ($authority ? '//' . $authority : '');

        return $host . $path;
    }

    public function baseUrl()
    {
        if (is_string($this->uri)) {
            return $this->uri;
        }
        if (method_exists($this->uri, 'getBaseUrl')) {
            return $this->uri->getBaseUrl();
        }
    }

    public function isCurrentPath($name, $data = [])
    {
        return $this->router->pathFor($name, $data) === $this->uri->getBasePath() . '/' . ltrim($this->uri->getPath(), '/');
    }

    /**
     * Returns current path on given URI.
     *
     * @param bool $withQueryString
     *
     * @return string
     */
    public function currentPath($withQueryString = false)
    {
        if (is_string($this->uri)) {
            return $this->uri;
        }

        $path = $this->uri->getBasePath() . '/' . ltrim($this->uri->getPath(), '/');

        if ($withQueryString && '' !== $query = $this->uri->getQuery()) {
            $path .= '?' . $query;
        }

        return $path;
    }

    /**
     * Set the base url
     *
     * @param string|\Slim\Http\Uri $baseUrl
     *
     * @return void
     */
    public function setBaseUrl($baseUrl)
    {
        $this->uri = $baseUrl;
    }

    /*
     * 0x12f functions
     */

    public function form($type, $name, $args = [])
    {
        return form($type, $name, $args);
    }

    // todo посмотреть на это решение еще
    public function reference($reference, $value = null)
    {
        try {
            $reference = constant(str_replace('/', '\\', $reference));

            switch ($value) {
                case null:
                    return $reference;
                    break;

                default:
                    return $reference[$value];
            }

        } catch (\Exception $e) {
            /* todo nothing */
        }

        return $value;
    }

    /**
     * Debug function
     *
     * @param mixed ...$args
     */
    public function pre(...$args)
    {
        call_user_func_array('pre', $args);
    }

    public function count($obj)
    {
        return is_countable($obj) ? count($obj) : false;
    }

    public function collect(array $array = [])
    {
        return collect($array);
    }

    public function non_page_path()
    {
        $path = $this->uri->getBasePath() . '/' . ltrim($this->uri->getPath(), '/');
        $path = explode('/', $path);

        if (($key = count($path) - 1) && ($buf = $path[$key]) && ctype_digit($buf)) {
            unset($path[$key]);
        }

        return implode('/', $path);
    }

    public function current_page_number()
    {
        $page = 0;
        $path = explode('/', ltrim($this->uri->getPath(), '/'));

        if (($key = count($path) - 1) && ($buf = $path[$key]) && ctype_digit($buf)) {
            $page = $path[$key];
        }

        return $page;
    }

    public function is_current_page_number($number)
    {
        return $this->current_page_number() == $number;
    }

    /*
     * files functions
     */

    // получает файлы по параметрам
    public function files($data = [])
    {
        $default = [
            'uuid' => '',
            'item' => '',
            'item_uuid' => '',
        ];
        $data = array_merge($default, $data);
        $criteria = [];

        if ($data['uuid']) {
            if (!is_array($data['uuid'])) $data['uuid'] = [$data['uuid']];

            foreach ($data['uuid'] as $value) {
                if (\Ramsey\Uuid\Uuid::isValid($value) === true) {
                    $criteria['uuid'][] = $value;
                }
            }
        }

        if ($data['item']) {
            $criteria['item'] = $data['item'];
        }

        if ($data['item_uuid']) {
            if (!is_array($data['item_uuid'])) $data['item_uuid'] = [$data['item_uuid']];

            foreach ($data['item_uuid'] as $value) {
                if (\Ramsey\Uuid\Uuid::isValid($value) === true) {
                    $criteria['item_uuid'][] = $value;
                }
            }
        }

        /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $repository */
        $repository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);

        return collect($repository->findBy($criteria));
    }

    /*
     * publication functions
     */

    // получение списка категорий публикаций
    public function publication_category($limit = null)
    {
        static $buf;

        if (!$buf) {
            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $repository */
            $repository = $this->entityManager->getRepository(\App\Domain\Entities\Publication\Category::class);

            $buf = collect($repository->findAll());
        }

        return $buf;
    }

    // получение списка публикаций
    public function publication($category = null, $order = [], $limit = 10, $offset = null)
    {
        static $buf;

        $criteria = [];

        if ($category) {
            switch (true) {
                case \Ramsey\Uuid\Uuid::isValid($category) === true:
                    $criteria['uuid'] = $category;
                    break;

                default:
                    $criteria['address'] = $category;
                    break;
            }
        }

        $key = json_encode($criteria, JSON_UNESCAPED_UNICODE).$limit.$offset;

        if (!isset($buf[$key])) {
            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $repository */
            $repository = $this->entityManager->getRepository(\App\Domain\Entities\Publication::class);

            $buf[$key] = $limit > 1 ? collect($repository->findBy($criteria, $order, $limit, $offset)) : $repository->findOneBy($criteria, $order);
        }

        return $buf[$key];
    }

    /*
     * catalog functions
     */

    // получение списка категорий товаров
    public function catalog_category()
    {
        static $buf;

        if (!$buf) {
            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $repository */
            $repository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class);

            $buf = collect($repository->findAll());
        }

        return $buf;
    }

    // вернет список родительских категорий
    public function catalog_breadcrumb(\App\Domain\Entities\Catalog\Category $category = null)
    {
        $categories = $this->catalog_category();
        $breadcrumb = [];

        if (!is_null($category)) {
            $breadcrumb[] = $category;

            while($category->parent->toString() != Uuid::NIL) {
                /**
                 * @var \App\Domain\Entities\Catalog\Category $category;
                 */
                $category = $categories->firstWhere('uuid', $category->parent);
                $breadcrumb[] = $category;
            }
        }

        return collect($breadcrumb)->reverse();
    }

    // получение списка товаров
    public function catalog_product($category = null, $order = [], $limit = 10, $offset = null)
    {
        static $buf;

        $criteria = [
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
        ];

        if ($category) {
            if (!is_array($category)) $category = [$category];

            foreach ($category as $value) {
                switch (true) {
                    case \Ramsey\Uuid\Uuid::isValid($value) === true:
                        $criteria['uuid'][] = $value;
                        break;

                    default:
                        $criteria['address'][] = $value;
                        break;
                }
            }
        }

        $key = json_encode($criteria, JSON_UNESCAPED_UNICODE).$limit.$offset;

        if (!isset($buf[$key])) {
            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $repository */
            $repository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);

            $buf[$key] = $limit > 1 ? collect($repository->findBy($criteria, $order, $limit, $offset)) : $repository->findOneBy($criteria, $order);
        }

        return $buf[$key];
    }

    // сохраняет переданный в аргумент uuid товара, если null возвращает список товаров
    public function catalog_product_view(\Ramsey\Uuid\DegradedUuid $uuid = null, $limit = 10)
    {
        $list = $_SESSION['catalog_product_view'] ?? [];

        switch (true) {
            case is_null($uuid) === true:
                return $list;

            case Uuid::isValid($uuid) === true:
                $list[] = $uuid->toString();
                $list = array_unique($list);

                // shift first element
                if (count($list) > $limit) {
                    $list = array_slice($list, 0 - $limit, $limit);
                }

                $_SESSION['catalog_product_view'] = $list;
        }
    }

    // получение заказа
    public function catalog_order($unique)
    {
        static $buf;

        $criteria = [];

        switch (true) {
            case \Ramsey\Uuid\Uuid::isValid($unique) === true:
                $criteria['uuid'] = $unique;
                break;

            default:
                $criteria['serial'] = $unique;
                break;
        }

        $key = json_encode($criteria, JSON_UNESCAPED_UNICODE);

        if (!isset($buf[$key])) {
            /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $repository */
            $repository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Order::class);

            $buf[$key] = collect($repository->findOneBy($criteria));
        }

        return $buf[$key];
    }

    /*
     * trademaster functions
     */

    // tm api
    public function tm_api($endpoint, array $params = [], $method = 'GET')
    {
        return $this->container->get('trademaster')->api([
            'endpoint' => $endpoint,
            'params' => $params,
            'method' => $method,
        ]);
    }
}
