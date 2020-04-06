<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Application\Actions\Action;

class XMLFileAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        $name = $this->resolveArg('name');

        if ($name && in_array($name, ['yml', 'gmf', 'sitemap'], true)) {
            $path = VAR_DIR . '/xml/' . $name . '.xml';

            if (file_exists($path)) {
                return $this->response
                    ->withAddedHeader('Content-type', 'text/xml; charset=utf-8')
                    ->write(file_get_contents(VAR_DIR . '/xml/' . $name . '.xml'));
            }
        }

        return $this->respondRender('p404.twig')->withStatus(404);
    }
}
