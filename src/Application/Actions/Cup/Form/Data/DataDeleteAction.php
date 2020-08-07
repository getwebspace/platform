<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;
use App\Domain\Service\Form\DataService as FormDataService;

class DataDeleteAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if (
            $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid')) &&
            $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            $this->formDataService->delete($this->resolveArg('data'));
        }

        return $this->response->withRedirect('/cup/form/' . $this->resolveArg('uuid') . '/view');
    }
}
