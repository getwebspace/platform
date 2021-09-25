<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\TokenParser\AbstractTokenParser;

class LocaleParser extends AbstractTokenParser
{
    public function getTag()
    {
        return 'locale';
    }

    public function decideForFork(\Twig\Token $token)
    {
        return $token->test(['plural', 'notes', 'endlocale']);
    }

    public function decideForEnd(\Twig\Token $token)
    {
        return $token->test('endlocale');
    }

    public function parse(\Twig\Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $count = null;
        $plural = null;
        $notes = null;

        if (!$stream->test(\Twig\Token::BLOCK_END_TYPE)) {
            $body = $this->parser->getExpressionParser()->parseExpression();
        } else {
            $stream->expect(\Twig\Token::BLOCK_END_TYPE);
            $body = $this->parser->subparse([$this, 'decideForFork']);
            $next = $stream->next()->getValue();

            if ('plural' === $next) {
                $count = $this->parser->getExpressionParser()->parseExpression();
                $stream->expect(\Twig\Token::BLOCK_END_TYPE);
                $plural = $this->parser->subparse([$this, 'decideForFork']);

                if ('notes' === $stream->next()->getValue()) {
                    $stream->expect(\Twig\Token::BLOCK_END_TYPE);
                    $notes = $this->parser->subparse([$this, 'decideForEnd'], true);
                }
            } elseif ('notes' === $next) {
                $stream->expect(\Twig\Token::BLOCK_END_TYPE);
                $notes = $this->parser->subparse([$this, 'decideForEnd'], true);
            }
        }

        $stream->expect(\Twig\Token::BLOCK_END_TYPE);
        $this->checkTransString($body, $lineno);

        return new LocaleNode($body, $plural, $count, $notes, $lineno, $this->getTag());
    }

    protected function checkTransString(\Twig\Node\Node $body, $lineno)
    {
        foreach ($body as $node) {
            if (
                $node instanceof \Twig\Node\TextNode || (
                    $node instanceof \Twig\Node\PrintNode &&
                    $node->getNode('expr') instanceof \Twig\Node\Expression\NameExpression
                )
            ) {
                continue;
            }

            throw new \Twig\Error\SyntaxError(sprintf('The text to be translated with "locale" can only contain references to simple variables'), $lineno);
        }
    }
}
