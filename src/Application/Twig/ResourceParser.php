<?php declare(strict_types=1);

namespace App\Application\Twig;

use Twig\TokenParser\AbstractTokenParser;

class ResourceParser extends AbstractTokenParser
{
    public function parse(\Twig\Token $token)
    {
        $parser = $this->parser;
        $stream = $parser->getStream();

        $name = $stream->expect(\Twig\Token::STRING_TYPE)->getValue();
        $version = $stream->expect(\Twig\Token::STRING_TYPE)->getValue();
        $stream->expect(\Twig\Token::BLOCK_END_TYPE);

        return new ResourceNode($name, $version, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'resource';
    }
}
