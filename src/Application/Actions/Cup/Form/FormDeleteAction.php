<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

use App\Domain\Service\Form\FormService;

class FormDeleteAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $this->formService->delete($this->resolveArg('uuid'));
        }

        return $this->response->withRedirect('/cup/form');
    }
}
