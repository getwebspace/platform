<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;

class XMLFileAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $name = $this->resolveArg('name');

        if (in_array($name, ['yml', 'gmf', 'sitemap'], true)) {
            $path = VAR_DIR . '/xml/' . $name . '.xml';

            if (file_exists($path)) {
                $this->response->getBody()->write(file_get_contents(VAR_DIR . '/xml/' . $name . '.xml'));

                return $this->response->withAddedHeader('Content-type', 'text/xml; charset=utf-8');
            }
        }

        return $this->respond('p404.twig')->withStatus(404);
    }
}
