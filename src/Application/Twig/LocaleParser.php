<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\Error\SyntaxError;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class LocaleParser extends AbstractTokenParser
{
    public function getTag(): string
    {
        return 'locale';
    }

    public function decideForFork(Token $token): bool
    {
        return $token->test(['plural', 'notes', 'endlocale']);
    }

    public function decideForEnd(Token $token): bool
    {
        return $token->test('endlocale');
    }

    public function parse(Token $token): LocaleNode
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $count = null;
        $plural = null;
        $notes = null;

        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            $body = $this->parser->getExpressionParser()->parseExpression();
        } else {
            $stream->expect(Token::BLOCK_END_TYPE);
            $body = $this->parser->subparse([$this, 'decideForFork']);
            $next = $stream->next()->getValue();

            if ('plural' === $next) {
                $count = $this->parser->getExpressionParser()->parseExpression();
                $stream->expect(Token::BLOCK_END_TYPE);
                $plural = $this->parser->subparse([$this, 'decideForFork']);

                if ('notes' === $stream->next()->getValue()) {
                    $stream->expect(Token::BLOCK_END_TYPE);
                    $notes = $this->parser->subparse([$this, 'decideForEnd'], true);
                }
            } elseif ('notes' === $next) {
                $stream->expect(Token::BLOCK_END_TYPE);
                $notes = $this->parser->subparse([$this, 'decideForEnd'], true);
            }
        }

        $stream->expect(Token::BLOCK_END_TYPE);
        $this->checkTransString($body, $lineno);

        return new LocaleNode($body, $plural, $count, $notes, $lineno, $this->getTag());
    }

    /**
     * @throws SyntaxError
     */
    protected function checkTransString(Node $body, $lineno): void
    {
        foreach ($body as $node) {
            if (
                $node instanceof TextNode || (
                    $node instanceof PrintNode && $node->getNode('expr') instanceof NameExpression
                )
            ) {
                continue;
            }

            throw new SyntaxError('The text to be translated with "locale" can only contain references to simple variables', $lineno);
        }
    }
}
