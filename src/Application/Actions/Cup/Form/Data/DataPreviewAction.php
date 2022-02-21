<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;

class DataPreviewAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if (
            $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))
            && $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            $data = $this->formDataService->read([
                'uuid' => $this->resolveArg('data'),
            ]);

            if ($data) {
                $this->response->getBody()->write($this->renderer->fetchFromString($data->getMessage()));

                return $this->response;
            }
        }

        return $this->response->withStatus(404);
    }
}
