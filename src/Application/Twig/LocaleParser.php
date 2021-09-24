<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\TokenParser\AbstractTokenParser;

class LocaleParser extends AbstractTokenParser
{
    public function getTag()
    {
        return 'locale';
    }

    public function decideForEnd(\Twig\Token $token)
    {
        return $token->test('endlocale');
    }

    public function parse(\Twig\Token $token)
    {
        $parser = $this->parser;
        $stream = $parser->getStream();

        $stream->expect(\Twig\Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideForEnd'], true);
        $stream->expect(\Twig\Token::BLOCK_END_TYPE);

        return new LocaleNode($body, $token->getLine(), $this->getTag());
    }
}
