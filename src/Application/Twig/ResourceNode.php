<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\Compiler;
use Twig\Node\Node;

class ResourceNode extends Node
{
    private static array $storage = [];

    private string $name = '';

    private string $version = '';

    public function __construct($name, $version, $line, $tag = null)
    {
        parent::__construct([], [], $line, $tag);

        $this->name = $name;
        $this->version = $version;
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->raw('echo ')
            ->string($this->resource($this->name, $this->version) . PHP_EOL)
            ->raw(";\n");
    }

    protected function resource($search, $version)
    {
        $cdn = '';
        $search = explode(':', $search);
        $name = $search[0];

        if (!in_array($name, static::$storage, true)) {
            $result = json_decode(file_get_contents('https://api.cdnjs.com/libraries?search=' . $name), true);

            if ($result['total'] >= 1) {
                $index = +array_search($name, array_column($result['results'], 'name'), true);
                $libname = $result['results'][$index]['name'];
                $cdn = file_get_contents('https://api.cdnjs.com/libraries/' . $libname . ($version ? '/' . $version : '') . '?fields=name,version,files');

                static::$storage[$name] = $cdn;
            }
        } else {
            $cdn = static::$storage[$name];
        }

        if ($cdn) {
            $cdn = json_decode($cdn, true);

            if (!in_array($name, $cdn['files'], true)) {
                foreach ($cdn['files'] as $item) {
                    if (str_end_with($item, $search)) {
                        $name = $item;

                        break;
                    }
                }
            }

            $path = 'https://cdnjs.cloudflare.com/ajax/libs/' . $libname . '/' . $version . '/' . $name;

            switch (true) {
                case str_end_with($path, '.js'):
                    return '<script src="' . $path . '"></script>';

                case str_end_with($path, '.css'):
                    return '<link rel="stylesheet" href="' . $path . '">';
            }
        }

        return '';
    }
}
