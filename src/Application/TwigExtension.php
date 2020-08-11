<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\AbstractExtension;
use App\Domain\Service\Catalog\CategoryService as CatalogCatalogService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use DateTime;
use DateTimeZone;
use Exception;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Slim\Http\Uri;
use Tightenco\Collect\Support\Collection;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    /**
     * @var \Slim\Interfaces\RouterInterface
     */
    protected $router;

    /**
     * @var \Slim\Http\Uri|string
     */
    protected $uri;

    public function __construct(ContainerInterface $container, $uri)
    {
        parent::__construct($container);

        $this->router = $container->get('router');
        $this->uri = $uri;
    }

    public function getName()
    {
        return 'wse';
    }

    public function getFilters()
    {
        return [
            new TwigFilter('count', [$this, 'count']),
            new TwigFilter('df', [$this, 'df']),
        ];
    }

    public function getFunctions()
    {
        return [
            // slim functions
            new TwigFunction('path_for', [$this, 'pathFor']),
            new TwigFunction('full_url_for', [$this, 'fullUrlFor']),
            new TwigFunction('base_url', [$this, 'baseUrl']),
            new TwigFunction('is_current_path', [$this, 'isCurrentPath']),
            new TwigFunction('current_path', [$this, 'currentPath']),

            // wse functions
            new TwigFunction('form', [$this, 'form'], ['is_safe' => ['html']]),
            new TwigFunction('reference', [$this, 'reference']),
            new TwigFunction('parameter', [$this, 'parameter']),
            new TwigFunction('pre', [$this, 'pre']),
            new TwigFunction('dump', [$this, 'dump']),
            new TwigFunction('dumpe', [$this, 'dumpe']),
            new TwigFunction('count', [$this, 'count']),
            new TwigFunction('df', [$this, 'df']),
            new TwigFunction('collect', [$this, 'collect']),
            new TwigFunction('non_page_path', [$this, 'non_page_path']),
            new TwigFunction('current_page_number', [$this, 'current_page_number']),
            new TwigFunction('current_query', [$this, 'current_query'], ['is_safe' => ['html']]),
            new TwigFunction('is_current_page_number', [$this, 'is_current_page_number']),
            new TwigFunction('qr_code', [$this, 'qr_code'], ['is_safe' => ['html']]),

            // files functions
            new TwigFunction('files', [$this, 'files']),

            // publication functions
            new TwigFunction('publication', [$this, 'publication']),
            new TwigFunction('publication_category', [$this, 'publication_category']),

            // guestbook functions
            new TwigFunction('guestbook', [$this, 'guestbook']),

            // catalog functions
            new TwigFunction('catalog_category', [$this, 'catalog_category']),
            new TwigFunction('catalog_breadcrumb', [$this, 'catalog_breadcrumb']),
            new TwigFunction('catalog_products', [$this, 'catalog_products']),
            new TwigFunction('catalog_product', [$this, 'catalog_product']),
            new TwigFunction('catalog_product_view', [$this, 'catalog_product_view']),
            new TwigFunction('catalog_order', [$this, 'catalog_order']),

            // trademaster
            new TwigFunction('tm_api', [$this, 'tm_api']),
        ];
    }

    // slim functions

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

        // @var Uri $uri
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

    // базовый адрес без слеша в конце
    public function baseUrl()
    {
        return rtrim($this->parameter('common_homepage', ''), '/');
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

    // wse functions

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
                    if (is_array($reference)) {
                        return array_combine(array_values($reference), array_values($reference));
                    }

                    return $reference;

                default:
                    return $reference[$value];
            }
        } catch (Exception $e) {
            // todo nothing
        }

        return $value;
    }

    // возвращает значение параметра
    public function parameter($key = null, $default = null)
    {
        return parent::parameter($key, $default);
    }

    /**
     * Old debug function
     *
     * @param mixed ...$args
     */
    public function pre(...$args): void
    {
        call_user_func_array('pre', $args);
    }

    /**
     * New debug function
     *
     * @param mixed ...$args
     */
    public function dump(...$args): void
    {
        call_user_func_array('dump', $args);
    }

    /**
     * New debug function & exit shortcut.
     *
     * @param mixed ...$args
     */
    public function dumpe(...$args): void
    {
        call_user_func_array('dumpe', $args);
    }

    public function count($obj)
    {
        return is_countable($obj) ? count($obj) : false;
    }

    /**
     * Date format function
     *
     * @param DateTime|string $obj
     * @param null|string     $format
     * @param string          $timezone
     *
     * @throws Exception
     *
     * @return string
     */
    public function df($obj = 'now', $format = null, $timezone = '')
    {
        if (is_string($obj) || is_numeric($obj)) {
            $obj = new DateTime($obj);
        } elseif (is_null($obj)) {
            $obj = new DateTime();
        } else {
            $obj = clone $obj;
        }

        return $obj
            ->setTimezone(new DateTimeZone($timezone ? $timezone : $this->parameter('common_timezone', 'UTC')))
            ->format($format ? $format : $this->parameter('common_date_format', 'j-m-Y, H:i'));
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

    public function current_query($key = null, $value = null)
    {
        $query = [];

        foreach (explode('&', rawurldecode($this->uri->getQuery())) as $fragment) {
            if ($fragment) {
                $buf = explode('=', $fragment);
                $query[$buf[0]] = $buf[1];
            }
        }
        if ($key) {
            $query[$key] = $value;
        }

        return $query ? '?' . http_build_query($query) : '';
    }

    public function is_current_page_number($number)
    {
        return $this->current_page_number() === $number;
    }

    public function qr_code($value, $width = 256, $height = 256)
    {
        $renderer = new \BaconQrCode\Renderer\Image\Png();
        $renderer->setWidth($width);
        $renderer->setHeight($height);

        $writer = new \BaconQrCode\Writer($renderer);

        return '<img src="data:image/png;base64,' . base64_encode($writer->writeString($value)) . '" height="' . $height . '" width="' . $width . '">';
    }

    // files functions

    // получает файлы по параметрам
    public function files($files = [])
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:files', $files);

        $criteria = [];

        if ($files) {
            if (!is_a($files, \Tightenco\Collect\Support\Collection::class) && !is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $uuid) {
                if (\Ramsey\Uuid\Uuid::isValid($uuid) === true) {
                    $criteria['uuid'][] = $uuid;
                }
            }
        }

        $fileService = FileService::getWithContainer($this->container);
        $result = $fileService->read($criteria);

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:files', $files);

        return $result;
    }

    // publication functions

    // получение списка категорий публикаций
    public function publication_category($unique = null)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:publication_category');

        static $categories;

        if (!$categories) {
            $publicationCategoryService = PublicationCategoryService::getWithContainer($this->container);
            $categories = $publicationCategoryService->read();
        }

        static $buf;

        if (is_null($unique)) {
            return $categories->where('public', true);
        }
        if (is_string($unique)) {
            $unique = \Ramsey\Uuid\Uuid::fromString($unique);
        }
        if (!array_key_exists($unique, (array) $buf)) {
            $uuids = $categories->firstWhere('uuid', $unique)->getNested($categories)->pluck('uuid')->all();
            $buf[strval($unique)] = $categories->whereIn('uuid', $uuids, false);
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:publication_category');

        return $buf[strval($unique)];
    }

    // получение списка публикаций
    public function publication($data = null, $order = [], $limit = 10, $offset = null)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:publication');

        static $buf;

        $criteria = [];

        if ($data) {
            if (!is_array($data)) {
                $data = [$data];
            }
            $data = array_merge_recursive(['uuid' => [], 'address' => [], 'category' => []], $data);

            foreach ($data as $type => $values) {
                if (is_a($values, Collection::class)) {
                    $values = $values->all();
                }
                if (!is_array($data)) {
                    $values = [$values];
                }

                foreach ($values as $value) {
                    switch ($type) {
                        case 'uuid':
                            if (\Ramsey\Uuid\Uuid::isValid(strval($value)) === true) {
                                $criteria['uuid'][] = $value;
                            }

                            break;
                        case 'category':
                            if (is_object($value) && is_a($value, \App\Domain\Entities\Publication\Category::class)) {
                                $criteria['category'][] = $value->getUuid();
                            } else {
                                if (\Ramsey\Uuid\Uuid::isValid(strval($value)) === true) {
                                    $criteria['category'][] = $value;
                                }
                            }

                            break;
                        case 'address':
                            $criteria['address'][] = $value;
                    }
                }
            }
        }

        $key = json_encode($criteria, JSON_UNESCAPED_UNICODE) . $limit . $offset;

        if (!isset($buf[$key])) {
            $publicationService = PublicationService::getWithContainer($this->container);
            $buf[$key] = $publicationService->read(array_merge($criteria, [
                'order' => $order,
                'limit' => $limit,
                'offset' => $offset,
            ]));
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:publication');

        return $buf[$key];
    }

    // guestbook functions

    // получение списка записей в гостевой книге
    public function guestbook($order = [], $limit = 10, $offset = null)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:guestbook');

        static $buf;

        $key = json_encode($order, JSON_UNESCAPED_UNICODE) . $limit . $offset;

        if (!$buf) {
            $guestBookService = GuestBookService::getWithContainer($this->container);
            $buf[$key] = $guestBookService
                ->read([
                    'status' => \App\Domain\Types\GuestBookStatusType::STATUS_WORK,
                    'order' => $order,
                    'limit' => $limit,
                    'offset' => $offset,
                ])
                ->map(function ($el) {
                    if ($el->email) {
                        $em = explode('@', $el->email);
                        $name = implode(array_slice($em, 0, count($em) - 1), '@');
                        $len = floor(mb_strlen($name) / 2);

                        $el->email = mb_substr($name, 0, $len) . str_repeat('*', $len) . '@' . end($em);
                    }

                    return $el;
                });
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:guestbook');

        return $buf[$key];
    }

    // catalog functions

    // получение списка категорий товаров
    public function catalog_category()
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:catalog_category');

        static $buf;

        if (!$buf) {
            $catalogCategoryService = CatalogCatalogService::getWithContainer($this->container);
            $buf = $catalogCategoryService->read([
                'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            ]);
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:catalog_category');

        return $buf;
    }

    // вернет список родительских категорий
    public function catalog_breadcrumb(\App\Domain\Entities\Catalog\Category $category = null)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:catalog_breadcrumb');

        $categories = $this->catalog_category();
        $breadcrumb = [];

        if (!is_null($category)) {
            $breadcrumb[] = $category;

            while ($category->getParent()->toString() !== Uuid::NIL) {
                /**
                 * @var \App\Domain\Entities\Catalog\Category;
                 */
                $category = $categories->firstWhere('uuid', $category->getParent());
                $breadcrumb[] = $category;
            }
        }

        $result = collect($breadcrumb)->reverse();

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:catalog_breadcrumb');

        return $result;
    }

    // получение списка товаров по category_uuid
    public function catalog_products($unique, $order = [], $limit = 10, $offset = null)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:catalog_products');

        static $buf;

        $criteria = [
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
        ];

        if (!is_array($unique)) {
            $unique = [$unique];
        }

        foreach ($unique as $value) {
            switch (true) {
                case \Ramsey\Uuid\Uuid::isValid($value) === true:
                    $criteria['category'][] = $value;

                    break;

                case is_numeric($value) === true:
                    $criteria['external_id'][] = $value;

                    break;
            }
        }

        $key = json_encode($criteria, JSON_UNESCAPED_UNICODE) . $limit . $offset;

        if (!array_key_exists($key, (array) $buf)) {
            $catalogProductService = CatalogProductService::getWithContainer($this->container);
            $buf[$key] = $catalogProductService->read(array_merge($criteria, ['order' => $order, 'limit' => $limit, 'offset' => $offset]));
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:catalog_products (%s)', $key);

        return $buf[$key];
    }

    // получение списка товаров по uuid, external_id или address
    public function catalog_product($unique = null, $order = [], $limit = 10, $offset = null)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:catalog_product');

        static $buf;

        $criteria = [
            'status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK,
        ];

        if ($unique) {
            if (!is_array($unique)) {
                $unique = [$unique];
            }

            foreach ($unique as $value) {
                switch (true) {
                    case \Ramsey\Uuid\Uuid::isValid($value) === true:
                        $criteria['uuid'][] = $value;

                        break;

                    case is_numeric($value) === true:
                        $criteria['external_id'][] = $value;

                        break;

                    default:
                        $criteria['address'][] = $value;

                        break;
                }
            }
        }

        $key = json_encode($criteria, JSON_UNESCAPED_UNICODE) . $limit . $offset;

        if (!array_key_exists($key, (array) $buf)) {
            $catalogProductService = CatalogProductService::getWithContainer($this->container);
            $buf[$key] = $catalogProductService->read(array_merge($criteria, ['order' => $order, 'limit' => $limit, 'offset' => $offset]));
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:catalog_product (%s)', $key);

        return $buf[$key];
    }

    // сохраняет переданный в аргумент uuid товара, если null возвращает список товаров
    public function catalog_product_view(\Ramsey\Uuid\UuidInterface $uuid = null, $limit = 10)
    {
        $list = $_SESSION['catalog_product_view'] ?? [];

        switch (true) {
            case is_null($uuid):
                return $list;

            case Uuid::isValid($uuid):
                $list[] = $uuid->toString();
                $list = array_unique($list);

                // shift first element
                if (count($list) > $limit) {
                    $list = array_slice($list, 0 - $limit, $limit);
                }

                $_SESSION['catalog_product_view'] = $list;

                return true;
        }

        return null;
    }

    // получение заказа
    public function catalog_order($unique)
    {
        \RunTracy\Helpers\Profiler\Profiler::start('twig:fn:catalog_order');

        static $buf;

        $criteria = [];

        switch (true) {
            case \Ramsey\Uuid\Uuid::isValid($unique):
                $criteria['uuid'] = $unique;

                break;

            case is_numeric($unique):
                $criteria['external_id'] = $unique;

                break;

            default:
                $criteria['serial'] = $unique;

                break;
        }

        $key = json_encode($criteria, JSON_UNESCAPED_UNICODE);

        if (!array_key_exists($key, (array) $buf)) {
            $catalogOrderService = CatalogOrderService::getWithContainer($this->container);
            $buf[$key] = $catalogOrderService->read($criteria);
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('twig:fn:catalog_order (%s)', $key);

        return $buf[$key];
    }
}
