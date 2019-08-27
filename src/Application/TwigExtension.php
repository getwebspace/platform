<?php

namespace App\Application;

use Psr\Container\ContainerInterface;
use Slim\Http\Uri;

class TwigExtension extends \Twig\Extension\AbstractExtension
{
    /**
     * @var ContainerInterface
     */
    protected $container;

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
        $this->router = $container->get('router');
        $this->uri = $uri;
    }

    public function getName()
    {
        return '0x12f';
    }

    public function getFunctions()
    {
        return array_merge(
            [
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
                new \Twig\TwigFunction('collect', [$this, 'collect']),
                new \Twig\TwigFunction('non_page_path', [$this, 'non_page_path']),
                new \Twig\TwigFunction('current_page_number', [$this, 'current_page_number']),
                new \Twig\TwigFunction('is_current_page_number', [$this, 'is_current_page_number']),
            ]
        );
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
}
