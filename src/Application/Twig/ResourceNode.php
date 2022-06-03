<?php declare(strict_types=1);

namespace App\Application\Twig;

use App\Domain\Traits\StorageTrait;
use Twig\Compiler;
use Twig\Node\Node;

class ResourceNode extends Node
{
    use StorageTrait;

    private string $name;

    private string $version;

    public function __construct($name, $version, $line, $tag = null)
    {
        parent::__construct([], [], $line, $tag);

        $this->name = $name;
        $this->version = $version;
    }

    public function compile(Compiler $compiler): void
    {
        $resource = $this->getResource($this->name, $this->version);

        if ($resource) {
            $compiler
                ->addDebugInfo($this)
                ->raw('echo ')
                ->string($resource)
                ->raw(";\n");
        }
    }

    protected function getResource($search, $version): ?string
    {
        $cdn = null;
        $search = explode(':', $search);
        $name = $search[0];

        if (!static::hasStorage($name, true)) {
            $result = @file_get_contents('https://api.cdnjs.com/libraries?search=' . $name);

            if ($result) {
                $result = json_decode($result, true);

                if ($result && $result['total'] >= 1) {
                    $index = +array_search($name, array_column($result['results'], 'name'), true);
                    $result = @file_get_contents('https://api.cdnjs.com/libraries/' . $result['results'][$index]['name'] . '/' . $version . '?fields=name,version,files');

                    if ($result) {
                        $cdn = static::setStorage($name, $result);
                    }
                }
            }
        } else {
            $cdn = static::getStorage($name);
        }

        if ($cdn) {
            $cdn = json_decode($cdn, true);
            $libname = $cdn['name'];

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

        return null;
    }
}
