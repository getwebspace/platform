<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\Node\Node;

class LocaleNode extends Node
{
    public function __construct(\Twig\Node\Node $body, \Twig\Node\Node $plural = null, \Twig\Node\Expression\AbstractExpression $count = null, \Twig\Node\Node $notes = null, $lineno, $tag = null)
    {
        $nodes = ['body' => $body];

        if (null !== $plural) {
            $nodes['plural'] = $plural;
        }
        if (null !== $count) {
            $nodes['count'] = $count;
        }
        if (null !== $notes) {
            $nodes['notes'] = $notes;
        }

        parent::__construct($nodes, [], $lineno, $tag);
    }

    public function compile(\Twig\Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        [$msg, $vars] = $this->compileString($this->getNode('body'));

        if ($this->hasNode('plural')) {
            [$msg1, $vars1] = $this->compileString($this->getNode('plural'));

            $vars = array_merge($vars, $vars1);
        }

        if ($this->hasNode('notes')) {
            $message = trim($this->getNode('notes')->getAttribute('data'));

            // line breaks are not allowed cause we want a single line comment
            $message = str_replace(["\n", "\r"], ' ', $message);
            $compiler->write("// notes: {$message}\n");
        }

        if ($vars) {
            $compiler
                ->write('echo strtr(__(')
                ->subcompile($msg);

            if ($this->hasNode('plural')) {
                $compiler
                    ->raw(', ')
                    ->subcompile($msg1)
                    ->raw(', abs(')
                    ->subcompile($this->hasNode('count') ? $this->getNode('count') : null)
                    ->raw(')');
            }

            $compiler->raw('), array(');

            foreach ($vars as $var) {
                if ('count' === $var->getAttribute('name')) {
                    $compiler
                        ->string('%count%')
                        ->raw(' => abs(')
                        ->subcompile($this->hasNode('count') ? $this->getNode('count') : null)
                        ->raw('), ');
                } else {
                    $compiler
                        ->string('%' . $var->getAttribute('name') . '%')
                        ->raw(' => ')
                        ->subcompile($var)
                        ->raw(', ');
                }
            }

            $compiler->raw("));\n");
        } else {
            $compiler
                ->write('echo __(')
                ->subcompile($msg);

            if ($this->hasNode('plural')) {
                $compiler
                    ->raw(', ')
                    ->subcompile($msg1)
                    ->raw(', abs(')
                    ->subcompile($this->hasNode('count') ? $this->getNode('count') : null)
                    ->raw(')');
            }

            $compiler->raw(");\n");
        }
    }

    protected function compileString(\Twig\Node\Node $body): array
    {
        if (
            $body instanceof \Twig\Node\Expression\NameExpression ||
            $body instanceof \Twig\Node\Expression\ConstantExpression ||
            $body instanceof \Twig\Node\Expression\TempNameExpression
        ) {
            return [$body, []];
        }

        $vars = [];
        if (count($body)) {
            $msg = '';

            foreach ($body as $node) {
                if (get_class($node) === '\Twig\Node\Node' && $node->getNode(0) instanceof \Twig\Node\SetNode) {
                    $node = $node->getNode(1);
                }

                if ($node instanceof \Twig\Node\PrintNode) {
                    $n = $node->getNode('expr');
                    while ($n instanceof \Twig\Node\Expression\FilterExpression) {
                        $n = $n->getNode('node');
                    }
                    $msg .= sprintf('%%%s%%', $n->getAttribute('name'));
                    $vars[] = new \Twig\Node\Expression\NameExpression($n->getAttribute('name'), $n->getTemplateLine());
                } else {
                    $msg .= $node->getAttribute('data');
                }
            }
        } else {
            $msg = $body->getAttribute('data');
        }

        return [
            new \Twig\Node\Node([
                new \Twig\Node\Expression\ConstantExpression(trim(preg_replace('/\s\s+/', ' ', $msg)), $body->getTemplateLine()),
            ]),
            $vars,
        ];
    }
}
