<?php

namespace Application;


class TwigExtension extends \Slim\Views\TwigExtension
{
    /**
     * @var \Slim\Interfaces\RouterInterface
     */
    private $router;

    /**
     * @var string|\Slim\Http\Uri
     */
    private $uri;

    public function __construct($router, $uri)
    {
        parent::__construct($router, $uri);

        $this->router = $router;
        $this->uri = $uri;
    }

    public function getName()
    {
        return '0x12f';
    }

    public function getFunctions()
    {
        return array_merge(
            parent::getFunctions(),
            [
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

    public function form($type, $name, $args = []) {
        return form($type, $name, $args);
    }

    // todo посмотреть на это решение еще
    public function reference($reference, $value = null) {
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
