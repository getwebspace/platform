<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\Node\Node;

class LocaleNode extends Node
{
    public function __construct(\Twig\Node\Node $body, $lineno, $tag = null)
    {
        parent::__construct(['string' => $body], [], $lineno, $tag);
    }

    public function compile(\Twig\Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('echo __(')
            ->string(trim(preg_replace('/\s\s+/', ' ', $this->getNode('string')->getAttribute('data'))))
            ->raw(");\n");
    }
}
