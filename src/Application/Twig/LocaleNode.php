<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\TempNameExpression;
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\Node\SetNode;

class LocaleNode extends Node
{
    public function __construct(Node $body, ?Node $plural = null, ?AbstractExpression $count = null, ?Node $notes = null, $lineno = 0, $tag = null)
    {
        $nodes = ['body' => $body];

        if ($plural !== null) {
            $nodes['plural'] = $plural;
        }
        if ($count !== null) {
            $nodes['count'] = $count;
        }
        if ($notes !== null) {
            $nodes['notes'] = $notes;
        }

        parent::__construct($nodes, [], $lineno, $tag);
    }

    public function compile(Compiler $compiler): void
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

    protected function compileString(Node $body): array
    {
        if ($body instanceof NameExpression || $body instanceof ConstantExpression || $body instanceof TempNameExpression) {
            return [$body, []];
        }

        $vars = [];
        if (count($body)) {
            $msg = '';

            foreach ($body as $node) {
                if (get_class($node) === Node::class && $node->getNode(0) instanceof SetNode) {
                    $node = $node->getNode(1);
                }

                if ($node instanceof PrintNode) {
                    $n = $node->getNode('expr');
                    while ($n instanceof FilterExpression) {
                        $n = $n->getNode('node');
                    }
                    $msg .= sprintf('%%%s%%', $n->getAttribute('name'));
                    $vars[] = new NameExpression($n->getAttribute('name'), $n->getTemplateLine());
                } else {
                    $msg .= $node->getAttribute('data');
                }
            }
        } else {
            $msg = $body->getAttribute('data');
        }

        return [
            new Node([
                new ConstantExpression(trim(preg_replace('/\s\s+/', ' ', $msg)), $body->getTemplateLine()),
            ]),
            $vars,
        ];
    }
}
