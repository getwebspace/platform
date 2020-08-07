<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;
use App\Domain\Service\Form\DataService as FormDataService;

class DataViewAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if (
            $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid')) &&
            $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            $data = $this->formDataService->read([
                'uuid' => $this->resolveArg('data'),
            ]);

            if ($data) {
                return $this->respondWithTemplate('cup/form/view/detail.twig', [
                    'item' => $data,
                ]);
            }
        }

        return $this->response->withRedirect('/cup/form');
    }
}
