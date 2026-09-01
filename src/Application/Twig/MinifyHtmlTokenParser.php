<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Parses {% htmlcompress %}...{% endhtmlcompress %}
 *
 * See MinifyHtmlNode for why this is a first-party port.
 */
class MinifyHtmlTokenParser extends AbstractTokenParser
{
    public function decideHtmlCompressEnd(Token $token): bool
    {
        return $token->test('endhtmlcompress');
    }

    public function getTag(): string
    {
        return 'htmlcompress';
    }

    public function parse(Token $token): MinifyHtmlNode
    {
        $lineNumber = $token->getLine();
        $stream = $this->parser->getStream();
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideHtmlCompressEnd'], true);
        $stream->expect(Token::BLOCK_END_TYPE);

        return new MinifyHtmlNode(['body' => $body], [], $lineNumber, $this->getTag());
    }
}
