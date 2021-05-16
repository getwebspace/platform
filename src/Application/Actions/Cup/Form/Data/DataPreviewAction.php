<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;

class DataPreviewAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if (
            $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))
            && $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            $data = $this->formDataService->read([
                'uuid' => $this->resolveArg('data'),
            ]);

            if ($data) {
                return $this->response->write($this->renderer->fetchFromString($data->getMessage()));
            }
        }

        return $this->response->withStatus(404);
    }
}
