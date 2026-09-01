<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\Compiler;
use Twig\Node\Node;

/**
 * Compiled body of {% htmlcompress %}...{% endhtmlcompress %}
 *
 * Ported from voku/html-compress-twig (abandoned, pins voku/html-min to
 * ~4.4 - the last release before it fixed a PHP 8.5 deprecation) so the
 * project can move html-min to ^5.0 on its own. Behaviour is unchanged;
 * only the extension it calls back into moved to TwigExtension.
 */
class MinifyHtmlNode extends Node
{
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write("ob_start();\n")
            ->subcompile($this->getNode('body'))
            ->write('$extension = $this->env->getExtension(\'' . \App\Application\TwigExtension::class . '\');' . "\n")
            ->write('echo $extension->compress($this->env, ob_get_clean());' . "\n");
    }
}
