<?php declare(strict_types=1);

namespace App\Application;

use App\Application\Twig\LocaleParser;
use App\Application\Twig\ResourceParser;
use App\Domain\AbstractExtension;
use App\Domain\OAuth\FacebookOAuthProvider;
use App\Domain\OAuth\VKOAuthProvider;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Support\Collection;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\UuidInterface as Uuid;
use Slim\Interfaces\RouteCollectorInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    protected RouteCollectorInterface $routeCollector;

    public function __construct(ContainerInterface $container = null)
    {
        parent::__construct($container);

        $this->routeCollector = $container->get(RouteCollectorInterface::class);
    }

    public function getName()
    {
        return 'wse';
    }

    public function getTokenParsers()
    {
        return [
            new ResourceParser(),
            new LocaleParser(),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('count', [$this, 'count']),
            new TwigFilter('df', [$this, 'df']),
            new TwigFilter('locale', '__', ['is_safe' => ['html']]),
            new TwigFilter('trans', [$this, 'trans'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('current_url', [$this, 'currentUrl']),
            new TwigFunction('parse_url', [$this, 'parseUrl']),

            // slim functions
            new TwigFunction('path_for', [$this, 'pathFor']),
            new TwigFunction('full_url_for', [$this, 'fullUrlFor']),
            new TwigFunction('base_url', [$this, 'baseUrl']),
            new TwigFunction('is_current_path', [$this, 'isCurrentPath']),
            new TwigFunction('current_path', [$this, 'currentPath']),

            // wse functions
            new TwigFunction('_', '__', ['is_safe' => ['html']]),
            new TwigFunction('form', [$this, 'form'], ['is_safe' => ['html']]),
            new TwigFunction('reference', [$this, 'reference']),
            new TwigFunction('parameter', [$this, 'parameter']),
            new TwigFunction('pre', [$this, 'pre']),
            new TwigFunction('count', [$this, 'count']),
            new TwigFunction('df', [$this, 'df']),
            new TwigFunction('collect', [$this, 'collect']),
            new TwigFunction('non_page_path', [$this, 'non_page_path']),
            new TwigFunction('current_page_number', [$this, 'current_page_number']),
            new TwigFunction('current_query', [$this, 'current_query'], ['is_safe' => ['html']]),
            new TwigFunction('is_current_page_number', [$this, 'is_current_page_number']),
            new TwigFunction('build_query', [$this, 'build_query'], ['is_safe' => ['html']]),
            new TwigFunction('base64_encode', [$this, 'base64_encode']),
            new TwigFunction('base64_decode', [$this, 'base64_decode']),
            new TwigFunction('json_encode', [$this, 'json_encode']),
            new TwigFunction('json_decode', [$this, 'json_decode']),
            new TwigFunction('qr_code', [$this, 'qr_code'], ['is_safe' => ['html']]),
            new TwigFunction('oauth_url', [$this, 'oauth_url'], ['is_safe' => ['html']]),

            // files functions
            new TwigFunction('files', [$this, 'files']),

            // publication functions
            new TwigFunction('publication_category', [$this, 'publication_category']),
            new TwigFunction('publication', [$this, 'publication']),

            // guestbook functions
            new TwigFunction('guestbook', [$this, 'guestbook']),

            // catalog functions
            new TwigFunction('catalog_category', [$this, 'catalog_category']),
            new TwigFunction('catalog_category_parents', [$this, 'catalog_category_parents']),
            new TwigFunction('catalog_products', [$this, 'catalog_products']),
            new TwigFunction('catalog_product', [$this, 'catalog_product']),
            new TwigFunction('catalog_product_view', [$this, 'catalog_product_view']),
            new TwigFunction('catalog_order', [$this, 'catalog_order']),
        ];
    }

    protected function currentHost() {
        // from nginx
        if ($_SERVER['HTTP_X_FORWARDED_PROTO'] && $_SERVER['HTTP_X_FORWARDED_HOST']) {
            return $_SERVER['HTTP_X_FORWARDED_PROTO'] . '://' . $_SERVER['HTTP_X_FORWARDED_HOST'];
        }
        // from params
        if (($host = $this->parameter('common_homepage', false))) {
            return $host;
        }
        // from request
        if ($_SERVER['REQUEST_SCHEME'] && $_SERVER['HTTP_HOST']) {
            return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
        }

        return 'http://localhost';
    }

    public function currentUrl(): string
    {
        return $this->currentHost() . $_SERVER['REQUEST_URI'];
    }

    public function parseUrl(): array
    {
        return parse_url($this->currentUrl());
    }

    // slim functions

    public function pathFor($name, $data = [], $queryParams = [])
    {
        return $this->routeCollector->getRouteParser()->urlFor($name, $data, $queryParams);
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
        return $this->currentHost() . $this->pathFor($name, $data, $queryParams);
    }

    // base address without slash in end
    public function baseUrl()
    {
        return rtrim($this->parameter('common_homepage', ''), '/');
    }

    public function isCurrentPath($name, $data = [])
    {
        return $this->routeCollector->pathFor($name, $data) === $this->baseUrl() . '/' . ltrim($this->currentUrl(), '/');
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
        $path = ltrim($this->currentUrl(), '/');

        return $withQueryString || !mb_strrpos($path, '?') ? $path : mb_strstr($path, '?', true);
    }

    // wse functions

    public function trans($obj)
    {
        if (!is_array($obj)) {
            return $obj;
        }

        return $obj[i18n::$localeCode] ?? '';
    }

    public function form($type, $name, $args = [])
    {
        return form($type, $name, $args);
    }

    // todo review this
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
            return $value;
        }
    }

    // return parameter value by key or default
    public function parameter(mixed $key = null, mixed $default = null): mixed
    {
        return parent::parameter($key, $default);
    }

    /**
     * old debug function
     *
     * @param mixed ...$args
     *
     * @deprecated
     * @tracySkipLocation
     */
    public function pre(...$args): void
    {
        foreach ($args as $obj) {
            dump(is_array($obj) || is_object($obj) ? array_serialize($obj) : $obj);
        }
    }

    /**
     * @param $obj
     *
     * @return false|int
     */
    public function count($obj)
    {
        return is_countable($obj) ? count($obj) : false;
    }

    /**
     * Date format function
     *
     * @param DateTime|string $obj
     *
     * @throws Exception
     */
    public function df(mixed $obj = 'now', string $format = null, string $timezone = ''): string
    {
        if (is_string($obj) || is_numeric($obj)) {
            $obj = new DateTime($obj);
        } elseif (is_null($obj)) {
            $obj = new DateTime();
        } else {
            $obj = clone $obj;
        }

        return $obj
            ->setTimezone(new DateTimeZone($timezone ?: $this->parameter('common_timezone', 'UTC')))
            ->format($format ?: $this->parameter('common_date_format', 'j-m-Y, H:i'));
    }

    public function collect(array $array = []): Collection
    {
        return collect($array);
    }

    public function non_page_path(): string
    {
        $url = $this->parseUrl();
        $path = explode('/', ltrim($url['path'], '/'));

        if (($key = count($path) - 1) && ($buf = $path[$key]) && ctype_digit($buf)) {
            unset($path[$key]);
        }

        return '/' . implode('/', $path);
    }

    public function current_page_number(): int
    {
        $url = $this->parseUrl();
        $path = explode('/', ltrim($url['path'], '/'));
        $page = 0;

        if (($key = count($path) - 1) && ($buf = $path[$key]) && ctype_digit($buf)) {
            $page = +$path[$key];
        }

        return $page;
    }

    public function current_query(string $key = null, mixed $value = null): string
    {
        $url = $this->parseUrl();
        $query = [];

        foreach (explode('&', rawurldecode($url['query'] ?? '')) as $fragment) {
            if ($fragment) {
                $buf = explode('=', $fragment);
                $query[$buf[0]] = $buf[1] ?? '';
            }
        }
        if ($key) {
            $query[$key] = $value;
        }

        return $query ? '?' . rawurldecode(http_build_query($query)) : '';
    }

    public function is_current_page_number($number): bool
    {
        return $this->current_page_number() === $number;
    }

    public function build_query($url = '', array $params = []): string
    {
        if (is_array($url)) {
            $params = $url;
            $url = '';
        }

        return $url . '?' . urldecode(http_build_query($params));
    }

    public function base64_encode(string $string): string
    {
        return base64_encode($string);
    }

    public function base64_decode(string $string, bool $strict = false): false|string
    {
        return base64_decode($string, $strict);
    }

    public function json_encode(string $json, ?bool $associative = true, int $depth = 512, int $flags = 0): mixed
    {
        return json_decode($json, $associative, $depth, $flags);
    }

    public function json_decode($value, int $flags = JSON_UNESCAPED_UNICODE, int $depth = 512): false|string
    {
        return json_encode($value, $flags, $depth);
    }

    public function qr_code(mixed $value, $size = 256, $margin = 0): string
    {
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle($size, $margin),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );

        $writer = new \BaconQrCode\Writer($renderer);

        return '<img src="data:image/svg+xml;base64,' . base64_encode($writer->writeString($value)) . '" height="' . $size . '" width="' . $size . '">';
    }

    public function oauth_url(string $provider): string
    {
        switch ($provider) {
            case 'facebook':
                return (new FacebookOAuthProvider($this->container))->getAuthUrl();

            case 'vk':
                return (new VKOAuthProvider($this->container))->getAuthUrl();
        }

        return '';
    }

    // files functions

    // fetch files by args
    public function files(array $criteria = [], $order = [], $limit = 10, $offset = null)
    {
        $fileService = $this->container->get(FileService::class);

        return $fileService->read(array_merge($criteria, [
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }

    // publication functions

    // fetch publication category
    public function publication_category(bool $public = true)
    {
        $publicationCategoryService = $this->container->get(PublicationCategoryService::class);

        return $publicationCategoryService->read([
            'public' => $public ?: null,
        ]);
    }

    // fetch publications by criteria
    public function publication(array $criteria = [], $order = [], $limit = 10, $offset = null)
    {
        $publicationService = $this->container->get(PublicationService::class);

        return $publicationService->read(array_merge($criteria, [
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }

    // guestbook functions

    // fetch guest book rows
    public function guestbook($order = [], $limit = 10, $offset = null)
    {
        $guestBookService = $this->container->get(GuestBookService::class);

        return $guestBookService
            ->read([
                'status' => \App\Domain\Types\GuestBookStatusType::STATUS_WORK,
                'order' => $order,
                'limit' => $limit,
                'offset' => $offset,
            ])
            ->map(function ($model) {
                /** @var \App\Domain\Entities\GuestBook $model */
                $email = explode('@', $model->getEmail());
                $name = implode('@', array_slice($email, 0, count($email) - 1));
                $len = (int) floor(mb_strlen($name) / 2);

                $model->setEmail(mb_substr($name, 0, $len) . str_repeat('*', $len) . '@' . end($email));

                return $model;
            });
    }

    // catalog functions

    // fetch categories list
    public function catalog_category()
    {
        $catalogCategoryService = $this->container->get(CatalogCategoryService::class);

        return $catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
        ]);
    }

    // return parent categories
    public function catalog_category_parents(\App\Domain\Entities\Catalog\Category $category = null)
    {
        $categories = $this->catalog_category();
        $breadcrumb = [];

        if (!is_null($category)) {
            $breadcrumb[] = $category;

            while ($category->getParent()->toString() !== \Ramsey\Uuid\Uuid::NIL) {
                /**
                 * @var \App\Domain\Entities\Catalog\Category;
                 */
                $category = $categories->firstWhere('uuid', $category->getParent());
                $breadcrumb[] = $category;
            }
        }

        return collect($breadcrumb)->reverse();
    }

    // getting a list of products by criteria
    public function catalog_products(array $criteria = [], $order = [], $limit = 10, $offset = null)
    {
        $criteria['status'] = \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK;
        $catalogProductService = $this->container->get(CatalogProductService::class);

        return $catalogProductService->read(array_merge($criteria, ['order' => $order, 'limit' => $limit, 'offset' => $offset]));
    }

    // returns a product or a list of products by criteria
    public function catalog_product(array $criteria = [], $order = [], $limit = 10, $offset = null)
    {
        $criteria['status'] = \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK;
        $catalogProductService = $this->container->get(CatalogProductService::class);

        return $catalogProductService->read(array_merge($criteria, ['order' => $order, 'limit' => $limit, 'offset' => $offset]));
    }

    // save uuid of product in session or return saved list
    public function catalog_product_view($uuid = null, $limit = 10)
    {
        $list = $_SESSION['catalog_product_view'] ?? [];

        switch (true) {
            case is_null($uuid):
                return $list;

            case is_string($uuid) && \Ramsey\Uuid\Uuid::isValid($uuid):
            case is_object($uuid) && is_a($uuid, Uuid::class):
                $list[] = $uuid->toString();
                $list = array_unique($list);

                // shift first element
                if (count($list) > $limit) {
                    $list = array_slice($list, 0 - $limit, $limit);
                }

                $_SESSION['catalog_product_view'] = $list;
        }
    }

    // fetch order
    public function catalog_order(array $criteria = [], $order = [], $limit = 10, $offset = null)
    {
        $catalogOrderService = $this->container->get(CatalogOrderService::class);

        return $catalogOrderService->read(array_merge($criteria, [
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset,
        ]));
    }
}
